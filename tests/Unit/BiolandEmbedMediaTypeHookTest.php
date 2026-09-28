<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1218 embed media type wiring.
 *
 * Source-level checks, in the style of BiolandContribFeatureModulesHookTest:
 * hook 9098 and hook_install() enable iframe + media_iframe through the
 * shared helper, create the embed type on media_iframe's inline_frame source,
 * and re-apply the CKEditor media picker allowlist afterwards.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedMediaTypeHookTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.editor.inc';
  }

  /**
   * Reads a module file relative to the module root.
   */
  private function read(string $path): string {
    $file = dirname(__DIR__, 2) . '/' . $path;
    $this->assertFileExists($file);
    return file_get_contents($file);
  }

  /**
   * Returns one function's source, cut at the next function declaration.
   */
  private function functionBody(string $source, string $name): string {
    $offset = strpos($source, 'function ' . $name . '(');
    $this->assertIsInt($offset, $name . '() must exist.');
    $body = substr($source, $offset);
    if (preg_match('/^function /m', $body, $matches, PREG_OFFSET_CAPTURE, 1)) {
      $body = substr($body, 0, $matches[0][1]);
    }
    return $body;
  }

  /**
   * The embed modules are iframe and media_iframe, in dependency order.
   */
  public function testEmbedModulesAreIframeThenMediaIframe(): void {
    $this->assertSame(['iframe', 'media_iframe'], BIOLAND_EMBED_MODULES);
  }

  /**
   * Hook 9098 enables the modules, creates the type, then re-applies the
   * picker allowlist, in that order.
   */
  public function testUpdateHookOrder(): void {
    $hook = $this->functionBody($this->read('includes/bioland.install.editor.inc'), 'bioland_update_9098');
    $enable = strpos($hook, '_bioland_enable_contrib_feature_modules(BIOLAND_EMBED_MODULES)');
    $create = strpos($hook, '_bioland_create_embed_media_type()');
    $allow = strpos($hook, '_bioland_configure_media_embed_allowed_types()');
    $this->assertIsInt($enable);
    $this->assertIsInt($create);
    $this->assertIsInt($allow);
    $this->assertLessThan($create, $enable);
    $this->assertLessThan($allow, $create);
  }

  /**
   * The type uses media_iframe's inline_frame source and never overwrites an
   * existing embed type.
   */
  public function testCreateHelperUsesInlineFrameSourceAndIsIdempotent(): void {
    $body = $this->functionBody($this->read('includes/bioland.install.editor.inc'), '_bioland_create_embed_media_type');
    $this->assertStringContainsString("'source' => 'inline_frame'", $body);
    $this->assertStringContainsString("moduleExists('media_iframe')", $body);
    $this->assertMatchesRegularExpression("/if \\(\\\$storage->load\\('embed'\\)\\) \\{\\s*return/", $body);
    $this->assertStringContainsString('->prepareFormDisplay(', $body);
    $this->assertStringContainsString('->prepareViewDisplay(', $body);
  }

  /**
   * Fresh installs get the same wiring, outside a config sync.
   */
  public function testInstallWiresEmbedOutsideConfigSync(): void {
    $install = $this->functionBody($this->read('bioland.install'), 'bioland_install');
    $this->assertMatchesRegularExpression(
      '/if \(!\\\\Drupal::isConfigSyncing\(\)\) \{[^}]*_bioland_enable_contrib_feature_modules\(BIOLAND_EMBED_MODULES\);\s*_bioland_create_embed_media_type\(\);\s*_bioland_configure_media_embed_allowed_types\(\);/',
      $install
    );
  }

}
