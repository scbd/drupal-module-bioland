<?php

namespace Drupal\Tests\bioland\Unit\Plugin\QueueWorker;

use Drupal\bioland\Plugin\QueueWorker\BiolandDmsmGeographyWorker;
use Drupal\bioland\Service\BiolandDmsmConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Tests the queue worker that performs the geography fetch off the request path.
 *
 * @coversDefaultClass \Drupal\bioland\Plugin\QueueWorker\BiolandDmsmGeographyWorker
 * @group bioland
 */
class BiolandDmsmGeographyWorkerTest extends TestCase
{
    /**
     * The worker hands the queued hostname to the service fetch.
     *
     * @covers ::processItem
     */
    public function testProcessItemFetchesForTheQueuedHostname()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('updateCountriesFromDmsm')
            ->with('example.bl2.cbddev.xyz')
            ->willReturn(['success' => true, 'message' => 'ok']);

        $worker = new BiolandDmsmGeographyWorker([], 'bioland_dmsm_geography', [], $service);
        $result = $worker->processItem(['hostname' => 'example.bl2.cbddev.xyz']);

        $this->assertTrue($result['success']);
    }

    /**
     * A malformed payload still reaches the service, which decides the outcome.
     *
     * @covers ::processItem
     */
    public function testProcessItemWithoutHostnameDelegatesNull()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $service->expects($this->once())
            ->method('updateCountriesFromDmsm')
            ->with(null)
            ->willReturn(['success' => false, 'message' => 'no hostname']);

        $worker = new BiolandDmsmGeographyWorker([], 'bioland_dmsm_geography', [], $service);
        $result = $worker->processItem('not-an-array');

        $this->assertFalse($result['success']);
    }

    /**
     * The worker is built from the container with the dmsm config service.
     *
     * @covers ::create
     */
    public function testCreatePullsTheServiceFromTheContainer()
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
            ->method('get')
            ->with('bioland.dmsm_config')
            ->willReturn($service);

        $worker = BiolandDmsmGeographyWorker::create($container, [], 'bioland_dmsm_geography', []);

        $this->assertInstanceOf(BiolandDmsmGeographyWorker::class, $worker);
    }

    /**
     * The worker declares the cron-drained queue plugin id the service enqueues to.
     */
    public function testWorkerPluginIdMatchesTheServiceQueueName()
    {
        $annotation = (new \ReflectionClass(BiolandDmsmGeographyWorker::class))->getDocComment();

        $this->assertStringContainsString(
            'id = "' . BiolandDmsmConfigService::QUEUE_NAME . '"',
            $annotation
        );
        $this->assertStringContainsString('cron = {"time"', $annotation);
    }
}
