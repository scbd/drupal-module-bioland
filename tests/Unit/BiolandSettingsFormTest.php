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
use Drupal\Core\Config\Config;
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

  /**
   * Tests the mapper reads site name from the flattened value path.
   */
  public function testExtractGeneralSettingsReadsSiteName(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      ['site_name' => 'My Site', 'date_default_timezone' => 'UTC'],
      []
    );

    $this->assertSame('My Site', $mapped['site']['name']);
    $this->assertSame('UTC', $mapped['timezone']);
    $this->assertSame([], $mapped['overrides']);
  }

  /**
   * Tests the mapper still accepts the nested site_name_section path.
   */
  public function testExtractGeneralSettingsAcceptsNestedSiteName(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      ['site_name_section' => ['site_name' => 'Nested Site']],
      []
    );

    $this->assertSame('Nested Site', $mapped['site']['name']);
  }

  /**
   * Tests the mapper unwraps a text_format slogan array.
   */
  public function testExtractGeneralSettingsUnwrapsSloganTextFormat(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      [
        'site_name' => 'S',
        'site_slogan' => ['value' => 'Hello world', 'format' => 'full_html'],
      ],
      []
    );

    $this->assertSame('Hello world', $mapped['site']['slogan']);
  }

  /**
   * Tests a hidden slogan field is not mirrored (no accidental wipe).
   */
  public function testExtractGeneralSettingsSkipsAbsentSlogan(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      ['site_name' => 'S'],
      []
    );

    $this->assertArrayNotHasKey('slogan', $mapped['site']);
  }

  /**
   * Tests translations are read from the flattened top-level path.
   */
  public function testExtractGeneralSettingsReadsTranslationsFromFlatPath(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      [
        'site_name' => 'S',
        'site_name_translations' => ['fr' => 'Mon site', 'es' => 'Mi sitio'],
      ],
      ['fr', 'es']
    );

    $this->assertSame('Mon site', $mapped['overrides']['fr']['name']);
    $this->assertSame('Mi sitio', $mapped['overrides']['es']['name']);
  }

  /**
   * Tests an emptied translation maps to an empty string (removal signal).
   */
  public function testExtractGeneralSettingsEmptyTranslationSignalsRemoval(): void {
    $mapped = BiolandSettingsForm::extractGeneralSettings(
      [
        'site_name' => 'S',
        'site_name_translations' => ['fr' => ''],
      ],
      ['fr']
    );

    $this->assertSame('', $mapped['overrides']['fr']['name']);
  }

  /**
   * Tests the full submit round-trip writes to system.site and system.date.
   *
   * Proves the WRITE direction of the bidirectional mirror: values typed in
   * the Bioland form land on the same config objects the core forms edit.
   */
  public function testSubmitGeneralMirrorsToSystemConfig(): void {
    // Editable config objects the submit will write to.
    $siteConfig = new Config('system.site', ['name' => 'Old', 'slogan' => '', 'mail' => 'admin@example.com']);
    $dateConfig = new Config('system.date', ['timezone' => ['default' => 'UTC']]);
    $biolandConfig = new Config('bioland.settings', []);
    $frOverride = new Config('system.site', []);

    // Fake config factory returning the mutable stubs above.
    $factory = new class($siteConfig, $dateConfig, $biolandConfig) {
      public function __construct(private $site, private $date, private $bioland) {}
      public function get($name) {
        return $name === 'bioland.settings' ? $this->bioland : ($name === 'system.date' ? $this->date : $this->site);
      }
      public function getEditable($name) {
        return $name === 'system.date' ? $this->date : ($name === 'bioland.settings' ? $this->bioland : $this->site);
      }
    };

    // Language manager: default en, plus fr; fr override is a mutable stub.
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getDefaultLanguage')->willReturn(new Language('en', 'English'));
    $languageManager->method('getLanguages')->willReturn([
      'en' => new Language('en', 'English'),
      'fr' => new Language('fr', 'French'),
    ]);
    $languageManager->method('getLanguageConfigOverride')->willReturn($frOverride);

    $form = new class($languageManager, $this->entityTypeManager, $this->translationBatchService, $this->database, $this->currentUser, $this->requestStack, $factory) extends BiolandSettingsForm {
      public function __construct($lm, $etm, $tbs, $db, $cu, $rs, $factory) {
        parent::__construct($lm, $etm, $tbs, $db, $cu, $rs);
        $this->configFactory = $factory;
      }
    };

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')->with('bioland_section')->willReturn('general');
    $formState->method('getValues')->willReturn([
      'site_name' => 'New Site',
      'date_default_timezone' => 'America/Toronto',
      'site_name_translations' => ['fr' => 'Nouveau site'],
      'region' => '',
      'continent' => '',
    ]);

    $formArray = [];
    $form->submitForm($formArray, $formState);

    // system.site name mirrored and persisted.
    $this->assertSame('New Site', $siteConfig->get('name'));
    $this->assertTrue($siteConfig->saved);
    // Phantom site_mail no longer clobbers the existing mail.
    $this->assertSame('admin@example.com', $siteConfig->get('mail'));
    // system.date timezone mirrored and persisted.
    $this->assertSame('America/Toronto', $dateConfig->get('timezone.default'));
    $this->assertTrue($dateConfig->saved);
    // fr language override written and persisted.
    $this->assertSame('Nouveau site', $frOverride->get('name'));
    $this->assertTrue($frOverride->saved);
  }

  /**
   * Tests clearing a translation deletes the empty language override.
   */
  public function testSubmitGeneralDeletesEmptiedOverride(): void {
    $siteConfig = new Config('system.site', ['name' => 'Site', 'mail' => 'a@b.com']);
    $dateConfig = new Config('system.date', ['timezone' => ['default' => 'UTC']]);
    $biolandConfig = new Config('bioland.settings', []);
    // Override currently holds a translated name that the user is clearing.
    $frOverride = new Config('system.site', ['name' => 'Ancien site']);

    $factory = new class($siteConfig, $dateConfig, $biolandConfig) {
      public function __construct(private $site, private $date, private $bioland) {}
      public function get($name) {
        return $name === 'bioland.settings' ? $this->bioland : ($name === 'system.date' ? $this->date : $this->site);
      }
      public function getEditable($name) {
        return $name === 'system.date' ? $this->date : ($name === 'bioland.settings' ? $this->bioland : $this->site);
      }
    };

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getDefaultLanguage')->willReturn(new Language('en', 'English'));
    $languageManager->method('getLanguages')->willReturn([
      'en' => new Language('en', 'English'),
      'fr' => new Language('fr', 'French'),
    ]);
    $languageManager->method('getLanguageConfigOverride')->willReturn($frOverride);

    $form = new class($languageManager, $this->entityTypeManager, $this->translationBatchService, $this->database, $this->currentUser, $this->requestStack, $factory) extends BiolandSettingsForm {
      public function __construct($lm, $etm, $tbs, $db, $cu, $rs, $factory) {
        parent::__construct($lm, $etm, $tbs, $db, $cu, $rs);
        $this->configFactory = $factory;
      }
    };

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('get')->with('bioland_section')->willReturn('general');
    $formState->method('getValues')->willReturn([
      'site_name' => 'Site',
      'date_default_timezone' => 'UTC',
      'site_name_translations' => ['fr' => ''],
      'region' => '',
      'continent' => '',
    ]);

    $formArray = [];
    $form->submitForm($formArray, $formState);

    // The now-empty override is deleted rather than saved with an empty name.
    $this->assertTrue($frOverride->deleted);
  }

}
