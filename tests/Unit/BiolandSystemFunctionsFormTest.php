<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandSystemFunctionsForm;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for BiolandSystemFunctionsForm.
 *
 * @covers \Drupal\bioland\Form\BiolandSystemFunctionsForm
 */
class BiolandSystemFunctionsFormTest extends TestCase {

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
   * @return \Drupal\bioland\Form\BiolandSystemFunctionsForm
   *   The form instance.
   */
  protected function createForm(): BiolandSystemFunctionsForm {
    return new BiolandSystemFunctionsForm(
      $this->languageManager,
      $this->entityTypeManager,
      $this->translationBatchService,
      $this->database,
      $this->currentUser,
      $this->requestStack
    );
  }

  /**
   * Tests validateForm sets error when auto_create enabled but no entity types.
   */
  public function testValidateFormSetsErrorForMissingEntityTypes(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
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

}
