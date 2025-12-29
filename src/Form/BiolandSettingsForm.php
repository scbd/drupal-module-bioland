<?php

namespace Drupal\bioland\Form;

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
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, BiolandTranslationBatchService $translation_batch_service, Connection $database, AccountProxyInterface $current_user) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->translationBatchService = $translation_batch_service;
    $this->database = $database;
    $this->currentUser = $current_user;
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
      $container->get('current_user')
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
        '#type' => 'text_format',
        '#title' => $this->t('Slogan'),
        '#default_value' => $site_config->get('slogan'),
        '#format' => 'full_html',
        '#allowed_formats' => ['full_html'],
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
            // Use text_format for slogan, textfield for others
            if ($field_name === 'site_slogan') {
              $form['general']["{$field_name}_translations"][$langcode] = [
                '#type' => 'text_format',
                '#title' => $languages[$langcode]->getName(),
                '#default_value' => $config_override->get($translation_info['config_key']),
                '#format' => 'full_html',
                '#allowed_formats' => ['full_html'],
              ];
            }
            else {
              $form['general']["{$field_name}_translations"][$langcode] = [
                '#type' => 'textfield',
                '#title' => $languages[$langcode]->getName(),
                '#default_value' => $config_override->get($translation_info['config_key']),
              ];
            }
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

      // Promote and Sticky section
      $form['system_functions']['promote_sticky_section'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Promote and Sticky'),
        '#collapsible' => FALSE,
      ];

      $form['system_functions']['promote_sticky_section']['promote_sticky_description'] = [
        '#markup' => '<p>' . $this->t('Promote and Sticky flags on records can be seen by public or anonymous users.') . '</p>',
      ];

      $form['system_functions']['promote_sticky_section']['promote_and_sticky_public'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Promote and Sticky flags to public users'),
        '#default_value' => $config->get('config.promote_and_sticky_public') !== FALSE,
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

      // Reset Ordering section
      $form['system_functions']['ordering_section'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Content Ordering Reset'),
        '#collapsible' => FALSE,
      ];

      $form['system_functions']['ordering_section']['ordering_description'] = [
        '#markup' => '<p>' . $this->t('Resets the field_order value to 10000 for all nodes. This creates a new revision for each node with the revision message "Reset ordering". The original last updated date and user are preserved. Use this to normalize content ordering across the site.') . '</p>',
      ];

      $form['system_functions']['ordering_section']['reset_ordering'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset Ordering'),
        '#submit' => ['::submitResetOrdering'],
        '#ajax' => [
          'callback' => '::ajaxResetOrdering',
          'wrapper' => 'system-functions-messages',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Resetting ordering values...'),
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

      $form['field_visibility']['enable_field_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Field Visibility Control'),
        '#description' => $this->t('Show/hide fields based on content type selection. Configure which fields are visible for each content type below.'),
        '#default_value' => $config->get('enable_field_visibility') !== FALSE,
        '#suffix' => '<a href="#" class="bioland-toggle-visibility-settings" data-target="field-visibility-settings">Show more</a>',
      ];

      // Container for all field visibility settings
      $form['field_visibility']['settings_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bioland-field-visibility-settings', 'bioland-collapsible-hidden']],
      ];

      // Get content types from taxonomy vocabulary 'tags'.
      $content_types = $this->getContentTypeOptions();

      // URL Field visibility settings
      $form['field_visibility']['settings_container']['url_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('URL Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['settings_container']['url_field']['url_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show URL field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.url_content_types') ?: [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50],
      ];

      // Published Field visibility settings
      $form['field_visibility']['settings_container']['published_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Published Field'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['settings_container']['published_field']['published_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Published field for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.published_content_types') ?: [3, 5, 12],
      ];

      // Date Range Field visibility settings
      $form['field_visibility']['settings_container']['date_range_field'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Date Range Field (Start & End Date)'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['field_visibility']['settings_container']['date_range_field']['date_range_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Date Range fields (Start & End Date) for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('field_visibility.date_range_content_types') ?: [2, 3, 13],
      ];
    }

    if ($section === 'tags') {
      $form['tags_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Tags Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      // Additional Tags functionality - wrapped in fieldset
      $form['tags_settings']['additional_tags'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Additional Tags Control'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['tags_settings']['additional_tags']['enable_additional_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Additional Tags'),
        '#description' => $this->t('Add content-type specific additional tags (event statuses, project statuses, etc.) based on thesaurus content type.'),
        '#default_value' => $config->get('enable_additional_fields') !== FALSE,
      ];

      // Get content type options for tag configuration
      $content_types = $this->getContentTypeOptions();

      // Event Status tag settings
      $form['tags_settings']['additional_tags']['event_status'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Event Status'),
        '#description' => $this->t('Content types that will have Event Status tags available.'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['additional_tags']['event_status']['event_status_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Event Status tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.event_status_content_types') ?: [3],
      ];

      // Project Status tag settings
      $form['tags_settings']['additional_tags']['project_status'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Project Status'),
        '#description' => $this->t('Content types that will have Project Status and Geographic Scope tags available.'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['additional_tags']['project_status']['project_status_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Project Status tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.project_status_content_types') ?: [5],
      ];

      // Organization Types tag settings
      $form['tags_settings']['additional_tags']['organization_types'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Organization Types'),
        '#description' => $this->t('Content types that will have Organization Types and Government Types tags available.'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['additional_tags']['organization_types']['organization_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Organization Types tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.organization_types_content_types') ?: [8],
      ];

      // Ecosystem Types tag settings
      $form['tags_settings']['additional_tags']['ecosystem_types'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Ecosystem Types'),
        '#description' => $this->t('Content types that will have Ecosystem Types tags available.'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['additional_tags']['ecosystem_types']['ecosystem_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Ecosystem Types tags for these content types:'),
        '#options' => $content_types,
        '#default_value' => $config->get('additional_tags.ecosystem_types_content_types') ?: [9],
      ];

      // Document Types tag settings
      $form['tags_settings']['additional_tags']['document_types'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Document Types'),
        '#description' => $this->t('Content types that will have Document Types tags available.'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['tags_settings']['additional_tags']['document_types']['document_types_content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Show Document Types tags for these content types:'),
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
        '#type' => 'fieldset',
        '#title' => $this->t('Promotion Options Help'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
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
        '#type' => 'fieldset',
        '#title' => $this->t('Order Override Help'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="enable_help_comments"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $default_order_override_text = $this->t('<b>Content sorting priority (highest to lowest):</b>
<ol>
<li><b>Sticky</b> – Items marked "Sticky at top of lists" always appear first</li>
<li><b>Order Override</b> – Lower numbers appear before higher numbers (e.g., 10 appears before 20)</li>
<li><b>Promoted</b> – Items marked "Promoted to front page" come next</li>
<li><b>Start Date</b> – Then sorted by start date (or published date if no start date)</li>
<li><b>Last Modified</b> – Finally sorted by most recently updated</li>
</ol>
<b>Tip:</b> Leave empty to use default sorting. Use increments of 10 (e.g., 10, 20, 30) to leave room for inserting items later.');

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

    if ($section === 'configuration') {
      // Redirect to the first sub-tab (General) when accessing the parent Configuration tab
      $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
        \Drupal\Core\Url::fromRoute('bioland.settings.configuration.general')->toString()
      );
      $response->send();
      exit;
    }

    if ($section === 'configuration_general') {
      $form['configuration_general_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('General Configuration'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['configuration_general_settings']['placeholder'] = [
        '#markup' => '<p>' . $this->t('General configuration settings coming soon.') . '</p>',
      ];
    }

    if ($section === 'configuration_mega_menu') {
      $form['mega_menu_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Mega Menu Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['mega_menu_settings']['placeholder'] = [
        '#markup' => '<p>' . $this->t('Mega Menu settings coming soon.') . '</p>',
      ];
    }

    if ($section === 'configuration_home_page') {
      $form['home_page_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Home Page Settings'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];

      $form['home_page_settings']['placeholder'] = [
        '#markup' => '<p>' . $this->t('Home Page settings coming soon.') . '</p>',
      ];
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
      // Extract slogan value from text_format array
      $slogan_value = is_array($values['site_slogan']) ? ($values['site_slogan']['value'] ?? '') : $values['site_slogan'];
      if ($site_config->get('slogan') !== $slogan_value) {
        $site_config->set('slogan', $slogan_value);
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
            $field_value = $values[$form_key][$langcode] ?? '';
            // Extract value from text_format array for slogan field
            if ($config_key === 'slogan' && is_array($field_value)) {
              $new_value = $field_value['value'] ?? '';
            }
            else {
              $new_value = $field_value;
            }
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
      $config
        ->set('enable_auto_summary', $values['enable_auto_summary'])
        ->set('is_biosafety_land', $values['is_biosafety_land'])
        ->set('enable_debug_logging', $values['enable_debug_logging'])
        ->set('debug_log_areas.field_visibility', $values['debug_log_field_visibility'])
        ->set('debug_log_areas.additional_fields', $values['debug_log_additional_fields'])
        ->set('debug_log_areas.auto_summary', $values['debug_log_auto_summary'])
        ->set('debug_log_areas.help_comments', $values['debug_log_help_comments']);
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

    if ($section === 'system_functions') {
      $config
        ->set('config.promote_and_sticky_public', (bool) $values['promote_and_sticky_public']);
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
