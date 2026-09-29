<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use Drupal\Core\Config\Config;
use Drupal\Core\File\FileUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1218 embed allowlist backfill run by bioland_update_9099().
 *
 * @group bioland
 */
class BiolandEmbedAllowlistBackfillTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.editor.inc';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Wires bioland.settings and a public files URL; returns the config.
   */
  private function site(array $data, $filesUrl): Config {
    $config = new Config('bioland.settings', $data);
    $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $factory->method('getEditable')->willReturn($config);
    \Drupal::setService('config.factory', $factory);

    $generator = $this->createMock(FileUrlGeneratorInterface::class);
    if ($filesUrl instanceof \Throwable) {
      $generator->method('generateAbsoluteString')->willThrowException($filesUrl);
    }
    else {
      $generator->method('generateAbsoluteString')->with('public://')->willReturn($filesUrl);
    }
    \Drupal::setService('file_url_generator', $generator);
    return $config;
  }

  /**
   * A site without the key gets the defaults plus its own files URL.
   */
  public function testSeedsDefaultsAndFilesUrl(): void {
    $config = $this->site(['region' => 'r'], 'HTTPS://Example.org/sites/example/files/');

    $message = _bioland_backfill_embed_allowed_origins();

    $expected = BiolandEmbedAllowlist::DEFAULTS;
    $expected[] = ['url' => 'https://example.org/sites/example/files', 'label' => 'Site files', 'sandbox' => 'allow-scripts'];
    $this->assertSame($expected, $config->get('embed.allowed_origins'));
    $this->assertTrue($config->saved);
    $this->assertSame('r', $config->get('region'));
    $this->assertStringContainsString('https://example.org/sites/example/files', $message);
  }

  /**
   * A list an admin already set is never overwritten, even an empty one.
   *
   * @dataProvider adminListProvider
   */
  public function testKeepsAnExistingList(array $list): void {
    $config = $this->site(['embed' => ['allowed_origins' => $list]], 'https://example.org/sites/example/files/');

    $message = _bioland_backfill_embed_allowed_origins();

    $this->assertSame($list, $config->get('embed.allowed_origins'));
    $this->assertFalse($config->saved);
    $this->assertStringContainsString('left unchanged', $message);
  }

  /**
   * Lists an admin may have saved.
   */
  public static function adminListProvider(): array {
    return [
      'custom list' => [[['url' => 'https://only.example', 'label' => 'Only', 'sandbox' => '']]],
      'empty list' => [[]],
    ];
  }

  /**
   * No real host (drush without --uri) or no generator seeds no files entry.
   *
   * @dataProvider noFilesHostProvider
   */
  public function testSkipsTheFilesEntryWithoutARealHost($filesUrl): void {
    $config = $this->site([], $filesUrl);

    $message = _bioland_backfill_embed_allowed_origins();

    $this->assertSame(BiolandEmbedAllowlist::DEFAULTS, $config->get('embed.allowed_origins'));
    $this->assertStringContainsString('without the site files URL', $message);
  }

  /**
   * Files URLs that must not become an entry.
   */
  public static function noFilesHostProvider(): array {
    return [
      'drush default host' => ['http://default/sites/default/files'],
      'https default host' => ['https://default/sites/default/files'],
      'http behind a TLS proxy' => ['http://site.example.org/sites/x/files'],
      'localhost' => ['https://localhost/sites/x/files'],
      'dotless container name' => ['https://drupal/sites/x/files'],
      'ip literal' => ['https://10.0.0.5/sites/x/files'],
      'generator failure' => [new \RuntimeException('no request')],
    ];
  }

  /**
   * hook_install() adds a usable files URL to the installed list, once.
   */
  public function testInstallSeedAppendsTheFilesUrlOnce(): void {
    $config = $this->site(['embed' => ['allowed_origins' => BiolandEmbedAllowlist::DEFAULTS]], 'https://example.org/sites/example/files/');

    _bioland_seed_embed_files_origin();
    $once = $config->get('embed.allowed_origins');
    $config->saved = FALSE;
    _bioland_seed_embed_files_origin();

    $this->assertCount(count(BiolandEmbedAllowlist::DEFAULTS) + 1, $once);
    $this->assertSame('https://example.org/sites/example/files', end($once)['url']);
    $this->assertSame($once, $config->get('embed.allowed_origins'));
    $this->assertFalse($config->saved, 'A second run finds the entry and writes nothing.');
  }

  /**
   * hook_install() writes nothing when the files URL is not usable.
   */
  public function testInstallSeedSkipsAnUnusableFilesUrl(): void {
    $config = $this->site(['embed' => ['allowed_origins' => BiolandEmbedAllowlist::DEFAULTS]], 'http://default/sites/default/files');

    _bioland_seed_embed_files_origin();

    $this->assertSame(BiolandEmbedAllowlist::DEFAULTS, $config->get('embed.allowed_origins'));
    $this->assertFalse($config->saved);
  }

  /**
   * hook_install() seeds the files entry inside the non-sync block.
   */
  public function testInstallCallsTheSeedOutsideConfigSync(): void {
    $install = file_get_contents(__DIR__ . '/../../bioland.install');
    $this->assertMatchesRegularExpression('/if \(!\\\\Drupal::isConfigSyncing\(\)\) \{[^}]*_bioland_setup_embed_media\(\);[^}]*_bioland_seed_embed_files_origin\(\);/', $install);
  }

  /**
   * The schema types embed.allowed_origins as a sequence of url/label/sandbox.
   */
  public function testSchemaDeclaresTheAllowlist(): void {
    $schema = file_get_contents(__DIR__ . '/../../config/schema/bioland.schema.yml');
    $this->assertMatchesRegularExpression(
      '/^    embed:\n      type: mapping\n(?:.*\n)*?        allowed_origins:\n          type: sequence\n(?:.*\n)*?            type: mapping\n            label: .*\n            mapping:\n              url:\n                type: string\n(?:.*\n){1}              label:\n                type: string\n(?:.*\n){1}              sandbox:\n                type: string\n/m',
      $schema
    );
  }

  /**
   * The install default ships exactly the shared defaults, in order.
   */
  public function testInstallDefaultMatchesTheSharedDefaults(): void {
    $yaml = file_get_contents(__DIR__ . '/../../config/install/bioland.settings.yml');
    $block = substr($yaml, (int) strpos($yaml, "embed:\n  allowed_origins:\n"));
    preg_match_all("/^    - \{ url: '([^']*)', label: '([^']*)', sandbox: '([^']*)' \}$/m", $block, $m, PREG_SET_ORDER);
    $shipped = array_map(static fn (array $row): array => ['url' => $row[1], 'label' => $row[2], 'sandbox' => $row[3]], $m);
    $this->assertSame(BiolandEmbedAllowlist::DEFAULTS, $shipped);
  }

  /**
   * The hook runs the backfill before converging Search API last.
   */
  public function testUpdateHookOrder(): void {
    $source = file_get_contents(__DIR__ . '/../../includes/bioland.install.editor.inc');
    $body = substr($source, strpos($source, 'function bioland_update_9099('));
    $backfill = strpos($body, '_bioland_backfill_embed_allowed_origins()');
    $converge = strpos($body, '_bioland_v2_update_search_and_facets_config()');
    $this->assertIsInt($backfill);
    $this->assertIsInt($converge);
    $this->assertLessThan($converge, $backfill);
  }

}
