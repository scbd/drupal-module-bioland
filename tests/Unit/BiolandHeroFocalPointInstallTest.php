<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\Core\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * Tests _bioland_configure_hero_focal_point() (bioland_update_9084(), BL-841).
 *
 * @group bioland
 */
class BiolandHeroFocalPointInstallTest extends TestCase {

  private array $enabled = [];
  private array $installed = [];
  private ?object $display = NULL;
  private Config $resourceConfig;

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.focal_point.inc';
  }

  protected function setUp(): void {
    parent::setUp();
    $test = $this;

    \Drupal::setService('module_handler', new class($test) {
      public function __construct(private $test) {}

      public function moduleExists($m) {
        return $this->test->isEnabled($m);
      }
    });
    \Drupal::setService('module_installer', new class($test) {
      public function __construct(private $test) {}

      public function install(array $modules) {
        $this->test->markInstalled($modules);
      }
    });

    $this->resourceConfig = new Config('jsonapi_extras.jsonapi_resource_config.media--hero', []);
    $this->resourceConfig->isNew = TRUE;
    $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $factory->method('getEditable')->willReturn($this->resourceConfig);
    \Drupal::setService('config.factory', $factory);

    $storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $storage->method('load')->willReturnCallback(fn () => $this->display);
    $etm = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $etm->method('getStorage')->willReturn($storage);
    \Drupal::setService('entity_type.manager', $etm);

    $this->setAvailable(TRUE);
  }

  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  public function isEnabled(string $module): bool {
    return in_array($module, $this->enabled, TRUE);
  }

  public function markInstalled(array $modules): void {
    $this->installed = array_merge($this->installed, $modules);
    $this->enabled = array_merge($this->enabled, $modules);
  }

  private function setAvailable(bool $available): void {
    \Drupal::setService('extension.list.module', new class($available) {
      public function __construct(private bool $available) {}

      public function exists($name) {
        return $this->available && $name === 'focal_point';
      }
    });
  }

  private function display(?array $component): object {
    return $this->display = new class($component) {
      public int $saves = 0;

      public function __construct(public ?array $component) {}

      public function getComponent($name) {
        return $name === 'field_media_image' ? $this->component : NULL;
      }

      public function setComponent($name, array $options) {
        $this->component = $options;
        return $this;
      }

      public function save() {
        $this->saves++;
      }
    };
  }

  public function testSkipsWhenFocalPointCodeIsAbsent(): void {
    $this->setAvailable(FALSE);
    $display = $this->display(['type' => 'image_image', 'settings' => []]);

    $this->assertStringContainsString('not available', _bioland_configure_hero_focal_point());
    $this->assertSame([], $this->installed);
    $this->assertSame(0, $display->saves);
  }

  public function testInstallsModuleAndSwitchesWidgetKeepingSettings(): void {
    $display = $this->display([
      'type' => 'image_image',
      'weight' => 1,
      'settings' => ['progress_indicator' => 'bar', 'preview_image_style' => 'medium', 'preview_link' => FALSE],
    ]);

    $message = _bioland_configure_hero_focal_point();

    $this->assertSame(['focal_point'], $this->installed);
    $this->assertSame(1, $display->saves);
    $this->assertSame('image_focal_point', $display->component['type']);
    $this->assertSame(1, $display->component['weight']);
    $this->assertSame([
      'preview_link' => TRUE,
      'offsets' => '50,50',
      'progress_indicator' => 'bar',
      'preview_image_style' => 'medium',
    ], $display->component['settings']);
    $this->assertStringContainsString('now uses the focal point widget', $message);
  }

  public function testIsIdempotent(): void {
    $this->enabled = ['focal_point'];
    $display = $this->display(['type' => 'image_focal_point', 'settings' => []]);

    $this->assertStringContainsString('already uses', _bioland_configure_hero_focal_point());
    $this->assertSame([], $this->installed);
    $this->assertSame(0, $display->saves);
  }

  public function testMissingHeroDisplayOrFieldIsSafe(): void {
    $this->enabled = ['focal_point'];
    $this->assertStringContainsString('not found', _bioland_configure_hero_focal_point());

    $this->display(NULL);
    $this->assertStringContainsString('not found', _bioland_configure_hero_focal_point());
  }

  public function testEnablesFieldInExistingJsonapiOverride(): void {
    $this->enabled = ['focal_point', 'jsonapi_extras'];
    $this->display(['type' => 'image_focal_point']);
    $this->resourceConfig->isNew = FALSE;
    $this->resourceConfig->set('resourceFields', [
      'bioland_focal_point' => ['fieldName' => 'bioland_focal_point', 'publicName' => 'x', 'disabled' => TRUE],
    ]);

    _bioland_configure_hero_focal_point();

    $field = $this->resourceConfig->get('resourceFields')['bioland_focal_point'];
    $this->assertFalse($field['disabled']);
    $this->assertSame('bioland_focal_point', $field['publicName']);
    $this->assertTrue($this->resourceConfig->saved);
  }

  public function testLeavesJsonapiDefaultsAloneWithoutOverride(): void {
    $this->enabled = ['focal_point', 'jsonapi_extras'];
    $this->display(['type' => 'image_focal_point']);

    $this->assertStringContainsString('exposed by default', _bioland_configure_hero_focal_point());
    $this->assertFalse($this->resourceConfig->saved);
  }

}
