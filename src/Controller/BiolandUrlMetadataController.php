<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\BiolandUrlMetadata;
use Drupal\bioland\BiolandUrlScreenshotPolicy;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
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
 * pinned to the checked address (CURLOPT_RESOLVE, no DNS rebinding window),
 * redirects are followed by hand and re-checked, and the body is capped.
 * Access is the node-form permission plus a CSRF token (bioland.routing.yml),
 * and flood control caps lookups per user.
 */
class BiolandUrlMetadataController implements ContainerInjectionInterface {

  const MAX_REDIRECTS = 3;
  const MAX_BYTES = 524288;
  const ABORT_BYTES = 2097152;
  const FLOOD_EVENT = 'bioland.url_metadata';
  const FLOOD_LIMIT = 30;
  const FLOOD_WINDOW = 60;

  protected ClientInterface $httpClient;
  protected FloodInterface $flood;

  /**
   * @var callable(string): string[]
   */
  protected $resolver;

  public function __construct(ClientInterface $httpClient, FloodInterface $flood, ?callable $resolver = NULL) {
    $this->httpClient = $httpClient;
    $this->flood = $flood;
    $this->resolver = $resolver ?: [static::class, 'resolveHost'];
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('http_client'), $container->get('flood'));
  }

  /**
   * Route callback: GET /bioland/url-metadata?url=...
   */
  public function lookup(Request $request): JsonResponse {
    $url = trim((string) $request->query->get('url', ''));
    if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
      return $this->json(['error' => 'invalid_url'], 400);
    }
    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW)) {
      return $this->json(['error' => 'rate_limited'], 429);
    }
    $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

    try {
      return $this->json($this->fetch($url), 200);
    }
    catch (\Throwable $e) {
      // The editor just keeps typing by hand; nothing to surface or log.
      return $this->json(['error' => 'unreachable'], 502);
    }
  }

  /**
   * Fetches $url (following guarded redirects) and parses its metadata.
   *
   * @return array{title: string, description: string}
   */
  protected function fetch(string $url): array {
    for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
      if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
        throw new \RuntimeException('Refused URL.');
      }
      $parts = parse_url($url);
      $host = strtolower(trim($parts['host'], '[]'));
      $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
      $addresses = ($this->resolver)($host);
      if ($addresses === [] || array_filter($addresses, fn($ip) => !BiolandUrlMetadata::isPublicIp($ip)) !== []) {
        throw new \RuntimeException('Refused address.');
      }
      $ip = strpos($addresses[0], ':') !== FALSE ? '[' . $addresses[0] . ']' : $addresses[0];

      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 6,
        'connect_timeout' => 3,
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        // Not 'stream': that swaps in Guzzle's PHP-stream handler, which
        // ignores CURLOPT_RESOLVE and would reopen the DNS rebinding window.
        'progress' => static function ($total, $downloaded): void {
          if ($downloaded > self::ABORT_BYTES) {
            throw new \RuntimeException('Response too large.');
          }
        },
        'headers' => ['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => 'Bioland link preview'],
        'curl' => [CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip]],
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
