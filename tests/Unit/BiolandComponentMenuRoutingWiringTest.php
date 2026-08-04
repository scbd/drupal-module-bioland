<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Controller\BiolandMenuController;
use Drupal\bioland\Form\BiolandComponentMenuLinkForm;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use PHPUnit\Framework\TestCase;

/**
 * Guards the wiring of the dedicated "Add component menu link" flow.
 *
 * Three declarations have to agree for the flow to exist at all, and none of
 * the three can see the other two at runtime:
 *   1. bioland.routing.yml declares the route and its access;
 *   2. bioland.links.action.yml puts an action for that route on the menu
 *      manage screen;
 *   3. bioland_entity_type_alter() registers the "component" form operation
 *      the route's controller asks for.
 * A typo in any one of them fails silently in the browser (a 404, a missing
 * button, or the regular form served under the component URL), so each edge is
 * pinned here.
 *
 * Access is asserted to be IDENTICAL to core's entity.menu.add_link_form —
 * _entity_create_access on menu_link_content and nothing else. Adding a
 * permission or role requirement here would silently diverge the component
 * flow from the regular one (plan decision #5).
 *
 * @group bioland
 * @covers ::bioland_entity_type_alter
 */
class BiolandComponentMenuRoutingWiringTest extends TestCase {

  /**
   * The machine name of the route under test.
   */
  private const ROUTE = 'bioland.menu_link_component.add';

  /**
   * The local action id under test.
   */
  private const ACTION = 'bioland.menu_link_component_add';

  /**
   * The single translatable string this task introduces.
   */
  private const TITLE = 'Add component menu link';

  /**
   * Returns the module root directory.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 2);
  }

  /**
   * Parses a nested YAML file into an array.
   *
   * The module has no symfony/yaml dependency, and the flat readers in
   * BiolandLocalTasksTest / BiolandSettingsRoutingWiringTest cannot see the
   * nesting this route needs (options.parameters.menu.type) or the sequence
   * under appears_on. This reader handles exactly what these two files use:
   * block maps, block sequences of scalars, quoted scalars and comments.
   *
   * @param string $relativePath
   *   Path relative to the module root.
   *
   * @return array
   *   The parsed structure.
   */
  private function parseYaml(string $relativePath): array {
    $path = $this->moduleRoot() . '/' . $relativePath;
    $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $index = 0;

    return $this->parseBlock($lines, 0, $index);
  }

  /**
   * Parses one indentation block, advancing the shared cursor.
   *
   * @param array $lines
   *   Every line of the file.
   * @param int $indent
   *   The minimum indentation belonging to this block.
   * @param int $index
   *   The cursor into $lines, advanced in place.
   *
   * @return array
   *   The block, as a map or a list.
   */
  private function parseBlock(array $lines, int $indent, int &$index): array {
    $result = [];

    while ($index < count($lines)) {
      $line = $lines[$index];
      if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
        $index++;
        continue;
      }

      $lineIndent = strlen($line) - strlen(ltrim($line));
      if ($lineIndent < $indent) {
        break;
      }

      $trimmed = ltrim($line);
      if (strpos($trimmed, '- ') === 0) {
        $result[] = $this->scalar(substr($trimmed, 2));
        $index++;
        continue;
      }

      if (preg_match('/^([A-Za-z0-9_.\-]+):\s*(.*)$/', $trimmed, $matches)) {
        $value = trim($matches[2]);
        if ($value === '') {
          $index++;
          $result[$matches[1]] = $this->parseBlock($lines, $lineIndent + 1, $index);
        }
        else {
          $result[$matches[1]] = $this->scalar($value);
          $index++;
        }
        continue;
      }

      $index++;
    }

