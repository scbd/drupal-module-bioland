<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1218 embed allowlist normaliser and validator.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandEmbedAllowlist
 * @group bioland
 */
class BiolandEmbedAllowlistTest extends TestCase {

  /**
   * Valid URLs normalise to lowercase scheme/host with no trailing slash.
   *
   * @dataProvider validUrlProvider
   */
  public function testNormalizeUrl(string $input, string $expected): void {
    $this->assertSame($expected, BiolandEmbedAllowlist::normalizeUrl($input));
  }

  /**
   * Valid inputs and their canonical form.
   */
  public static function validUrlProvider(): array {
    return [
      'origin' => ['https://www.youtube.com', 'https://www.youtube.com'],
      'trailing slash' => ['https://player.vimeo.com/', 'https://player.vimeo.com'],
      'uppercase scheme and host' => ['HTTPS://App.PowerBI.com/view/', 'https://app.powerbi.com/view'],
      'path case kept' => ['https://example.org/Sites/Files', 'https://example.org/Sites/Files'],
      'custom port kept' => ['http://localhost:8080/files/', 'http://localhost:8080/files'],
      'default port dropped' => ['https://example.org:443/x', 'https://example.org/x'],
      'punycode host' => ['https://xn--bcher-kva.example', 'https://xn--bcher-kva.example'],
      'dotted-quad ipv4' => ['http://127.0.0.1:8080', 'http://127.0.0.1:8080'],
      'digits inside a label' => ['https://web2.example', 'https://web2.example'],
      'dotted file name kept' => ['https://a.example/x.y/..z', 'https://a.example/x.y/..z'],
      'surrounding whitespace' => ['  https://example.org  ', 'https://example.org'],
    ];
  }

  /**
   * Invalid URLs are rejected.
   *
   * @dataProvider invalidUrlProvider
   */
  public function testNormalizeUrlRejects(string $input): void {
    $this->assertNull(BiolandEmbedAllowlist::normalizeUrl($input));
  }

  /**
   * Inputs that must never become an allowlist entry.
   */
  public static function invalidUrlProvider(): array {
    return [
      'empty' => [''],
      'relative' => ['/sites/default/files'],
      'scheme-relative' => ['//app.powerbi.com/view'],
      'javascript' => ['javascript:alert(1)'],
      'ftp' => ['ftp://example.org'],
      'no host' => ['https://'],
      'userinfo' => ['https://user:pass@app.powerbi.com/view'],
      'userinfo lookalike' => ['https://app.powerbi.com@evil.example/view'],
      'query' => ['https://app.powerbi.com/view?r=abc'],
      'empty query' => ['https://app.powerbi.com/view?'],
      'fragment' => ['https://app.powerbi.com/view#x'],
      'backslash' => ['https://evil.example\\@app.powerbi.com'],
      'whitespace inside' => ['https://app.powerbi.com/vi ew'],
      'bad port' => ['https://example.org:99999'],
      'dot segment' => ['https://example.org/files/../admin'],
      'underscore host' => ['https://bad_host.example'],
      'encoded slash' => ['https://a.example/b%2Fc'],
      'encoded backslash' => ['https://a.example/b%5cc'],
      'encoded dot segment' => ['https://a.example/b/%2e%2e/c'],
      'mixed encoded dot segment' => ['https://a.example/b/.%2E/c'],
      'encoded single dot' => ['https://a.example/%2E/c'],
      'unicode host' => ['https://bücher.example'],
      'hex ipv4 shorthand' => ['https://0x7f.1'],
      'decimal ipv4 integer' => ['https://2130706433'],
      'octal ipv4' => ['https://0177.0.0.1'],
      'leading-zero ipv4' => ['https://127.0.0.01'],
      'three-part ipv4' => ['https://127.0.1'],
      'out-of-range ipv4' => ['https://256.1.1.1'],
      'numeric last label' => ['https://example.123'],
    ];
  }

  /**
   * A lookalike host and a /view-evil path are stored as their own entries;
   * they are never folded into, or widened to, the legitimate entry.
   */
  public function testLookalikesStayDistinct(): void {
    $this->assertSame('https://evil-youtube.com', BiolandEmbedAllowlist::normalizeUrl('https://evil-youtube.com/'));
    $this->assertSame('https://www.youtube.com.evil.example', BiolandEmbedAllowlist::normalizeUrl('https://www.youtube.com.evil.example'));
    $this->assertSame('https://app.powerbi.com/view-evil', BiolandEmbedAllowlist::normalizeUrl('https://app.powerbi.com/view-evil/'));
  }

  /**
   * Sandbox validation cases.
   *
   * @dataProvider entryProvider
   */
  public function testValidateEntry(array $entry, array $expected): void {
    $this->assertSame($expected, BiolandEmbedAllowlist::validateEntry($entry));
  }

