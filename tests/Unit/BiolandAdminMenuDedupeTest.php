<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandAdminMenuDedupe;
use PHPUnit\Framework\TestCase;

/**
 * Pins the BL-836 duplicate admin-menu link decision.
 *
 * @covers \Drupal\bioland\BiolandAdminMenuDedupe
 */
class BiolandAdminMenuDedupeTest extends TestCase {

  private const ADD = BiolandAdminMenuDedupe::ADD_PARENT;
  private const PUBLISHING = 'menu_link_content:publishing-parent';

  /**
   * Builds a descriptor.
   */
  private function link(int $id, string $title, string $uri, string $parent): array {
    return ['id' => $id, 'title' => $title, 'uri' => $uri, 'parent' => $parent];
  }

  /**
   * Repeated "Content" links under one parent keep only the lowest id.
   */
  public function testExactDuplicatesUnderOneParentKeepLowestId(): void {
    $links = [
      $this->link(212, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(245, 'Content', 'internal:/node/add/content', self::ADD),
    ];

    $this->assertSame([212, 245], BiolandAdminMenuDedupe::idsToDelete($links));
  }

  /**
   * A copy under "Add" of an item living elsewhere is deleted, not the other.
   */
  public function testCopyUnderAddOfItemElsewhereDeletesTheAddCopy(): void {
    $links = [
      $this->link(136, 'Content (pages)', 'internal:/admin/content', self::PUBLISHING),
      $this->link(139, 'Media (attachments)', 'internal:/admin/content/media', self::PUBLISHING),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(301, 'Content (pages)', 'internal:/admin/content', self::ADD),
      $this->link(302, 'Media (attachments)', 'internal:/admin/content/media', self::ADD),
    ];

    $this->assertSame([301, 302], BiolandAdminMenuDedupe::idsToDelete($links));
  }

  /**
   * The full screenshot scenario: both rules together.
   */
  public function testReportedFlyoutCollapsesToSingleContent(): void {
    $links = [
      $this->link(136, 'Content (pages)', 'internal:/admin/content', self::PUBLISHING),
      $this->link(139, 'Media (attachments)', 'internal:/admin/content/media', self::PUBLISHING),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(212, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(301, 'Content (pages)', 'internal:/admin/content', self::ADD),
      $this->link(302, 'Media (attachments)', 'internal:/admin/content/media', self::ADD),
    ];

    $this->assertSame([212, 301, 302], BiolandAdminMenuDedupe::idsToDelete($links));
  }

  /**
   * A clean menu returns nothing to delete.
   */
  public function testNoDuplicatesReturnsEmpty(): void {
    $links = [
      $this->link(136, 'Content (pages)', 'internal:/admin/content', self::PUBLISHING),
      $this->link(139, 'Media (attachments)', 'internal:/admin/content/media', self::PUBLISHING),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
    ];

    $this->assertSame([], BiolandAdminMenuDedupe::idsToDelete($links));
    $this->assertSame([], BiolandAdminMenuDedupe::idsToDelete([]));
  }

  /**
   * Same title with a different uri is a different link.
   */
  public function testDifferentUrisAreNotDuplicates(): void {
    $links = [
      $this->link(139, 'Media (attachments)', 'internal:/admin/content/media', self::PUBLISHING),
      $this->link(302, 'Media (attachments)', 'internal:/media/add', self::ADD),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(171, 'Content', 'internal:/node/add/page', self::ADD),
    ];

    $this->assertSame([], BiolandAdminMenuDedupe::idsToDelete($links));
  }

}
