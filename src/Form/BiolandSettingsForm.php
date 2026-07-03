<?php

namespace Drupal\bioland\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
  * Constructs a new Drupal Module Bioland settings form.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bioland\Service\BiolandTranslationBatchService $translation_batch_service
   *   The translation batch service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, BiolandTranslationBatchService $translation_batch_service, Connection $database, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->translationBatchService = $translation_batch_service;
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('bioland.translation_batch'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('request_stack')
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
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|array
   *   The page title.
   */
  public function getTitle() {
    $branding = $this->getBrandingName();
    $title = $this->t('@branding Settings', ['@branding' => $branding]);
    
    return $title;
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
    }

    if ($section === 'system_functions') {
      $form['system_functions'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('System Functions'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Description at the top
      $form['system_functions']['description'] = [
        '#markup' => '<p><em>' . $this->t('Administrative system operations. Use with caution.') . '</em></p>',
        '#weight' => -100,
      ];

      // Wrapper for AJAX messages
      $form['system_functions']['messages_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'system-functions-messages'],
      ];

      // Cache Rebuild section
      $form['system_functions']['cache_section'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Drupal Cache Management'),
        '#collapsible' => FALSE,
      ];

      $form['system_functions']['cache_section']['cache_description'] = [
        '#markup' => '<p>' . $this->t('Clears all cached data from Drupal. This includes page caches, render caches, and compiled templates. Use this when you see outdated content or after making configuration changes that are not reflected on the site. Note, this does not affect CDN cache, browser cache, or middleware API wrapper cache.') . '</p>',
      ];

      $form['system_functions']['cache_section']['rebuild_cache'] = [
        '#type' => 'submit',
        '#value' => $this->t('Rebuild Drupal Cache'),
        '#submit' => ['::submitRebuildCache'],
        '#ajax' => [
          'callback' => '::ajaxRebuildCache',
          'wrapper' => 'system-functions-messages',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Rebuilding cache...'),
          ],
        ],
      ];

      // Translation Defaults section (moved from translation tab)
      $form['system_functions']['translation_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Translation Defaults'),
        '#open' => FALSE,
      ];

      $form['system_functions']['translation_section']['auto_create'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Automatically create translation defaults'),
        '#description' => $this->t('When enabled, translation defaults (placeholders) are created for other languages at entity create/update. Existing translations with proper source are not overwritten.'),
        '#default_value' => $config->get('translation.auto_create') ?: FALSE,
      ];

      $form['system_functions']['translation_section']['use_all_languages'] = [
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

      $form['system_functions']['translation_section']['target_languages'] = [
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

      $form['system_functions']['translation_section']['copy_source_values'] = [
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

      $form['system_functions']['translation_section']['entity_types'] = [
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

      // Batch Operations for translation defaults
      $form['system_functions']['translation_section']['batch_operations'] = [
        '#type' => 'details',
        '#title' => $this->t('Batch Operations'),
        '#description' => $this->t('Process existing entities to create missing translation defaults.'),
        '#open' => FALSE,
      ];

      $form['system_functions']['translation_section']['batch_operations']['batch_entity_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity type to process'),
        '#options' => ['' => $this->t('- Select -')] + $content_entity_options,
        '#description' => $this->t('Select an entity type to create translations for existing entities.'),
      ];

      $form['system_functions']['translation_section']['batch_operations']['run_batch'] = [
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

    if ($section === 'field_visibility') {
      $form['field_visibility'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Field Visibility Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Enable checkbox in its own box
      $form['field_visibility']['enable'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Enable'),
        '#collapsible' => FALSE,
      ];

      $form['field_visibility']['enable']['enable_field_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Visibility Control'),
        '#description' => $this->t('Show/hide fields based on content type selection. Configure which fields are visible for each content type below.'),
        '#default_value' => $config->get('enable_field_visibility') !== FALSE,
      ];

      // Get content types from taxonomy vocabulary 'tags'.
      $content_types = $this->getContentTypeOptions();

      // URL Field visibility settings - as collapsible details
      $form['field_visibility']['url_field'] = [
        '#type' => 'details',
        '#title' => $this->t('URL Field'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['url_field']['url_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show URL field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.url_content_types') ?: [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50],
      ];

      // Published Field visibility settings - as collapsible details
      $form['field_visibility']['published_field'] = [
        '#type' => 'details',
        '#title' => $this->t('Published Field'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['published_field']['published_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Published field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.published_content_types') ?: [3, 5, 12],
      ];

      // Date Range Field visibility settings - as collapsible details
      $form['field_visibility']['date_range_field'] = [
        '#type' => 'details',
        '#title' => $this->t('Date Range Field (Start & End Date)'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['date_range_field']['date_range_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Date Range fields (Start & End Date) for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.date_range_content_types') ?: [2, 3, 13],
      ];
    }

    if ($section === 'tags') {
      $form['tags_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Tags Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Enable checkbox in its own box
      $form['tags_settings']['enable'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Enable'),
        '#collapsible' => FALSE,
      ];

      $form['tags_settings']['enable']['enable_additional_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Additional Tags'),
        '#description' => $this->t('Add content-type specific additional tags (event statuses, project statuses, etc.) based on thesaurus content type.'),
        '#default_value' => $config->get('enable_additional_fields') !== FALSE,
      ];

      // Get content type options for tag configuration
      $content_types = $this->getContentTypeOptions();

      // Event Status tag settings
      $form['tags_settings']['event_status'] = [
        '#type' => 'details',
        '#title' => $this->t('Event Status'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['event_status']['event_status_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Event Status tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.event_status_content_types') ?: [3],
      ];

      // Project Status tag settings
      $form['tags_settings']['project_status'] = [
        '#type' => 'details',
        '#title' => $this->t('Project Status'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['project_status']['project_status_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Project Status tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.project_status_content_types') ?: [5],
      ];

      // Organization Type tag settings
      $form['tags_settings']['organization_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Organization Type'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['organization_types']['organization_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Organization Type tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.organization_types_content_types') ?: [8],
      ];

      // Ecosystem Type tag settings
      $form['tags_settings']['ecosystem_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Ecosystem Type'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['ecosystem_types']['ecosystem_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Ecosystem Type tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.ecosystem_types_content_types') ?: [9],
      ];

      // Document Type tag settings
      $form['tags_settings']['document_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Document Type'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['document_types']['document_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Document Type tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.document_types_content_types') ?: [12],
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
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Enable checkbox in its own box
      $form['help_comments']['enable'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Enable'),
        '#collapsible' => FALSE,
      ];

      $form['help_comments']['enable']['enable_help_comments'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Help Comments'),
        '#description' => $this->t('Display contextual help comments for fields based on content type.'),
        '#default_value' => $config->get('enable_help_comments') !== FALSE,
      ];

      // Body Field Help Comment
      $form['help_comments']['body_help'] = [
        '#type' => 'details',
        '#title' => $this->t('Body Field Help'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['help_comments']['body_help']['help_body_text'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Body Field Help Text'),
        '#description' => $this->t('Help text displayed for the body/content field.'),
        '#default_value' => $config->get('help_comments.body_text') ?: $this->t('This will be the main content of your new site content. The summary can be used to display a brief concise description of your content. Further, the summary will be displayed in list and card views of your record on the website. Alternatively, the first few sentences from the main content will be used.'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
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
            '#type' => 'text_format',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.body_text_translations.{$langcode}") ?: '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
          ];
        }
      }

      // Attachments Field Help Comment - Images
      $form['help_comments']['attachments_help'] = [
        '#type' => 'details',
        '#title' => $this->t('Attachments Field Help'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['help_comments']['attachments_help']['help_attachments_images_text'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Images Help Text'),
        '#description' => $this->t('Help text for the images section of attachments.'),
        '#default_value' => $config->get('help_comments.attachments_images_text') ?: $this->t('The first image in order of left to right here, will be the main image of your record displayed on the page and in thumbnails in list and card views. All other images will be displayed below the main content.'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
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
            '#type' => 'text_format',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.attachments_images_translations.{$langcode}") ?: '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
          ];
        }
      }

      $form['help_comments']['attachments_help']['help_attachments_heroes_text'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Heroes Help Text'),
        '#description' => $this->t('Help text for the hero banners section.'),
        '#default_value' => $config->get('help_comments.attachments_heroes_text') ?: $this->t('Any page/content type can have multiple hero banners. If there is more than one they will be rotated on an hourly basis.'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
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
            '#type' => 'text_format',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.attachments_heroes_translations.{$langcode}") ?: '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
          ];
        }
      }

      // Promotion Options Field Help Comment
      $form['help_comments']['promotion_help'] = [
        '#type' => 'details',
        '#title' => $this->t('Promotion Options Help'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $default_promotion_text = $this->t('<b>Promoted to front page:</b>
<ul>
<li>Content appears in featured listings, homepage blocks, or "promoted" views</li>
<li>Useful for highlighting important or timely content</li>
<li>Multiple items can be promoted simultaneously</li>
</ul>

<b>Sticky at top of lists:</b>
<ul>
<li>Content stays pinned at the top of content listings</li>
<li>Remains at top regardless of publication date</li>
<li>Multiple sticky items appear before non-sticky content</li>
</ul>

<b>Using both:</b> Content can be both promoted AND sticky for maximum visibility.');

      $form['help_comments']['promotion_help']['help_promotion_text'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Promotion Options Help Text'),
        '#description' => $this->t('Help text explaining the Promoted and Sticky options.'),
        '#default_value' => $config->get('help_comments.promotion_text') ?: $default_promotion_text,
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
      ];

      // Promotion Help Translations
      if ($has_multiple_languages) {
        $form['help_comments']['promotion_help']['promotion_help_translations'] = [
          '#type' => 'details',
          '#title' => $this->t('Translate Promotion Help Text'),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $form['help_comments']['promotion_help']['promotion_help_translations'][$langcode] = [
            '#type' => 'text_format',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.promotion_translations.{$langcode}") ?: '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
          ];
        }
      }

      // Order Override Field Help Comment
      $form['help_comments']['order_override_help'] = [
        '#type' => 'details',
        '#title' => $this->t('Order Override Help'),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $default_order_override_text = $this->t('<b>Content sorting priority (highest to lowest):</b>
<ol>
<li><b>Sticky</b> – Items marked "Sticky at top of lists" always appear first followed by:</li>
<li><b>Promoted</b> – Items marked "Promoted to front page" but sort above the other fields, followed by</li>
<li><b>Order Override</b> – A tool for fine grain ordering. Lower numbers appear before higher numbers (e.g., 10 appears before 20).</li>
<li><b>Start Date</b> – Then sorted by start date if it exists, followed by</li>
<li><b>Published Date</b> – Then sorted by publish date if it exists, followed by</li>
<li><b>Last Modified</b> – Finally sorted by most recently updated</li>
</ol>
<b>Tip:</b> Leave it at 10000 which is off essentially, to use default sorting. Use increments of 10 (e.g., 10, 20, 30) to leave room for inserting items later.');

      $form['help_comments']['order_override_help']['help_order_override_text'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Order Override Help Text'),
        '#description' => $this->t('Help text explaining the Order Override field and content sorting priority.'),
        '#default_value' => $config->get('help_comments.order_override_text') ?: $default_order_override_text,
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
      ];

      // Order Override Help Translations
      if ($has_multiple_languages) {
        $form['help_comments']['order_override_help']['order_override_help_translations'] = [
          '#type' => 'details',
          '#title' => $this->t('Translate Order Override Help Text'),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          $form['help_comments']['order_override_help']['order_override_help_translations'][$langcode] = [
            '#type' => 'text_format',
            '#title' => $language->getName(),
            '#default_value' => $config->get("help_comments.order_override_translations.{$langcode}") ?: '',
            '#format' => 'full_html',
            '#allowed_formats' => ['full_html'],
          ];
        }
      }
    }

    if ($section === 'front_end') {
      // Redirect to the first sub-tab (Front End General) when accessing the parent Front End tab
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
        \Drupal\Core\Url::fromRoute('bioland.settings.front_end.general')->toString()
      );
      $response->send();
      exit;
    }

    if ($section === 'front_end_general') {
      $form['front_end_general_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Front End General'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Promote and Sticky section (moved from system_functions)
      $form['front_end_general_settings']['promote_sticky_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Promote and Sticky Icon Visibility'),
        '#open' => FALSE,
      ];

      $form['front_end_general_settings']['promote_sticky_section']['promote_sticky_description'] = [
        '#markup' => '<p>' . $this->t('Promote and Sticky flags on records can be seen by public or anonymous users.') . '</p>',
      ];

      $form['front_end_general_settings']['promote_sticky_section']['promote_and_sticky_public'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Promote and Sticky flags to public users'),
        '#default_value' => $config->get('config.promote_and_sticky_public') !== FALSE,
      ];

      // Content Ordering Reset section (moved from system_functions)
      $form['front_end_general_settings']['ordering_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Content Ordering Reset'),
        '#open' => FALSE,
      ];

      // Wrapper for AJAX messages
      $form['front_end_general_settings']['ordering_section']['messages_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'front-end-general-messages'],
      ];

      $form['front_end_general_settings']['ordering_section']['ordering_description'] = [
        '#markup' => '<p>' . $this->t('Resets the field_order value to 10000 for all nodes. This creates a new revision for each node with the revision message "Reset ordering". The original last updated date and user are preserved. Use this to normalize content ordering across the site.') . '</p>',
      ];

      $form['front_end_general_settings']['ordering_section']['reset_ordering'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset Ordering'),
        '#submit' => ['::submitResetOrdering'],
        '#ajax' => [
          'callback' => '::ajaxResetOrdering',
          'wrapper' => 'front-end-general-messages',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Resetting ordering values...'),
          ],
        ],
      ];

      // Reset Promoted Content section
      $form['front_end_general_settings']['promoted_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Reset Promoted Content'),
        '#open' => FALSE,
      ];

      $form['front_end_general_settings']['promoted_section']['promoted_description'] = [
        '#markup' => '<p>' . $this->t('Removes all the promoted flags from every content entry.') . '</p>',
      ];

      $form['front_end_general_settings']['promoted_section']['reset_promoted'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset Promoted'),
        '#submit' => ['::submitResetPromoted'],
        '#ajax' => [
          'callback' => '::ajaxResetPromoted',
          'wrapper' => 'front-end-general-messages',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Resetting promoted flags...'),
          ],
        ],
      ];

      // Reset Sticky Content section
      $form['front_end_general_settings']['sticky_section'] = [
        '#type' => 'details',
        '#title' => $this->t('Reset Sticky Content'),
        '#open' => FALSE,
      ];

      $form['front_end_general_settings']['sticky_section']['sticky_description'] = [
        '#markup' => '<p>' . $this->t('Removes all the sticky flags from every content entry.') . '</p>',
      ];

      $form['front_end_general_settings']['sticky_section']['reset_sticky'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset Sticky'),
        '#submit' => ['::submitResetSticky'],
        '#ajax' => [
          'callback' => '::ajaxResetSticky',
          'wrapper' => 'front-end-general-messages',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Resetting sticky flags...'),
          ],
        ],
      ];
    }

    if ($section === 'front_end_mega_menu') {
      $form['mega_menu_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Mega Menu Component Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#tree' => TRUE,
      ];

      // Content Type Menus section
      $form['mega_menu_settings']['content_type_menus'] = [
        '#type' => 'details',
        '#title' => $this->t('Content Type Menus'),
        '#open' => FALSE,
        '#description' => $this->t('Configure automatic menu entries for each content type in the mega menu.'),
        '#tree' => TRUE,
      ];

      // Add rescan button
      // $form['mega_menu_settings']['content_type_menus']['rescan_menus'] = [
      //   '#type' => 'submit',
      //   '#value' => $this->t('Rescan Menus'),
      //   '#submit' => ['::submitRescanMenus'],
      //   '#limit_validation_errors' => [],
      //   '#attributes' => ['class' => ['button', 'button--small']],
      //   '#description' => $this->t('Click to rescan all menus for content type classes.'),
      // ];

      // Get enabled content types with plural field (alphabetically sorted)
      $content_types = $this->getContentTypeOptionsForMegaMenu();
      
      // Get content type TIDs that are actually in use in menus
      // TODO: Debug why this returns empty - for now show all content types
      // $tids_in_use = $this->getContentTypeTidsInUse();

      // Create a fieldset for each content type
      foreach ($content_types as $tid => $type_name) {
        // TODO: Re-enable this filter after debugging getContentTypeTidsInUse()
        // Only show if this content type is in use in menus
        // if (!in_array($tid, $tids_in_use)) {
        //   continue;
        // }
        
        $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid] = [
          '#type' => 'details',
          '#title' => $this->t('@type_name Menu', ['@type_name' => $type_name]),
          '#open' => FALSE,
        ];

        // Maximum menus to show (1-20)
        $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['max_menus_' . $tid] = [
          '#type' => 'select',
          '#title' => $this->t('Maximum menus to show'),
          '#options' => array_combine(range(1, 20), range(1, 20)),
          '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.max_menus') ?? 6,
          '#description' => $this->t('Maximum number of menu entries to display for this content type.'),
        ];

        // Drupal menus position (top/bottom)
        $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['menu_position_' . $tid] = [
          '#type' => 'select',
          '#title' => $this->t('Drupal menus position'),
          '#options' => [
            'top' => $this->t('Top'),
            'bottom' => $this->t('Bottom'),
          ],
          '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.menu_position') ?? 'top',
          '#description' => $this->t('Drupal menu entries will appear at the top or bottom of automatic entries.'),
        ];

        // Show menu even if empty checkbox
        $form['mega_menu_settings']['content_type_menus']['content_type_' . $tid]['show_if_empty_' . $tid] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show menu even if empty'),
          '#default_value' => $config->get('mega_menu.content_type_menus.' . $tid . '.show_if_empty') ?? FALSE,
          '#description' => $this->t('Display this menu section even when there are no entries.'),
        ];
      }

      // Content Types Statistics Menu section
      $form['mega_menu_settings']['content_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Content Types Statistics Menu'),
        '#open' => FALSE,
      ];

      // Menu position (top/bottom)
      $form['mega_menu_settings']['content_types']['content_types_position'] = [
        '#type' => 'select',
        '#title' => $this->t('Menu position'),
        '#options' => [
          'top' => $this->t('Top'),
          'bottom' => $this->t('Bottom'),
        ],
        '#default_value' => $config->get('mega_menu.content_types.position') ?? 'top',
        '#description' => $this->t('Position where menu entries manually created in drupal will appear next to the automated section.'),
      ];

      $form['mega_menu_settings']['content_types']['hide_content_types'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide content types in mega menu'),
        '#default_value' => $config->get('mega_menu.content_types.hide_content_types') ?? TRUE,
        '#description' => $this->t('When enabled, content types will be hidden in the mega menu.'),
      ];

      // Additional menu sections
      $additional_menus = [
        'country_profiles' => $this->t('Country Profiles Menu'),
        'focal_points' => $this->t('Focal Points Menu'),
        'national_targets' => $this->t('National Targets Menu'),
        'national_report' => $this->t('National Report Menu'),
        'bch' => $this->t('BCH Menu'),
        'absch' => $this->t('ABSCH Menu'),
        'forums' => $this->t('Forums Menu'),
      ];

      foreach ($additional_menus as $menu_key => $menu_title) {
        $form['mega_menu_settings'][$menu_key] = [
          '#type' => 'details',
          '#title' => $menu_title,
          '#open' => FALSE,
        ];

        // Menu position (top/bottom)
        $form['mega_menu_settings'][$menu_key][$menu_key . '_position'] = [
          '#type' => 'select',
          '#title' => $this->t('Menu position'),
          '#options' => [
            'top' => $this->t('Top'),
            'bottom' => $this->t('Bottom'),
          ],
          '#default_value' => $config->get('mega_menu.' . $menu_key . '.position') ?? 'top',
          '#description' => $this->t('Position where menu entries manually created in drupal will appear next to the automated section.'),
        ];

        // Show menu even if empty checkbox
        $form['mega_menu_settings'][$menu_key][$menu_key . '_show_if_empty'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show menu even if empty'),
          '#default_value' => $config->get('mega_menu.' . $menu_key . '.show_if_empty') ?? FALSE,
          '#description' => $this->t('Display this menu section even when there are no entries.'),
        ];
      }
    }

    if ($section === 'front_end_home_page') {
      // Load heroes and display each in its own fieldset
      $this->buildHeroSections($form);
    }

    if ($section === 'front_end_home_widgets') {
      // Ensure default values are saved to config if not already present
      $this->ensureHomeWidgetDefaults($config);
      
      $form['home_widgets_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Home Page Widget Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#tree' => TRUE,
      ];

      // GBIF Widget section
      $form['home_widgets_settings']['gbif_widget'] = [
        '#type' => 'details',
        '#title' => $this->t('GBIF Widget'),
        '#open' => FALSE,
        '#tree' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
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
        '#states' => [
          'visible' => [
            ':input[name="is_biosafety_land"]' => ['checked' => FALSE],
          ],
        ],
      ];

      $form['home_widgets_settings']['geobon_widget']['enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable GEOBON Widget'),
        '#default_value' => $config->get('home_widgets.geobon_widget.enable') !== FALSE,
        '#description' => $this->t('Enable the GEOBON widget on the home page.'),
      ];
    }

    if ($section === 'admin') {
      $form['admin_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Admin Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Site email configuration
      $site_config = $this->configFactory()->getEditable('system.site');
      $form['admin_settings']['site_email'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Site Email'),
        '#collapsible' => FALSE,
      ];

      $form['admin_settings']['site_email']['site_mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#default_value' => $site_config->get('mail'),
        '#description' => $this->t("The <em>From</em> address in automated e-mails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this e-mail being flagged as spam.)"),
        '#required' => TRUE,
      ];

      // Auto Summary functionality
      $form['admin_settings']['auto_summary'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Auto Summary'),
        '#collapsible' => FALSE,
      ];

      $form['admin_settings']['auto_summary']['enable_auto_summary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Auto Summary'),
        '#description' => $this->t('Automatically generate summary from body content as user types.'),
        '#default_value' => $config->get('enable_auto_summary') !== FALSE,
      ];

      // Biosafety Land setting
      $form['admin_settings']['biosafety_land'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Biosafety Land'),
        '#collapsible' => FALSE,
      ];

      $form['admin_settings']['biosafety_land']['is_biosafety_land'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Is Biosafety Land?'),
        '#description' => $this->t('Indicates whether this is a Biosafety Land instance.'),
        '#default_value' => $config->get('is_biosafety_land') ?: FALSE,
      ];

      // Countries configuration
      $form['admin_settings']['countries'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Countries'),
        '#collapsible' => FALSE,
      ];

      $form['admin_settings']['countries']['countries'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Countries'),
        '#description' => $this->t('Enter one country code per line.'),
        '#default_value' => implode("\n", (array) ($config->get('countries') ?: ['lk'])),
        '#required' => TRUE,
      ];

      // Debug Logging settings
      $form['admin_settings']['debug_logging'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Debug Logging'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['admin_settings']['debug_logging']['enable_debug_logging'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Debug Logging'),
        '#description' => $this->t('Enable console.log output for Bioland JavaScript features. Useful for debugging.'),
        '#default_value' => $config->get('enable_debug_logging') ?: FALSE,
      ];

      $form['admin_settings']['debug_logging']['debug_log_areas'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Debug Log Areas'),
        '#description' => $this->t('Select which areas should output debug logs. Only effective when Debug Logging is enabled.'),
        '#states' => [
          'visible' => [
            ':input[name="enable_debug_logging"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_field_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Field Visibility'),
        '#description' => $this->t('Log field visibility show/hide operations.'),
        '#default_value' => $config->get('debug_log_areas.field_visibility') !== FALSE,
      ];

      $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_additional_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Additional Fields'),
        '#description' => $this->t('Log additional fields mounting and content type changes.'),
        '#default_value' => $config->get('debug_log_areas.additional_fields') !== FALSE,
      ];

      $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_auto_summary'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Auto Summary'),
        '#description' => $this->t('Log auto summary generation and CKEditor connections.'),
        '#default_value' => $config->get('debug_log_areas.auto_summary') !== FALSE,
      ];

      $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_help_comments'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Help Comments'),
        '#description' => $this->t('Log help comment insertion and visibility changes.'),
        '#default_value' => $config->get('debug_log_areas.help_comments') !== FALSE,
      ];

      // Main Menu Lock settings
      $form['admin_settings']['main_menu_lock'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Main Menu Lock'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['admin_settings']['main_menu_lock']['main_menu_lock_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Lock Main Menu Editing'),
        '#description' => $this->t('Prevents scbd_staff, site_manager and content_manager from editing the main menu. When unchecked, these roles will be granted the "Administer Main navigation menu items" permission.'),
        '#default_value' => $config->get('main_menu_lock') !== FALSE,
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

    if ($section === 'system_functions') {
      // Validate translation settings that are now in system_functions
      $auto_create = $form_state->getValue('auto_create');
      $entity_types = array_filter($form_state->getValue('entity_types') ?: []);

      // If auto-create is enabled but no entity types are selected, show a warning
      if ($auto_create && empty($entity_types)) {
        $form_state->setErrorByName('entity_types', $this->t('You must select at least one entity type for automatic translation defaults to work. Please select one or more entity types, or disable "Automatically create translation defaults".'));
      }
    }
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $section = $form_state->get('bioland_section');
    $config = $this->config('bioland.settings');

    if ($section === 'general') {
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

    if ($section === 'field_visibility') {
      // Process field visibility content type selections
      $url_content_types = array_values(array_filter($values['url_content_types']));
      $published_content_types = array_values(array_filter($values['published_content_types']));
      $date_range_content_types = array_values(array_filter($values['date_range_content_types']));

      $config
        ->set('enable_field_visibility', $values['enable_field_visibility'])
        ->set('field_visibility.url_content_types', $url_content_types)
        ->set('field_visibility.published_content_types', $published_content_types)
        ->set('field_visibility.date_range_content_types', $date_range_content_types);
    }

    if ($section === 'tags') {
      // Process additional tags content type selections
      $event_status_content_types = array_values(array_filter($values['event_status_content_types']));
      $project_status_content_types = array_values(array_filter($values['project_status_content_types']));
      $organization_types_content_types = array_values(array_filter($values['organization_types_content_types']));
      $ecosystem_types_content_types = array_values(array_filter($values['ecosystem_types_content_types']));
      $document_types_content_types = array_values(array_filter($values['document_types_content_types']));

      $config
        ->set('enable_additional_fields', $values['enable_additional_fields'])
        ->set('additional_tags.event_status_content_types', $event_status_content_types)
        ->set('additional_tags.project_status_content_types', $project_status_content_types)
        ->set('additional_tags.organization_types_content_types', $organization_types_content_types)
        ->set('additional_tags.ecosystem_types_content_types', $ecosystem_types_content_types)
        ->set('additional_tags.document_types_content_types', $document_types_content_types);
    }

    if ($section === 'help_comments') {
      $languages = $this->languageManager->getLanguages();
      $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

      // Helper function to extract value and format from text_format fields
      $extractTextFormat = function($field) {
        if (is_array($field)) {
          return [
            'value' => $field['value'] ?? '',
            'format' => $field['format'] ?? 'basic_html',
          ];
        }
        return ['value' => $field, 'format' => 'basic_html'];
      };

      // Extract values and formats for all help text fields
      $body_text = $extractTextFormat($values['help_body_text']);
      $images_text = $extractTextFormat($values['help_attachments_images_text']);
      $heroes_text = $extractTextFormat($values['help_attachments_heroes_text']);
      $promotion_text = $extractTextFormat($values['help_promotion_text']);
      $order_override_text = $extractTextFormat($values['help_order_override_text']);

      $config
        ->set('enable_help_comments', $values['enable_help_comments'])
        ->set('help_comments.body_text', $body_text['value'])
        ->set('help_comments.body_text_format', $body_text['format'])
        ->set('help_comments.attachments_images_text', $images_text['value'])
        ->set('help_comments.attachments_images_text_format', $images_text['format'])
        ->set('help_comments.attachments_heroes_text', $heroes_text['value'])
        ->set('help_comments.attachments_heroes_text_format', $heroes_text['format'])
        ->set('help_comments.promotion_text', $promotion_text['value'])
        ->set('help_comments.promotion_text_format', $promotion_text['format'])
        ->set('help_comments.order_override_text', $order_override_text['value'])
        ->set('help_comments.order_override_text_format', $order_override_text['format']);

      // Save translations for help comments
      if (count($languages) > 1) {
        $body_translations = $values['body_help_translations'] ?? [];
        $images_translations = $values['images_help_translations'] ?? [];
        $heroes_translations = $values['heroes_help_translations'] ?? [];
        $promotion_translations = $values['promotion_help_translations'] ?? [];
        $order_override_translations = $values['order_override_help_translations'] ?? [];

        foreach ($languages as $langcode => $language) {
          if ($langcode === $default_langcode) {
            continue;
          }
          if (!empty($body_translations[$langcode])) {
            $body_trans = $extractTextFormat($body_translations[$langcode]);
            $config->set("help_comments.body_text_translations.{$langcode}", $body_trans['value']);
            $config->set("help_comments.body_text_translations_format.{$langcode}", $body_trans['format']);
          }
          if (!empty($images_translations[$langcode])) {
            $images_trans = $extractTextFormat($images_translations[$langcode]);
            $config->set("help_comments.attachments_images_translations.{$langcode}", $images_trans['value']);
            $config->set("help_comments.attachments_images_translations_format.{$langcode}", $images_trans['format']);
          }
          if (!empty($heroes_translations[$langcode])) {
            $heroes_trans = $extractTextFormat($heroes_translations[$langcode]);
            $config->set("help_comments.attachments_heroes_translations.{$langcode}", $heroes_trans['value']);
            $config->set("help_comments.attachments_heroes_translations_format.{$langcode}", $heroes_trans['format']);
          }
          if (!empty($promotion_translations[$langcode])) {
            $promotion_trans = $extractTextFormat($promotion_translations[$langcode]);
            $config->set("help_comments.promotion_translations.{$langcode}", $promotion_trans['value']);
            $config->set("help_comments.promotion_translations_format.{$langcode}", $promotion_trans['format']);
          }
          if (!empty($order_override_translations[$langcode])) {
            $order_override_trans = $extractTextFormat($order_override_translations[$langcode]);
            $config->set("help_comments.order_override_translations.{$langcode}", $order_override_trans['value']);
            $config->set("help_comments.order_override_translations_format.{$langcode}", $order_override_trans['format']);
          }
        }
      }
    }

    if ($section === 'admin') {
      // Update system.site mail configuration
      $site_config = $this->configFactory()->getEditable('system.site');
      $site_config->set('mail', $values['site_mail'])->save();

      // Handle countries field - normalize textarea input
      $countries = array_filter(array_map('trim', explode("\n", $values['countries'])));

      $config
        ->set('enable_auto_summary', $values['enable_auto_summary'])
        ->set('is_biosafety_land', $values['is_biosafety_land'])
        ->set('countries', array_values($countries))
        ->set('enable_debug_logging', $values['enable_debug_logging'])
        ->set('debug_log_areas.field_visibility', $values['debug_log_field_visibility'])
        ->set('debug_log_areas.additional_fields', $values['debug_log_additional_fields'])
        ->set('debug_log_areas.auto_summary', $values['debug_log_auto_summary'])
        ->set('debug_log_areas.help_comments', $values['debug_log_help_comments'])
        ->set('main_menu_lock', $values['main_menu_lock_enabled']);

      // Handle Main Menu Lock permission changes
      $this->handleMainMenuLockPermissions($values['main_menu_lock_enabled']);
    }

    if ($section === 'system_functions') {
      // Save translation settings (moved from translation section)
      $target_languages = array_filter($values['target_languages'] ?? []);
      $entity_types = array_filter($values['entity_types'] ?? []);

      $config
        ->set('translation.auto_create', $values['auto_create'] ?? FALSE)
        ->set('translation.use_all_languages', $values['use_all_languages'] ?? TRUE)
        ->set('translation.target_languages', array_values($target_languages))
        ->set('translation.copy_source_values', $values['copy_source_values'] ?? FALSE)
        ->set('translation.entity_types', array_values($entity_types));
    }

    if ($section === 'front_end_general') {
      // Save Promote and Sticky settings (moved from system_functions)
      $config
        ->set('config.promote_and_sticky_public', (bool) ($values['promote_and_sticky_public'] ?? TRUE));
    }

    if ($section === 'front_end_mega_menu') {
      // Clear cached visible TIDs to force rescan on next load
      $config->clear('mega_menu.content_type_menus.visible_content_type_menus');
      
      // Get mega_menu_settings values - with #tree => TRUE, values are nested
      $mega_menu_values = $values['mega_menu_settings'] ?? [];
      $content_type_menus = $mega_menu_values['content_type_menus'] ?? [];
      
      // Debug: Log only keys to understand structure without memory issues
      \Drupal::logger('bioland')->debug('Mega Menu submit - top-level keys: @keys', [
        '@keys' => implode(', ', array_keys($values)),
      ]);
      \Drupal::logger('bioland')->debug('Mega Menu submit - mega_menu_values keys: @keys', [
        '@keys' => is_array($mega_menu_values) ? implode(', ', array_keys($mega_menu_values)) : 'NOT AN ARRAY',
      ]);
      \Drupal::logger('bioland')->debug('Mega Menu submit - content_type_menus keys: @keys', [
        '@keys' => is_array($content_type_menus) ? implode(', ', array_keys($content_type_menus)) : 'NOT AN ARRAY',
      ]);
      
      // Save mega menu content type settings
      // Form values are nested: $values['mega_menu_settings']['content_type_menus']['content_type_X']['max_menus_X']
      $content_types = $this->getContentTypeOptionsForMegaMenu();
      
      foreach ($content_types as $tid => $type_name) {
        $content_type_key = 'content_type_' . $tid;
        $max_menus_key = 'max_menus_' . $tid;
        $menu_position_key = 'menu_position_' . $tid;
        $show_if_empty_key = 'show_if_empty_' . $tid;
        
        // Values are nested under content_type_menus -> content_type_X
        $content_type_values = $content_type_menus[$content_type_key] ?? [];
        
        if (isset($content_type_values[$max_menus_key])) {
          $config->set('mega_menu.content_type_menus.' . $tid . '.max_menus', (int) $content_type_values[$max_menus_key]);
        }
        
        if (isset($content_type_values[$menu_position_key])) {
          $config->set('mega_menu.content_type_menus.' . $tid . '.menu_position', $content_type_values[$menu_position_key]);
        }
        
        if (isset($content_type_values[$show_if_empty_key])) {
          $config->set('mega_menu.content_type_menus.' . $tid . '.show_if_empty', (bool) $content_type_values[$show_if_empty_key]);
        }
      }

      // Save content types settings (nested under mega_menu_settings -> content_types)
      $content_types_settings = $mega_menu_values['content_types'] ?? [];
      if (isset($content_types_settings['content_types_position'])) {
        $config->set('mega_menu.content_types.position', $content_types_settings['content_types_position']);
      }
      $config->set('mega_menu.content_types.hide_content_types', (bool) ($content_types_settings['hide_content_types'] ?? TRUE));

      // Save additional menu settings
      // Form values are nested: $values['mega_menu_settings']['country_profiles']['country_profiles_position']
      $additional_menus = ['country_profiles', 'focal_points', 'national_targets', 'national_report', 'bch', 'absch', 'forums'];
      
      foreach ($additional_menus as $menu_key) {
        $position_key = $menu_key . '_position';
        $show_if_empty_key = $menu_key . '_show_if_empty';
        
        // Values are nested under mega_menu_settings -> menu_key
        $menu_values = $mega_menu_values[$menu_key] ?? [];
        
        if (isset($menu_values[$position_key])) {
          $config->set('mega_menu.' . $menu_key . '.position', $menu_values[$position_key]);
        }
        
        if (isset($menu_values[$show_if_empty_key])) {
          $config->set('mega_menu.' . $menu_key . '.show_if_empty', (bool) $menu_values[$show_if_empty_key]);
        }
      }
    }

    if ($section === 'front_end_home_widgets') {
      // Get home_widgets_settings values - with #tree => TRUE, values are nested
      $home_widgets_values = $values['home_widgets_settings'] ?? [];
      
      // Save GBIF widget settings
      $gbif_widget = $home_widgets_values['gbif_widget'] ?? [];
      
      // Save enable checkbox
      $config->set('home_widgets.gbif_widget.enable', (bool) ($gbif_widget['enable'] ?? TRUE));
      
      // Save country-specific settings
      $countries_data = $gbif_widget['countries'] ?? [];
      foreach ($countries_data as $country_code => $country_settings) {
        $config->set("home_widgets.gbif_widget.countries.{$country_code}.zoom_level", (int) ($country_settings['zoom_level'] ?? 7));
        $config->set("home_widgets.gbif_widget.countries.{$country_code}.longitude", (float) ($country_settings['longitude'] ?? 0.0));
        $config->set("home_widgets.gbif_widget.countries.{$country_code}.latitude", (float) ($country_settings['latitude'] ?? 0.0));
      }
      
      // Save Latest News Widget settings
      $config->set('home_widgets.latest_news_widget.enable', (bool) ($home_widgets_values['latest_news_widget']['enable'] ?? TRUE));
      
      // Save National Targets Widget settings
      $config->set('home_widgets.national_targets_widget.enable', (bool) ($home_widgets_values['national_targets_widget']['enable'] ?? TRUE));
      
      // Save Panorama Solutions Widget settings
      $config->set('home_widgets.panorama_solutions_widget.enable', (bool) ($home_widgets_values['panorama_solutions_widget']['enable'] ?? TRUE));
      
      // Save E-Learning Widget settings
      $config->set('home_widgets.elearning_widget.enable', (bool) ($home_widgets_values['elearning_widget']['enable'] ?? TRUE));
      
      // Save Implementation Widget settings
      $config->set('home_widgets.implementation_widget.enable', (bool) ($home_widgets_values['implementation_widget']['enable'] ?? TRUE));
      
      // Save Technical & Scientific Cooperation Widget settings
      $config->set('home_widgets.technical_cooperation_widget.enable', (bool) ($home_widgets_values['technical_cooperation_widget']['enable'] ?? TRUE));
      
      // Save Latest Discussions Widget settings
      $config->set('home_widgets.latest_discussions_widget.enable', (bool) ($home_widgets_values['latest_discussions_widget']['enable'] ?? TRUE));
      
      // Save Content Statistics Widget settings
      $config->set('home_widgets.content_statistics_widget.enable', (bool) ($home_widgets_values['content_statistics_widget']['enable'] ?? TRUE));
      
      // Save GEOBON Widget settings
      $config->set('home_widgets.geobon_widget.enable', (bool) ($home_widgets_values['geobon_widget']['enable'] ?? TRUE));
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
   * Submit handler for cache rebuild.
   */
  public function submitRebuildCache(array &$form, FormStateInterface $form_state) {
    // Rebuild cache.
    drupal_flush_all_caches();
    $this->messenger()->addStatus($this->t('Drupal cache has been rebuilt successfully.'));
  }

  /**
   * AJAX callback for cache rebuild.
   */
  public function ajaxRebuildCache(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand($this->t('Drupal cache has been rebuilt successfully.'), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for reset ordering.
   */
  public function submitResetOrdering(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllNodeOrdering();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_ordering_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset field_order to 1000 for @count nodes.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset ordering.
   */
  public function ajaxResetOrdering(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_ordering_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset field_order to 1000 for @count nodes.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for reset promoted.
   */
  public function submitResetPromoted(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllPromotedFlags();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_promoted_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset promoted flag to 0 for @count content entries.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset promoted.
   */
  public function ajaxResetPromoted(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_promoted_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset promoted flag to 0 for @count content entries.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for reset sticky.
   */
  public function submitResetSticky(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllStickyFlags();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_sticky_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset sticky flag to 0 for @count content entries.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset sticky.
   */
  public function ajaxResetSticky(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_sticky_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset sticky flag to 0 for @count content entries.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for rescan menus button.
   */
  public function submitRescanMenus(array &$form, FormStateInterface $form_state) {
    // Clear the cached visible TIDs
    $this->configFactory->getEditable('bioland.settings')
      ->clear('mega_menu.content_type_menus.visible_content_type_menus')
      ->save();
    
    $this->messenger()->addStatus($this->t('Menu scan cache cleared. The page will reload to show updated content types.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Resets field_order to 1000 for all nodes while preserving timestamps.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllNodeOrdering() {
    $count = 0;
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Load all nodes that have field_order.
    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->exists('field_order');

    $nids = $query->execute();

    if (empty($nids)) {
      return 0;
    }

    // Process nodes in batches to avoid memory issues.
    $batch_size = 50;
    $nid_chunks = array_chunk($nids, $batch_size);

    foreach ($nid_chunks as $chunk) {
      $nodes = $node_storage->loadMultiple($chunk);

      foreach ($nodes as $node) {
        // Check if field_order exists and if value is different from 1000.
        if (!$node->hasField('field_order')) {
          continue;
        }

        $current_value = $node->get('field_order')->value;
        if ($current_value == 1000) {
          // Already set to 1000, skip.
          continue;
        }

        // Store original values.
        $original_changed = $node->getChangedTime();
        $original_uid = $node->getRevisionUserId();

        // Set new revision.
        $node->setNewRevision(TRUE);
        $node->set('field_order', 1000);
        $node->setRevisionLogMessage($this->t('Reset ordering'));
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());

        // Save the node (this will trigger hooks).
        $node->save();

        // Now restore the original changed time and uid using direct database update.
        // This preserves the "last updated" display while having proper revision history.
        $this->database->update('node_field_data')
          ->fields([
            'changed' => $original_changed,
            'uid' => $original_uid,
          ])
          ->condition('nid', $node->id())
          ->execute();

        // Also update for translations if any.
        $this->database->update('node_field_data')
          ->fields([
            'changed' => $original_changed,
          ])
          ->condition('nid', $node->id())
          ->execute();

        $count++;
      }
    }

    // Clear node cache after bulk updates.
    $node_storage->resetCache($nids);

    \Drupal::logger('bioland')->notice('Reset field_order to 1000 for @count nodes.', ['@count' => $count]);

    return $count;
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
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'tags',
          'status' => 1,
        ]);

      foreach ($terms as $term) {
        // Load translated version of the term if available
        if ($term->hasTranslation($current_language)) {
          $term = $term->getTranslation($current_language);
        }
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
   * Get content type options with plural field for mega menu display.
   *
   * @return array
   *   Array of content type options keyed by term ID with plural field value,
   *   sorted alphabetically by plural name.
   */
  protected function getContentTypeOptionsForMegaMenu() {
    $options = [];
    try {
      $current_language = $this->languageManager->getCurrentLanguage()->getId();
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'tags',
          'status' => 1,
        ]);

      foreach ($terms as $term) {
        // Load translated version of the term if available
        if ($term->hasTranslation($current_language)) {
          $term = $term->getTranslation($current_language);
        }
        $tid = (int) $term->id();
        // Get plural field if available, fallback to label
        $plural = $term->hasField('field_plural') && !$term->get('field_plural')->isEmpty()
          ? $term->get('field_plural')->value
          : $term->label();
        $options[$tid] = $plural;
      }

      // Sort alphabetically by plural name
      asort($options);
    }
    catch (\Exception $e) {
      // Log error and return empty array if taxonomy terms cannot be loaded.
      \Drupal::logger('bioland')->error('Failed to load content type options for mega menu: @message', ['@message' => $e->getMessage()]);
    }

    return $options;
  }

  /**
   * Get content type reference data.
   *
   * This is a reference of all known content types with their metadata.
   * Can be imported and used for configuration, validation, or frontend display.
   *
   * @return array
   *   Array of content type data, each with:
   *   - tid: Taxonomy term ID
   *   - name: Singular name
   *   - field_plural: Plural name
   *   - icon: Emoji icon
   *   - slug: Kebab-case version of singular name
   */
  protected static function getContentTypeReference() {
    return [
      ['tid' => 2, 'name' => 'News', 'field_plural' => 'News', 'icon' => '📰', 'slug' => 'news'],
      ['tid' => 3, 'name' => 'Event', 'field_plural' => 'Events', 'icon' => '📅', 'slug' => 'event'],
      ['tid' => 4, 'name' => 'Learning Resource', 'field_plural' => 'Learning Resources', 'icon' => '🎓', 'slug' => 'learning-resource'],
      ['tid' => 5, 'name' => 'Project', 'field_plural' => 'Projects', 'icon' => '📊', 'slug' => 'project'],
      ['tid' => 6, 'name' => 'Basic Page', 'field_plural' => 'Basic Pages', 'icon' => '📄', 'slug' => 'basic-page'],
      ['tid' => 54, 'name' => 'Article', 'field_plural' => 'Articles', 'icon' => '📄', 'slug' => 'article'],
      ['tid' => 8, 'name' => 'Government Ministry or Institute', 'field_plural' => 'Government Ministries or Institutes', 'icon' => '🏛️', 'slug' => 'government-ministry-or-institute'],
      ['tid' => 9, 'name' => 'Ecosystem', 'field_plural' => 'Ecosystems', 'icon' => '🌍', 'slug' => 'ecosystem'],
      ['tid' => 10, 'name' => 'Protected Area', 'field_plural' => 'Protected Areas', 'icon' => '🏞️', 'slug' => 'protected-area'],
      ['tid' => 11, 'name' => 'Biodiversity Data', 'field_plural' => 'Biodiversity Data', 'icon' => '📈', 'slug' => 'biodiversity-data'],
      ['tid' => 12, 'name' => 'Document', 'field_plural' => 'Documents', 'icon' => '📋', 'slug' => 'document'],
      ['tid' => 13, 'name' => 'Related Website', 'field_plural' => 'Related Websites', 'icon' => '🔗', 'slug' => 'related-website'],
      ['tid' => 15, 'name' => 'Other', 'field_plural' => 'Others', 'icon' => '⚙️', 'slug' => 'other'],
      ['tid' => 16, 'name' => 'Image or Video', 'field_plural' => 'Images or Videos', 'icon' => '🎬', 'slug' => 'image-or-video'],
      ['tid' => 55, 'name' => 'Other Resource', 'field_plural' => 'Other Resources', 'icon' => '⚙️', 'slug' => 'other-resource'],
      ['tid' => 43, 'name' => 'FAQ', 'field_plural' => 'FAQs', 'icon' => '❓', 'slug' => 'faq'],
      ['tid' => 44, 'name' => 'National Information', 'field_plural' => 'National Informations', 'icon' => '🏴', 'slug' => 'national-information'],
      ['tid' => 45, 'name' => 'Status of LMO', 'field_plural' => 'Status of LMOs', 'icon' => '🧬', 'slug' => 'status-of-lmo'],
      ['tid' => 46, 'name' => 'Field Trial', 'field_plural' => 'Field Trials', 'icon' => '🌱', 'slug' => 'field-trial'],
      ['tid' => 47, 'name' => 'National Mainstreaming Strategy', 'field_plural' => 'National Mainstreaming Strategies', 'icon' => '🗂️', 'slug' => 'national-mainstreaming-strategy'],
      ['tid' => 48, 'name' => 'Capacity Building', 'field_plural' => 'Capacity Building', 'icon' => '🔧', 'slug' => 'capacity-building'],
      ['tid' => 49, 'name' => 'Announcement', 'field_plural' => 'Announcements', 'icon' => '📢', 'slug' => 'announcement'],
      ['tid' => 50, 'name' => 'Contact', 'field_plural' => 'Contacts', 'icon' => '📞', 'slug' => 'contact'],
    ];
  }

  /**
   * Get content type TIDs that are in use in menus.
   *
   * Scans all menus (except excluded ones) for bl2-content-type-* classes
   * and returns the TIDs of content types that are referenced.
   *
   * @return array
   *   Array of taxonomy term IDs that are in use in menus.
   */
  protected function getContentTypeTidsInUse() {
    $config = $this->config('bioland.settings');
    $enable_debug = $config->get('enable_debug_logging') ?: FALSE;
    
    // Check if we already have saved visible content type menus
    // $saved_tids = $config->get('mega_menu.content_type_menus.visible_content_type_menus');
    // if (!empty($saved_tids) && is_array($saved_tids)) {
    //   if ($enable_debug) {
    //     \Drupal::logger('bioland')->debug('Mega Menu: Using saved visible content type TIDs: @tids', [
    //       '@tids' => implode(', ', $saved_tids),
    //     ]);
    //   }
    //   return $saved_tids;
    // }
    
    $tids_in_use = [];
    $excluded_menus = ['admin', 'footer', 'account', 'tools', 'footer-credits', 'main'];
    
    // Get content type reference for slug-to-tid mapping
    $content_type_ref = self::getContentTypeReference();
    $slug_to_tid = [];
    foreach ($content_type_ref as $ct) {
      $slug_to_tid[$ct['slug']] = $ct['tid'];
    }

    try {
      // Load all menus
      $menu_storage = $this->entityTypeManager->getStorage('menu');
      $menus = $menu_storage->loadMultiple();

      if ($enable_debug) {
        \Drupal::logger('bioland')->debug('Mega Menu: Scanning menus for content type classes');
      }

      foreach ($menus as $menu) {
        $menu_id = $menu->id();
        
        // Skip excluded menus
        if (in_array($menu_id, $excluded_menus)) {
          continue;
        }

        // Load ALL menu items from database for this menu to scan all levels
        $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
        $menu_links = $menu_link_storage->loadByProperties([
          'menu_name' => $menu_id,
          'enabled' => 1,
        ]);
        
       // if ($enable_debug) {
          \Drupal::logger('bioland')->debug('Mega Menu: Scanning menu @menu with @count links', [
            '@menu' => $menu_id,
            '@count' => count($menu_links),
          ]);
      //  }
        
        // Process each menu item
        foreach ($menu_links as $menu_link) {
          // Get link options which contain attributes
          $link_field = $menu_link->get('link');
          if ($link_field->isEmpty()) {
            continue;
          }
          
          $link_item = $link_field->first();
          if (!$link_item) {
            continue;
          }
          

          // Get options - it may be stored as 'options' property or in the value array
          $options = [];
          $link_value = $link_item->getValue();

           \Drupal::logger('bioland')->debug('Mega Menu link @menu', [
                '@menu' => print_r($link_value, TRUE)
          ]);
          if (isset($link_value['options'])) {
            $options = $link_value['options'];
          }
          \Drupal::logger('bioland')->debug('Mega Menu options: @menu', [
                '@menu' => print_r($link_value['options'], TRUE)
          ]);
          // Check for classes in attributes
          if (isset($options['attributes']['class'])) {
            $classes = is_array($options['attributes']['class']) 
              ? $options['attributes']['class'] 
              : explode(' ', $options['attributes']['class']);
            
            if ($enable_debug && !empty($classes)) {
              \Drupal::logger('bioland')->debug('Mega Menu: Found classes in menu @menu item "@title": @classes', [
                '@menu' => $menu_id,
                '@title' => $menu_link->label(),
                '@classes' => implode(', ', $classes),
              ]);
            }
                            
                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: classes raw @class', [
                    '@class' => implode(', ', $classes)
                    ]);
                  }
            // Extract bl2-content-type-* classes
            foreach ($classes as $class) {
                              

              if (strpos($class, 'bl2-content-type-') === 0) {
                // Remove prefix to get slug
                $slug = substr($class, strlen('bl2-content-type-'));
                                            
                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: class @class', [
                    '@clas' => $class
                    ]);
                  }
                if ($enable_debug) {
                  \Drupal::logger('bioland')->debug('Mega Menu: Found bl2-content-type class: @class (slug: @slug) in item "@title"', [
                    '@class' => $class,
                    '@slug' => $slug,
                    '@title' => $menu_link->label(),
                  ]);
                }
                
                // Match to TID
                if (isset($slug_to_tid[$slug])) {
                  $tids_in_use[] = $slug_to_tid[$slug];
                  if ($enable_debug) {
                    \Drupal::logger('bioland')->debug('Mega Menu: Matched slug @slug to TID @tid', [
                      '@slug' => $slug,
                      '@tid' => $slug_to_tid[$slug],
                    ]);
                  }
                } else {
                  if ($enable_debug) {
                    \Drupal::logger('bioland')->debug('Mega Menu: No TID match found for slug: @slug', [
                      '@slug' => $slug,
                    ]);
                  }
                }
              }
            }
          }
        }
      }
      
      // Return unique TIDs
      $unique_tids = array_unique($tids_in_use);
      
      if ($enable_debug) {
        \Drupal::logger('bioland')->debug('Mega Menu: Final visible content type TIDs: @tids', [
          '@tids' => implode(', ', $unique_tids),
        ]);
      }
      
      // Save the visible TIDs to config for future use
      \Drupal::configFactory()->getEditable('bioland.settings')
        ->set('mega_menu.content_type_menus.visible_content_type_menus', array_values($unique_tids))
        ->save();
      
      return $unique_tids;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to scan menus for content types: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Handle Main Menu Lock permission changes.
   *
   * @param bool $lock_enabled
   *   TRUE if main menu lock is enabled, FALSE if disabled.
   */
  protected function handleMainMenuLockPermissions($lock_enabled) {
    $permission = 'administer main menu items';
    $roles_to_manage = ['scbd_staff', 'site_manager'];

    try {
      $role_storage = $this->entityTypeManager->getStorage('user_role');

      foreach ($roles_to_manage as $role_id) {
        /** @var \Drupal\user\RoleInterface $role */
        $role = $role_storage->load($role_id);
        
        if ($role) {
          if ($lock_enabled) {
            // Lock enabled: Remove permission
            if ($role->hasPermission($permission)) {
              $role->revokePermission($permission);
              $role->save();
              \Drupal::logger('bioland')->info('Removed "@permission" permission from @role role.', [
                '@permission' => $permission,
                '@role' => $role_id,
              ]);
            }
          }
          else {
            // Lock disabled: Grant permission
            if (!$role->hasPermission($permission)) {
              $role->grantPermission($permission);
              $role->save();
              \Drupal::logger('bioland')->info('Granted "@permission" permission to @role role.', [
                '@permission' => $permission,
                '@role' => $role_id,
              ]);
            }
          }
        }
        else {
          \Drupal::logger('bioland')->warning('Role @role not found when managing main menu lock permissions.', [
            '@role' => $role_id,
          ]);
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to manage main menu lock permissions: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while updating menu permissions. Please check the logs.'));
    }
  }

  /**
   * Resets promoted flag to 0 for all content nodes.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllPromotedFlags() {
    try {
      // Use direct database update for efficiency.
      $count = $this->database->update('node_field_data')
        ->fields(['promoted' => 0])
        ->condition('type', 'content')
        ->condition('promoted', 1)
        ->execute();

      \Drupal::logger('bioland')->info('Reset promoted flag for @count content entries.', ['@count' => $count]);
      return $count;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to reset promoted flags: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while resetting promoted flags. Please check the logs.'));
      return 0;
    }
  }

  /**
   * Resets sticky flag to 0 for all content nodes.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllStickyFlags() {
    try {
      // Use direct database update for efficiency.
      $count = $this->database->update('node_field_data')
        ->fields(['sticky' => 0])
        ->condition('type', 'content')
        ->condition('sticky', 1)
        ->execute();

      \Drupal::logger('bioland')->info('Reset sticky flag for @count content entries.', ['@count' => $count]);
      return $count;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to reset sticky flags: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while resetting sticky flags. Please check the logs.'));
      return 0;
    }
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

  /**
   * Builds the home-page hero help/intro markup for the current site flavor.
   *
   * The copy differs by site flavor. Biosafety Land (BSL) sites - detected via
   * the persisted bioland.settings.is_biosafety_land flag (the same flag
   * getBrandingName() reads; it is written by BiolandDmsmConfigService when the
   * DMSM multiSiteCode is 'bsl') - show a single-hero variant sourced from the
   * home_hero_help_bsl_heading / home_hero_help_bsl_text config properties. All
   * other (BL2) sites keep the original rotating-hero copy.
   *
   * Like getBrandingName(), the source strings are wrapped in $this->t() so the
   * translations/bioland.<langcode>.po catalogs apply. The config properties
   * hold the canonical English (source) strings that are used verbatim as the
   * t() msgid; a matching msgid/msgstr pair is shipped in every .po file.
   *
   * Caveat: editing the home_hero_help_bsl_* config replaces the t() source
   * string, so an edited value has no matching .po msgid and renders untranslated
   * for every locale. The BSL branch output is also run through Xss::filterAdmin()
   * as defense-in-depth, since the config-sourced strings become dynamic t()
   * msgids concatenated into raw markup.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The bioland.settings configuration.
   *
   * @return string
   *   The rendered HTML markup for the hero description block.
   */
  protected function buildHeroDescriptionMarkup($config) {
    if ($config->get('is_biosafety_land')) {
      // Biosafety Land variant. Config holds the canonical source strings.
      // Use ?? (not ?:) so only an unset/NULL key falls back to the seeded
      // default: the fallback guards existing sites where the update hook has
      // not yet written the key, not an admin who intentionally blanks the copy.
      $heading = $config->get('help_comments.home_hero_help_bsl_heading')
        ?? 'About Home Page Heroe';
      $body = $config->get('help_comments.home_hero_help_bsl_text')
        ?? 'Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.';

      // Defense-in-depth: the config-sourced strings become dynamic t() msgids
      // concatenated into raw markup, so filter the assembled output through
      // Xss::filterAdmin() before it reaches the #value render array.
      return Xss::filterAdmin(
        '<p style="margin: 0 0 10px 0;"><strong>' . $this->t($heading) . '</strong></p>' .
        '<p style="margin: 0;">' . $this->t($body) . '</p>'
      );
    }

    // Default (BL2) variant - unchanged original copy.
    return '<p style="margin: 0 0 10px 0;"><strong>' . $this->t('About Home Page Heroes') . '</strong></p>' .
      '<p style="margin: 0 0 10px 0;">' . $this->t('Heroes are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you configure the hero banners.') . '</p>' .
      '<p style="margin: 0 0 10px 0;">' . $this->t('The heroes rotate automatically every hour, allowing you to display different messages and images throughout the day.') . '</p>' .
      '<p style="margin: 0;">' . $this->t('If you prefer to display only one hero, simply unpublish the other hero(es) using the Edit button below.') . '</p>';
  }

  /**
   * Build hero sections for the Home Hero(s) tab.
   *
   * @param array &$form
   *   The form array to add hero sections to.
   */
  protected function buildHeroSections(array &$form) {
    try {
      // Query field_attachments_target_id from taxonomy_term__field_attachments
      // where bundle = 'system_pages' and entity_id = 20
      $query = $this->database->select('taxonomy_term__field_attachments', 'fa')
        ->fields('fa', ['field_attachments_target_id'])
        ->condition('fa.bundle', 'system_pages')
        ->condition('fa.entity_id', 20);
      $results = $query->execute()->fetchCol();

      if (empty($results)) {
        $form['no_heroes'] = [
          '#markup' => '<p>' . $this->t('No attachments found for taxonomy term 20.') . '</p>',
        ];
        return;
      }

      // Create outer wrapper fieldset
      $form['home_page_heroes'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Home Page Heros'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#attributes' => ['class' => ['bioland-heroes-wrapper']],
      ];

      // Add description explaining how heroes work. The intro copy differs by
      // site flavor: Biosafety Land (BSL) sites show a single-hero variant,
      // all other (BL2) sites keep the original rotating-hero copy.
      $form['home_page_heroes']['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['bioland-heroes-description'], 'style' => 'margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa; border-radius: 4px;'],
        '#value' => $this->buildHeroDescriptionMarkup($this->config('bioland.settings')),
      ];

      // Add global styles
      $form['home_page_heroes']['hero_styles'] = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '
          .bioland-heroes-wrapper {
            background: #f5f5f5;
            padding: 15px;
          }
          .bioland-hero-content {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 15px;
            background: #e8e8e8;
            border-radius: 4px;
          }
          .bioland-hero-image {
            flex: 0 0 200px;
          }
          .bioland-hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
          }
          .bioland-hero-status {
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
          }
          .bioland-hero-status.published {
            background: #d4edda;
            color: #155724;
          }
          .bioland-hero-status.unpublished {
            background: #f8d7da;
            color: #721c24;
          }
          .bioland-hero-description {
            flex: 1;
            padding: 0 15px;
          }
          .bioland-hero-actions {
            flex: 0 0 auto;
            margin-left: auto;
          }
          .bioland-hero-edit-btn {
            padding: 8px 16px;
            background: #0073aa;
            color: white !important;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
          }
          .bioland-hero-edit-btn:hover {
            background: #005177;
            color: white !important;
          }
        ',
      ];

      // Load each media entity by ID
      $media_storage = $this->entityTypeManager->getStorage('media');
      $hero_index = 0;
      
      foreach ($results as $target_id) {
        $entity = $media_storage->load($target_id);
        if (!$entity) {
          continue;
        }

        $hero_index++;
        $title = $entity->label() ?: $this->t('Hero @num', ['@num' => $hero_index]);

        // Create a fieldset for each hero with its title inside the wrapper
        $form['home_page_heroes']['hero_' . $target_id] = [
          '#type' => 'fieldset',
          '#title' => $title,
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        ];

        // Build the content markup
        $content = '<div class="bioland-hero-content">';

        // Image section
        $content .= '<div class="bioland-hero-image">';
        $image_url = NULL;
        
        // Try field_media_image first (common for media entities)
        if ($entity->hasField('field_media_image') && !$entity->get('field_media_image')->isEmpty()) {
          $image = $entity->get('field_media_image')->entity;
          if ($image) {
            $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
          }
        }
        // Try field_image as fallback
        elseif ($entity->hasField('field_image') && !$entity->get('field_image')->isEmpty()) {
          $image = $entity->get('field_image')->entity;
          if ($image) {
            $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
          }
        }

        if ($image_url) {
          $content .= '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($title) . '" />';
        }
        else {
          $content .= '<div style="width: 200px; height: 120px; background: #ccc; display: flex; align-items: center; justify-content: center; border-radius: 4px;">No Image</div>';
        }
        
        // Add published/unpublished status
        $is_published = $entity->isPublished();
        $status_class = $is_published ? 'published' : 'unpublished';
        $status_text = $is_published ? $this->t('Published') : $this->t('Unpublished');
        $content .= '<div class="bioland-hero-status ' . $status_class . '">' . $status_text . '</div>';
        
        $content .= '</div>';

        // Description section
        $content .= '<div class="bioland-hero-description">';
        $description = '';
        
        // Try different field names for description/body
        if ($entity->hasField('field_description') && !$entity->get('field_description')->isEmpty()) {
          $description = $entity->get('field_description')->value;
        }
        elseif ($entity->hasField('field_body') && !$entity->get('field_body')->isEmpty()) {
          $description = $entity->get('field_body')->value;
        }
        elseif ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
          $description = $entity->get('body')->value;
        }

        if ($description) {
          $content .= '<div>' . $description . '</div>';
        }
        else {
          $content .= '<p><em>' . $this->t('No description available') . '</em></p>';
        }
        $content .= '</div>';

        // Edit button section
        $content .= '<div class="bioland-hero-actions">';
        $edit_url = $entity->toUrl('edit-form')->toString();
        $content .= '<a href="' . htmlspecialchars($edit_url) . '" class="bioland-hero-edit-btn">' . $this->t('Edit') . '</a>';
        $content .= '</div>';

        $content .= '</div>'; // Close hero-content

        $form['home_page_heroes']['hero_' . $target_id]['content'] = [
          '#type' => 'inline_template',
          '#template' => '{{ content|raw }}',
          '#context' => ['content' => $content],
        ];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to load heroes: @message', [
        '@message' => $e->getMessage(),
      ]);
      $form['error'] = [
        '#markup' => '<p>' . $this->t('An error occurred while loading heroes. Please check the logs.') . '</p>',
      ];
    }
  }

}
