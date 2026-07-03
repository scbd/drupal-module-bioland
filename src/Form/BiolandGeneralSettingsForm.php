<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * General settings section form.
 */
class BiolandGeneralSettingsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'general';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
      $site_config = $this->config('system.site');
      $languages = $this->getFilteredLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      $has_multiple_languages = count($languages) > 1;

      $form['general'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('General Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Site name in collapsible box (same style as Field Visibility)
      $form['general']['site_name_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Site Name'),
        '#open' => FALSE,
      ];

      $form['general']['site_name_section']['site_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Site name'),
        '#default_value' => $site_config->get('name'),
        '#required' => TRUE,
      ];

      $form['general']['site_slogan'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Slogan'),
        '#default_value' => $site_config->get('slogan'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
        '#description' => $this->t('How this is used depends on your site\'s theme.'),
        '#access' => FALSE, // Hidden - change to TRUE to re-enable
      ];

      // Translation fields (refactored to reduce duplication)
      if ($has_multiple_languages) {
        $translatable_fields = [
          'site_name' => [
            'title' => $this->t('Translate Site Name'),
            'config_key' => 'name',
          ],
          'site_slogan' => [
            'title' => $this->t('Translate Slogan'),
            'config_key' => 'slogan',
          ],
        ];

        // Cache config overrides (load once per language)
        $config_overrides = [];
        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $config_overrides[$langcode] = $this->languageManager->getLanguageConfigOverride($langcode, 'system.site');
        }

        foreach ($translatable_fields as $field_name => $translation_info) {
          // Put site_name translations inside site_name_section
          $parent_key = $field_name === 'site_name' ? 'site_name_section' : 'general';
          $form['general'][$parent_key]["{$field_name}_translations"] = [
            '#type' => 'details',
            '#title' => $translation_info['title'],
            '#open' => FALSE,
            '#tree' => TRUE,
            '#access' => $field_name !== 'site_slogan', // Hide slogan translations - change condition to TRUE to re-enable
          ];

          foreach ($config_overrides as $langcode => $config_override) {
            // Use text_format for slogan, textfield for others
            $parent_key = $field_name === 'site_name' ? 'site_name_section' : 'general';
            if ($field_name === 'site_slogan') {
              $form['general'][$parent_key]["{$field_name}_translations"][$langcode] = [
                '#type' => 'text_format',
                '#title' => $languages[$langcode]->getName(),
                '#default_value' => $config_override->get($translation_info['config_key']),
                '#format' => 'full_html',
                '#allowed_formats' => ['full_html'],
              ];
            }
            else {
              $form['general'][$parent_key]["{$field_name}_translations"][$langcode] = [
                '#type' => 'textfield',
                '#title' => $languages[$langcode]->getName(),
                '#default_value' => $config_override->get($translation_info['config_key']),
              ];
            }
          }
        }
      }

      // Timezone configuration
      $system_date_config = $this->config('system.date');
      $form['general']['timezone'] = [
        '#type' => 'details',
        '#title' => $this->t('Time zones'),
        '#open' => FALSE,
      ];

      $form['general']['timezone']['date_default_timezone'] = [
        '#type' => 'select',
        '#title' => $this->t('Default time zone'),
        '#default_value' => $system_date_config->get('timezone.default') ?: date_default_timezone_get(),
        '#options' => $this->getTimezoneOptions(),
      ];

      $form['general']['region'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Region'),
        '#description' => $this->t('Geographic region (auto-populated from DMSM API).'),
        '#default_value' => $config->get('region') ?: '',
        '#required' => FALSE,
        '#access' => FALSE, // Hidden - automatically populated from DMSM API
      ];

      $form['general']['continent'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Continent'),
        '#description' => $this->t('Continent (auto-populated from DMSM API).'),
        '#default_value' => $config->get('continent') ?: '',
        '#required' => FALSE,
        '#access' => FALSE, // Hidden - automatically populated from DMSM API
      ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

      // Build the list of translatable language codes (all configured
      // languages except the default one) so the mirror is driven by the
      // site's real language set, not a hardcoded list.
      $translation_langcodes = [];
      foreach ($this->languageManager->getLanguages() as $langcode => $language) {
        if ($langcode !== $default_langcode) {
          $translation_langcodes[] = $langcode;
        }
      }

      // Normalise the raw form values into the exact set of config writes.
      // Kept as a pure, unit-testable mapping (see extractGeneralSettings()).
      $mapped = self::extractGeneralSettings($values, $translation_langcodes);

      // Mirror site name/slogan back to system.site (write only on change).
      $site_config = $this->configFactory()->getEditable('system.site');
      $site_config_changed = FALSE;
      foreach ($mapped['site'] as $config_key => $new_value) {
        if ($site_config->get($config_key) !== $new_value) {
          $site_config->set($config_key, $new_value);
          $site_config_changed = TRUE;
        }
      }
      if ($site_config_changed) {
        $site_config->save();
      }

      // Mirror per-language name/slogan to the system.site language overrides.
      // An emptied translation removes the override key (core behaviour),
      // rather than persisting an empty string.
      foreach ($mapped['overrides'] as $langcode => $override_values) {
        $config_override = $this->languageManager->getLanguageConfigOverride($langcode, 'system.site');
        $override_changed = FALSE;
        foreach ($override_values as $config_key => $new_value) {
          if ($new_value === '') {
            if ($config_override->get($config_key) !== NULL) {
              $config_override->clear($config_key);
              $override_changed = TRUE;
            }
          }
          elseif ($config_override->get($config_key) !== $new_value) {
            $config_override->set($config_key, $new_value);
            $override_changed = TRUE;
          }
        }
        if ($override_changed) {
          // Delete the override entirely once it holds no data, so a cleared
          // translation does not leave an empty override behind.
          if (empty($config_override->get())) {
            $config_override->delete();
          }
          else {
            $config_override->save();
          }
        }
      }

      // Mirror the default time zone back to system.date.
      $date_config = $this->configFactory()->getEditable('system.date');
      $date_changed = FALSE;
      if ($date_config->get('timezone.default') !== $mapped['timezone']) {
        $date_config->set('timezone.default', $mapped['timezone']);
        $date_changed = TRUE;
      }
      // Keep a single site-wide time zone: always ensure per-user selection is off.
      if ($date_config->get('timezone.user.configurable') !== FALSE) {
        $date_config->set('timezone.user.configurable', FALSE);
        $date_changed = TRUE;
      }
      if ($date_changed) {
        $date_config->save();
      }

      $config
        ->set('region', $values['region'])
        ->set('continent', $values['continent']);
  }

  /**
   * Maps raw General-tab form values to the config writes they mirror.
   *
   * Pure function (no Drupal services) so the value-path handling can be unit
   * tested directly. It resolves the exact submitted-value locations for the
   * General section and normalises text_format wrappers.
   *
   * The General section does NOT set #tree on the "general" fieldset or the
   * "site_name_section" details, so Form API flattens their descendants to the
   * top level of $values keyed by the leaf element key:
   *   - site name        -> $values['site_name']
   *   - slogan           -> $values['site_slogan'] (text_format array)
   *   - name translations   -> $values['site_name_translations'][$langcode]
   *   - slogan translations -> $values['site_slogan_translations'][$langcode]
   *   - time zone        -> $values['date_default_timezone']
   * (A nested 'site_name_section' path is still accepted defensively in case
   * #tree is added to that container later.)
   *
   * @param array $values
   *   The raw $form_state->getValues() array.
   * @param string[] $translation_langcodes
   *   Non-default language codes to build per-language overrides for.
   *
   * @return array
   *   Normalised writes: [
   *     'site'      => ['name' => string, 'slogan' => string],
   *     'overrides' => [langcode => ['name' => string, 'slogan' => string]],
   *     'timezone'  => string,
   *   ]. Override values are '' when the field was emptied (signal to remove).
   */
  public static function extractGeneralSettings(array $values, array $translation_langcodes) {
    // text_format elements submit as ['value' => ..., 'format' => ...].
    $text = static function ($field) {
      if (is_array($field)) {
        return (string) ($field['value'] ?? '');
      }
      return (string) ($field ?? '');
    };

    $site_name = $values['site_name_section']['site_name'] ?? $values['site_name'] ?? '';

    $mapped = [
      'site' => [
        'name' => (string) $site_name,
      ],
      'overrides' => [],
      'timezone' => (string) ($values['date_default_timezone'] ?? ''),
    ];

    // The slogan field can be access-hidden; only mirror it when it was part
    // of the submission, so a hidden field never wipes existing config.
    $slogan_present = array_key_exists('site_slogan', $values);
    if ($slogan_present) {
      $mapped['site']['slogan'] = $text($values['site_slogan']);
    }

    foreach ($translation_langcodes as $langcode) {
      $name_value = $values['site_name_translations'][$langcode]
        ?? $values['site_name_section']['site_name_translations'][$langcode]
        ?? '';

      $override = ['name' => $text($name_value)];

      $slogan_trans_present = isset($values['site_slogan_translations'])
        && array_key_exists($langcode, $values['site_slogan_translations']);
      if ($slogan_trans_present) {
        $override['slogan'] = $text($values['site_slogan_translations'][$langcode]);
      }

      $mapped['overrides'][$langcode] = $override;
    }

    return $mapped;
  }

  /**
   * Get timezone options grouped by region.
   *
   * @return array
   *   Array of timezone options grouped by region.
   */
  protected function getTimezoneOptions() {
    $zones = \DateTimeZone::listIdentifiers();
    $grouped_zones = [];

    foreach ($zones as $zone) {
      // Split timezone into region and city
      $parts = explode('/', $zone, 2);
      if (count($parts) === 2) {
        $region = str_replace('_', ' ', $parts[0]);
        $city = str_replace('_', ' ', $parts[1]);
        $grouped_zones[$region][$zone] = $city . ' (' . $zone . ')';
      }
      else {
        // Handle special cases like UTC
        $grouped_zones['Other'][$zone] = $zone;
      }
    }

    return $grouped_zones;
  }

  /**
   * Get available region options.
   *
   * @return array
   *   Array of region options.
   */
  protected function getRegionOptions() {
    return [
      'north_america' => $this->t('North America'),
      'south_america' => $this->t('South America'),
      'europe' => $this->t('Europe'),
      'asia' => $this->t('Asia'),
      'africa' => $this->t('Africa'),
      'oceania' => $this->t('Oceania'),
    ];
  }

  /**
   * Get available locale options.
   *
   * @return array
   *   Array of locale options.
   */
  protected function getLocaleOptions() {
    return [
      'en' => $this->t('English'),
      'es' => $this->t('Spanish'),
      'fr' => $this->t('French'),
      'de' => $this->t('German'),
      'it' => $this->t('Italian'),
      'pt' => $this->t('Portuguese'),
      'nl' => $this->t('Dutch'),
      'zh' => $this->t('Chinese'),
      'ja' => $this->t('Japanese'),
      'ko' => $this->t('Korean'),
      'ar' => $this->t('Arabic'),
      'ru' => $this->t('Russian'),
    ];
  }

}
