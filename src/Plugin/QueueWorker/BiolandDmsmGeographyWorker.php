<?php

namespace Drupal\bioland\Plugin\QueueWorker;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetches the site's geography document outside any web request.
 *
 * This worker exists purely to move the fetch off the request path. The config
 * API base URL is now a configured value (bioland.settings.dmsm_config_base_url)
 * and is expected to point at this site's own Drupal config route. A fetch made
 * during a web request would therefore have one PHP-FPM worker of the pool
 * blocking on a second worker of the SAME pool; with a pool of N workers, N
 * concurrent triggers deadlock the site. Update hooks and hook_install only
 * enqueue; cron drains this queue.
 *
 * @QueueWorker(
 *   id = "bioland_dmsm_geography",
 *   title = @Translation("Bioland DMSM geography fetch"),
 *   cron = {"time" = 30}
 * )
 */
class BiolandDmsmGeographyWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface
{
    /**
     * How many times one hostname may be retried after a transient failure.
     */
    const MAX_ATTEMPTS = 3;

    /**
     * State key prefix holding the per-hostname transient attempt count.
     */
    const STATE_ATTEMPT_PREFIX = 'bioland.dmsm_geography_attempts.';

    /**
     * The DMSM config service.
     *
     * @var \Drupal\bioland\Service\BiolandDmsmConfigService
     */
    protected $dmsmConfigService;

    /**
     * The state store, holding the bounded retry counter.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    /**
     * Constructs a BiolandDmsmGeographyWorker.
     *
     * @param array $configuration
     *   Plugin configuration.
     * @param string $plugin_id
     *   The plugin ID.
     * @param mixed $plugin_definition
     *   The plugin definition.
     * @param \Drupal\bioland\Service\BiolandDmsmConfigService $dmsm_config_service
     *   The service that performs the fetch and the config write-back.
     * @param \Drupal\Core\State\StateInterface $state
     *   The state store holding the bounded per-hostname retry counter.
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        BiolandDmsmConfigService $dmsm_config_service,
        StateInterface $state
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->dmsmConfigService = $dmsm_config_service;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(
        ContainerInterface $container,
        array $configuration,
        $plugin_id,
        $plugin_definition
    ) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('bioland.dmsm_config'),
            $container->get('state')
        );
    }

    /**
     * Performs the geography fetch for one queued site.
     *
     * The item carries the hostname captured at enqueue time, because a cron
     * or drush run has no meaningful request host of its own.
     *
     * Outcomes are split by cause. A configuration refusal (unset base URL, a
     * production site pointed outside the allowlist, an unsafe fetch target)
     * is permanent: retrying in a cron loop would only repeat the refusal, so
     * the item is dropped and the refusal is visible through
     * hook_requirements() on the status report. A transient failure (timeout,
     * 502 - anything the service marks 'transient') is requeued through
     * RequeueException up to self::MAX_ATTEMPTS times; previously it returned,
     * which made Drupal delete the item and drop the fetch for good with
     * nothing left to re-enqueue it.
     *
     * @param mixed $data
     *   The queue item payload, expected to be ['hostname' => string].
     *
     * @return array
     *   The service result.
     *
     * @throws \Drupal\Core\Queue\RequeueException
     *   When the failure was transient and attempts remain.
     */
    public function processItem($data)
    {
        $hostname = is_array($data) && isset($data['hostname']) ? $data['hostname'] : null;
        $stateKey = self::STATE_ATTEMPT_PREFIX
            . (is_string($hostname) && $hostname !== '' ? $hostname : '_unknown');

        $result = $this->dmsmConfigService->updateCountriesFromDmsm($hostname);

        if (!empty($result['success']) || empty($result['transient'])) {
            // Success, or a permanent configuration refusal: stop counting.
            $this->state->delete($stateKey);

            return $result;
        }

        $attempts = (int) $this->state->get($stateKey, 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->state->delete($stateKey);

            return $result;
        }

        $this->state->set($stateKey, $attempts);

        throw new RequeueException(sprintf(
            'Transient DMSM geography fetch failure for %s (attempt %d of %d); requeued.',
            is_string($hostname) && $hostname !== '' ? $hostname : '(unknown host)',
            $attempts,
            self::MAX_ATTEMPTS
        ));
    }
}
