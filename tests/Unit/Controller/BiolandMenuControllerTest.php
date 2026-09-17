<?php

namespace Drupal\Tests\bioland\Unit\Controller;

use Drupal\bioland\Controller\BiolandMenuController;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the dedicated "Add Mega Menu component" controller.
 *
 * Beyond ordinary behaviour coverage this pins the security property the
 * p02-01 dispatcher depends on: BiolandComponentMenuFormMode::applies() trusts
 * $form_state->getFormObject()->getOperation() verbatim, so the "component"
 * operation must be impossible to induce from a request. It can only originate
 * from the hard-coded class constant this controller passes to the entity form
 * builder - the route declares a _controller, never an _entity_form, so no
 * route default, path segment, query parameter or POST value reaches the
 * $operation argument.
 *
 * @covers \Drupal\bioland\Controller\BiolandMenuController
 */
class BiolandMenuControllerTest extends TestCase {

  /**
   * The entity form builder double.
   *
   * @var \Drupal\Tests\bioland\Unit\Controller\TestEntityFormBuilder
   */
  protected $formBuilder;

  /**
   * The menu_link_content storage double.
   *
   * @var \Drupal\Tests\bioland\Unit\Controller\TestMenuLinkStorage
   */
  protected $storage;

  /**
   * Entity type ids the controller asked storage for.
   *
   * @var array
   */
  protected $requestedStorage = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->formBuilder = new TestEntityFormBuilder();
    $this->storage = new TestMenuLinkStorage();
    $this->requestedStorage = [];
  }

  /**
   * Builds the controller over the doubles.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface|null $menuLinkManager
   *   The menu link manager double, or NULL for an unwired container.
   */
  private function createController(?MenuLinkManagerInterface $menuLinkManager = NULL): BiolandMenuController {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(function ($entity_type_id) {
      $this->requestedStorage[] = $entity_type_id;
      return $this->storage;
    });

    return new BiolandMenuController($entityTypeManager, $this->formBuilder, $menuLinkManager);
  }

  /**
   * Builds a menu link manager knowing one link per menu.
   */
  private function createMenuLinkManager(): TestMenuLinkManager {
    return new TestMenuLinkManager([
      'menu_link_content:aaaa-1111' => ['menu_name' => 'main'],
      'menu_link_content:bbbb-2222' => ['menu_name' => 'main'],
      'menu_link_content:cccc-3333' => ['menu_name' => 'footer'],
      'system.admin' => ['menu_name' => 'admin'],
    ]);
  }

  /**
   * Returns the create() values for a ?parent= query value.
   *
   * @param mixed $parent
   *   The raw query value, or NULL to send no query at all.
   * @param string $menu
   *   The menu being added to.
   * @param bool $wired
   *   FALSE to omit the menu link manager.
   *
   * @return array
   *   The values the controller handed to storage.
   */
  private function valuesForParent($parent, string $menu = 'main', bool $wired = TRUE): array {
    $request = $parent === NULL ? NULL : new Request(['parent' => $parent]);
    $manager = $wired ? $this->createMenuLinkManager() : NULL;
    $this->createController($manager)->addComponentLink($this->createMenu($menu), $request);

    return $this->storage->createdValues;
  }

  /**
   * Builds a menu double.
   */
  private function createMenu(string $id): MenuInterface {
    $menu = $this->createMock(MenuInterface::class);
    $menu->method('id')->willReturn($id);

    return $menu;
  }

  /**
   * The new link is created in the menu from the route parameter.
   */
  public function testPrefillsMenuNameFromTheRouteParameter(): void {
    $this->createController()->addComponentLink($this->createMenu('main'));

    $this->assertSame(['menu_name' => 'main'], $this->storage->createdValues);
  }

  /**
   * A different menu prefills a different menu_name.
   */
  public function testMenuNameFollowsTheMenuBeingEdited(): void {
    $this->createController()->addComponentLink($this->createMenu('bioland-mega-menu'));

    $this->assertSame(['menu_name' => 'bioland-mega-menu'], $this->storage->createdValues);
  }

  /**
   * The entity is created through menu_link_content storage.
   */
  public function testCreatesAMenuLinkContentEntity(): void {
    $this->createController()->addComponentLink($this->createMenu('main'));

    $this->assertSame([BiolandComponentMenuFormMode::ENTITY_TYPE], $this->requestedStorage);
    $this->assertSame(['menu_link_content'], $this->requestedStorage);
  }

  /**
   * The form is built for the "component" operation, on the created entity.
   */
  public function testReturnsTheFormForTheComponentOperation(): void {
    $form = $this->createController()->addComponentLink($this->createMenu('main'));

    $this->assertSame(BiolandComponentMenuFormMode::OPERATION, $this->formBuilder->operation);
    $this->assertSame('component', $this->formBuilder->operation);
    $this->assertSame($this->storage->created, $this->formBuilder->entity);
    $this->assertSame($this->formBuilder->returnValue, $form);
  }

  /**
   * The container factory wires the two services the controller needs.
   */
  public function testCreateResolvesItsServicesFromTheContainer(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $container = new TestContainer([
      'entity_type.manager' => $entityTypeManager,
      'entity.form_builder' => $this->formBuilder,
      'plugin.manager.menu.link' => $this->createMenuLinkManager(),
    ]);

    $controller = BiolandMenuController::create($container);

    $this->assertInstanceOf(BiolandMenuController::class, $controller);
    $this->assertSame(
      ['entity_type.manager', 'entity.form_builder', 'plugin.manager.menu.link'],
      $container->requested,
      'The controller must depend on exactly these three services.'
    );
  }

  /**
   * A parent in the same menu is accepted and prefilled.
   */
  public function testAcceptsAParentInTheSameMenu(): void {
    $this->assertSame(
      ['menu_name' => 'main', 'parent' => 'menu_link_content:aaaa-1111'],
      $this->valuesForParent('menu_link_content:aaaa-1111')
    );
  }

  /**
   * A non-content menu link is a valid parent too, when it is in the menu.
   */
  public function testAcceptsANonContentParentInTheSameMenu(): void {
    $this->assertSame(
      ['menu_name' => 'admin', 'parent' => 'system.admin'],
      $this->valuesForParent('system.admin', 'admin')
    );
  }

  /**
   * SECURITY: a plugin id that does not exist is dropped silently.
   */
  public function testRejectsABogusParent(): void {
    $this->assertSame(['menu_name' => 'main'], $this->valuesForParent('menu_link_content:does-not-exist'));
  }

  /**
   * SECURITY: an existing link in ANOTHER menu is dropped silently.
   *
   * The check that stops a request from grafting a new link onto a menu the
   * editor is not editing.
   */
  public function testRejectsAParentFromAnotherMenu(): void {
    $this->assertSame(['menu_name' => 'main'], $this->valuesForParent('menu_link_content:cccc-3333'));
  }

  /**
   * SECURITY: an empty parent is dropped silently.
   */
  public function testRejectsAnEmptyParent(): void {
    $this->assertSame(['menu_name' => 'main'], $this->valuesForParent(''));
  }

  /**
   * SECURITY: the request layer rejects "?parent[]=x" before the controller.
   *
   * Not "dropped silently" - InputBag::get() throws BadRequestException for any
   * value that is neither scalar nor \Stringable, and HttpKernel turns that
   * into a 400. There is no request in which addComponentLink() is entered with
   * an array parent, so nothing here has to cope with one.
   */
  public function testANonScalarParentIsRejectedByTheRequestLayer(): void {
    $this->expectException(BadRequestException::class);
    $this->expectExceptionMessage('Input value "parent" contains a non-scalar value.');

    $this->valuesForParent(['menu_link_content:aaaa-1111']);
  }

  /**
   * The 400 is raised before anything is created.
   */
  public function testANonScalarParentCreatesNothing(): void {
    try {
      $this->valuesForParent(['menu_link_content:aaaa-1111']);
      $this->fail('A non-scalar parent must not reach the controller at all.');
    }
    catch (BadRequestException $e) {
      $this->assertNull($this->storage->createdValues, 'No entity may be created.');
      $this->assertNull($this->formBuilder->operation, 'And no form may be opened.');
    }
  }

  /**
   * DEFENCE IN DEPTH: a non-string parent is dropped rather than looked up.
   *
   * Unreachable through a real query string - those yield strings, and
   * InputBag::get() has already refused everything that is not scalar - so this
   * exercises validatedParent()'s !is_string() guard directly, through a
   * synthetic Request no HTTP request can produce. The guard exists so an int
   * is never handed to hasDefinition(), which is typed for a string id.
   */
  public function testANonStringParentIsDroppedByTheGuard(): void {
    $this->assertSame(['menu_name' => 'main'], $this->valuesForParent(42));
  }

  /**
   * No request at all means no parent, and no error.
   */
  public function testAcceptsNoParentAtAll(): void {
    $this->assertSame(['menu_name' => 'main'], $this->valuesForParent(NULL));
  }

  /**
   * Without the menu link manager nothing can be validated, so nothing is used.
   */
  public function testRejectsEveryParentWhenTheManagerIsMissing(): void {
    $this->assertSame(
      ['menu_name' => 'main'],
      $this->valuesForParent('menu_link_content:aaaa-1111', 'main', FALSE)
    );
  }

  /**
   * SECURITY: supplying a parent does not change the form operation.
   */
  public function testTheOperationIsUnchangedByAParent(): void {
    $this->createController($this->createMenuLinkManager())
      ->addComponentLink($this->createMenu('main'), new Request(['parent' => 'menu_link_content:aaaa-1111']));

    $this->assertSame(BiolandComponentMenuFormMode::OPERATION, $this->formBuilder->operation);
  }

  /**
   * SECURITY: the operation string is a constant, never request-derived.
   *
   * The dispatcher trusts getOperation() with no further validation, so this
   * file is the whole trust boundary for the operation.
   *
   * This used to be a blanket ban on every request-shaped token in the file.
   * That is no longer the right pin: the controller now legitimately reads
   * ?parent=. So the pin is tightened instead of loosened - it asserts the
   * exact shape of the single getForm() call, which is what actually decides
   * the operation, rather than policing vocabulary around it.
   */
  public function testTheOperationCannotComeFromTheRequest(): void {
    $source = $this->controllerSource();

    $this->assertSame(
      1,
      substr_count($source, '->getForm('),
      'Exactly one call may open the entity form.'
    );
    $this->assertMatchesRegularExpression(
      '/->getForm\(\s*\$menu_link,\s*BiolandComponentMenuFormMode::OPERATION\s*\)/',
      $source,
      'The operation argument must be the class constant, literally.'
    );

    // Superglobals and the container-wide request accessors stay banned
    // outright: the one request value this controller may read arrives as a
    // resolved method argument, so none of these has any legitimate use here.
    $forbidden = ['$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_COOKIE', 'RequestStack', 'getCurrentRequest'];
    foreach ($forbidden as $needle) {
      $this->assertStringNotContainsString(
        $needle,
        $source,
        sprintf('"%s" must not appear in the controller.', $needle)
      );
    }
  }

  /**
   * SECURITY: the only request read is ?parent=, and only inside the validator.
   *
   * Keeps the widened trust boundary narrow: one read, one key, one method.
   * addComponentLink() itself must never touch the request, so a future edit
   * cannot quietly route a second request value into the create() values.
   */
  public function testTheOnlyRequestReadIsTheValidatedParent(): void {
    $source = $this->controllerSource();

    $this->assertSame(
      1,
      substr_count($source, '$request->query'),
      'The request may be read exactly once.'
    );
    $this->assertStringContainsString("\$request->query->get('parent')", $source);

    $this->assertStringNotContainsString(
      '$request->',
      $this->methodBody('addComponentLink'),
      'addComponentLink() must delegate every request read to validatedParent(); it may only pass the request along.'
    );
    $this->assertStringContainsString(
      "\$request->query->get('parent')",
      $this->methodBody('validatedParent'),
      'The request read must live in the validator.'
    );
  }

  /**
   * SECURITY: a validated parent is the only thing that can become one.
   *
   * The create() values must never receive the raw query value; it has to pass
   * through validatedParent() first.
   */
  public function testTheParentReachesStorageOnlyThroughTheValidator(): void {
    $body = $this->methodBody('addComponentLink');

    $this->assertStringContainsString('$this->validatedParent($menu, $request)', $body);
    $this->assertMatchesRegularExpression(
      "/\\\$values\['parent'\] = \\\$parent;/",
      $body,
      'Only the validator\'s return value may be stored as the parent.'
    );
  }

  /**
   * Returns the controller's full source.
   */
  private function controllerSource(): string {
    return file_get_contents((new ReflectionClass(BiolandMenuController::class))->getFileName());
  }

  /**
   * Returns the source of one controller method.
   *
   * @param string $method
   *   The method name.
   *
   * @return string
   *   The method's own lines, docblock excluded.
   */
  private function methodBody(string $method): string {
    $reflection = new ReflectionMethod(BiolandMenuController::class, $method);
    $lines = file($reflection->getFileName());

    return implode('', array_slice(
      $lines,
      $reflection->getStartLine() - 1,
      $reflection->getEndLine() - $reflection->getStartLine() + 1
    ));
  }

  /**
   * SECURITY: no route hands the operation to Drupal as a form default.
   *
   * An _entity_form default of the shape "menu_link_content.component" would
   * be a second, route-driven source of the operation. There is exactly one.
   */
  public function testNoRouteDeclaresTheOperationAsAnEntityFormDefault(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/bioland.routing.yml');

    $this->assertStringNotContainsString('_entity_form', $routing);
    $this->assertSame(
      1,
      substr_count($routing, 'add-component'),
      'Exactly one route may serve the component add flow.'
    );
  }

}

