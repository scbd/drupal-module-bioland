<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandSettingsForm;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The mock database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The mock current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mock request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->translationBatchService = $this->createMock(BiolandTranslationBatchService::class);
    $this->database = $this->createMock(Connection::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    
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
      $this->translationBatchService,
      $this->database,
      $this->currentUser,
      $this->requestStack
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
      ->willReturn('system_functions');
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
      ->willReturn('system_functions');
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
      ->willReturn('system_functions');
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
   * Tests hero description uses the default (BL2) copy when not BSL.
   */
  public function testBuildHeroDescriptionMarkupReturnsBl2Variant(): void {
    $form = $this->createFormWithConfig(['is_biosafety_land' => FALSE]);

    $result = $this->invokeBuildHeroDescriptionMarkup($form);

    // BL2 keeps the original three-paragraph rotating-hero copy.
    $this->assertStringContainsString('About Home Page Heroes', $result);
    $this->assertStringContainsString('this is where you configure the hero banners', $result);
    $this->assertStringContainsString('rotate automatically every hour', $result);
    $this->assertStringContainsString('unpublish the other hero(es)', $result);

    // It must NOT contain the BSL variant strings.
    $this->assertStringNotContainsString('this is where you edit the hero banner', $result);
  }

  /**
   * Tests hero description uses the BSL variant when the site is BSL.
   */
  public function testBuildHeroDescriptionMarkupReturnsBslVariant(): void {
    $form = $this->createFormWithConfig([
      'is_biosafety_land' => TRUE,
      'help_comments' => [
        'home_hero_help_bsl_heading' => 'About Home Page Heroe',
        'home_hero_help_bsl_text' => 'Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.',
      ],
    ]);

    $result = $this->invokeBuildHeroDescriptionMarkup($form);

    // BSL uses the single-hero heading + body sourced from config.
    $this->assertStringContainsString('About Home Page Heroe', $result);
    $this->assertStringContainsString('this is where you edit the hero banner for the home page', $result);

    // It must NOT contain the BL2 rotating-hero copy.
    $this->assertStringNotContainsString('rotate automatically every hour', $result);
    $this->assertStringNotContainsString('unpublish the other hero(es)', $result);
    $this->assertStringNotContainsString('this is where you configure the hero banners', $result);
  }

  /**
   * Tests the BSL variant falls back to hardcoded defaults when config is empty.
   *
   * Guards existing sites where the update hook has not yet seeded the new
   * config properties: the markup must still render the BSL copy.
   */
  public function testBuildHeroDescriptionMarkupBslFallsBackWhenConfigMissing(): void {
    $form = $this->createFormWithConfig(['is_biosafety_land' => TRUE]);

    $result = $this->invokeBuildHeroDescriptionMarkup($form);

    $this->assertStringContainsString('About Home Page Heroe', $result);
    $this->assertStringContainsString('this is where you edit the hero banner for the home page', $result);
    $this->assertStringNotContainsString('rotate automatically every hour', $result);
  }

  /**
   * Tests the BSL variant strips unsafe markup from config-sourced strings.
   *
   * Defense-in-depth: the config-sourced heading/body become dynamic t() msgids
   * concatenated into raw markup, so Xss::filterAdmin() must strip any unsafe
   * tags (e.g. <script>) from the assembled output while keeping the safe text.
   */
  public function testBuildHeroDescriptionMarkupBslStripsUnsafeConfigMarkup(): void {
    $form = $this->createFormWithConfig([
      'is_biosafety_land' => TRUE,
      'help_comments' => [
        'home_hero_help_bsl_heading' => 'Safe<script>alert(1)</script>',
        'home_hero_help_bsl_text' => 'Body',
      ],
    ]);

    $result = $this->invokeBuildHeroDescriptionMarkup($form);

    // Xss::filterAdmin() strips the unsafe <script> tag from the output.
    $this->assertStringNotContainsString('<script>', $result);

    // The safe text is preserved.
    $this->assertStringContainsString('Safe', $result);
  }

  /**
   * Invokes the protected buildHeroDescriptionMarkup() method via reflection.
   *
   * @param \Drupal\bioland\Form\BiolandSettingsForm $form
   *   The form instance (must expose config() returning bioland.settings).
   *
   * @return string
   *   The rendered hero description markup.
   */
  protected function invokeBuildHeroDescriptionMarkup(BiolandSettingsForm $form): string {
    // Reflect on the runtime class so the anonymous subclass's overridden,
    // protected config() (returning the injected test config) is used.
    $reflection = new \ReflectionObject($form);

    $configMethod = $reflection->getMethod('config');
    $configMethod->setAccessible(TRUE);
    $config = $configMethod->invoke($form, 'bioland.settings');

    $method = $reflection->getMethod('buildHeroDescriptionMarkup');
    $method->setAccessible(TRUE);

    return $method->invoke($form, $config);
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
    return new class($this->languageManager, $this->entityTypeManager, $this->translationBatchService, $this->database, $this->currentUser, $this->requestStack, $configObject) extends BiolandSettingsForm {
      protected $testConfig;
      
      public function __construct($languageManager, $entityTypeManager, $translationBatchService, $database, $currentUser, $requestStack, $config) {
        parent::__construct($languageManager, $entityTypeManager, $translationBatchService, $database, $currentUser, $requestStack);
        $this->testConfig = $config;
      }
      
      protected function config($name) {
        return $this->testConfig;
      }
    };
  }

}
