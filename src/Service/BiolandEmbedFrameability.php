<?php

namespace Drupal\bioland\Service;

use Drupal\bioland\BiolandUrlMetadata;
use Drupal\bioland\BiolandUrlScreenshotPolicy;
use Drupal\bioland\Controller\BiolandUrlMetadataController;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * BL-1273: checks that an embed URL's page allows being framed on this site.
 *
 * A page that answers with CSP frame-ancestors or X-Frame-Options blocking
 * this origin saves fine and renders as an empty box. The URL is fetched
 * server side (headers only), so every hop is SSRF-guarded like
 * BiolandUrlMetadataController; any failure to verify blocks the save.
 */
class BiolandEmbedFrameability {

  const MAX_REDIRECTS = 5;
  const MAX_HEADER_ECHO = 200;
  const CACHE_SECONDS = 3600;
  const USER_AGENT = 'Mozilla/5.0 (compatible; BiolandEmbedCheck/1.0)';

  /**
   * @var callable(string): string[]
   */
  protected $resolver;

  public function __construct(
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cache,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected RequestStack $requestStack,
    ?callable $resolver = NULL,
  ) {
    $this->resolver = $resolver ?: [BiolandUrlMetadataController::class, 'resolveHost'];
  }

  /**
   * The translated form error for an embed URL, or NULL when it can be framed.
   */
  public function error(string $url): ?TranslatableMarkup {
    $verdict = $this->verdict(trim($url));
    return match ($verdict['status']) {
      'refused' => new TranslatableMarkup('This page cannot be embedded: @host sends @header which blocks framing on this site. Link to it instead, or host a copy you control.', ['@host' => $verdict['host'], '@header' => $verdict['header']]),
      'unverified' => new TranslatableMarkup('The URL @host could not be reached to verify that it can be embedded. Try again later, or link to it instead.', ['@host' => $verdict['host']]),
      default => NULL,
    };
  }

