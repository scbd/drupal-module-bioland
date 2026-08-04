<?php

namespace Drupal\bioland\Form;

use Drupal\menu_link_content\Form\MenuLinkContentForm;

/**
 * The menu_link_content entity form for the "component" operation.
 *
 * DELIBERATELY EMPTY. This class exists only so Drupal has something to
 * instantiate for the "component" form operation registered by
 * bioland_entity_type_alter(); the operation string itself is the entire
 * payload. Every visible difference between this form and the regular one is
 * applied later, at alter time, by the bioland.component_menu_form_mode
 * service, whose dispatcher accepts a form precisely because
 * $form_state->getFormObject()->getOperation() === 'component'.
 *
 * DO NOT OVERRIDE form() (or buildForm(), or any other build-phase method) to
 * add the picker, strip the component token, style the form, or register an
 * entity builder. A form class's form() runs BEFORE all hook_form_alter()
 * implementations, and menu_link_attributes' entity builder — registered from
 * its own form alter — rebuilds link.options.attributes wholesale from ITS
 * form values on every save. Anything this class wrote would therefore be
 * silently discarded on save. The ordering guarantee that makes Component mode
 * work (bioland_module_implements_alter() moving bioland's form_alter last, so
 * the service's entity builder lands after the contrib module's) exists only
 * on the alter path. See the class docblock of
 * \Drupal\bioland\Service\BiolandComponentMenuFormMode.
 *
 * Two inherited behaviours are load-bearing and must not be broken:
 * - The base form id stays "menu_link_content_form" (core EntityForm derives
 *   it from the entity type, not the operation), so both
 *   menu_link_attributes_form_menu_link_content_form_alter() and
 *   bioland_form_alter() still fire on this form.
 * - The concrete form id — "menu_link_content_menu_link_content_component_form"
 *   — still starts with "menu_link_content_", so bioland_form_alter()'s prefix
 *   test keeps appending the cache-busting submit handler
 *   bioland_menu_link_form_submit() and keeps hiding menu_parent and the
 *   display settings on translation forms.
 *
 * Both are pinned by BiolandComponentMenuLinkFormTest, which also fails if a
 * later change declares form(), getFormId(), or getBaseFormId() here.
 *
 * @see bioland_entity_type_alter()
 * @see \Drupal\bioland\Controller\BiolandMenuController::addComponentLink()
 * @see \Drupal\bioland\Service\BiolandComponentMenuFormMode
 */
class BiolandComponentMenuLinkForm extends MenuLinkContentForm {

}
