<?php

namespace Drupal\Core\Entity;

/**
 * Stub interface for ContentEntityInterface.
 */
interface ContentEntityInterface {

  /**
   * Gets the entity type ID.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId();

  /**
   * Gets the entity ID.
   *
   * @return mixed
   *   The entity ID.
   */
  public function id();

  /**
   * Checks if the entity is translatable.
   *
   * @return bool
   *   TRUE if translatable.
   */
  public function isTranslatable();

  /**
   * Gets the untranslated entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The untranslated entity.
   */
  public function getUntranslated();

  /**
   * Checks if a translation exists.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return bool
   *   TRUE if a translation exists.
   */
  public function hasTranslation($langcode);

  /**
   * Gets a translation.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translation.
   */
  public function getTranslation($langcode);

  /**
   * Adds a translation.
   *
   * @param string $langcode
   *   The language code.
   * @param array $values
   *   The translation values.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translation.
   */
  public function addTranslation($langcode, array $values = []);

  /**
   * Gets the language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The language.
   */
  public function language();

  /**
   * Checks if the entity has a field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if the entity has the field.
   */
  public function hasField($field_name);

  /**
   * Gets a field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return mixed
   *   The field.
   */
  public function get($field_name);

  /**
   * Sets a field value.
   *
   * @param string $field_name
   *   The field name.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function set($field_name, $value);

  /**
   * Gets the field definitions.
   *
   * @return array
   *   The field definitions.
   */
  public function getFieldDefinitions();

  /**
   * Gets the entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType();

  /**
   * Saves the entity.
   */
  public function save();

}
