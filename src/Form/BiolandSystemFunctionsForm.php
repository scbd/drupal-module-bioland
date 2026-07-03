<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Configure System Functions settings for the Bioland module.
 */
class BiolandSystemFunctionsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_system_functions_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'system_functions';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
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

    $languages = $this->getFilteredLanguages();
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate translation settings that are now in system_functions
    $auto_create = $form_state->getValue('auto_create');
    $entity_types = array_filter($form_state->getValue('entity_types') ?: []);

    // If auto-create is enabled but no entity types are selected, show a warning
    if ($auto_create && empty($entity_types)) {
      $form_state->setErrorByName('entity_types', $this->t('You must select at least one entity type for automatic translation defaults to work. Please select one or more entity types, or disable "Automatically create translation defaults".'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

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

}
