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
  private function setUpSite(array $modules, bool $embed_type, array $patterns, array $media = [], array $aliased = []): object {
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
      public array $states = [];

      public function updateEntityAlias($entity, $op, array $options = []) {
        $this->calls[] = [$entity->id, $op, $options];
        return ['alias' => '/embed/x'];
      }

      public function get($collection) {
        $generator = $this;
        return new class($generator, $collection) {
          public function __construct(private object $generator, private string $collection) {}

          public function set($key, $value) {
            $this->generator->states[$this->collection][$key] = $value;
          }
        };
      }
    };
    \Drupal::setService('pathauto.generator', $generator);
    \Drupal::setService('keyvalue', $generator);
    \Drupal::setService('path_alias.repository', new class($aliased) {
      public function __construct(private array $aliased) {}

      public function lookupBySystemPath($path, $langcode) {
        return in_array($path, $this->aliased, TRUE) ? ['alias' => '/x'] : NULL;
      }
    });
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

      public function id() {
        return $this->id;
      }

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
    $generator = $this->setUpSite(['pathauto'], TRUE, ['media' => $media_pattern, 'media_by_id' => $other], [5 => $this->media(5), 9 => $this->media(9), 11 => $this->media(11)], ['/media/11']);

    $message = _bioland_add_embed_to_media_alias_pattern();

    $this->assertTrue($media_pattern->saved);
    $this->assertSame(['embed' => 'embed', 'image' => 'image'], $media_pattern->criteria['c1']['bundles']);
    $this->assertFalse($other->saved);
    // Forced past the SKIP state pathauto stored; 11 keeps its own alias.
    $this->assertSame([[5, 'bulkupdate', ['force' => TRUE]], [9, 'bulkupdate', ['force' => TRUE]]], $generator->calls);
    $this->assertSame(['5' => 1, '9' => 1], $generator->states['pathauto_state.media']);
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
   * The setup and hook 9105 both run it; the hook converges last.
   */
  public function testWiring(): void {
    $source = file_get_contents(__DIR__ . '/../../includes/bioland.install.editor.inc');
    $setup = substr($source, strpos($source, 'function _bioland_setup_embed_media('));
    $this->assertMatchesRegularExpression('/^[^}]*_bioland_add_embed_to_media_alias_pattern\(\)/', $setup);
    $hook = substr($source, strpos($source, 'function bioland_update_9105('));
    $this->assertLessThan(strpos($hook, '_bioland_v2_update_search_and_facets_config()'), strpos($hook, '_bioland_add_embed_to_media_alias_pattern()'));
  }

  /**
   * Enabling pathauto after the embed type still converges.
   */
  public function testPathautoEnableTriggersPattern(): void {
    $source = file_get_contents(__DIR__ . '/../../bioland.module');
    $hook = substr($source, strpos($source, 'function bioland_modules_installed('));
    $hook = substr($hook, 0, strpos($hook, "\n}\n"));
    $this->assertMatchesRegularExpression("/in_array\\('pathauto', \\\$modules, TRUE\\)\\) \\{[^}]*_bioland_add_embed_to_media_alias_pattern\\(\\);/", $hook);
  }

}
