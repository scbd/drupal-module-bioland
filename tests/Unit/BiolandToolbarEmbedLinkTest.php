<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandToolbarEmbedLink;
use PHPUnit\Framework\TestCase;

/**
 * Pins the BL-1276 stray Embed admin toolbar item decision.
 *
 * @covers \Drupal\bioland\BiolandToolbarEmbedLink
 */
class BiolandToolbarEmbedLinkTest extends TestCase {

  private const EMBED = 'admin_toolbar_tools.extra_links:media.add.embed';

  /**
   * The embed link is kept but disabled, and other links are untouched.
   */
  public function testDisablesEmbedLinkOnly(): void {
    $image = ['title' => 'Image', 'enabled' => TRUE];
    $links = [
      self::EMBED => ['title' => 'Embed', 'route_name' => 'media.add', 'enabled' => TRUE],
      'admin_toolbar_tools.extra_links:media.add.image' => $image,
    ];

    BiolandToolbarEmbedLink::disable($links);

    $this->assertArrayHasKey(self::EMBED, $links, 'The link must be disabled, never unset.');
    $this->assertFalse($links[self::EMBED]['enabled']);
    $this->assertSame('media.add', $links[self::EMBED]['route_name']);
    $this->assertSame($image, $links['admin_toolbar_tools.extra_links:media.add.image']);
  }

  /**
   * Nothing happens, and nothing is created, when the link is absent.
   */
  public function testNoOpWhenAbsent(): void {
    $links = ['system.admin' => ['enabled' => TRUE]];
    BiolandToolbarEmbedLink::disable($links);
    $this->assertSame(['system.admin' => ['enabled' => TRUE]], $links);

    $empty = [];
    BiolandToolbarEmbedLink::disable($empty);
    $this->assertSame([], $empty);
  }

  /**
   * The module alter delegates to the helper and 9109 rebuilds the menu links.
   */
  public function testWiring(): void {
    $root = dirname(__DIR__, 2);
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_menu_links_discovered_alter\s*\(&\$links\)\s*\{(?:(?!\nfunction\s).)*BiolandToolbarEmbedLink::disable\(\$links\)/s',
      file_get_contents($root . '/bioland.module')
    );
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9109\s*\([^)]*\)\s*\{(?:(?!\nfunction\s).)*plugin\.manager\.menu\.link\'\)->rebuild\(\)/s',
      file_get_contents($root . '/includes/bioland.install.editor.inc'),
      'bioland_update_9109() must rebuild the menu links.'
    );
  }

}
