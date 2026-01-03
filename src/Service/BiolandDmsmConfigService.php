<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for fetching configuration from DMSM API.
 */
class BiolandDmsmConfigService
{
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
     * Constructs a BiolandDmsmConfigService object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory.
     * @param \GuzzleHttp\ClientInterface $http_client
     *   The HTTP client.
     * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
     *   The logger factory.
     */
    public function __construct(
        ConfigFactoryInterface $config_factory,
        ClientInterface $http_client,
        LoggerChannelFactoryInterface $logger_factory
    ) {
        $this->configFactory = $config_factory;
        $this->httpClient = $http_client;
        $this->logger = $logger_factory->get('bioland');
    }

    /**
     * Fetch and update countries configuration from DMSM API.
     *
     * @param string|null $hostname
     *   The hostname to parse. If NULL, will use the current request host.
     *
     * @return array
     *   Array containing 'success' (bool) and 'message' (string).
     */
    public function updateCountriesFromDmsm($hostname = null)
    {
        // Get hostname from current request if not provided.
        if ($hostname === null) {
            $request = \Drupal::request();
            $hostname = $request->getHost();
        }

        // Parse hostname to get env, multiSiteCode, and siteCode.
        $params = $this->parseHostname($hostname);
        
        if (!$params) {
            $message = sprintf('Unable to parse hostname: %s', $hostname);
            $this->logger->error($message);
            return ['success' => false, 'message' => $message];
        }

        $env = $params['env'];
        $multiSiteCode = $params['multiSiteCode'];
        $siteCode = $params['siteCode'];

        // Build API URL.
        $url = sprintf(
            'https://dmsm.cbddev.xyz/api/config/%s/%s/%s',
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
