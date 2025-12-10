<?php

namespace Drupal\Core\Language;

/**
 * Stub interface for LanguageInterface.
 */
interface LanguageInterface {

  /**
   * Gets the language code.
   *
   * @return string
   *   The language code.
   */
  public function getId();

  /**
   * Gets the language name.
   *
   * @return string
   *   The language name.
   */
  public function getName();

}
