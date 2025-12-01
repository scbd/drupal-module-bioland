<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Drupal Module Bioland service for managing Translation Defaults of entities
 * with SCBD fields.
 */
class BiolandTranslationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BiolandTranslationManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
  * Creates translation defaults for an entity if configured to do so.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to create translations for.
   * @param string $operation
   *   The operation being performed (insert, update, etc.).
   *
   * @return bool
   *   TRUE if translations were created, FALSE otherwise.
   */
  public function createTranslations(ContentEntityInterface $entity, $operation = 'insert') {
    // Prevent recursive calls when saving translations
    static $processing = [];
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();
    
    if (isset($processing[$entity_key])) {
      return FALSE;
    }
    
    $processing[$entity_key] = TRUE;
    
    try {
      $result = $this->doCreateTranslations($entity, $operation);
      unset($processing[$entity_key]);
      return $result;
    }
    catch (\Exception $e) {
      unset($processing[$entity_key]);
      throw $e;
    }
  }

  /**
   * Internal method to create translation defaults.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to create translations for.
   * @param string $operation
   *   The operation being performed (insert, update, etc.).
   *
   * @return bool
   *   TRUE if translations were created, FALSE otherwise.
   */
  protected function doCreateTranslations(ContentEntityInterface $entity, $operation = 'insert') {
    $config = $this->configFactory->get('bioland.settings');

    $this->loggerFactory->get('bioland')->debug('createTranslations called for entity type @type, id @id, operation @op', [
      '@type' => $entity->getEntityTypeId(),
      '@id' => $entity->id(),
      '@op' => $operation,
    ]);

  // Check if translation defaults creation is enabled
    $auto_create = $config->get('translation.auto_create');
    if (!$auto_create) {
      $this->loggerFactory->get('bioland')->debug('Auto-create is disabled');
      return FALSE;
    }

    // Check if this entity type should be auto-translated
    $enabled_types = $config->get('translation.entity_types');
    
    // Handle empty configuration - provide helpful warning
    if (empty($enabled_types)) {
      $this->loggerFactory->get('bioland')->warning(
        'Translation defaults are enabled but no entity types are configured. Please visit /admin/config/bioland/settings and select at least one entity type, or run "drush updatedb" to apply configuration updates.'
      );
      return FALSE;
    }
    
    $this->loggerFactory->get('bioland')->debug('Enabled entity types: @types', [
      '@types' => print_r($enabled_types, TRUE),
    ]);
    
    if (!in_array($entity->getEntityTypeId(), $enabled_types)) {
      $this->loggerFactory->get('bioland')->debug('Entity type @type not in enabled types: @types', [
        '@type' => $entity->getEntityTypeId(),
        '@types' => print_r($enabled_types, TRUE),
      ]);
      return FALSE;
    }

    // Only process translatable entities
    if (!$entity->isTranslatable()) {
      return FALSE;
    }

    // Always work with the source entity to avoid title concatenation issues
    $source_entity = $entity->getUntranslated();

  // Determine target languages based on configuration
    $use_all_languages = $config->get('translation.use_all_languages') ?: FALSE;
    $configured_target_languages = $config->get('translation.target_languages') ?: [];

    if ($use_all_languages) {
      $available_languages = $this->languageManager->getLanguages();
      $target_languages = array_keys($available_languages);
    } else {
      $target_languages = $configured_target_languages;
    }

    $copy_source_values = $config->get('translation.copy_source_values') ?: FALSE;
    $source_language = $source_entity->language();
    $created_count = 0;

    foreach ($target_languages as $langcode) {
      // Skip if it's the same as source language
      if ($langcode === $source_language->getId()) {
        $this->loggerFactory->get('bioland')->debug('Skipping source language: @lang', ['@lang' => $langcode]);
        continue;
      }

      // Check if the language exists
      $language = $this->languageManager->getLanguage($langcode);
      if (!$language) {
        $this->loggerFactory->get('bioland')->warning('Language @lang not found on site', ['@lang' => $langcode]);
        continue;
      }

      // If a translation exists and its source is proper (not 'und'), do not overwrite.
      if ($source_entity->hasTranslation($langcode)) {
        $existing_translation = $source_entity->getTranslation($langcode);
        $translation_source_language = $existing_translation->getUntranslated()->language()->getId();
        if ($translation_source_language !== 'und') {
          $this->loggerFactory->get('bioland')->debug('Skipping @lang; existing translation source is @source (proper)', [
            '@lang' => $langcode,
            '@source' => $translation_source_language,
          ]);
          continue;
        }
      }

      $this->loggerFactory->get('bioland')->debug('Creating translation for @lang', ['@lang' => $langcode]);

      try {
        // Create the translation using the source entity
        $translation_values = $this->prepareTranslationValues($source_entity, $langcode, $copy_source_values);

        // Debug: Log the title being set
        if (isset($translation_values['title'])) {
          $this->loggerFactory->get('bioland')->debug('Setting original title for @lang: @title', [
            '@lang' => $langcode,
            '@title' => $translation_values['title']
          ]);
        }

        // Debug: Log if body is being copied
        if (isset($translation_values['body'])) {
          $this->loggerFactory->get('bioland')->debug('Copying body content for @lang', ['@lang' => $langcode]);
        }

        $translation = $source_entity->addTranslation($langcode, $translation_values);

        // Mark newly created translations as outdated relative to the source content.
        // This ensures editors can clearly see that the auto-generated translation
        // still needs manual review and update.
        if ($translation->hasField('content_translation_outdated')) {
          $translation->set('content_translation_outdated', 1);
        }

        $created_count++;

        $this->loggerFactory->get('bioland')->info(
          'Created translation default for @langcode on @entity_type @entity_id',
          [
            '@langcode' => $langcode,
            '@entity_type' => $source_entity->getEntityTypeId(),
            '@entity_id' => $source_entity->id(),
          ]
        );
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('bioland')->error(
          'Failed to create @langcode translation for @entity_type @entity_id: @message',
          [
            '@langcode' => $langcode,
            '@entity_type' => $source_entity->getEntityTypeId(),
            '@entity_id' => $source_entity->id(),
            '@message' => $e->getMessage(),
          ]
        );
      }
    }

    // Save the entity once after all translations are added
    if ($created_count > 0) {
      try {
        $source_entity->save();
        $this->loggerFactory->get('bioland')->info(
          'Saved @entity_type @entity_id with @count new translations',
          [
            '@entity_type' => $source_entity->getEntityTypeId(),
            '@entity_id' => $source_entity->id(),
            '@count' => $created_count,
          ]
        );
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('bioland')->error(
          'Failed to save entity with translations: @message',
          ['@message' => $e->getMessage()]
        );
        return FALSE;
      }
    }

    return $created_count > 0;
  }

  /**
   * Checks if an entity has SCBD thesaurus fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity has SCBD fields, FALSE otherwise.
   */
  protected function entityHasScbdFields(ContentEntityInterface $entity) {
    foreach ($entity->getFieldDefinitions() as $field_name => $field_definition) {
      if ($field_definition->getType() === 'scbd_field_thesaurus') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Prepares translation values for a new translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity.
   * @param string $langcode
   *   The target language code.
   * @param bool $copy_source_values
   *   Whether to copy source field values.
   *
   * @return array
   *   The translation values.
   */
  protected function prepareTranslationValues(ContentEntityInterface $entity, $langcode, $copy_source_values) {
    $values = [];

    // Get the original source entity (not a translation)
    $source_entity = $entity->getUntranslated();

    // Copy the title/label exactly as is from the original source (no language suffix)
    $label_field = $entity->getEntityType()->getKey('label');
    if ($label_field && $source_entity->hasField($label_field)) {
      $original_title = $source_entity->get($label_field)->value;
      $values[$label_field] = $original_title; // Keep original title without modification
    }

    // Copy the publication status from the source
    if ($source_entity->hasField('status')) {
      $values['status'] = $source_entity->get('status')->value;
    }

    // Always copy the body field if it exists
    if ($source_entity->hasField('body')) {
      $body_value = $source_entity->get('body')->getValue();
      if (!empty($body_value)) {
        $values['body'] = $body_value;
      }
    }

    // If configured, copy all translatable field values from the source
    if ($copy_source_values) {
      foreach ($source_entity->getFieldDefinitions() as $field_name => $field_definition) {
        // Skip base fields that are already handled above
        if (in_array($field_name, [$label_field, 'status', 'body'])) {
          continue;
        }

        // Copy translatable fields
        if ($field_definition->isTranslatable()) {
          $field_value = $source_entity->get($field_name)->getValue();
          if (!empty($field_value)) {
            $values[$field_name] = $field_value;
          }
        }
      }
    }

    return $values;
  }

  /**
   * Gets the list of configured target languages.
   *
   * @return array
   *   Array of language codes.
   */
  public function getTargetLanguages() {
    $config = $this->configFactory->get('bioland.settings');
    $use_all_languages = $config->get('translation.use_all_languages') ?: FALSE;

    if ($use_all_languages) {
      $available_languages = $this->languageManager->getLanguages();
      return array_keys($available_languages);
    } else {
      return $config->get('translation.target_languages') ?: [];
    }
  }

  /**
   * Checks if auto-translation is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isAutoTranslationEnabled() {
    $config = $this->configFactory->get('bioland.settings');
    return $config->get('translation.auto_create') ?: FALSE;
  }

  /**
   * Debug helper method to log messages.
   *
   * @param string $message
   *   The debug message.
   * @param array $context
   *   Additional context for the message.
   */
  protected function debug($message, array $context = []) {
    // Only log debug messages if debugging is enabled
    $config = $this->configFactory->get('bioland.settings');
    if ($config->get('debug_enabled')) {
      $this->loggerFactory->get('bioland')->debug($message, $context);
    }
  }

}
