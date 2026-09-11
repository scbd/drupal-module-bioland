<?php

namespace Drupal\Core\Entity;

/**
 * Stub interface for EntityTypeManagerInterface.
 */
interface EntityTypeManagerInterface {

  /**
   * Gets the entity storage for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  public function getStorage($entity_type_id);

  /**
   * Gets all entity type definitions.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity type definitions.
   */
  public function getDefinitions();

  /**
   * Gets a specific entity type definition.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The entity type definition.
   */
  public function getDefinition($entity_type_id);

}
