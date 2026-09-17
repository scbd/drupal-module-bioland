<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandFrontEndGeneralForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
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

    \Drupal::resetContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
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
   * A recording messenger double that captures every message by type.
   *
   * @return object
   *   The messenger double, exposing ->warnings/->statuses/->errors arrays
   *   of message strings in call order.
   */
  protected function recordingMessenger() {
    return new class {
      public array $warnings = [];
      public array $statuses = [];
      public array $errors = [];

      public function addMessage($message, $type = 'status') {}

      public function addStatus($message) {
        $this->statuses[] = (string) $message;
      }

      public function addWarning($message) {
        $this->warnings[] = (string) $message;
      }

      public function addError($message) {
        $this->errors[] = (string) $message;
      }

    };
  }

  /**
   * A recording logger factory + 'bioland' channel pair.
   *
   * Register the factory as the 'logger.factory' service so
   * \Drupal::logger('bioland') resolves to the returned channel.
   *
   * @return array{0: object, 1: object}
   *   [$factory, $channel]. $channel exposes ->notices, each entry a
   *   ['message' => string, 'context' => array] pair, in call order.
   */
  protected function recordingLogger(): array {
    $channel = new class {
      public array $notices = [];

      public function notice($message, array $context = []) {
        $this->notices[] = ['message' => (string) $message, 'context' => $context];
      }

    };

    $factory = new class($channel) {
      private $channel;

      public function __construct($channel) {
        $this->channel = $channel;
      }

      public function get($channel_name) {
        return $this->channel;
      }

    };

    return [$factory, $channel];
  }

  /**
   * Injects a config factory returning $config for 'bioland.settings'.
   *
   * Production forms get a config factory through create()'s container
   * lookup; tests construct the form directly, so a full submitForm() pass
   * must inject one directly onto the protected property FormBase declares.
   *
   * @param \Drupal\bioland\Form\BiolandFrontEndGeneralForm $bioForm
   *   The form instance.
   * @param \Drupal\Core\Config\Config $config
   *   The config double submitForm() must read and save.
   */
  protected function injectConfigFactory(BiolandFrontEndGeneralForm $bioForm, Config $config): void {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('bioland.settings')->willReturn($config);
    $factory->method('getEditable')->with('bioland.settings')->willReturn($config);

    $property = (new \ReflectionClass($bioForm))->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($bioForm, $factory);
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
   * Tests both help texts retain the current host and consent restrictions.
   */
  public function testGoogleAnalyticsHelpDescribesCurrentLoadingRestrictions(): void {
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $this->config()]);
    $section = $form['front_end_general_settings']['google_analytics_section'];

    foreach (['google_analytics_enabled', 'google_analytics_ids'] as $key) {
      $description = (string) $section[$key]['#description'];
      $this->assertStringContainsString('only on the production host of a bl2 site (other multisites do not load Google tags today)', $description);
      $this->assertStringContainsString('Google Analytics cookie category', $description);
      $this->assertStringContainsString('Saved changes reach the public site within about 5 minutes', $description);
      $this->assertStringNotContainsString('all that is required', $description);
      $this->assertStringNotContainsString('subject only to', $description);
    }

    $description = (string) $section['google_analytics_enabled']['#description'];
    $this->assertStringContainsString('Off by default.', $description);
    $this->assertStringContainsString('While this is off the public site loads no Google tag, even when tag IDs are configured below.', $description);
    $this->assertStringContainsString('Turning it on allows the configured IDs to load only for visitors who accept', $description);
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
   * Tests a stored non-boolean renders unticked, matching what the head does.
   *
   * Drupal does not enforce the boolean schema on a write, so drush or a
   * hand-edited import can leave a string here. The head gates on === true, so
   * none of these load a tag; the checkbox must say the same thing.
   *
   * @dataProvider nonBooleanStoredValues
   */
  public function testGoogleAnalyticsEnabledFieldIsUntickedForNonBooleans($stored): void {
    $config = $this->config(['google_analytics_enabled' => $stored]);
    $form = $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);

    $field = $form['front_end_general_settings']['google_analytics_section']['google_analytics_enabled'];

    $this->assertFalse($field['#default_value']);
  }

  /**
   * Stored values that are not the boolean TRUE.
   *
   * @return array<string, array{mixed}>
   *   Test cases.
   */
  public static function nonBooleanStoredValues(): array {
    return [
      'string true'  => ['true'],
      'string false' => ['false'],
      'string one'   => ['1'],
      'string zero'  => ['0'],
      'integer one'  => [1],
      'integer zero' => [0],
      'null'         => [NULL],
      'boolean false' => [FALSE],
    ];
  }

  /**
   * Tests turning the switch on with no tag IDs warns instead of failing.
   */
  public function testValidateFormWarnsWhenEnabledWithoutIds(): void {
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => '',
    ]);
    $messenger = $this->recordingMessenger();
    $bioForm = $this->createForm();
    $bioForm->setMessenger($messenger);

    $bioForm->validateForm($form, $formState);

    // A warning, not a form error: this is a legitimate order of work.
    $this->assertSame([], $formState->getErrors());
    $this->assertCount(1, $messenger->warnings);
    $this->assertStringContainsString('no Google tag IDs are configured', $messenger->warnings[0]);
  }

  /**
   * Tests the switch off with no IDs raises no warning: nothing to load.
   */
  public function testValidateFormRaisesNoWarningWhenDisabledWithoutIds(): void {
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 0,
      'google_analytics_ids' => '',
    ]);
    $messenger = $this->recordingMessenger();
    $bioForm = $this->createForm();
    $bioForm->setMessenger($messenger);

    $bioForm->validateForm($form, $formState);

    $this->assertSame([], $messenger->warnings);
  }

  /**
   * Tests the switch on with IDs configured raises no warning.
   */
  public function testValidateFormRaisesNoWarningWhenEnabledWithIds(): void {
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => 'G-ABC1234567',
    ]);
    $messenger = $this->recordingMessenger();
    $bioForm = $this->createForm();
    $bioForm->setMessenger($messenger);

    $bioForm->validateForm($form, $formState);

    $this->assertSame([], $messenger->warnings);
  }

  /**
   * Tests the switch on with only invalid IDs raises no warning.
   *
   * The `!$invalid` guard suppresses it: the form error already tells the
   * administrator something is wrong, so the warning would be redundant.
   */
  public function testValidateFormRaisesNoWarningWhenEnabledWithOnlyInvalidIds(): void {
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => 'NOT-VALID',
    ]);
    $messenger = $this->recordingMessenger();
    $bioForm = $this->createForm();
    $bioForm->setMessenger($messenger);

    $bioForm->validateForm($form, $formState);

    $this->assertNotEmpty($formState->getErrors());
    $this->assertSame([], $messenger->warnings);
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

  /**
   * Tests a FALSE-to-TRUE transition logs an 'enabled' audit notice.
   */
  public function testSubmitFormLogsEnabledTransition(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);
    $this->currentUser->method('id')->willReturn(42);

    $config = $this->config(['google_analytics_enabled' => FALSE]);
    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => '',
    ]);

    $bioForm->submitForm($form, $formState);

    $this->assertCount(1, $channel->notices);
    $this->assertSame(['@state' => 'enabled', '@uid' => 42], $channel->notices[0]['context']);
  }

  /**
   * Tests a TRUE-to-FALSE transition logs a 'disabled' audit notice.
   */
  public function testSubmitFormLogsDisabledTransition(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);
    $this->currentUser->method('id')->willReturn(7);

    $config = $this->config(['google_analytics_enabled' => TRUE]);
    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    // An unchecked checkbox submits no key at all.
    $formState = $this->formState(['google_analytics_ids' => '']);

    $bioForm->submitForm($form, $formState);

    $this->assertCount(1, $channel->notices);
    $this->assertSame(['@state' => 'disabled', '@uid' => 7], $channel->notices[0]['context']);
  }

  /**
   * Tests an unrelated save (only the tag IDs change) logs no audit notice.
   */
  public function testSubmitFormLogsNoNoticeWhenOnlyIdsChange(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);

    $config = $this->config(['google_analytics_enabled' => TRUE, 'google_analytics_ids' => '']);
    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => 'G-ABC1234567',
    ]);

    $bioForm->submitForm($form, $formState);

    $this->assertSame([], $channel->notices);
  }

  /**
   * Tests no audit notice ever carries a Google tag ID in its context.
   *
   * The tag IDs are never logged - only the fact that the switch moved and
   * who moved it.
   */
  public function testSubmitFormNeverLogsTagIdsInContext(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);
    $this->currentUser->method('id')->willReturn(1);

    $config = $this->config(['google_analytics_enabled' => FALSE]);
    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => 'G-ABC1234567,GTM-XYZ789',
    ]);

    $bioForm->submitForm($form, $formState);

    $this->assertNotEmpty($channel->notices);
    foreach ($channel->notices as $notice) {
      foreach ($notice['context'] as $value) {
        $this->assertDoesNotMatchRegularExpression(
          '/\b(G|GTM|AW|DC|UA)-/',
          (string) $value,
          'No audit notice may carry a Google tag ID token in its context.'
        );
      }
    }
  }

  /**
   * Tests the audit notice is never written when config->save() throws.
   *
   * The audit control exists so the log and the active config agree; writing
   * it before persistence is the one ordering that breaks that.
   */
  public function testSubmitFormWritesNoAuditNoticeWhenSaveThrows(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);
    $this->currentUser->method('id')->willReturn(1);

    $config = new class('bioland.settings', ['google_analytics_enabled' => FALSE]) extends Config {

      public function save($has_trusted_data = FALSE) {
        throw new \RuntimeException('storage failure');
      }

    };

    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    $formState = $this->formState([
      'google_analytics_enabled' => 1,
      'google_analytics_ids' => '',
    ]);

    try {
      $bioForm->submitForm($form, $formState);
      $this->fail('Expected the config->save() failure to propagate.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('storage failure', $e->getMessage());
    }

    $this->assertSame([], $channel->notices);
  }

  /**
   * Tests an out-of-band non-boolean stored value logs a distinct notice.
   *
   * Both the render and the ordinary change detector already read a
   * non-boolean as off, so without this the save would silently normalize it
   * to a real boolean with no record at all.
   */
  public function testSubmitFormLogsDistinctNoticeWhenNormalizingNonBoolean(): void {
    [$factory, $channel] = $this->recordingLogger();
    \Drupal::setService('logger.factory', $factory);
    $this->currentUser->method('id')->willReturn(9);

    $config = $this->config(['google_analytics_enabled' => 'true']);
    $bioForm = $this->createForm();
    $this->injectConfigFactory($bioForm, $config);
    $form = [];
    // The checkbox rendered unticked (the non-boolean reads as off), so an
    // unrelated save on this form submits it unchecked.
    $formState = $this->formState(['google_analytics_ids' => '']);

    $bioForm->submitForm($form, $formState);

    $this->assertCount(1, $channel->notices);
    $this->assertStringContainsString('normalized', $channel->notices[0]['message']);
    $this->assertSame(['@state' => 'disabled'], $channel->notices[0]['context']);
    $this->assertFalse($config->get('google_analytics_enabled'));
  }

}