<?php

namespace Drupal\Tests\bioland\Unit\MediaLibrary;

use Drupal\bioland\MediaLibrary\BiolandHeroImageOpener;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
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
   * Builds the opener over a storage returning $hero and a create handler.
   */
  private function opener($hero = NULL, ?AccessResult $create = NULL): BiolandHeroImageOpener {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($hero);
    $handler = new class($create ?? AccessResult::neutral()) {

      public array $calls = [];

      public function __construct(private AccessResult $result) {
      }

      public function createAccess($bundle, $account, array $context, $return_as_object) {
        $this->calls[] = $bundle;
        return $this->result;
      }

    };
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('media')->willReturn($storage);
    $manager->method('getAccessControlHandler')->with('media')->willReturn($handler);

    return new BiolandHeroImageOpener($manager);
  }

  /**
   * A media stand-in with a bundle and a fixed update decision.
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

    };
  }

  /**
   * An account holding only the given permissions.
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(fn ($p) => in_array($p, $permissions, TRUE));
    return $account;
  }

  /**
   * State as BiolandHeroMediaLibrary builds it.
   */
  private function state(?int $entity_id): MediaLibraryState {
    return MediaLibraryState::create('bioland.opener.hero_image', ['image'], 'image', 1, [
      'widget_id' => 'bioland-hero-image',
      'entity_id' => $entity_id,
    ]);
  }

  /**
   * @covers ::checkAccess
   */
  public function testEditorWhoCanUpdateHeroAndViewMediaIsAllowed(): void {
    $opener = $this->opener($this->media('hero', AccessResult::allowed()));

    $this->assertTrue($opener->checkAccess($this->state(7), $this->account(['view media']))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testViewMediaPermissionIsRequired(): void {
    $opener = $this->opener($this->media('hero', AccessResult::allowed()));

    $this->assertFalse($opener->checkAccess($this->state(7), $this->account([]))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testNoUpdateAccessOnHeroIsDenied(): void {
    $opener = $this->opener($this->media('hero', AccessResult::forbidden()));

    $this->assertTrue($opener->checkAccess($this->state(7), $this->account(['view media']))->isForbidden());
  }

  /**
   * @covers ::checkAccess
   */
  public function testMissingOrNonHeroEntityIsForbidden(): void {
    $account = $this->account(['view media']);

    $this->assertTrue($this->opener(NULL)->checkAccess($this->state(7), $account)->isForbidden());
    $this->assertTrue($this->opener($this->media('image', AccessResult::allowed()))->checkAccess($this->state(7), $account)->isForbidden());
  }

  /**
   * @covers ::checkAccess
   */
  public function testAddFormUsesHeroCreateAccess(): void {
    $account = $this->account(['view media']);

    $this->assertTrue($this->opener(NULL, AccessResult::allowed())->checkAccess($this->state(NULL), $account)->isAllowed());
    $this->assertFalse($this->opener(NULL, AccessResult::neutral())->checkAccess($this->state(NULL), $account)->isAllowed());
  }

  /**
   * @covers ::getSelectionResponse
   */
  public function testSelectionFillsHiddenInputThenFiresUpdateButton(): void {
    $commands = $this->opener()->getSelectionResponse($this->state(7), ['12', 5])->getCommands();

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
