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
 * No default value ships in config/install/bioland.settings.yml either: a
 * shipped value would have to be a real host, and the only host that would
 * suit every environment is a non-production one, which the production guard
 * must refuse. The value is therefore deploy-time configuration, seeded on
 * existing sites by bioland_update_9082() from $settings or the environment.
 *
 * Fetch target safety
 * -------------------
 * This fetch runs as CLI on the application host, which is a good SSRF
 * position: it can reach loopback, the container network, and the cloud
 * metadata endpoint. The configured value is therefore treated as untrusted
 * input even though only config-import, `drush cset` or deploy access can set
 * it. Before connecting, ::assertUrlIsFetchable() requires https (http only
 * off production), rejects userinfo outright, rejects forbidden host names and
 * suffixes, resolves the host, and rejects every private, loopback,
 * link-local, CGNAT or otherwise reserved address. Redirects are capped at one
 * hop and the same check is re-applied to the redirect target through Guzzle's
 * on_redirect callback, so a benign-looking host cannot 302 the worker onto
 * loopback or 169.254.169.254.
 *
 * Known residual risk: the resolve-then-connect sequence is a DNS rebinding
 * window. Closing it needs connection-level pinning (resolving once and
 * forcing the socket to that address), which Guzzle does not expose portably.
 * The one-hop redirect cap and the scheme allowlist bound the damage.
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
     * No fallback exists for this key anywhere: not in code, and not in
     * config/install/bioland.settings.yml either. It is deploy-time
     * configuration, seeded on existing sites by bioland_update_9082().
     */
    const CONFIG_BASE_URL_KEY = 'dmsm_config_base_url';

    /**
     * The queue that performs the geography fetch outside any web request.
     */
    const QUEUE_NAME = 'bioland_dmsm_geography';

    /**
     * The bioland.settings key overriding the production host allowlist.
     *
     * This is the ONLY source of the production allowlist. It holds an exact
     * list of host names; it is never a way to widen the check to "anything".
     * Like the base URL itself it is deploy-time configuration, seeded from
     * $settings['bioland_dmsm_prod_host_allowlist'] (or the comma-separated
     * BIOLAND_DMSM_PROD_HOST_ALLOWLIST environment variable) by
     * bioland_update_9082().
     */
    const CONFIG_PROD_HOST_ALLOWLIST_KEY = 'dmsm_config_prod_host_allowlist';

    /**
     * Hosts a production site may fetch its geography from.
     *
     * This is an ALLOWLIST, deliberately. The previous implementation
     * denylisted three host labels and one suffix, which stopped exactly the
     * one misconfiguration being retired and let everything else through: an
     * arbitrary host, a bare IP literal of the dev host, a trailing-dot form
     * of the dev host, and "dmsm-dev.example.com" (whose first label is
     * "dmsm-dev", not "dev").
     *
     * It ships EMPTY on purpose, for the same reason no base URL ships in
     * config/install: this repo carries no verified production config host,
     * and inventing one in code is exactly how the retired hardcoded-host
     * defect was born. An empty allowlist refuses every production fetch and
     * says so on the status report - the loud failure, not a guess that
     * silently points production somewhere wrong.
     */
    const PRODUCTION_HOST_ALLOWLIST = [];

    /**
     * Host suffixes that may never be fetched, in any environment.
     */
    const FORBIDDEN_HOST_SUFFIXES = ['.internal', '.local', '.localdomain'];

    /**
     * Host names that may never be fetched, in any environment.
     */
    const FORBIDDEN_HOSTS = ['localhost', 'internal', 'metadata.google.internal'];

    /**
     * Hard cap on the response body, in bytes.
     */
    const MAX_RESPONSE_BYTES = 262144;

    /**
     * Hard cap on the number of countries accepted from one response.
     */
    const MAX_COUNTRIES = 512;

    /**
     * Total request timeout, in seconds.
     */
    const HTTP_TIMEOUT = 10;

    /**
     * Connection timeout, in seconds.
     */
    const HTTP_CONNECT_TIMEOUT = 5;

    /**
     * Maximum characters of third-party text copied into a log message.
     */
    const MAX_LOGGED_TEXT = 500;

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
            return ['success' => false, 'message' => $message, 'transient' => false];
        }

        $hostname = $this->resolveHostname($hostname);

        // Parse hostname to get env, multiSiteCode, and siteCode.
        $params = $hostname === null ? null : $this->parseHostname($hostname);

        if (!$params) {
            $message = sprintf('Unable to parse hostname: %s', (string) $hostname);
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'transient' => false];
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
            return ['success' => false, 'message' => $message, 'transient' => false];
        }

        $guardError = $this->checkBaseUrlAgainstEnv($env, $baseUrl);

        if ($guardError !== null) {
            $this->logger->error($guardError);
            return ['success' => false, 'message' => $guardError, 'transient' => false];
        }

        // Build API URL from the configured base.
        $url = sprintf(
            '%s/api/config/%s/%s/%s',
            $baseUrl,
            $env,
            $multiSiteCode,
            $siteCode
        );

        // Treat the configured value as untrusted input: this process can reach
        // loopback, the container network and the metadata endpoint.
        try {
            $this->assertUrlIsFetchable($url, $env);
        } catch (\RuntimeException $e) {
            $message = sprintf(
                'Refusing DMSM geography fetch: %s',
                $this->sanitizeLogText($e->getMessage())
            );
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'transient' => false];
        }

        $this->logger->info('Fetching countries from DMSM API: @url', [
            '@url' => $this->sanitizeUrlForLog($url),
        ]);

        try {
            // Make HTTP request.
            $response = $this->httpClient->request('GET', $url, $this->buildRequestOptions($env));

            $responseBody = $this->readBoundedBody($response);
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

            // Validate the shape before anything is written back. The response
            // comes from a configured remote host, so its length and its entry
            // types are both untrusted: an unbounded list lands in
            // bioland.settings.countries and the widgets form then builds one
            // details element per entry.
            $countries = $this->normalizeCountries($countries);

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
                'Successfully updated countries from DMSM API (env: %s, multiSiteCode: %s, siteCode: %s, is_biosafety_land: %s): %d countries [%s]',
                $env,
                $multiSiteCode,
                $siteCode,
                $is_biosafety_land ? 'true' : 'false',
                count($countries),
                $this->summarizeCountries($countries)
            );

            $this->logger->info($message);

            return ['success' => true, 'message' => $message, 'transient' => false];
        } catch (RequestException $e) {
            // A timeout or a 502 is transient: the worker requeues it (bounded).
            $message = sprintf(
                'HTTP error fetching DMSM config from %s: %s',
                $this->sanitizeUrlForLog($url),
                $this->sanitizeLogText($e->getMessage())
            );
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'transient' => true];
        } catch (\Exception $e) {
            $message = sprintf(
                'Error processing DMSM config: %s',
                $this->sanitizeLogText($e->getMessage())
            );
            $this->logger->error($message);
            return ['success' => false, 'message' => $message, 'transient' => false];
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
     * Guard a production environment with an allowlist of permitted hosts.
     *
     * This is an allowlist, not a denylist. Denylisting the dev labels stopped
     * exactly the misconfiguration being retired and nothing else: a trailing
     * dot ("dmsm.cbddev.xyz.", which DNS resolves identically), a bare IP
     * literal of the dev host, "dmsm-dev.example.com" (whose first label is
     * "dmsm-dev", not "dev") and any unrelated host all passed. Only hosts
     * named here, or in the optional
     * bioland.settings.dmsm_config_prod_host_allowlist override, may serve a
     * production site's geography.
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

        $host = $this->normalizeHost((string) parse_url($baseUrl, PHP_URL_HOST));
        $allowlist = $this->getProductionHostAllowlist();

        if (in_array($host, $allowlist, true)) {
            return null;
        }

        return sprintf(
            'Refusing DMSM geography fetch: environment is "%s" but bioland.settings.%s points at "%s", which is '
            . 'not in the production host allowlist (%s). Name this environment\'s production config host in '
            . 'bioland.settings.%s (deploy-time configuration; no production host ships in code).',
            $env,
            self::CONFIG_BASE_URL_KEY,
            $host,
            $allowlist === [] ? 'empty - no production host is configured' : implode(', ', $allowlist),
            self::CONFIG_PROD_HOST_ALLOWLIST_KEY
        );
    }

    /**
     * The hosts a production site may fetch from.
     *
     * @return string[]
     *   Normalised host names.
     */
    protected function getProductionHostAllowlist()
    {
        $configured = $this->configFactory->get('bioland.settings')->get(self::CONFIG_PROD_HOST_ALLOWLIST_KEY);
        $hosts = [];

        if (is_array($configured)) {
            foreach ($configured as $host) {
                if (is_string($host) && trim($host) !== '') {
                    $hosts[] = $this->normalizeHost($host);
                }
            }
        }

        return $hosts === [] ? self::PRODUCTION_HOST_ALLOWLIST : array_values(array_unique($hosts));
    }

    /**
     * Normalise a host for comparison.
     *
     * Lower-cases it and strips the trailing dot of the fully-qualified form:
     * "dmsm.cbddev.xyz." and "dmsm.cbddev.xyz" resolve identically in DNS, so
     * they must compare identically here too.
     *
     * @param string $host
     *   The raw host.
     *
     * @return string
     *   The normalised host.
     */
    protected function normalizeHost($host)
    {
        return rtrim(strtolower(trim((string) $host)), '.');
    }

    /**
     * Refuse a URL the worker must not connect to.
     *
     * Applied to the built request URL before connecting, and again to every
     * redirect target. See the class docblock for why this process is a
     * valuable SSRF position.
     *
     * @param string $url
     *   The URL about to be fetched.
     * @param string $env
     *   The resolved environment; plain http is permitted off production only.
     *
     * @throws \RuntimeException
     *   When the URL must not be fetched. The message names the reason and the
     *   host, never any credential.
     */
    protected function assertUrlIsFetchable($url, $env)
    {
        $parts = parse_url((string) $url);

        if (!is_array($parts)) {
            throw new \RuntimeException('the configured URL cannot be parsed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException(
                'the configured URL carries embedded credentials (user:password@host), which are forbidden.'
            );
        }

        $scheme = strtolower((string) (isset($parts['scheme']) ? $parts['scheme'] : ''));

        if ($scheme !== 'https' && !($scheme === 'http' && $env !== 'prod')) {
            throw new \RuntimeException(sprintf(
                'scheme "%s" is not permitted (https is required%s).',
                $scheme === '' ? '(none)' : $scheme,
                $env === 'prod' ? ' on production' : '; http is allowed off production only'
            ));
        }

        $host = $this->normalizeHost((string) (isset($parts['host']) ? $parts['host'] : ''));

        if ($host === '') {
            throw new \RuntimeException('the configured URL has no host.');
        }

        if (in_array($host, self::FORBIDDEN_HOSTS, true)) {
            throw new \RuntimeException(sprintf('host "%s" is never permitted.', $host));
        }

        foreach (self::FORBIDDEN_HOST_SUFFIXES as $suffix) {
            if (substr($host, -strlen($suffix)) === $suffix) {
                throw new \RuntimeException(sprintf('host "%s" uses the forbidden suffix "%s".', $host, $suffix));
            }
        }

        $addresses = $this->resolveHostAddresses($host);

        if ($addresses === []) {
            throw new \RuntimeException(sprintf('host "%s" does not resolve to any address.', $host));
        }

        foreach ($addresses as $address) {
            if (!$this->isPublicIpAddress($address)) {
                throw new \RuntimeException(sprintf(
                    'host "%s" resolves to %s, which is loopback, private, link-local, CGNAT or otherwise reserved.',
                    $host,
                    $address
                ));
            }
        }
    }

    /**
     * Resolve a host to every address it points at.
     *
     * An IP literal resolves to itself. Split out so tests can supply a fixed
     * map instead of depending on live DNS.
     *
     * @param string $host
     *   The normalised host.
     *
     * @return string[]
     *   The resolved addresses; empty when the host does not resolve.
     */
    protected function resolveHostAddresses($host)
    {
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $addresses = gethostbynamel($host);
        $addresses = is_array($addresses) ? $addresses : [];

        $records = @dns_get_record($host, DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Whether an address is publicly routable.
     *
     * FILTER_FLAG_NO_PRIV_RANGE covers RFC1918 and the IPv6 unique-local and
     * link-local ranges; FILTER_FLAG_NO_RES_RANGE covers loopback, 169.254/16,
     * 0.0.0.0/8 and 240/4. CGNAT (100.64.0.0/10) is in neither, so it is
     * checked explicitly.
     *
     * @param string $address
     *   The address to test.
     *
     * @return bool
     *   TRUE when the address may be connected to.
     */
    protected function isPublicIpAddress($address)
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($address);

            // 100.64.0.0/10 - carrier-grade NAT.
            if ($long !== false && ($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The Guzzle options for the geography fetch.
     *
     * Redirects are capped at one hop and the target is re-checked by the same
     * guard, because Guzzle's default (5 hops, unchecked) lets a benign-looking
     * host 302 this CLI worker onto loopback or 169.254.169.254.
     *
     * @param string $env
     *   The resolved environment.
     *
     * @return array
     *   The request options.
     */
    protected function buildRequestOptions($env)
    {
        $service = $this;

        return [
            'timeout' => self::HTTP_TIMEOUT,
            'connect_timeout' => self::HTTP_CONNECT_TIMEOUT,
            'allow_redirects' => [
                'max' => 1,
                'strict' => true,
                'referer' => false,
                'protocols' => $env === 'prod' ? ['https'] : ['http', 'https'],
                'track_redirects' => false,
                'on_redirect' => function ($request, $response, $uri) use ($service, $env) {
                    $service->assertRedirectTargetIsFetchable((string) $uri, $env);
                },
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Re-apply the fetch-target guard to a redirect target.
     *
     * Public because Guzzle invokes it through the on_redirect closure.
     *
     * @param string $uri
     *   The redirect target.
     * @param string $env
     *   The resolved environment.
     *
     * @throws \RuntimeException
     *   When the redirect target must not be followed.
     */
    public function assertRedirectTargetIsFetchable($uri, $env)
    {
        try {
            $this->assertUrlIsFetchable($uri, $env);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf(
                'Refusing to follow DMSM redirect to %s: %s',
                $this->sanitizeUrlForLog($uri),
                $e->getMessage()
            ));
        }
    }

    /**
     * Read at most self::MAX_RESPONSE_BYTES of the response body.
     *
     * A hostile or broken config host can otherwise stream an unbounded body
     * straight into memory. Content-Length is checked first when the response
     * declares one; it is only a hint, so the read itself is bounded too.
     *
     * @param object $response
     *   The HTTP response.
     *
     * @return string
     *   The body.
     *
     * @throws \Exception
     *   When the body exceeds the cap.
     */
    protected function readBoundedBody($response)
    {
        if (method_exists($response, 'getHeaderLine')) {
            $declared = $response->getHeaderLine('Content-Length');

            if (is_numeric($declared) && (int) $declared > self::MAX_RESPONSE_BYTES) {
                throw new \Exception(sprintf(
                    'DMSM API response declares %d bytes, over the %d byte cap',
                    (int) $declared,
                    self::MAX_RESPONSE_BYTES
                ));
            }
        }

        $stream = $response->getBody();
        $contents = '';

        if (method_exists($stream, 'read') && method_exists($stream, 'eof')) {
            while (!$stream->eof() && strlen($contents) <= self::MAX_RESPONSE_BYTES) {
                $chunk = $stream->read(8192);

                if ($chunk === '' || $chunk === false || $chunk === null) {
                    break;
                }

                $contents .= $chunk;
            }
        } else {
            $contents = (string) $stream->getContents();
        }

        if (strlen($contents) > self::MAX_RESPONSE_BYTES) {
            throw new \Exception(sprintf(
                'DMSM API response exceeds the %d byte cap',
                self::MAX_RESPONSE_BYTES
            ));
        }

        return $contents;
    }

    /**
     * Validate and normalise the countries list from the response.
     *
     * The list is remote input: it is length-capped, and every entry must be a
     * two-letter code. The previous array_map('strval', ...) turned a nested
     * array into the literal string "Array" plus a PHP 8 warning.
     *
     * @param array $countries
     *   The raw list from the response.
     *
     * @return string[]
     *   The validated, lower-cased codes.
     *
     * @throws \Exception
     *   When the list is too long, or an entry is not a country code.
     */
    protected function normalizeCountries(array $countries)
    {
        if (count($countries) > self::MAX_COUNTRIES) {
            throw new \Exception(sprintf(
                'DMSM API returned %d countries, over the %d entry cap',
                count($countries),
                self::MAX_COUNTRIES
            ));
        }

        $normalized = [];

        foreach ($countries as $country) {
            if (!is_string($country) && !is_int($country)) {
                throw new \Exception('DMSM API returned a non-scalar country entry');
            }

            $code = strtolower(trim((string) $country));

            if ($code === '') {
                continue;
            }

            if (!preg_match('/^[a-z]{2}$/', $code)) {
                throw new \Exception(sprintf(
                    'DMSM API returned an invalid country code: %s',
                    $this->sanitizeLogText($code)
                ));
            }

            $normalized[] = $code;
        }

        if ($normalized === []) {
            throw new \Exception('No valid countries after filtering DMSM API response');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * A bounded preview of the countries list, for the success log.
     *
     * @param string[] $countries
     *   The validated codes.
     *
     * @return string
     *   At most the first five codes.
     */
    protected function summarizeCountries(array $countries)
    {
        $preview = implode(', ', array_slice($countries, 0, 5));

        return count($countries) > 5 ? $preview . ', ...' : $preview;
    }

    /**
     * A URL reduced to scheme, host, port and path, for logging.
     *
     * Userinfo and the query string never reach the log: a base URL carrying
     * credentials would otherwise put them into watchdog and every log shipper
     * downstream of it.
     *
     * @param string $url
     *   The URL.
     *
     * @return string
     *   The loggable form.
     */
    protected function sanitizeUrlForLog($url)
    {
        $parts = parse_url((string) $url);

        if (!is_array($parts) || !isset($parts['host'])) {
            return '[unparseable URL]';
        }

        return sprintf(
            '%s%s%s%s',
            isset($parts['scheme']) ? $parts['scheme'] . '://' : '',
            $this->normalizeHost($parts['host']),
            isset($parts['port']) ? ':' . $parts['port'] : '',
            isset($parts['path']) ? $parts['path'] : ''
        );
    }

    /**
     * Strip credentials and newlines from third-party text before logging.
     *
     * A Guzzle RequestException message carries the full request URI, so a
     * base URL with userinfo leaks through it. Newlines are collapsed because
     * a value under remote control can otherwise inject fake watchdog lines.
     *
     * @param string $text
     *   The text.
     *
     * @return string
     *   The loggable form, truncated to self::MAX_LOGGED_TEXT.
     */
    protected function sanitizeLogText($text)
    {
        $text = (string) preg_replace('#([a-z][a-z0-9+.\-]*://)[^/@\s]*@#i', '$1', (string) $text);
        $text = (string) preg_replace('/[\r\n\t]+/', ' ', $text);

        if (strlen($text) > self::MAX_LOGGED_TEXT) {
            $text = substr($text, 0, self::MAX_LOGGED_TEXT) . '...';
        }

        return $text;
    }

    /**
     * Read the site's EFFECTIVE dmsm theme, per the plan's pinned MD rule.
     *
     * Read-only: this method never writes config. It exists so the Theme tab
     * (\Drupal\bioland\Form\BiolandThemeForm) can pre-populate its fields for a
     * site that has not authored `bioland.settings:theme` yet (plan decision
     * D5, lazy seed). The editor's save is what persists anything.
     *
     * The MD rule is a DECLARED INPUT, taken from bioland-head ADR 0012
     * ("Head theme resolution: precedence, merge, and columns") and the shared
     * fixture tests/Unit/fixtures/theme-effective-values.json. It is not
     * re-derived here:
     *
     * - dmsm performs NO merge. Its config document carries two independent
     *   theme objects side by side: `theme` (the site's own block) and
     *   `runTime.theme` (the multiSite/network block copied verbatim). See
     *   dmsm server/utils/config/index.js:236-251.
     * - Merge is PER-LEAF, site over network: a leaf the site block omits
     *   falls through to the network block. A leaf is a scalar or a list
     *   (`hero.primary`, `homePageWidgets.columns` are replaced wholesale,
     *   never element-merged).
     * - `hero` is DERIVED to [color.primary, color.secondary] only when
     *   `hero.primary` is absent from EVERY source. An authored hero is never
     *   recomputed: four live prod sites (be, e2e, han, rjh) carry
     *   `hero.primary[1] = #CBB279`, which the derived formula would replace
     *   with `#889262`. `hero` itself is never authorable in the Theme tab
     *   (D4) -- it is carried through here only so the effective value the
     *   fixture pins is the real one.
     *
     * Keys here are the head/dmsm camelCase spellings (`backGround`,
     * `homePageWidgets`, `megaMenu`, `maxLangBeforeWrap`) at the dmsm document
     * depth. That is a DIFFERENT contract from the snake_case
     * `bioland.settings:theme` keys the Theme tab writes (`back_ground`,
     * `home_page_widgets`, `mega_menu`, `max_lang_before_wrap`); the form owns
     * the translation between the two, and the two depths are pinned by
     * separate assertions.
     *
     * Deliberately additive: the fetch/decode block below duplicates
     * self::updateCountriesFromDmsm() (:86-116) rather than extracting a
     * shared helper, so that live write path keeps a zero-line diff. Factoring
     * the two together is a separate, base-branched refactor.
     *
     * @param string|null $hostname
     *   The hostname to resolve. If NULL, the current request host is used.
     *
     * @return array|null
     *   The effective theme keyed by the head's camelCase names, or NULL when
     *   the hostname cannot be parsed, the request fails, or the document
     *   carries no theme block at either level.
     */
    public function getEffectiveTheme($hostname = null)
    {
        if ($hostname === null) {
            $request = \Drupal::request();
            $hostname = $request->getHost();
        }

        $params = $this->parseHostname($hostname);

        if (!$params) {
            $this->logger->error(sprintf('Unable to parse hostname for theme seed: %s', $hostname));
            return null;
        }

        // Same configured base as the geography fetch: no host ships in code.
        $baseUrl = $this->getConfiguredBaseUrl();

        if ($baseUrl === null) {
            $this->logger->error(sprintf(
                'DMSM config base URL is not set (bioland.settings.%s); refusing to fetch the theme. '
                . 'There is no fallback host in code.',
                self::CONFIG_BASE_URL_KEY
            ));
            return null;
        }

        $guardError = $this->checkBaseUrlAgainstEnv($params['env'], $baseUrl);

        if ($guardError !== null) {
            $this->logger->error($guardError);
            return null;
        }

        $url = sprintf(
            '%s/api/config/%s/%s/%s',
            $baseUrl,
            $params['env'],
            $params['multiSiteCode'],
            $params['siteCode']
        );

        // The configured value is untrusted input; this process can reach
        // loopback, the container network and the metadata endpoint.
        try {
            $this->assertUrlIsFetchable($url, $params['env']);
        } catch (\RuntimeException $e) {
            $this->logger->error(sprintf(
                'Refusing DMSM theme fetch: %s',
                $this->sanitizeLogText($e->getMessage())
            ));
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, $this->buildRequestOptions($params['env']));

            $data = json_decode($this->readBoundedBody($response), true);

            // Handle double-encoded JSON: if the response is a JSON string, decode it again.
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            if (!$data || !is_array($data)) {
                throw new \Exception('Invalid JSON response from DMSM API - expected object');
            }

            $site = (isset($data['theme']) && is_array($data['theme'])) ? $data['theme'] : [];
            $network = (isset($data['runTime']['theme']) && is_array($data['runTime']['theme']))
                ? $data['runTime']['theme']
                : [];

            if ($site === [] && $network === []) {
                throw new \Exception('No theme block found in DMSM API response - expected theme or runTime.theme');
            }

            // Per-leaf merge, site over network. A list (hero.primary,
            // homePageWidgets.columns) is a leaf and is replaced wholesale;
            // only associative branches recurse.
            //
            // The empty-array case is why this is not a bare
            // !array_is_list() test on each side. json_decode() renders an
            // empty JSON OBJECT (`"megaMenu": {}`) and an empty JSON ARRAY
            // (`"megaMenu": []`) as the same PHP `[]`, and array_is_list([])
            // is TRUE -- so a site block carrying an empty object would be
            // classified as a list, replace the whole network branch
            // wholesale, and wipe every leaf the network defined. That is
            // exactly the per-leaf merge the MD rule forbids. `[]` therefore
            // counts as a branch on BOTH operands: as an override it
            // contributes no leaves and the network branch survives intact,
            // and as a base it is simply filled by the override.
            $isBranch = static fn($value): bool => is_array($value)
                && ($value === [] || !array_is_list($value));

            $mergeLeaves = function (array $base, array $override) use (&$mergeLeaves, $isBranch) {
                foreach ($override as $key => $value) {
                    $recurse = $isBranch($value)
                        && isset($base[$key])
                        && $isBranch($base[$key]);
                    $base[$key] = $recurse ? $mergeLeaves($base[$key], $value) : $value;
                }

                return $base;
            };

            $effective = $mergeLeaves($network, $site);

            // Derive hero ONLY when absent from every source. array_key_exists,
            // not isset: an explicit NULL still counts as authored.
            $heroAuthored = (isset($site['hero']) && is_array($site['hero']) && array_key_exists('primary', $site['hero']))
                || (isset($network['hero']) && is_array($network['hero']) && array_key_exists('primary', $network['hero']));

            if (!$heroAuthored
                && isset($effective['color']['primary'], $effective['color']['secondary'])) {
                $effective['hero']['primary'] = [
                    $effective['color']['primary'],
                    $effective['color']['secondary'],
                ];
            }

            return $effective;
        } catch (RequestException $e) {
            $this->logger->error(sprintf('HTTP error fetching DMSM theme: %s', $e->getMessage()));
            return null;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error processing DMSM theme: %s', $e->getMessage()));
            return null;
        }
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

    /**
     * Report whether the configured base URL would be accepted right now.
     *
     * Used by hook_requirements() so a refusal is visible on the status report
     * instead of being a silent data freeze: on refusal the site simply keeps
     * whatever countries it already has (or the seeded value on a fresh
     * install) and nothing surfaces it anywhere an operator looks.
     *
     * Deliberately does NOT resolve DNS - the status report must not block on
     * a name lookup. It checks presence, shape, credentials, scheme and the
     * production allowlist; the address checks still run at fetch time.
     *
     * @param string|null $hostname
     *   The site hostname, or NULL to take the current request's host.
     *
     * @return array
     *   'status' is one of 'ok', 'unset' or 'refused'; 'message' explains a
     *   non-ok status; 'value' is the loggable form of the configured URL.
     */
    public function checkConfiguredBaseUrl($hostname = null)
    {
        $baseUrl = $this->getConfiguredBaseUrl();

        if ($baseUrl === null) {
            return [
                'status' => 'unset',
                'message' => sprintf(
                    'bioland.settings.%s is not set. The geography fetch refuses to run and this site keeps its '
                    . 'current countries, region and continent indefinitely. There is no fallback host in code.',
                    self::CONFIG_BASE_URL_KEY
                ),
                'value' => '',
            ];
        }

        $value = $this->sanitizeUrlForLog($baseUrl);
        $hostname = $this->resolveHostname($hostname);
        $params = $hostname === null ? null : $this->parseHostname($hostname);

        if (!$params) {
            return [
                'status' => 'refused',
                'message' => sprintf('Unable to parse the site hostname "%s"; the geography fetch cannot run.', (string) $hostname),
                'value' => $value,
            ];
        }

        $guardError = $this->checkBaseUrlAgainstEnv($params['env'], $baseUrl);

        if ($guardError !== null) {
            return ['status' => 'refused', 'message' => $guardError, 'value' => $value];
        }

        $parts = parse_url($baseUrl);

        if (isset($parts['user']) || isset($parts['pass'])) {
            return [
                'status' => 'refused',
                'message' => 'The configured base URL carries embedded credentials, which are forbidden.',
                'value' => $value,
            ];
        }

        $scheme = strtolower((string) (isset($parts['scheme']) ? $parts['scheme'] : ''));

        if ($scheme !== 'https' && !($scheme === 'http' && $params['env'] !== 'prod')) {
            return [
                'status' => 'refused',
                'message' => sprintf('The configured base URL uses the scheme "%s"; https is required.', $scheme),
                'value' => $value,
            ];
        }

        return ['status' => 'ok', 'message' => '', 'value' => $value];
    }
}
