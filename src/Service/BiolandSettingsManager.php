<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides access to Drupal Module Bioland settings.
 */
class BiolandSettingsManager {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Get the Bioland settings config object.
   */
  public function getConfig() {
    return $this->configFactory->get('bioland.settings');
  }

  /**
   * Get a specific Bioland setting with optional default.
   */
  public function get($key, $default = NULL) {
    $config = $this->getConfig();
    $value = $config->get($key);
    return $value === NULL ? $default : $value;
  }
}
