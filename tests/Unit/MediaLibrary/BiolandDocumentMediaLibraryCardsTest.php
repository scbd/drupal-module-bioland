<?php

namespace Drupal\Tests\bioland\Unit\MediaLibrary;

use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1205 document media library card clean-up helpers.
 *
 * Covers bioland_update_9094()'s three helpers: removing the stray
 * hand-added "bundle" field from the media_library view's widget displays,
 * fixing the media.document media_library view display so cards show the
 * document's image and name, and restricting the CKEditor media_embed
 * filter to image + remote_video.
 *
 * @group bioland
 */
class BiolandDocumentMediaLibraryCardsTest extends TestCase {

  /**
   * Config objects keyed by name, returned by the stubbed config factory.
   *
   * @var \Drupal\Core\Config\Config[]
   */
  private array $configs = [];

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../includes/bioland.install.views.inc';
    require_once __DIR__ . '/../../../includes/bioland.install.helpers.inc';
  }

  protected function setUp(): void {
    parent::setUp();

    $this->configs = [];

    $module_handler = new class {
      public function moduleExists($module) {
        return TRUE;
      }
    };
    \Drupal::setService('module_handler', $module_handler);

    $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $factory->method('getEditable')->willReturnCallback(function ($name) {
      return $this->config($name);
    });
    \Drupal::setService('config.factory', $factory);

    \Drupal::setService('views.views_data', new class {
      public int $cleared = 0;

      public function clear() {
        $this->cleared++;
      }
    });
  }

  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Gets (creating if necessary) the stub config object for a name.
   */
  private function config(string $name, array $data = []): \Drupal\Core\Config\Config {
    if (!isset($this->configs[$name])) {
      $config = new \Drupal\Core\Config\Config($name, $data);
      $config->isNew = empty($data);
      $this->configs[$name] = $config;
    }
    return $this->configs[$name];
  }

  /*
   * =============================================================================
   * _bioland_remove_media_library_bundle_field()
   * =============================================================================
   */

  public function testRemovesBundleFieldFromBothWidgetDisplays(): void {
    $this->config('views.view.media_library', [
      'display' => [
        'widget' => [
          'display_options' => [
            'fields' => [
              'bundle' => ['id' => 'bundle'],
              'name' => ['id' => 'name'],
            ],
          ],
        ],
        'widget_table' => [
          'display_options' => [
            'fields' => [
              'bundle' => ['id' => 'bundle'],
              'thumbnail__target_id' => ['id' => 'thumbnail__target_id'],
            ],
          ],
        ],
      ],
    ]);

    $message = _bioland_remove_media_library_bundle_field();

    $config = $this->config('views.view.media_library');
    $this->assertArrayNotHasKey('bundle', $config->get('display.widget.display_options.fields'));
    $this->assertArrayHasKey('name', $config->get('display.widget.display_options.fields'));
    $this->assertArrayNotHasKey('bundle', $config->get('display.widget_table.display_options.fields'));
    $this->assertArrayHasKey('thumbnail__target_id', $config->get('display.widget_table.display_options.fields'));
    $this->assertTrue($config->saved);
    $this->assertStringContainsString('Removed', $message);
    $this->assertStringContainsString('widget', $message);
    $this->assertStringContainsString('widget_table', $message);
  }

  public function testRemoveBundleFieldIsNoOpWhenAlreadyAbsent(): void {
    $this->config('views.view.media_library', [
      'display' => [
        'widget' => [
          'display_options' => [
            'fields' => ['name' => ['id' => 'name']],
          ],
        ],
        'widget_table' => [
          'display_options' => [
            'fields' => ['name' => ['id' => 'name']],
          ],
        ],
      ],
    ]);

    $message = _bioland_remove_media_library_bundle_field();

    $config = $this->config('views.view.media_library');
    $this->assertFalse($config->saved);
    $this->assertStringContainsString('already', $message);
  }

  public function testRemoveBundleFieldSkipsWhenViewNotFound(): void {
    $message = _bioland_remove_media_library_bundle_field();

    $this->assertStringContainsString('not found', $message);
  }

  /*
   * =============================================================================
   * _bioland_fix_document_media_library_display()
   * =============================================================================
   */

  public function testFixesDocumentDisplayContentAndHiddenShape(): void {
    $this->config('core.entity_view_display.media.document.media_library', [
      'content' => [
        'thumbnail' => [
          'type' => 'file_default',
          'label' => 'hidden',
          'settings' => [],
          'weight' => 0,
        ],
      ],
      'hidden' => [],
    ]);

    $message = _bioland_fix_document_media_library_display();

    $config = $this->config('core.entity_view_display.media.document.media_library');
    $content = $config->get('content');
    $hidden = $config->get('hidden');

    $this->assertArrayNotHasKey('thumbnail', $content);
    $this->assertTrue($hidden['thumbnail']);

    $this->assertSame('image', $content['field_media_image']['type']);
    $this->assertSame('hidden', $content['field_media_image']['label']);
    $this->assertSame(0, $content['field_media_image']['weight']);
    $this->assertSame('medium', $content['field_media_image']['settings']['image_style']);
    $this->assertSame('lazy', $content['field_media_image']['settings']['image_loading']['attribute']);

    $this->assertSame('string', $content['name']['type']);
    $this->assertSame('hidden', $content['name']['label']);
    $this->assertSame(1, $content['name']['weight']);

    $this->assertTrue($config->saved);
    $this->assertStringContainsString('Fixed', $message);
  }

  public function testFixDocumentDisplayIsIdempotent(): void {
    $this->config('core.entity_view_display.media.document.media_library', [
      'content' => [],
      'hidden' => [],
    ]);

    _bioland_fix_document_media_library_display();

    $config = $this->config('core.entity_view_display.media.document.media_library');
    $config->saved = FALSE;

    $message = _bioland_fix_document_media_library_display();

    $this->assertFalse($config->saved);
    $this->assertStringContainsString('already', $message);
  }

  public function testFixDocumentDisplaySkipsWhenConfigNotFound(): void {
    $message = _bioland_fix_document_media_library_display();

    $this->assertStringContainsString('not found', $message);
  }

  /*
   * =============================================================================
   * _bioland_restrict_media_embed_allowed_types()
   * =============================================================================
   */

  public function testRestrictsAllowedMediaTypesOnBothFormats(): void {
    foreach (['full_html', 'basic_html'] as $format_id) {
      $this->config('filter.format.' . $format_id, [
        'filters' => [
          'media_embed' => [
            'id' => 'media_embed',
            'settings' => [
              'default_view_mode' => 'default',
              'allowed_media_types' => [
                'image' => 'image',
                'remote_video' => 'remote_video',
                'document' => 'document',
              ],
            ],
          ],
        ],
      ]);
    }

    $message = _bioland_restrict_media_embed_allowed_types();

    foreach (['full_html', 'basic_html'] as $format_id) {
      $config = $this->config('filter.format.' . $format_id);
      $this->assertSame(
        ['image' => 'image', 'remote_video' => 'remote_video'],
        $config->get('filters.media_embed.settings.allowed_media_types')
      );
      $this->assertTrue($config->saved);
    }
    $this->assertStringContainsString('Restricted', $message);
  }

  public function testRestrictAllowedMediaTypesIsNoOpOnSecondRun(): void {
    foreach (['full_html', 'basic_html'] as $format_id) {
      $this->config('filter.format.' . $format_id, [
        'filters' => [
          'media_embed' => [
            'id' => 'media_embed',
            'settings' => [
              'allowed_media_types' => ['image' => 'image', 'remote_video' => 'remote_video'],
            ],
          ],
        ],
      ]);
    }

    $message = _bioland_restrict_media_embed_allowed_types();

    foreach (['full_html', 'basic_html'] as $format_id) {
      $this->assertFalse($this->config('filter.format.' . $format_id)->saved);
    }
    $this->assertStringContainsString('already', $message);
  }

  public function testRestrictAllowedMediaTypesLeavesFormatWithoutMediaEmbedUntouched(): void {
    $this->config('filter.format.full_html', [
      'filters' => [
        'linkit' => ['id' => 'linkit', 'settings' => []],
      ],
    ]);
    $this->config('filter.format.basic_html', [
      'filters' => [
        'media_embed' => [
          'id' => 'media_embed',
          'settings' => ['allowed_media_types' => ['image' => 'image', 'remote_video' => 'remote_video']],
        ],
      ],
    ]);

    $message = _bioland_restrict_media_embed_allowed_types();

    $full_html = $this->config('filter.format.full_html');
    $this->assertFalse($full_html->saved);
    $this->assertSame(
      ['linkit' => ['id' => 'linkit', 'settings' => []]],
      $full_html->get('filters')
    );
    $this->assertStringContainsString('no media_embed filter', $message);
  }

  public function testRestrictAllowedMediaTypesSkipsMissingFormat(): void {
    $this->config('filter.format.basic_html', [
      'filters' => [
        'media_embed' => [
          'id' => 'media_embed',
          'settings' => ['allowed_media_types' => ['image' => 'image', 'remote_video' => 'remote_video']],
        ],
      ],
    ]);

    $message = _bioland_restrict_media_embed_allowed_types();

    $this->assertStringContainsString('not found', $message);
  }

}
