<?php

namespace Drupal\bioland\Service;

use Drupal\bioland\BiolandUrlScreenshotPolicy;
use Drupal\bioland\ConvertApi\BiolandConvertApiClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\Entity\Media;

/**
 * BL-1191: auto screenshot image for "Related websites" nodes.
 *
 * Tagged needs_destruction (bioland.services.yml) so core's kernel destruct
 * subscriber calls destruct() after the editor's response is already flushed;
 * the queue (bioland_url_screenshot) is the retry path only, drained by
 * BiolandUrlScreenshotWorker for items that failed transiently or whose
 * request ended before destruct() ran (CLI, fatal errors).
 *
 * Known constraint: destruct() deletes a successfully-processed queue item
 * with `$queue->deleteItem((object) ['item_id' => $id])`, a shape that works
 * for core's default DatabaseQueue/Memory backends but is not the full
 * claimed-item object some other queue backends require. This module only
 * ever uses core's default queue, so this is acceptable as-is.
 */
class BiolandUrlScreenshotService implements DestructableInterface {

  const QUEUE_NAME = 'bioland_url_screenshot';

  /**
   * Queue items recorded during this request, keyed by a dedupe key.
   *
   * @var array<string, array{id: int, item: array}>
   */
  protected array $pending = [];

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;
  protected FileRepositoryInterface $fileRepository;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected QueueFactory $queueFactory;
  protected ConfigFactoryInterface $configFactory;
  protected StateInterface $state;

