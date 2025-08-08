<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Bioland settings.
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
   * Constructs a new BiolandSettingsForm.
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bioland.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['general']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Country'),
      '#description' => $this->t('Select the default country for this site.'),
      '#options' => $this->getCountryOptions(),
      '#default_value' => $config->get('country') ?: 'us',
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

    $form['localization'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Localization Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['localization']['default_locale'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Locale'),
      '#description' => $this->t('Select the default locale for this site.'),
      '#options' => $this->getLocaleOptions(),
      '#default_value' => $config->get('default_locale') ?: 'en',
      '#required' => TRUE,
    ];

    $form['localization']['enabled_locales'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled Locales'),
      '#description' => $this->t('Select which locales should be available on this site.'),
      '#options' => $this->getLocaleOptions(),
      '#default_value' => $config->get('enabled_locales') ?: ['en'],
    ];

    $form['field_behavior'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field Behavior Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Field Visibility functionality
    $form['field_behavior']['enable_field_visibility'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Field Visibility Control'),
      '#description' => $this->t('Show/hide fields based on content type selection. Controls URL, date, and published fields visibility.'),
      '#default_value' => $config->get('enable_field_visibility') !== FALSE,
    ];

    // Additional Fields functionality
    $form['field_behavior']['enable_additional_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Additional Fields'),
      '#description' => $this->t('Add content-type specific additional fields (event statuses, project statuses, etc.) based on thesaurus content type.'),
      '#default_value' => $config->get('enable_additional_fields') !== FALSE,
    ];

    // Auto Summary functionality
    $form['field_behavior']['enable_auto_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Auto Summary'),
      '#description' => $this->t('Automatically generate summary from body content as user types.'),
      '#default_value' => $config->get('enable_auto_summary') !== FALSE,
    ];

    // Legacy support for existing setting
    $form['field_behavior']['enable_dynamic_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Dynamic Fields (Legacy)'),
      '#description' => $this->t('Legacy setting - use individual settings above instead.'),
      '#default_value' => $config->get('enable_dynamic_fields') !== FALSE,
    ];

    $form['field_behavior']['field_visibility_rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field Visibility Rules'),
      '#description' => $this->t('Define custom field visibility rules (JSON format).'),
      '#default_value' => $config->get('field_visibility_rules') ?: '',
      '#states' => [
        'visible' => [
          ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['translation'] = [
      '#type' => 'details',
      '#title' => $this->t('Translation Settings'),
      '#description' => $this->t('Configure automatic translation creation for translatable entities.'),
      '#open' => FALSE,
    ];

    $form['translation']['auto_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create translations'),
      '#description' => $this->t('When enabled, translations will be automatically created for translatable entities.'),
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
    $language_options = [];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }

    $form['translation']['target_languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Target languages'),
      '#description' => $this->t('Select which languages to create translations for. This is only used when "Use all available languages" is disabled.'),
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
      '#default_value' => $config->get('translation.copy_source_values') ?: FALSE,
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
      '#title' => $this->t('Entity types'),
      '#description' => $this->t('Select which entity types should have automatic translations created.'),
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
      '#description' => $this->t('Process existing entities to create missing translations.'),
      '#open' => FALSE,
    ];

    $form['translation']['batch_operations']['batch_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type to process'),
      '#options' => ['' => $this->t('- Select -')] + $content_entity_options,
      '#description' => $this->t('Select an entity type to create translations for existing entities.'),
    ];

    $form['translation']['batch_operations']['run_batch'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create translations for existing entities'),
      '#submit' => ['::submitBatchForm'],
      '#states' => [
        'visible' => [
          ':input[name="batch_entity_type"]' => ['!value' => ''],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Filter out empty locales
    $enabled_locales = array_filter($values['enabled_locales']);
    $target_languages = array_filter($values['target_languages']);
    $entity_types = array_filter($values['entity_types']);

    $this->config('bioland.settings')
      ->set('country', $values['country'])
      ->set('region', $values['region'])
      ->set('default_locale', $values['default_locale'])
      ->set('enabled_locales', $enabled_locales)
      ->set('enable_field_visibility', $values['enable_field_visibility'])
      ->set('enable_additional_fields', $values['enable_additional_fields'])
      ->set('enable_auto_summary', $values['enable_auto_summary'])
      ->set('enable_dynamic_fields', $values['enable_dynamic_fields'])
      ->set('field_visibility_rules', $values['field_visibility_rules'])
      ->set('translation.auto_create', $values['auto_create'])
      ->set('translation.use_all_languages', $values['use_all_languages'])
      ->set('translation.target_languages', array_values($target_languages))
      ->set('translation.copy_source_values', $values['copy_source_values'])
      ->set('translation.entity_types', array_values($entity_types))
      ->save();

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
  protected function getCountryOptions() {
    return [
      'us' => $this->t('United States'),
      'ca' => $this->t('Canada'),
      'mx' => $this->t('Mexico'),
      'br' => $this->t('Brazil'),
      'ar' => $this->t('Argentina'),
      'uk' => $this->t('United Kingdom'),
      'de' => $this->t('Germany'),
      'fr' => $this->t('France'),
      'es' => $this->t('Spain'),
      'it' => $this->t('Italy'),
      'nl' => $this->t('Netherlands'),
      'be' => $this->t('Belgium'),
      'ch' => $this->t('Switzerland'),
      'au' => $this->t('Australia'),
      'nz' => $this->t('New Zealand'),
      'jp' => $this->t('Japan'),
      'cn' => $this->t('China'),
      'in' => $this->t('India'),
      'za' => $this->t('South Africa'),
      'eg' => $this->t('Egypt'),
    ];
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
