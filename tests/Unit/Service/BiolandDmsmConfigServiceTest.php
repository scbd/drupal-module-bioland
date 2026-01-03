<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandDmsmConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BiolandDmsmConfigService.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandDmsmConfigService
 * @group bioland
 */
class BiolandDmsmConfigServiceTest extends TestCase
{
    /**
     * Test hostname parsing for various patterns.
     *
     * @dataProvider hostnameProvider
     */
    public function testHostnameParsing($hostname, $expected)
    {
        $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $logger = $this->createMock('Drupal\Core\Logger\LoggerChannelInterface');
        
        $loggerFactory->method('get')->willReturn($logger);

        $service = new BiolandDmsmConfigService($configFactory, $httpClient, $loggerFactory);

        // Use reflection to access protected method.
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseHostname');
        $method->setAccessible(true);

        $result = $method->invoke($service, $hostname);

        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for hostname parsing tests.
     */
    public function hostnameProvider()
    {
        return [
            // Dev environment - bl2
            'dev bl2' => [
                'example.bl2.cbddev.xyz',
                [
                    'env' => 'dev',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'example',
                ],
            ],
            // Dev environment - bsl
            'dev bsl' => [
                'test.bsl.cbddev.xyz',
                [
                    'env' => 'dev',
                    'multiSiteCode' => 'bsl',
                    'siteCode' => 'test',
                ],
            ],
            // Staging environment
            'staging' => [
                'mysite.bl2.staging.cbd.int',
                [
                    'env' => 'stg',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'mysite',
                ],
            ],
            // Production - chm-cbd.net
            'prod chm' => [
                'demo.bl2.chm-cbd.net',
                [
                    'env' => 'prod',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'demo',
                ],
            ],
            // Production - biodiv.be
            'prod biodiv.be' => [
                'biodiv.be',
                [
                    'env' => 'prod',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'be',
                ],
            ],
            // Production - www.biodiv.be
            'prod www.biodiv.be' => [
                'www.biodiv.be',
                [
                    'env' => 'prod',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'be',
                ],
            ],
            // Production - biodiv.mnhn.fr
            'prod biodiv.mnhn.fr' => [
                'biodiv.mnhn.fr',
                [
                    'env' => 'prod',
                    'multiSiteCode' => 'bl2',
                    'siteCode' => 'fr',
                ],
            ],
            // Invalid hostname
            'invalid' => [
                'invalid.example.com',
                null,
            ],
        ];
    }

    /**
     * Test successful API response with runTime.countries.
     */
    public function testUpdateCountriesFromDmsmWithRuntimeCountries()
    {
        $this->markTestSkipped('Requires Drupal bootstrap - integration test');
    }

    /**
     * Test successful API response with data.country fallback.
     */
    public function testUpdateCountriesFromDmsmWithCountryFallback()
    {
        $this->markTestSkipped('Requires Drupal bootstrap - integration test');
    }

    /**
     * Test API failure handling.
     */
    public function testUpdateCountriesFromDmsmApiFailure()
    {
        $this->markTestSkipped('Requires Drupal bootstrap - integration test');
    }

    /**
     * Test invalid hostname handling.
     */
    public function testUpdateCountriesFromDmsmInvalidHostname()
    {
        $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $logger = $this->createMock('Drupal\Core\Logger\LoggerChannelInterface');

        $loggerFactory->method('get')->willReturn($logger);

        $service = new BiolandDmsmConfigService($configFactory, $httpClient, $loggerFactory);
        $result = $service->updateCountriesFromDmsm('invalid.example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unable to parse hostname', $result['message']);
    }

    /**
     * Test that bsl sites set is_biosafety_land to true.
     *
     * @dataProvider biosafetySiteProvider
     */
    public function testBiosafetySiteDetection($hostname, $expectedIsBiosafety)
    {
        $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $logger = $this->createMock('Drupal\Core\Logger\LoggerChannelInterface');

        $loggerFactory->method('get')->willReturn($logger);

        $service = new BiolandDmsmConfigService($configFactory, $httpClient, $loggerFactory);

        // Use reflection to test parseHostname.
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseHostname');
        $method->setAccessible(true);

        $result = $method->invoke($service, $hostname);

        if ($result === null) {
            $this->fail("Expected hostname to be parseable: {$hostname}");
        }

        $this->assertEquals(
            $expectedIsBiosafety ? 'bsl' : 'bl2',
            $result['multiSiteCode'],
            "Hostname {$hostname} should have multiSiteCode " . ($expectedIsBiosafety ? 'bsl' : 'bl2')
        );
    }

    /**
     * Data provider for biosafety site detection tests.
     */
    public function biosafetySiteProvider()
    {
        return [
            'bsl dev site' => ['test.bsl.cbddev.xyz', true],
            'bsl staging site' => ['mysite.bsl.staging.cbd.int', true],
            'bsl prod site' => ['country.bsl.chm-cbd.net', true],
            'bl2 dev site' => ['test.bl2.cbddev.xyz', false],
            'bl2 staging site' => ['mysite.bl2.staging.cbd.int', false],
            'bl2 prod site' => ['country.bl2.chm-cbd.net', false],
            'biodiv.be' => ['biodiv.be', false],
            'biodiv.mnhn.fr' => ['biodiv.mnhn.fr', false],
        ];
    }
}
