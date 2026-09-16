<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for fetching the site's geography document from the config API.
 *
 * Execution context
 * -----------------
 * The fetch itself (::updateCountriesFromDmsm()) runs ONLY outside a web
 * request — from a queue worker drained by cron, or from drush on the CLI.
 * It refuses to run under a web SAPI. The reason is re-entrancy: the base URL
 * is now configurable and is expected to point at the site's own Drupal config
 * route, so an in-request fetch has one PHP-FPM worker of the pool blocking on
 * a second worker of the SAME pool. With a pool of N workers, N concurrent
 * triggers deadlock the site. Callers therefore use ::enqueueCountriesUpdate(),
 * which only pushes a queue item and returns.
 *
 * Base URL
 * --------
 * The host comes from the `bioland.settings` key named by
 * self::CONFIG_BASE_URL_KEY. There is deliberately NO hardcoded fallback in
 * code: the previous implementation hardcoded the dev host for every
 * environment, so every production site fetched its geography from dev. A code
 * fallback is exactly how that defect would survive a configuration mistake.
 * When the key is unset the fetch logs an error and fails; it never guesses.
 * The deployed default lives in config/install/bioland.settings.yml.
 *
 * Honest caveat
 * -------------
 * This re-hosts the geography cycle, it does not break it. While the
 * `bioland.settings.countries` write-back remains, Drupal still fetches its own
 * geography over HTTP and stores a copy in its own configuration. Three live
 * consumers read that copy (BiolandSettingsManager, BiolandHomeWidgetsForm,
 * BiolandAdminSettingsForm), so the write-back cannot simply be dropped.
 */
class BiolandDmsmConfigService
{
    /**
     * The bioland.settings key holding the config API base URL.
     *
     * No code fallback exists for this key; the deployed default ships in
     * config/install/bioland.settings.yml.
     */
    const CONFIG_BASE_URL_KEY = 'dmsm_config_base_url';

    /**
     * The queue that performs the geography fetch outside any web request.
     */
    const QUEUE_NAME = 'bioland_dmsm_geography';

    /**
     * Host labels that mark a base URL as non-production.
     */
    const NON_PRODUCTION_HOST_LABELS = ['dev', 'stg', 'staging'];

    /**
     * Host suffixes that mark a base URL as non-production.
     */
    const NON_PRODUCTION_HOST_SUFFIXES = ['cbddev.xyz'];

    /**
     * The config factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * The logger.
     *
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    /**
     * The queue factory.
     *
     * @var \Drupal\Core\Queue\QueueFactory
     */
    protected $queueFactory;

    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Constructs a BiolandDmsmConfigService object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory.
     * @param \GuzzleHttp\ClientInterface $http_client
     *   The HTTP client.
     * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
     *   The logger factory.
     * @param \Drupal\Core\Queue\QueueFactory $queue_factory
     *   The queue factory, used to defer the fetch out of the request path.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack, used only to resolve the site hostname at enqueue
     *   time. The fetch itself never touches it.
     */
    public function __construct(
        ConfigFactoryInterface $config_factory,
        ClientInterface $http_client,
        LoggerChannelFactoryInterface $logger_factory,
        QueueFactory $queue_factory,
        RequestStack $request_stack
    ) {
        $this->configFactory = $config_factory;
        $this->httpClient = $http_client;
        $this->logger = $logger_factory->get('bioland');
        $this->queueFactory = $queue_factory;
        $this->requestStack = $request_stack;
    }

    /**
     * Queue a geography update instead of performing it now.
     *
     * This is the ONLY entry point a web request (update.php, hook_install) may
     * use. It performs no HTTP request: it resolves and validates the hostname,
     * pushes one queue item, and returns. The queue is drained by cron, outside
     * the request that enqueued it, which is what keeps the PHP-FPM pool from
     * blocking on itself once the base URL points at this site's own route.
     *
     * @param string|null $hostname
     *   The hostname to resolve the site from. If NULL, the current request's
     *   host is used.
     *
     * @return array
     *   Array containing 'success' (bool), 'message' (string) and
     *   'queued' (bool).
     */
    public function enqueueCountriesUpdate($hostname = null)
    {
        $hostname = $this->resolveHostname($hostname);

        if ($hostname === null) {
            $message = 'Unable to determine hostname for the DMSM geography fetch; nothing queued.';
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'queued' => false];
        }