  /**
   * @var callable(string): BiolandConvertApiClient
   */
  protected $adapterFactory;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem,
    FileRepositoryInterface $fileRepository,
    LoggerChannelFactoryInterface $loggerFactory,
    QueueFactory $queueFactory,
    ConfigFactoryInterface $configFactory,
    StateInterface $state,
    ?callable $adapterFactory = NULL
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
    $this->fileRepository = $fileRepository;
    $this->loggerFactory = $loggerFactory;
    $this->queueFactory = $queueFactory;
    $this->configFactory = $configFactory;
    $this->state = $state;
    $this->adapterFactory = $adapterFactory ?: static function (string $secret): BiolandConvertApiClient {
      return new BiolandConvertApiClient($secret);
    };
  }

  /**
   * Enqueues a website screenshot for the entity, if it qualifies.
   */
  public function enqueueIfNeeded(ContentEntityInterface $entity, string $op): void {
    if (!empty($entity->bioland_screenshot_saving)) {
      // The worker's own save re-entering hook_entity_update(); never re-enqueue.
      return;
    }
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }

    $config = $this->configFactory->get('bioland.settings');
    $enabled = $config->get('enable_url_screenshot') !== FALSE;
    $secret = BiolandUrlScreenshotPolicy::resolveSecret([Settings::class, 'get']);

    $tid = $this->fieldTargetId($entity, 'field_type_placement');
    $url = $this->fieldValue($entity, 'field_url', 'uri');

    if (!BiolandUrlScreenshotPolicy::qualifies($entity->bundle(), $tid, $url, $enabled, $secret !== '')) {
      return;
    }

    if ($op === 'update') {
      $original = $entity->original ?? NULL;
      $originalUri = $original ? $this->fieldValue($original, 'field_url', 'uri') : NULL;
      if (!BiolandUrlScreenshotPolicy::urlChanged($originalUri, $url)) {
        return;
      }
    }

    if (!BiolandUrlScreenshotPolicy::isFetchableUrl($url)) {
      $this->loggerFactory->get('bioland')->notice('Skipped website screenshot for node @nid: URL is not fetchable.', ['@nid' => $entity->id()]);
      return;
    }

    $langcode = $entity->language()->getId();
    $dedupeKey = $entity->id() . '|' . $langcode . '|' . $url;
    if (isset($this->pending[$dedupeKey])) {
      // One form submit produces one queue item, mirroring
      // _bioland_dmsm_enqueue_dedupe_state().
      return;
    }

    $item = ['nid' => (int) $entity->id(), 'langcode' => $langcode, 'url' => $url];
    $id = $this->queueFactory->get(self::QUEUE_NAME)->createItem($item);
    $this->pending[$dedupeKey] = ['id' => $id, 'item' => $item];
  }

  /**
   * Processes every item recorded this request, right after the response.
   *
   * {@inheritdoc}
   */
  public function destruct(): void {
    if ($this->pending === []) {
      return;
    }

    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    foreach ($this->pending as $row) {
      try {
        $this->process($row['item']);
        $queue->deleteItem((object) ['item_id' => $row['id']]);
      }
      catch (\Throwable $e) {
        // Never log $e->getMessage(): a \ConvertApi\Error\Api may embed request
        // details. Log only the exception class and, when available, the
        // HTTP status code it carries.
        $status = BiolandConvertApiClient::statusFromException($e);
        $this->loggerFactory->get('bioland')->warning(
          'Website screenshot failed for node @nid; left in the retry queue: @class (status @status)',
          ['@nid' => $row['item']['nid'] ?? '?', '@class' => get_class($e), '@status' => $status ?: 'n/a']
        );
      }
    }

    $this->pending = [];
  }

  /**
   * Fetches and attaches a website screenshot. Called by destruct() and by
   * BiolandUrlScreenshotWorker on retry.
   */
  public function process(array $item): void {
    $node = $this->entityTypeManager->getStorage('node')->load($item['nid']);
    if (!$node || $this->fieldValue($node, 'field_url', 'uri') !== $item['url']) {
      // Missing, or a newer edit has already superseded this URL.
      return;
    }

    $secret = BiolandUrlScreenshotPolicy::resolveSecret([Settings::class, 'get']);
    if ($secret === '') {
      $this->loggerFactory->get('bioland')->warning('Website screenshot skipped for node @nid: CONVERT_API_SECRET is not set.', ['@nid' => $item['nid']]);
      return;
    }

    $adapter = ($this->adapterFactory)($secret);
    $bytes = $adapter->screenshotToWebp($item['url']);

    $directory = 'public://bioland/website-screenshots';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $directory . '/' . $item['nid'] . '-' . date('Ymd-His') . '.webp';
    $file = $this->fileRepository->writeData($bytes, $destination);

    $host = (string) parse_url($item['url'], PHP_URL_HOST);
    $title = (string) $node->label();
    $name = BiolandUrlScreenshotPolicy::buildMediaName($title, date('Y-m-d'));
    $alt = BiolandUrlScreenshotPolicy::buildMediaAlt($title, $host);
    $summary = $node->hasField('field_description') ? (string) $node->get('field_description')->value : '';
    $mediaTitle = BiolandUrlScreenshotPolicy::buildMediaTitle($summary, $host);

    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'langcode' => $item['langcode'],
      'uid' => $node->getOwnerId(),
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
        'title' => $mediaTitle,
      ],
    ]);
    $media->save();

    $existing = $node->hasField('field_website_image') ? $node->get('field_website_image')->getValue() : [];
    array_unshift($existing, ['target_id' => $media->id()]);
    $node->set('field_website_image', $existing);
    $node->bioland_screenshot_saving = TRUE;
    $node->save();
    unset($node->bioland_screenshot_saving);

    $this->loggerFactory->get('bioland')->info('Website screenshot attached for node @nid: media @mid (@bytes bytes).', [
      '@nid' => $item['nid'],
      '@mid' => $media->id(),
      '@bytes' => strlen($bytes),
    ]);
  }

  /**
   * Reads a scalar field property, or NULL when the field is absent/empty.
   */
  protected function fieldValue(ContentEntityInterface $entity, string $fieldName, string $property): ?string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }
    $value = $entity->get($fieldName)->{$property} ?? NULL;
    return $value === NULL ? NULL : (string) $value;
  }

  /**
   * Reads a field's target_id as an int, or NULL when absent/empty.
   */
  protected function fieldTargetId(ContentEntityInterface $entity, string $fieldName): ?int {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }
    $target = $entity->get($fieldName)->target_id ?? NULL;
    return $target === NULL ? NULL : (int) $target;
  }

}
