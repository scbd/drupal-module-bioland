<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\TestCase;

/**
 * A service with the two host-environment bindings replaced by test seams.
 *
 * Two things the real service reads from the host are unavailable to a unit
 * test: the SAPI name (always "cli" under PHPUnit, so the web-request refusal
 * could never be exercised) and live DNS (a unit test must neither depend on
 * it nor be able to reach a real address). Both are overridden here.
 *
 * IP literals are deliberately NOT faked: ::resolveHostAddresses() returns the
 * literal itself, exactly as the parent does, so every address-range check in
 * ::isPublicIpAddress() runs for real against 127.0.0.1, 169.254.169.254 and
 * friends. Only name resolution is stubbed.
 */
class TestableDmsmConfigService extends BiolandDmsmConfigService
{
    /**
     * The SAPI name this instance reports.
     *
     * @var string
     */
    public $sapiName = 'cli';

    /**
     * Host name to address list, for the DNS seam.
     *
     * @var array
     */
    public $addressMap = [];

    /**
     * Addresses returned for a host not in the map: one public address.
     *
     * @var string[]
     */
    public $defaultAddresses = ['93.184.216.34'];

    /**
     * {@inheritdoc}
     */
    protected function getSapiName()
    {
        return $this->sapiName;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveHostAddresses($host)
    {
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        return isset($this->addressMap[$host]) ? $this->addressMap[$host] : $this->defaultAddresses;
    }

    /**
     * Exposes the Guzzle options so the redirect wiring is directly testable.
     *
     * @param string $env
     *   The resolved environment.
     *
     * @return array
     *   The request options.
     */
    public function requestOptionsFor($env)
    {
        return $this->buildRequestOptions($env);
    }
}

/**
 * Tests the configured base URL, the SSRF guard, the production allowlist and
 * the worker-only fetch.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandDmsmConfigService
 * @group bioland
 */
class BiolandDmsmConfigGeographyFetchTest extends TestCase
{
    /**
     * A dev site hostname, parsed to env "dev".
     */
    const DEV_SITE = 'example.bl2.cbddev.xyz';

    /**
     * A prod site hostname, parsed to env "prod".
     */
    const PROD_SITE = 'demo.bl2.chm-cbd.net';

    /**
     * The host a production site is allowed to fetch from, in these tests.
     */
    const PROD_ALLOWED_HOST = 'config.prod.test';

    /**
     * The config factory mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $configFactory;

    /**
     * The HTTP client mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $httpClient;

    /**
     * The queue mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $queue;

    /**
     * The queue factory mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $queueFactory;

    /**
     * The request stack mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $requestStack;

    /**
     * The logger mock.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $logger;

    /**
     * The mutable config the service writes back to.
     *
     * @var \Drupal\Core\Config\Config
     */
    protected $editableConfig;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $this->httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $this->queue = $this->createMock('Drupal\Core\Queue\QueueInterface');
        $this->queueFactory = $this->createMock('Drupal\Core\Queue\QueueFactory');
        $this->requestStack = $this->createMock('Symfony\Component\HttpFoundation\RequestStack');
        $this->logger = $this->createMock('Drupal\Core\Logger\LoggerChannelInterface');

        $this->queueFactory->method('get')->willReturn($this->queue);
        $this->editableConfig = new Config('bioland.settings', []);
    }

    /**
     * Builds a service instance with the configured base URL value.
     *
     * @param string|null $baseUrl
     *   The value of bioland.settings.dmsm_config_base_url, or NULL to leave
     *   the key unset entirely.
     * @param array $extra
     *   Extra bioland.settings values, for example the prod host allowlist.
     *
     * @return \Drupal\Tests\bioland\Unit\Service\TestableDmsmConfigService
     *   The service.
     */
    protected function buildService($baseUrl, array $extra = [])
    {
        $data = $baseUrl === null ? [] : ['dmsm_config_base_url' => $baseUrl];
        $data += $extra;

        $this->configFactory->method('get')->willReturn(new ImmutableConfig('bioland.settings', $data));
        $this->configFactory->method('getEditable')->willReturn($this->editableConfig);

        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $loggerFactory->method('get')->willReturn($this->logger);

        return new TestableDmsmConfigService(
            $this->configFactory,
            $this->httpClient,
            $loggerFactory,
            $this->queueFactory,
            $this->requestStack
        );
    }

