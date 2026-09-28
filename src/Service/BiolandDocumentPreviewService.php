<?php

namespace Drupal\bioland\Service;

use ConvertApi\ConvertApi;
use ConvertApi\FileUpload;
use Drupal\bioland\BiolandDocumentPreviewPolicy as Policy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Generates a first-page WebP preview for Document media via ConvertAPI.
 *
 * External HTTP never happens inside the editor's request: ::enqueueIfNeeded()
 * only pushes a queue item and remembers it; ::destruct() (this service is
 * tagged needs_destruction in bioland.services.yml, so core's kernel destruct
 * subscriber calls it after the response is flushed) converts every item
 * recorded in the request and deletes it from the queue on success. The queue
 * is the retry path only: BiolandDocumentPreviewWorker (cron) drains items
 * that failed here, or whose request never reached destruct() (CLI, fatal).
 * Same shape as BiolandUrlScreenshotService (BL-1191); a site with no cron
 * still gets its preview right after the media is saved.
 */
class BiolandDocumentPreviewService implements DestructableInterface
{
    public const QUEUE_ID = 'bioland_document_preview';

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FileSystemInterface $fileSystem;
    protected FileRepositoryInterface $fileRepository;
    protected $logger;
    protected QueueFactory $queueFactory;
    protected ConfigFactoryInterface $configFactory;
    protected StateInterface $state;

    /**
     * Queue items recorded during this request, keyed by "mid:fid" so one
     * save produces one item; processed by ::destruct().
     *
     * @var array<string, array{id: int|string, item: array}>
     */
    protected array $pending = [];

    public function __construct(
        EntityTypeManagerInterface $entity_type_manager,
        FileSystemInterface $file_system,
        FileRepositoryInterface $file_repository,
        LoggerChannelFactoryInterface $logger_factory,
        QueueFactory $queue_factory,
        ConfigFactoryInterface $config_factory,
        StateInterface $state
    ) {
        $this->entityTypeManager = $entity_type_manager;
        $this->fileSystem = $file_system;
        $this->fileRepository = $file_repository;
        $this->logger = $logger_factory->get('bioland');
        $this->queueFactory = $queue_factory;
        $this->configFactory = $config_factory;
        $this->state = $state;
    }

    /** Enqueues a preview conversion for a saved Document media item, if needed. */
    public function enqueueIfNeeded(ContentEntityInterface $entity, string $op): void
    {
        if (!empty($entity->bioland_preview_saving)) {
            return; // The worker's own save; never re-enqueue from it.
        }

        $documentFid = $this->targetId($entity, Policy::DOCUMENT_FIELD);
        $enabled = $this->configFactory->get('bioland.settings')->get('enable_document_preview') !== false;
        $secret = Policy::resolveSecret();

        if (!Policy::qualifies($entity->getEntityTypeId(), (string) $entity->bundle(), $documentFid !== null, $enabled, $secret !== '')) {
            return;
        }

        if ($op === 'update') {
            $original = $entity->original ?? null;
            $originalFid = $original ? $this->targetId($original, Policy::DOCUMENT_FIELD) : null;
            if (!Policy::documentChanged($originalFid, $documentFid)) {
                return;
            }
        }

        $imageFid = $this->targetId($entity, Policy::IMAGE_FIELD);
        if ($imageFid !== null && !Policy::isModuleOwnedImage($this->fileUri($imageFid))) {
            return; // An editor-chosen image is never overwritten.
        }

        $file = $this->entityTypeManager->getStorage('file')->load($documentFid);
        $extension = $file ? strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) : '';

        if (!Policy::converterFor($extension)) {
            $this->logger->notice('Document preview skipped for media @mid: unsupported extension "@ext".', [
                '@mid' => $entity->id(), '@ext' => $extension,
            ]);
            return;
        }

        $dedupeKey = $entity->id() . ':' . $documentFid;
        if (isset($this->pending[$dedupeKey])) {
            return;
        }

