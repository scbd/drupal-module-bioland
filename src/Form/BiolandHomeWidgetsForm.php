<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Home Widgets settings for the Bioland module.
 */
class BiolandHomeWidgetsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_home_widgets_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end_home_widgets';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    // Ensure default values are saved to config if not already present
    $this->ensureHomeWidgetDefaults($config);

    $isBsl = (bool) $config->get('is_biosafety_land');

    $form['home_widgets_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Home Page Widget Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];

    if ($isBsl) {
      $form['home_widgets_settings']['bsl_info'] = [
        '#type' => 'markup',
        '#markup' => '<p class="description">' . $this->t('This is a Biosafety Clearing-House site. Only BSL home page sections are shown below.') . '</p>',
      ];

      // BSL: National Biosafety Framework widget
      $form['home_widgets_settings']['nbf_widget'] = [
        '#type' => 'details',
        '#title' => $this->t('National Biosafety Framework Section'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];
      $form['home_widgets_settings']['nbf_widget']['enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable National Biosafety Framework Section'),
        '#default_value' => $config->get('home_widgets.nbf_widget.enable') !== FALSE,
        '#description' => $this->t('Show the National Biosafety Framework content swiper on the BSL home page.'),
      ];

      // BSL: BCH News widget
      $form['home_widgets_settings']['bch_news_widget'] = [
        '#type' => 'details',
        '#title' => $this->t('BCH News Section'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];
      $form['home_widgets_settings']['bch_news_widget']['enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable BCH News Section'),
        '#default_value' => $config->get('home_widgets.bch_news_widget.enable') !== FALSE,
        '#description' => $this->t('Show the BCH news swiper on the BSL home page.'),
      ];

      // BSL: BCH Resources widget
      $form['home_widgets_settings']['bch_resources_widget'] = [
        '#type' => 'details',
        '#title' => $this->t('BCH Resources Section'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];
      $form['home_widgets_settings']['bch_resources_widget']['enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable BCH Resources Section'),
        '#default_value' => $config->get('home_widgets.bch_resources_widget.enable') !== FALSE,
        '#description' => $this->t('Show the BCH resources swiper on the BSL home page.'),
      ];

      return $form;
    }

    // CHM (Bioland) widgets — shown only for non-BSL sites.

    // GBIF Widget section
    $form['home_widgets_settings']['gbif_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('GBIF Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['gbif_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable GBIF Widget'),
      '#default_value' => $config->get('home_widgets.gbif_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the GBIF (Global Biodiversity Information Facility) widget on the home page.'),
    ];

    // Get countries from config
    $countries = $config->get('countries') ?: ['lk'];
    
    // Import country defaults service
    $country_defaults = \Drupal\bioland\Service\BiolandCountryMapDefaults::getDefaults();
    
    // Create a details section for each country
    foreach ($countries as $country_code) {
      $country_code = trim(strtolower($country_code));
      $country_code_upper = strtoupper($country_code);
      
      // Get defaults for this country
      $defaults = $country_defaults[$country_code_upper] ?? NULL;
      
      $form['home_widgets_settings']['gbif_widget']['countries'][$country_code] = [
        '#type' => 'details',
        '#title' => $this->t('Country: @country', ['@country' => $country_code_upper]),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      // If we have preset defaults for this country, show them in the description
      $default_info = '';
      if ($defaults) {
        $default_info = ' ' . $this->t('(Preset default: @zoom)', [
          '@zoom' => $defaults['zoomLevel'],
        ]);
      }

      // Zoom factor
      $form['home_widgets_settings']['gbif_widget']['countries'][$country_code]['zoom_level'] = [
        '#type' => 'number',
        '#title' => $this->t('Zoom Factor'),
        '#default_value' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.zoom_level") 
          ?? ($defaults ? $defaults['zoomLevel'] : 7),
        '#min' => 1,
        '#max' => 255,
        '#step' => 1,
        '#description' => $this->t('Zoom level for the map (1-255).@info', ['@info' => $default_info]),
      ];

      // If we have preset coordinates, show them in descriptions
      $lng_info = '';
      $lat_info = '';
      if ($defaults) {
        $lng_info = ' ' . $this->t('(Preset default: @lng)', [
          '@lng' => number_format($defaults['coordinates']['longitude'], 6),
        ]);
        $lat_info = ' ' . $this->t('(Preset default: @lat)', [
          '@lat' => number_format($defaults['coordinates']['latitude'], 6),
        ]);
      }

      // Center point - Longitude
      $form['home_widgets_settings']['gbif_widget']['countries'][$country_code]['longitude'] = [
        '#type' => 'number',
        '#title' => $this->t('Center Point - Longitude'),
        '#default_value' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.longitude") 
          ?? ($defaults ? $defaults['coordinates']['longitude'] : 0.0),
        '#step' => 'any',
        '#min' => -180,
        '#max' => 180,
        '#description' => $this->t('Longitude coordinate for map center point.@info', ['@info' => $lng_info]),
      ];

      // Center point - Latitude
      $form['home_widgets_settings']['gbif_widget']['countries'][$country_code]['latitude'] = [
        '#type' => 'number',
        '#title' => $this->t('Center Point - Latitude'),
        '#default_value' => $config->get("home_widgets.gbif_widget.countries.{$country_code}.latitude") 
          ?? ($defaults ? $defaults['coordinates']['latitude'] : 0.0),
        '#step' => 'any',
        '#min' => -90,
        '#max' => 90,
        '#description' => $this->t('Latitude coordinate for map center point.@info', ['@info' => $lat_info]),
      ];
    }

    // Latest News and Updates Widget section
    $form['home_widgets_settings']['latest_news_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Latest News and Updates Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['latest_news_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Latest News and Updates Widget'),
      '#default_value' => $config->get('home_widgets.latest_news_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Latest News and Updates widget on the home page.'),
    ];

    // National Targets Widget section
    $form['home_widgets_settings']['national_targets_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('National Targets Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['national_targets_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable National Targets Widget'),
      '#default_value' => $config->get('home_widgets.national_targets_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the National Targets widget on the home page.'),
    ];

    // Panorama Solutions Widget section
    $form['home_widgets_settings']['panorama_solutions_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Panorama Solutions Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['panorama_solutions_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Panorama Solutions Widget'),
      '#default_value' => $config->get('home_widgets.panorama_solutions_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Panorama Solutions widget on the home page.'),
    ];

    // E-Learning Widget section
    $form['home_widgets_settings']['elearning_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('E-Learning Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['elearning_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable E-Learning Widget'),
      '#default_value' => $config->get('home_widgets.elearning_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the E-Learning widget on the home page.'),
    ];

    // Implementation Widget section
    $form['home_widgets_settings']['implementation_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Implementation Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['implementation_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Implementation Widget'),
      '#default_value' => $config->get('home_widgets.implementation_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Implementation widget on the home page.'),
    ];

    // Technical & Scientific Cooperation Widget section
    $form['home_widgets_settings']['technical_cooperation_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Technical & Scientific Cooperation Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['technical_cooperation_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Technical & Scientific Cooperation Widget'),
      '#default_value' => $config->get('home_widgets.technical_cooperation_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Technical & Scientific Cooperation widget on the home page.'),
    ];

    // Latest Discussions Widget section
    $form['home_widgets_settings']['latest_discussions_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Latest Discussions Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['latest_discussions_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Latest Discussions Widget'),
      '#default_value' => $config->get('home_widgets.latest_discussions_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Latest Discussions widget on the home page.'),
    ];

    // Content Statistics Widget section
    $form['home_widgets_settings']['content_statistics_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Statistics Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['content_statistics_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Content Statistics Widget'),
      '#default_value' => $config->get('home_widgets.content_statistics_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the Content Statistics widget on the home page.'),
    ];

    // GEOBON Widget section
    $form['home_widgets_settings']['geobon_widget'] = [
      '#type' => 'details',
      '#title' => $this->t('GEOBON Widget'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    $form['home_widgets_settings']['geobon_widget']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable GEOBON Widget'),
      '#default_value' => $config->get('home_widgets.geobon_widget.enable') !== FALSE,
      '#description' => $this->t('Enable the GEOBON widget on the home page.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();
    $home_widgets_values = $values['home_widgets_settings'] ?? [];
    $isBsl = (bool) $config->get('is_biosafety_land');

    if ($isBsl) {
      // Save BSL-specific widget settings only.
      $config->set('home_widgets.nbf_widget.enable', (bool) ($home_widgets_values['nbf_widget']['enable'] ?? TRUE));
      $config->set('home_widgets.bch_news_widget.enable', (bool) ($home_widgets_values['bch_news_widget']['enable'] ?? TRUE));
      $config->set('home_widgets.bch_resources_widget.enable', (bool) ($home_widgets_values['bch_resources_widget']['enable'] ?? TRUE));
      return;
    }

    // CHM (Bioland) widget settings.
    $gbif_widget = $home_widgets_values['gbif_widget'] ?? [];
    $config->set('home_widgets.gbif_widget.enable', (bool) ($gbif_widget['enable'] ?? TRUE));
    $countries_data = $gbif_widget['countries'] ?? [];
    foreach ($countries_data as $country_code => $country_settings) {
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.zoom_level", (int) ($country_settings['zoom_level'] ?? 7));
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.longitude", (float) ($country_settings['longitude'] ?? 0.0));
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.latitude", (float) ($country_settings['latitude'] ?? 0.0));
    }

    $config->set('home_widgets.latest_news_widget.enable', (bool) ($home_widgets_values['latest_news_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.national_targets_widget.enable', (bool) ($home_widgets_values['national_targets_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.panorama_solutions_widget.enable', (bool) ($home_widgets_values['panorama_solutions_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.elearning_widget.enable', (bool) ($home_widgets_values['elearning_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.implementation_widget.enable', (bool) ($home_widgets_values['implementation_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.technical_cooperation_widget.enable', (bool) ($home_widgets_values['technical_cooperation_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.latest_discussions_widget.enable', (bool) ($home_widgets_values['latest_discussions_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.content_statistics_widget.enable', (bool) ($home_widgets_values['content_statistics_widget']['enable'] ?? TRUE));
    $config->set('home_widgets.geobon_widget.enable', (bool) ($home_widgets_values['geobon_widget']['enable'] ?? TRUE));
  }

  /**
   * Ensure home widget defaults are saved to config.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object.
   */
  protected function ensureHomeWidgetDefaults($config) {
    $widgets = [
      'gbif_widget',
      'latest_news_widget',
      'national_targets_widget',
      'panorama_solutions_widget',
      'elearning_widget',
      'implementation_widget',
      'technical_cooperation_widget',
      'latest_discussions_widget',
      'content_statistics_widget',
      'geobon_widget',
      'nbf_widget',
      'bch_news_widget',
      'bch_resources_widget',
    ];

    $needs_save = FALSE;
    foreach ($widgets as $widget) {
      // Check if the enable key exists, if not set it to TRUE (default)
      if ($config->get("home_widgets.{$widget}.enable") === NULL) {
        $config->set("home_widgets.{$widget}.enable", TRUE);
        $needs_save = TRUE;
      }
    }

    if ($needs_save) {
      $config->save();
    }
  }

}
