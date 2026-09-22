<?php

namespace Drupal\Tests\bioland\Unit\MediaLibrary;

use Drupal\bioland\MediaLibrary\BiolandHeroImageOpener;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\MediaLibraryState;
use PHPUnit\Framework\TestCase;

/**
 * Tests the hero image Media Library opener (BL-807, BL-387).
 *
 * @coversDefaultClass \Drupal\bioland\MediaLibrary\BiolandHeroImageOpener
 * @group bioland
 */
class BiolandHeroImageOpenerTest extends TestCase {

  /**
   * Builds the opener over a media storage and access control handler.
   *
   * @param object|null $hero
   *   What storage load() returns.
   * @param \Drupal\Core\Access\AccessResult|null $create
   *   The createAccess() result; neutral by default.
   * @param \Drupal\Core\Access\AccessResult|null $field
   *   The fieldAccess('edit') result; allowed by default.
   * @param object|null $handler
   *   Receives the handler so a test can inspect its calls.
   */
  private function opener($hero = NULL, ?AccessResult $create = NULL, ?AccessResult $field = NULL, &$handler = NULL): BiolandHeroImageOpener {
    $storage = new class($hero, fn (array $values) => $this->media($values['bundle'], AccessResult::neutral())) {

      public function __construct(private $hero, private \Closure $create) {
      }

      public function load($id) {
        return $this->hero;
      }

      public function create(array $values) {
        return ($this->create)($values);
      }

    };
    $handler = new class($create ?? AccessResult::neutral(), $field ?? AccessResult::allowed()) {

      public array $calls = [];

      public function __construct(private AccessResult $create, private AccessResult $field) {
      }

      public function access($entity, $operation, $account, $return_as_object) {
        $this->calls[] = ['access', $operation];
        return $entity->access($operation, $account, $return_as_object);
      }

      public function createAccess($bundle, $account, array $context, $return_as_object) {
        $this->calls[] = ['createAccess', $bundle];
        return $this->create;
      }

      public function fieldAccess($operation, $field_definition, $account, $items, $return_as_object) {
        $this->calls[] = ['fieldAccess', $operation, $field_definition->getName(), $items->getEntity()->bundle()];
        return $this->field;
      }

    };
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('media')->willReturn($storage);
    $manager->method('getAccessControlHandler')->with('media')->willReturn($handler);

    return new BiolandHeroImageOpener($manager);
  }

  /**
   * A media stand-in with a bundle, a fixed update decision and field items.
   */
  private function media(string $bundle, AccessResult $update): object {
    return new class($bundle, $update) {

      public function __construct(private string $bundle, private AccessResult $update) {
      }

      public function bundle() {
        return $this->bundle;
      }

      public function access($operation, $account, $return_as_object) {
        return $operation === 'update' ? $this->update : AccessResult::neutral();
      }

      public function get($field_name) {
        $entity = $this;
        return new class($entity, $field_name) {

          public function __construct(private object $entity, private string $name) {
          }

          public function getEntity() {
            return $this->entity;
          }

          public function getFieldDefinition() {
            $name = $this->name;
            return new class($name) {

              public function __construct(private string $name) {
              }

              public function getName() {
                return $this->name;
              }

            };
          }

        };
      }

    };
  }

