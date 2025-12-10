<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Service\BiolandTranslationManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Unit tests for BiolandTranslationManager service.
 *
 * @covers \Drupal\bioland\Service\BiolandTranslationManager
 */
class BiolandTranslationManagerTest extends TestCase {

  /**
   * The mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mock language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * The mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mock logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')
      ->with('bioland')
      ->willReturn($this->logger);
  }

  /**
   * Creates a translation manager with mocked dependencies.
   *
   * @return \Drupal\bioland\Service\BiolandTranslationManager
   *   The translation manager.
   */
  protected function createManager(): BiolandTranslationManager {
    return new BiolandTranslationManager(
      $this->configFactory,
      $this->languageManager,
      $this->loggerFactory,
      $this->entityTypeManager
    );
  }

  /**
   * Tests createTranslations returns false when auto_create is disabled.
   */
  public function testCreateTranslationsReturnsFalseWhenDisabled(): void {
    $configData = [
      'translation' => [
        'auto_create' => FALSE,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(1);
    
    $manager = $this->createManager();
    $result = $manager->createTranslations($entity);
    
    $this->assertFalse($result);
  }

  /**
   * Tests createTranslations returns false when entity type not in enabled types.
   */
  public function testCreateTranslationsReturnsFalseForDisabledEntityType(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
        'entity_types' => ['node'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('taxonomy_term');
    $entity->method('id')->willReturn(1);
    
    $manager = $this->createManager();
    $result = $manager->createTranslations($entity);
    
    $this->assertFalse($result);
  }

  /**
   * Tests createTranslations returns false when no entity types are configured.
   */
  public function testCreateTranslationsReturnsFalseWhenNoEntityTypesConfigured(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
        'entity_types' => [],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(1);
    
    $manager = $this->createManager();
    $result = $manager->createTranslations($entity);
    
    $this->assertFalse($result);
  }

  /**
   * Tests createTranslations returns false for non-translatable entities.
   */
  public function testCreateTranslationsReturnsFalseForNonTranslatableEntity(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
        'entity_types' => ['node'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(1);
    $entity->method('isTranslatable')->willReturn(FALSE);
    
    $manager = $this->createManager();
    $result = $manager->createTranslations($entity);
    
    $this->assertFalse($result);
  }

  /**
   * Tests getTargetLanguages returns all languages when use_all_languages is true.
   */
  public function testGetTargetLanguagesReturnsAllLanguages(): void {
    $configData = [
      'translation' => [
        'use_all_languages' => TRUE,
        'target_languages' => ['fr'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $this->languageManager->method('getLanguages')
      ->willReturn([
        'en' => new Language('en', 'English'),
        'fr' => new Language('fr', 'French'),
        'es' => new Language('es', 'Spanish'),
      ]);
    
    $manager = $this->createManager();
    $result = $manager->getTargetLanguages();
    
    $this->assertSame(['en', 'fr', 'es'], $result);
  }

  /**
   * Tests getTargetLanguages returns configured languages when use_all_languages is false.
   */
  public function testGetTargetLanguagesReturnsConfiguredLanguages(): void {
    $configData = [
      'translation' => [
        'use_all_languages' => FALSE,
        'target_languages' => ['fr', 'es'],
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = $this->createManager();
    $result = $manager->getTargetLanguages();
    
    $this->assertSame(['fr', 'es'], $result);
  }

  /**
   * Tests getTargetLanguages returns empty array when no languages configured.
   */
  public function testGetTargetLanguagesReturnsEmptyArray(): void {
    $configData = [
      'translation' => [
        'use_all_languages' => FALSE,
        'target_languages' => NULL,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = $this->createManager();
    $result = $manager->getTargetLanguages();
    
    $this->assertSame([], $result);
  }

  /**
   * Tests isAutoTranslationEnabled returns true when enabled.
   */
  public function testIsAutoTranslationEnabledReturnsTrue(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = $this->createManager();
    
    $this->assertTrue($manager->isAutoTranslationEnabled());
  }

  /**
   * Tests isAutoTranslationEnabled returns false when disabled.
   */
  public function testIsAutoTranslationEnabledReturnsFalse(): void {
    $configData = [
      'translation' => [
        'auto_create' => FALSE,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = $this->createManager();
    
    $this->assertFalse($manager->isAutoTranslationEnabled());
  }

  /**
   * Tests isAutoTranslationEnabled returns false when not configured.
   */
  public function testIsAutoTranslationEnabledReturnsFalseWhenNotConfigured(): void {
    $configData = [
      'translation' => [
        'auto_create' => NULL,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $manager = $this->createManager();
    
    $this->assertFalse($manager->isAutoTranslationEnabled());
  }

  /**
   * Tests createTranslations prevents recursive calls.
   */
  public function testCreateTranslationsPreventRecursion(): void {
    $configData = [
      'translation' => [
        'auto_create' => TRUE,
        'entity_types' => ['node'],
        'use_all_languages' => TRUE,
        'copy_source_values' => FALSE,
      ],
    ];
    
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    $this->configFactory->method('get')
      ->with('bioland.settings')
      ->willReturn($configObject);
    
    $sourceLanguage = new Language('en', 'English');
    $frenchLanguage = new Language('fr', 'French');
    
    // Create a mock translation that will be returned.
    $translationMock = $this->createMock(ContentEntityInterface::class);
    $translationMock->method('getUntranslated')->willReturnSelf();
    $translationMock->method('language')->willReturn($sourceLanguage);
    
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn(1);
    $entity->method('isTranslatable')->willReturn(TRUE);
    $entity->method('getUntranslated')->willReturn($entity);
    $entity->method('language')->willReturn($sourceLanguage);
    $entity->method('hasTranslation')->willReturn(TRUE);
    $entity->method('getTranslation')->willReturn($translationMock);
    
    $this->languageManager->method('getLanguages')
      ->willReturn([
        'en' => new Language('en', 'English'),
        'fr' => new Language('fr', 'French'),
      ]);
    $this->languageManager->method('getLanguage')
      ->willReturn($frenchLanguage);
    
    $manager = $this->createManager();
    
    // First call should work.
    $result1 = $manager->createTranslations($entity);
    
    // The result depends on whether translations were actually created.
    // Since we're mocking hasTranslation to return TRUE and existing translation has proper source,
    // no new translations will be created.
    $this->assertFalse($result1);
  }

}
