<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandToolbarLlmsTxtLink;
use PHPUnit\Framework\TestCase;

/**
 * Pins the BL-1291 stray llms.txt configuration admin toolbar item decision.
 *
 * @covers \Drupal\bioland\BiolandToolbarLlmsTxtLink
 */
class BiolandToolbarLlmsTxtLinkTest extends TestCase {

  private const LLMS = 'llms_txt.llms_txt_config';

  /**
   * The link is kept but disabled, and other links are untouched.
   */
  public function testDisablesLlmsTxtLinkOnly(): void {
    $other = ['title' => 'Content', 'enabled' => TRUE];
    $links = [
      self::LLMS => ['title' => 'llms.txt configuration', 'route_name' => 'llms_txt.llms_txt_config', 'enabled' => TRUE],
      'system.admin_content' => $other,
    ];

    BiolandToolbarLlmsTxtLink::disable($links);

    $this->assertArrayHasKey(self::LLMS, $links, 'The link must be disabled, never unset.');
    $this->assertFalse($links[self::LLMS]['enabled']);
    $this->assertSame('llms.txt configuration', $links[self::LLMS]['title']);
    $this->assertSame('llms_txt.llms_txt_config', $links[self::LLMS]['route_name']);
    $this->assertSame($other, $links['system.admin_content']);
  }

  /**
   * Nothing happens, and nothing is created, when the link is absent.
   */
  public function testNoOpWhenAbsent(): void {
    $links = ['system.admin' => ['enabled' => TRUE]];
    BiolandToolbarLlmsTxtLink::disable($links);
    $this->assertSame(['system.admin' => ['enabled' => TRUE]], $links);

    $empty = [];
    BiolandToolbarLlmsTxtLink::disable($empty);
    $this->assertSame([], $empty);
  }

  /**
   * The module alter delegates to the helper.
   */
  public function testWiring(): void {
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_menu_links_discovered_alter\s*\(&\$links\)\s*\{(?:(?!\nfunction\s).)*BiolandToolbarLlmsTxtLink::disable\(\$links\)/s',
      file_get_contents(dirname(__DIR__, 2) . '/bioland.module')
    );
  }

}
