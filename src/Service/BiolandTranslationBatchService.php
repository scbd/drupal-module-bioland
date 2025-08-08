<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Batch operations for Bioland translations.
 */
class BiolandTranslationBatchService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The translation manager.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationManager
   */
  protected $translationManager;

  /**
   * Constructs a new BiolandTranslationBatchService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\bioland\Service\BiolandTranslationManager $translation_manager
   *   The translation manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, BiolandTranslationManager $translation_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->translationManager = $translation_manager;
  }

  /**
   * Creates a batch for generating translations for existing entities.
   *
   * @param string $entity_type_id
   *   The entity type ID to process.
   * @param array $entity_ids
   *   Optional array of specific entity IDs to process.
   *
   * @return array
   *   The batch definition.
   */
  public function createTranslationBatch($entity_type_id, array $entity_ids = []) {
    $operations = [];

    if (empty($entity_ids)) {
      // Get all translatable entities of this type.
      $entity_ids = $this->getTranslatableEntities($entity_type_id);
    }

    // Create batch operations, processing entities in chunks.
    $chunks = array_chunk($entity_ids, 20);
    foreach ($chunks as $chunk) {
      $operations[] = [
        [static::class, 'processTranslationBatch'],
        [$entity_type_id, $chunk],
      ];
    }

    $batch = [
      'title' => $this->t('Creating translations for @type entities', ['@type' => $entity_type_id]),
      'operations' => $operations,
      'finished' => [static::class, 'finishTranslationBatch'],
      'progressive' => TRUE,
      'file' => \Drupal::service('extension.list.module')->getPath('bioland') . '/src/Service/BiolandTranslationBatchService.php',
    ];

    return $batch;
  }

  /**
   * Batch operation callback for processing translations.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param array $entity_ids
   *   The entity IDs to process.
   * @param array $context
   *   The batch context.
   */
  public static function processTranslationBatch($entity_type_id, array $entity_ids, array &$context) {
    $translation_manager = \Drupal::service('bioland.translation_manager');
    $entity_type_manager = \Drupal::entityTypeManager();

    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
      $context['results']['created'] = 0;
      $context['results']['errors'] = 0;
    }

    $storage = $entity_type_manager->getStorage($entity_type_id);

    foreach ($entity_ids as $entity_id) {
      try {
        $entity = $storage->load($entity_id);
        if ($entity) {
          $created = $translation_manager->createTranslations($entity, 'batch');
          if ($created) {
            $context['results']['created']++;
          }
          $context['results']['processed']++;
        }
      }
      catch (\Exception $e) {
        $context['results']['errors']++;
        \Drupal::logger('bioland')->error('Error processing entity @id: @message', [
          '@id' => $entity_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $context['message'] = t('Processed @processed entities, created @created translations', [
      '@processed' => $context['results']['processed'],
      '@created' => $context['results']['created'],
    ]);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public static function finishTranslationBatch($success, array $results, array $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        $results['processed'],
        'Processed 1 entity, created @created translations.',
        'Processed @count entities, created @created translations.',
        ['@created' => $results['created']]
      );
      \Drupal::messenger()->addMessage($message);

      if ($results['errors'] > 0) {
        \Drupal::messenger()->addWarning(\Drupal::translation()->formatPlural(
          $results['errors'],
          '1 error occurred during processing.',
          '@count errors occurred during processing.'
        ));
      }
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during batch processing.'));
    }
  }

  /**
   * Gets entity IDs that are translatable.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Array of entity IDs.
   */
  protected function getTranslatableEntities($entity_type_id) {
    $entity_ids = [];

    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Check if the entity type itself is translatable
      if ($entity_type->isTranslatable()) {
        $query = $storage->getQuery()
          ->accessCheck(FALSE);

        $entity_ids = $query->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Error getting translatable entities: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return array_values($entity_ids);
  }

}
