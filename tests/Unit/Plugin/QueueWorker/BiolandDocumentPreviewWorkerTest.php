<?php

namespace Drupal\Tests\bioland\Unit\Plugin\QueueWorker;

use ConvertApi\Error\Api as ConvertApiError;
use Drupal\bioland\Plugin\QueueWorker\BiolandDocumentPreviewWorker;
use Drupal\bioland\Service\BiolandDocumentPreviewService;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use PHPUnit\Framework\TestCase;

/**
 * An in-memory state store, so the retry counter is observable in a unit test.
 */
class DocumentPreviewArrayState implements StateInterface
{
    public $values = [];

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function delete($key)
    {
        unset($this->values[$key]);
    }
}

/**
 * Tests the queue worker that drains BL-1192 document-preview conversions.
 *
 * @coversDefaultClass \Drupal\bioland\Plugin\QueueWorker\BiolandDocumentPreviewWorker
 * @group bioland
 */
class BiolandDocumentPreviewWorkerTest extends TestCase
{
    protected DocumentPreviewArrayState $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new DocumentPreviewArrayState();
    }

    private function buildWorker(\Closure $processBehavior): BiolandDocumentPreviewWorker
    {
        $service = $this->createMock(BiolandDocumentPreviewService::class);
        $service->expects($this->once())->method('process')->willReturnCallback($processBehavior);

        return new BiolandDocumentPreviewWorker([], 'bioland_document_preview', [], $service, $this->state);
    }

    /**
     * @covers ::processItem
     */
    public function testSuccessClearsTheAttemptCounter(): void
    {
        $key = BiolandDocumentPreviewWorker::STATE_ATTEMPT_PREFIX . '1';
        $this->state->set($key, 1);

        $worker = $this->buildWorker(function () {
            // No exception: success.
        });

        $worker->processItem(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertNull($this->state->get($key));
    }

    /**
     * A transient ConvertAPI failure (429/5xx) is requeued, not dropped.
     *
     * @covers ::processItem
     */
    public function testTransientFailureIncrementsCounterAndRethrows(): void
    {
        $worker = $this->buildWorker(function () {
            throw new ConvertApiError('Service unavailable', 503);
        });

        $threw = false;

        try {
            $worker->processItem(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);
        } catch (RequeueException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'A transient failure must throw RequeueException.');
        $this->assertSame(1, $this->state->get(BiolandDocumentPreviewWorker::STATE_ATTEMPT_PREFIX . '1'));
    }

    /**
     * The requeue is bounded: the last attempt drops the item instead of looping.
     *
     * @covers ::processItem
     */
    public function testTransientRequeueIsBounded(): void
    {
        $key = BiolandDocumentPreviewWorker::STATE_ATTEMPT_PREFIX . '1';
        $this->state->set($key, BiolandDocumentPreviewWorker::MAX_ATTEMPTS - 1);

        $worker = $this->buildWorker(function () {
            throw new ConvertApiError('Service unavailable', 503);
        });

        $worker->processItem(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertNull($this->state->get($key));
    }

    /**
     * A permanent ConvertAPI failure (e.g. 400) clears the counter and does not rethrow.
     *
     * @covers ::processItem
     */
    public function testPermanentFailureClearsCounterAndDoesNotRethrow(): void
    {
        $key = BiolandDocumentPreviewWorker::STATE_ATTEMPT_PREFIX . '1';
        $this->state->set($key, 2);

        $worker = $this->buildWorker(function () {
            throw new ConvertApiError('Bad request', 400);
        });

        $worker->processItem(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertNull($this->state->get($key));
    }

    /**
     * @covers ::create
     */
    public function testCreatePullsTheServiceFromTheContainer(): void
    {
        $service = $this->createMock(BiolandDocumentPreviewService::class);
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($id) use ($service) {
                return $id === 'bioland.document_preview' ? $service : new DocumentPreviewArrayState();
            });

        $worker = BiolandDocumentPreviewWorker::create($container, [], 'bioland_document_preview', []);

        $this->assertInstanceOf(BiolandDocumentPreviewWorker::class, $worker);
    }
}
