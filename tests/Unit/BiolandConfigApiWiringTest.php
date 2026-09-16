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
   * Returns one top-level YAML stanza by name.
   *
   * Asserting against the whole routing file would let every assertion below
   * stay green while the thing it guards moved to a DIFFERENT route: put
   * `no_cache: TRUE` or `_bioland_config_api: 'TRUE'` on any other route and a
   * file-wide `assertStringContainsString` still finds it. These assertions
   * are the last thing standing between 211 sites and a public config
   * endpoint, so they are scoped to the stanza they are about.
   *
   * @param string $file
   *   The module file name.
   * @param string $key
   *   The top-level key, without its colon.
   *
   * @return string
   *   The stanza body, from its key line to the next top-level key.
   */
  protected function stanza(string $file, string $key): string {
    $source = $this->moduleFile($file);
    $blocks = preg_split('/^(?=\S)/m', $source, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($blocks as $block) {
      if (str_starts_with($block, $key . ':')) {
        return $block;
      }
    }
    $this->fail("No top-level '$key:' stanza in $file.");
  }

  /**
   * The config API route exists and requires the dedicated access check.
   */
  public function testConfigApiRouteRequiresTheAccessCheck() {
    $route = $this->stanza('bioland.routing.yml', 'bioland.config_api');

    $this->assertStringContainsString("path: '/bioland/api/config'", $route);
    $this->assertStringContainsString('_controller: \'\Drupal\bioland\Controller\BiolandConfigController::document\'', $route);
    $this->assertStringContainsString("_bioland_config_api: 'TRUE'", $route);
    $this->assertStringContainsString("_format: 'json'", $route);
    $this->assertStringContainsString('methods: [GET]', $route);
    $this->assertStringContainsString('no_cache: TRUE', $route);
    // Default access mode is ANY: without ALL, a contrib dynamic access check
    // returning allowed would grant access alongside ours rather than having
    // to agree with it.
    $this->assertStringContainsString("_access_mode: 'ALL'", $route);
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
    $services = $this->stanza('bioland.services.yml', 'services');

    $this->assertStringContainsString('bioland.config_document_builder:', $services);

    // Scoped to the access check's own definition: a tag on some other
    // service would not wire this route's access check.
    $offset = strpos($services, 'bioland.config_api_access_check:');
    $this->assertNotFalse($offset, 'The access check service is not registered.');
    $definition = substr($services, $offset);
    $next = preg_match('/\n  \S/', $definition, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : NULL;
    if ($next !== NULL) {
      $definition = substr($definition, 0, $next);
    }

    $this->assertStringContainsString('Drupal\bioland\Access\BiolandConfigApiAccessCheck', $definition);
    // Both halves of the tag matter: `applies_to` alone, on a tag that is not
    // `access_check`, registers nothing and the route would fall through to
    // whatever else it requires.
    $this->assertStringContainsString('name: access_check', $definition);
    $this->assertStringContainsString('applies_to: _bioland_config_api', $definition);
    $this->assertStringContainsString("arguments: ['@settings']", $definition);
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
