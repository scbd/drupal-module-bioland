<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\BiolandUrlMetadata;
use Drupal\bioland\BiolandUrlRefusedException;
use Drupal\bioland\BiolandUrlScreenshotPolicy;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * BL-1191: looks up a Related websites URL's title and description.
 *
 * The node form calls this when the editor leaves the URL field, because a
 * browser cannot read another site's HTML (CORS). It is an editor-typed URL
 * fetched server side, so every hop is SSRF-guarded: http(s) only, no
 * credentials, every resolved address must be public, the connection is
 * pinned to the checked address (CURLOPT_RESOLVE, no proxy, no DNS rebinding
 * window), redirects are followed by hand and re-checked, the whole chain
 * shares one deadline, and the body is capped. Access is the node-form
 * permission plus a CSRF token (bioland.routing.yml), flood control caps
 * lookups per user, and refused addresses are logged.
 */
class BiolandUrlMetadataController implements ContainerInjectionInterface {

  const MAX_REDIRECTS = 3;
  const MAX_BYTES = 524288;
  const ABORT_BYTES = 2097152;
  const TOTAL_TIMEOUT = 6.0;
  const FLOOD_EVENT = 'bioland.url_metadata';
  const FLOOD_LIMIT = 30;
  const FLOOD_WINDOW = 60;

  protected ClientInterface $httpClient;
  protected FloodInterface $flood;
  protected AccountInterface $currentUser;
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * @var callable(string): string[]
   */
  protected $resolver;

  public function __construct(ClientInterface $httpClient, FloodInterface $flood, AccountInterface $currentUser, LoggerChannelFactoryInterface $loggerFactory, ?callable $resolver = NULL) {
    $this->httpClient = $httpClient;
    $this->flood = $flood;
    $this->currentUser = $currentUser;
    $this->loggerFactory = $loggerFactory;
    $this->resolver = $resolver ?: [static::class, 'resolveHost'];
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('flood'),
      $container->get('current_user'),
      $container->get('logger.factory')
    );
  }

  /**
   * Route callback: GET /bioland/url-metadata?url=...
   */
  public function lookup(Request $request): JsonResponse {
    $url = trim((string) $request->query->get('url', ''));
    if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
      return $this->json(['error' => 'invalid_url'], 400);
    }
    // Per account, not per IP: editors behind one NAT share an address.
    $identifier = 'uid:' . $this->currentUser->id();
    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $identifier)) {
      return $this->json(['error' => 'rate_limited'], 429);
    }
    $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW, $identifier);

    try {
      return $this->json($this->fetch($url), 200);
    }
    catch (BiolandUrlRefusedException $e) {
      // A refused address can be an SSRF probe: record who and which host,
      // never the query string or any body.
      $this->loggerFactory->get('bioland')->notice('URL metadata lookup refused for uid @uid, host @host.', [
        '@uid' => $this->currentUser->id(),
        '@host' => $e->getMessage(),
      ]);
      return $this->json(['error' => 'unreachable'], 502);
    }
    catch (\Throwable $e) {
      // Unreachable or not HTML: the editor just keeps typing by hand.
      return $this->json(['error' => 'unreachable'], 502);
    }
  }

  /**
   * Fetches $url (following guarded redirects) and parses its metadata.
   *
   * @return array{title: string, description: string}
   */
  protected function fetch(string $url): array {
    $deadline = microtime(TRUE) + self::TOTAL_TIMEOUT;
    for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
      if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
        throw new BiolandUrlRefusedException((string) parse_url($url, PHP_URL_HOST));
      }
      $parts = parse_url($url);
      $host = strtolower(trim($parts['host'], '[]'));
      $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
      $addresses = ($this->resolver)($host);
      if ($addresses === [] || array_filter($addresses, fn($ip) => !BiolandUrlMetadata::isPublicIp($ip)) !== []) {
        throw new BiolandUrlRefusedException($host);
      }
      $remaining = $deadline - microtime(TRUE);
      if ($remaining <= 0) {
        throw new \RuntimeException('Lookup deadline spent.');
      }
      $ip = strpos($addresses[0], ':') !== FALSE ? '[' . $addresses[0] . ']' : $addresses[0];

      $response = $this->httpClient->request('GET', $url, [
        'timeout' => max(0.5, $remaining),
        'connect_timeout' => min(3, max(0.5, $remaining)),
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        // An environment proxy would resolve the host itself and bypass the pin.
        'proxy' => '',
        'headers' => ['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => 'Bioland link preview'],
        // Not 'stream': that swaps in Guzzle's PHP-stream handler, which
        // ignores CURLOPT_RESOLVE and would reopen the DNS rebinding window.
        'curl' => [
          CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
          CURLOPT_PROXY => '',
          CURLOPT_NOPROXY => '*',
          CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
          CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
          // Abort oversized bodies inside curl: a declared or streamed size
          // past the cap stops the transfer.
          CURLOPT_MAXFILESIZE => self::ABORT_BYTES,
          CURLOPT_NOPROGRESS => FALSE,
          // CURLOPT_XFERINFOFUNCTION arrived in PHP 8.2; 8.1 has the older
          // progress callback with the same signature.
          (defined('CURLOPT_XFERINFOFUNCTION') ? CURLOPT_XFERINFOFUNCTION : CURLOPT_PROGRESSFUNCTION) => [static::class, 'abortOversized'],
        ],
      ]);

      $status = $response->getStatusCode();
      if ($status >= 300 && $status < 400 && $response->getHeaderLine('Location') !== '') {
        $url = BiolandUrlMetadata::resolveRedirect($url, $response->getHeaderLine('Location'));
        continue;
      }
      $type = $response->getHeaderLine('Content-Type');
      if ($status !== 200 || stripos($type, 'html') === FALSE) {
        throw new \RuntimeException('Not an HTML page.');
      }

      $body = $response->getBody();
      $html = '';
      while (!$body->eof() && strlen($html) < self::MAX_BYTES) {
        $html .= $body->read(8192);
      }
      return BiolandUrlMetadata::parse($html, BiolandUrlMetadata::detectCharset($type));
    }
    throw new \RuntimeException('Too many redirects.');
  }

  /**
   * Curl transfer callback: a non-zero return aborts the transfer.
   */
  public static function abortOversized($handle, $downloadTotal, $downloaded, $uploadTotal, $uploaded): int {
    return ($downloadTotal > self::ABORT_BYTES || $downloaded > self::ABORT_BYTES) ? 1 : 0;
  }

  /**
   * Every IPv4 and IPv6 address a host resolves to; an IP literal is itself.
   *
   * @return string[]
   */
  public static function resolveHost(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP) !== FALSE) {
      return [$host];
    }
    $addresses = gethostbynamel($host) ?: [];
    foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
      if (!empty($record['ipv6'])) {
        $addresses[] = $record['ipv6'];
      }
    }
    return array_values(array_unique($addresses));
  }

  protected function json(array $data, int $status): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

}