  /**
   * An account holding only the given permissions.
   */
  private function account(array $permissions = []): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(fn ($p) => in_array($p, $permissions, TRUE));
    return $account;
  }

  /**
   * State as BiolandHeroMediaLibrary builds it: entity_id only when saved.
   */
  private function state(?string $entity_id): MediaLibraryState {
    $parameters = ['widget_id' => 'bioland-hero-image'];
    if ($entity_id !== NULL) {
      $parameters['entity_id'] = $entity_id;
    }
    return MediaLibraryState::create('bioland.opener.hero_image', ['image'], 'image', 1, $parameters);
  }

  /**
   * @covers ::checkAccess
   */
  public function testEditorWhoCanUpdateHeroAndEditItsImageIsAllowed(): void {
    $opener = $this->opener($this->media('hero', AccessResult::allowed()), NULL, NULL, $handler);

    $result = $opener->checkAccess($this->state('7'), $this->account());

    $this->assertTrue($result->isAllowed());
    $this->assertSame([['access', 'update'], ['fieldAccess', 'edit', 'field_media_image', 'hero']], $handler->calls);
    $this->assertContains('url.query_args', $result->getCacheContexts());
  }

  /**
   * 'view media' is MediaLibraryUiBuilder's check, not the opener's.
   *
   * @covers ::checkAccess
   */
  public function testViewMediaPermissionIsLeftToTheUiBuilder(): void {
    $opener = $this->opener($this->media('hero', AccessResult::allowed()));

    $this->assertTrue($opener->checkAccess($this->state('7'), $this->account([]))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testNoEditAccessOnTheImageFieldIsDenied(): void {
    $opener = $this->opener($this->media('hero', AccessResult::allowed()), NULL, AccessResult::forbidden());

    $this->assertTrue($opener->checkAccess($this->state('7'), $this->account())->isForbidden());
    $this->assertFalse($this->opener($this->media('hero', AccessResult::allowed()), NULL, AccessResult::neutral())
      ->checkAccess($this->state('7'), $this->account())->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testNoUpdateAccessOnHeroIsDeniedBeforeFieldAccess(): void {
    $opener = $this->opener($this->media('hero', AccessResult::forbidden()), NULL, NULL, $handler);

    $result = $opener->checkAccess($this->state('7'), $this->account());

    $this->assertTrue($result->isForbidden());
    $this->assertSame([['access', 'update']], $handler->calls);
    $this->assertContains('url.query_args', $result->getCacheContexts());
  }

  /**
   * @covers ::checkAccess
   */
  public function testMissingAndNonHeroEntitiesAreForbiddenWithDistinctReasons(): void {
    $missing = $this->opener(NULL)->checkAccess($this->state('7'), $this->account());
    $wrong_bundle = $this->opener($this->media('image', AccessResult::allowed()))->checkAccess($this->state('7'), $this->account());

    $this->assertTrue($missing->isForbidden());
    $this->assertTrue($wrong_bundle->isForbidden());
    $this->assertSame('The hero media item does not exist.', $missing->getReason());
    $this->assertSame('The media item is not a hero media item.', $wrong_bundle->getReason());
    $this->assertContains('url.query_args', $missing->getCacheContexts());
  }

  /**
   * @covers ::checkAccess
   */
  public function testAddFormUsesHeroCreateAccessThenFieldAccessOnAnUnsavedHero(): void {
    $opener = $this->opener(NULL, AccessResult::allowed(), NULL, $handler);

    $this->assertTrue($opener->checkAccess($this->state(NULL), $this->account())->isAllowed());
    $this->assertSame([['createAccess', 'hero'], ['fieldAccess', 'edit', 'field_media_image', 'hero']], $handler->calls);
    $this->assertFalse($this->opener(NULL, AccessResult::neutral())->checkAccess($this->state(NULL), $this->account())->isAllowed());
  }

  /**
   * @covers ::getSelectionResponse
   */
  public function testSelectionFillsHiddenInputThenFiresUpdateButton(): void {
    $commands = $this->opener()->getSelectionResponse($this->state('7'), ['12', 5])->getCommands();

    $this->assertSame([
      ['command' => 'invoke', 'selector' => '[data-bioland-hero-media-value="bioland-hero-image"]', 'method' => 'val', 'args' => ['12,5']],
      ['command' => 'invoke', 'selector' => '[data-bioland-hero-media-update="bioland-hero-image"]', 'method' => 'trigger', 'args' => ['mousedown']],
    ], $commands);
  }

  /**
   * @covers ::getSelectionResponse
   */
  public function testSelectionRejectsUnsafeWidgetId(): void {
    $state = MediaLibraryState::create('bioland.opener.hero_image', ['image'], 'image', 1, ['widget_id' => '"] body [x="']);

    $this->expectException(\InvalidArgumentException::class);
    $this->opener()->getSelectionResponse($state, [1]);
  }

}
