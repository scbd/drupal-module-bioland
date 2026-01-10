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

  /**
   * Get home widget settings with country map defaults applied.
   *
   * @return array
   *   Home widget settings with country-specific defaults.
   */
  public function getHomeWidgetSettings() {
    $config = $this->getConfig();
    $countries = $config->get('countries') ?: [];
    $country_defaults = BiolandCountryMapDefaults::getDefaults();
    
    $widget_settings = [
      'gbif_widget' => [
        'enable' => $config->get('home_widgets.gbif_widget.enable') !== FALSE,
        'countries' => [],
      ],
    ];
    
    // For each configured country, merge with preset defaults
    foreach ($countries as $country_code) {
      $country_code = trim(strtolower($country_code));
      $country_code_upper = strtoupper($country_code);
      
      // Get preset defaults for this country
      $defaults = $country_defaults[$country_code_upper] ?? NULL;
      
      // Get saved values or use preset defaults
      $widget_settings['gbif_widget']['countries'][$country_code] = [
        'zoomLevel' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.zoom_level") 
          ?? ($defaults ? $defaults['zoomLevel'] : 7),
        'longitude' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.longitude") 
          ?? ($defaults ? $defaults['coordinates']['longitude'] : 0.0),
        'latitude' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.latitude") 
          ?? ($defaults ? $defaults['coordinates']['latitude'] : 0.0),
      ];
    }
    
    return $widget_settings;
  }
}
