<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\bioland\Service\BiolandTranslationManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Unit tests for BiolandTranslationBatchService.
 *
 * @covers \Drupal\bioland\Service\BiolandTranslationBatchService
 */
class BiolandTranslationBatchServiceTest extends TestCase {

  /**
   * The mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mock translation manager.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $translationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->translationManager = $this->createMock(BiolandTranslationManager::class);
  }

  /**
   * Creates the batch service with mocked dependencies.
   *
   * @return \Drupal\bioland\Service\BiolandTranslationBatchService
   *   The batch service.
   */
  protected function createService(): BiolandTranslationBatchService {
    return new BiolandTranslationBatchService(
      $this->entityTypeManager,
      $this->configFactory,
      $this->translationManager
    );
  }

  /**
   * Tests createTranslationBatch returns proper batch structure.
   */
  public function testCreateTranslationBatchReturnsProperStructure(): void {
    $entityIds = [1, 2, 3, 4, 5];
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', $entityIds);
    
    $this->assertIsArray($batch);
    $this->assertArrayHasKey('title', $batch);
    $this->assertArrayHasKey('operations', $batch);
    $this->assertArrayHasKey('finished', $batch);
    $this->assertArrayHasKey('progressive', $batch);
    $this->assertTrue($batch['progressive']);
  }

  /**
   * Tests createTranslationBatch with specific entity IDs.
   */
  public function testCreateTranslationBatchWithSpecificIds(): void {
    $entityIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', $entityIds);
    
    // With 10 entities and chunk size of 20, should have 1 operation.
    $this->assertCount(1, $batch['operations']);
    
    // Each operation should have the correct parameters.
    $operation = $batch['operations'][0];
    $this->assertSame([BiolandTranslationBatchService::class, 'processTranslationBatch'], $operation[0]);
    $this->assertSame('node', $operation[1][0]);
    $this->assertSame($entityIds, $operation[1][1]);
  }

  /**
   * Tests createTranslationBatch chunks large sets of IDs.
   */
  public function testCreateTranslationBatchChunksLargeSets(): void {
    // Create 50 entity IDs.
    $entityIds = range(1, 50);
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', $entityIds);
    
    // With 50 entities and chunk size of 20, should have 3 operations.
    $this->assertCount(3, $batch['operations']);
    
    // First chunk should have 20 IDs.
    $this->assertCount(20, $batch['operations'][0][1][1]);
    
    // Second chunk should have 20 IDs.
    $this->assertCount(20, $batch['operations'][1][1][1]);
    
    // Third chunk should have 10 IDs.
    $this->assertCount(10, $batch['operations'][2][1][1]);
  }

  /**
   * Tests createTranslationBatch with empty entity IDs fetches from storage.
   */
  public function testCreateTranslationBatchFetchesEntitiesWhenEmpty(): void {
    $query = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('execute')->willReturn([1, 2, 3]);
    
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isTranslatable')->willReturn(TRUE);
    
    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $this->entityTypeManager->method('getDefinition')
      ->with('node')
      ->willReturn($entityType);
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', []);
    
    $this->assertCount(1, $batch['operations']);
  }

  /**
   * Tests createTranslationBatch title contains entity type.
   */
  public function testCreateTranslationBatchTitleContainsEntityType(): void {
    $service = $this->createService();
    $batch = $service->createTranslationBatch('taxonomy_term', [1, 2, 3]);
    
    $this->assertStringContainsString('taxonomy_term', $batch['title']);
  }

  /**
   * Tests createTranslationBatch finished callback is set.
   */
  public function testCreateTranslationBatchFinishedCallbackIsSet(): void {
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', [1]);
    
    $this->assertSame(
      [BiolandTranslationBatchService::class, 'finishTranslationBatch'],
      $batch['finished']
    );
  }

  /**
   * Tests createTranslationBatch with single entity.
   */
  public function testCreateTranslationBatchWithSingleEntity(): void {
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', [42]);
    
    $this->assertCount(1, $batch['operations']);
    $this->assertSame([42], $batch['operations'][0][1][1]);
  }

  /**
   * Tests createTranslationBatch handles exactly 20 entities (one chunk).
   */
  public function testCreateTranslationBatchHandlesExactlyOneChunk(): void {
    $entityIds = range(1, 20);
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', $entityIds);
    
    $this->assertCount(1, $batch['operations']);
    $this->assertCount(20, $batch['operations'][0][1][1]);
  }

  /**
   * Tests createTranslationBatch handles 21 entities (two chunks).
   */
  public function testCreateTranslationBatchHandlesTwoChunks(): void {
    $entityIds = range(1, 21);
    
    $service = $this->createService();
    $batch = $service->createTranslationBatch('node', $entityIds);
    
    $this->assertCount(2, $batch['operations']);
    $this->assertCount(20, $batch['operations'][0][1][1]);
    $this->assertCount(1, $batch['operations'][1][1][1]);
  }

}

namespace Drupal\Core\Entity\Query;

/**
 * Stub interface for QueryInterface.
 */
interface QueryInterface {

  /**
   * Sets access check.
   *
   * @param bool $check
   *   Whether to check access.
   *
   * @return $this
   */
  public function accessCheck($check);

  /**
   * Executes the query.
   *
   * @return array
   *   The result.
   */
  public function execute();

}
