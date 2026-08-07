<?php

namespace Drupal\bioland\Controller;

use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
 * SECURITY NOTE — the operation string is not user input. It is the class
 * constant BiolandComponentMenuFormMode::OPERATION, hard-coded at the single
 * call site below. No route default, query parameter, request attribute, or
 * form value can introduce it: the route declares a _controller (not an
 * _entity_form), so nothing in the request reaches the form builder's
 * $operation argument. The dispatcher's trust in getOperation() therefore
 * rests entirely on this file.
 *
 * ACCESS mirrors core's entity.menu.add_link_form exactly — the route requires
 * _entity_create_access on menu_link_content and adds no new permission. The
 * picker is separately gated on "use menu link attributes" inside the service.
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
   * Constructs the Bioland menu controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Builds the Component-mode add form for a menu.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu the new link belongs to, upcast from the {menu} route
   *   parameter.
   *
   * @return array
   *   The menu_link_content add form, opened with the "component" operation.
   */
  public function addComponentLink(MenuInterface $menu) {
    $menu_link = $this->entityTypeManager
      ->getStorage(BiolandComponentMenuFormMode::ENTITY_TYPE)
      ->create(['menu_name' => $menu->id()]);

    return $this->entityFormBuilder->getForm($menu_link, BiolandComponentMenuFormMode::OPERATION);
  }

}