    /**
     * Builds a service whose production allowlist names one host.
     *
     * @param string $baseUrl
     *   The configured base URL.
     *
     * @return \Drupal\Tests\bioland\Unit\Service\TestableDmsmConfigService
     *   The service.
     */
    protected function buildProdService($baseUrl)
    {
        return $this->buildService($baseUrl, [
            'dmsm_config_prod_host_allowlist' => [self::PROD_ALLOWED_HOST],
        ]);
    }

    /**
     * Makes a response stub carrying a serialized geography document.
     *
     * @param array $document
     *   The decoded document to serve.
     *
     * @return object
     *   The response stub.
     */
    protected function expectHttpFetch(array $document)
    {
        $body = new class (json_encode($document)) {
            /**
             * The serialized body.
             *
             * @var string
             */
            private $contents;

            /**
             * Constructs the body stub.
             *
             * @param string $contents
             *   The serialized body.
             */
            public function __construct($contents)
            {
                $this->contents = $contents;
            }

            /**
             * Returns the serialized body.
             *
             * @return string
             *   The body.
             */
            public function getContents()
            {
                return $this->contents;
            }
        };

        return new class ($body) {
            /**
             * The body stub.
             *
             * @var object
             */
            private $body;

            /**
             * Constructs the response stub.
             *
             * @param object $body
             *   The body stub.
             */
            public function __construct($body)
            {
                $this->body = $body;
            }

            /**
             * Returns the body stub.
             *
             * @return object
             *   The body.
             */
            public function getBody()
            {
                return $this->body;
            }
        };
    }

    /**
     * The URL is built from the configured base URL, not a hardcoded host.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testUrlIsBuiltFromConfiguredBaseUrl()
    {
        $service = $this->buildService('https://config.example.test/');
        $response = $this->expectHttpFetch(['runTime' => ['countries' => ['be']], 'region' => 'europe']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://config.example.test/api/config/dev/bl2/example',
                $this->anything()
            )
            ->willReturn($response);

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * The existing config write-back still runs after a worker fetch.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testWriteBackStillRuns()
    {
        $service = $this->buildService('https://config.example.test');
        $response = $this->expectHttpFetch([
            'runTime' => ['countries' => ['be', 'fr']],
            'region' => 'europe',
            'continent' => 'EU',
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $service->updateCountriesFromDmsm('example.bsl.cbddev.xyz');

        $this->assertTrue($result['success'], $result['message']);
        $this->assertTrue($this->editableConfig->saved);
        $this->assertSame(['be', 'fr'], $this->editableConfig->get('countries'));
        $this->assertTrue($this->editableConfig->get('is_biosafety_land'));
        $this->assertSame('europe', $this->editableConfig->get('region'));
        $this->assertSame('EU', $this->editableConfig->get('continent'));
    }

    /**
     * An unset base URL fails loudly and never falls back to a hardcoded host.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testUnsetBaseUrlFailsWithoutRequest()
    {
        $service = $this->buildService(null);

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('dmsm_config_base_url', $result['message']);
        $this->assertStringContainsString('no fallback host in code', $result['message']);
    }

    /**
     * An empty base URL is treated as unset, not as a relative URL.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testEmptyBaseUrlFailsWithoutRequest()
    {
        $service = $this->buildService('   ');

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('is not set', $result['message']);
    }

    /* ---------------------------------------------------------------------
     * BLOCK 1 - SSRF: the fetch target itself.
     * ------------------------------------------------------------------ */

    /**
     * Every SSRF payload the review proved reachable is now refused.
     *
     * The fetch runs as CLI on the application host, so loopback, the
     * container network and the cloud metadata endpoint are all reachable
     * from it. Each of these passed the previous "non-empty parsed host"
     * check.
     *
     * @covers ::updateCountriesFromDmsm
     *
     * @dataProvider ssrfPayloadProvider
     */
    public function testSsrfPayloadsAreRefusedBeforeConnecting($baseUrl, $expected)
    {
        $service = $this->buildService($baseUrl);

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success'], 'Payload must be refused: ' . $baseUrl);
        $this->assertStringContainsString($expected, $result['message']);
    }

