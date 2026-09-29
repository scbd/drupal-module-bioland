<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1218 embed media type wiring.
 *
 * Behaviour tests drive _bioland_create_embed_media_type() through its skip
 * and exists branches with a stubbed container; source-level checks, in the
 * style of BiolandContribFeatureModulesHookTest, pin the hook 9098,
 * hook_install(), hook_modules_installed() and hook_requirements() wiring.
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
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Registers a module handler reporting the given modules as enabled.
   */
  private function setEnabledModules(array $modules): void {
    \Drupal::setService('module_handler', new class($modules) {
      public function __construct(private array $modules) {}

      public function moduleExists($module) {
        return in_array($module, $this->modules, TRUE);
      }
    });
  }

  /**
   * Registers media_type storage whose load() returns $type.
   *
   * create() fails the test: the exists branches must never build a type.
   */
  private function setMediaTypeStorage(?object $type): void {
    $storage = new class($type) {
      public function __construct(private ?object $type) {}

      public function load($id) {
        return $id === 'embed' ? $this->type : NULL;
      }

      public function create(array $values) {
        throw new \LogicException('create() must not be called.');
      }
    };
    \Drupal::setService('entity_type.manager', new class($storage) {
      public function __construct(private object $storage) {}

      public function getStorage($entity_type_id) {
        return $this->storage;
      }
    });
  }

  /**
   * A media type double on the given source, with or without a source field.
   */
  private function mediaType(string $plugin_id, bool $has_source_field): object {
    $source = new class($plugin_id, $has_source_field) {
      public function __construct(private string $pluginId, private bool $hasField) {}

      public function getPluginId() {
        return $this->pluginId;
      }

      public function getSourceFieldDefinition($type) {
        return $this->hasField ? new \stdClass() : NULL;
      }

      public function createSourceField($type) {
        throw new \LogicException('createSourceField() reached.');
      }
    };
    return new class($source) {
      public function __construct(private object $source) {}

      public function getSource() {
        return $this->source;
      }
    };
  }

  /**
   * Returns one function's source, cut at the next function declaration.
   */
  private function functionBody(string $path, string $name): string {
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    $offset = strpos($source, 'function ' . $name . '(');
    $this->assertIsInt($offset, $name . '() must exist.');
    $body = substr($source, $offset);
    if (preg_match('/^function /m', $body, $matches, PREG_OFFSET_CAPTURE, 1)) {
      $body = substr($body, 0, $matches[0][1]);
    }
    return $body;
  }

  /**
   * Asserts each needle appears in $haystack, in the given order.
   */
  private function assertInOrder(string $haystack, array $needles): void {
    $last = -1;
    foreach ($needles as $needle) {
      $position = strpos($haystack, $needle, $last + 1);
      $this->assertIsInt($position, "Missing or out of order: $needle");
      $last = $position;
    }
  }

  /**
   * The embed modules are iframe and media_iframe, in dependency order.
   */
  public function testEmbedModulesAreIframeThenMediaIframe(): void {
    $this->assertSame(['iframe', 'media_iframe'], BIOLAND_EMBED_MODULES);
  }

  /**
   * Without media_iframe the type is skipped, and storage is never touched.
   */
  public function testSkipsWhenMediaIframeIsDisabled(): void {
    $this->setEnabledModules(['media', 'iframe']);
    $this->assertSame(
      'media_iframe is not enabled; skipped the embed media type.',
      _bioland_create_embed_media_type()
    );
  }

  /**
   * An embed type on another source is left alone.
   */
  public function testLeavesForeignSourceEmbedTypeUntouched(): void {
    $this->setEnabledModules(['media_iframe']);
    $this->setMediaTypeStorage($this->mediaType('oembed:video', TRUE));
    $this->assertSame(
      'Media type embed exists on another source; left it untouched.',
      _bioland_create_embed_media_type()
    );
  }

  /**
   * A complete embed type is neither recreated nor given a new field.
   */
  public function testCompleteEmbedTypeIsNotRebuilt(): void {
    $this->setEnabledModules(['media_iframe']);
    $this->setMediaTypeStorage($this->mediaType('inline_frame', TRUE));
    $this->assertSame('Media type embed already exists.', _bioland_create_embed_media_type());
  }

  /**
   * A half-created type (no source field) is finished, not skipped.
   */
  public function testTypeWithoutSourceFieldIsFinished(): void {
    $this->setEnabledModules(['media_iframe']);
    $this->setMediaTypeStorage($this->mediaType('inline_frame', FALSE));
    $this->expectExceptionMessage('createSourceField() reached.');
    _bioland_create_embed_media_type();
  }

  /**
   * The helper uses the inline_frame source and ensures the media_library
   * displays after the create branch, guarded on media_library.
   */
  public function testCreateHelperWiring(): void {
    $body = $this->functionBody('includes/bioland.install.editor.inc', '_bioland_create_embed_media_type');
    $this->assertStringContainsString("'source' => 'inline_frame'", $body);
    $this->assertInOrder($body, [
      'if (!$source->getSourceFieldDefinition($type)) {',
      '->prepareFormDisplay(',
      '->prepareViewDisplay(',
      "moduleExists('media_library')",
      "function_exists('_media_library_configure_form_display')",
      '_media_library_configure_form_display($type)',
      '_media_library_configure_view_display($type)',
    ]);
  }

  /**
   * Hook 9098 enables the modules, then sets the type and picker up.
   */
  public function testUpdateHookOrder(): void {
    $this->assertInOrder($this->functionBody('includes/bioland.install.editor.inc', 'bioland_update_9098'), [
      '_bioland_enable_contrib_feature_modules(BIOLAND_EMBED_MODULES)',
      '_bioland_setup_embed_media()',
      '_bioland_v2_update_search_and_facets_config()',
    ]);
    $this->assertInOrder($this->functionBody('includes/bioland.install.editor.inc', '_bioland_setup_embed_media'), [
      '_bioland_create_embed_media_type()',
      '_bioland_configure_media_embed_allowed_types()',
    ]);
  }

  /**
   * Fresh installs get the same wiring, inside the config-sync guard.
   */
  public function testInstallWiresEmbedOutsideConfigSync(): void {
    $this->assertInOrder($this->functionBody('bioland.install', 'bioland_install'), [
      'if (!\Drupal::isConfigSyncing()) {',
      '_bioland_enable_contrib_feature_modules(BIOLAND_EMBED_MODULES);',
      '_bioland_setup_embed_media();',
    ]);
  }

  /**
   * Enabling media_iframe later self-heals, except during a config sync.
   */
  public function testModulesInstalledSelfHeals(): void {
    $this->assertInOrder($this->functionBody('bioland.module', 'bioland_modules_installed'), [
      'if ($is_syncing) {',
      "if (in_array('media_iframe', \$modules, TRUE)) {",
      "loadInclude('bioland', 'install')",
      '_bioland_setup_embed_media();',
    ]);
  }

  /**
   * The status report flags missing embed modules or a missing embed type.
   */
  public function testRequirementsFlagMissingEmbed(): void {
    $this->assertInOrder($this->functionBody('bioland.install', 'bioland_requirements'), [
      'BIOLAND_EMBED_MODULES',
      "\$requirements['bioland_embed_media']",
      "->getStorage('media_type')->load('embed')",
    ]);
  }

}
