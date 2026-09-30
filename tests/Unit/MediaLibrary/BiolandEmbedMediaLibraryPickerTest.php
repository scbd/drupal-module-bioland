<?php

namespace Drupal\Tests\bioland\Unit\MediaLibrary;

use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1272 embed picker helpers.
 *
 * Covers removing the hand-added Media type filter from the media_library
 * widget views, and laying out the embed type's media_library view display.
 *
 * @group bioland
 */
class BiolandEmbedMediaLibraryPickerTest extends TestCase {

  /**
   * Config objects keyed by name, returned by the stubbed config factory.
   *
   * @var \Drupal\Core\Config\Config[]
   */
  private array $configs = [];

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../includes/bioland.install.views.inc';
    require_once __DIR__ . '/../../../includes/bioland.install.editor.inc';
    require_once __DIR__ . '/../../../includes/bioland.install.helpers.inc';
  }

  protected function setUp(): void {
    parent::setUp();
    $this->configs = [];

    \Drupal::setService('module_handler', new class {
      public function moduleExists($module) {
        return TRUE;
      }
    });

    $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $factory->method('getEditable')->willReturnCallback(function ($name) {
      return $this->config($name);
    });
    \Drupal::setService('config.factory', $factory);

    \Drupal::setService('views.views_data', new class {
      public function clear() {}
    });

    $storage = new class {
      public function load($id) {
        return (object) ['id' => $id];
      }
    };
    \Drupal::setService('entity_type.manager', new class($storage) {
      public function __construct(private object $storage) {}

      public function getStorage($entity_type_id) {
        return $this->storage;
      }
    });
  }

  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  private function config(string $name, array $data = []): \Drupal\Core\Config\Config {
    if (!isset($this->configs[$name])) {
      $config = new \Drupal\Core\Config\Config($name, $data);
      $config->isNew = empty($data);
      $this->configs[$name] = $config;
    }
    return $this->configs[$name];
  }

  /**
   * A media type filter shaped the way the Views UI stores it.
   */
  private function mediaTypeFilter(string $key, bool $exposed = FALSE): array {
    return [
      'id' => $key,
      'table' => 'media_field_data',
      'field' => 'bundle',
      'plugin_id' => 'bundle',
      'entity_type' => 'media',
      'entity_field' => 'bundle',
      'operator' => 'in',
      'value' => ['document' => 'document', 'image' => 'image'],
      'exposed' => $exposed,
    ];
  }

  private function baseFilters(): array {
    return [
      'status' => ['id' => 'status', 'field' => 'status', 'plugin_id' => 'boolean'],
      'name' => ['id' => 'name', 'field' => 'name', 'plugin_id' => 'string', 'exposed' => TRUE],
      'default_langcode' => ['id' => 'default_langcode', 'field' => 'default_langcode', 'plugin_id' => 'boolean'],
      'langcode' => ['id' => 'langcode', 'field' => 'langcode', 'plugin_id' => 'language'],
    ];
  }

  private function viewWithTypeFilter(bool $with, string $key = 'bundle'): array {
    $filters = $this->baseFilters();
    if ($with) {
      $filters[$key] = $this->mediaTypeFilter($key);
    }
    $display = [
      'display_options' => [
        'filters' => $filters,
        'arguments' => ['bundle' => ['id' => 'bundle']],
      ],
    ];
    $default = [
      'display_options' => [
        'filters' => $this->baseFilters() + ['bundle' => $this->mediaTypeFilter('bundle', TRUE)],
      ],
    ];
    return ['display' => ['default' => $default, 'widget' => $display, 'widget_table' => $display]];
  }

  public function testRemovesTypeFilterFromBothDisplays(): void {
    $this->config('views.view.media_library', $this->viewWithTypeFilter(TRUE));

    $message = _bioland_remove_media_library_type_filter();

    $config = $this->config('views.view.media_library');
    foreach (['widget', 'widget_table'] as $id) {
      $filters = $config->get("display.$id.display_options.filters");
      $this->assertArrayNotHasKey('bundle', $filters);
      $this->assertSame(['status', 'name', 'default_langcode', 'langcode'], array_keys($filters));
      $this->assertArrayHasKey('bundle', $config->get("display.$id.display_options.arguments"));
    }
    // The exposed Media type filter on the default display restricts nothing
    // and must survive.
    $this->assertArrayHasKey('bundle', $config->get('display.default.display_options.filters'));
    $this->assertTrue($config->saved);
    $this->assertStringContainsString('Removed', $message);
    $this->assertStringContainsString('widget, widget_table', $message);
  }

  public function testRemovesTypeFilterStoredUnderDuplicateKey(): void {
    $this->config('views.view.media_library', $this->viewWithTypeFilter(TRUE, 'bundle_1'));

    _bioland_remove_media_library_type_filter();

    $filters = $this->config('views.view.media_library')->get('display.widget.display_options.filters');
    $this->assertArrayNotHasKey('bundle_1', $filters);
    $this->assertSame(['status', 'name', 'default_langcode', 'langcode'], array_keys($filters));
  }

  public function testRemovesForcedTypeFilterInheritedFromDefaultDisplay(): void {
    $view = $this->viewWithTypeFilter(FALSE);
    $view['display']['default']['display_options']['filters']['bundle'] = $this->mediaTypeFilter('bundle');
    $this->config('views.view.media_library', $view);

    $message = _bioland_remove_media_library_type_filter();

    $config = $this->config('views.view.media_library');
    $this->assertArrayNotHasKey('bundle', $config->get('display.default.display_options.filters'));
    $this->assertStringContainsString('default', $message);
  }

  public function testRemovesExposedTypeFilterWithStoredValueFromWidgetOnly(): void {
    $view = $this->viewWithTypeFilter(FALSE);
    $view['display']['widget']['display_options']['filters']['bundle'] = $this->mediaTypeFilter('bundle', TRUE);
    $this->config('views.view.media_library', $view);

    $message = _bioland_remove_media_library_type_filter();

    $config = $this->config('views.view.media_library');
    $this->assertArrayNotHasKey('bundle', $config->get('display.widget.display_options.filters'));
    $this->assertArrayHasKey('bundle', $config->get('display.default.display_options.filters'));
    $this->assertStringContainsString('widget', $message);
    $this->assertStringNotContainsString('default', $message);
  }

  public function testTypeFilterRemovalIsNoOpWhenAbsent(): void {
    $this->config('views.view.media_library', $this->viewWithTypeFilter(FALSE));

    $message = _bioland_remove_media_library_type_filter();

    $this->assertFalse($this->config('views.view.media_library')->saved);
    $this->assertStringContainsString('already', $message);
  }

  public function testForcedTypeFilterDetection(): void {
    $this->assertTrue(_bioland_is_forced_media_type_filter($this->mediaTypeFilter('bundle')));
    $this->assertTrue(_bioland_is_forced_media_type_filter($this->mediaTypeFilter('bundle_1')));
    $this->assertTrue(_bioland_is_forced_media_type_filter(['id' => 'type', 'value' => ['document' => 'document']]));
    $this->assertFalse(_bioland_is_forced_media_type_filter($this->mediaTypeFilter('bundle', TRUE)));
    $this->assertTrue(_bioland_is_forced_media_type_filter($this->mediaTypeFilter('bundle', TRUE), TRUE));
    $exposed_empty = ['value' => []] + $this->mediaTypeFilter('bundle', TRUE);
    $this->assertFalse(_bioland_is_forced_media_type_filter($exposed_empty, TRUE));
    $this->assertFalse(_bioland_is_forced_media_type_filter(['table' => 'node_field_data'] + $this->mediaTypeFilter('bundle')));
    $this->assertFalse(_bioland_is_forced_media_type_filter(['id' => 'status', 'field' => 'status', 'plugin_id' => 'boolean']));
    $this->assertFalse(_bioland_is_forced_media_type_filter('not-an-array'));
  }

  public function testTypeFilterRemovalSkipsWhenViewNotFound(): void {
    $this->assertStringContainsString('not found', _bioland_remove_media_library_type_filter());
  }

  public function testConfiguresEmbedLibraryDisplayOnce(): void {
    $message = _bioland_configure_embed_media_library_display();

    $config = $this->config('core.entity_view_display.media.embed.media_library');
    $this->assertTrue($config->saved);
    $this->assertStringContainsString('Laid out', $message);
    $content = $config->get('content');
    $this->assertSame(['field_media_inline_frame', 'name'], array_keys($content));
    $this->assertSame('visually_hidden', $content['field_media_inline_frame']['label']);
    $this->assertSame('iframe_only', $content['field_media_inline_frame']['type']);
    $this->assertSame('hidden', $content['name']['label']);
    $this->assertSame('string', $content['name']['type']);
    $this->assertContains('core.entity_view_mode.media.media_library', $config->get('dependencies.config'));
    $hidden = $config->get('hidden');
    $this->assertArrayNotHasKey('name', $hidden);
    foreach (['langcode', 'created', 'uid', 'thumbnail'] as $field) {
      $this->assertTrue($hidden[$field], $field);
    }
    $this->assertSame('media.embed.media_library', $config->get('id'));

    // The stub does not flip isNew on save; model the persisted config.
    $config->isNew = FALSE;
    $config->saved = FALSE;
    $message = _bioland_configure_embed_media_library_display();
    $this->assertFalse($config->saved);
    $this->assertStringContainsString('already', $message);
  }

  public function testEmbedLibraryDisplayHidesStrayComponentsAndKeepsSettings(): void {
    $this->config('core.entity_view_display.media.embed.media_library', [
      'content' => [
        'field_media_inline_frame' => [
          'type' => 'iframe_default',
          'label' => 'hidden',
          'weight' => 3,
          'region' => 'content',
          'settings' => ['url' => '0'],
          'third_party_settings' => [],
        ],
        'name' => ['type' => 'string', 'label' => 'above', 'region' => 'content', 'weight' => 5],
        'created' => ['type' => 'timestamp', 'label' => 'hidden', 'region' => 'content'],
      ],
      'hidden' => ['thumbnail' => TRUE],
    ]);

    _bioland_configure_embed_media_library_display();

    $config = $this->config('core.entity_view_display.media.embed.media_library');
    $content = $config->get('content');
    $this->assertSame(['field_media_inline_frame', 'name'], array_keys($content));
    $this->assertSame('hidden', $content['name']['label']);
    $this->assertSame(5, $content['name']['weight']);
    $this->assertSame('visually_hidden', $content['field_media_inline_frame']['label']);
    $this->assertSame(3, $content['field_media_inline_frame']['weight']);
    $this->assertSame(['url' => '0'], $content['field_media_inline_frame']['settings']);
    $this->assertTrue($config->get('hidden')['created']);
    $this->assertArrayNotHasKey('name', $config->get('hidden'));
  }

  public function testWiring(): void {
    $source = file_get_contents(__DIR__ . '/../../../includes/bioland.install.editor.inc');
    $setup = substr($source, strpos($source, 'function _bioland_setup_embed_media('));
    $this->assertMatchesRegularExpression('/^[^}]*_bioland_configure_embed_media_library_display\(\)/', $setup);
    $hook = substr($source, strpos($source, 'function bioland_update_9107('));
    $this->assertLessThan(strpos($hook, '_bioland_v2_update_search_and_facets_config()'), strpos($hook, '_bioland_remove_media_library_type_filter()'));
    $this->assertLessThan(strpos($hook, '_bioland_v2_update_search_and_facets_config()'), strpos($hook, '_bioland_configure_embed_media_library_display()'));
    // 9110 re-runs the filter removal for sites already past 9107.
    $rerun = substr($source, strpos($source, 'function bioland_update_9110('));
    $this->assertLessThan(strpos($rerun, '_bioland_v2_update_search_and_facets_config()'), strpos($rerun, '_bioland_remove_media_library_type_filter()'));
  }

}
