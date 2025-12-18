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
    }

    if ($section === 'field_visibility') {
      $form['field_visibility_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Visibility Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Field Visibility functionality - wrapped in fieldset
      $form['field_visibility_settings']['field_visibility'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Visibility Control'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['field_visibility_settings']['field_visibility']['enable_field_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Visibility Control'),
        '#description' => $this->t('Show/hide fields based on content type selection. Configure which fields are visible for each content type below.'),
        '#default_value' => $config->get('enable_field_visibility') !== FALSE,
        '#suffix' => '<a href="#" class="bioland-toggle-visibility-settings" data-target="field-visibility-settings">Show more</a>',
      ];

      // Container for all field visibility settings
      $form['field_visibility_settings']['field_visibility']['settings_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bioland-field-visibility-settings', 'bioland-collapsible-hidden']],
      ];

      // Get content types from taxonomy vocabulary 'tags'.
      $content_types = $this->getContentTypeOptions();

      // URL Field visibility settings
      $form['field_visibility_settings']['field_visibility']['settings_container']['url_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('URL Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility_settings']['field_visibility']['settings_container']['url_field']['url_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show URL field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.url_content_types') ?: [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50],
      ];

      // Published Field visibility settings
      $form['field_visibility_settings']['field_visibility']['settings_container']['published_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Published Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility_settings']['field_visibility']['settings_container']['published_field']['published_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Published field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.published_content_types') ?: [3, 5, 12],
      ];

      // Date Range Field visibility settings
      $form['field_visibility_settings']['field_visibility']['settings_container']['date_range_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Date Range Field (Start & End Date)'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility_settings']['field_visibility']['settings_container']['date_range_field']['date_range_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Date Range fields (Start & End Date) for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.date_range_content_types') ?: [2, 3, 13],
      ];

      $form['field_visibility_settings']['field_visibility_rules'] = [
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

      // Additional Fields functionality - wrapped in fieldset
      $form['field_visibility_settings']['additional_fields'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Fields Control'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['field_visibility_settings']['additional_fields']['enable_additional_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Additional Fields'),
        '#description' => $this->t('Add content-type specific additional fields (event statuses, project statuses, etc.) based on thesaurus content type.'),
        '#default_value' => $config->get('enable_additional_fields') !== FALSE,
        '#suffix' => '<a href="#" class="bioland-toggle-additional-fields-settings" data-target="additional-fields-settings">Show more</a>',
      ];

      // Container for additional fields information
      $form['field_visibility_settings']['additional_fields']['settings_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bioland-additional-fields-settings', 'bioland-collapsible-hidden']],
      ];

      $form['field_visibility_settings']['additional_fields']['settings_container']['info'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Fields Mapping'),
        '#description' => $this->t('The following content types will have these additional fields available:'),
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility_settings']['additional_fields']['settings_container']['info']['mapping'] = [
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
    }

    if ($section === 'help_comments') {
      $languages = $this->languageManager->getLanguages();
      // Filter out Lolspeak language.
      $languages = array_filter($languages, function($language) {
        return $language->getId() !== 'en-x-lolspeak';
      });
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
      $has_multiple_languages = count($languages) > 1;

      $form['help_comments'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Help Comments Settings'),
        '#description' => $this->t('Configure contextual help messages displayed for fields in the content form.'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['help_comments']['enable_help_comments'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Help Comments'),
        '#description' => $this->t('Display contextual help comments for fields based on content type.'),
        '#default_value' => $config->get('enable_help_comments') !== FALSE,
      ];

      // Body Field Help Comment
      $form['help_comments']['body_help'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Body Field Help'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['help_comments']['body_help']['help_body_text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Body Field Help Text'),
        '#description' => $this->t('Help text displayed for the body/content field.'),
        '#default_value' => $config->get('help_comments.body_text') ?: $this->t('This will be the main content of your new site content. The summary can be used to display a brief concise description of your content. Further, the summary will be displayed in list and card views of your record on the website. Alternatively, the first few sentences from the main content will be used.'),
        '#rows' => 3,
      ];

      // Body Help Translations
      if ($has_multiple_languages) {
        $form['help_comments']['body_help']['body_help_translations'] = [
          '#type' => 'details',
          '#title' => $this->t('Translate Body Help Text'),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $form['help_comments']['body_help']['body_help_translations'][$langcode] = [
            '#type' => 'textarea',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.body_text_translations.{$langcode}") ?: '',
            '#rows' => 3,
          ];
        }
      }

      // Attachments Field Help Comment - Images
      $form['help_comments']['attachments_help'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Attachments Field Help'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['help_comments']['attachments_help']['help_attachments_images_text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Images Help Text'),
        '#description' => $this->t('Help text for the images section of attachments.'),
        '#default_value' => $config->get('help_comments.attachments_images_text') ?: $this->t('The first image in order of left to right here, will be the main image of your record displayed on the page and in thumbnails in list and card views. All other images will be displayed below the main content.'),
        '#rows' => 3,
      ];

      // Images Help Translations
      if ($has_multiple_languages) {
        $form['help_comments']['attachments_help']['images_help_translations'] = [
          '#type' => 'details',
          '#title' => $this->t('Translate Images Help Text'),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $form['help_comments']['attachments_help']['images_help_translations'][$langcode] = [
            '#type' => 'textarea',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.attachments_images_translations.{$langcode}") ?: '',
            '#rows' => 3,
          ];
        }
      }

      $form['help_comments']['attachments_help']['help_attachments_heroes_text'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Heroes Help Text'),
        '#description' => $this->t('Help text for the hero banners section.'),
        '#default_value' => $config->get('help_comments.attachments_heroes_text') ?: $this->t('Any page/content type can have multiple hero banners. If there is more than one they will be rotated on an hourly basis.'),
        '#rows' => 3,
      ];

      // Heroes Help Translations
      if ($has_multiple_languages) {
        $form['help_comments']['attachments_help']['heroes_help_translations'] = [
          '#type' => 'details',
          '#title' => $this->t('Translate Heroes Help Text'),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $form['help_comments']['attachments_help']['heroes_help_translations'][$langcode] = [
            '#type' => 'textarea',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.attachments_heroes_translations.{$langcode}") ?: '',
            '#rows' => 3,
          ];
        }
      }
    }

    if ($section === 'admin') {
      $form['admin_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Admin Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Auto Summary functionality
      $form['admin_settings']['enable_auto_summary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Auto Summary'),
        '#description' => $this->t('Automatically generate summary from body content as user types.'),
        '#default_value' => $config->get('enable_auto_summary') !== FALSE,
      ];

      $form['admin_settings']['is_biosafety_land'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Is Biosafety Land?'),
        '#description' => $this->t('Indicates whether this is a Biosafety Land instance.'),
        '#default_value' => $config->get('is_biosafety_land') ?: FALSE,
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
        ->set('region', $values['region']);
    }

    if ($section === 'field_visibility') {
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
        ->set('field_visibility_rules', $values['field_visibility_rules']);
    }

    if ($section === 'help_comments') {
      $languages = $this->languageManager->getLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

      $config
        ->set('enable_help_comments', $values['enable_help_comments'])
        ->set('help_comments.body_text', $values['help_body_text'])
        ->set('help_comments.attachments_images_text', $values['help_attachments_images_text'])
        ->set('help_comments.attachments_heroes_text', $values['help_attachments_heroes_text']);

      // Save translations for help comments
      if (count($languages) > 1) {
        $body_translations = $values['body_help_translations'] ?? [];
        $images_translations = $values['images_help_translations'] ?? [];
        $heroes_translations = $values['heroes_help_translations'] ?? [];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          if (!empty($body_translations[$langcode])) {
            $config->set("help_comments.body_text_translations.{$langcode}", $body_translations[$langcode]);
          }
          if (!empty($images_translations[$langcode])) {
            $config->set("help_comments.attachments_images_translations.{$langcode}", $images_translations[$langcode]);
          }
          if (!empty($heroes_translations[$langcode])) {
            $config->set("help_comments.attachments_heroes_translations.{$langcode}", $heroes_translations[$langcode]);
          }
        }
      }
    }

    if ($section === 'admin') {
      $config
        ->set('enable_auto_summary', $values['enable_auto_summary'])
        ->set('is_biosafety_land', $values['is_biosafety_land']);
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

  /**
   * Get content type options from the 'tags' taxonomy vocabulary.
   *
   * @return array
   *   Array of content type options keyed by term ID with term name as value.
   */
  protected function getContentTypeOptions() {
    $options = [];
    try {
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'tags']);

      foreach ($terms as $term) {
        $tid = (int) $term->id();
        $name = $term->label();
        $options[$tid] = $this->t('@name (@tid)', ['@name' => $name, '@tid' => $tid]);
      }

      // Sort by term ID for consistent ordering.
      ksort($options);
    }
    catch (\Exception $e) {
      // Log error and return empty array if taxonomy terms cannot be loaded.
      \Drupal::logger('bioland')->error('Failed to load content type options from tags vocabulary: @message', ['@message' => $e->getMessage()]);
    }

    return $options;
  }

}
