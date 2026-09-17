<?php

namespace Drupal\menu_link_content\Form;

use Drupal\Core\Entity\EntityForm;

/**
 * Stub class for MenuLinkContentForm.
 *
 * Core's MenuLinkContentForm extends ContentEntityForm and only adds link-field
 * handling; it overrides neither getFormId() nor getBaseFormId(). The stub
 * therefore inherits both from the EntityForm stub unchanged, which is exactly
 * the inheritance BiolandComponentMenuLinkForm relies on.
 */
class MenuLinkContentForm extends EntityForm {

}
