<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Service\BiolandEmbedFrameability;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * BL-1273: header decision table and fetch behaviour of the frameability check.
 *
 * @group bioland
 * @coversDefaultClass \Drupal\bioland\Service\BiolandEmbedFrameability
 */
class BiolandEmbedFrameabilityTest extends TestCase {

  const SITE = 'https://co.bsl.cbddev.xyz';

  /**
   * @dataProvider decideProvider
   */
  public function testDecide(array $csp, array $xfo, ?string $expected): void {
    $this->assertSame($expected, BiolandEmbedFrameability::decide($csp, $xfo, self::SITE));
  }

  public static function decideProvider(): array {
    return [
      'no headers' => [[], [], NULL],
      'csp without frame-ancestors' => [["default-src 'self'"], [], NULL],
      'self refuses' => [["default-src *; frame-ancestors 'self' chrome-extension://x"], [], "Content-Security-Policy: frame-ancestors 'self' chrome-extension://x"],
      'star allows' => [['frame-ancestors *'], [], NULL],
      'exact origin allows' => [['frame-ancestors https://co.bsl.cbddev.xyz'], [], NULL],
      'other origin refuses' => [['frame-ancestors https://other.example'], [], 'Content-Security-Policy: frame-ancestors https://other.example'],
      'scheme only allows' => [['frame-ancestors https:'], [], NULL],
      'wrong scheme refuses' => [['frame-ancestors http:'], [], 'Content-Security-Policy: frame-ancestors http:'],
      'wildcard host allows' => [['frame-ancestors *.cbddev.xyz'], [], NULL],
      'bare host allows' => [['frame-ancestors co.bsl.cbddev.xyz'], [], NULL],
      'none refuses' => [["frame-ancestors 'none'"], [], "Content-Security-Policy: frame-ancestors 'none'"],
      'sameorigin refuses' => [[], ['SAMEORIGIN'], 'X-Frame-Options: SAMEORIGIN'],
      'deny refuses' => [[], ['deny'], 'X-Frame-Options: deny'],
      'allow-from other refuses' => [[], ['ALLOW-FROM https://other.example'], 'X-Frame-Options: ALLOW-FROM https://other.example'],
      'allow-from site allows' => [[], ['ALLOW-FROM https://co.bsl.cbddev.xyz'], NULL],
      'scheme wildcard allows' => [['frame-ancestors https://*'], [], NULL],
      'wrong scheme wildcard refuses' => [['frame-ancestors http://*'], [], 'Content-Security-Policy: frame-ancestors http://*'],
      'portless host allows default port' => [['frame-ancestors https://co.bsl.cbddev.xyz'], [], NULL],
      'explicit default port allows' => [['frame-ancestors https://co.bsl.cbddev.xyz:443'], [], NULL],
      'other port refuses' => [['frame-ancestors https://co.bsl.cbddev.xyz:8443'], [], 'Content-Security-Policy: frame-ancestors https://co.bsl.cbddev.xyz:8443'],
      'frame-ancestors overrides xfo' => [['frame-ancestors *'], ['DENY'], NULL],
    ];
  }

  private function fakeResponse(int $status, array $headers = []): object {
    return new class($status, $headers) {

      public function __construct(private int $status, private array $headers) {}

      public function getStatusCode(): int {
        return $this->status;
      }

      public function getHeader(string $name): array {
        return isset($this->headers[$name]) ? [$this->headers[$name]] : [];
      }

      public function getHeaderLine(string $name): string {
        return $this->headers[$name] ?? '';
      }

    };
  }

  /**
   * Builds the service over a client that replays $queue (responses or throwables).
   */
  private function service(array $queue, ?array &$history = NULL, ?callable $resolver = NULL): BiolandEmbedFrameability {
    $history = [];
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willReturnCallback(function ($method, $url, $options) use (&$queue, &$history) {
      $history[] = $url;
      $next = array_shift($queue);
      if ($next instanceof \Throwable) {
        throw $next;
      }
      // Guzzle runs on_headers and lets its exception abort the transfer.
      $options['on_headers']($next);
    });
    $cache = new class implements CacheBackendInterface {

      private array $items = [];

      public function get($cid, $allow_invalid = FALSE) {
        return isset($this->items[$cid]) ? (object) ['data' => $this->items[$cid]] : FALSE;
      }

      public function set($cid, $data, $expire = -1, array $tags = []) {
        $this->items[$cid] = $data;
      }

    };
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->with('bioland')->willReturn($this->createMock(LoggerChannelInterface::class));
    $request = $this->getMockBuilder(Request::class)->addMethods(['getSchemeAndHttpHost'])->getMock();
    $request->method('getSchemeAndHttpHost')->willReturn(self::SITE);
    $requests = $this->createMock(RequestStack::class);
    $requests->method('getCurrentRequest')->willReturn($request);
    return new BiolandEmbedFrameability($client, $cache, $logger, $requests, $resolver ?? fn() => ['93.184.216.34']);
  }

