<?php

namespace Drupal\bioland\Plugin\QueueWorker;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
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
     * The DMSM config service.
     *
     * @var \Drupal\bioland\Service\BiolandDmsmConfigService
     */
    protected $dmsmConfigService;

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
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        BiolandDmsmConfigService $dmsm_config_service
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->dmsmConfigService = $dmsm_config_service;
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
            $container->get('bioland.dmsm_config')
        );
    }

    /**
     * Performs the geography fetch for one queued site.
     *
     * The item carries the hostname captured at enqueue time, because a cron
     * or drush run has no meaningful request host of its own.
     *
     * A failed fetch is logged and the item is dropped rather than requeued:
     * the causes are configuration-shaped (unset base URL, a production site
     * pointed at a dev host), so retrying in a tight cron loop would only
     * repeat the same refusal.
     *
     * @param mixed $data
     *   The queue item payload, expected to be ['hostname' => string].
     */
    public function processItem($data)
    {
        $hostname = is_array($data) && isset($data['hostname']) ? $data['hostname'] : null;

        return $this->dmsmConfigService->updateCountriesFromDmsm($hostname);
    }
}
