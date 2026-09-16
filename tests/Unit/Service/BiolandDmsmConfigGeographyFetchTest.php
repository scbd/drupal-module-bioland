<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\TestCase;

/**
 * A service whose SAPI can be forced, so the web-request guard is testable.
 */
class WebSapiDmsmConfigService extends BiolandDmsmConfigService
{
    /**
     * The SAPI name this instance reports.
     *
     * @var string
     */
    public $sapiName = 'fpm-fcgi';

    /**
     * {@inheritdoc}
     */
    protected function getSapiName()
    {
        return $this->sapiName;
    }
}

/**
 * Tests the configured base URL, the prod guard, and the worker-only fetch.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandDmsmConfigService
 * @group bioland
 */
class BiolandDmsmConfigGeographyFetchTest extends TestCase
{
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
     * @param string $class
     *   The service class to instantiate.
     *
     * @return \Drupal\bioland\Service\BiolandDmsmConfigService
     *   The service.
     */
    protected function buildService($baseUrl, $class = BiolandDmsmConfigService::class)
    {
        $data = $baseUrl === null ? [] : ['dmsm_config_base_url' => $baseUrl];
        $this->configFactory->method('get')->willReturn(new ImmutableConfig('bioland.settings', $data));
        $this->configFactory->method('getEditable')->willReturn($this->editableConfig);

        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $loggerFactory->method('get')->willReturn($this->logger);

        return new $class(
            $this->configFactory,
            $this->httpClient,
            $loggerFactory,
            $this->queueFactory,
            $this->requestStack
        );
    }

    /**
     * Makes the HTTP client return a valid geography document.
     *
     * @param array $document
     *   The decoded document to serve.
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

        $response = new class ($body) {
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

        return $response;
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

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

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

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

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

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('is not set', $result['message']);
    }

    /**
     * A prod site pointed at a dev or staging host is refused.
     *
     * @covers ::updateCountriesFromDmsm
     *
     * @dataProvider refusedProdHostProvider
     */
    public function testProdGuardRefusesNonProductionHost($baseUrl)
    {
        $service = $this->buildService($baseUrl);

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm('demo.bl2.chm-cbd.net');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('non-production host', $result['message']);
    }

    /**
     * Data provider of base URLs a prod site must refuse.
     */
    public function refusedProdHostProvider()
    {
        return [
            'the retired hardcoded dev host' => ['https://dmsm.cbddev.xyz'],
            'bare cbddev.xyz' => ['https://cbddev.xyz'],
            'a dev host label' => ['https://config.dev.cbd.int'],
            'a stg host label' => ['https://config.stg.cbd.int'],
            'a staging host label' => ['https://config.staging.cbd.int'],
        ];
    }

    /**
     * A prod site pointed at a production host is permitted.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testProdGuardPermitsProductionHost()
    {
        $service = $this->buildService('https://dmsm.chm-cbd.net');
        $response = $this->expectHttpFetch(['country' => 'be']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://dmsm.chm-cbd.net/api/config/prod/bl2/demo', $this->anything())
            ->willReturn($response);

        $result = $service->updateCountriesFromDmsm('demo.bl2.chm-cbd.net');

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * Non-prod environments may keep using a dev host.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testGuardDoesNotApplyOutsideProd()
    {
        $service = $this->buildService('https://dmsm.cbddev.xyz');
        $response = $this->expectHttpFetch(['country' => 'be']);

        $this->httpClient->expects($this->once())->method('request')->willReturn($response);

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

        $this->assertTrue($result['success'], $result['message']);
    }

    /**
     * The fetch refuses to run inside a web request.
     *
     * @covers ::updateCountriesFromDmsm
     */
    public function testFetchIsRefusedInsideAWebRequest()
    {
        $service = $this->buildService('https://config.example.test', WebSapiDmsmConfigService::class);
        $service->sapiName = 'fpm-fcgi';

        $this->httpClient->expects($this->never())->method('request');
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

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
        $service = $this->buildService('https://config.example.test', WebSapiDmsmConfigService::class);
        $service->sapiName = 'cli';

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->expectHttpFetch(['country' => 'be']));

        $result = $service->updateCountriesFromDmsm('example.bl2.cbddev.xyz');

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
            ->with(['hostname' => 'example.bl2.cbddev.xyz']);

        $result = $service->enqueueCountriesUpdate('example.bl2.cbddev.xyz');

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
        $service = $this->buildService('https://config.example.test', WebSapiDmsmConfigService::class);
        $service->sapiName = 'fpm-fcgi';

        $this->requestStack->method('getCurrentRequest')
            ->willReturn(new \Symfony\Component\HttpFoundation\Request('example.bl2.cbddev.xyz'));

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
}
