<?php

namespace Drupal\Core\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Stub class for ConfigFormBase.
 */
abstract class ConfigFormBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  abstract protected function getEditableConfigNames();

  /**
   * Gets the config.
   *
   * @param string $name
   *   The config name.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  protected function config($name) {
    if ($this->configFactory) {
      return $this->configFactory->get($name);
    }
    return NULL;
  }

  /**
   * Gets the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface|null
   *   The config factory.
   */
  protected function configFactory() {
    return $this->configFactory ?? NULL;
  }

}
