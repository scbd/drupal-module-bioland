<?php

namespace Drupal\bioland\Plugin\QueueWorker;

use ConvertApi\Error\Base as ConvertApiError;
use Drupal\bioland\BiolandDocumentPreviewPolicy as Policy;
use Drupal\bioland\Service\BiolandDocumentPreviewService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drains document-preview conversions outside any web request.
 *
 * Modelled on BiolandDmsmGeographyWorker: the conversion itself is HTTP to
 * ConvertAPI, so it never runs inside a request; only this worker, drained
 * by cron, calls BiolandDocumentPreviewService::process().
 *
 * @QueueWorker(
 *   id = "bioland_document_preview",
 *   title = @Translation("Bioland document preview conversion"),
 *   cron = {"time" = 60}
 * )
 */
class BiolandDocumentPreviewWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
{
    /** How many times one media item may be retried after a transient failure. */
    public const MAX_ATTEMPTS = 3;

    /** State key prefix holding the per-media transient attempt count. */
    public const STATE_ATTEMPT_PREFIX = 'bioland.document_preview_attempts.';

    protected BiolandDocumentPreviewService $documentPreviewService;
    protected StateInterface $state;
    protected LoggerChannelInterface $logger;

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        BiolandDocumentPreviewService $document_preview_service,
        StateInterface $state,
        LoggerChannelFactoryInterface $logger_factory
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->documentPreviewService = $document_preview_service;
        $this->state = $state;
        $this->logger = $logger_factory->get('bioland');
    }

    /** {@inheritdoc} */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('bioland.document_preview'),
            $container->get('state'),
            $container->get('logger.factory')
        );
    }

    /** {@inheritdoc} */
    public function processItem($data)
    {
        $mid = is_array($data) && isset($data['mid']) ? (int) $data['mid'] : 0;
        $stateKey = self::STATE_ATTEMPT_PREFIX . ($mid ?: '_unknown');

        try {
            $this->documentPreviewService->process(is_array($data) ? $data : []);
            $this->state->delete($stateKey);
        } catch (ConvertApiError $e) {
            $statusCode = (int) $e->getCode();
            if (Policy::classifyStatus($statusCode) !== Policy::STATUS_TRANSIENT) {
                // Permanent failure: give up, do not retry. Never log the
                // exception message, which may carry request details.
                $this->logger->error('Document preview permanently failed for media @mid: HTTP @status.', [
                    '@mid' => $mid, '@status' => $statusCode,
                ]);
                $this->state->delete($stateKey);
                return;
            }

            $attempts = (int) $this->state->get($stateKey, 0) + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->state->delete($stateKey);
                return;
            }
            $this->state->set($stateKey, $attempts);

            throw new RequeueException(sprintf(
                'Transient document preview failure for media %d (attempt %d of %d); requeued.',
                $mid,
                $attempts,
                self::MAX_ATTEMPTS
            ));
        } catch (\Throwable $e) {
            // Anything else (file-system, FileUpload, file.repository write
            // failure, ...) is unbounded unless it is folded into the same
            // bounded counter; otherwise cron re-leases and retries forever.
            // Never log the exception message, only its class.
            $this->logger->error('Document preview failed for media @mid with @class.', [
                '@mid' => $mid, '@class' => get_class($e),
            ]);

            $attempts = (int) $this->state->get($stateKey, 0) + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->state->delete($stateKey);
                return;
            }
            $this->state->set($stateKey, $attempts);

            throw new RequeueException(sprintf(
                'Non-ConvertAPI document preview failure for media %d (attempt %d of %d); requeued.',
                $mid,
                $attempts,
                self::MAX_ATTEMPTS
            ));
        }
    }
}
