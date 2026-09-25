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
  private function link(int $id, string $title, string $uri, string $parent, bool $enabled = TRUE): array {
    return ['id' => $id, 'title' => $title, 'uri' => $uri, 'parent' => $parent, 'enabled' => $enabled];
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

  /**
   * Rule 1 keeps the lowest enabled id, not a lower disabled one.
   */
  public function testExactDuplicatesKeepLowestEnabledId(): void {
    $links = [
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD, FALSE),
      $this->link(212, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(245, 'Content', 'internal:/node/add/content', self::ADD),
    ];

    $this->assertSame([170 => 212, 245 => 212], BiolandAdminMenuDedupe::plan($links));
  }

  /**
   * Rule 1 falls back to the lowest id when every copy is disabled.
   */
  public function testExactDuplicatesAllDisabledKeepLowestId(): void {
    $links = [
      $this->link(245, 'Content', 'internal:/node/add/content', self::ADD, FALSE),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD, FALSE),
    ];

    $this->assertSame([245 => 170], BiolandAdminMenuDedupe::plan($links));
  }

  /**
   * Rule 2 ignores a disabled copy elsewhere, so the Add copy stays.
   */
  public function testCopyUnderAddKeptWhenOnlyDisabledCopyElsewhere(): void {
    $links = [
      $this->link(136, 'Content (pages)', 'internal:/admin/content', self::PUBLISHING, FALSE),
      $this->link(301, 'Content (pages)', 'internal:/admin/content', self::ADD),
    ];

    $this->assertSame([], BiolandAdminMenuDedupe::idsToDelete($links));
  }

  /**
   * Rule 2 maps the deleted Add copy to the enabled copy elsewhere.
   */
  public function testCopyUnderAddSurvivorIsEnabledCopyElsewhere(): void {
    $links = [
      $this->link(130, 'Media (attachments)', 'internal:/admin/content/media', 'menu_link_content:other', FALSE),
      $this->link(139, 'Media (attachments)', 'internal:/admin/content/media', self::PUBLISHING),
      $this->link(302, 'Media (attachments)', 'internal:/admin/content/media', self::ADD),
    ];

    $this->assertSame([302 => 139], BiolandAdminMenuDedupe::plan($links));
  }

  /**
   * The canonical Add > Content link survives a same-named link elsewhere.
   */
  public function testCanonicalContentUnderAddIsNeverRemovedByRule2(): void {
    $links = [
      $this->link(90, 'Content', 'internal:/node/add/content', self::PUBLISHING),
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
    ];

    $this->assertSame([], BiolandAdminMenuDedupe::idsToDelete($links));
  }

  /**
   * Surrounding whitespace in title or uri does not hide a duplicate.
   */
  public function testTitleAndUriAreTrimmed(): void {
    $links = [
      $this->link(170, 'Content', 'internal:/node/add/content', self::ADD),
      $this->link(212, " Content\t", ' internal:/node/add/content ', self::ADD),
      $this->link(136, 'Content (pages)', 'internal:/admin/content', self::PUBLISHING),
      $this->link(301, 'Content (pages) ', 'internal:/admin/content', self::ADD),
    ];

    $this->assertSame([212 => 170, 301 => 136], BiolandAdminMenuDedupe::plan($links));
  }

  /**
   * The Content link helper must not create a second copy (BL-836 guard).
   */
  public function testContentLinkHelperChecksForExistingLink(): void {
    $source = file_get_contents(dirname(__DIR__, 2) . '/includes/bioland.install.menu.inc');
    $this->assertMatchesRegularExpression(
      '/function\s+_bioland_create_content_menu_link\s*\(\s*\)\s*\{(?:(?!\nfunction\s).)*loadByProperties\(\s*\[\s*\'title\'\s*=>\s*\'Content\'.*?\'link__uri\'\s*=>\s*\'internal:\/node\/add\/content\'(?:(?!\nfunction\s).)*if\s*\(\s*!empty\(\$existing\)\s*\)\s*\{(?:(?!\nfunction\s).)*return(?:(?!\nfunction\s).)*->create\(/s',
      $source,
      '_bioland_create_content_menu_link() must return early when the Content link already exists, before creating one.'
    );
  }

}