        if (!$this->parseHostname($hostname)) {
            $message = sprintf('Unable to parse hostname: %s', $hostname);
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'queued' => false];
        }

        $this->queueFactory->get(self::QUEUE_NAME)->createItem(['hostname' => $hostname]);

        $message = sprintf(
            'Queued DMSM geography update for %s; it runs on the next cron queue pass, outside this request.',
            $hostname
        );
        $this->logger->info($message);

        return ['success' => true, 'message' => $message, 'queued' => true];
    }

    /**
     * Fetch and update countries configuration from the config API.
     *
     * Worker-only. Refuses to run under a web SAPI (see the class docblock:
     * an in-request fetch can re-enter and deadlock the site's own PHP-FPM
     * pool). Web-request callers must use ::enqueueCountriesUpdate() instead.
     *
     * Also refuses when the base-URL key is unset — there is no code fallback —
     * and when the prod environment is pointed at a dev or staging host.
     *
     * @param string|null $hostname
     *   The hostname to parse. If NULL, will use the current request host.
     *
     * @return array
     *   Array containing 'success' (bool) and 'message' (string).
     */
    public function updateCountriesFromDmsm($hostname = null)
    {
        if ($this->isWebRequestContext()) {
            $message = 'Refusing to fetch DMSM geography inside a web request; '
                . 'use the bioland_dmsm_geography queue (drained by cron) instead.';
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        $hostname = $this->resolveHostname($hostname);

        // Parse hostname to get env, multiSiteCode, and siteCode.
        $params = $hostname === null ? null : $this->parseHostname($hostname);

        if (!$params) {
            $message = sprintf('Unable to parse hostname: %s', (string) $hostname);
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        $env = $params['env'];
        $multiSiteCode = $params['multiSiteCode'];
        $siteCode = $params['siteCode'];

        $baseUrl = $this->getConfiguredBaseUrl();

        if ($baseUrl === null) {
            $message = sprintf(
                'DMSM config base URL is not set (bioland.settings.%s); refusing to fetch. There is no fallback host in code.',
                self::CONFIG_BASE_URL_KEY
            );
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        $guardError = $this->checkBaseUrlAgainstEnv($env, $baseUrl);

        if ($guardError !== null) {
            $this->logger->error($guardError);
            return ['success' => false, 'message' => $guardError];
        }

        // Build API URL from the configured base.
        $url = sprintf(
            '%s/api/config/%s/%s/%s',
            $baseUrl,
            $env,
            $multiSiteCode,
            $siteCode
        );

        $this->logger->info('Fetching countries from DMSM API: @url', ['@url' => $url]);

        try {
            // Make HTTP request.
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            // Handle double-encoded JSON: if the response is a JSON string, decode it again
            if (is_string($data)) {
                $this->logger->debug('DMSM API response was double-encoded JSON string, decoding again');
                $data = json_decode($data, true);
            }

            if (!$data || !is_array($data)) {
                throw new \Exception('Invalid JSON response from DMSM API - expected object');
            }

            // Extract countries: try runTime.countries first, fallback to country at root level.
            $countries = null;
            
            if (isset($data['runTime']['countries']) && is_array($data['runTime']['countries'])) {
                $countries = $data['runTime']['countries'];
            } elseif (isset($data['country']) && is_string($data['country'])) {
                // country is a single string - convert to array.
                $countries = [$data['country']];
            }

            if (!$countries || empty($countries)) {
                throw new \Exception('No countries found in DMSM API response - expected runTime.countries (array) or country (string)');
            }

            // Filter and normalize countries (ensure they're strings).
            // This completely replaces any existing countries configuration.
            $countries = array_values(array_filter(array_map('strval', $countries)));

            if (empty($countries)) {
                throw new \Exception('No valid countries after filtering DMSM API response');
            }

            // Update config - completely replace existing values.
            $config = $this->configFactory->getEditable('bioland.settings');
            
            // Replace countries array (not merge - complete replacement).
            $config->set('countries', $countries);
            
            // Set is_biosafety_land based on multiSiteCode.
            $is_biosafety_land = ($multiSiteCode === 'bsl');
            $config->set('is_biosafety_land', $is_biosafety_land);
            
            // Extract and save region if present.
            if (isset($data['region']) && is_string($data['region'])) {
                $config->set('region', $data['region']);
            }
            
            // Extract and save continent if present.
            if (isset($data['continent']) && is_string($data['continent'])) {
                $config->set('continent', $data['continent']);
            }
            
            $config->save();

            $message = sprintf(
                'Successfully updated countries from DMSM API (env: %s, multiSiteCode: %s, siteCode: %s, is_biosafety_land: %s): %s',
                $env,
                $multiSiteCode,
                $siteCode,
                $is_biosafety_land ? 'true' : 'false',
                implode(', ', $countries)
            );
            
            $this->logger->info($message);
            
            return ['success' => true, 'message' => $message];
        } catch (RequestException $e) {
            $message = sprintf('HTTP error fetching DMSM config: %s', $e->getMessage());
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        } catch (\Exception $e) {
            $message = sprintf('Error processing DMSM config: %s', $e->getMessage());
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        }
    }

    /**
     * Resolve the hostname to operate on.
     *
     * @param string|null $hostname
     *   An explicit hostname, or NULL to take the current request's host.
     *
     * @return string|null
     *   The hostname, or NULL when none can be determined (for example under
     *   drush with no --uri, where the caller must pass one explicitly).
     */
    protected function resolveHostname($hostname = null)
    {
        if ($hostname !== null && $hostname !== '') {
            return $hostname;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $host = $request->getHost();

        return $host === '' ? null : $host;
    }

    /**
     * Whether the current process is serving a web request.
     *
     * The fetch is forbidden here. Once the configured base URL points at this
     * site's own Drupal route, an in-request fetch blocks one PHP-FPM worker
     * waiting on another worker from the same pool; enough concurrent triggers
     * exhaust the pool and the site stops answering.
     *
     * @return bool
     *   TRUE when running under a web SAPI, FALSE on CLI (drush cron,
     *   drush queue:run, drush updatedb).
     */
    protected function isWebRequestContext()
    {
        return !in_array($this->getSapiName(), ['cli', 'phpdbg'], true);
    }

    /**
     * The current PHP SAPI name.
     *
     * Split out so tests can simulate a web SAPI.
     *
     * @return string
     *   The SAPI name.
     */
    protected function getSapiName()
    {
        return PHP_SAPI;
    }

    /**
     * Read the configured config API base URL.
     *
     * There is NO code fallback. A missing, empty or host-less value yields
     * NULL and the caller fails loudly. The deployed default value lives in
     * config/install/bioland.settings.yml.
     *
     * @return string|null
     *   The base URL without a trailing slash, or NULL when unusable.
     */
    protected function getConfiguredBaseUrl()
    {
        $value = $this->configFactory->get('bioland.settings')->get(self::CONFIG_BASE_URL_KEY);

        if (!is_string($value)) {
            return null;
        }

        $value = rtrim(trim($value), '/');

        if ($value === '') {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        return $value;
    }

    /**
     * Guard a production environment against a dev or staging base URL.
     *
     * This is the whole point of the change: the retired defect pointed every
     * environment, production included, at the dev host. If configuration ever
     * reintroduces that, the fetch must refuse loudly rather than silently pull
     * production geography from dev.
     *
     * @param string $env
     *   The resolved environment ('dev', 'stg' or 'prod').
     * @param string $baseUrl
     *   The configured base URL.
     *
     * @return string|null
     *   An error message when the fetch must be refused, NULL when permitted.
     */
    protected function checkBaseUrlAgainstEnv($env, $baseUrl)
    {
        if ($env !== 'prod') {
            return null;
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $labels = explode('.', $host);
        $isNonProduction = (bool) array_intersect($labels, self::NON_PRODUCTION_HOST_LABELS);

        foreach (self::NON_PRODUCTION_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || substr($host, -strlen('.' . $suffix)) === '.' . $suffix) {
                $isNonProduction = true;
            }
        }

        if (!$isNonProduction) {
            return null;
        }

        return sprintf(
            'Refusing DMSM geography fetch: environment is "%s" but bioland.settings.%s points at the non-production host "%s".',
            $env,
            self::CONFIG_BASE_URL_KEY,
            $host
        );
    }

    /**
     * Parse hostname to extract environment, multiSiteCode, and siteCode.
     *
     * @param string $hostname
     *   The hostname to parse.
     *
     * @return array|null
     *   Array with keys 'env', 'multiSiteCode', 'siteCode', or NULL if cannot parse.
     */
    protected function parseHostname($hostname)
    {
        $hostname = strtolower(trim($hostname));

        // Determine environment.
        $env = null;
        if (strpos($hostname, 'cbddev.xyz') !== false) {
            $env = 'dev';
        } elseif (strpos($hostname, 'staging.cbd.int') !== false) {
            $env = 'stg';
        } elseif (strpos($hostname, 'chm-cbd.net') !== false) {
            $env = 'prod';
        } elseif (in_array($hostname, ['www.biodiv.be', 'biodiv.be', 'biodiv.mnhn.fr'])) {
            $env = 'prod';
        }

        if (!$env) {
            return null;
        }

        // Determine multiSiteCode.
        $multiSiteCode = null;
        if (strpos($hostname, '.bsl.') !== false) {
            $multiSiteCode = 'bsl';
        } elseif (strpos($hostname, '.bl2.') !== false) {
            $multiSiteCode = 'bl2';
        } elseif (in_array($hostname, ['www.biodiv.be', 'biodiv.be', 'biodiv.mnhn.fr'])) {
            $multiSiteCode = 'bl2';
        }

        if (!$multiSiteCode) {
            return null;
        }

        // Determine siteCode.
        $siteCode = null;
        
        // Special cases first.
        if (in_array($hostname, ['www.biodiv.be', 'biodiv.be'])) {
            $siteCode = 'be';
        } elseif ($hostname === 'biodiv.mnhn.fr') {
            $siteCode = 'fr';
        } else {
            // Extract from pattern: ${anyThingBeforFirstPeriod}.bl2.${aBaseHost}
            // or ${anyThingBeforFirstPeriod}.bsl.${aBaseHost}
            $parts = explode('.', $hostname);
            if (count($parts) > 0) {
                $siteCode = $parts[0];
            }
        }

        if (!$siteCode) {
            return null;
        }

        return [
            'env' => $env,
            'multiSiteCode' => $multiSiteCode,
            'siteCode' => $siteCode,
        ];
    }
}
