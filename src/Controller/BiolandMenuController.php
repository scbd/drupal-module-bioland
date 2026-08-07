<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the dedicated "Add Mega Menu component" form.
 *
 * A near-copy of core \Drupal\menu_link_content\Controller\MenuController::addLink():
 * create an unsaved menu_link_content entity with menu_name prefilled from the
 * {menu} route parameter, then hand it to the entity form builder. The single
 * difference is the operation passed to the builder — Component mode instead
 * of the default — which is the whole point of the route.
 *
 * WHY THE OPERATION IS THE ONLY DIFFERENCE. The form class registered for that
 * operation (BiolandComponentMenuLinkForm) adds no behaviour; the
 * bioland.component_menu_form_mode service reads the operation off the form
 * object at alter time and does all the work then. See that service's class
 * docblock for why mode application cannot happen any earlier.
 *
 * SECURITY NOTE — THE OPERATION is not user input. It is the class constant
 * BiolandComponentMenuFormMode::OPERATION, hard-coded at the single call site
 * below. No route default, query parameter, request attribute, or form value
 * can introduce it: the route declares a _controller (not an _entity_form), so
 * nothing in the request reaches the form builder's $operation argument. The
 * dispatcher's trust in getOperation() therefore rests entirely on this file.
 *
 * The claim is scoped to the operation, and to that alone. This controller DOES
 * read one request value — the ?parent= query parameter — and that value is
 * hard-validated by validatedParent() before it can reach storage: it must name
 * an existing menu link plugin, and that plugin must belong to the very {menu}
 * being added to. A parent that fails either check is dropped silently, exactly
 * as if no parent had been requested. The two paths never meet; a request can
 * influence which parent a new link is prefilled with, never which form
 * operation is opened.
 *
 * NON-SCALAR INPUT NEVER REACHES HERE AT ALL. "?parent[]=x" is rejected one
 * layer earlier: Symfony's InputBag::get() throws BadRequestException for any
 * value that is neither scalar nor \Stringable, and HttpKernel turns that into
 * a 400 before this controller is entered. It is not "dropped silently" — there
 * is no request in which this code sees an array.
 *
 * WHY VALIDATE AT ALL, given core reads the same parameter unvalidated
 * (MenuLinkContentForm::form(): $this->entity->getParentId() ?:
 * $this->getRequest()->query->get('parent'))? Because setting a VALIDATED
 * parent here makes getParentId() truthy, so that unvalidated read is never
 * reached on this route. Validating is strictly narrower than the inherited
 * behaviour, never wider.
 *
 * ACCESS mirrors core's entity.menu.add_link_form exactly — the route requires
 * _entity_create_access on menu_link_content and adds no new permission. The
 * ?parent= check is a correctness gate on top of that, not an access decision:
 * a caller who cannot create menu links is already refused by the route.
 * The picker is separately gated on "use menu link attributes" inside the
 * service.
 *
 * @see \Drupal\bioland\Form\BiolandComponentMenuLinkForm
 * @see \Drupal\bioland\Service\BiolandComponentMenuFormMode::applies()
 */
class BiolandMenuController implements ContainerInjectionInterface {

  /**
   * The entity type manager, used to build the unsaved menu link.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The menu link manager, used to validate a requested parent.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface|null
   */
  protected $menuLinkManager;

  /**
   * Constructs the Bioland menu controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface|null $menu_link_manager
   *   The menu link manager. Nullable so the flow degrades safely rather than
   *   fatally on a half-wired container: without it no ?parent= is ever
   *   accepted, which is the same outcome as an invalid one.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, ?MenuLinkManagerInterface $menu_link_manager = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->menuLinkManager = $menu_link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('plugin.manager.menu.link')
    );
  }

  /**
   * Builds the Component-mode add form for a menu.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu the new link belongs to, upcast from the {menu} route
   *   parameter.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request, resolved from the type hint by
   *   argument_resolver.request. Read for ?parent= and nothing else. Nullable
   *   with a default so the controller is callable without one.
   *
   * @return array
   *   The menu_link_content add form, opened with the "component" operation.
   */
  public function addComponentLink(MenuInterface $menu, ?Request $request = NULL) {
    $values = ['menu_name' => $menu->id()];

    // Fed by the "Add Mega Menu Child" row operation on the menu overview
    // screen (BiolandComponentMenuOverview). The key is the entity's own
    // 'parent' base field, so MenuLinkContentForm's parent select opens on it.
    $parent = $this->validatedParent($menu, $request);
    if ($parent !== NULL) {
      $values['parent'] = $parent;
    }

    $menu_link = $this->entityTypeManager
      ->getStorage(BiolandComponentMenuFormMode::ENTITY_TYPE)
      ->create($values);

    return $this->entityFormBuilder->getForm($menu_link, BiolandComponentMenuFormMode::OPERATION);
  }

  /**
   * Returns the requested parent plugin id, or NULL when it is not usable.
   *
   * The whole validation surface for the one request value this controller
   * reads. Deliberately a hard allowlist rather than a sanitiser: the id must
   * name a menu link plugin that actually exists AND that lives in the same
   * menu as the link being created. A parent from another menu would put the
   * new link somewhere the editor did not ask for; a non-existent one would
   * leave a dangling parent reference. Both are dropped silently — the form
   * simply opens with no parent preselected, which is the pre-existing
   * behaviour of the route.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu being added to.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request.
   *
   * @return string|null
   *   The validated plugin id, or NULL.
   */
  protected function validatedParent(MenuInterface $menu, ?Request $request): ?string {
    if ($request === NULL || $this->menuLinkManager === NULL) {
      return NULL;
    }

    $parent = $request->query->get('parent');
    // Defence in depth. Unreachable from a real query string - InputBag::get()
    // has already thrown a 400 on anything non-scalar, and a query string can
    // only yield strings - but a synthetic Request built in code can hand us
    // an int, and hasDefinition() would then be asked for a non-string id.
    if (!is_string($parent) || $parent === '') {
      return NULL;
    }

    if (!$this->menuLinkManager->hasDefinition($parent)) {
      return NULL;
    }

    // FALSE: return NULL for an unknown id rather than throwing. hasDefinition()
    // already covered that, so this is belt and braces on a plugin manager that
    // could disagree with itself.
    $definition = $this->menuLinkManager->getDefinition($parent, FALSE);
    if (!is_array($definition)) {
      return NULL;
    }

    return ($definition['menu_name'] ?? NULL) === $menu->id() ? $parent : NULL;
  }

}
