<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Guards the embed media_library view display (BL-1272, BL-1312).
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedMediaLibraryDisplayTest extends TestCase {

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
   * Registers the site and returns the editable display config.
   */
  private function site(array $data, bool $new = FALSE, array $modules = ['media_iframe', 'media_library'], bool $type = TRUE): Config {
    $config = new Config('core.entity_view_display.media.embed.media_library', $data);
    $config->isNew = $new;
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('getEditable')->with('core.entity_view_display.media.embed.media_library')->willReturn($config);
    \Drupal::setService('config.factory', $factory);
    \Drupal::setService('module_handler', new class($modules) {
      public function __construct(private array $modules) {}

      public function moduleExists($name) {
        return in_array($name, $this->modules, TRUE);
      }
    });
    $types = new class($type) {
      public function __construct(private bool $type) {}

      public function load($id) {
        return $this->type ? new \stdClass() : NULL;
      }
    };
    \Drupal::setService('entity_type.manager', new class($types) {
      public function __construct(private object $types) {}

      public function getStorage($entity_type_id) {
        return $this->types;
      }
    });
    return $config;
  }

  /**
   * The display as laid out on co (Randy's iframe_only change).
   */
  private function laidOut(string $formatter): array {
    return [
      'content' => [
        'field_media_inline_frame' => [
          'type' => $formatter,
          'label' => 'visually_hidden',
          'settings' => ['width' => ''],
          'third_party_settings' => [],
          'weight' => 0,
          'region' => 'content',
        ],
        'name' => [
          'type' => 'string',
          'label' => 'hidden',
          'settings' => ['link_to_entity' => FALSE, 'link_rel' => 'canonical'],
          'third_party_settings' => [],
          'weight' => 1,
          'region' => 'content',
        ],
      ],
      'hidden' => ['created' => TRUE, 'langcode' => TRUE, 'search_api_excerpt' => TRUE, 'thumbnail' => TRUE, 'uid' => TRUE],
    ];
  }

  /**
   * A fresh display shows the frame with iframe_only and the plain name.
   */
  public function testLaysOutFreshDisplayWithIframeOnly(): void {
    $config = $this->site([], TRUE);
    $this->assertSame('Laid out the embed media_library display.', _bioland_configure_embed_media_library_display());
    $this->assertTrue($config->saved);
    $frame = $config->get('content.field_media_inline_frame');
    $this->assertSame('iframe_only', $frame['type']);
    $this->assertSame('visually_hidden', $frame['label']);
    $this->assertSame('string', $config->get('content.name.type'));
    $this->assertSame('hidden', $config->get('content.name.label'));
  }

  /**
   * A display on iframe_default flips once, then stays quiet.
   */
  public function testFlipsIframeDefaultOnce(): void {
    $config = $this->site($this->laidOut('iframe_default'));
    $this->assertSame('Laid out the embed media_library display.', _bioland_configure_embed_media_library_display());
    $this->assertSame('iframe_only', $config->get('content.field_media_inline_frame.type'));
    $this->assertSame(['width' => ''], $config->get('content.field_media_inline_frame.settings'));
    $this->assertTrue($config->saved);

    $config->saved = FALSE;
    $this->assertSame('Embed media_library display already laid out; left untouched.', _bioland_configure_embed_media_library_display());
    $this->assertFalse($config->saved);
  }

  /**
   * A display an admin set to iframe_only is left untouched.
   */
  public function testLeavesIframeOnlyUntouched(): void {
    $config = $this->site($this->laidOut('iframe_only'));
    $this->assertSame('Embed media_library display already laid out; left untouched.', _bioland_configure_embed_media_library_display());
    $this->assertFalse($config->saved);
  }

  /**
   * Missing modules or type skip without saving.
   */
  public function testSkips(): void {
    foreach ([[['media_library'], TRUE], [['media_iframe'], TRUE], [['media_iframe', 'media_library'], FALSE]] as [$modules, $type]) {
      $config = $this->site([], TRUE, $modules, $type);
      $this->assertStringContainsString('skipped', _bioland_configure_embed_media_library_display());
      $this->assertFalse($config->saved);
    }
  }

}
