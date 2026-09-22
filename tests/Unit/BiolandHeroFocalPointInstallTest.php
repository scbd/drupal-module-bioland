<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\Core\Field\BaseFieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Tests the hero focal point install helpers (bioland_update_9084(), BL-841).
 *
 * @group bioland
 */
class BiolandHeroFocalPointInstallTest extends TestCase {

  private array $enabled = [];
  private array $installed = [];
  private ?\Throwable $installError = NULL;
  private array $entityTypes = ['media', 'entity_form_display'];
  private ?object $display = NULL;
  private ?object $resource = NULL;
  private object $updateManager;

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

    $etm = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $etm->method('hasDefinition')->willReturnCallback(fn ($id) => in_array($id, $this->entityTypes, TRUE));
    $etm->method('getStorage')->willReturnCallback(function ($id) {
      $storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
      $storage->method('load')->willReturnCallback(fn () => $id === 'jsonapi_resource_config' ? $this->resource : $this->display);
      return $storage;
    });
    \Drupal::setService('entity_type.manager', $etm);

    $this->updateManager = new class {
      public array $storage = [];

      public function getFieldStorageDefinition($name, $entity_type_id) {
        return $this->storage["$entity_type_id.$name"] ?? NULL;
      }

      public function installFieldStorageDefinition($name, $entity_type_id, $provider, $definition) {
        $this->storage["$entity_type_id.$name"] = [$provider, $definition];
      }
    };
    \Drupal::setService('entity.definition_update_manager', $this->updateManager);

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
    if ($this->installError) {
      throw $this->installError;
    }
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

  private function resource(array $fields): object {
    return $this->resource = new class($fields) {
      public int $saves = 0;

      public function __construct(public array $resourceFields) {}

      public function get($key) {
        return $key === 'resourceFields' ? $this->resourceFields : NULL;
      }

      public function set($key, $value) {
        $this->resourceFields = $value;
        return $this;
      }

      public function save() {
        $this->saves++;
      }
    };
  }

  public function testConfigureNeverInstallsModules(): void {
    $display = $this->display(['type' => 'image_image', 'settings' => []]);

    $this->assertStringContainsString('not enabled', _bioland_configure_hero_focal_point());
    $this->assertSame([], $this->installed);
    $this->assertSame(0, $display->saves);
  }

  public function testEnableSkipsWhenFocalPointCodeIsAbsent(): void {
    $this->setAvailable(FALSE);

    $this->assertStringContainsString('not available', _bioland_enable_focal_point_module());
    $this->assertSame([], $this->installed);
  }

  public function testEnableInstallsWhenAvailableAndIsIdempotent(): void {
    $this->assertStringContainsString('Enabled', _bioland_enable_focal_point_module());
    $this->assertSame(['focal_point'], $this->installed);
    $this->assertNull(_bioland_enable_focal_point_module());
  }

  public function testEnableReportsInstallFailureInsteadOfThrowing(): void {
    $this->installError = new \RuntimeException('Missing dependency crop');

    $this->assertStringContainsString('Missing dependency crop', _bioland_enable_focal_point_module());
    $this->assertSame([], $this->enabled);
  }

  public function testSwitchesWidgetKeepingSettings(): void {
    $this->enabled = ['focal_point'];
    $display = $this->display([
      'type' => 'image_image',
      'weight' => 1,
      'settings' => ['progress_indicator' => 'bar', 'preview_image_style' => 'medium', 'preview_link' => FALSE],
    ]);

    $message = _bioland_configure_hero_focal_point();

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
    $this->assertSame(0, $display->saves);
  }

  public function testMissingHeroDisplayOrFieldIsSafe(): void {
    $this->enabled = ['focal_point'];
    $this->assertStringContainsString('not found', _bioland_configure_hero_focal_point());

    $this->display(NULL);
    $this->assertStringContainsString('not found', _bioland_configure_hero_focal_point());
  }

  public function testEnablesFieldInExistingJsonapiOverrideEntity(): void {
    $this->enabled = ['focal_point'];
    $this->entityTypes[] = 'jsonapi_resource_config';
    $this->display(['type' => 'image_focal_point']);
    $resource = $this->resource([
      'bioland_focal_point' => ['fieldName' => 'bioland_focal_point', 'publicName' => 'x', 'disabled' => TRUE],
      'name' => ['fieldName' => 'name', 'publicName' => 'name', 'disabled' => FALSE],
    ]);

    _bioland_configure_hero_focal_point();

    $field = $resource->resourceFields['bioland_focal_point'];
    $this->assertFalse($field['disabled']);
    $this->assertSame('bioland_focal_point', $field['publicName']);
    $this->assertArrayHasKey('name', $resource->resourceFields);
    $this->assertSame(1, $resource->saves);
  }

  public function testLeavesJsonapiDefaultsAloneWithoutOverride(): void {
    $this->enabled = ['focal_point'];
    $this->entityTypes[] = 'jsonapi_resource_config';
    $this->display(['type' => 'image_focal_point']);

    $this->assertStringContainsString('exposed by default', _bioland_configure_hero_focal_point());
  }

  public function testSkipsJsonapiWithoutResourceConfigEntityType(): void {
    $this->enabled = ['focal_point'];
    $this->display(['type' => 'image_focal_point']);

    $this->assertStringContainsString('jsonapi_extras not enabled', _bioland_configure_hero_focal_point());
  }

  public function testInstallsFieldStorageDefinitionOnce(): void {
    $this->assertStringContainsString('Installed', _bioland_install_hero_focal_point_field());
    [$provider, $definition] = $this->updateManager->storage['media.bioland_focal_point'];
    $this->assertSame('bioland', $provider);
    $this->assertInstanceOf(BaseFieldDefinition::class, $definition);

    $this->assertStringContainsString('already installed', _bioland_install_hero_focal_point_field());
    $this->assertCount(1, $this->updateManager->storage);
  }

  public function testFieldInstallSkipsWithoutMedia(): void {
    $this->entityTypes = [];

    $this->assertStringContainsString('Media is not installed', _bioland_install_hero_focal_point_field());
    $this->assertSame([], $this->updateManager->storage);
  }

}