  public function testRefusingOk200(): void {
    $status = $this->service([$this->fakeResponse(200, ['Content-Security-Policy' => "frame-ancestors 'self'"])])->verdict('https://claude.ai/artifact/1');
    $this->assertSame('refused', $status['status']);
    $this->assertStringContainsString("frame-ancestors 'self'", $status['header']);
  }

  public function testRefusing403(): void {
    $verdict = $this->service([$this->fakeResponse(403, ['X-Frame-Options' => 'SAMEORIGIN'])])->verdict('https://claude.ai/artifact/1');
    $this->assertSame(['refused', 'X-Frame-Options: SAMEORIGIN'], [$verdict['status'], $verdict['header']]);
  }

  public function testAllowing200AndCache(): void {
    $service = $this->service([$this->fakeResponse(200)], $history);
    $this->assertNull($service->error('https://www.youtube.com/embed/x'));
    $this->assertNull($service->error('https://www.youtube.com/embed/x'));
    $this->assertCount(1, $history, 'second call is served from the cache');
  }

  public function testUnverifiedVerdictIsNotCached(): void {
    $service = $this->service([$this->fakeResponse(503), $this->fakeResponse(200)], $history);
    $this->assertSame('unverified', $service->verdict('https://a.example/x')['status']);
    $this->assertSame('ok', $service->verdict('https://a.example/x')['status'], 'a retry re-fetches');
    $this->assertCount(2, $history);
  }

  public function testEchoedHeaderIsTruncated(): void {
    $verdict = $this->service([$this->fakeResponse(200, ['X-Frame-Options' => 'ALLOW-FROM https://' . str_repeat('a', 500) . '.example'])])->verdict('https://claude.ai/x');
    $this->assertLessThanOrEqual(BiolandEmbedFrameability::MAX_HEADER_ECHO, mb_strlen($verdict['header']));
  }

  public function testRedirectChainEndingInRefusal(): void {
    $service = $this->service([
      $this->fakeResponse(302, ['Location' => 'https://b.example/next']),
      $this->fakeResponse(200, ['X-Frame-Options' => 'DENY']),
    ], $history);
    $this->assertSame('refused', $service->verdict('https://a.example/x')['status']);
    $this->assertCount(2, $history);
  }

  public function testRedirectIntoPrivateHostIsNotFetched(): void {
    $resolver = fn($host) => $host === 'internal.example' ? ['10.0.0.5'] : ['93.184.216.34'];
    $service = $this->service([$this->fakeResponse(302, ['Location' => 'https://internal.example/'])], $history, $resolver);
    $this->assertSame('unverified', $service->verdict('https://a.example/x')['status']);
    $this->assertCount(1, $history);
  }

  public function testTooManyRedirectsFailClosed(): void {
    $queue = array_fill(0, 7, $this->fakeResponse(302, ['Location' => 'https://a.example/loop']));
    $this->assertSame('unverified', $this->service($queue)->verdict('https://a.example/x')['status']);
  }

  public function testNetworkErrorsFailClosed(): void {
    foreach ([new \RuntimeException('cURL error 28: timed out'), new \RuntimeException('TLS failure')] as $exception) {
      $service = $this->service([$exception]);
      $this->assertSame('unverified', $service->verdict('https://a.example/x')['status']);
      $this->assertStringContainsString('could not be reached', (string) $service->error('https://a.example/x'));
    }
  }

  public function testNon2xxWithoutRefusalHeaderFailsClosed(): void {
    $this->assertSame('unverified', $this->service([$this->fakeResponse(503)])->verdict('https://a.example/x')['status']);
  }

  public function testGuardedHostsMakeNoRequest(): void {
    $service = $this->service([], $history, fn() => ['127.0.0.1']);
    foreach (['https://localhost/x', 'ftp://a.example/x', 'https://user:pw@a.example/x'] as $url) {
      $this->assertSame('unverified', $service->verdict($url)['status'], $url);
    }
    $this->assertCount(0, $history);
  }

  public function testOwnHostIsAllowedWithoutRequest(): void {
    $service = $this->service([], $history);
    $this->assertNull($service->error(self::SITE . '/embed/1'));
    $this->assertCount(0, $history);
  }

  public function testErrorTextNamesHostAndHeader(): void {
    $message = (string) $this->service([$this->fakeResponse(200, ['Content-Security-Policy' => "frame-ancestors 'self'"])])->error('https://claude.ai/artifact/1');
    $this->assertStringContainsString('claude.ai', $message);
    $this->assertStringContainsString('frame-ancestors', $message);
  }

}
