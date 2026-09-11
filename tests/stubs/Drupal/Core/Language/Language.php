<?php

namespace Drupal\Core\Language;

/**
 * Stub class for Language.
 */
class Language implements LanguageInterface {

  /**
   * The language code.
   *
   * @var string
   */
  protected $id;

  /**
   * The language name.
   *
   * @var string
   */
  protected $name;

  /**
   * Constructs a new Language.
   *
   * @param string $id
   *   The language code.
   * @param string $name
   *   The language name.
   */
  public function __construct(string $id, string $name = '') {
    $this->id = $id;
    $this->name = $name ?: $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

}