    /**
     * Data provider of fetch targets that must never be connected to.
     *
     * @return array
     *   Base URL, plus an expected fragment of the refusal message.
     */
    public function ssrfPayloadProvider()
    {
        return [
            'cloud metadata endpoint' => ['http://169.254.169.254', 'link-local'],
            'loopback with a port' => ['http://127.0.0.1:8080', 'loopback'],
            'loopback by name' => ['http://localhost', 'never permitted'],
            'rfc1918 private range' => ['http://10.1.2.3', 'private'],
            'rfc1918 192.168 range' => ['http://192.168.1.1', 'private'],
            'carrier-grade NAT' => ['http://100.64.0.1', 'CGNAT'],
            'gopher smuggling to redis' => ['gopher://127.0.0.1:6379/_x', 'not permitted'],
            'local file read' => ['file://localhost/etc/passwd', 'not permitted'],
            'embedded credentials' => ['https://user:pw@evil.example.com', 'embedded credentials'],
            'internal suffix' => ['https://config.internal', 'forbidden suffix'],
            'local suffix' => ['https://config.local', 'forbidden suffix'],
            'metadata by name' => ['https://metadata.google.internal', 'never permitted'],
        ];
    }

    /**
     * A public-looking name that resolves to loopback is still refused.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testHostResolvingToLoopbackIsRefused()
    {
        $service = $this->buildService('https://rebind.example.com');
        $service->addressMap['rebind.example.com'] = ['127.0.0.1'];

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('resolves to 127.0.0.1', $result['message']);
    }

    /**
     * A host that does not resolve at all is refused rather than attempted.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testUnresolvableHostIsRefused()
    {
        $service = $this->buildService('https://nowhere.example.com');
        $service->addressMap['nowhere.example.com'] = [];

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('does not resolve', $result['message']);
    }

    /**
     * Plain http is permitted off production and refused on it.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testHttpIsRefusedOnProduction()
    {
        $service = $this->buildProdService('http://' . self::PROD_ALLOWED_HOST);

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->updateCountriesFromDmsm(self::PROD_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('https is required', $result['message']);
    }

    /**
     * Guzzle's 5-hop default is replaced by one checked hop, plus timeouts.
     *
     * Left at the default, a benign host could 302 this CLI worker onto
     * loopback or 169.254.169.254 with the guard never re-applied.
     *
     * @covers ::buildRequestOptions
     */
    public function testRedirectsAreCappedAndTimeoutsAreSet()
    {
        $options = $this->buildService('https://config.example.test')->requestOptionsFor('dev');

        $this->assertSame(1, $options['allow_redirects']['max']);
        $this->assertSame(['http', 'https'], $options['allow_redirects']['protocols']);
        $this->assertIsCallable($options['allow_redirects']['on_redirect']);
        $this->assertGreaterThan(0, $options['connect_timeout']);
        $this->assertGreaterThan(0, $options['timeout']);
    }

    /**
     * On production only https may be followed across a redirect.
     *
     * @covers ::buildRequestOptions
     */
    public function testProductionRedirectsAreHttpsOnly()
    {
        $options = $this->buildProdService('https://' . self::PROD_ALLOWED_HOST)->requestOptionsFor('prod');

        $this->assertSame(['https'], $options['allow_redirects']['protocols']);
    }

