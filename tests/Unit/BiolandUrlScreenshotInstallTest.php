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

  public function testInstallCallsWebsiteImageFieldHelper(): void {
    $content = file_get_contents($this->moduleRoot() . '/bioland.install');

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_install\s*\(\s*\)\s*\{.*_bioland_install_website_image_field\s*\(\s*\)/s',
      $content,
      'bioland_install() must call _bioland_install_website_image_field().'
    );
  }

  public function testRequirementsDeclaresConvertApiDependency(): void {
    $content = file_get_contents($this->moduleRoot() . '/bioland.install');

    // BL-1191 and BL-1192 (auto-document-preview) both depend on the same
    // convertapi/convertapi-php library; bsl-cop-17-dev merges the two
    // identical bioland_dep_convertapi requirement blocks into one, using
    // the correct \ConvertApi\ConvertApi class (the triple-segment
    // \ConvertApi\ConvertApi\ConvertApi this test previously asserted on was
    // a copy-paste bug that never matched the real library, so the
    // requirement would have shown permanently "Missing" even once
    // composer had installed it).
    $this->assertStringContainsString('bioland_dep_convertapi', $content);
    $this->assertStringContainsString("class_exists('\\ConvertApi\\ConvertApi')", $content);
  }

  public function testUpdate9090ExistsThrowsAndConverges(): void {
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
      '/function\s+bioland_update_9090\s*\([^)]*\)\s*\{.*_bioland_install_website_image_field\s*\(\s*\).*_bioland_v2_update_search_and_facets_config\s*\(/s',
      $content,
      'bioland_update_9090() must call _bioland_install_website_image_field() and re-apply the canonical v2 config.'
    );
  }

}
