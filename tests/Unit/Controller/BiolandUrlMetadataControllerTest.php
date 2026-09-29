<?php

namespace Drupal\Tests\bioland\Unit\Controller;

use Drupal\bioland\Controller\BiolandUrlMetadataController;
use Drupal\Core\Flood\FloodInterface;
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
    $flood->method('isAllowed')->willReturn($floodAllowed);
    $resolver = static fn(string $host): array => $dns[$host] ?? ['93.184.216.34'];
    return new BiolandUrlMetadataController($client, $flood, $resolver);
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