/**
 * Entity form builder double recording the entity and operation it got.
 */
class TestEntityFormBuilder implements EntityFormBuilderInterface {

  /**
   * The entity passed to getForm().
   *
   * @var mixed
   */
  public $entity;

  /**
   * The operation passed to getForm().
   *
   * @var string|null
   */
  public $operation;

  /**
   * The render array getForm() returns.
   *
   * @var array
   */
  public $returnValue = ['#form' => 'component-add'];

  /**
   * {@inheritdoc}
   */
  public function getForm($entity, $operation = 'default', array $form_state_additions = []) {
    $this->entity = $entity;
    $this->operation = $operation;

    return $this->returnValue;
  }

}

/**
 * menu_link_content storage double recording create() values.
 *
 * A hand-written double rather than a mock because the shared
 * EntityStorageInterface stub does not declare create().
 */
class TestMenuLinkStorage implements EntityStorageInterface {

  /**
   * The values create() was called with.
   *
   * @var array|null
   */
  public $createdValues;

  /**
   * The entity create() returned.
   *
   * @var object|null
   */
  public $created;

  /**
   * Creates an unsaved entity.
   *
   * @param array $values
   *   The field values.
   *
   * @return object
   *   The unsaved entity double.
   */
  public function create(array $values = []) {
    $this->createdValues = $values;
    $this->created = (object) $values;

    return $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery() {
    return NULL;
  }

}

/**
 * Menu link manager double over a fixed definition map.
 */
class TestMenuLinkManager implements MenuLinkManagerInterface {

  /**
   * The definitions, keyed by plugin id.
   *
   * @var array
   */
  protected $definitions;

  /**
   * Constructs the double.
   *
   * @param array $definitions
   *   The definitions, keyed by plugin id.
   */
  public function __construct(array $definitions) {
    $this->definitions = $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return isset($this->definitions[$plugin_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->definitions[$plugin_id] ?? NULL;
  }

}

/**
 * Service container double recording the ids it was asked for.
 */
class TestContainer implements ContainerInterface {

  /**
   * The services, keyed by id.
   *
   * @var array
   */
  protected $services;

  /**
   * The ids get() was called with, in order.
   *
   * @var array
   */
  public $requested = [];

  /**
   * Constructs the container double.
   *
   * @param array $services
   *   The services, keyed by id.
   */
  public function __construct(array $services) {
    $this->services = $services;
  }

  /**
   * {@inheritdoc}
   */
  public function get($id) {
    $this->requested[] = $id;

    return $this->services[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function has($id) {
    return isset($this->services[$id]);
  }

}
