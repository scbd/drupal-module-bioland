<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandHeroMediaLibrary;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the hero "Choose from media library" picker (BL-807).
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandHeroMediaLibrary
 * @group bioland
 */
class BiolandHeroMediaLibraryTest extends TestCase {

  /**
   * Builds the service over a media storage returning $media.
   */
  private function service($media = NULL, array $permissions = ['view media']): BiolandHeroMediaLibrary {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($media);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('media')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(fn ($p) => in_array($p, $permissions, TRUE));

    return new BiolandHeroMediaLibrary($manager, $account);
  }

  /**
   * The widget hook context for a field on a media entity.
   */
  private function context(string $bundle, string $field = 'field_media_image', ?int $id = 7): array {
    $entity = new class($bundle, $id) {

      public function __construct(private string $bundle, private ?int $id) {
      }

      public function getEntityTypeId() {
        return 'media';
      }

      public function bundle() {
        return $this->bundle;
      }

      public function isNew() {
        return $this->id === NULL;
      }

      public function id() {
        return $this->id;
      }

    };
    $items = new class($entity, $field) {

      public function __construct(private object $entity, private string $field) {
      }

      public function getEntity() {
        return $this->entity;
      }

      public function getFieldDefinition() {
        $field = $this->field;
        return new class($field) {

          public function __construct(private string $field) {
          }

          public function getName() {
            return $this->field;
          }

        };
      }

    };

    return ['items' => $items, 'default' => FALSE];
  }

  /**
   * An image media stand-in whose source field holds $value.
   */
  private function imageMedia(array $value, string $bundle = 'image', bool $viewable = TRUE): object {
    return new class($value, $bundle, $viewable) {

      public function __construct(private array $value, private string $bundle, private bool $viewable) {
      }

      public function bundle() {
        return $this->bundle;
      }

      public function access($operation, $account = NULL) {
        return $operation === 'view' && $this->viewable;
      }

      public function getSource() {
        return new class {

          public function getConfiguration() {
            return ['source_field' => 'field_media_image'];
          }

        };
      }

      public function get($field) {
        $values = $field === 'field_media_image' ? [$this->value] : [];
        return new class($values) {

          public function __construct(private array $values) {
          }

          public function getValue() {
            return $this->values;
          }

        };
      }

    };
  }

  /**
   * @covers ::alterWidget
   */
  public function testHeroEditFormGetsPickerRestrictedToOneImage(): void {
    $element = ['widget' => ['#field_parents' => []]];
    $this->service()->alterWidget($element, $this->context('hero'));

    $picker = $element['bioland_media_library'];
    $query = $picker['open']['#url']->getOptions()['query'];
    $this->assertSame('media_library.ui', $picker['open']['#url']->getRouteName());
    $this->assertSame('bioland.opener.hero_image', $query['media_library_opener_id']);
    $this->assertSame(['image'], $query['media_library_allowed_types']);
    $this->assertSame(1, $query['media_library_remaining']);
    $this->assertSame(['widget_id' => 'bioland-hero-image', 'entity_id' => 7], $query['media_library_opener_parameters']);
    $this->assertContains('use-ajax', $picker['open']['#attributes']['class']);
    $this->assertSame('modal', $picker['open']['#attributes']['data-dialog-type']);

    $this->assertSame('bioland-hero-image', $picker['selection']['#attributes']['data-bioland-hero-media-value']);
    $this->assertSame('bioland-hero-image', $picker['update']['#attributes']['data-bioland-hero-media-update']);
    $this->assertSame([['bioland_hero_media_library']], $picker['update']['#limit_validation_errors']);
    $this->assertSame('bioland-hero-media-image-wrapper', $picker['update']['#ajax']['wrapper']);
    $this->assertStringContainsString('id="bioland-hero-media-image-wrapper"', $element['#prefix']);
    $this->assertSame('</div>', $element['#suffix']);
  }

  /**
   * @covers ::alterWidget
   */
  public function testHeroAddFormPassesNoEntityId(): void {
    $element = [];
    $this->service()->alterWidget($element, $this->context('hero', 'field_media_image', NULL));

    $query = $element['bioland_media_library']['open']['#url']->getOptions()['query'];
    $this->assertNull($query['media_library_opener_parameters']['entity_id']);
  }

  /**
   * @covers ::alterWidget
   */
  public function testOtherFormsAndUsersWithoutViewMediaAreUntouched(): void {
    $cases = [
      [$this->service(), $this->context('image')],
      [$this->service(), $this->context('hero', 'field_other_image')],
      [$this->service(NULL, []), $this->context('hero')],
      [$this->service(), ['default' => TRUE] + $this->context('hero')],
    ];
    foreach ($cases as [$service, $context]) {
      $element = ['widget' => []];
      $service->alterWidget($element, $context);
      $this->assertSame(['widget' => []], $element);
    }
  }

  /**
   * @covers ::resolveSelection
   */
  public function testSelectionCopiesFileAndAltWhenHeroAltIsEmpty(): void {
    $media = $this->imageMedia(['target_id' => 42, 'alt' => 'Mangroves', 'title' => '', 'width' => 800, 'height' => 600]);

    $this->assertSame(['item' => [
      'target_id' => 42,
      'fids' => [42],
      'alt' => 'Mangroves',
      'title' => '',
      'width' => 800,
      'height' => 600,
    ]], $this->service($media)->resolveSelection('9', ''));
  }

  /**
   * @covers ::resolveSelection
   */
  public function testSelectionKeepsExistingHeroAlt(): void {
    $media = $this->imageMedia(['target_id' => 42, 'alt' => 'Mangroves']);

    $this->assertSame('Coral reef', $this->service($media)->resolveSelection('9,3', 'Coral reef')['item']['alt']);
  }

  /**
   * @covers ::resolveSelection
   */
  public function testEmptySelectionIsANoOp(): void {
    $this->assertSame([], $this->service()->resolveSelection('', ''));
  }

  /**
   * @covers ::resolveSelection
   */
  public function testRejectedSelectionsReturnAnError(): void {
    $image = ['target_id' => 42];
    $cases = [
      [NULL, '9'],
      [$this->imageMedia($image), 'abc'],
      [$this->imageMedia($image, 'document'), '9'],
      [$this->imageMedia($image, 'image', FALSE), '9'],
      [$this->imageMedia([]), '9'],
    ];
    foreach ($cases as [$media, $selection]) {
      $this->assertArrayHasKey('error', $this->service($media)->resolveSelection($selection, ''), "Selection '$selection' must be rejected.");
    }
  }

  /**
   * The services and the guarded widget hook are wired.
   *
   * @coversNothing
   */
  public function testServicesAndHookAreWired(): void {
    $root = dirname(__DIR__, 3);
    $services = file_get_contents($root . '/bioland.services.yml');
    $module = file_get_contents($root . '/bioland.module');

    $this->assertStringContainsString("bioland.opener.hero_image:\n    class: Drupal\\bioland\\MediaLibrary\\BiolandHeroImageOpener", $services);
    $this->assertStringContainsString("bioland.hero_media_library:\n    class: Drupal\\bioland\\Service\\BiolandHeroMediaLibrary", $services);
    $this->assertMatchesRegularExpression(
      "/function bioland_field_widget_complete_image_image_form_alter\\([^)]*\\) \\{\\s*if \\(\\\\Drupal::moduleHandler\\(\\)->moduleExists\\('media_library'\\)\\)/",
      $module
    );
  }

}