  /**
   * Entries and their expected error codes.
   */
  public static function entryProvider(): array {
    $url = 'https://app.powerbi.com/view';
    return [
      'no sandbox' => [['url' => $url, 'sandbox' => ''], []],
      'known tokens' => [['url' => $url, 'sandbox' => 'allow-scripts allow-forms allow-popups'], []],
      'tokens are case-insensitive' => [['url' => $url, 'sandbox' => 'Allow-Scripts'], []],
      'unknown token' => [['url' => $url, 'sandbox' => 'allow-scripts allow-everything'], ['sandbox_token']],
      'storage access token' => [['url' => $url, 'sandbox' => 'allow-scripts allow-storage-access-by-user-activation'], []],
      'top navigation' => [['url' => $url, 'sandbox' => 'allow-top-navigation'], ['sandbox_never']],
      'popups escape' => [['url' => $url, 'sandbox' => 'allow-popups allow-popups-to-escape-sandbox'], ['sandbox_never']],
      'custom protocols' => [['url' => $url, 'sandbox' => 'allow-top-navigation-to-custom-protocols'], ['sandbox_never']],
      'user-activated top navigation' => [['url' => $url, 'sandbox' => 'allow-top-navigation-by-user-activation'], []],
      'scripts plus same-origin' => [['url' => $url, 'sandbox' => 'allow-same-origin  allow-scripts'], ['sandbox_escape']],
      'same-origin alone' => [['url' => $url, 'sandbox' => 'allow-same-origin'], []],
      'bad url' => [['url' => 'https://user@app.powerbi.com', 'sandbox' => ''], ['url']],
      'missing keys' => [[], ['url']],
    ];
  }

  /**
   * The accepted and never-applied tokens match bioland-head app/utils/html.js.
   */
  public function testTokenListsMatchTheHead(): void {
    $this->assertSame([
      'allow-downloads', 'allow-forms', 'allow-modals', 'allow-orientation-lock', 'allow-pointer-lock', 'allow-popups', 'allow-popups-to-escape-sandbox', 'allow-presentation', 'allow-same-origin', 'allow-scripts', 'allow-storage-access-by-user-activation', 'allow-top-navigation', 'allow-top-navigation-by-user-activation', 'allow-top-navigation-to-custom-protocols',
    ], BiolandEmbedAllowlist::SANDBOX_TOKENS);
    $this->assertSame(['allow-top-navigation', 'allow-popups-to-escape-sandbox', 'allow-top-navigation-to-custom-protocols'], BiolandEmbedAllowlist::NEVER_ALLOWED_TOKENS);
  }

  /**
   * normalizeEntry() stores the canonical url, a trimmed label and tokens.
   */
  public function testNormalizeEntry(): void {
    $this->assertSame(
      ['url' => 'https://example.org/sites/x/files', 'label' => 'Files', 'sandbox' => 'allow-scripts'],
      BiolandEmbedAllowlist::normalizeEntry(['url' => 'HTTPS://Example.org/sites/x/files/', 'label' => ' Files ', 'sandbox' => ' ALLOW-SCRIPTS  allow-scripts ', 'remove' => 0])
    );
  }

  /**
   * Every shipped default is already canonical and valid.
   */
  public function testDefaultsAreCanonicalAndValid(): void {
    foreach (BiolandEmbedAllowlist::DEFAULTS as $entry) {
      $this->assertSame([], BiolandEmbedAllowlist::validateEntry($entry), $entry['url']);
      $this->assertSame($entry, BiolandEmbedAllowlist::normalizeEntry($entry));
    }
  }

  /**
   * matches() compares parsed scheme, host, port and path segments.
   *
   * @dataProvider matchProvider
   */
  public function testMatches(string $url, bool $expected): void {
    $entries = [
      ['url' => 'https://app.powerbi.com/view'],
      ['url' => 'https://www.youtube.com'],
      ['url' => 'http://localhost:8080/sites/x/files'],
      ['url' => 'not a url'],
      'not an entry',
    ];
    $this->assertSame($expected, BiolandEmbedAllowlist::matches($url, $entries));
  }

