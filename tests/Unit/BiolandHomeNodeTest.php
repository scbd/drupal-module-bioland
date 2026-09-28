<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the editable home node helper shared by install and update 9100.
 *
 * The suite has no Drupal kernel, so entities, storages, config and services
 * are small in-memory fakes that expose only the entity API calls the helper
 * makes. The fakes record saves, so "nothing changed" is asserted directly.
 *
 * @group bioland
 */
class BiolandHomeNodeTest extends TestCase
{
    /**
     * Fake storages keyed by entity type.
     *
     * @var \Drupal\Tests\bioland\Unit\HomeNodeFakeStorage[]
     */
    private array $storages = [];

    /**
     * Fake config objects keyed by name.
     *
     * @var \Drupal\Tests\bioland\Unit\HomeNodeFakeConfig[]
     */
    private array $configs = [];

    /**
     * Installed langcodes.
     *
     * @var string[]
     */
    private array $languages = [];

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../includes/bioland.install.home_node.inc';
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['node', 'node_type', 'taxonomy_term', 'media'] as $type) {
            $this->storages[$type] = new HomeNodeFakeStorage();
        }
        $this->storages['node_type']->add(new HomeNodeFakeEntity('content', 'x', 'node_type'));

        // A bl2 site on the legacy taxonomy-term home.
        $this->configs['system.site'] = new HomeNodeFakeConfig(['page.front' => '/home']);
        $this->configs['bioland.settings'] = new HomeNodeFakeConfig([]);

        $basicPage = new HomeNodeFakeEntity(7, BIOLAND_HOME_NODE_BASIC_PAGE_TAG_UUID, 'tags', 'Basic Page');
        $article = new HomeNodeFakeEntity(8, 'article-uuid', 'tags', 'Article');
        $legacyHome = new HomeNodeFakeEntity(20, 'term-20', 'system_pages', 'Home', [
            'field_attachments' => [['target_id' => 31], ['target_id' => 32], ['target_id' => 33]],
        ]);
        $this->storages['taxonomy_term']->add($basicPage)->add($article)->add($legacyHome);

        foreach ([31, 32, 33] as $mid) {
            $this->storages['media']->add(new HomeNodeFakeEntity($mid, "media-$mid", 'image'));
        }

        $this->languages = ['en', 'fr', 'zh-hans', 'fil', 'xx-lolspeak', 'und'];

        $storages = &$this->storages;
        \Drupal::setService('entity_type.manager', new class($storages) {
            private $storages;

            public function __construct(array &$storages)
            {
                $this->storages = &$storages;
            }

            public function getStorage($type)
            {
                return $this->storages[$type];
            }
        });

        $configs = &$this->configs;
        \Drupal::setService('config.factory', new class($configs) {
            private $configs;

            public function __construct(array &$configs)
            {
                $this->configs = &$configs;
            }

            public function get($name)
            {
                return $this->configs[$name];
            }

            public function getEditable($name)
            {
                return $this->configs[$name];
            }
        });

        \Drupal::setService('path_alias.manager', new class {
            public function getPathByAlias($alias)
            {
                return $alias === '/home' ? '/taxonomy/term/20' : $alias;
            }
        });

        $languages = &$this->languages;
        \Drupal::setService('language_manager', new class($languages) {
            private $languages;

            public function __construct(array &$languages)
            {
                $this->languages = &$languages;
            }

            public function getLanguages()
            {
                return array_fill_keys($this->languages, TRUE);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        \Drupal::resetContainer();
        parent::tearDown();
    }

    /**
     * The home node as the helper left it.
     */
    private function homeNode(): ?HomeNodeFakeEntity
    {
        return $this->storages['node']->load(BIOLAND_HOME_NODE_NID);
    }

    /**
     * A fresh site gets node 1000 with every piece and the front page moves.
     */
    public function testCreatesTheHomeNode(): void
    {
        $message = _bioland_ensure_home_node();

        $node = $this->homeNode();
        $this->assertNotNull($node, $message);
        $this->assertSame(BIOLAND_HOME_NODE_UUID, $node->uuid());
        $this->assertSame('content', $node->values['type']);
        $this->assertSame('Home', $node->values['title']);
        $this->assertSame('en', $node->values['langcode']);
        $this->assertTrue($node->enforcedNew, 'An explicit nid must be saved as a new node.');
        $this->assertSame(1, $node->saves);
        $this->assertSame([['target_id' => 7]], $node->fields['field_type_placement']);
        $this->assertSame([['target_id' => 31], ['target_id' => 32], ['target_id' => 33]], $node->fields['field_attachments']);
        $this->assertSame('/node/1000', $this->configs['system.site']->get('page.front'));
        $this->assertSame(1, $this->configs['system.site']->saves);
        $this->assertArrayNotHasKey('body', $node->fields);
    }

    /**
     * Running twice changes nothing the second time.
     */
    public function testReRunIsANoOp(): void
    {
        _bioland_ensure_home_node();
        $node = $this->homeNode();
        $translations = $node->translations;

        $message = _bioland_ensure_home_node();

        $this->assertSame(1, $node->saves, 'A re-run must not save the node again.');
        $this->assertSame(1, $this->configs['system.site']->saves, 'A re-run must not save system.site again.');
        $this->assertSame($translations, $node->translations);
        $this->assertStringContainsString('already complete', $message);
    }

    /**
     * An editor's attachments are never replaced by a re-run.
     */
    public function testReRunKeepsEditedAttachments(): void
    {
        _bioland_ensure_home_node();
        $node = $this->homeNode();
        $node->fields['field_attachments'] = [['target_id' => 33]];

        _bioland_ensure_home_node();

        $this->assertSame([['target_id' => 33]], $node->fields['field_attachments']);
    }

    /**
     * Node 1000 with another uuid leaves the site untouched.
     */
    public function testNidConflictChangesNothing(): void
    {
        $other = new HomeNodeFakeEntity(1000, 'someone-else', 'content');
        $this->storages['node']->add($other);

        $message = _bioland_ensure_home_node();

        $this->assertStringContainsString('someone-else', $message);
        $this->assertStringContainsString(BIOLAND_HOME_NODE_UUID, $message);
        $this->assertSame(0, $other->saves);
        $this->assertSame('/home', $this->configs['system.site']->get('page.front'));
        $this->assertSame(0, $this->configs['system.site']->saves);
    }

    /**
     * The home uuid on another nid leaves the site untouched.
     */
    public function testUuidConflictChangesNothing(): void
    {
        $this->storages['node']->add(new HomeNodeFakeEntity(11000, BIOLAND_HOME_NODE_UUID, 'content'));

        $message = _bioland_ensure_home_node();

        $this->assertStringContainsString('node 11000', $message);
        $this->assertNull($this->homeNode());
        $this->assertSame(0, $this->configs['system.site']->saves);
    }

    /**
     * A missing placement tag is skipped with a warning, never guessed.
     */
    public function testMissingPlacementTagIsSkipped(): void
    {
        $this->storages['taxonomy_term']->remove(7);

        $message = _bioland_ensure_home_node();

        $this->assertStringContainsString('placement tag skipped', $message);
        $this->assertArrayNotHasKey('field_type_placement', $this->homeNode()->fields);
        $this->assertSame('/node/1000', $this->configs['system.site']->get('page.front'));
    }

    /**
     * Biosafety Land uses the single exactly-named "Article" tag.
     */
    public function testBiosafetyLandUsesTheArticleTag(): void
    {
        $this->configs['bioland.settings']->set('is_biosafety_land', TRUE);

        _bioland_ensure_home_node();

        $this->assertSame([['target_id' => 8]], $this->homeNode()->fields['field_type_placement']);
    }

    /**
     * Two "Article" tags on Biosafety Land are ambiguous: skipped.
     */
    public function testBiosafetyLandAmbiguousArticleTagIsSkipped(): void
    {
        $this->configs['bioland.settings']->set('is_biosafety_land', TRUE);
        $this->storages['taxonomy_term']->add(new HomeNodeFakeEntity(9, 'article-2', 'tags', 'Article'));

        $message = _bioland_ensure_home_node();

        $this->assertStringContainsString('2 terms match', $message);
        $this->assertArrayNotHasKey('field_type_placement', $this->homeNode()->fields);
    }

    /**
     * Attachments keep their order and deleted media are dropped.
     */
    public function testAttachmentCopyDropsDeletedMedia(): void
    {
        $this->storages['media']->remove(32);

        $message = _bioland_ensure_home_node();

        $this->assertSame([['target_id' => 31], ['target_id' => 33]], $this->homeNode()->fields['field_attachments']);
        $this->assertStringContainsString('deleted media 32', $message);
    }

    /**
     * When the front page is already node 1000, the legacy Home term is the source.
     */
    public function testAttachmentSourceFallsBackToLegacyHomeTerm(): void
    {
        $this->configs['system.site']->set('page.front', '/node/1000');

        _bioland_ensure_home_node();

        $this->assertSame([['target_id' => 31], ['target_id' => 32], ['target_id' => 33]], $this->homeNode()->fields['field_attachments']);
        $this->assertSame(0, $this->configs['system.site']->saves);
    }

    /**
     * Translations: mapped codes added, excluded and unmapped ones skipped.
     */
    public function testTranslationsAreAddedAndSkipped(): void
    {
        $this->languages[] = 'no';

        $message = _bioland_ensure_home_node();
        $translations = $this->homeNode()->translations;

        $this->assertSame('Accueil', $translations['fr']['title']);
        $this->assertSame('主页', $translations['zh-hans']['title']);
        $this->assertSame('Bahay', $translations['fil']['title']);
        $this->assertArrayNotHasKey('xx-lolspeak', $translations);
        $this->assertArrayNotHasKey('und', $translations);
        $this->assertArrayNotHasKey('en', $translations);
        $this->assertArrayNotHasKey('no', $translations);
        $this->assertStringContainsString('no "Home" title for no', $message);
    }

    /**
     * A language that already has a translation keeps it.
     */
    public function testExistingTranslationIsKept(): void
    {
        $node = new HomeNodeFakeEntity(1000, BIOLAND_HOME_NODE_UUID, 'content');
        $node->translations['fr'] = ['title' => 'Page d\'accueil'];
        $this->storages['node']->add($node);

        _bioland_ensure_home_node();

        $this->assertSame('Page d\'accueil', $node->translations['fr']['title']);
        $this->assertSame('主页', $node->translations['zh-hans']['title']);
        $this->assertSame(1, $node->saves);
    }

    /**
     * A failure is returned as a message, never thrown.
     */
    public function testFailureIsReturnedNotThrown(): void
    {
        $this->storages['node']->failOnSave = TRUE;

        $message = _bioland_ensure_home_node();

        $this->assertStringContainsString('failed', $message);
        $this->assertSame('/home', $this->configs['system.site']->get('page.front'));
    }

    /**
     * The title map mirrors dmsm mapLocaleFromDrupal and the head locales.
     */
    public function testTitleMapAndLocaleMapping(): void
    {
        $this->assertSame('zh', _bioland_home_node_locale_from_drupal('zh-hans'));
        $this->assertSame('tl', _bioland_home_node_locale_from_drupal('fil'));
        $this->assertSame('xx', _bioland_home_node_locale_from_drupal('xx-lolspeak'));
        $this->assertSame('de', _bioland_home_node_locale_from_drupal('de'));

        $map = _bioland_home_node_title_map();
        $this->assertCount(79, $map, '80 head locales carry "Home"; en is the source.');
        $this->assertArrayNotHasKey('en', $map);
        $this->assertSame('Heim', $map['de']);
        $this->assertNull(_bioland_home_node_title_for('ps'));
    }
}

/**
 * In-memory entity storage exposing the calls the helper makes.
 */
class HomeNodeFakeStorage
{
    /**
     * Entities keyed by id.
     *
     * @var \Drupal\Tests\bioland\Unit\HomeNodeFakeEntity[]
     */
    public array $entities = [];

    /**
     * Make save() on created entities throw.
     */
    public bool $failOnSave = FALSE;

    public function add(HomeNodeFakeEntity $entity): self
    {
        $entity->storage = $this;
        $this->entities[$entity->id()] = $entity;
        return $this;
    }

    public function remove($id): void
    {
        unset($this->entities[$id]);
    }

    public function load($id)
    {
        return $this->entities[$id] ?? NULL;
    }

    public function loadMultiple(array $ids)
    {
        return array_intersect_key($this->entities, array_flip($ids));
    }

    public function loadByProperties(array $values)
    {
        return array_filter($this->entities, static function (HomeNodeFakeEntity $entity) use ($values) {
            foreach ($values as $key => $value) {
                $actual = match ($key) {
                    'uuid' => $entity->uuid(),
                    'vid' => $entity->bundle(),
                    // SQL name matching is case-insensitive.
                    'name' => strtolower((string) $entity->label()),
                };
                if ($actual !== ($key === 'name' ? strtolower($value) : $value)) {
                    return FALSE;
                }
            }
            return TRUE;
        });
    }

    public function create(array $values)
    {
        $entity = new HomeNodeFakeEntity($values['nid'], $values['uuid'], $values['type'], $values['title']);
        $entity->values = $values;
        $entity->storage = $this;
        $entity->pendingNew = TRUE;
        return $entity;
    }

    public function persist(HomeNodeFakeEntity $entity): void
    {
        if ($this->failOnSave) {
            throw new \RuntimeException('database went away');
        }
        $this->entities[$entity->id()] = $entity;
    }
}

/**
 * A field item list exposing isEmpty() and getValue().
 */
class HomeNodeFakeFieldList
{
    public function __construct(private array $items)
    {
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getValue(): array
    {
        return $this->items;
    }
}

/**
 * A node, term, media or node type with fields and translations.
 */
class HomeNodeFakeEntity
{
    public array $values = [];
    public array $translations = [];
    public int $saves = 0;
    public bool $enforcedNew = FALSE;
    public bool $pendingNew = FALSE;
    public ?HomeNodeFakeStorage $storage = NULL;

    /**
     * Field values; a field is present when its key exists.
     */
    public array $fields = [];

    /**
     * Fields defined on this bundle.
     */
    private array $defined = ['field_type_placement', 'field_attachments', 'body'];

    public function __construct(private $id, private string $uuid, private string $bundle, private ?string $label = NULL, array $fields = [])
    {
        $this->fields = $fields;
    }

    public function id()
    {
        return $this->id;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function bundle(): string
    {
        return $this->bundle;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function hasField(string $name): bool
    {
        return in_array($name, $this->defined, TRUE);
    }

    public function get(string $name): HomeNodeFakeFieldList
    {
        return new HomeNodeFakeFieldList($this->fields[$name] ?? []);
    }

    public function set(string $name, $value): self
    {
        $this->fields[$name] = $value;
        return $this;
    }

    public function hasTranslation(string $langcode): bool
    {
        return $langcode === 'en' || isset($this->translations[$langcode]);
    }

    public function addTranslation(string $langcode, array $values): self
    {
        $this->translations[$langcode] = $values;
        return $this;
    }

    public function enforceIsNew(bool $value = TRUE): self
    {
        $this->enforcedNew = $value;
        return $this;
    }

    public function save(): int
    {
        $this->storage?->persist($this);
        $this->saves++;
        return 1;
    }
}

/**
 * A config object with get/set/save, counting saves.
 */
class HomeNodeFakeConfig
{
    public int $saves = 0;

    public function __construct(private array $data)
    {
    }

    public function get($key)
    {
        return $this->data[$key] ?? NULL;
    }

    public function set($key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function save(): self
    {
        $this->saves++;
        return $this;
    }
}
