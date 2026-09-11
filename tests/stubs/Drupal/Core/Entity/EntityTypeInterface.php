<?php

namespace Drupal\Core\Entity;

/**
 * Stub interface for EntityTypeInterface.
 */
interface EntityTypeInterface {

  /**
   * Gets the label.
   *
   * @return string
   *   The entity type label.
   */
  public function getLabel();

  /**
   * Gets an entity key.
   *
   * @param string $key
   *   The key name.
   *
   * @return string|null
   *   The key value.
   */
  public function getKey($key);

  /**
   * Checks if the entity type is translatable.
   *
   * @return bool
   *   TRUE if translatable.
   */
  public function isTranslatable();

  /**
   * Checks if the entity class implements an interface.
   *
   * @param string $interface
   *   The interface name.
   *
   * @return bool
   *   TRUE if it implements the interface.
   */
  public function entityClassImplements($interface);

}
