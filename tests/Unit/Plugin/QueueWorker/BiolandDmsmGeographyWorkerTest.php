<?php

namespace Drupal\Tests\bioland\Unit\Plugin\QueueWorker;

use Drupal\bioland\Plugin\QueueWorker\BiolandDmsmGeographyWorker;
use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Queue\RequeueException;
use PHPUnit\Framework\TestCase;

/**
 * An in-memory state store, so the retry counter is observable in a unit test.
 */
class ArrayState implements \Drupal\Core\State\StateInterface
{
    /**
     * The stored values.
     *
     * @var array
     */
    public $values = [];

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        unset($this->values[$key]);
    }
}

/**
 * Tests the queue worker that performs the geography fetch off the request path.
 *
 * @coversDefaultClass \Drupal\bioland\Plugin\QueueWorker\BiolandDmsmGeographyWorker
 * @group bioland
 */
class BiolandDmsmGeographyWorkerTest extends TestCase
{
    /**
     * The in-memory state store.
     *
     * @var \Drupal\Tests\bioland\Unit\Plugin\QueueWorker\ArrayState
     */
    protected $state;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new ArrayState();
    }

    /**
     * Builds a worker around a service returning a fixed result.
     *
     * @param array $result
     *   The service result to return.
     * @param mixed $expectedHostname
     *   The hostname the service must be called with, or FALSE to not assert.
     *
     * @return \Drupal\bioland\Plugin\QueueWorker\BiolandDmsmGeographyWorker
     *   The worker.
     */
    protected function buildWorker(array $result, $expectedHostname = false)
    {
        $service = $this->createMock(BiolandDmsmConfigService::class);
        $expectation = $service->expects($this->once())->method('updateCountriesFromDmsm');

        if ($expectedHostname !== false) {
            $expectation->with($expectedHostname);
        }

        $expectation->willReturn($result);

        return new BiolandDmsmGeographyWorker([], 'bioland_dmsm_geography', [], $service, $this->state);
    }

    /**
     * The worker hands the queued hostname to the service fetch.
     *
     * @covers ::processItem
     */
    public function testProcessItemFetchesForTheQueuedHostname()
    {
        $worker = $this->buildWorker(
            ['success' => true, 'message' => 'ok', 'transient' => false],
            'example.bl2.cbddev.xyz'
        );

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
        $worker = $this->buildWorker(
            ['success' => false, 'message' => 'no hostname', 'transient' => false],
            null
        );

        $result = $worker->processItem('not-an-array');

        $this->assertFalse($result['success']);
    }

    /**
     * A transient failure is requeued, not dropped.
     *
     * Previously the worker returned, so Drupal deleted the item and a single
     * timeout permanently dropped the fetch with nothing left to re-enqueue it.
     *
     * @covers ::processItem
     */
    public function testTransientFailureIsRequeued()
    {
        $worker = $this->buildWorker(
            ['success' => false, 'message' => 'timeout', 'transient' => true],
            'example.bl2.cbddev.xyz'
        );

        $threw = false;

        try {
            $worker->processItem(['hostname' => 'example.bl2.cbddev.xyz']);
        } catch (RequeueException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'A transient failure must throw RequeueException.');
        $this->assertSame(
            1,
            $this->state->get(BiolandDmsmGeographyWorker::STATE_ATTEMPT_PREFIX . 'example.bl2.cbddev.xyz')
        );
    }

    /**
     * The requeue is bounded: the last attempt drops the item instead of looping.
     *
     * @covers ::processItem
     */
    public function testTransientRequeueIsBounded()
    {
        $key = BiolandDmsmGeographyWorker::STATE_ATTEMPT_PREFIX . 'example.bl2.cbddev.xyz';
        $this->state->set($key, BiolandDmsmGeographyWorker::MAX_ATTEMPTS - 1);

        $worker = $this->buildWorker(
            ['success' => false, 'message' => 'timeout', 'transient' => true],
            'example.bl2.cbddev.xyz'
        );

        $result = $worker->processItem(['hostname' => 'example.bl2.cbddev.xyz']);

        $this->assertFalse($result['success']);
        $this->assertNull($this->state->get($key));
    }

    /**
     * A configuration refusal is permanent: the item is dropped, never requeued.
     *
     * @covers ::processItem
     */
    public function testConfigurationRefusalIsDroppedNotRequeued()
    {
        $worker = $this->buildWorker(
            ['success' => false, 'message' => 'base URL is not set', 'transient' => false],
            'example.bl2.cbddev.xyz'
        );

        $result = $worker->processItem(['hostname' => 'example.bl2.cbddev.xyz']);

        $this->assertFalse($result['success']);
    }

    /**
     * A success clears any retry counter left by an earlier transient failure.
     *
     * @covers ::processItem
     */
    public function testSuccessClearsTheAttemptCounter()
    {
        $key = BiolandDmsmGeographyWorker::STATE_ATTEMPT_PREFIX . 'example.bl2.cbddev.xyz';
        $this->state->set($key, 1);

        $worker = $this->buildWorker(
            ['success' => true, 'message' => 'ok', 'transient' => false],
            'example.bl2.cbddev.xyz'
        );

        $worker->processItem(['hostname' => 'example.bl2.cbddev.xyz']);

        $this->assertNull($this->state->get($key));
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
        $container->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($id) use ($service) {
                return $id === 'bioland.dmsm_config' ? $service : new ArrayState();
            });

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
