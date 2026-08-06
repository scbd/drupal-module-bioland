<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\bioland\BiolandHomeWidgetRegistry;

/**
 * Configure Home Widgets settings for the Bioland module.
 */
class BiolandHomeWidgetsForm extends BiolandSettingsFormBase {

  /**
   * The BSL (Biosafety Clearing-House) home page widgets.
   *
   * Each supports both an enable flag and a content type selection.
   *
   * The vocabulary itself lives in BiolandHomeWidgetRegistry; this constant is
   * only an alias so existing call sites keep working.
   */
  protected const BSL_WIDGETS = BiolandHomeWidgetRegistry::BSL_WIDGET_KEYS;

  /**
   * Default content type term IDs per BSL widget.
   *
   * These mirror the lists the front end hard-coded before the selection was
   * configurable, so an unconfigured site keeps its existing behaviour.
   */
  protected const BSL_WIDGET_DEFAULT_CONTENT_TYPES = [
    'nbf_widget' => [56, 44, 5, 45, 46, 47],
    'bch_news_widget' => [2, 3, 49],
    'bch_resources_widget' => [15, 48, 43, 16, 6, 12],
  ];

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

      // Published content types from the 'tags' vocabulary — the same option
      // source the mega menu content type selects use.
      $content_type_options = $this->getPublishedContentTypeOptions();

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
      $form['home_widgets_settings']['nbf_widget']['content_types'] = $this->buildContentTypeSelect(
        $content_type_options,
        $this->getWidgetContentTypes($config, 'nbf_widget'),
        $this->t('Select the content types shown in the National Biosafety Framework swiper. Leave empty to use the defaults. This section shows site content only — nothing is pulled from the BCH central registry.')
      );

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
      $form['home_widgets_settings']['bch_news_widget']['content_types'] = $this->buildContentTypeSelect(
        $content_type_options,
        $this->getWidgetContentTypes($config, 'bch_news_widget'),
        $this->t('Select the site content types shown in this section and searched by its "View more" link. Leave empty to use the defaults. In addition to the selected content types, this section also pulls these record types from the BCH central registry: @registry.', [
          '@registry' => 'news, notification, statement, meeting, pressRelease',
        ])
      );

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
      $form['home_widgets_settings']['bch_resources_widget']['content_types'] = $this->buildContentTypeSelect(
        $content_type_options,
        $this->getWidgetContentTypes($config, 'bch_resources_widget'),
        $this->t('Select the site content types shown in this section and searched by its "View more" link. Leave empty to use the defaults. In addition to the selected content types, this section also pulls these record types from the BCH central registry: @registry.', [
          '@registry' => 'capacityBuildingInitiative, dnaSequence, modifiedOrganism, laboratoryDetection, resource, organism',
        ])
      );

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
      foreach (self::BSL_WIDGETS as $widget) {
        $widget_values = $home_widgets_values[$widget] ?? [];
        $config->set("home_widgets.{$widget}.enable", (bool) ($widget_values['enable'] ?? TRUE));
        $config->set("home_widgets.{$widget}.content_types", $this->normalizeContentTypes($widget_values['content_types'] ?? []));
      }
      return;
    }

    // CHM (Bioland) widget settings. Every CHM widget carries an enable flag;
    // the registry owns which keys those are.
    foreach (BiolandHomeWidgetRegistry::chmKeys() as $widget) {
      $config->set("home_widgets.{$widget}.enable", (bool) ($home_widgets_values[$widget]['enable'] ?? TRUE));
    }

    // The GBIF widget additionally carries per-country map settings.
    $countries_data = $home_widgets_values['gbif_widget']['countries'] ?? [];
    foreach ($countries_data as $country_code => $country_settings) {
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.zoom_level", (int) ($country_settings['zoom_level'] ?? 7));
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.longitude", (float) ($country_settings['longitude'] ?? 0.0));
      $config->set("home_widgets.gbif_widget.countries.{$country_code}.latitude", (float) ($country_settings['latitude'] ?? 0.0));
    }
  }

  /**
   * Ensure home widget defaults are saved to config.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object.
   */
  protected function ensureHomeWidgetDefaults($config) {
    $widgets = BiolandHomeWidgetRegistry::allKeys();

    $needs_save = FALSE;
    foreach ($widgets as $widget) {
      // Check if the enable key exists, if not set it to TRUE (default)
      if ($config->get("home_widgets.{$widget}.enable") === NULL) {
        $config->set("home_widgets.{$widget}.enable", TRUE);
        $needs_save = TRUE;
      }
    }

    // Seed the BSL content type selections with the front end's previous
    // hard-coded lists so behaviour is unchanged until an editor changes them.
    foreach (self::BSL_WIDGET_DEFAULT_CONTENT_TYPES as $widget => $defaults) {
      if ($config->get("home_widgets.{$widget}.content_types") === NULL) {
        $config->set("home_widgets.{$widget}.content_types", $defaults);
        $needs_save = TRUE;
      }
    }

    if ($needs_save) {
      $config->save();
    }
  }

  /**
   * Builds a multi-select of published content types for a widget.
   *
   * @param array $options
   *   Content type options keyed by term ID.
   * @param array $default_value
   *   The currently selected term IDs.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $description
   *   The element description.
   *
   * @return array
   *   The form element.
   */
  protected function buildContentTypeSelect(array $options, array $default_value, $description): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Content types'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $default_value,
      '#description' => $description,
      // Roughly a screenful without pushing the rest of the form off-page.
      '#size' => min(max(count($options), 5), 12),
    ];
  }

  /**
   * Gets the configured content type term IDs for a BSL widget.
   *
   * Falls back to the widget's defaults when nothing has been configured yet.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config object.
   * @param string $widget
   *   The widget key.
   *
   * @return int[]
   *   The selected term IDs.
   */
  protected function getWidgetContentTypes($config, string $widget): array {
    $configured = $config->get("home_widgets.{$widget}.content_types");

    if ($configured === NULL) {
      return self::BSL_WIDGET_DEFAULT_CONTENT_TYPES[$widget] ?? [];
    }

    return $this->normalizeContentTypes($configured);
  }

  /**
   * Normalizes a submitted content type selection to a list of term IDs.
   *
   * Drupal's multi-select returns values keyed by option key, and unselected
   * options can arrive as 0/''. Both are stripped here so config only ever
   * holds a clean, re-indexed list of positive integers.
   *
   * @param mixed $values
   *   The raw submitted or stored value.
   *
   * @return int[]
   *   The normalized term IDs.
   */
  protected function normalizeContentTypes($values): array {
    if (!is_array($values)) {
      return [];
    }

    $tids = array_map('intval', array_values($values));
    $tids = array_filter($tids, static fn(int $tid): bool => $tid > 0);

    return array_values(array_unique($tids));
  }

}
