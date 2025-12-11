<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Drupal Module Bioland settings.
 */
class BiolandSettingsForm extends ConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation batch service.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationBatchService
   */
  protected $translationBatchService;

  /**
  * Constructs a new Drupal Module Bioland settings form.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bioland\Service\BiolandTranslationBatchService $translation_batch_service
   *   The translation batch service.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, BiolandTranslationBatchService $translation_batch_service) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->translationBatchService = $translation_batch_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('bioland.translation_batch')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bioland.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_form';
  }

  /**
   * Gets the branding name based on the is_biosafety_land setting.
   *
   * @return string
   *   Returns 'Biosafety Land' or 'Bioland' based on configuration.
   */
  protected function getBrandingName() {
    $config = $this->config('bioland.settings');
    return $config->get('is_biosafety_land') ? $this->t('Biosafety Land') : $this->t('Bioland');
  }

  /**
   * Gets the page title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getTitle() {
    $branding = $this->getBrandingName();
    return $this->t('@branding Settings', ['@branding' => $branding]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $section = 'general') {
    $config = $this->config('bioland.settings');

    // Store the section in the form state for submit handler
    $form_state->set('bioland_section', $section);

    if ($section === 'general') {
      $site_config = $this->config('system.site');
      $languages = $this->languageManager->getLanguages();
      // Filter out Lolspeak language.
      $languages = array_filter($languages, function($language) {
        return $language->getId() !== 'en-x-lolspeak';
      });
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      $has_multiple_languages = count($languages) > 1;

      $form['general'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('General Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['general']['site_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Site name'),
        '#default_value' => $site_config->get('name'),
        '#required' => TRUE,
      ];

      $form['general']['site_slogan'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Slogan'),
        '#default_value' => $site_config->get('slogan'),
        '#description' => $this->t('How this is used depends on your site\'s theme.'),
      ];

      $form['general']['site_mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#default_value' => $site_config->get('mail'),
        '#description' => $this->t("The <em>From</em> address in automated e-mails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this e-mail being flagged as spam.)"),
        '#required' => TRUE,
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
          $form['general']["{$field_name}_translations"] = [
            '#type' => 'details',
            '#title' => $translation_info['title'],
            '#open' => FALSE,
            '#tree' => TRUE,
          ];

          foreach ($config_overrides as $langcode => $config_override) {
            $form['general']["{$field_name}_translations"][$langcode] = [
              '#type' => 'textfield',
              '#title' => $languages[$langcode]->getName(),
              '#default_value' => $config_override->get($translation_info['config_key']),
            ];
          }
        }
      }

      $form['general']['countries'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Countries'),
        '#description' => $this->t('Enter one country code per line.'),
        '#default_value' => implode("\n", (array) ($config->get('countries') ?: ['lk'])),
        '#required' => TRUE,
      ];

      $form['general']['region'] = [
        '#type' => 'select',
        '#title' => $this->t('Region'),
        '#description' => $this->t('Select the geographical region.'),
        '#options' => $this->getRegionOptions(),
        '#default_value' => $config->get('region') ?: 'north_america',
        '#required' => TRUE,
      ];

      $form['general']['is_biosafety_land'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Is Biosafety Land?'),
        '#description' => $this->t('Indicates whether this is a Biosafety Land instance.'),
        '#default_value' => $config->get('is_biosafety_land') ?: FALSE,
      ];
    }

    if ($section === 'fields') {
      $form['field_behavior'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Behavior Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Field Visibility functionality - wrapped in fieldset
      $form['field_behavior']['field_visibility'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Visibility Control'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['field_behavior']['field_visibility']['enable_field_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Visibility Control'),
        '#description' => $this->t('Show/hide fields based on content type selection. Configure which fields are visible for each content type below.'),
        '#default_value' => $config->get('enable_field_visibility') !== FALSE,
        '#suffix' => '<a href="#" class="bioland-toggle-visibility-settings" data-target="field-visibility-settings">Show more</a>',
      ];

      // Container for all field visibility settings
      $form['field_behavior']['field_visibility']['settings_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bioland-field-visibility-settings', 'bioland-collapsible-hidden']],
      ];

      // Define content types with their names
      $content_types = [
        2 => $this->t('News (2)'),
        3 => $this->t('Meeting or Event (3)'),
        5 => $this->t('Project (5)'),
        12 => $this->t('Document (12)'),
        13 => $this->t('Related Website (13)'),
        15 => $this->t('Other Resource (15)'),
        16 => $this->t('Image or Video (16)'),
        43 => $this->t('FAQ (43)'),
        44 => $this->t('National Information (44)'),
        45 => $this->t('Status of LMOs (45)'),
        46 => $this->t('Field Trial (46)'),
        47 => $this->t('National Mainstreaming Strategy (47)'),
        48 => $this->t('Capacity-Building (48)'),
        49 => $this->t('Announcement (49)'),
        50 => $this->t('Contact (50)'),
      ];

      // URL Field visibility settings
      $form['field_behavior']['field_visibility']['settings_container']['url_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('URL Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_behavior']['field_visibility']['settings_container']['url_field']['url_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show URL field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.url_content_types') ?: [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50],
      ];

      // Published Field visibility settings
      $form['field_behavior']['field_visibility']['settings_container']['published_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Published Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_behavior']['field_visibility']['settings_container']['published_field']['published_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Published field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.published_content_types') ?: [3, 5, 12],
      ];

      // Date Range Field visibility settings
      $form['field_behavior']['field_visibility']['settings_container']['date_range_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Date Range Field (Start & End Date)'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_behavior']['field_visibility']['settings_container']['date_range_field']['date_range_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Date Range fields (Start & End Date) for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.date_range_content_types') ?: [2, 3, 13],
      ];

      // Additional Fields functionality - wrapped in fieldset
      $form['field_behavior']['additional_fields'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Fields Control'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['field_behavior']['additional_fields']['enable_additional_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Additional Fields'),
        '#description' => $this->t('Add content-type specific additional fields (event statuses, project statuses, etc.) based on thesaurus content type.'),
        '#default_value' => $config->get('enable_additional_fields') !== FALSE,
        '#suffix' => '<a href="#" class="bioland-toggle-additional-fields-settings" data-target="additional-fields-settings">Show more</a>',
      ];

      // Container for additional fields information
      $form['field_behavior']['additional_fields']['settings_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bioland-additional-fields-settings', 'bioland-collapsible-hidden']],
      ];

      $form['field_behavior']['additional_fields']['settings_container']['info'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Fields Mapping'),
        '#description' => $this->t('The following content types will have these additional fields available:'),
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_behavior']['additional_fields']['settings_container']['info']['mapping'] = [
        '#markup' => '
          <div class="bioland-additional-fields-mapping">
            <ul>
              <li><strong>' . $this->t('Meeting or Event (3)') . ':</strong> ' . $this->t('Event Statuses') . '</li>
              <li><strong>' . $this->t('Project (5)') . ':</strong> ' . $this->t('Project Statuses, Geographic Scopes') . '</li>
              <li><strong>' . $this->t('Ministry (8)') . ':</strong> ' . $this->t('Organization Types, Government Types') . '</li>
              <li><strong>' . $this->t('Ecosystem (9)') . ':</strong> ' . $this->t('Ecosystem Types') . '</li>
              <li><strong>' . $this->t('Document (12)') . ':</strong> ' . $this->t('Document Types') . '</li>
            </ul>
            <p><em>' . $this->t('These fields are dynamically added based on the selected content type and are powered by a Vue.js component.') . '</em></p>
          </div>
        ',
      ];

      // Auto Summary functionality
      $form['field_behavior']['enable_auto_summary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Auto Summary'),
        '#description' => $this->t('Automatically generate summary from body content as user types.'),
        '#default_value' => $config->get('enable_auto_summary') !== FALSE,
      ];

      // Field Help Comments functionality
      $form['field_behavior']['enable_help_comments'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Help Comments'),
        '#description' => $this->t('Display contextual help comments for fields based on content type.'),
        '#default_value' => $config->get('enable_help_comments') !== FALSE,
      ];

      $form['field_behavior']['field_visibility_rules'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Field Visibility Rules (Advanced)'),
        '#description' => $this->t('Define custom field visibility rules in JSON format. Leave empty to use the settings above.'),
        '#default_value' => $config->get('field_visibility_rules') ?: '',
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    if ($section === 'translation') {
      $form['translation'] = [
        '#type' => 'details',
        '#title' => $this->t('Translation Defaults'),
        '#description' => $this->t('Configure creation of translation defaults (language placeholders) for translatable entities.'),
        '#open' => TRUE,
      ];

      $form['translation']['auto_create'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Automatically create translation defaults'),
        '#description' => $this->t('When enabled, translation defaults (placeholders) are created for other languages at entity create/update. Existing translations with proper source are not overwritten.'),
        '#default_value' => $config->get('translation.auto_create') ?: FALSE,
      ];

      $form['translation']['use_all_languages'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use all available languages'),
        '#description' => $this->t('When enabled, translations will be created for all languages installed on the site. When disabled, only the selected target languages below will be used.'),
        '#default_value' => $config->get('translation.use_all_languages') ?: TRUE,
        '#states' => [
          'visible' => [
            ':input[name="auto_create"]' => ['checked' => TRUE],
          ],
        ],
      ];

      // Get available languages.
      $languages = $this->languageManager->getLanguages();
      // Filter out Lolspeak language.
      $languages = array_filter($languages, function($language) {
        return $language->getId() !== 'en-x-lolspeak';
      });
      $language_options = [];
      foreach ($languages as $langcode => $language) {
        $language_options[$langcode] = $language->getName();
      }

      $form['translation']['target_languages'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Target languages for translation defaults'),
        '#description' => $this->t('Select which languages to create translation defaults for. Used only when "Use all available languages" is disabled.'),
        '#options' => $language_options,
        '#default_value' => array_combine(
          $config->get('translation.target_languages') ?: [],
          $config->get('translation.target_languages') ?: []
        ),
        '#states' => [
          'visible' => [
            ':input[name="use_all_languages"]' => ['checked' => FALSE],
            ':input[name="auto_create"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['translation']['copy_source_values'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Copy source field values'),
        '#description' => $this->t('When enabled, translatable field values from the source language will be copied to new translations.'),
        '#default_value' => $config->get('translation.copy_source_values') !== FALSE,
        '#states' => [
          'visible' => [
            ':input[name="auto_create"]' => ['checked' => TRUE],
          ],
        ],
      ];

      // Get content entity types.
      $entity_types = $this->entityTypeManager->getDefinitions();
      $content_entity_options = [];
      foreach ($entity_types as $entity_type_id => $entity_type) {
        if ($entity_type->entityClassImplements('Drupal\Core\Entity\ContentEntityInterface')) {
          $content_entity_options[$entity_type_id] = $entity_type->getLabel();
        }
      }

      $form['translation']['entity_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Entity types') . ' ' . $this->t('(Required)'),
        '#description' => $this->t('<strong>Required:</strong> Select which entity types should have translation defaults created. You must select at least one entity type for automatic translation defaults to work. Common choice: <em>Content (node)</em>.'),
        '#options' => $content_entity_options,
        '#default_value' => array_combine(
          $config->get('translation.entity_types') ?: [],
          $config->get('translation.entity_types') ?: []
        ),
        '#states' => [
          'visible' => [
            ':input[name="auto_create"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['translation']['batch_operations'] = [
        '#type' => 'details',
        '#title' => $this->t('Batch Operations'),
        '#description' => $this->t('Process existing entities to create missing translation defaults.'),
        '#open' => TRUE,
      ];

      $form['translation']['batch_operations']['batch_entity_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity type to process'),
        '#options' => ['' => $this->t('- Select -')] + $content_entity_options,
        '#description' => $this->t('Select an entity type to create translations for existing entities.'),
      ];

      $form['translation']['batch_operations']['run_batch'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create translation defaults for existing entities'),
        '#submit' => ['::submitBatchForm'],
        '#states' => [
          'visible' => [
            ':input[name="batch_entity_type"]' => ['!value' => ''],
          ],
        ],
      ];
    }

    // Attach the settings toggle library
    $form['#attached']['library'][] = 'bioland/settings_toggle';

    // Add cache metadata for proper invalidation
    $form['#cache']['tags'] = ['config:system.site', 'config:bioland.settings'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $section = $form_state->get('bioland_section');

    if ($section === 'translation') {
      // Validate translation settings
      $auto_create = $form_state->getValue('auto_create');
      $entity_types = array_filter($form_state->getValue('entity_types') ?: []);

      // If auto-create is enabled but no entity types are selected, show a warning
      if ($auto_create && empty($entity_types)) {
        $form_state->setErrorByName('entity_types', $this->t('You must select at least one entity type for automatic translation defaults to work. Please select one or more entity types, or disable "Automatically create translation defaults".'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $section = $form_state->get('bioland_section');
    $config = $this->config('bioland.settings');

    if ($section === 'general') {
      $languages = $this->languageManager->getLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

      // Save system.site configuration (only if changed)
      $site_config = $this->configFactory()->getEditable('system.site');
      $site_config_changed = FALSE;

      if ($site_config->get('name') !== $values['site_name']) {
        $site_config->set('name', $values['site_name']);
        $site_config_changed = TRUE;
      }
      if ($site_config->get('slogan') !== $values['site_slogan']) {
        $site_config->set('slogan', $values['site_slogan']);
        $site_config_changed = TRUE;
      }
      if ($site_config->get('mail') !== $values['site_mail']) {
        $site_config->set('mail', $values['site_mail']);
        $site_config_changed = TRUE;
      }

      if ($site_config_changed) {
        $site_config->save();
      }

      // Save translations if there are multiple languages
      if (count($languages) > 1) {
        $translatable_fields = [
          'name' => 'site_name_translations',
          'slogan' => 'site_slogan_translations',
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }

          $config_override = $this->languageManager->getLanguageConfigOverride($langcode, 'system.site');
          $override_changed = FALSE;

          foreach ($translatable_fields as $config_key => $form_key) {
            $new_value = $values[$form_key][$langcode] ?? '';
            if ($config_override->get($config_key) !== $new_value) {
              $config_override->set($config_key, $new_value);
              $override_changed = TRUE;
            }
          }

          if ($override_changed) {
            $config_override->save();
          }
        }
      }

      // Normalize textarea inputs (one per line) to arrays
      $countries = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $values['countries']))));
      
      $config
        ->set('countries', $countries)
        ->set('region', $values['region'])
        ->set('is_biosafety_land', $values['is_biosafety_land']);
    }

    if ($section === 'fields') {
      // Process field visibility content type selections
      $url_content_types = array_values(array_filter($values['url_content_types']));
      $published_content_types = array_values(array_filter($values['published_content_types']));
      $date_range_content_types = array_values(array_filter($values['date_range_content_types']));

      $config
        ->set('enable_field_visibility', $values['enable_field_visibility'])
        ->set('field_visibility.url_content_types', $url_content_types)
        ->set('field_visibility.published_content_types', $published_content_types)
        ->set('field_visibility.date_range_content_types', $date_range_content_types)
        ->set('enable_additional_fields', $values['enable_additional_fields'])
        ->set('enable_auto_summary', $values['enable_auto_summary'])
        ->set('enable_help_comments', $values['enable_help_comments'])
        ->set('field_visibility_rules', $values['field_visibility_rules']);
    }

    if ($section === 'translation') {
      $target_languages = array_filter($values['target_languages']);
      $entity_types = array_filter($values['entity_types']);

      $config
        ->set('translation.auto_create', $values['auto_create'])
        ->set('translation.use_all_languages', $values['use_all_languages'])
        ->set('translation.target_languages', array_values($target_languages))
        ->set('translation.copy_source_values', $values['copy_source_values'])
        ->set('translation.entity_types', array_values($entity_types));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for batch operations.
   */
  public function submitBatchForm(array &$form, FormStateInterface $form_state) {
    $entity_type_id = $form_state->getValue('batch_entity_type');

    if (empty($entity_type_id)) {
      $this->messenger()->addError($this->t('Please select an entity type to process.'));
      return;
    }

    $batch = $this->translationBatchService->createTranslationBatch($entity_type_id);
    batch_set($batch);
  }

  /**
   * Get available country options.
   *
   * @return array
   *   Array of country options.
   */
  // No country options needed; countries are entered as free-form codes per line.

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
