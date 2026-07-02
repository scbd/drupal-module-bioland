<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards against regressions in the Bioland settings tab wiring.
 *
 * Ensures every settings route defined in bioland.routing.yml that is meant
 * to appear as a local task has a matching entry in bioland.links.task.yml,
 * and specifically that the Admin tab (bioland.settings.admin) is present
 * and ordered last. This is a lightweight, dependency-free YAML reader
 * (the module has no symfony/yaml requirement) sufficient for the flat
 * `key: value` structure of these two files.
 */
class BiolandLocalTasksTest extends TestCase {

  /**
   * Parses a simple two-level YAML file (top-level keys with scalar props).
   *
   * @param string $path
   *   Absolute path to the YAML file.
   *
   * @return array
   *   Associative array keyed by the top-level machine name, each value an
   *   associative array of its scalar properties.
   */
  private function parseFlatYaml(string $path): array {
    $lines = file(__DIR__ . '/../../' . $path, FILE_IGNORE_NEW_LINES);
    $result = [];
    $currentKey = NULL;

    foreach ($lines as $line) {
      if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
        continue;
      }
      // Top-level key: starts at column 0 and ends with a colon.
      if (preg_match('/^(\S[^:]*):\s*$/', $line, $matches)) {
        $currentKey = $matches[1];
        $result[$currentKey] = [];
        continue;
      }
      // Indented property: "  key: value".
      if ($currentKey !== NULL && preg_match('/^\s+([A-Za-z0-9_]+):\s*(.*)$/', $line, $matches)) {
        $value = trim($matches[2]);
        // Strip surrounding quotes.
        $value = trim($value, "'\"");
        $result[$currentKey][$matches[1]] = $value;
      }
    }

    return $result;
  }

  /**
   * Routes that are expected to render as local task tabs on the settings
   * form (i.e. every route sharing the BiolandSettingsForm base route
   * hierarchy). Kept in sync manually with bioland.routing.yml.
   */
  private function getExpectedTabRoutes(): array {
    return [
      'bioland.settings',
      'bioland.settings.field_visibility',
      'bioland.settings.tags',
      'bioland.settings.help_comments',
      'bioland.settings.front_end',
      'bioland.settings.front_end.general',
      'bioland.settings.front_end.mega_menu',
      'bioland.settings.front_end.home_page',
      'bioland.settings.front_end.home_widgets',
      'bioland.settings.system_functions',
      'bioland.settings.admin',
    ];
  }

  /**
   * Every expected settings route must have a matching local task entry.
   */
  public function testEveryExpectedRouteHasALocalTask(): void {
    $tasks = $this->parseFlatYaml('bioland.links.task.yml');
    $taskRouteNames = array_column($tasks, 'route_name');

    foreach ($this->getExpectedTabRoutes() as $routeName) {
      $this->assertContains(
        $routeName,
        $taskRouteNames,
        sprintf('Expected a local task entry in bioland.links.task.yml for route "%s".', $routeName)
      );
    }
  }

  /**
   * The Admin tab must exist, target the admin route, and be the LAST tab
   * on the bioland.settings base route (highest weight among siblings).
   */
  public function testAdminTabExistsAndIsLast(): void {
    $tasks = $this->parseFlatYaml('bioland.links.task.yml');

    $this->assertArrayHasKey(
      'bioland.settings_admin_tab',
      $tasks,
      'The Admin local task (bioland.settings_admin_tab) must be defined in bioland.links.task.yml.'
    );

    $adminTask = $tasks['bioland.settings_admin_tab'];
    $this->assertSame('bioland.settings.admin', $adminTask['route_name']);
    $this->assertSame('bioland.settings', $adminTask['base_route']);

    $adminWeight = (int) $adminTask['weight'];

    // Compare against every other tab sharing the same base_route.
    foreach ($tasks as $machineName => $task) {
      if ($machineName === 'bioland.settings_admin_tab') {
        continue;
      }
      if (($task['base_route'] ?? NULL) !== 'bioland.settings') {
        continue;
      }
      $this->assertGreaterThan(
        (int) $task['weight'],
        $adminWeight,
        sprintf('Admin tab weight (%d) must be greater than sibling tab "%s" weight (%d) so it renders last.', $adminWeight, $machineName, (int) $task['weight'])
      );
    }
  }

  /**
   * The admin route itself must exist and require the administrator role,
   * matching the access model used for the sibling System Functions tab.
   */
  public function testAdminRouteExistsWithAdministratorRoleRequirement(): void {
    $lines = file(__DIR__ . '/../../bioland.routing.yml', FILE_IGNORE_NEW_LINES);
    $content = implode("\n", $lines);

    $this->assertStringContainsString('bioland.settings.admin:', $content);

    // Extract the block for bioland.settings.admin (from its header to the
    // next top-level key or EOF).
    $this->assertMatchesRegularExpression(
      '/^bioland\.settings\.admin:\n(?:.+\n?)*?\s+_role:\s*[\'"]administrator[\'"]/m',
      $content,
      'bioland.settings.admin route must require the "administrator" role, matching bioland.settings.system_functions.'
    );
  }

}
