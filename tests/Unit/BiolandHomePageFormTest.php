<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandHomePageForm;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for BiolandHomePageForm.
 *
 * @covers \Drupal\bioland\Form\BiolandHomePageForm
 */
class BiolandHomePageFormTest extends TestCase {

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
   * @return \Drupal\bioland\Form\BiolandHomePageForm
   *   The form instance.
   */
  protected function createForm(): BiolandHomePageForm {
    return new BiolandHomePageForm(
      $this->languageManager,
      $this->entityTypeManager,
      $this->translationBatchService,
      $this->database,
      $this->currentUser,
      $this->requestStack
    );
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
   * @param \Drupal\bioland\Form\BiolandHomePageForm $form
   *   The form instance (must expose config() returning bioland.settings).
   *
   * @return string
   *   The rendered hero description markup.
   */
  protected function invokeBuildHeroDescriptionMarkup(BiolandHomePageForm $form): string {
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
   * @return \Drupal\bioland\Form\BiolandHomePageForm
   *   The form instance.
   */
  protected function createFormWithConfig(array $configData): BiolandHomePageForm {
    $form = $this->createForm();

    // Use reflection to inject a mock config method.
    $configObject = new ImmutableConfig('bioland.settings', $configData);

    // Create a mock that returns the config.
    $reflection = new \ReflectionClass($form);

    // Create a subclass that overrides config method for testing.
    return new class($this->languageManager, $this->entityTypeManager, $this->translationBatchService, $this->database, $this->currentUser, $this->requestStack, $configObject) extends BiolandHomePageForm {
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