    return $result;
  }

  /**
   * Normalises a scalar: strips quotes and resolves YAML booleans.
   *
   * @param string $value
   *   The raw scalar text.
   *
   * @return mixed
   *   The normalised value.
   */
  private function scalar(string $value) {
    $value = trim($value);
    $value = trim($value, "'\"");
    if (in_array(strtolower($value), ['true', 'yes'], TRUE)) {
      return TRUE;
    }
    if (in_array(strtolower($value), ['false', 'no'], TRUE)) {
      return FALSE;
    }

    return $value;
  }

  /**
   * The route exists at the agreed path, with the agreed controller and title.
   */
  public function testRouteIsDeclaredWithTheAgreedPathAndController(): void {
    $routes = $this->parseYaml('bioland.routing.yml');

    $this->assertArrayHasKey(self::ROUTE, $routes, 'The component add route must be declared.');
    $route = $routes[self::ROUTE];

    $this->assertSame('/admin/structure/menu/manage/{menu}/add-component', $route['path']);
    $this->assertSame(
      '\Drupal\bioland\Controller\BiolandMenuController::addComponentLink',
      $route['defaults']['_controller']
    );
    $this->assertSame(self::TITLE, $route['defaults']['_title']);
  }

  /**
   * The route's controller and method actually exist.
   */
  public function testRouteControllerIsCallable(): void {
    $routes = $this->parseYaml('bioland.routing.yml');
    [$class, $method] = explode('::', $routes[self::ROUTE]['defaults']['_controller']);

    $this->assertTrue(class_exists($class), sprintf('Controller class %s must exist.', $class));
    $this->assertTrue(
      method_exists($class, $method),
      sprintf('Controller method %s::%s must exist.', $class, $method)
    );
    $this->assertSame(BiolandMenuController::class, ltrim($class, '\\'));
  }

  /**
   * Access mirrors core's add-link route exactly: no new permission or role.
   */
  public function testRouteAccessMirrorsCoreAddLinkExactly(): void {
    $route = $this->parseYaml('bioland.routing.yml')[self::ROUTE];

    $this->assertSame(
      ['_entity_create_access' => 'menu_link_content'],
      $route['requirements'],
      'The component add route must require exactly what core entity.menu.add_link_form requires - no extra _permission or _role.'
    );
  }

  /**
   * The {menu} parameter upcasts to a menu entity, on an admin route.
   */
  public function testRouteUpcastsTheMenuParameterAndIsAnAdminRoute(): void {
    $route = $this->parseYaml('bioland.routing.yml')[self::ROUTE];

    $this->assertTrue($route['options']['_admin_route'], 'The form must render in the admin theme, as core\'s does.');
    $this->assertSame(
      'entity:menu',
      $route['options']['parameters']['menu']['type'],
      'Without the entity:menu upcast the controller receives a string and MenuInterface::id() fatals.'
    );
  }

  /**
   * No core route is redefined by this module.
   */
  public function testNoCoreMenuRouteIsOverridden(): void {
    $routes = $this->parseYaml('bioland.routing.yml');

    foreach (array_keys($routes) as $routeName) {
      $this->assertStringStartsWith(
        'bioland.',
        $routeName,
        sprintf('Route "%s" is not namespaced to bioland; core menu routes must stay untouched.', $routeName)
      );
    }
  }

  /**
   * The local action points at the route and appears beside core's Add link.
   */
  public function testLocalActionAppearsOnTheMenuEditForm(): void {
    $actions = $this->parseYaml('bioland.links.action.yml');

    $this->assertArrayHasKey(self::ACTION, $actions, 'The component add local action must be declared.');
    $action = $actions[self::ACTION];

    $this->assertSame(self::ROUTE, $action['route_name']);
    $this->assertSame(self::TITLE, $action['title']);
    $this->assertContains(
      'entity.menu.edit_form',
      $action['appears_on'],
      'The action must appear wherever core\'s "Add link" does - the menu manage screen.'
    );
  }

  /**
   * Every declared local action targets a route this module defines.
   */
  public function testEveryLocalActionTargetsADeclaredRoute(): void {
    $actions = $this->parseYaml('bioland.links.action.yml');
    $routes = $this->parseYaml('bioland.routing.yml');
    $this->assertNotEmpty($actions, 'No local actions parsed - bioland.links.action.yml may be malformed.');

    foreach ($actions as $id => $action) {
      $this->assertArrayHasKey(
        $action['route_name'],
        $routes,
        sprintf('Local action "%s" targets route "%s", which bioland.routing.yml does not declare.', $id, $action['route_name'])
      );
    }
  }

  /**
   * hook_entity_type_alter registers the component form class on the operation.
   */
  public function testEntityTypeAlterRegistersTheComponentFormOperation(): void {
    require_once $this->moduleRoot() . '/bioland.module';

    $menuLink = new TestAlterableEntityType();
    $entity_types = [
      BiolandComponentMenuFormMode::ENTITY_TYPE => $menuLink,
      'node' => new TestAlterableEntityType(),
    ];

    bioland_entity_type_alter($entity_types);

    $this->assertSame(
      [BiolandComponentMenuFormMode::OPERATION => BiolandComponentMenuLinkForm::class],
      $menuLink->formClasses,
      'The "component" operation must map to BiolandComponentMenuLinkForm, and nothing else may be registered.'
    );
    $this->assertSame(
      [],
      $entity_types['node']->formClasses,
      'Only menu_link_content may be altered.'
    );
  }

  /**
   * The hook is a no-op when menu_link_content is absent.
   */
  public function testEntityTypeAlterIsANoOpWithoutMenuLinkContent(): void {
    require_once $this->moduleRoot() . '/bioland.module';

    $entity_types = ['node' => new TestAlterableEntityType()];
    bioland_entity_type_alter($entity_types);

    $this->assertSame([], $entity_types['node']->formClasses);
  }

  /**
   * The user-facing title is present in the English translation catalog.
   *
   * The catalogs are the drift detector: renaming the route or action title
   * without updating them silently un-translates the string in 66 locales.
   */
  public function testTheActionTitleIsPresentInTheEnglishCatalog(): void {
    $catalog = file_get_contents($this->moduleRoot() . '/translations/bioland.en.po');

    $this->assertStringContainsString(
      sprintf('msgid "%s"', self::TITLE),
      $catalog,
      'The route/action title must be registered in the translation catalogs.'
    );
  }

}

/**
 * Entity type definition double recording setFormClass() calls.
 */
class TestAlterableEntityType {

  /**
   * Form classes registered on this definition, keyed by operation.
   *
   * @var array
   */
  public $formClasses = [];

  /**
   * Registers a form class for an operation.
   *
   * @param string $operation
   *   The operation name.
   * @param string $class
   *   The form class.
   *
   * @return $this
   */
  public function setFormClass($operation, $class) {
    $this->formClasses[$operation] = $class;
    return $this;
  }

}
