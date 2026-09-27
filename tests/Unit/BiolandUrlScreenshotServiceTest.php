<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\ConvertApi\BiolandConvertApiClient;
use Drupal\bioland\Service\BiolandUrlScreenshotService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BiolandUrlScreenshotService (BL-1191).
 *
 * @covers \Drupal\bioland\Service\BiolandUrlScreenshotService
 * @group bioland
 */
class BiolandUrlScreenshotServiceTest extends TestCase {

  protected function tearDown(): void {
    putenv('CONVERT_API_SECRET');
    parent::tearDown();
  }

  /**
   * A lightweight stand-in for a field item / list, exposing the magic
   * properties this service reads.
   */
  protected function fieldItem(?string $uri = NULL, ?int $targetId = NULL, array $value = []): object {
    return new class($uri, $targetId, $value) {
      public function __construct(public $uri, public $target_id, protected array $value) {
      }

      public function isEmpty(): bool {
        return $this->uri === NULL && $this->target_id === NULL && $this->value === [];
      }

      public function getValue(): array {
        return $this->value;
      }
    };
  }

  protected function makeService(
    EntityTypeManagerInterface $entityTypeManager,
    ?callable $adapterFactory = NULL
  ): BiolandUrlScreenshotService {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileRepository = $this->createMock(FileRepositoryInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn(new class {
      public function __call($name, $args) {}
    });
    $queueFactory = $this->createMock(QueueFactory::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $state = $this->createMock(StateInterface::class);

    return new BiolandUrlScreenshotService(
      $entityTypeManager,
      $fileSystem,
      $fileRepository,
      $loggerFactory,
      $queueFactory,
      $configFactory,
      $state,
      $adapterFactory
    );
  }

  /**
   * destruct() with two recorded items: the first succeeds (deleted), the
   * second throws (left in the queue); no exception escapes and the list
   * empties either way.
   */
  public function testDestructDeletesOnlySuccessfulItemsAndNeverThrows(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())->method('deleteItem');

    /** @var \Drupal\bioland\Service\BiolandUrlScreenshotService|\PHPUnit\Framework\MockObject\MockObject $service */
    $service = $this->getMockBuilder(BiolandUrlScreenshotService::class)
      ->setConstructorArgs([
        $entityTypeManager,
        $this->createMock(FileSystemInterface::class),
        $this->createMock(FileRepositoryInterface::class),
        $this->loggerFactoryStub(),
        $this->queueFactoryReturning($queue),
        $this->createMock(ConfigFactoryInterface::class),
        $this->createMock(StateInterface::class),
      ])
      ->onlyMethods(['process'])
      ->getMock();

    $service->expects($this->exactly(2))
      ->method('process')
      ->willReturnCallback(function (array $item) {
        if ($item['nid'] === 2) {
          throw new \RuntimeException('transient failure');
        }
      });

    $pending = new \ReflectionProperty($service, 'pending');
    $pending->setAccessible(TRUE);
    $pending->setValue($service, [
      'a' => ['id' => 101, 'item' => ['nid' => 1, 'url' => 'https://a.org']],
      'b' => ['id' => 102, 'item' => ['nid' => 2, 'url' => 'https://b.org']],
    ]);

    $service->destruct();

    $this->assertSame([], $pending->getValue($service));
  }

  public function testDestructIsNoOpWhenNothingPending(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $service = $this->getMockBuilder(BiolandUrlScreenshotService::class)
      ->setConstructorArgs([
        $entityTypeManager,
        $this->createMock(FileSystemInterface::class),
        $this->createMock(FileRepositoryInterface::class),
        $this->loggerFactoryStub(),
        $this->createMock(QueueFactory::class),
        $this->createMock(ConfigFactoryInterface::class),
        $this->createMock(StateInterface::class),
      ])
      ->onlyMethods(['process'])
      ->getMock();

    $service->expects($this->never())->method('process');
    $service->destruct();
  }

  /**
   * process() calls the adapter exactly once with the item URL and hands the
   * returned bytes to the file repository.
   */
  public function testProcessCallsAdapterOnceAndWritesBytes(): void {
    putenv('CONVERT_API_SECRET=test-secret');

    $url = 'https://www.cbd.int';
    $bytes = 'fake-webp-bytes';

    $node = $this->createMock(ContentEntityInterface::class);
    $node->method('hasField')->willReturnMap([
      ['field_url', TRUE],
      ['field_description', FALSE],
      ['field_website_image', TRUE],
    ]);
    $node->method('get')->willReturnMap([
      ['field_url', $this->fieldItem($url)],
      ['field_website_image', $this->fieldItem(NULL, NULL, [])],
    ]);
    $node->method('label')->willReturn('CBD');
    $node->method('getOwnerId')->willReturn(1);
    $node->expects($this->once())->method('set')->with('field_website_image', $this->callback(
      fn ($value) => is_array($value) && isset($value[0]['target_id'])
    ));
    $node->expects($this->once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(42)->willReturn($node);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $adapter = $this->createMock(BiolandConvertApiClient::class);
    $adapter->expects($this->once())->method('screenshotToWebp')->with($url)->willReturn($bytes);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $file = new File(9);
    $fileRepository = $this->createMock(FileRepositoryInterface::class);
    $fileRepository->expects($this->once())
      ->method('writeData')
      ->with($bytes, $this->stringContains('.webp'))
      ->willReturn($file);

    $service = new BiolandUrlScreenshotService(
      $entityTypeManager,
      $fileSystem,
      $fileRepository,
      $this->loggerFactoryStub(),
      $this->createMock(QueueFactory::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(StateInterface::class),
      fn (string $secret) => $adapter
    );

    $service->process(['nid' => 42, 'langcode' => 'en', 'url' => $url]);
  }

  public function testProcessSkipsWhenUrlNoLongerMatches(): void {
    $node = $this->createMock(ContentEntityInterface::class);
    $node->method('hasField')->with('field_url')->willReturn(TRUE);
    $node->method('get')->with('field_url')->willReturn($this->fieldItem('https://newer.org'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($node);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $adapter = $this->createMock(BiolandConvertApiClient::class);
    $adapter->expects($this->never())->method('screenshotToWebp');

    $service = new BiolandUrlScreenshotService(
      $entityTypeManager,
      $this->createMock(FileSystemInterface::class),
      $this->createMock(FileRepositoryInterface::class),
      $this->loggerFactoryStub(),
      $this->createMock(QueueFactory::class),
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(StateInterface::class),
      fn (string $secret) => $adapter
    );

    $service->process(['nid' => 42, 'langcode' => 'en', 'url' => 'https://stale.org']);
  }

  protected function loggerFactoryStub(): LoggerChannelFactoryInterface {
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn(new class {
      public function __call($name, $args) {}
    });
    return $loggerFactory;
  }

  protected function queueFactoryReturning(QueueInterface $queue): QueueFactory {
    $factory = $this->createMock(QueueFactory::class);
    $factory->method('get')->willReturn($queue);
    return $factory;
  }

}
