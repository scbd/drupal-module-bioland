<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Plugin\Validation\Constraint\BiolandEmbedAllowedOriginConstraint;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1218 embed URL constraint wiring.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedValidationWiringTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../bioland.module';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Runs the bundle field alter with an embed type using $source_field.
   */
  private function alter(string $entity_type_id, string $bundle, array $fields, ?string $source_field = 'field_media_iframe'): void {
    $type = $source_field === NULL ? NULL : new class($source_field) {

      public function __construct(private string $field) {}

      public function get(string $key) {
        return $key === 'source_configuration' ? ['source_field' => $this->field] : NULL;
      }

    };
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('embed')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('media_type')->willReturn($storage);
    \Drupal::setService('entity_type.manager', $manager);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn($entity_type_id);
    bioland_entity_bundle_field_info_alter($fields, $entity_type, $bundle);
  }

  /**
   * Returns a field definition double expecting $times addConstraint calls.
   */
  private function field(int $times): object {
    $field = $this->getMockBuilder(\stdClass::class)->addMethods(['addConstraint'])->getMock();
    $field->expects($this->exactly($times))->method('addConstraint')->with(BiolandEmbedAllowedOriginConstraint::PLUGIN_ID);
    return $field;
  }

  /**
   * The constraint lands on the embed type's source field only.
   */
  public function testConstraintAttachesToEmbedSourceField(): void {
    $this->alter('media', 'embed', ['field_media_iframe' => $this->field(1), 'name' => $this->field(0)]);
  }

  /**
   * Other bundles, other entity types, a missing type or field: no constraint.
   */
  public function testConstraintSkipsEverythingElse(): void {
    $this->alter('media', 'document', ['field_media_iframe' => $this->field(0)]);
    $this->alter('node', 'embed', ['field_media_iframe' => $this->field(0)]);
    $this->alter('media', 'embed', ['field_media_iframe' => $this->field(0)], NULL);
    $this->alter('media', 'embed', ['field_other' => $this->field(0)]);
  }

}