    /**
     * The same guard is re-applied to a redirect target, through Guzzle's hook.
     *
     * @covers ::assertRedirectTargetIsFetchable
     *
     * @dataProvider redirectTargetProvider
     */
    public function testRedirectToAForbiddenTargetIsRefused($target)
    {
        $service = $this->buildService('https://config.example.test');
        $options = $service->requestOptionsFor('dev');
        $onRedirect = $options['allow_redirects']['on_redirect'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to follow DMSM redirect');

        $onRedirect(null, null, $target);
    }

    /**
     * Data provider of redirect targets that must not be followed.
     *
     * @return array
     *   The targets.
     */
    public function redirectTargetProvider()
    {
        return [
            'loopback' => ['http://127.0.0.1:8080/admin'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private range' => ['http://10.0.0.5/'],
            'embedded credentials' => ['https://user:pw@evil.example.com/'],
            'gopher' => ['gopher://127.0.0.1:6379/_x'],
        ];
    }

    /**
     * A redirect to another public https host passes the guard.
     *
     * @covers ::assertRedirectTargetIsFetchable
     */
    public function testRedirectToAPublicHostIsAllowed()
    {
        $service = $this->buildService('https://config.example.test');
        $options = $service->requestOptionsFor('dev');
        $onRedirect = $options['allow_redirects']['on_redirect'];

        $this->assertNull($onRedirect(null, null, 'https://config.example.test/moved'));
    }

    /* ---------------------------------------------------------------------
     * BLOCK 2 - the production guard is an allowlist, not a denylist.
     * ------------------------------------------------------------------ */

    /**
     * Every documented bypass of the old denylist is refused on production.
     *
     * @covers ::updateCountriesFromDmsm
     *
     * @dataProvider refusedProdHostProvider
     */
    public function testProdGuardRefusesEveryHostOutsideTheAllowlist($baseUrl)
    {
        $service = $this->buildProdService($baseUrl);

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm(self::PROD_SITE);

        $this->assertFalse($result['success'], 'Must be refused on prod: ' . $baseUrl);
        $this->assertStringContainsString('production host allowlist', $result['message']);
    }

    /**
     * Data provider of base URLs a prod site must refuse.
     *
     * The first three are the verified bypasses of the retired denylist: a
     * trailing dot ("dmsm.cbddev.xyz." resolves identically in DNS), a bare IP
     * literal of the dev host, and a host whose first label is "dmsm-dev"
     * rather than "dev". The rest show the denylist's other half - any
     * unrelated host passed it untouched - and that the allowlist compares
     * whole hosts, not suffixes or prefixes.
     *
     * @return array
     *   The base URLs.
     */
    public function refusedProdHostProvider()
    {
        return [
            'trailing-dot form of the dev host' => ['https://dmsm.cbddev.xyz.'],
            'bare IP literal of the dev host' => ['https://203.0.113.10'],
            'a "dmsm-dev" label the denylist missed' => ['https://dmsm-dev.example.com'],
            'an entirely unrelated host' => ['https://evil.example.com'],
            'the retired hardcoded dev host' => ['https://dmsm.cbddev.xyz'],
            'a dev host label' => ['https://config.dev.cbd.int'],
            'a stg host label' => ['https://config.stg.cbd.int'],
            'a staging host label' => ['https://config.staging.cbd.int'],
            'allowlisted host as a subdomain prefix' => ['https://config.prod.test.evil.example.com'],
            'allowlisted host behind an extra label' => ['https://evil.config.prod.test.example.com'],
        ];
    }

    /**
     * A prod site pointed at an allowlisted host is permitted.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testProdGuardPermitsAnAllowlistedHost()
    {
        $service = $this->buildProdService('https://' . self::PROD_ALLOWED_HOST);
        $response = $this->expectHttpFetch(['country' => 'be']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://' . self::PROD_ALLOWED_HOST . '/api/config/prod/bl2/demo',
                $this->anything()
            )
            ->willReturn($response);

        $result = $service->updateCountriesFromDmsm(self::PROD_SITE);

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * The trailing-dot form of an allowlisted host is normalised, not refused.
     *
     * Normalisation has to cut both ways: "config.prod.test." is the same host
     * in DNS, so it must compare equal here too.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testTrailingDotOnAnAllowlistedHostIsNormalised()
    {
        $service = $this->buildProdService('https://' . self::PROD_ALLOWED_HOST . '.');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->expectHttpFetch(['country' => 'be']));

        $result = $service->updateCountriesFromDmsm(self::PROD_SITE);

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * With no allowlist configured, production refuses everything and says so.
     *
     * No production host ships in code, so an unconfigured production site
     * must fail loudly rather than guess.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testProductionWithNoAllowlistRefusesAndExplains()
    {
        $service = $this->buildService('https://' . self::PROD_ALLOWED_HOST);

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->updateCountriesFromDmsm(self::PROD_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no production host is configured', $result['message']);
        $this->assertStringContainsString('dmsm_config_prod_host_allowlist', $result['message']);
    }

    /**
     * Non-prod environments are not subject to the production allowlist.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testGuardDoesNotApplyOutsideProd()
    {
        $service = $this->buildService('https://dmsm.cbddev.xyz');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->expectHttpFetch(['country' => 'be']));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertTrue($result['success'], $result['message']);
    }

    /* ---------------------------------------------------------------------
     * MAJOR B - what reaches watchdog.
     * ------------------------------------------------------------------ */

    /**
     * A Guzzle error message never carries userinfo into the log.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testRequestExceptionMessageIsStrippedOfCredentials()
    {
        $service = $this->buildService('https://config.example.test');
        $logged = [];

        $this->logger->method('error')->willReturnCallback(function ($message) use (&$logged) {
            $logged[] = $message;
        });

        $this->httpClient->method('request')->willThrowException(
            new \GuzzleHttp\Exception\RequestException(
                "cURL error 7: failed to connect to https://svc:hunter2@config.example.test/api\nsecond line"
            )
        );

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['transient'], 'An HTTP error is transient, so the worker requeues it.');
        $this->assertStringNotContainsString('hunter2', implode("\n", $logged));
        $this->assertStringNotContainsString('hunter2', $result['message']);
        $this->assertStringNotContainsString("\n", $result['message']);
    }

    /* ---------------------------------------------------------------------
     * MAJOR D - the response body is untrusted input.
     * ------------------------------------------------------------------ */

    /**
     * An oversized countries list is rejected, not written to config.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testOversizedCountriesListIsRejected()
    {
        $service = $this->buildService('https://config.example.test');
        $countries = array_fill(0, BiolandDmsmConfigService::MAX_COUNTRIES + 1, 'be');

        $this->httpClient->method('request')
            ->willReturn($this->expectHttpFetch(['runTime' => ['countries' => $countries]]));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('entry cap', $result['message']);
        $this->assertFalse($this->editableConfig->saved);
    }

    /**
     * A malformed entry is rejected rather than coerced to the string "Array".
     *
     * @covers ::updateCountriesFromDmsm
     *
     * @dataProvider malformedCountryProvider
     */
    public function testMalformedCountryEntriesAreRejected(array $countries)
    {
        $service = $this->buildService('https://config.example.test');

        $this->httpClient->method('request')
            ->willReturn($this->expectHttpFetch(['runTime' => ['countries' => $countries]]));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertFalse($this->editableConfig->saved);
    }

    /**
     * Data provider of malformed countries payloads.
     *
     * @return array
     *   The payloads.
     */
    public function malformedCountryProvider()
    {
        return [
            'a nested array' => [[['be']]],
            'an object' => [[['code' => 'be']]],
            'a three-letter code' => [['bel']],
            'an injected log line' => [["be\nWARNING: fake watchdog line"]],
            'a path traversal attempt' => [['../../etc/passwd']],
            'an html payload' => [['<script>alert(1)</script>']],
        ];
    }

    /**
     * An oversized response body is refused before it is decoded.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testOversizedResponseBodyIsRefused()
    {
        $service = $this->buildService('https://config.example.test');

        $this->httpClient->method('request')->willReturn($this->expectHttpFetch([
            'runTime' => ['countries' => ['be']],
            'padding' => str_repeat('x', BiolandDmsmConfigService::MAX_RESPONSE_BYTES + 1),
        ]));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('byte cap', $result['message']);
        $this->assertFalse($this->editableConfig->saved);
    }

    /**
     * The success log carries a bounded preview, not the whole remote list.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testSuccessLogPreviewIsBounded()
    {
        $service = $this->buildService('https://config.example.test');
        $countries = ['be', 'fr', 'de', 'es', 'it', 'nl', 'pt', 'se'];

        $this->httpClient->method('request')
            ->willReturn($this->expectHttpFetch(['runTime' => ['countries' => $countries]]));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertStringContainsString('be, fr, de, es, it, ...', $result['message']);
        $this->assertStringNotContainsString('pt', $result['message']);
    }

    /* ---------------------------------------------------------------------
     * Execution context and enqueueing.
     * ------------------------------------------------------------------ */

    /**
     * The fetch refuses to run inside a web request.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testFetchIsRefusedInsideAWebRequest()
    {
        $service = $this->buildService('https://config.example.test');
        $service->sapiName = 'fpm-fcgi';

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Refusing to fetch', $result['message']);
        $this->assertStringContainsString('queue', $result['message']);
    }

    /**
     * The same fetch is permitted on the CLI, where a worker runs it.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testFetchIsPermittedOnCli()
    {
        $service = $this->buildService('https://config.example.test');
        $service->sapiName = 'cli';

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->expectHttpFetch(['country' => 'be']));

        $result = $service->updateCountriesFromDmsm(self::DEV_SITE);

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * Enqueueing pushes exactly one item and performs no HTTP request.
     *
     * @covers ::enqueueCountriesUpdate
     */
    public function testEnqueueQueuesTheItemAndMakesNoRequest()
    {
        $service = $this->buildService('https://config.example.test');

        $this->httpClient->expects($this->never())->method('request');
        $this->queueFactory->expects($this->once())
            ->method('get')
            ->with('bioland_dmsm_geography')
            ->willReturn($this->queue);
        $this->queue->expects($this->once())
            ->method('createItem')
            ->with(['hostname' => self::DEV_SITE]);

        $result = $service->enqueueCountriesUpdate(self::DEV_SITE);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['queued']);
    }

    /**
     * Enqueueing from a web request is allowed and still makes no HTTP call.
     *
     * @covers ::enqueueCountriesUpdate
     */
    public function testEnqueueFromWebRequestMakesNoRequest()
    {
        $service = $this->buildService('https://config.example.test');
        $service->sapiName = 'fpm-fcgi';

        $this->requestStack->method('getCurrentRequest')
            ->willReturn(new \Symfony\Component\HttpFoundation\Request(self::DEV_SITE));

        $this->httpClient->expects($this->never())->method('request');
        $this->queue->expects($this->once())->method('createItem');

        $result = $service->enqueueCountriesUpdate();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['queued']);
    }

