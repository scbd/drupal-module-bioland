<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Access\BiolandConfigApiAccessCheck;
use PHPUnit\Framework\TestCase;

/**
 * Guards the route, permission and service wiring of the config API.
 *
 * The module has no YAML parser available in unit tests, so these assertions
 * are textual. They exist to catch the wiring regressions that would turn a
 * private endpoint public across every Bioland site.
 */
class BiolandConfigApiWiringTest extends TestCase {

  /**
   * Reads a module file.
   *
   * @param string $name
   *   The file name relative to the module root.
   *
   * @return string
   *   The file contents.
   */
  protected function moduleFile(string $name): string {
    $path = __DIR__ . '/../../' . $name;
    $this->assertFileExists($path);
    return file_get_contents($path);
  }

  /**
   * No route in the module is open to everyone.
   */
  public function testNoRouteIsOpenToEveryone() {
    $routing = preg_replace('/^\s*#.*$/m', '', $this->moduleFile('bioland.routing.yml'));
    $this->assertDoesNotMatchRegularExpression("/_access:\s*'?TRUE'?/i", $routing);
  }

  /**
   * The config API route exists and requires the dedicated access check.
   */
  public function testConfigApiRouteRequiresTheAccessCheck() {
    $routing = $this->moduleFile('bioland.routing.yml');

    $this->assertStringContainsString('bioland.config_api:', $routing);
    $this->assertStringContainsString("path: '/bioland/api/config'", $routing);
    $this->assertStringContainsString('_controller: \'\Drupal\bioland\Controller\BiolandConfigController::document\'', $routing);
    $this->assertStringContainsString("_bioland_config_api: 'TRUE'", $routing);
    $this->assertStringContainsString("_format: 'json'", $routing);
    $this->assertStringContainsString('methods: [GET]', $routing);
    $this->assertStringContainsString('no_cache: TRUE', $routing);
  }

  /**
   * The route path does not collide with the module's admin form routes.
   */
  public function testRoutePathDoesNotCollide() {
    $routing = $this->moduleFile('bioland.routing.yml');
    preg_match_all("/^\s+path: '([^']+)'/m", $routing, $matches);

    $paths = $matches[1];
    $this->assertContains('/bioland/api/config', $paths);
    $this->assertSame(count($paths), count(array_unique($paths)), 'Two routes declare the same path.');
  }

  /**
   * The dedicated permission is declared and access-restricted.
   */
  public function testPermissionIsDeclared() {
    $permissions = $this->moduleFile('bioland.permissions.yml');

    $this->assertStringContainsString(BiolandConfigApiAccessCheck::PERMISSION . ':', $permissions);
    $offset = strpos($permissions, BiolandConfigApiAccessCheck::PERMISSION . ':');
    $this->assertStringContainsString('restrict access: TRUE', substr($permissions, $offset));
  }

  /**
   * The access check and the document builder are registered services.
   */
  public function testServicesAreRegistered() {
    $services = $this->moduleFile('bioland.services.yml');

    $this->assertStringContainsString('bioland.config_document_builder:', $services);
    $this->assertStringContainsString('bioland.config_api_access_check:', $services);
    $this->assertStringContainsString('Drupal\bioland\Access\BiolandConfigApiAccessCheck', $services);
    $this->assertStringContainsString('applies_to: _bioland_config_api', $services);
    $this->assertStringContainsString("arguments: ['@settings']", $services);
  }

  /**
   * No source file accepts the api key from a query string.
   */
  public function testNoSourceFileReadsTheKeyFromTheQueryString() {
    foreach (['src/Controller/BiolandConfigController.php', 'src/Access/BiolandConfigApiAccessCheck.php'] as $file) {
      $source = $this->moduleFile($file);
      $this->assertDoesNotMatchRegularExpression('/query->get\(\s*[\'"]api[-_]?key/i', $source, "$file must not read the api key from the query string.");
    }
  }

}