  /**
   * The verdict for a URL (cached unless unverified): status ok, refused or unverified.
   *
   * @return array{status: string, host: string, header: string}
   */
  public function verdict(string $url): array {
    $origin = $this->requestStack->getCurrentRequest()?->getSchemeAndHttpHost() ?? '';
    $cid = 'bioland_embed_frameability:' . hash('sha256', $url . '|' . $origin);
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }
    $verdict = $this->fetchVerdict($url, $origin);
    // A fail-closed verdict is never cached: a transient timeout must not lock
    // the editor out for the whole hour.
    if ($verdict['status'] !== 'unverified') {
      $this->cache->set($cid, $verdict, time() + self::CACHE_SECONDS);
    }
    return $verdict;
  }

  protected function fetchVerdict(string $url, string $origin): array {
    $host = (string) parse_url($url, PHP_URL_HOST);
    $verdict = ['status' => 'unverified', 'host' => $host, 'header' => ''];
    // The site's own pages are same-origin, so framing is always allowed.
    if ($host !== '' && strcasecmp($host, (string) parse_url($origin, PHP_URL_HOST)) === 0) {
      return ['status' => 'ok'] + $verdict;
    }
    try {
      $response = $this->request($url);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('bioland')->warning('Embed frameability check of @url failed: @message', ['@url' => $url, '@message' => $e->getMessage()]);
      return $verdict;
    }
    $refusal = self::decide($response->getHeader('Content-Security-Policy'), $response->getHeader('X-Frame-Options'), $origin);
    if ($refusal !== NULL) {
      // The header is remote-controlled text that lands in the error message.
      return ['status' => 'refused', 'header' => mb_strimwidth($refusal, 0, self::MAX_HEADER_ECHO, '...')] + $verdict;
    }
    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      $this->loggerFactory->get('bioland')->warning('Embed frameability check of @url got HTTP @status with no framing header.', ['@url' => $url, '@status' => $status]);
      return $verdict;
    }
    return ['status' => 'ok'] + $verdict;
  }

  /**
   * Fetches the final response's headers, following guarded redirects.
   */
  protected function request(string $url): object {
    for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
      if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
        throw new \RuntimeException('URL refused by the fetch policy.');
      }
      $parts = parse_url($url);
      $host = strtolower(trim($parts['host'], '[]'));
      $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
      $addresses = ($this->resolver)($host);
      if ($addresses === [] || array_filter($addresses, fn($ip) => !BiolandUrlMetadata::isPublicIp($ip)) !== []) {
        throw new \RuntimeException("Host $host does not resolve to a public address.");
      }
      $ip = strpos($addresses[0], ':') !== FALSE ? '[' . $addresses[0] . ']' : $addresses[0];
      $captured = NULL;
      try {
        $this->httpClient->request('GET', $url, [
          'timeout' => 6,
          'connect_timeout' => 3,
          'allow_redirects' => FALSE,
          'http_errors' => FALSE,
          'proxy' => '',
          'headers' => ['Accept' => 'text/html', 'User-Agent' => self::USER_AGENT],
          // Headers are all that is read: abort before the body downloads.
          'on_headers' => function ($response) use (&$captured) {
            $captured = $response;
            throw new \RuntimeException('Headers received.');
          },
          // Pin the connection to the checked address (no DNS rebinding).
          'curl' => [
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
          ],
        ]);
      }
      catch (\Throwable $e) {
        if ($captured === NULL) {
          throw $e;
        }
      }
      $status = $captured->getStatusCode();
      if ($status >= 300 && $status < 400 && $captured->getHeaderLine('Location') !== '') {
        $url = BiolandUrlMetadata::resolveRedirect($url, $captured->getHeaderLine('Location'));
        continue;
      }
      return $captured;
    }
    throw new \RuntimeException('Too many redirects.');
  }

  /**
   * The blocking header a response sends for this site origin, or NULL.
   *
   * Pure so the rules are testable: frame-ancestors wins over X-Frame-Options,
   * and Report-Only policies are never passed in.
   *
   * @param string[] $csp
   *   Content-Security-Policy header values.
   * @param string[] $xfo
   *   X-Frame-Options header values.
   * @param string $origin
   *   The site origin, scheme plus host (and port).
   */
  public static function decide(array $csp, array $xfo, string $origin): ?string {
    $found = FALSE;
    foreach ($csp as $header) {
      foreach (explode(',', $header) as $policy) {
        foreach (explode(';', $policy) as $directive) {
          $tokens = preg_split('/\s+/', trim($directive));
          if (strtolower($tokens[0]) !== 'frame-ancestors') {
            continue;
          }
          $found = TRUE;
          $sources = array_slice($tokens, 1);
          if (!array_filter($sources, fn($source) => self::sourceCovers($source, $origin))) {
            return 'Content-Security-Policy: ' . trim($directive);
          }
        }
      }
    }
    if ($found) {
      return NULL;
    }
    foreach ($xfo as $header) {
      foreach (explode(',', $header) as $value) {
        $value = trim($value);
        if (preg_match('/^(deny|sameorigin)$/i', $value)
          || (preg_match('/^allow-from\s+(\S+)/i', $value, $m) && !self::sourceCovers($m[1], $origin))) {
          return 'X-Frame-Options: ' . $value;
        }
      }
    }
    return NULL;
  }

  /**
   * Whether one frame-ancestors source expression covers the site origin.
   */
  protected static function sourceCovers(string $source, string $origin): bool {
    $site = parse_url($origin);
    if ($source === '*') {
      return TRUE;
    }
    if (preg_match('/^([a-z][a-z0-9+.-]*):$/i', $source, $m)) {
      return strcasecmp($m[1], (string) ($site['scheme'] ?? '')) === 0;
    }
    if (!preg_match('#^(?:([a-z][a-z0-9+.-]*)://)?([^/:]+)(?::(\d+|\*))?(?:/.*)?$#i', $source, $m)) {
      return FALSE;
    }
    [, $scheme, $host, $port] = $m + [3 => ''];
    if ($scheme !== '' && strcasecmp($scheme, (string) ($site['scheme'] ?? '')) !== 0) {
      return FALSE;
    }
    $default = strtolower((string) ($site['scheme'] ?? '')) === 'https' ? 443 : 80;
    $sitePort = $site['port'] ?? $default;
    // A source without a port matches only the scheme's default port.
    if ($port !== '*' && (int) ($port === '' ? $default : $port) !== $sitePort) {
      return FALSE;
    }
    if ($host === '*') {
      return TRUE;
    }
    $siteHost = strtolower((string) ($site['host'] ?? ''));
    $host = strtolower($host);
    return str_starts_with($host, '*.') ? str_ends_with($siteHost, substr($host, 1)) : $host === $siteHost;
  }

}
