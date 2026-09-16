<?php

namespace Drupal\Core\Entity;

/**
 * Stub class for EntityForm.
 *
 * Only the two form-id methods are reproduced, verbatim in behaviour from core
 * \Drupal\Core\Entity\EntityForm, because they are what
 * BiolandComponentMenuLinkFormTest pins: registering an entity-form operation
 * must not change the base form id, or menu_link_attributes' and bioland's
 * hook_form_BASE_FORM_ID_alter() implementations would stop firing.
 *
 * Core's real chain is EntityForm -> ContentEntityForm -> MenuLinkContentForm;
 * neither of the two subclasses overrides getFormId() or getBaseFormId(), so
 * collapsing them here changes nothing these tests observe.
 */
class EntityForm {

  /**
   * The entity being edited.
   *
   * @var mixed
   */
  protected $entity;

  /**
   * The name of the current operation.
   *
   * @var string
   */
  protected $operation = 'default';

  /**
   * Sets the operation this form runs under.
   *
   * @param string $operation
   *   The operation name.
   *
   * @return $this
   */
  public function setOperation($operation) {
    if ($operation !== 'default') {
      $this->operation = $operation;
    }
    return $this;
  }

  /**
   * Returns the operation this form runs under.
   *
   * @return string
   *   The operation name.
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * Sets the entity being edited.
   *
   * @param mixed $entity
   *   The entity.
   *
   * @return $this
   */
  public function setEntity($entity) {
    $this->entity = $entity;
    return $this;
  }

  /**
   * Returns the entity being edited.
   *
   * @return mixed
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Returns the concrete form id.
   *
   * Mirrors core: entity type id, suffixed by the bundle when the entity type
   * has a bundle key, then suffixed by the operation when it is not "default".
   *
   * @return string
   *   The form id.
   */
  public function getFormId() {
    $form_id = $this->entity->getEntityTypeId();
    if ($this->entity->getEntityType()->hasKey('bundle')) {
      $form_id = $form_id . '_' . $this->entity->bundle();
    }
    if ($this->operation != 'default') {
      $form_id = $form_id . '_' . $this->operation;
    }
    return $form_id . '_form';
  }

  /**
   * Returns the base form id.
   *
   * Mirrors core: ENTITYTYPE_form, blanked when it would equal the concrete
   * form id (which would make the alter hooks fire twice).
   *
   * @return string
   *   The base form id.
   */
  public function getBaseFormId() {
    $base_form_id = $this->entity->getEntityTypeId() . '_form';
    if ($base_form_id == $this->getFormId()) {
      $base_form_id = '';
    }
    return $base_form_id;
  }

  /**
   * Builds the entity form's own elements.
   *
   * Present so a test can assert the bioland subclass does NOT override it.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function form(array $form, $form_state) {
    return $form;
  }

}
