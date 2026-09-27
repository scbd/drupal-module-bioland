<?php

namespace Drupal\bioland\Plugin\QueueWorker;

use Drupal\bioland\BiolandUrlScreenshotPolicy;
use Drupal\bioland\Service\BiolandUrlScreenshotService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retry path for BL-1191 website screenshots (bioland_url_screenshot).
 *
 * The primary trigger is BiolandUrlScreenshotService::destruct(), run after
 * the editor's response is flushed. This worker only handles items whose
 * in-request attempt failed transiently, or whose request ended before
 * destruct() ran (CLI, fatal errors) - modelled on
 * BiolandDmsmGeographyWorker's bounded per-item retry counter.
 *
 * @QueueWorker(
 *   id = "bioland_url_screenshot",
 *   title = @Translation("Bioland website screenshot (retry path)"),
 *   cron = {"time" = 60}
 * )
 */
class BiolandUrlScreenshotWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const MAX_ATTEMPTS = 3;
  const STATE_ATTEMPT_PREFIX = 'bioland.url_screenshot_attempts.';

  protected BiolandUrlScreenshotService $service;
  protected StateInterface $state;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BiolandUrlScreenshotService $service,
    StateInterface $state
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->service = $service;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('bioland.url_screenshot'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $nid = is_array($data) && isset($data['nid']) ? $data['nid'] : '_unknown';
    $stateKey = self::STATE_ATTEMPT_PREFIX . $nid;

    try {
      $this->service->process(is_array($data) ? $data : []);
      $this->state->delete($stateKey);
    }
    catch (\Throwable $e) {
      // A ConvertApiException carries an HTTP status; classify it. Anything
      // else (a bug, a missing dependency) is treated as permanent so it
      // never retries silently forever.
      $status = method_exists($e, 'getHttpStatusCode') ? (int) $e->getHttpStatusCode() : 0;
      $classification = $status > 0 ? BiolandUrlScreenshotPolicy::classifyResponse($status) : 'permanent';

      if ($classification !== 'transient') {
        $this->state->delete($stateKey);
        return;
      }

      $attempts = (int) $this->state->get($stateKey, 0) + 1;

      if ($attempts >= self::MAX_ATTEMPTS) {
        $this->state->delete($stateKey);
        return;
      }

      $this->state->set($stateKey, $attempts);
      // Never interpolate $e->getMessage(): a ConvertApiException may embed
      // request details. Log only the exception class and HTTP status.
      throw new RequeueException(sprintf(
        'Transient website screenshot failure for node %s (attempt %d of %d); requeued: %s (status %d)',
        $nid,
        $attempts,
        self::MAX_ATTEMPTS,
        get_class($e),
        $status
      ));
    }
  }

}
