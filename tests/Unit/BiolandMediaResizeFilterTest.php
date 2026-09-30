<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Covers _bioland_enable_media_resize_filter() (BL-917).
 *
 * ckeditor_media_resizer stores an editor-chosen size as data-media-width,
 * which only reaches the rendered HTML through its media_resize filter. The
 * helper turns that filter on, after every other filter, on each format that
 * embeds media.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandMediaResizeFilterTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    require_once dirname(__DIR__, 2) . '/includes/bioland.install.editor.inc';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Registers the module handler and a filter_format storage fake.
   *
   * @param array $formats
   *   Format id => filters config.
   * @param bool $resizer_enabled
   *   Whether ckeditor_media_resizer is enabled.
   *
   * @return object[]
   *   The format fakes, keyed by id.
   */
  private function stubFormats(array $formats, bool $resizer_enabled = TRUE): array {
    \Drupal::setService('module_handler', new class($resizer_enabled) {

      public function __construct(private bool $enabled) {}

      public function moduleExists($module) {
        return $module === 'ckeditor_media_resizer' && $this->enabled;
      }

    });

    $fakes = [];
    foreach ($formats as $id => $filters) {
      $fakes[$id] = new class($id, $filters) {

        public int $saved = 0;

        public function __construct(private string $formatId, public array $filters) {}

        public function id() {
          return $this->formatId;
        }

        public function get($key) {
          return $key === 'filters' ? $this->filters : NULL;
        }

        public function setFilterConfig($instance_id, array $configuration) {
          $this->filters[$instance_id] = $configuration;
          return $this;
        }

        public function save() {
          $this->saved++;
        }

      };
    }

    $storage = new class($fakes) {

      public function __construct(private array $formats) {}

      public function loadMultiple() {
        return $this->formats;
      }

    };
    \Drupal::setService('entity_type.manager', new class($storage) {

      public function __construct(private object $storage) {}

      public function getStorage($entity_type) {
        return $entity_type === 'filter_format' ? $this->storage : NULL;
      }

    });

    return $fakes;
  }

  /**
   * The filter is added after every other filter on media formats only.
   */
  public function testEnablesFilterAfterEveryFilterOnMediaFormats(): void {
    $formats = $this->stubFormats([
      'full_html' => [
        'filter_align' => ['status' => TRUE, 'weight' => 8],
        'media_embed' => ['status' => TRUE, 'weight' => 100],
        'linkit' => ['status' => TRUE, 'weight' => 101],
      ],
      'plain_text' => [
        'filter_html_escape' => ['status' => TRUE, 'weight' => -10],
      ],
      'restricted' => [
        'media_embed' => ['status' => FALSE, 'weight' => 100],
      ],
    ]);

    $message = _bioland_enable_media_resize_filter();

    $this->assertSame(['status' => TRUE, 'weight' => 102, 'settings' => []], $formats['full_html']->filters['media_resize']);
    $this->assertSame(1, $formats['full_html']->saved);
    $this->assertArrayNotHasKey('media_resize', $formats['plain_text']->filters);
    $this->assertArrayNotHasKey('media_resize', $formats['restricted']->filters);
    $this->assertSame(0, $formats['plain_text']->saved + $formats['restricted']->saved);
    $this->assertSame('Enabled the media_resize filter on: full_html.', $message);
  }

  /**
   * A format already running the filter after media_embed is not re-saved.
   */
  public function testLeavesCorrectlyOrderedFilterAlone(): void {
    $formats = $this->stubFormats([
      'full_html' => [
        'media_embed' => ['status' => TRUE, 'weight' => 100],
        'media_resize' => ['status' => TRUE, 'weight' => 150],
      ],
    ]);

    $message = _bioland_enable_media_resize_filter();

    $this->assertSame(0, $formats['full_html']->saved);
    $this->assertSame(150, $formats['full_html']->filters['media_resize']['weight']);
    $this->assertStringContainsString('already runs after media_embed', $message);
  }

  /**
   * A disabled or misordered filter is enabled and moved last.
   */
  public function testFixesDisabledOrMisorderedFilter(): void {
    $formats = $this->stubFormats([
      'disabled' => [
        'media_embed' => ['status' => TRUE, 'weight' => 100],
        'media_resize' => ['status' => FALSE, 'weight' => 200],
      ],
      'misordered' => [
        'media_resize' => ['status' => TRUE, 'weight' => 10],
        'media_embed' => ['status' => TRUE, 'weight' => 100],
      ],
    ]);

    _bioland_enable_media_resize_filter();

    $this->assertSame(['status' => TRUE, 'weight' => 101, 'settings' => []], $formats['disabled']->filters['media_resize']);
    $this->assertSame(['status' => TRUE, 'weight' => 101, 'settings' => []], $formats['misordered']->filters['media_resize']);
    $this->assertSame(1, $formats['disabled']->saved);
    $this->assertSame(1, $formats['misordered']->saved);
  }

  /**
   * Nothing is touched while ckeditor_media_resizer is off.
   */
  public function testSkipsWhenModuleIsOff(): void {
    $formats = $this->stubFormats([
      'full_html' => ['media_embed' => ['status' => TRUE, 'weight' => 100]],
    ], FALSE);

    $message = _bioland_enable_media_resize_filter();

    $this->assertSame(0, $formats['full_html']->saved);
    $this->assertStringContainsString('not enabled', $message);
  }

}
