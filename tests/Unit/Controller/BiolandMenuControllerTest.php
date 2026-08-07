<?php

namespace Drupal\Tests\bioland\Unit\Controller;

use Drupal\bioland\Controller\BiolandMenuController;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   */
  private function createController(): BiolandMenuController {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(function ($entity_type_id) {
      $this->requestedStorage[] = $entity_type_id;
      return $this->storage;
    });

    return new BiolandMenuController($entityTypeManager, $this->formBuilder);
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
    ]);

    $controller = BiolandMenuController::create($container);

    $this->assertInstanceOf(BiolandMenuController::class, $controller);
    $this->assertSame(
      ['entity_type.manager', 'entity.form_builder'],
      $container->requested,
      'The controller must depend on exactly these two services.'
    );
  }

  /**
   * SECURITY: the operation string is a constant, never request-derived.
   *
   * The dispatcher trusts getOperation() with no further validation, so this
   * file is the whole trust boundary. Any read of the request here would let a
   * caller opt an arbitrary menu link form into Component mode.
   */
  public function testTheOperationCannotComeFromTheRequest(): void {
    $source = file_get_contents((new ReflectionClass(BiolandMenuController::class))->getFileName());

    $this->assertStringContainsString(
      'BiolandComponentMenuFormMode::OPERATION',
      $source,
      'The operation must be passed as the class constant.'
    );

    $forbidden = ['$_GET', '$_POST', '$_REQUEST', 'getRequest', 'RequestStack', '->query', '->request', 'getCurrentRequest'];
    foreach ($forbidden as $needle) {
      $this->assertStringNotContainsString(
        $needle,
        $source,
        sprintf('"%s" must not appear in the controller: the "component" operation must never be request-derived.', $needle)
      );
    }
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
