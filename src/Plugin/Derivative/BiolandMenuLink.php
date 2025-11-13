<?php

namespace Drupal\bioland\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic menu links for Bioland.
 */
class BiolandMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new BiolandMenuLink.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $config = $this->configFactory->get('bioland.settings');
    $is_biosafety_land = $config->get('is_biosafety_land');
    $branding = $is_biosafety_land ? t('Biosafety Land') : t('Bioland');

    $this->derivatives['bioland.settings'] = $base_plugin_definition;
    $this->derivatives['bioland.settings']['title'] = $branding;
    $this->derivatives['bioland.settings']['description'] = t('Configure @branding settings including locales, countries, and region.', ['@branding' => $branding]);

    return $this->derivatives;
  }

}
