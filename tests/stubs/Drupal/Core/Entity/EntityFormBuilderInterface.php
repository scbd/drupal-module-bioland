<?php

namespace Drupal\Core\Entity;

/**
 * Stub interface for EntityFormBuilderInterface.
 */
interface EntityFormBuilderInterface {

  /**
   * Gets the built and processed entity form for the given entity.
   *
   * @param mixed $entity
   *   The entity to be created or edited.
   * @param string $operation
   *   The operation identifying the form variation to be returned.
   * @param array $form_state_additions
   *   Additional form state values.
   *
   * @return array
   *   The processed form for the given entity and operation.
   */
  public function getForm($entity, $operation = 'default', array $form_state_additions = []);

}