  /**
   * URLs and whether they match the entries above.
   */
  public static function matchProvider(): array {
    return [
      'powerbi report' => ['https://app.powerbi.com/view?r=eyJrIjoiYWJjIn0%3D', TRUE],
      'powerbi trailing slash' => ['https://app.powerbi.com/view/?r=1', TRUE],
      'deeper path' => ['https://app.powerbi.com/view/sub', TRUE],
      'host case ignored' => ['HTTPS://APP.POWERBI.COM/view', TRUE],
      'default port' => ['https://app.powerbi.com:443/view', TRUE],
      'origin entry any path' => ['https://www.youtube.com/embed/abc#t=1', TRUE],
      'files with port' => ['http://localhost:8080/sites/x/files/2026-09/flow.html', TRUE],
      'at in path on origin entry' => ['https://www.youtube.com/@user/videos', TRUE],
      'at in path under entry path' => ['https://app.powerbi.com/view/x@y', TRUE],
      'at in query' => ['https://app.powerbi.com/view?r=a@b', TRUE],
      'path not on segment boundary' => ['https://app.powerbi.com/view-evil', FALSE],
      'path prefix only' => ['https://app.powerbi.com/vie', FALSE],
      'path case differs' => ['https://app.powerbi.com/View', FALSE],
      'other path' => ['https://app.powerbi.com/reportEmbed', FALSE],
      'lookalike suffix host' => ['https://app.powerbi.com.evil.example/view', FALSE],
      'lookalike prefix host' => ['https://evil-app.powerbi.com/view', FALSE],
      'parent domain' => ['https://powerbi.com/view', FALSE],
      'subdomain of origin entry' => ['https://m.youtube.com/embed/abc', FALSE],
      'scheme differs' => ['http://app.powerbi.com/view', FALSE],
      'port differs' => ['http://localhost:8081/sites/x/files/a.html', FALSE],
      'missing port' => ['http://localhost/sites/x/files/a.html', FALSE],
      'userinfo' => ['https://user@app.powerbi.com/view', FALSE],
      'userinfo spoof' => ['https://app.powerbi.com/view@evil.example/', FALSE],
      'password userinfo' => ['https://app.powerbi.com:pw@evil.example/view', FALSE],
      'encoded slash' => ['https://app.powerbi.com/view%2F..%2Fother', FALSE],
      'encoded backslash' => ['https://app.powerbi.com/view%5cother', FALSE],
      'raw backslash' => ['https://app.powerbi.com\\@evil.example/view', FALSE],
      'dot segment' => ['https://app.powerbi.com/view/../other', FALSE],
      'encoded dot segment' => ['https://app.powerbi.com/view/%2e%2e/other', FALSE],
      'whitespace' => ['https://app.powerbi.com/view x', FALSE],
      'protocol relative' => ['//app.powerbi.com/view', FALSE],
      'relative' => ['/view', FALSE],
      'javascript' => ['javascript:alert(1)//https://app.powerbi.com/view', FALSE],
      'empty' => ['', FALSE],
    ];
  }

  /**
   * classify() separates a wrong host from a wrong or malformed path.
   *
   * @dataProvider classifyProvider
   */
  public function testClassify(string $url, string $expected): void {
    $entries = [['url' => 'https://app.powerbi.com/view'], ['url' => 'https://www.youtube.com']];
    $this->assertSame($expected, BiolandEmbedAllowlist::classify($url, $entries));
  }

  /**
   * URLs and their classification.
   */
  public static function classifyProvider(): array {
    return [
      'match' => ['https://app.powerbi.com/view?r=1', BiolandEmbedAllowlist::MATCH],
      'other host' => ['https://evil.example/view', BiolandEmbedAllowlist::HOST_NOT_ALLOWED],
      'userinfo' => ['https://u@app.powerbi.com/view', BiolandEmbedAllowlist::HOST_NOT_ALLOWED],
      'relative' => ['/view', BiolandEmbedAllowlist::HOST_NOT_ALLOWED],
      'port differs' => ['https://app.powerbi.com:8443/view', BiolandEmbedAllowlist::HOST_NOT_ALLOWED],
      'path outside entry' => ['https://app.powerbi.com/reportEmbed', BiolandEmbedAllowlist::PATH_NOT_ALLOWED],
      'encoded slash' => ['https://app.powerbi.com/view%2fx', BiolandEmbedAllowlist::PATH_NOT_ALLOWED],
      'dot segment' => ['https://app.powerbi.com/view/../x', BiolandEmbedAllowlist::PATH_NOT_ALLOWED],
      'space' => ['https://app.powerbi.com/view x', BiolandEmbedAllowlist::PATH_NOT_ALLOWED],
    ];
  }

  /**
   * findEntry() returns the entry that matched, first one wins.
   */
  public function testFindEntry(): void {
    $entries = [['url' => 'https://www.youtube.com', 'label' => 'A'], ['url' => 'https://www.youtube.com/embed', 'label' => 'B']];
    $this->assertSame('A', BiolandEmbedAllowlist::findEntry('https://www.youtube.com/embed/x', $entries)['label']);
    $this->assertNull(BiolandEmbedAllowlist::findEntry('https://youtube.com/embed/x', $entries));
  }

