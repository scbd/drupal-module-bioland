<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1312 embed frame size backfill.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedDimensionBackfillTest extends TestCase {

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
   * Builds an embed media double holding a frame item.
   */
  private function media(?string $width, ?string $height): object {
    $item = new class($width, $height) {
      public array $values;

      public function __construct($width, $height) {
        $this->values = ['width' => $width, 'height' => $height];
      }

      public function get($name) {
        $value = $this->values[$name];
        return new class($value) {
          public function __construct(private $value) {}

          public function getValue() {
            return $this->value;
          }
        };
      }

      public function set($name, $value) {
        $this->values[$name] = $value;
      }
    };
    return new class($item) {
      public int $saves = 0;

      public function __construct(public object $item) {}

      public function get($field) {
        $item = $this->item;
        return new class($item) {
          public function __construct(private object $item) {}

          public function first() {
            return $this->item;
          }
        };
      }

      public function save() {
        $this->saves++;
      }
    };
  }

  /**
   * Registers the site with the given media.
   */
  private function site(array $media, ?string $plugin = 'inline_frame', bool $module = TRUE): void {
    $query = new class(array_keys($media)) {
      public function __construct(private array $ids) {}

      public function accessCheck($check) {
        return $this;
      }

      public function condition($field, $value) {
        return $this;
      }

      public function execute() {
        return $this->ids;
      }
    };
    $media_storage = new class($query, $media) {
      public function __construct(private object $query, private array $media) {}

      public function getQuery() {
        return $this->query;
      }

      public function loadMultiple($ids) {
        return $this->media;
      }
    };
    $source = new class($plugin) {
      public function __construct(private ?string $plugin) {}

      public function getPluginId() {
        return $this->plugin;
      }

      public function getConfiguration() {
        return ['source_field' => 'field_media_inline_frame'];
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
    \Drupal::setService('entity_type.manager', new class($media_storage, $types) {
      public function __construct(private object $media, private object $types) {}

      public function getStorage($entity_type_id) {
        return $entity_type_id === 'media_type' ? $this->types : $this->media;
      }
    });
    \Drupal::setService('module_handler', new class($module) {
      public function __construct(private bool $module) {}

      public function moduleExists($name) {
        return $this->module;
      }
    });
  }

  /**
   * Only the empty dimension is filled; a stored value is never changed.
   */
  public function testFillsOnlyEmptyDimensions(): void {
    $media = [
      1 => $this->media(NULL, NULL),
      2 => $this->media('', '50%'),
      3 => $this->media('75%', ' '),
      4 => $this->media('75%', '50%'),
    ];
    $this->site($media);

    $this->assertSame('Set the default frame size on 3 embed media.', _bioland_backfill_embed_frame_dimensions());

    $this->assertSame(['width' => '100%', 'height' => '75%'], $media[1]->item->values);
    $this->assertSame(['width' => '100%', 'height' => '50%'], $media[2]->item->values);
    $this->assertSame(['width' => '75%', 'height' => '75%'], $media[3]->item->values);
    $this->assertSame(['width' => '75%', 'height' => '50%'], $media[4]->item->values);
    $this->assertSame([1, 1, 1, 0], array_map(fn ($m) => $m->saves, array_values($media)));
  }

  /**
   * Nothing to fill means no save, and a second run is quiet.
   */
  public function testNothingToFillSavesNothing(): void {
    $media = [1 => $this->media('75%', '50%')];
    $this->site($media);
    $this->assertSame('No embed media needed a default frame size.', _bioland_backfill_embed_frame_dimensions());
    $this->assertSame(0, $media[1]->saves);

    $media = [1 => $this->media(NULL, NULL)];
    $this->site($media);
    _bioland_backfill_embed_frame_dimensions();
    $this->assertSame('No embed media needed a default frame size.', _bioland_backfill_embed_frame_dimensions());
    $this->assertSame(1, $media[1]->saves);
  }

  /**
   * Without the inline-frame embed type nothing is touched.
   */
  public function testSkips(): void {
    foreach ([['inline_frame', FALSE], [NULL, TRUE], ['file', TRUE]] as [$plugin, $module]) {
      $media = [1 => $this->media(NULL, NULL)];
      $this->site($media, $plugin, $module);
      $this->assertStringContainsString('skipped', _bioland_backfill_embed_frame_dimensions());
      $this->assertSame(0, $media[1]->saves);
    }
  }

}
