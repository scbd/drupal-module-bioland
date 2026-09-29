<?php

namespace Drupal\Tests\bioland\Unit\Controller;

use Drupal\bioland\Controller\BiolandUrlMetadataController;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * BL-1191: the Related websites title/description lookup route.
 *
 * @covers \Drupal\bioland\Controller\BiolandUrlMetadataController
 * @group bioland
 */
class BiolandUrlMetadataControllerTest extends TestCase {

  /**
   * Requests the fake client received: [url, options].
   */
  protected array $requests = [];

  /**
   * Flood calls: [method, args].
   */
  protected array $floodCalls = [];

  /**
   * Notices logged to the bioland channel: [message, context].
   */
  protected array $notices = [];

  protected function fakeResponse(int $status, array $headers, string $body = ''): object {
    return new class($status, $headers, $body) {

      public function __construct(private int $status, private array $headers, private string $body) {
      }

      public function getStatusCode(): int {
        return $this->status;
      }

      public function getHeaderLine(string $name): string {
        return $this->headers[$name] ?? '';
      }

      public function getBody(): object {
        return new class($this->body) {

          public function __construct(private string $data) {
          }

          public function eof(): bool {
            return $this->data === '';
          }

          public function read(int $length): string {
            $chunk = substr($this->data, 0, $length);
            $this->data = (string) substr($this->data, $length);
            return $chunk;
          }

        };
      }

    };
  }

  protected function controller(array $responses, array $dns = [], bool $floodAllowed = TRUE): BiolandUrlMetadataController {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willReturnCallback(function ($method, $url, $options) use (&$responses) {
      $this->requests[] = [$url, $options];
      return array_shift($responses);
    });
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturnCallback(function (...$args) use ($floodAllowed) {
      $this->floodCalls[] = ['isAllowed', $args];
      return $floodAllowed;
    });
    $flood->method('register')->willReturnCallback(function (...$args) {
      $this->floodCalls[] = ['register', $args];
    });
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $channel = $this->createMock(LoggerChannelInterface::class);
    $channel->method('notice')->willReturnCallback(function ($message, array $context = []) {
      $this->notices[] = [$message, $context];
    });
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('bioland')->willReturn($channel);
    $resolver = static fn(string $host): array => $dns[$host] ?? ['93.184.216.34'];
    return new BiolandUrlMetadataController($client, $flood, $account, $loggerFactory, $resolver);
  }

  protected function lookup(BiolandUrlMetadataController $controller, string $url): array {
    $response = $controller->lookup(new Request(['url' => $url]));
    return [$response->getStatusCode(), json_decode($response->getContent(), TRUE), $response];
  }

  public function testReturnsTitleAndDescription(): void {
    $html = '<title>CBD</title><meta name="description" content="Convention on Biological Diversity">';
    [$status, $data, $response] = $this->lookup($this->controller([$this->fakeResponse(200, ['Content-Type' => 'text/html; charset=utf-8'], $html)]), 'https://www.cbd.int/');
    $this->assertSame(200, $status);
    $this->assertSame(['title' => 'CBD', 'description' => 'Convention on Biological Diversity'], $data);
    $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    // Connection pinned to the address that passed the check.
    $this->assertSame(['www.cbd.int:443:93.184.216.34'], $this->requests[0][1]['curl'][CURLOPT_RESOLVE]);
    $this->assertFalse($this->requests[0][1]['allow_redirects']);
    $this->assertArrayNotHasKey('stream', $this->requests[0][1]);
    $this->assertSame([], $this->notices);
  }

