<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests adding the embed bundle to the media URL alias pattern (BL-1218).
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedAliasPatternTest extends TestCase {

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
   * A media bundle condition as pathauto stores it.
   */
  private static function condition(array $bundles, bool $negate = FALSE): array {
    return [
      'id' => 'entity_bundle:media',
      'negate' => $negate,
      'uuid' => 'c1',
      'context_mapping' => ['media' => 'media'],
      'bundles' => $bundles,
    ];
  }

  /**
   * The pure helper adds embed to limiting bundle conditions only.
   */
  public function testCriteriaWithEmbed(): void {
    $criteria = ['c1' => self::condition(['remote_video' => 'remote_video', 'image' => 'image'])];
    $this->assertSame(
      ['c1' => self::condition(['embed' => 'embed', 'image' => 'image', 'remote_video' => 'remote_video'])],
      _bioland_media_alias_criteria_with_embed($criteria)
    );
    $this->assertNull(_bioland_media_alias_criteria_with_embed(['c1' => self::condition(['embed' => 'embed'])]));
    $this->assertNull(_bioland_media_alias_criteria_with_embed(['c1' => self::condition(['hero' => 'hero'], TRUE)]));
    $this->assertNull(_bioland_media_alias_criteria_with_embed([]));
    $this->assertNull(_bioland_media_alias_criteria_with_embed(['c1' => ['id' => 'language', 'langcodes' => ['en' => 'en']]]));
  }

  /**
   * Registers the services the setup reads; returns the pattern objects.
   */
  private function setUpSite(array $modules, bool $embed_type, array $patterns, array $media = []): object {
    \Drupal::setService('module_handler', new class($modules) {
      public function __construct(private array $modules) {}

      public function moduleExists($module) {
        return in_array($module, $this->modules, TRUE);
      }
    });
    $storages = [
      'media_type' => new class($embed_type) {
        public function __construct(private bool $exists) {}

        public function load($id) {
          return $this->exists && $id === 'embed' ? new \stdClass() : NULL;
        }
      },
      'pathauto_pattern' => new class($patterns) {
        public function __construct(private array $patterns) {}

        public function loadByProperties(array $values) {
          return $values === ['type' => 'canonical_entities:media'] ? $this->patterns : [];
        }
      },
      'media' => new class($media) {
        public function __construct(public array $media) {}

        public function getQuery() {
          return new class(array_keys($this->media)) {
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
        }

        public function loadMultiple($ids) {
          return array_intersect_key($this->media, array_flip($ids));
        }
      },
    ];
    \Drupal::setService('entity_type.manager', new class($storages) {
      public function __construct(private array $storages) {}

      public function getStorage($id) {
        return $this->storages[$id];
      }
    });
    $generator = new class {
      public array $calls = [];

      public function updateEntityAlias($entity, $op) {
        $this->calls[] = [$entity->id, $op];
        return ['alias' => '/embed/x'];
      }
    };
    \Drupal::setService('pathauto.generator', $generator);
    return $generator;
  }

  /**
   * A pathauto pattern entity double.
   */
  private function pattern(string $id, string $pattern, array $criteria): object {
    return new class($id, $pattern, $criteria) {
      public bool $saved = FALSE;

      public function __construct(private string $id, private string $pattern, public array $criteria) {}

      public function id() {
        return $this->id;
      }

      public function getPattern() {
        return $this->pattern;
      }

      public function get($key) {
        return $key === 'selection_criteria' ? $this->criteria : NULL;
      }

      public function set($key, $value) {
        $this->criteria = $value;
        return $this;
      }

      public function save() {
        $this->saved = TRUE;
      }
    };
  }

  /**
   * An embed media double with one translation.
   */
  private function media(int $id): object {
    return new class($id) {
      public function __construct(public int $id) {}

      public function getTranslationLanguages() {
        return ['en' => NULL];
      }

      public function getTranslation($langcode) {
        return $this;
      }
    };
  }

  /**
   * The media pattern gains embed and existing embeds are aliased.
   */
  public function testAddsEmbedAndAliasesExisting(): void {
    $media_pattern = $this->pattern('media', ' /[media:bundle]/[media:name] ', ['c1' => self::condition(['image' => 'image'])]);
    $other = $this->pattern('media_by_id', '/media/[media:mid]', ['c1' => self::condition(['image' => 'image'])]);
    $generator = $this->setUpSite(['pathauto'], TRUE, ['media' => $media_pattern, 'media_by_id' => $other], [5 => $this->media(5), 9 => $this->media(9)]);

    $message = _bioland_add_embed_to_media_alias_pattern();

    $this->assertTrue($media_pattern->saved);
    $this->assertSame(['embed' => 'embed', 'image' => 'image'], $media_pattern->criteria['c1']['bundles']);
    $this->assertFalse($other->saved);
    $this->assertSame([[5, 'bulkupdate'], [9, 'bulkupdate']], $generator->calls);
    $this->assertStringContainsString('Added embed to media URL alias pattern(s): media.', $message);
    $this->assertStringContainsString('Generated 2 embed media URL alias(es).', $message);
  }

  /**
   * A second run changes nothing but still aliases stragglers.
   */
  public function testIdempotent(): void {
    $media_pattern = $this->pattern('media', '/[media:bundle]/[media:name]', ['c1' => self::condition(['embed' => 'embed'])]);
    $this->setUpSite(['pathauto'], TRUE, ['media' => $media_pattern]);
    $this->assertStringContainsString('already covers embed', _bioland_add_embed_to_media_alias_pattern());
    $this->assertFalse($media_pattern->saved);
  }

  /**
   * Skips without pathauto, without the embed type, or without the pattern.
   */
  public function testSkips(): void {
    $this->setUpSite([], TRUE, []);
    $this->assertStringContainsString('pathauto is not enabled', _bioland_add_embed_to_media_alias_pattern());
    $this->setUpSite(['pathauto'], FALSE, []);
    $this->assertStringContainsString('Media type embed does not exist', _bioland_add_embed_to_media_alias_pattern());
    $this->setUpSite(['pathauto'], TRUE, ['x' => $this->pattern('x', '/media/[media:mid]', [])]);
    $this->assertStringContainsString('No media URL alias pattern', _bioland_add_embed_to_media_alias_pattern());
  }

  /**
   * The setup and hook 9102 both run it; the hook converges last.
   */
  public function testWiring(): void {
    $source = file_get_contents(__DIR__ . '/../../includes/bioland.install.editor.inc');
    $setup = substr($source, strpos($source, 'function _bioland_setup_embed_media('));
    $this->assertMatchesRegularExpression('/^[^}]*_bioland_add_embed_to_media_alias_pattern\(\)/', $setup);
    $hook = substr($source, strpos($source, 'function bioland_update_9102('));
    $this->assertLessThan(strpos($hook, '_bioland_v2_update_search_and_facets_config()'), strpos($hook, '_bioland_add_embed_to_media_alias_pattern()'));
  }

}
