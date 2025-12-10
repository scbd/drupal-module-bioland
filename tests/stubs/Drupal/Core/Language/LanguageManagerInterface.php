<?php

namespace Drupal\Core\Language;

/**
 * Stub interface for LanguageManagerInterface.
 */
interface LanguageManagerInterface {

  /**
   * Gets all available languages.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   An array of language objects.
   */
  public function getLanguages();

  /**
   * Gets a specific language.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The language object or NULL.
   */
  public function getLanguage($langcode);

  /**
   * Gets the default language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The default language.
   */
  public function getDefaultLanguage();

  /**
   * Gets the language config override for a specific language.
   *
   * @param string $langcode
   *   The language code.
   * @param string $name
   *   The configuration name.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The language config override.
   */
  public function getLanguageConfigOverride($langcode, $name);

}