    /**
     * An unparseable hostname is rejected before anything is queued.
     *
     * @covers ::enqueueCountriesUpdate
     */
    public function testEnqueueRejectsUnparseableHostname()
    {
        $service = $this->buildService('https://config.example.test');

        $this->queue->expects($this->never())->method('createItem');
        $this->httpClient->expects($this->never())->method('request');

        $result = $service->enqueueCountriesUpdate('invalid.example.com');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['queued']);
        $this->assertStringContainsString('Unable to parse hostname', $result['message']);
    }

    /**
     * With no hostname and no request, nothing is queued.
     *
     * @covers ::enqueueCountriesUpdate
     */
    public function testEnqueueWithoutHostnameOrRequestFails()
    {
        $service = $this->buildService('https://config.example.test');

        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->queue->expects($this->never())->method('createItem');

        $result = $service->enqueueCountriesUpdate();

        $this->assertFalse($result['success']);
        $this->assertFalse($result['queued']);
    }

    /* ---------------------------------------------------------------------
     * MAJOR C - the refusal is visible on the status report.
     * ------------------------------------------------------------------ */

    /**
     * The requirements helper reports an unset key rather than staying silent.
     *
     * @covers ::checkConfiguredBaseUrl
     */
    public function testCheckReportsAnUnsetBaseUrl()
    {
        $check = $this->buildService(null)->checkConfiguredBaseUrl(self::DEV_SITE);

        $this->assertSame('unset', $check['status']);
        $this->assertStringContainsString('dmsm_config_base_url', $check['message']);
    }

    /**
     * The requirements helper reports a production guard refusal.
     *
     * @covers ::checkConfiguredBaseUrl
     */
    public function testCheckReportsAProductionRefusal()
    {
        $check = $this->buildProdService('https://evil.example.com')
            ->checkConfiguredBaseUrl(self::PROD_SITE);

        $this->assertSame('refused', $check['status']);
        $this->assertStringContainsString('production host allowlist', $check['message']);
    }

    /**
     * The reported value never carries credentials.
     *
     * @covers ::checkConfiguredBaseUrl
     */
    public function testCheckStripsCredentialsFromTheReportedValue()
    {
        $check = $this->buildService('https://svc:hunter2@config.example.test')
            ->checkConfiguredBaseUrl(self::DEV_SITE);

        $this->assertSame('refused', $check['status']);
        $this->assertStringNotContainsString('hunter2', $check['value'] . $check['message']);
    }

    /**
     * A healthy configuration reports ok.
     *
     * @covers ::checkConfiguredBaseUrl
     */
    public function testCheckReportsOkForAValidConfiguration()
    {
        $check = $this->buildService('https://config.example.test')
            ->checkConfiguredBaseUrl(self::DEV_SITE);

        $this->assertSame('ok', $check['status']);
        $this->assertSame('https://config.example.test', $check['value']);
    }
}