  public function testPinCannotBeBypassedAndBodyIsCapped(): void {
    $this->lookup($this->controller([$this->fakeResponse(200, ['Content-Type' => 'text/html'], '<title>x</title>')]), 'https://example.org/');
    $options = $this->requests[0][1];
    $this->assertSame('', $options['proxy']);
    $this->assertArrayNotHasKey('progress', $options);
    $this->assertLessThanOrEqual(BiolandUrlMetadataController::TOTAL_TIMEOUT, $options['timeout']);
    $this->assertGreaterThan(5, $options['timeout']);
    $curl = $options['curl'];
    $this->assertSame('', $curl[CURLOPT_PROXY]);
    $this->assertSame('*', $curl[CURLOPT_NOPROXY]);
    $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $curl[CURLOPT_PROTOCOLS]);
    $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $curl[CURLOPT_REDIR_PROTOCOLS]);
    $this->assertSame(BiolandUrlMetadataController::ABORT_BYTES, $curl[CURLOPT_MAXFILESIZE]);
    $this->assertFalse($curl[CURLOPT_NOPROGRESS]);
    $callback = $curl[defined('CURLOPT_XFERINFOFUNCTION') ? CURLOPT_XFERINFOFUNCTION : CURLOPT_PROGRESSFUNCTION];
    $cap = BiolandUrlMetadataController::ABORT_BYTES;
    $this->assertSame(0, $callback(NULL, 0, $cap, 0, 0));
    $this->assertSame(1, $callback(NULL, 0, $cap + 1, 0, 0));
    $this->assertSame(1, $callback(NULL, $cap + 1, 0, 0, 0));
  }

  public function testFloodIsPerUser(): void {
    $this->lookup($this->controller([$this->fakeResponse(200, ['Content-Type' => 'text/html'], '')]), 'https://example.org/');
    $this->assertSame('isAllowed', $this->floodCalls[0][0]);
    $this->assertSame('uid:42', $this->floodCalls[0][1][3]);
    $this->assertSame('register', $this->floodCalls[1][0]);
    $this->assertSame('uid:42', $this->floodCalls[1][1][2]);
  }

  public function testRejectsInvalidUrlWithoutFetching(): void {
    [$status] = $this->lookup($this->controller([]), 'ftp://example.org');
    $this->assertSame(400, $status);
    $this->assertSame([], $this->requests);
  }

  public function testRejectsPrivateAddress(): void {
    [$status] = $this->lookup($this->controller([], ['intranet.test' => ['10.0.0.5']]), 'http://intranet.test/');
    $this->assertSame(502, $status);
    $this->assertSame([], $this->requests);
  }

  public function testLogsRefusedAddressWithoutUrlDetails(): void {
    [$status, $data] = $this->lookup($this->controller([], ['intranet.test' => ['10.0.0.5']]), 'http://intranet.test/secret?token=abc');
    $this->assertSame(['error' => 'unreachable'], $data);
    $this->assertCount(1, $this->notices);
    $this->assertSame(['@uid' => 42, '@host' => 'intranet.test'], $this->notices[0][1]);
    $this->assertStringNotContainsString('token', json_encode($this->notices));
  }

  public function testDoesNotLogUnreachableSites(): void {
    [$status] = $this->lookup($this->controller([$this->fakeResponse(500, ['Content-Type' => 'text/html'])]), 'https://example.org/');
    $this->assertSame(502, $status);
    $this->assertSame([], $this->notices);
  }

  public function testRedirectWithoutLocationFails(): void {
    [$status] = $this->lookup($this->controller([$this->fakeResponse(302, [])]), 'https://example.org/');
    $this->assertSame(502, $status);
    $this->assertCount(1, $this->requests);
  }

  public function testRejectsHostWithAnyPrivateAddress(): void {
    [$status] = $this->lookup($this->controller([], ['mixed.test' => ['93.184.216.34', '127.0.0.1']]), 'http://mixed.test/');
    $this->assertSame(502, $status);
  }

  public function testRechecksRedirectTargets(): void {
    $controller = $this->controller(
      [$this->fakeResponse(302, ['Location' => 'http://169.254.169.254/latest/meta-data'])],
      ['169.254.169.254' => ['169.254.169.254']]
    );
    [$status] = $this->lookup($controller, 'https://example.org/');
    $this->assertSame(502, $status);
    $this->assertCount(1, $this->requests);
  }

  public function testFollowsRelativeRedirect(): void {
    $controller = $this->controller([
      $this->fakeResponse(301, ['Location' => '/en/']),
      $this->fakeResponse(200, ['Content-Type' => 'text/html'], '<title>English</title>'),
    ]);
    [$status, $data] = $this->lookup($controller, 'https://example.org/');
    $this->assertSame(200, $status);
    $this->assertSame('English', $data['title']);
    $this->assertSame('https://example.org/en/', $this->requests[1][0]);
  }

  public function testStopsAfterTooManyRedirects(): void {
    $loop = array_fill(0, 5, $this->fakeResponse(302, ['Location' => '/again']));
    [$status] = $this->lookup($this->controller($loop), 'https://example.org/');
    $this->assertSame(502, $status);
    $this->assertCount(BiolandUrlMetadataController::MAX_REDIRECTS + 1, $this->requests);
  }

  public function testRejectsNonHtml(): void {
    [$status] = $this->lookup($this->controller([$this->fakeResponse(200, ['Content-Type' => 'application/pdf'], '%PDF')]), 'https://example.org/a.pdf');
    $this->assertSame(502, $status);
  }

  public function testFloodLimit(): void {
    [$status] = $this->lookup($this->controller([], [], FALSE), 'https://example.org/');
    $this->assertSame(429, $status);
    $this->assertSame([], $this->requests);
  }

}
