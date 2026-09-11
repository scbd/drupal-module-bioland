<?php

namespace Drupal\Core\Entity;

/**
 * Stub interface for EntityStorageInterface.
 */
interface EntityStorageInterface {

  /**
   * Loads an entity by ID.
   *
   * @param mixed $id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity or NULL.
   */
  public function load($id);

  /**
   * Gets a query for the entity type.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query.
   */
  public function getQuery();

}