  /**
   * entryUrls() lists the valid entries in canonical form, de-duplicated.
   */
  public function testEntryUrls(): void {
    $this->assertSame(
      ['https://app.powerbi.com/view', 'https://www.youtube.com'],
      BiolandEmbedAllowlist::entryUrls([['url' => 'HTTPS://app.powerbi.com/view/'], ['url' => 'nope'], 'x', ['url' => 'https://www.youtube.com'], ['url' => 'https://app.powerbi.com/view']])
    );
  }

  /**
   * frameSandbox() mirrors the head's toSandbox().
   *
   * @dataProvider sandboxProvider
   */
  public function testFrameSandbox(string $sandbox, ?string $expected): void {
    $this->assertSame($expected, BiolandEmbedAllowlist::frameSandbox(['url' => 'https://x.example', 'sandbox' => $sandbox]));
  }

  /**
   * Entry sandbox values and the attribute they produce.
   */
  public static function sandboxProvider(): array {
    return [
      'none set' => ['', NULL],
      'whitespace only' => ['   ', NULL],
      'scripts' => ['allow-scripts', 'allow-scripts'],
      'same-origin dropped beside scripts' => ['allow-same-origin allow-scripts allow-forms', 'allow-scripts allow-forms'],
      'same-origin alone kept' => ['allow-same-origin', 'allow-same-origin'],
      'never-allowed dropped' => ['allow-top-navigation allow-popups', 'allow-popups'],
      'all filtered becomes empty' => ['allow-top-navigation bogus', ''],
      'case folded' => ['ALLOW-SCRIPTS', 'allow-scripts'],
    ];
  }

  /**
   * An empty or missing list matches nothing.
   */
  public function testEmptyListMatchesNothing(): void {
    $this->assertFalse(BiolandEmbedAllowlist::matches('https://app.powerbi.com/view', []));
    $this->assertFalse(BiolandEmbedAllowlist::matches('https://app.powerbi.com/view', [[]]));
  }

  /**
   * autoEntry() builds an unsandboxed origin entry for public https hosts.
   *
   * @dataProvider autoEntryProvider
   */
  public function testAutoEntry(string $url, ?array $expected): void {
    $this->assertSame($expected, BiolandEmbedAllowlist::autoEntry($url));
  }

  /**
   * URLs and the auto entry each one gets.
   */
  public static function autoEntryProvider(): array {
    return [
      'artifact page' => ['https://claude.ai/artifact/94Nk8f', ['url' => 'https://claude.ai', 'label' => 'claude.ai', 'sandbox' => '']],
      'mixed case, default port, query' => ['  HTTPS://Example.ORG:443/a?b=1 ', ['url' => 'https://example.org', 'label' => 'example.org', 'sandbox' => '']],
      'explicit port kept' => ['https://example.org:8443/a', ['url' => 'https://example.org:8443', 'label' => 'example.org', 'sandbox' => '']],
      'http' => ['http://example.org/a', NULL],
      'ip literal' => ['https://10.0.0.1/a', NULL],
      'localhost' => ['https://localhost/a', NULL],
      'dotless host' => ['https://intranet/a', NULL],
      'userinfo' => ['https://user@example.org/a', NULL],
      'relative' => ['/a', NULL],
      'empty' => ['', NULL],
    ];
  }

  /**
   * withAutoEntry() appends an entry only for a URL on no listed host.
   */
  public function testWithAutoEntry(): void {
    $entries = [['url' => 'https://app.powerbi.com/view', 'label' => 'Power BI', 'sandbox' => '']];
    $added = BiolandEmbedAllowlist::withAutoEntry('https://claude.ai/artifact/x', $entries);
    $this->assertSame([...$entries, ['url' => 'https://claude.ai', 'label' => 'claude.ai', 'sandbox' => '']], $added);
    $this->assertTrue(BiolandEmbedAllowlist::matches('https://claude.ai/artifact/x', $added));

    // Already allowed, or listed under a narrower path: nothing to add.
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('https://app.powerbi.com/view?r=1', $entries));
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('https://app.powerbi.com/other', $entries));
    // Malformed path on a new host still fails.
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('https://claude.ai/a/../b', $entries));
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('https://claude.ai/a%2fb', $entries));
    // Not eligible for an auto entry.
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('http://claude.ai/x', $entries));
  }

  /**
   * withAutoEntry() never grows the list past MAX_ENTRIES.
   */
  public function testWithAutoEntryRespectsMaxEntries(): void {
    $entries = array_map(fn ($i) => ['url' => "https://h$i.example", 'label' => '', 'sandbox' => ''], range(1, BiolandEmbedAllowlist::MAX_ENTRIES));
    $this->assertNull(BiolandEmbedAllowlist::withAutoEntry('https://claude.ai/x', $entries));
    $this->assertCount(BiolandEmbedAllowlist::MAX_ENTRIES, BiolandEmbedAllowlist::withAutoEntry('https://claude.ai/x', array_slice($entries, 1)));
  }

}
