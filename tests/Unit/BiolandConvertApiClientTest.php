<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\ConvertApi\BiolandConvertApiClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ConvertAPI adapter's pure helpers.
 *
 * The static ConvertApi SDK client is never instantiated here (its
 * constructor requires real credentials and network access); only the
 * parameter builders and exception classifier are covered, per BL-1191's
 * test plan.
 *
 * @covers \Drupal\bioland\ConvertApi\BiolandConvertApiClient
 * @group bioland
 */
class BiolandConvertApiClientTest extends TestCase {

  public function testBuildHtmlToJpgOptions(): void {
    $options = BiolandConvertApiClient::buildHtmlToJpgOptions('https://example.org');

    $this->assertSame('https://example.org', $options['Url']);
    $this->assertSame(1280, $options['ImageWidth']);
    $this->assertSame(800, $options['ImageHeight']);
    $this->assertSame(2, $options['ConversionDelay']);
  }

  public function testBuildJpgToWebpOptionsChainsStoredFileUrl(): void {
    $options = BiolandConvertApiClient::buildJpgToWebpOptions('https://v2.convertapi.com/d/abc/screenshot.jpg');

    $this->assertSame(['File' => 'https://v2.convertapi.com/d/abc/screenshot.jpg'], $options);
  }

  /**
   * @dataProvider classifyProvider
   */
  public function testClassifyExceptionStatus(int $status, string $expected): void {
    $this->assertSame($expected, BiolandConvertApiClient::classifyExceptionStatus($status));
  }

  public static function classifyProvider(): array {
    return [
      '429 transient' => [429, 'transient'],
      '503 transient' => [503, 'transient'],
      '401 permanent' => [401, 'permanent'],
      '400 permanent' => [400, 'permanent'],
    ];
  }

  public function testStatusFromExceptionReadsConvertApiErrorCode(): void {
    $e = new \ConvertApi\Error\Api('rate limited', 429);

    $this->assertSame(429, BiolandConvertApiClient::statusFromException($e));
  }

  public function testStatusFromExceptionIgnoresOtherThrowables(): void {
    $e = new \RuntimeException('unrelated failure', 500);

    $this->assertSame(0, BiolandConvertApiClient::statusFromException($e));
  }

}
