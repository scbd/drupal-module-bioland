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
  private function registerDisplay(array $components, bool $existing = TRUE): object {
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
    \Drupal::setService('entity_type.manager', new class($storage) {
      public function __construct(private object $storage) {}

      public function getStorage($entity_type_id) {
        return $this->storage;
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
    $this->assertSame('iframe_default', $display->components['field_media_inline_frame']['type']);
    $this->assertSame('visually_hidden', $display->components['field_media_inline_frame']['label']);
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
   * The setup and hook 9106 both run it, and 9106 converges search last.
   */
  public function testWiring(): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/includes/bioland.install.editor.inc');
    $setup = substr($source, strpos($source, 'function _bioland_setup_embed_media('));
    $setup = substr($setup, 0, strpos($setup, "\n}\n"));
    $this->assertStringContainsString('_bioland_configure_embed_default_display()', $setup);
    $hook = substr($source, strpos($source, 'function bioland_update_9106('));
    $this->assertStringContainsString('_bioland_configure_embed_default_display()', $hook);
    $this->assertStringContainsString('_bioland_v2_update_search_and_facets_config()', $hook);
  }

}
