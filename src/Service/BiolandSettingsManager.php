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
      // GBIF widget with per-country map settings.
      'gbif_widget' => [
        'enable' => $config->get('home_widgets.gbif_widget.enable') !== FALSE,
        'countries' => [],
      ],
      // CHM (Bioland) simple enable toggles.
      'latest_news_widget'            => ['enable' => $config->get('home_widgets.latest_news_widget.enable') !== FALSE],
      'national_targets_widget'       => ['enable' => $config->get('home_widgets.national_targets_widget.enable') !== FALSE],
      'panorama_solutions_widget'     => ['enable' => $config->get('home_widgets.panorama_solutions_widget.enable') !== FALSE],
      'elearning_widget'              => ['enable' => $config->get('home_widgets.elearning_widget.enable') !== FALSE],
      'implementation_widget'         => ['enable' => $config->get('home_widgets.implementation_widget.enable') !== FALSE],
      'technical_cooperation_widget'  => ['enable' => $config->get('home_widgets.technical_cooperation_widget.enable') !== FALSE],
      'latest_discussions_widget'     => ['enable' => $config->get('home_widgets.latest_discussions_widget.enable') !== FALSE],
      'content_statistics_widget'     => ['enable' => $config->get('home_widgets.content_statistics_widget.enable') !== FALSE],
      'geobon_widget'                 => ['enable' => $config->get('home_widgets.geobon_widget.enable') !== FALSE],
      // BSL (Biosafety Clearing-House) simple enable toggles.
      'nbf_widget'                    => ['enable' => $config->get('home_widgets.nbf_widget.enable') !== FALSE],
      'bch_news_widget'               => ['enable' => $config->get('home_widgets.bch_news_widget.enable') !== FALSE],
      'bch_resources_widget'          => ['enable' => $config->get('home_widgets.bch_resources_widget.enable') !== FALSE],
    ];

    // For each configured country, merge GBIF map settings with preset defaults.
    foreach ($countries as $country_code) {
      $country_code = trim(strtolower($country_code));
      $country_code_upper = strtoupper($country_code);
      $defaults = $country_defaults[$country_code_upper] ?? NULL;

      $widget_settings['gbif_widget']['countries'][$country_code] = [
        'zoomLevel' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.zoom_level")
          ?? ($defaults ? $defaults['zoomLevel'] : 7),
        'longitude' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.longitude")
          ?? ($defaults ? $defaults['coordinates']['longitude'] : 0.0),
        'latitude'  => $config->get("home_widgets.gbif_widget.countries.{$country_code}.latitude")
          ?? ($defaults ? $defaults['coordinates']['latitude'] : 0.0),
      ];
    }

    return $widget_settings;
  }
}
