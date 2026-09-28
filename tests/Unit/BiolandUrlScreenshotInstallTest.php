<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * File-content assertions for the BL-1191 install/update/requirements wiring.
 *
 * Matches the style of the other install-hook tests in this suite
 * (SearchApiConvergenceHookTest): these hooks touch real Drupal storage APIs
 * that are not stubbed for execution, so this suite asserts on source text
 * rather than running the hooks.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandUrlScreenshotInstallTest extends TestCase {

  private function moduleRoot(): string {
    $root = dirname(__DIR__, 3);
    if (!file_exists($root . '/bioland.install')) {
      $root = __DIR__ . '/../..';
    }
    return $root;
  }

  public function testServiceYamlDeclaresNeedsDestructionTag(): void {
    $content = file_get_contents($this->moduleRoot() . '/bioland.services.yml');

    $this->assertMatchesRegularExpression(
      '/bioland\.url_screenshot:\s*\n(?:.*\n)*?\s*tags:\s*\n\s*- \{ name: needs_destruction \}/',
      $content,
      'bioland.url_screenshot must carry the needs_destruction tag so core calls destruct() after the response is sent.'
    );
  }

  public function testInstallDoesNotCreateWebsiteImageField(): void {
    $root = $this->moduleRoot();
    $install = file_get_contents($root . '/bioland.install');
    $fields = file_get_contents($root . '/includes/bioland.install.fields.inc');

    $this->assertStringNotContainsString('_bioland_install_website_image_field', $install . $fields);
    $this->assertStringNotContainsString("'field_website_image'", file_get_contents($root . '/src/Service/BiolandUrlScreenshotService.php'));
  }

  public function testRequirementsDeclaresConvertApiDependency(): void {
    $content = file_get_contents($this->moduleRoot() . '/bioland.install');

    $this->assertStringContainsString('bioland_dep_convertapi', $content);
    $this->assertStringContainsString("class_exists('\\\\ConvertApi\\\\ConvertApi')", $content);
  }

  public function testUpdate9090ExistsThrowsRemovesFieldAndConverges(): void {
    $file = $this->moduleRoot() . '/includes/bioland.install.fields.inc';
    $content = file_get_contents($file);

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9090\s*\(\s*\)\s*\{/',
      $content,
      'bioland_update_9090() must exist.'
    );

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9090\s*\([^)]*\)\s*\{.*ConvertApi.*UpdateException.*\}/s',
      $content,
      'bioland_update_9090() must throw an UpdateException when convertapi/convertapi-php is missing.'
    );

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9090\s*\([^)]*\)\s*\{.*_bioland_remove_website_image_field\s*\(\s*\).*_bioland_v2_update_search_and_facets_config\s*\(/s',
      $content,
      'bioland_update_9090() must call _bioland_remove_website_image_field() and re-apply the canonical v2 config.'
    );
  }

}
