<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandUrlScreenshotPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the BL-1191 pure decision logic.
 *
 * @covers \Drupal\bioland\BiolandUrlScreenshotPolicy
 * @group bioland
 */
class BiolandUrlScreenshotPolicyTest extends TestCase {

  /**
   * @dataProvider qualifiesProvider
   */
  public function testQualifies(string $bundle, ?int $tid, ?string $url, bool $enabled, bool $hasSecret, bool $expected): void {
    $this->assertSame($expected, BiolandUrlScreenshotPolicy::qualifies($bundle, $tid, $url, $enabled, $hasSecret));
  }

  public static function qualifiesProvider(): array {
    return [
      'wrong bundle' => ['page', 13, 'https://example.org', TRUE, TRUE, FALSE],
      'wrong tid' => ['content', 5, 'https://example.org', TRUE, TRUE, FALSE],
      'empty url' => ['content', 13, '', TRUE, TRUE, FALSE],
      'null url' => ['content', 13, NULL, TRUE, TRUE, FALSE],
      'toggle off' => ['content', 13, 'https://example.org', FALSE, TRUE, FALSE],
      'missing secret' => ['content', 13, 'https://example.org', TRUE, FALSE, FALSE],
      'happy path' => ['content', 13, 'https://example.org', TRUE, TRUE, TRUE],
    ];
  }

  public function testUrlChangedIdenticalReturnsFalse(): void {
    $this->assertFalse(BiolandUrlScreenshotPolicy::urlChanged('https://a.org', 'https://a.org'));
  }

  public function testUrlChangedNullOriginalReturnsTrue(): void {
    $this->assertTrue(BiolandUrlScreenshotPolicy::urlChanged(NULL, 'https://a.org'));
  }

  public function testUrlChangedDifferingReturnsTrue(): void {
    $this->assertTrue(BiolandUrlScreenshotPolicy::urlChanged('https://a.org', 'https://b.org'));
  }

  /**
   * @dataProvider fetchableUrlProvider
   */
  public function testIsFetchableUrl(?string $url, bool $expected): void {
    $this->assertSame($expected, BiolandUrlScreenshotPolicy::isFetchableUrl($url));
  }

  public static function fetchableUrlProvider(): array {
    return [
      'ftp rejected' => ['ftp://example.org/x', FALSE],
      'javascript rejected' => ['javascript:alert(1)', FALSE],
      'credentials rejected' => ['https://user:pw@example.org', FALSE],
      'empty host rejected' => ['https:///path', FALSE],
      'empty string rejected' => ['', FALSE],
      'null rejected' => [NULL, FALSE],
      'https accepted' => ['https://example.org/path', TRUE],
    ];
  }

  public function testResolveSecretPrefersEnvVar(): void {
    putenv('CONVERT_API_SECRET=from-env');
    try {
      $this->assertSame('from-env', BiolandUrlScreenshotPolicy::resolveSecret(fn ($k) => 'from-settings'));
    }
    finally {
      putenv('CONVERT_API_SECRET');
    }
  }

  public function testResolveSecretFallsBackToSettings(): void {
    putenv('CONVERT_API_SECRET');
    $this->assertSame('from-settings', BiolandUrlScreenshotPolicy::resolveSecret(fn ($k) => 'from-settings'));
  }

  public function testResolveSecretEmptyWhenNeitherSet(): void {
    putenv('CONVERT_API_SECRET');
    $this->assertSame('', BiolandUrlScreenshotPolicy::resolveSecret(fn ($k) => NULL));
  }

  public function testBuildMediaNameTruncatesAndStripsTags(): void {
    $name = BiolandUrlScreenshotPolicy::buildMediaName('<b>My Site</b>', '2026-09-27');
    $this->assertSame('My Site - website screenshot 2026-09-27', $name);
  }

  public function testBuildMediaAltExtractsHost(): void {
    $alt = BiolandUrlScreenshotPolicy::buildMediaAlt('My Site', 'www.cbd.int');
    $this->assertSame('Screenshot of the My Site website (www.cbd.int)', $alt);
  }

  public function testBuildMediaTitleUsesSummaryWhenPresent(): void {
    $this->assertSame('Some summary', BiolandUrlScreenshotPolicy::buildMediaTitle('  <p>Some summary</p>  ', 'example.org'));
  }

  public function testBuildMediaTitleFallsBackToHost(): void {
    $this->assertSame('example.org', BiolandUrlScreenshotPolicy::buildMediaTitle(NULL, 'example.org'));
    $this->assertSame('example.org', BiolandUrlScreenshotPolicy::buildMediaTitle('', 'example.org'));
  }

  public function testCleanTextTruncatesAtMaxLength(): void {
    $long = str_repeat('a', 300);
    $this->assertSame(150, strlen(BiolandUrlScreenshotPolicy::cleanText($long, 150)));
  }

  /**
   * @dataProvider classifyResponseProvider
   */
  public function testClassifyResponse(int $status, string $expected): void {
    $this->assertSame($expected, BiolandUrlScreenshotPolicy::classifyResponse($status));
  }

  public static function classifyResponseProvider(): array {
    return [
      '200 success' => [200, 'success'],
      '429 transient' => [429, 'transient'],
      '503 transient' => [503, 'transient'],
      '401 permanent' => [401, 'permanent'],
      '400 permanent' => [400, 'permanent'],
    ];
  }

}
