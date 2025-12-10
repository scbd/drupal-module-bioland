<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandSettingsForm;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Unit tests for BiolandSettingsForm.
 *
 * @covers \Drupal\bioland\Form\BiolandSettingsForm
 */
class BiolandSettingsFormTest extends TestCase {

  /**
   * The mock language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mock translation batch service.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationBatchService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $translationBatchService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->translationBatchService = $this->createMock(BiolandTranslationBatchService::class);
    
    // Set up default language.
    $defaultLanguage = new Language('en', 'English');
    $this->languageManager->method('getDefaultLanguage')
      ->willReturn($defaultLanguage);
    
    // Set up available languages.
    $this->languageManager->method('getLanguages')
      ->willReturn([
        'en' => new Language('en', 'English'),
        'fr' => new Language('fr', 'French'),
        'es' => new Language('es', 'Spanish'),
      ]);
    
    // Set up language config overrides.
    $this->languageManager->method('getLanguageConfigOverride')
      ->willReturn(new ImmutableConfig('system.site', []));
  }

  /**
   * Creates a form instance with mocked dependencies.
   *
   * @return \Drupal\bioland\Form\BiolandSettingsForm
   *   The form instance.
   */
  protected function createForm(): BiolandSettingsForm {
    return new BiolandSettingsForm(
      $this->languageManager,
      $this->entityTypeManager,
      $this->translationBatchService
    );
  }

  /**
   * Tests getFormId returns correct form ID.
   */
  public function testGetFormIdReturnsCorrectId(): void {
    $form = $this->createForm();
    
    $this->assertSame('bioland_settings_form', $form->getFormId());
  }

  /**
   * Tests getEditableConfigNames returns bioland.settings.
   */
  public function testGetEditableConfigNamesReturnsBiolandSettings(): void {
    $form = $this->createForm();
    
    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getEditableConfigNames');
    $method->setAccessible(TRUE);
    
    $result = $method->invoke($form);
    
    $this->assertSame(['bioland.settings'], $result);
  }

  /**
   * Tests getRegionOptions returns all regions.
   */
  public function testGetRegionOptionsReturnsAllRegions(): void {
    $form = $this->createForm();
    
    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getRegionOptions');
    $method->setAccessible(TRUE);
    
    $result = $method->invoke($form);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('north_america', $result);
    $this->assertArrayHasKey('south_america', $result);
    $this->assertArrayHasKey('europe', $result);
    $this->assertArrayHasKey('asia', $result);
    $this->assertArrayHasKey('africa', $result);
    $this->assertArrayHasKey('oceania', $result);
    $this->assertCount(6, $result);
  }

  /**
   * Tests getLocaleOptions returns expected locales.
   */
  public function testGetLocaleOptionsReturnsExpectedLocales(): void {
    $form = $this->createForm();
    
    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getLocaleOptions');
    $method->setAccessible(TRUE);
    
    $result = $method->invoke($form);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('en', $result);
    $this->assertArrayHasKey('es', $result);
    $this->assertArrayHasKey('fr', $result);
    $this->assertArrayHasKey('de', $result);
    $this->assertArrayHasKey('zh', $result);
    $this->assertArrayHasKey('ar', $result);
  }

  /**
   * Tests getBrandingName returns Bioland when not biosafety land.
   */
  public function testGetBrandingNameReturnsBioland(): void {
    $form = $this->createFormWithConfig(['is_biosafety_land' => FALSE]);
    
    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getBrandingName');
    $method->setAccessible(TRUE);
    
    $result = $method->invoke($form);
    
    $this->assertSame('Bioland', $result);
  }

  /**
   * Tests getBrandingName returns Biosafety Land when configured.
   */
  public function testGetBrandingNameReturnsBiosafetyLand(): void {
    $form = $this->createFormWithConfig(['is_biosafety_land' => TRUE]);
    
    // Use reflection to access protected method.
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('getBrandingName');
    $method->setAccessible(TRUE);
    
    $result = $method->invoke($form);
    
    $this->assertSame('Biosafety Land', $result);
  }

  /**
   * Tests getTitle returns branded title.
   */
  public function testGetTitleReturnsBrandedTitle(): void {
    $form = $this->createFormWithConfig(['is_biosafety_land' => FALSE]);
    
    $result = $form->getTitle();
    
    $this->assertStringContainsString('Bioland', $result);
    $this->assertStringContainsString('Settings', $result);
  }

  /**
   * Tests validateForm sets error when auto_create enabled but no entity types.
   */
  public function testValidateFormSetsErrorForMissingEntityTypes(): void {
    $form = $this->createForm();
    
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')
      ->with('bioland_section')
      ->willReturn('translation');
    $formState->method('getValue')
      ->willReturnMap([
        ['auto_create', NULL, TRUE],
        ['entity_types', NULL, []],
      ]);
    
    $formState->expects($this->once())
      ->method('setErrorByName')
      ->with('entity_types', $this->stringContains('select at least one entity type'));
    
    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests validateForm does not set error when auto_create is disabled.
   */
  public function testValidateFormNoErrorWhenAutoCreateDisabled(): void {
    $form = $this->createForm();
    
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')
      ->with('bioland_section')
      ->willReturn('translation');
    $formState->method('getValue')
      ->willReturnMap([
        ['auto_create', NULL, FALSE],
        ['entity_types', NULL, []],
      ]);
    
    $formState->expects($this->never())
      ->method('setErrorByName');
    
    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests validateForm does not set error when entity types are selected.
   */
  public function testValidateFormNoErrorWhenEntityTypesSelected(): void {
    $form = $this->createForm();
    
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')
      ->with('bioland_section')
      ->willReturn('translation');
    $formState->method('getValue')
      ->willReturnMap([
        ['auto_create', NULL, TRUE],
        ['entity_types', NULL, ['node' => 'node']],
      ]);
    
    $formState->expects($this->never())
      ->method('setErrorByName');
    
    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Creates a form instance with mocked config.
   *
   * @param array $configData
   *   The config data.
   *
   * @return \Drupal\bioland\Form\BiolandSettingsForm
   *   The form instance.
   */
  protected function createFormWithConfig(array $configData): BiolandSettingsForm {
    $form = $this->createForm();
    
    // Use reflection to inject a mock config method.
    $configObject = new ImmutableConfig('bioland.settings', $configData);
    
    // Create a mock that returns the config.
    $reflection = new \ReflectionClass($form);
    
    // Create a subclass that overrides config method for testing.
    return new class($this->languageManager, $this->entityTypeManager, $this->translationBatchService, $configObject) extends BiolandSettingsForm {
      protected $testConfig;
      
      public function __construct($languageManager, $entityTypeManager, $translationBatchService, $config) {
        parent::__construct($languageManager, $entityTypeManager, $translationBatchService);
        $this->testConfig = $config;
      }
      
      protected function config($name) {
        return $this->testConfig;
      }
    };
  }

}
