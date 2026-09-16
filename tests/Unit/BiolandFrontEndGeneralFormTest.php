<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandFrontEndGeneralForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the Google tag IDs field on BiolandFrontEndGeneralForm.
 *
 * @covers \Drupal\bioland\Form\BiolandFrontEndGeneralForm
 */
class BiolandFrontEndGeneralFormTest extends TestCase {

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

    $this->languageManager->method('getDefaultLanguage')
      ->willReturn(new Language('en', 'English'));
    $this->languageManager->method('getCurrentLanguage')
      ->willReturn(new Language('en', 'English'));
  }

  /**
   * Creates a form instance with mocked dependencies.
   *
   * @return \Drupal\bioland\Form\BiolandFrontEndGeneralForm
   *   The form instance.
   */
  protected function createForm(): BiolandFrontEndGeneralForm {
    return new BiolandFrontEndGeneralForm(
      $this->languageManager,
      $this->entityTypeManager,
      $this->translationBatchService,
      $this->database,
      $this->currentUser,
      $this->requestStack
    );
  }

  /**
   * Invokes a protected method on the form.
   *
   * @param \Drupal\bioland\Form\BiolandFrontEndGeneralForm $form
   *   The form instance.
   * @param string $name
   *   The method name.
   * @param array $args
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invoke(BiolandFrontEndGeneralForm $form, string $name, array $args = []) {
    $method = (new \ReflectionClass($form))->getMethod($name);
    $method->setAccessible(TRUE);

    return $method->invokeArgs($form, $args);
  }

  /**
   * A mutable config double for `bioland.settings`.
   *
   * @param array $data
   *   The starting data.
   *
   * @return \Drupal\Core\Config\Config
   *   The config double, which records save()/delete() calls.
   */
  protected function config(array $data = []): Config {
    return new Config('bioland.settings', $data);
  }

  /**
   * A form-state double over a fixed value tree.
   *
   * The form does not set #tree, so the Google tag IDs value sits flat at
   * the top of the value tree. Every setErrorByName() call is recorded so
   * tests can assert both that an error fires and that it does not.
   *
   * @param array $values
   *   The submitted values.
   *
   * @return object
   *   The form-state double.
   */
  protected function formState(array $values) {
    return new class($values) implements \Drupal\Core\Form\FormStateInterface {
      public $values;
      public $errors = [];
      public $store = [];

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function getValues() {
        return $this->values;
      }

      public function getValue($key, $default = NULL) {
        $keys = is_array($key) ? $key : [$key];
        $cursor = $this->values;
        foreach ($keys as $segment) {
          if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return $default;
          }
          $cursor = $cursor[$segment];
        }
        return $cursor;
      }

      public function setValue($key, $value) {
        $this->values[$key] = $value;
        return $this;
      }

      public function get($key) {
        return $this->store[$key] ?? NULL;
      }

      public function set($key, $value) {
        $this->store[$key] = $value;
        return $this;
      }

      public function setErrorByName($name, $message = '') {
        $this->errors[$name] = (string) $message;
        return $this;
      }

      public function getErrors() {
        return $this->errors;
      }

      public function setRedirect($route_name, array $route_parameters = [], array $options = []) {
        return $this;
      }

    };
  }

  /**
   * Tests the form ID is unchanged.
   */
  public function testGetFormIdReturnsCorrectId(): void {
    $this->assertSame(
      'bioland_settings_front_end_general_form',
      $this->createForm()->getFormId()
    );
  }

  /**
   * Tests a valid mixed list normalizes to both IDs in order.
   */
  public function testNormalizeGoogleTagIdsReturnsCanonicalList(): void {
    $form = $this->createForm();

    $this->assertSame(
      ['G-ABC1234567', 'GTM-XYZ789'],
      $this->invoke($form, 'normalizeGoogleTagIds', ['G-ABC1234567,GTM-XYZ789'])
    );
  }

  /**
   * Tests a repeated ID is de-duplicated, keeping first-seen order.
   */
  public function testNormalizeGoogleTagIdsRemovesDuplicates(): void {
    $form = $this->createForm();

    $this->assertSame(
      ['G-ABC1234567', 'GTM-XYZ789'],
      $this->invoke($form, 'normalizeGoogleTagIds', ['G-ABC1234567,GTM-XYZ789,G-ABC1234567'])
    );
  }

  /**
   * Tests tokens are trimmed and upper-cased on the way through.
   */
  public function testNormalizeGoogleTagIdsTrimsAndUpperCases(): void {
    $form = $this->createForm();

    $this->assertSame(
      ['G-ABC1234567', 'GTM-XYZ789'],
      $this->invoke($form, 'normalizeGoogleTagIds', [' g-abc1234567 , gtm-xyz789 '])
    );
  }

  /**
   * Tests empty tokens are dropped.
   */
  public function testNormalizeGoogleTagIdsDropsEmptyTokens(): void {
    $form = $this->createForm();

    $this->assertSame(
      ['G-ABC1234567'],
      $this->invoke($form, 'normalizeGoogleTagIds', ['G-ABC1234567,,'])
    );
  }

  /**
   * Tests '', NULL, and non-strings all normalize to an empty list.
   *
   * @param mixed $value
   *   The raw value.
   *
   * @dataProvider emptyNormalizerValueProvider
   */
  public function testNormalizeGoogleTagIdsEmptyValuesYieldEmptyList($value): void {
    $form = $this->createForm();

    $this->assertSame(
      [],
      $this->invoke($form, 'normalizeGoogleTagIds', [$value])
    );
  }

  /**
   * Values that must normalize to an empty list.
   *
   * @return array
   *   Test cases.
   */
  public function emptyNormalizerValueProvider(): array {
    return [
      'empty string' => [''],
      'null' => [NULL],
      'non-string' => [['G-ABC1234567']],
    ];
  }

  /**
   * Tests a valid mixed list raises no validation error.
   */
  public function testValidateFormAcceptsValidMixedList(): void {
    $form = [];
    $formState = $this->formState(['google_analytics_ids' => 'G-ABC1234567,GTM-XYZ789']);

    $this->createForm()->validateForm($form, $formState);

    $this->assertSame([], $formState->getErrors());
  }

  /**
   * Tests an invalid token raises an error naming the offending token.
   *
   * @param string $value
   *   The submitted value.
   * @param string $expectedToken
   *   The invalid token the error message must name.
   *
   * @dataProvider invalidGoogleTagIdProvider
   */
  public function testValidateFormRejectsInvalidTokens(string $value, string $expectedToken): void {
    $form = [];
    $formState = $this->formState(['google_analytics_ids' => $value]);

    $this->createForm()->validateForm($form, $formState);

    $errors = $formState->getErrors();
    $this->assertArrayHasKey('google_analytics_ids', $errors);
    $this->assertStringContainsString($expectedToken, $errors['google_analytics_ids']);
  }

  /**
   * Invalid tag ID shapes and the token each error must name.
   *
   * @return array
   *   Test cases.
   */
  public function invalidGoogleTagIdProvider(): array {
    return [
      'no prefix' => ['ABC123', 'ABC123'],
      'unknown prefix' => ['XX-ABC123', 'XX-ABC123'],
      'bare prefix' => ['G-', 'G-'],
      'prefix with no hyphen' => ['GABC123', 'GABC123'],
      'illegal character' => ['G-ABC_123', 'G-ABC_123'],
    ];
  }

  /**
   * Tests '' and NULL validate clean: the feature is off, not a failure.
   *
   * @param mixed $value
   *   The submitted value.
   *
   * @dataProvider emptySubmissionProvider
   */
  public function testValidateFormAcceptsEmptySubmissions($value): void {
    $form = [];
    $formState = $this->formState(['google_analytics_ids' => $value]);

    $this->createForm()->validateForm($form, $formState);

    $this->assertSame([], $formState->getErrors());
  }

  /**
   * Empty submissions that must validate clean.
   *
   * @return array
   *   Test cases.
   */
  public function emptySubmissionProvider(): array {
    return [
      'empty string' => [''],
      'null' => [NULL],
    ];
  }

  /**
   * Tests one ID of every accepted prefix validates clean.
   */
  public function testValidateFormAcceptsEveryPrefix(): void {
    $form = [];
    $formState = $this->formState([
      'google_analytics_ids' => 'G-ABC1234567,GTM-XYZ789,AW-DEF1234567,DC-GHI1234567,UA-JKL1234567',
    ]);

    $this->createForm()->validateForm($form, $formState);

    $this->assertSame([], $formState->getErrors());
  }

  /**
   * Tests the built field's type, maxlength, and config-backed default.
   */
  public function testGoogleTagIdsFieldShape(): void {
    $config = $this->config(['google_analytics_ids' => 'G-ABC1234567,GTM-XYZ789']);
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $field = $form['front_end_general_settings']['google_analytics_section']['google_analytics_ids'];

    $this->assertSame('textfield', $field['#type']);
    $this->assertSame(512, $field['#maxlength']);
    $this->assertSame('G-ABC1234567,GTM-XYZ789', $field['#default_value']);
  }

  /**
   * Tests an unset default falls back to '' rather than NULL.
   */
  public function testGoogleTagIdsFieldDefaultsToEmptyString(): void {
    $config = $this->config();
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $field = $form['front_end_general_settings']['google_analytics_section']['google_analytics_ids'];

    $this->assertSame('', $field['#default_value']);
  }

  /**
   * Tests submitSectionForm() writes the canonical imploded string.
   */
  public function testSubmitSectionFormWritesCanonicalGoogleTagIds(): void {
    $config = $this->config();
    $form = [];
    $values = [
      'google_analytics_ids' => ' g-abc1234567 , gtm-xyz789 ,g-abc1234567',
    ];

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    // The literal key google_analytics_ids is the one written, in canonical
    // comma-joined form.
    $this->assertSame(
      'G-ABC1234567,GTM-XYZ789',
      $config->get('google_analytics_ids')
    );
  }

  /**
   * Tests an empty submission submits '' rather than leaving the key alone.
   */
  public function testSubmitSectionFormWritesEmptyStringWhenCleared(): void {
    $config = $this->config(['google_analytics_ids' => 'G-ABC1234567']);
    $form = [];
    $values = ['google_analytics_ids' => ''];

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertSame('', $config->get('google_analytics_ids'));
  }

  /**
   * Tests the switch is a checkbox that is off when config has never set it.
   */
  public function testGoogleAnalyticsEnabledFieldDefaultsToDisabled(): void {
    $config = $this->config();
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $field = $form['front_end_general_settings']['google_analytics_section']['google_analytics_enabled'];

    $this->assertSame('checkbox', $field['#type']);
    $this->assertFalse($field['#default_value']);
  }

  /**
   * Tests a site that has turned the switch on builds it checked.
   */
  public function testGoogleAnalyticsEnabledFieldReflectsSavedValue(): void {
    $config = $this->config(['google_analytics_enabled' => TRUE]);
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $field = $form['front_end_general_settings']['google_analytics_section']['google_analytics_enabled'];

    $this->assertTrue($field['#default_value']);
  }

  /**
   * Tests the switch is rendered above the tag IDs field it controls.
   *
   * Drupal renders siblings without #weight in declaration order, so the key
   * order in the section is the rendered order.
   */
  public function testGoogleAnalyticsEnabledIsDeclaredAboveTheIdsField(): void {
    $config = $this->config();
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $keys = array_keys($form['front_end_general_settings']['google_analytics_section']);
    $keys = array_values(array_filter($keys, static fn ($key): bool => strpos((string) $key, '#') !== 0));

    $this->assertSame(['google_analytics_enabled', 'google_analytics_ids'], $keys);
  }

  /**
   * Tests a checked submission stores a real boolean TRUE.
   */
  public function testSubmitSectionFormWritesEnabledAsBoolean(): void {
    $config = $this->config();
    $form = [];
    // Drupal submits a checked checkbox as the integer 1, not TRUE.
    $values = ['google_analytics_enabled' => 1, 'google_analytics_ids' => ''];

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertTrue($config->get('google_analytics_enabled'));
  }

  /**
   * Tests an unchecked or absent submission stores FALSE, not NULL.
   */
  public function testSubmitSectionFormWritesDisabledWhenUnchecked(): void {
    $config = $this->config(['google_analytics_enabled' => TRUE]);
    $form = [];
    // An unchecked checkbox submits 0; a missing key must behave the same.
    $values = ['google_analytics_ids' => ''];

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertFalse($config->get('google_analytics_enabled'));
  }

}