        $item = [
            'mid' => (int) $entity->id(), 'fid' => (int) $documentFid, 'extension' => $extension,
            // $imageFid is null or module-owned here (editor-owned returns above);
            // non-null means this media already had a module preview at enqueue time.
            'hadModuleImage' => $imageFid !== null,
        ];
        $id = $this->queueFactory->get(self::QUEUE_ID)->createItem($item);
        $this->pending[$dedupeKey] = ['id' => $id, 'item' => $item];
    }

    /**
     * Converts every item recorded this request, right after the response.
     *
     * A successful conversion deletes its queue item so cron never repeats
     * it; a failure leaves the item for BiolandDocumentPreviewWorker, which
     * owns the bounded retry policy. Deletion uses the (object) ['item_id']
     * shape core's DatabaseQueue/Memory backends accept, the only backends
     * this module uses.
     *
     * {@inheritdoc}
     */
    public function destruct(): void
    {
        if ($this->pending === []) {
            return;
        }

        $queue = $this->queueFactory->get(self::QUEUE_ID);
        foreach ($this->pending as $row) {
            try {
                $this->process($row['item']);
                $queue->deleteItem((object) ['item_id' => $row['id']]);
            } catch (\Throwable $e) {
                // Never log the message: a \ConvertApi\Error\Api may carry
                // request details. Class and HTTP code only.
                $this->logger->warning('Document preview failed for media @mid; left in the retry queue: @class (status @status).', [
                    '@mid' => $row['item']['mid'], '@class' => get_class($e), '@status' => (int) $e->getCode() ?: 'n/a',
                ]);
            }
        }

        $this->pending = [];
    }

    /**
     * Converts the queued document's first page and saves it to the Image field.
     *
     * @throws \ConvertApi\Error\Base
     *   On a ConvertAPI failure; the caller (the queue worker) classifies the
     *   error code via Policy::classifyStatus() to decide whether to retry.
     */
    public function process(array $item): void
    {
        $mid = (int) ($item['mid'] ?? 0);
        $fid = (int) ($item['fid'] ?? 0);
        $extension = (string) ($item['extension'] ?? '');

        $media = $mid ? $this->entityTypeManager->getStorage('media')->load($mid) : null;
        if (!$media) {
            $this->logger->notice('Document preview: media @mid no longer exists; skipping.', ['@mid' => $mid]);
            return;
        }

        if ($this->targetId($media, Policy::DOCUMENT_FIELD) !== $fid) {
            $this->logger->notice('Document preview: media @mid document changed since enqueue; a newer item will follow.', ['@mid' => $mid]);
            return;
        }

        $currentImageFid = $this->targetId($media, Policy::IMAGE_FIELD);
        if ($currentImageFid !== null && !Policy::isModuleOwnedImage($this->fileUri($currentImageFid))) {
            // An editor set their own image after this item was enqueued (a
            // cron retry can run much later): never overwrite it.
            $this->logger->info('Document preview: media @mid now has an editor-chosen image; skipping.', ['@mid' => $mid]);
            return;
        }
        if ($currentImageFid === null && !empty($item['hadModuleImage'])) {
            // The field held a module preview at enqueue time and is empty now:
            // an editor deliberately cleared it. Only this module ever writes a
            // file here, so a null field can't otherwise appear once populated.
            $this->logger->info('Document preview: media @mid image was explicitly cleared; skipping.', ['@mid' => $mid]);
            return;
        }

        $route = Policy::converterFor($extension);
        if (!$route) {
            return;
        }

        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        if (!$file) {
            return;
        }

        $realpath = (string) $this->fileSystem->realpath($file->getFileUri());
        $steps = Policy::buildParams($route);

        if ($route === Policy::ROUTE_DIRECT) {
            $params = $steps[0];
            if (Policy::dropsScaleProportions($extension)) {
                unset($params['ScaleProportions']);
            }
            $params['File'] = $this->newFileUpload($realpath);
            $result = $this->convert('webp', $params, $extension);
        } elseif ($route === Policy::ROUTE_VIA_OFFICE) {
            $officeExt = Policy::officeIntermediateFor($extension);
            $officeParams = $steps[0];
            $officeParams['File'] = $this->newFileUpload($realpath);
            $officeResult = $this->convert($officeExt, $officeParams, $extension);

            $webpParams = $steps[1];
            unset($webpParams['ScaleProportions']); // Not documented for docx/pptx/xlsx to webp.
            $webpParams['File'] = $officeResult->getFile();
            $result = $this->convert('webp', $webpParams, $officeExt);
        } else {
            $pdfParams = $steps[0];
            $pdfParams['File'] = $this->newFileUpload($realpath);
            $pdfResult = $this->convert('pdf', $pdfParams, $extension);

            $webpParams = $steps[1];
            $webpParams['File'] = $pdfResult->getFile();
            $result = $this->convert('webp', $webpParams, 'pdf');
        }

        $bytes = $result->getFile()->getContents();
        $cost = $result->getConversionCost();
        if ($route === Policy::ROUTE_DIRECT && $cost > 1) {
            $this->logger->warning('Document preview for media @mid billed for @cost pages; PageRange was not honoured.', [
                '@mid' => $mid, '@cost' => $cost,
            ]);
        }

        $destination = Policy::PREVIEW_URI_PREFIX . $mid . '-' . $fid . '.webp';
        $newFile = $this->fileRepository->writeData($bytes, $destination, $this->replaceExisting());

        $oldImageFid = $this->targetId($media, Policy::IMAGE_FIELD);
        $oldImageUri = $oldImageFid !== null ? $this->fileUri($oldImageFid) : null;

        $mediaName = (string) $media->label();
        $media->set(Policy::IMAGE_FIELD, [
            'target_id' => $newFile->id(), 'alt' => Policy::altText($mediaName), 'title' => Policy::titleText($mediaName),
        ]);

        if ($oldImageFid !== null && Policy::isModuleOwnedImage($oldImageUri)) {
            $this->entityTypeManager->getStorage('file')->load($oldImageFid)?->delete();
        }

        $media->bioland_preview_saving = true;
        if (method_exists($media, 'setNewRevision')) {
            $media->setNewRevision(false);
        }
        $media->save();
        unset($media->bioland_preview_saving);

        $this->logger->info('Document preview generated for media @mid: @bytes bytes, conversion cost @cost.', [
            '@mid' => $mid, '@bytes' => strlen($bytes), '@cost' => $cost,
        ]);
    }

    /**
     * Wraps the ConvertAPI call so tests can override it without HTTP.
     *
     * @return \ConvertApi\Result
     */
    protected function convert(string $toFormat, array $params, ?string $fromFormat = null)
    {
        ConvertApi::setApiCredentials(Policy::resolveSecret());
        ConvertApi::$conversionTimeout = 60;
        ConvertApi::$uploadTimeout = 60;
        ConvertApi::$readTimeout = 90;

        return ConvertApi::convert($toFormat, $params, $fromFormat);
    }

    protected function newFileUpload(string $realpath): FileUpload
    {
        return new FileUpload($realpath);
    }

    /**
     * The FileSystemInterface::EXISTS_REPLACE-equivalent for this core.
     *
     * @return int|\Drupal\Core\File\FileExists
     */
    protected function replaceExisting()
    {
        return class_exists('\Drupal\Core\File\FileExists')
            ? \Drupal\Core\File\FileExists::Replace
            : FileSystemInterface::EXISTS_REPLACE;
    }

    private function targetId(ContentEntityInterface $entity, string $fieldName): ?int
    {
        if (!$entity->hasField($fieldName)) {
            return null;
        }
        $field = $entity->get($fieldName);
        return (!$field || $field->isEmpty()) ? null : (int) $field->target_id;
    }

    private function fileUri(int $fid): ?string
    {
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        return $file ? $file->getFileUri() : null;
    }
}
