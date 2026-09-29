<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1271 embed Default view display configuration.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedDefaultDisplayTest extends TestCase {

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
   * Registers a view display double and returns it.
   */
  private function registerDisplay(array $components, bool $existing = TRUE, ?string $plugin = 'inline_frame', bool $module = TRUE, string $field = 'field_media_inline_frame'): object {
    $display = new class($components) {
      public int $saves = 0;

      public function __construct(public array $components) {}

      public function getComponent($name) {
        return $this->components[$name] ?? NULL;
      }

      public function removeComponent($name) {
        unset($this->components[$name]);
        return $this;
      }

      public function setComponent($name, array $options = []) {
        $this->components[$name] = $options;
        return $this;
      }

      public function save() {
        $this->saves++;
      }
    };
    $storage = new class($display, $existing) {
      public function __construct(private object $display, private bool $existing) {}

      public function load($id) {
        return $this->existing ? $this->display : NULL;
      }

      public function create(array $values) {
        return $this->display;
      }
    };
    $source = new class($plugin, $field) {
      public function __construct(private ?string $plugin, private string $field) {}

      public function getPluginId() {
        return $this->plugin;
      }

      public function getConfiguration() {
        return ['source_field' => $this->field];
      }
    };
    $type = $plugin === NULL ? NULL : new class($source) {
      public function __construct(private object $source) {}

      public function getSource() {
        return $this->source;
      }
    };
    $types = new class($type) {
      public function __construct(private ?object $type) {}

      public function load($id) {
        return $this->type;
      }
    };
    \Drupal::setService('entity_type.manager', new class($storage, $types) {
      public function __construct(private object $storage, private object $types) {}

      public function getStorage($entity_type_id) {
        return $entity_type_id === 'media_type' ? $this->types : $this->storage;
      }
    });
    \Drupal::setService('module_handler', new class($module) {
      public function __construct(private bool $module) {}

      public function moduleExists($name) {
        return $this->module;
      }
    });
    return $display;
  }

  /**
   * The auto-generated default is reduced to the frame with a hidden label.
   */
  public function testHidesBaseFieldsAndKeepsFrame(): void {
    $display = $this->registerDisplay([
      'created' => ['type' => 'timestamp'],
      'uid' => ['type' => 'author'],
      'thumbnail' => ['type' => 'image'],
      'name' => ['type' => 'string'],
      'field_media_inline_frame' => ['type' => 'iframe_default', 'label' => 'above'],
    ]);
    $this->assertSame('Configured the embed default view display.', _bioland_configure_embed_default_display());
    $this->assertSame(['field_media_inline_frame'], array_keys($display->components));
    $this->assertSame('iframe_only', $display->components['field_media_inline_frame']['type']);
    $this->assertSame('visually_hidden', $display->components['field_media_inline_frame']['label']);
    $this->assertSame(1, $display->saves);
  }

  /**
   * A frame left on iframe_default by 9106 is switched to iframe_only.
   */
  public function testSwitchesTitledFormatterToFrameOnly(): void {
    $display = $this->registerDisplay([
      'field_media_inline_frame' => ['type' => 'iframe_default', 'label' => 'visually_hidden', 'settings' => ['width' => '100%'], 'third_party_settings' => ['x' => ['y' => 1]]],
    ]);
    $this->assertSame('Configured the embed default view display.', _bioland_configure_embed_default_display());
    $this->assertSame('iframe_only', $display->components['field_media_inline_frame']['type']);
    $this->assertSame(['width' => '100%'], $display->components['field_media_inline_frame']['settings']);
    $this->assertSame(['x' => ['y' => 1]], $display->components['field_media_inline_frame']['third_party_settings']);
    $this->assertSame(1, $display->saves);
  }

  /**
   * A frame already on iframe_only with a visible label is re-saved once.
   */
  public function testFixesLabelOnFrameOnly(): void {
    $display = $this->registerDisplay([
      'field_media_inline_frame' => ['type' => 'iframe_only', 'label' => 'above', 'settings' => ['width' => '100%']],
    ]);
    _bioland_configure_embed_default_display();
    $this->assertSame('iframe_only', $display->components['field_media_inline_frame']['type']);
    $this->assertSame('visually_hidden', $display->components['field_media_inline_frame']['label']);
    $this->assertSame(['width' => '100%'], $display->components['field_media_inline_frame']['settings']);
    $this->assertSame(1, $display->saves);
  }

  /**
   * A missing display is created and configured.
   */
  public function testCreatesMissingDisplay(): void {
    $display = $this->registerDisplay([], FALSE);
    _bioland_configure_embed_default_display();
    $this->assertSame(['field_media_inline_frame'], array_keys($display->components));
    $this->assertSame(1, $display->saves);
  }

  /**
   * A second run reports no change and does not save.
   */
  public function testIsIdempotent(): void {
    $display = $this->registerDisplay([
      'created' => ['type' => 'timestamp'],
      'field_media_inline_frame' => ['type' => 'iframe_default', 'label' => 'above'],
    ]);
    _bioland_configure_embed_default_display();
    $this->assertSame('Embed default view display already configured.', _bioland_configure_embed_default_display());
    $this->assertSame(1, $display->saves);
  }

  /**
   * Without the inline-frame embed type nothing is saved.
   */
  public function testSkipsWithoutInlineFrameType(): void {
    $cases = [
      'no module' => [TRUE, NULL, FALSE],
      'no type' => [TRUE, NULL, TRUE],
      'other source' => [TRUE, 'file', TRUE],
    ];
    foreach ($cases as $label => [$existing, $plugin, $module]) {
      $display = $this->registerDisplay(['created' => ['type' => 'timestamp']], $existing, $plugin, $module);
      $this->assertStringContainsString('skipped', _bioland_configure_embed_default_display(), $label);
      $this->assertSame(0, $display->saves, $label);
      $this->assertArrayHasKey('created', $display->components, $label);
    }
  }

  /**
   * The frame field name comes from the media type's source configuration.
   */
  public function testUsesConfiguredSourceField(): void {
    $display = $this->registerDisplay([], FALSE, 'inline_frame', TRUE, 'field_media_inline_frame_1');
    _bioland_configure_embed_default_display();
    $this->assertSame(['field_media_inline_frame_1'], array_keys($display->components));
  }

  /**
   * The setup and hooks 9106 and 9111 run it, and 9111 converges search last.
   */
  public function testWiring(): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/includes/bioland.install.editor.inc');
    $setup = substr($source, strpos($source, 'function _bioland_setup_embed_media('));
    $setup = substr($setup, 0, strpos($setup, "\n}\n"));
    $this->assertStringContainsString('_bioland_configure_embed_default_display()', $setup);
    foreach (['9106', '9111'] as $number) {
      $hook = substr($source, strpos($source, "function bioland_update_$number("));
      $this->assertStringContainsString('_bioland_configure_embed_default_display()', $hook, $number);
      $this->assertStringContainsString('_bioland_v2_update_search_and_facets_config()', $hook, $number);
    }
  }

}
