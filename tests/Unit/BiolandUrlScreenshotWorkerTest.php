<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Plugin\QueueWorker\BiolandUrlScreenshotWorker;
use Drupal\bioland\Service\BiolandUrlScreenshotService;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\State\StateInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BiolandUrlScreenshotWorker's bounded retry counter.
 *
 * @covers \Drupal\bioland\Plugin\QueueWorker\BiolandUrlScreenshotWorker
 * @group bioland
 */
class BiolandUrlScreenshotWorkerTest extends TestCase {

  protected function makeWorker(BiolandUrlScreenshotService $service, StateInterface $state): BiolandUrlScreenshotWorker {
    return new BiolandUrlScreenshotWorker([], 'bioland_url_screenshot', [], $service, $state);
  }

  public function testSuccessClearsCounter(): void {
    $service = $this->createMock(BiolandUrlScreenshotService::class);
    $service->expects($this->once())->method('process');

    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())->method('delete')->with('bioland.url_screenshot_attempts.7');
    $state->expects($this->never())->method('set');

    $this->makeWorker($service, $state)->processItem(['nid' => 7]);
  }

  public function testTransientFailureIncrementsCounterAndRethrows(): void {
    $service = $this->createMock(BiolandUrlScreenshotService::class);
    $exception = $this->transientException();
    $service->method('process')->willThrowException($exception);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->with('bioland.url_screenshot_attempts.7', 0)->willReturn(1);
    $state->expects($this->once())->method('set')->with('bioland.url_screenshot_attempts.7', 2);
    $state->expects($this->never())->method('delete');

    $this->expectException(RequeueException::class);
    $this->makeWorker($service, $state)->processItem(['nid' => 7]);
  }

  public function testTransientFailureStopsRetryingAtMaxAttempts(): void {
    $service = $this->createMock(BiolandUrlScreenshotService::class);
    $service->method('process')->willThrowException($this->transientException());

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(BiolandUrlScreenshotWorker::MAX_ATTEMPTS - 1);
    $state->expects($this->once())->method('delete');
    $state->expects($this->never())->method('set');

    // No RequeueException once attempts are exhausted; the item is dropped.
    $this->makeWorker($service, $state)->processItem(['nid' => 7]);
  }

  public function testPermanentFailureClearsCounterAndDoesNotRethrow(): void {
    $service = $this->createMock(BiolandUrlScreenshotService::class);
    $service->method('process')->willThrowException($this->permanentException());

    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())->method('delete')->with('bioland.url_screenshot_attempts.7');
    $state->expects($this->never())->method('set');

    $this->makeWorker($service, $state)->processItem(['nid' => 7]);
  }

  protected function transientException(): \Exception {
    return new \ConvertApi\Error\Api('transient', 503);
  }

  protected function permanentException(): \Exception {
    return new \ConvertApi\Error\Api('permanent', 400);
  }

}
