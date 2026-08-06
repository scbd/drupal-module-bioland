<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\BiolandHomeWidgetRegistry;
use Drupal\bioland\BiolandThemeContract;
use Drupal\bioland\Form\BiolandThemeForm;
use Drupal\bioland\Service\BiolandDmsmConfigService;
use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the Theme tab.
 *
 * @covers \Drupal\bioland\Form\BiolandThemeForm
 * @covers \Drupal\bioland\Service\BiolandDmsmConfigService::getEffectiveTheme
 */
class BiolandThemeFormTest extends TestCase {

  /**
   * Config-key fragments that must never appear anywhere in the built form.
   *
   * `hero` is derived downstream and never authored (D4); the rest are the
   * dead legacy keys the plan drops.
   */
  private const FORBIDDEN_KEY_FRAGMENTS = [
    'hero',
    'text_over',
    'textOver',
    'can_auto_translate',
    'canAutoTranslate',
    'column_wrap',
    'horizontal_card_wrap',
    'show_empty',
    'colums_width',
    'columsWidth',
    'tertiary',
  ];

  /**
   * The mock language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->languageManager->method('getDefaultLanguage')->willReturn(new Language('en', 'English'));
    $this->languageManager->method('getCurrentLanguage')->willReturn(new Language('en', 'English'));

    \Drupal::resetContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  // ---------------------------------------------------------------------
  // Harness.
  // ---------------------------------------------------------------------

  /**
   * Creates the form under test.
   *
   * @return \Drupal\bioland\Form\BiolandThemeForm
   *   The form.
   */
  protected function createForm(): BiolandThemeForm {
    return new BiolandThemeForm(
      $this->languageManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(BiolandTranslationBatchService::class),
      $this->createMock(Connection::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(RequestStack::class)
    );
  }

  /**
   * Invokes a protected method on the form.
   *
   * @param \Drupal\bioland\Form\BiolandThemeForm $form
   *   The form.
   * @param string $name
   *   The method name.
   * @param array $args
   *   The arguments.
   *
   * @return mixed
   *   The return value.
   */
  protected function invoke(BiolandThemeForm $form, string $name, array $args = []) {
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
   * getValue() honours the `#tree` structure the form builds, and every
   * setErrorByName() call is recorded so tests can assert both that an error
   * fires and that it does not.
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
   * Registers a dmsm service double returning a fixed effective theme.
   *
   * @param mixed $effective
   *   The value getEffectiveTheme() should return.
   * @param int|null $expectedCalls
   *   Exact number of expected calls, or NULL for "any".
   */
  protected function stubDmsmService($effective, ?int $expectedCalls = NULL): void {
    $service = $this->createMock(BiolandDmsmConfigService::class);
    $matcher = $expectedCalls === NULL ? $this->any() : $this->exactly($expectedCalls);
    $service->expects($matcher)->method('getEffectiveTheme')->willReturn($effective);

    \Drupal::setService(BiolandThemeForm::DMSM_SERVICE_ID, $service);
  }

  /**
   * A messenger double that records every message by severity.
   *
   * @return object
   *   The recording messenger.
   */
  protected function recordingMessenger() {
    return new class {
      public $warnings = [];
      public $statuses = [];
      public $errors = [];

      public function addMessage($message, $type = 'status') {
        $this->statuses[] = (string) $message;
      }

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
   * Builds the section form against a config double.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config double.
   *
   * @return array
   *   The built form.
   */
  protected function build(Config $config): array {
    return $this->invoke($this->createForm(), 'buildSectionForm', [[], $this->formState([]), $config]);
  }

  /**
   * Builds the section form and returns both the form and its messages.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The config double.
   *
   * @return array
   *   [built form, recording messenger].
   */
  protected function buildWithMessenger(Config $config): array {
    $form = $this->createForm();
    $messenger = $this->recordingMessenger();
    $form->setMessenger($messenger);

    $built = $this->invoke($form, 'buildSectionForm', [[], $this->formState([]), $config]);

    return [$built, $messenger];
  }

  /**
   * The four shared H2 fixture cases.
   *
   * @return array
   *   The decoded cases, keyed by their id.
   */
  protected function fixtureCases(): array {
    $path = __DIR__ . '/fixtures/theme-effective-values.json';
    $decoded = json_decode(file_get_contents($path), TRUE);
    $this->assertIsArray($decoded, 'theme-effective-values.json must be valid JSON.');

    $cases = [];
    foreach ($decoded['cases'] as $case) {
      $cases[$case['id']] = $case;
    }

    return $cases;
  }

  /**
   * Runs getEffectiveTheme() against a canned dmsm document.
   *
   * @param array $document
   *   The dmsm config document.
   *
   * @return array|null
   *   The effective theme.
   */
  protected function effectiveThemeFor(array $document) {
    $body = new class(json_encode($document)) {
      private $json;

      public function __construct($json) {
        $this->json = $json;
      }

      public function getContents() {
        return $this->json;
      }

    };
    $response = new class($body) {
      private $body;

      public function __construct($body) {
        $this->body = $body;
      }

      public function getBody() {
        return $this->body;
      }

    };

    $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
    $httpClient->method('request')->willReturn($response);

    $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $loggerFactory->method('get')->willReturn($this->createMock('Drupal\Core\Logger\LoggerChannelInterface'));

    $service = new BiolandDmsmConfigService(
      $this->createMock('Drupal\Core\Config\ConfigFactoryInterface'),
      $httpClient,
      $loggerFactory
    );

    return $service->getEffectiveTheme('demo.bl2.chm-cbd.net');
  }

  /**
   * Flattens a nested array to dot-path => value leaves.
   *
   * Lists are leaves; only associative arrays recurse.
   *
   * @param array $data
   *   The nested array.
   * @param string $prefix
   *   The current path prefix.
   *
   * @return array
   *   Dot-path => value.
   */
  protected function flatten(array $data, string $prefix = ''): array {
    $flat = [];
    foreach ($data as $key => $value) {
      $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
      if (is_array($value) && $value !== [] && !array_is_list($value)) {
        $flat += $this->flatten($value, $path);
      }
      else {
        $flat[$path] = $value;
      }
    }

    return $flat;
  }

  // ---------------------------------------------------------------------
  // Identity and wiring.
  // ---------------------------------------------------------------------

  /**
   * The form id is distinct and follows the settings-form convention.
   */
  public function testFormIdAndSection(): void {
    $form = $this->createForm();
    $this->assertSame('bioland_settings_front_end_theme_form', $form->getFormId());
    $this->assertSame('front_end_theme', $this->invoke($form, 'getSection'));
  }

  // ---------------------------------------------------------------------
  // D4: every live key present and typed; hero and dead keys absent.
  // ---------------------------------------------------------------------

  /**
   * Every D4 live key is rendered with the type, bounds and required flag the
   * plan's field table specifies.
   */
  public function testEveryLiveKeyIsPresentAndTyped(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config(['theme' => ['color' => ['primary' => '#123456']]]));

    $colors = [
      ['color', 'primary'],
      ['color', 'secondary'],
      ['back_ground', 'secondary'],
    ];
    foreach ($colors as [$group, $key]) {
      $element = $form['theme'][$group][$key];
      $this->assertSame('color', $element['#type'], "{$group}.{$key} must be a hex colour picker.");
      $this->assertTrue($element['#required'], "{$group}.{$key} must be required (D3).");
    }

    $this->assertSame('checkbox', $form['theme']['mega_menu']['forums']['#type']);

    $maxColumns = $form['theme']['mega_menu']['max_columns'];
    $this->assertSame('number', $maxColumns['#type']);
    $this->assertSame(1, $maxColumns['#min']);
    $this->assertSame(6, $maxColumns['#max']);

    $maxRows = $form['theme']['mega_menu']['max_rows_per_column'];
    $this->assertSame('number', $maxRows['#type']);
    $this->assertSame(0, $maxRows['#min'], '0 means unlimited and must be reachable.');
    $this->assertArrayNotHasKey('#max', $maxRows, 'max_rows_per_column has no upper bound.');

    $cardMax = $form['theme']['mega_menu']['horizontal_card_max'];
    $this->assertSame('number', $cardMax['#type']);
    $this->assertSame(1, $cardMax['#min']);
    $this->assertSame(6, $cardMax['#max']);

    $wrap = $form['theme']['i18n']['max_lang_before_wrap'];
    $this->assertSame('number', $wrap['#type']);
    $this->assertTrue($wrap['#required']);

    $this->assertCount(
      BiolandThemeContract::HOME_PAGE_WIDGETS_COLUMN_COUNT,
      $form['theme']['home_page_widgets']['columns']
    );
  }

  /**
   * Every optional number says that blanking it is a no-op, not a clear.
   *
   * submitSectionForm() deliberately skips a blank instead of clearing the
   * key, so an editor who empties the field, saves, and sees the old value
   * come back has no way to tell that from a bug. The description is the
   * explanation, and it points at the control that does un-author. The
   * #required i18n number is excluded on purpose: core rejects a blank there
   * before the writer ever runs, so the advice would be wrong.
   */
  public function testOptionalNumbersExplainThatBlankingKeepsTheCurrentValue(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    foreach (['max_columns', 'max_rows_per_column', 'horizontal_card_max'] as $key) {
      $description = (string) ($form['theme']['mega_menu'][$key]['#description'] ?? '');
      $this->assertStringContainsString(
        'Leave blank to keep the current value',
        $description,
        "mega_menu.{$key} must say that a blank is kept, not cleared."
      );
      $this->assertStringContainsString(
        'Reset to network default',
        $description,
        "mega_menu.{$key} must name the control that does un-author."
      );
    }

    $this->assertArrayNotHasKey(
      '#description',
      $form['theme']['i18n']['max_lang_before_wrap'],
      'A #required number cannot be left blank, so the advice must not appear there.'
    );
  }

  /**
   * Neither hero nor any dead key is offered as a form element.
   */
  public function testHeroAndDeadKeysAreAbsentFromTheForm(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $paths = array_keys($this->flatten($form['theme']));

    foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
      foreach ($paths as $path) {
        // Ignore render-array properties; only real element keys matter.
        $elementPath = implode('.', array_filter(
          explode('.', $path),
          static fn(string $segment): bool => strpos($segment, '#') !== 0
        ));
        $this->assertStringNotContainsStringIgnoringCase(
          $fragment,
          $elementPath,
          sprintf('The Theme tab must not author "%s" (found at %s).', $fragment, $path)
        );
      }
    }
  }

  // ---------------------------------------------------------------------
  // D5 / H3: seed on save, never on GET.
  // ---------------------------------------------------------------------

  /**
   * Building the form performs zero config saves.
   *
   * This is the deliberate deviation from
   * BiolandHomeWidgetsForm::ensureHomeWidgetDefaults(), which saves during its
   * own build. A GET must never mutate a site's config.
   */
  public function testBuildPerformsZeroConfigSaves(): void {
    $this->stubDmsmService($this->fixtureCases()['theme-less-site']['expectedEffectiveTheme']);

    $config = $this->config();
    $this->build($config);

    $this->assertFalse($config->saved, 'Building the Theme tab must never call $config->save().');
    $this->assertFalse($config->deleted, 'Building the Theme tab must never delete config.');
    $this->assertNull($config->get('theme'), 'Building the Theme tab must not write the seed into config.');
  }

  /**
   * The seed pre-fills the fields when `theme` is empty.
   */
  public function testSeedPreFillsWhenThemeConfigIsEmpty(): void {
    $case = $this->fixtureCases()['theme-less-site'];
    $this->stubDmsmService($case['expectedEffectiveTheme'], 1);

    $form = $this->build($this->config());

    $this->assertSame('#009edb', $form['theme']['color']['primary']['#default_value']);
    $this->assertSame('#16c56e', $form['theme']['color']['secondary']['#default_value']);
    $this->assertSame('#F2F2F2', $form['theme']['back_ground']['secondary']['#default_value']);
    $this->assertSame(6, $form['theme']['i18n']['max_lang_before_wrap']['#default_value']);
    $this->assertSame(
      ['panorama', 'gbif', 'eLearning'],
      $form['theme']['home_page_widgets']['columns'][0]['#default_value']
    );
  }

  /**
   * An already-authored theme wins and the seed is never fetched.
   */
  public function testSeedIsSkippedWhenThemeConfigIsPopulated(): void {
    // exactly(0): the service must not be consulted at all.
    $this->stubDmsmService(['color' => ['primary' => '#ffffff']], 0);

    $form = $this->build($this->config([
      'theme' => [
        'color' => ['primary' => '#abcdef', 'secondary' => '#fedcba'],
      ],
    ]));

    $this->assertSame('#abcdef', $form['theme']['color']['primary']['#default_value']);
  }

  /**
   * An authored theme made entirely of falsy values still counts as authored.
   *
   * Presence, not truthiness: `maxRowsPerColumn: 0` and `forums: false` are
   * real authored values, so the seed must stay away.
   */
  public function testFalsyAuthoredValuesAreTreatedAsAuthored(): void {
    $this->stubDmsmService(['megaMenu' => ['maxRowsPerColumn' => 6, 'forums' => TRUE]], 0);

    $form = $this->build($this->config([
      'theme' => [
        'mega_menu' => ['forums' => FALSE, 'max_rows_per_column' => 0],
      ],
    ]));

    $this->assertSame(0, $form['theme']['mega_menu']['max_rows_per_column']['#default_value']);
    $this->assertFalse($form['theme']['mega_menu']['forums']['#default_value']);
  }

  /**
   * A seeded 0 / FALSE survives the mapping into form defaults.
   */
  public function testSeedPreservesZeroAndFalse(): void {
    $case = $this->fixtureCases()['bsl-zero-and-false'];
    $this->stubDmsmService($case['expectedEffectiveTheme']);

    // Built as a non-BSL site so the whole mega menu group is present; the
    // point here is the 0/FALSE values, not the BSL hiding.
    $form = $this->build($this->config());

    $this->assertSame(0, $form['theme']['mega_menu']['max_rows_per_column']['#default_value']);
    $this->assertFalse($form['theme']['mega_menu']['forums']['#default_value']);
  }

  /**
   * The container double throws for an unregistered id, like the real one.
   *
   * This is the pin under the guard test below. The stub used to return NULL
   * here, which made \Drupal::hasService() in dmsmConfigService() dead weight
   * as far as the suite was concerned: deleting the guard left every test
   * green while a real site fatalled on the Theme tab. If this assertion ever
   * goes back to expecting NULL, the guard is unpinned again.
   */
  public function testContainerDoubleThrowsForAnUnregisteredService(): void {
    $this->expectException(ServiceNotFoundException::class);
    \Drupal::service(BiolandThemeForm::DMSM_SERVICE_ID);
  }

  /**
   * dmsmConfigService() returns NULL for a missing service, never throws.
   *
   * The guard test. \Drupal::service() throws ServiceNotFoundException for an
   * unregistered id, so the documented NULL return is only reachable through
   * the \Drupal::hasService() check -- delete that check and this call
   * propagates the exception instead of returning, and this test goes red.
   */
  public function testDmsmConfigServiceReturnsNullWhenTheContainerHasNone(): void {
    $form = $this->createForm();

    $this->assertFalse(\Drupal::hasService(BiolandThemeForm::DMSM_SERVICE_ID));
    $this->assertNull(
      $this->invoke($form, 'dmsmConfigService'),
      'dmsmConfigService() must degrade to NULL, not throw, when the service is unregistered.'
    );
  }

  /**
   * With no dmsm service in the container the form still builds.
   *
   * It degrades to the fallback colours rather than to empty fields, and
   * says so. \Drupal::service() throws for an unregistered id, so the form
   * must ask \Drupal::hasService() first or this build would fatal.
   */
  public function testMissingDmsmServiceDegradesToFallbackColors(): void {
    [$form, $messenger] = $this->buildWithMessenger($this->config());

    $this->assertSame('color', $form['theme']['color']['primary']['#type']);
    $this->assertSame('#009edb', $form['theme']['color']['primary']['#default_value']);
    $this->assertCount(1, $messenger->warnings);
  }

  /**
   * An unreadable seed warns the editor and never leaves a colour empty.
   *
   * getEffectiveTheme() returns NULL on any HTTP or parse error. With no
   * default, the browser's native `<input type="color">` -- which has no
   * empty state -- would post #000000 on the editor's first Save, the
   * #required check would wave it through, and D5's seed-on-save would make
   * the black permanent, so a dmsm hiccup would silently black out a site.
   * Two guards: the warning, and the network-default colours.
   */
  public function testSeedFailureWarnsAndFallsBackToNetworkColors(): void {
    $this->stubDmsmService(NULL);

    [$form, $messenger] = $this->buildWithMessenger($this->config());

    $this->assertCount(1, $messenger->warnings, 'A failed seed must warn the editor exactly once.');
    $this->assertStringContainsString('could not be loaded', $messenger->warnings[0]);

    $this->assertSame('#009edb', $form['theme']['color']['primary']['#default_value']);
    $this->assertSame('#16c56e', $form['theme']['color']['secondary']['#default_value']);
    $this->assertSame('#f2f2f2', $form['theme']['back_ground']['secondary']['#default_value']);

    foreach ([
      $form['theme']['color']['primary']['#default_value'],
      $form['theme']['color']['secondary']['#default_value'],
      $form['theme']['back_ground']['secondary']['#default_value'],
    ] as $value) {
      $this->assertNotSame('', $value, 'An empty colour is what core turns into #000000.');
      $this->assertNotSame('#000000', $value);
    }
  }

  /**
   * A seed that succeeds raises no warning and is not overwritten.
   */
  public function testSuccessfulSeedRaisesNoWarning(): void {
    $case = $this->fixtureCases()['theme-less-site'];
    $this->stubDmsmService($case['expectedEffectiveTheme']);

    [$form, $messenger] = $this->buildWithMessenger($this->config());

    $this->assertSame([], $messenger->warnings);
    $this->assertSame('#009edb', $form['theme']['color']['primary']['#default_value']);
  }

  /**
   * An already-authored theme neither warns nor consults the seed.
   */
  public function testAuthoredThemeRaisesNoSeedWarning(): void {
    // exactly(0): a site with its own theme must not pay for the HTTP call.
    $this->stubDmsmService(NULL, 0);

    [, $messenger] = $this->buildWithMessenger($this->config([
      'theme' => ['color' => ['primary' => '#abcdef']],
    ]));

    $this->assertSame([], $messenger->warnings);
  }

  /**
   * M5: the 10 second HTTP seed is fetched at most once per form instance.
   */
  public function testSeedIsMemoizedPerFormInstance(): void {
    // exactly(1) across three seedFromDmsm() calls on one instance.
    $this->stubDmsmService(NULL, 1);

    $form = $this->createForm();

    for ($i = 0; $i < 3; $i++) {
      $this->assertSame('#009edb', $this->invoke($form, 'seedFromDmsm')['color']['primary']);
    }
  }

  // ---------------------------------------------------------------------
  // D7: BSL hides columns in build AND submit, and skips its validator.
  // ---------------------------------------------------------------------

  /**
   * On a BSL site the columns field is absent from the built form.
   */
  public function testBslHidesColumnsInBuild(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config(['is_biosafety_land' => TRUE]));

    $this->assertArrayNotHasKey('home_page_widgets', $form['theme']);
    // Everything else still renders.
    $this->assertArrayHasKey('color', $form['theme']);
    $this->assertArrayHasKey('mega_menu', $form['theme']);
  }

  /**
   * On a BSL site the submit leg writes no columns key, even when a stale
   * value is submitted.
   */
  public function testBslDoesNotWriteColumnsOnSubmit(): void {
    $this->stubDmsmService(NULL);
    $config = $this->config(['is_biosafety_land' => TRUE]);
    $form = $this->build($config);

    $values = $this->submittableValues();
    $values['theme']['home_page_widgets']['columns'] = [['gbif'], ['tsc'], ['geobon']];

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertNull(
      $config->get('theme.home_page_widgets'),
      'A hidden field must never be written.'
    );
    $this->assertSame('#123456', $config->get('theme.color.primary'), 'The rest of the tab still saves.');
  }

  /**
   * The columns validator never fires on a BSL site.
   *
   * The submitted value below is a single column, which would fail W2a on a
   * CHM site; on BSL the field does not exist, so no error may be raised.
   */
  public function testColumnsValidatorNeverFiresOnBsl(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config(['is_biosafety_land' => TRUE]));

    $values = $this->submittableValues();
    $values['theme']['home_page_widgets']['columns'] = [['gbif']];
    $formState = $this->formState($values);

    $this->createForm()->validateForm($form, $formState);

    $this->assertSame([], $formState->getErrors(), 'No validation error may be raised on BSL.');
  }

  // ---------------------------------------------------------------------
  // W2a: exactly three OUTER columns.
  // ---------------------------------------------------------------------

  /**
   * Three outer columns validate, whatever the total widget count.
   *
   * @dataProvider validColumnShapesProvider
   */
  public function testColumnsValidationAcceptsExactlyThreeOuterColumns(array $columns): void {
    $formState = $this->validateColumns($columns);

    $this->assertSame([], $formState->getErrors(), 'Three outer columns must validate.');
  }

  /**
   * Column shapes with exactly three OUTER entries.
   *
   * @return array
   *   Test cases.
   */
  public function validColumnShapesProvider(): array {
    return [
      'three columns, eight widgets' => [[
        ['panorama', 'gbif', 'eLearning'],
        ['implementation', 'tsc'],
        ['forums', 'geobon', 'contentStats'],
      ]],
      'three columns, three widgets' => [[['gbif'], ['tsc'], ['geobon']]],
      'three columns, two of them empty' => [[['gbif', 'tsc', 'geobon'], [], []]],
    ];
  }

  /**
   * Any outer length other than three fails, whatever the widget total.
   *
   * @dataProvider invalidColumnShapesProvider
   */
  public function testColumnsValidationRejectsAnyOtherOuterCount(array $columns, int $outer): void {
    $formState = $this->validateColumns($columns);

    $this->assertArrayHasKey(
      'theme][home_page_widgets][columns',
      $formState->getErrors(),
      sprintf('%d outer columns must fail W2a.', $outer)
    );
    $this->assertStringContainsString('exactly 3 columns', $formState->getErrors()['theme][home_page_widgets][columns']);
  }

  /**
   * Column shapes whose OUTER length is not three.
   *
   * The "one column, three widgets" and "nine widgets in two columns" rows are
   * the load-bearing ones: a validator that counted the total widget count
   * instead of the outer length would pass the first and fail differently on
   * the second.
   *
   * @return array
   *   Test cases.
   */
  public function invalidColumnShapesProvider(): array {
    return [
      'no columns' => [[], 0],
      'one column holding three widgets' => [[['gbif', 'tsc', 'geobon']], 1],
      'two columns holding nine widget slots' => [
        [['panorama', 'gbif', 'eLearning', 'implementation'], ['tsc', 'forums', 'geobon', 'contentStats']],
        2,
      ],
      'four columns' => [[['gbif'], ['tsc'], ['geobon'], ['forums']], 4],
    ];
  }

  /**
   * Runs validateForm() on a CHM site with the given submitted columns.
   *
   * @param array $columns
   *   The submitted columns.
   *
   * @return object
   *   The form-state double, carrying any errors raised.
   */
  protected function validateColumns(array $columns) {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $values = $this->submittableValues();
    $values['theme']['home_page_widgets']['columns'] = $columns;
    $formState = $this->formState($values);

    $this->createForm()->validateForm($form, $formState);

    return $formState;
  }

  /**
   * A complete, valid submitted value tree.
   *
   * @return array
   *   The values.
   */
  protected function submittableValues(): array {
    return [
      'theme' => [
        'color' => ['primary' => '#123456', 'secondary' => '#abcdef'],
        'back_ground' => ['secondary' => '#f2f2f2'],
        'home_page_widgets' => [
          'columns' => [['panorama', 'gbif'], ['implementation', 'tsc'], ['forums', 'geobon']],
        ],
        'mega_menu' => [
          'forums' => 1,
          'max_columns' => 5,
          'max_rows_per_column' => 0,
          'horizontal_card_max' => 3,
        ],
        'i18n' => ['max_lang_before_wrap' => 6],
      ],
    ];
  }

  // ---------------------------------------------------------------------
  // Registry vocabulary: placement-fixed widgets are never selectable.
  // ---------------------------------------------------------------------

  /**
   * The column options are exactly the registry's authorable theme-names.
   */
  public function testColumnOptionsAreExactlyTheAuthorableThemeNames(): void {
    $options = $this->invoke($this->createForm(), 'columnWidgetOptions');

    $this->assertSame(
      BiolandHomeWidgetRegistry::authorableThemeNames(),
      array_keys($options)
    );
    $this->assertNotEmpty($options);
  }

  /**
   * No placement-fixed or legacy non-authorable widget is offered.
   *
   * This is the duplicate-render trap: `news` (latest_news_widget) and
   * `national_targets_widget` are already rendered unconditionally outside the
   * column mechanism, so placing either in a column would render it twice.
   */
  public function testPlacementFixedAndLegacyWidgetsAreNotOffered(): void {
    $offered = array_keys($this->invoke($this->createForm(), 'columnWidgetOptions'));

    $forbidden = [];
    foreach (BiolandHomeWidgetRegistry::WIDGETS as $key => $definition) {
      if ($definition['classification'] === BiolandHomeWidgetRegistry::CLASSIFICATION_AUTHORABLE) {
        continue;
      }
      if ($definition['theme_name'] !== NULL) {
        $forbidden[$key] = $definition['theme_name'];
      }
    }

    // Guard the guard: if the registry ever stops classifying anything as
    // non-authorable-with-a-theme-name, this test would silently pass empty.
    $this->assertNotEmpty($forbidden, 'Expected at least one non-authorable widget with a theme-name.');
    $this->assertContains('news', $forbidden, 'latest_news_widget/news is the placement-fixed case.');

    foreach ($forbidden as $key => $themeName) {
      $this->assertNotContains(
        $themeName,
        $offered,
        sprintf('%s ("%s") is not authorable and must never be a column option.', $key, $themeName)
      );
    }
  }

  /**
   * A forged submission cannot smuggle a non-authorable widget into config.
   */
  public function testNormalizeColumnsDropsNonAuthorableNames(): void {
    $normalized = $this->invoke($this->createForm(), 'normalizeColumns', [[
      ['gbif', 'news', 'nationalTargets'],
      ['tsc', 'not-a-widget'],
      ['geobon'],
    ]]);

    $this->assertSame([['gbif'], ['tsc'], ['geobon']], $normalized);
  }

  /**
   * Columns survive a remove/reorder round-trip as re-indexed lists (M4).
   *
   * A gapped PHP array serializes to a JSON object rather than an array, which
   * the head cannot iterate; array_values() at both levels is what prevents
   * that. The precedent is BiolandHomeWidgetsForm::normalizeContentTypes().
   */
  public function testColumnsSurviveRemoveAndReorderRoundTrip(): void {
    // A gapped outer array with gapped inner arrays, reordered.
    $gapped = [
      0 => [2 => 'geobon', 0 => 'gbif'],
      2 => [5 => 'tsc'],
      7 => [],
    ];

    $normalized = $this->invoke($this->createForm(), 'normalizeColumns', [$gapped]);

    $this->assertSame([['geobon', 'gbif'], ['tsc'], []], $normalized);
    $this->assertTrue(array_is_list($normalized), 'The outer array must be a list.');
    foreach ($normalized as $column) {
      $this->assertTrue(array_is_list($column), 'Every inner array must be a list.');
    }
    $this->assertSame(
      '[["geobon","gbif"],["tsc"],[]]',
      json_encode($normalized),
      'A gapped array would encode as a JSON object and break the head.'
    );
  }

  // ---------------------------------------------------------------------
  // Writer conformance and the snake_case depth.
  // ---------------------------------------------------------------------

  /**
   * The writer's key set is exactly BiolandThemeContract::KEYS.
   *
   * BiolandThemeContractTest pins KEYS against the schema's `theme` mapping,
   * so writer == contract == schema transitively; any drift fails the build.
   */
  public function testSubmitWritesExactlyTheContractKeySet(): void {
    $this->stubDmsmService(NULL);
    $config = $this->config();
    $form = $this->build($config);

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($this->submittableValues()), $config]);

    $written = array_keys($this->flatten($config->get('theme')));
    sort($written);
    $expected = BiolandThemeContract::KEYS;
    sort($expected);

    $this->assertSame($expected, $written);
  }

  /**
   * The saved config carries the literal `back_ground` key.
   *
   * This is the Drupal snake_case depth. `background` would survive PHP
   * happily and then silently miss head's camelCase `backGround` consumer.
   * The head-facing camelCase depth is a separate contract, pinned by
   * testEffectiveThemeMatchesFixtureAtHeadDepth().
   */
  public function testSubmitWritesLiteralBackGroundKey(): void {
    $this->stubDmsmService(NULL);
    $config = $this->config();
    $form = $this->build($config);

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($this->submittableValues()), $config]);

    $theme = $config->get('theme');
    $this->assertArrayHasKey('back_ground', $theme);
    $this->assertArrayNotHasKey('background', $theme);
    $this->assertArrayNotHasKey('backGround', $theme);
    $this->assertSame('#f2f2f2', $config->get('theme.back_ground.secondary'));
  }

  /**
   * The submit leg preserves 0 and FALSE rather than defaulting them away.
   */
  public function testSubmitPreservesZeroAndFalse(): void {
    $this->stubDmsmService(NULL);
    $config = $this->config();
    $form = $this->build($config);

    $values = $this->submittableValues();
    $values['theme']['mega_menu']['forums'] = 0;
    $values['theme']['mega_menu']['max_rows_per_column'] = 0;

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertFalse($config->get('theme.mega_menu.forums'));
    $this->assertSame(0, $config->get('theme.mega_menu.max_rows_per_column'));
  }

  // ---------------------------------------------------------------------
  // D3 bounds: a blank optional numeric is not a zero.
  // ---------------------------------------------------------------------

  /**
   * A blank optional numeric is skipped, never cast to 0.
   *
   * `(int) ''` is 0, and core's Number::validateNumber() EARLY-RETURNS on ''
   * so #min/#max never fire for an empty field. Casting would land
   * `max_columns: 0` and `horizontal_card_max: 0` in config, both outside
   * D3's 1-6, in a config nothing downstream re-validates.
   *
   * @dataProvider blankNumericProvider
   */
  public function testBlankOptionalNumericIsNotWrittenAsZero(string $path, string $blank): void {
    $this->stubDmsmService(NULL);
    $config = $this->config();
    $form = $this->build($config);

    $values = $this->submittableValues();
    [$group, $key] = explode('.', $path);
    $values['theme'][$group][$key] = $blank;

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertNotSame(
      0,
      $config->get('theme.' . $path),
      sprintf('A blank %s must not be coerced to 0.', $path)
    );
    $this->assertNull(
      $config->get('theme.' . $path),
      sprintf('A blank %s must leave the key unauthored.', $path)
    );
  }

  /**
   * The optional numerics and the blank values they can arrive as.
   *
   * @return array
   *   Test cases.
   */
  public function blankNumericProvider(): array {
    return [
      // The two D3 1-6 bounded keys: 0 is outside their range entirely.
      'max_columns empty string' => ['mega_menu.max_columns', ''],
      'horizontal_card_max empty string' => ['mega_menu.horizontal_card_max', ''],
      // Not out of range, but 0 here MEANS "unlimited" -- a real setting the
      // editor never chose.
      'max_rows_per_column empty string' => ['mega_menu.max_rows_per_column', ''],
    ];
  }

  /**
   * A blank numeric leaves a previously authored value alone.
   *
   * Skip, not clear: an unrelated save must not be able to silently discard
   * a bound the editor set earlier. RS's reset is the deliberate way to
   * un-author.
   */
  public function testBlankNumericPreservesAnAuthoredValue(): void {
    $this->stubDmsmService(NULL, 0);
    $config = $this->config([
      'theme' => ['mega_menu' => ['max_columns' => 4]],
    ]);
    $form = $this->build($config);

    $values = $this->submittableValues();
    $values['theme']['mega_menu']['max_columns'] = '';

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertSame(4, $config->get('theme.mega_menu.max_columns'));
  }

  /**
   * A submitted 0 is still a real authored value, not a blank.
   *
   * The blank guard must test for NULL/'' explicitly, never empty().
   */
  public function testZeroIsStillAuthoredAfterTheBlankGuard(): void {
    $this->stubDmsmService(NULL);
    $config = $this->config();
    $form = $this->build($config);

    $values = $this->submittableValues();
    $values['theme']['mega_menu']['max_rows_per_column'] = '0';

    $this->invoke($this->createForm(), 'submitSectionForm', [&$form, $this->formState($values), $config]);

    $this->assertSame(
      0,
      $config->get('theme.mega_menu.max_rows_per_column'),
      '0 means unlimited and must survive the blank guard.'
    );
  }

  /**
   * Non-empty numerics outside D3's bounds are rejected server-side.
   *
   * @dataProvider boundedNumericProvider
   */
  public function testNonEmptyNumericsAreBoundsCheckedServerSide(string $path, $value, bool $valid): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $values = $this->submittableValues();
    [$group, $key] = explode('.', $path);
    $values['theme'][$group][$key] = $value;
    $formState = $this->formState($values);

    $this->createForm()->validateForm($form, $formState);

    $name = 'theme][' . str_replace('.', '][', $path);
    $this->assertSame(
      $valid,
      !array_key_exists($name, $formState->getErrors()),
      sprintf('%s = %s should be %s.', $path, var_export($value, TRUE), $valid ? 'accepted' : 'rejected')
    );
  }

  /**
   * Bounded numeric values and whether D3 allows them.
   *
   * @return array
   *   Test cases.
   */
  public function boundedNumericProvider(): array {
    return [
      'max_columns at min' => ['mega_menu.max_columns', 1, TRUE],
      'max_columns at max' => ['mega_menu.max_columns', 6, TRUE],
      'max_columns zero' => ['mega_menu.max_columns', 0, FALSE],
      'max_columns forged high' => ['mega_menu.max_columns', 999, FALSE],
      'max_columns non numeric' => ['mega_menu.max_columns', 'six', FALSE],
      // Blank is optional, not invalid: the writer skips it instead.
      'max_columns blank' => ['mega_menu.max_columns', '', TRUE],
      'horizontal_card_max at min' => ['mega_menu.horizontal_card_max', 1, TRUE],
      'horizontal_card_max at max' => ['mega_menu.horizontal_card_max', 6, TRUE],
      'horizontal_card_max zero' => ['mega_menu.horizontal_card_max', 0, FALSE],
      'horizontal_card_max forged high' => ['mega_menu.horizontal_card_max', 7, FALSE],
      'horizontal_card_max blank' => ['mega_menu.horizontal_card_max', '', TRUE],
    ];
  }

  // ---------------------------------------------------------------------
  // RS: reset to network default.
  // ---------------------------------------------------------------------

  /**
   * Reset deletes the `theme` key only, leaving the rest of bioland.settings.
   */
  public function testResetDeletesOnlyTheThemeKey(): void {
    $config = $this->config([
      'theme' => ['color' => ['primary' => '#abcdef']],
      'countries' => ['be'],
      'is_biosafety_land' => FALSE,
    ]);

    $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $configFactory->method('get')->willReturn($config);

    $form = $this->createForm();
    $property = (new \ReflectionClass($form))->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($form, $configFactory);

    $formArray = [];
    $form->submitResetToNetworkDefault($formArray, $this->formState([]));

    $this->assertNull($config->get('theme'), 'Reset must remove the whole theme subtree.');
    $this->assertFalse($config->deleted, 'Reset must not delete the bioland.settings object itself.');
    $this->assertTrue($config->saved, 'Reset must persist the removal.');
    $this->assertSame(['be'], $config->get('countries'), 'Unrelated settings must survive.');
  }

  /**
   * The reset control is a real submit button with its own handler, and it
   * bypasses validation so a half-filled form can still be reset.
   */
  public function testResetControlIsWiredAndSkipsValidation(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $reset = $form['actions']['theme_reset'];
    $this->assertSame('submit', $reset['#type']);
    $this->assertSame(['::submitResetToNetworkDefault'], $reset['#submit']);
    $this->assertSame([], $reset['#limit_validation_errors']);
    $this->assertStringStartsWith('return confirm(', $reset['#attributes']['onclick']);
  }

  // ---------------------------------------------------------------------
  // Hex validation.
  // ---------------------------------------------------------------------

  /**
   * Malformed colours are rejected; well-formed ones pass.
   *
   * @dataProvider hexProvider
   */
  public function testHexColorValidation(string $value, bool $valid): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $values = $this->submittableValues();
    $values['theme']['color']['primary'] = $value;
    $formState = $this->formState($values);

    $this->createForm()->validateForm($form, $formState);

    $this->assertSame(
      $valid,
      !array_key_exists('theme][color][primary', $formState->getErrors()),
      sprintf('"%s" should be %s.', $value, $valid ? 'accepted' : 'rejected')
    );
  }

  /**
   * Colour values and whether they are valid.
   *
   * @return array
   *   Test cases.
   */
  public function hexProvider(): array {
    return [
      'lowercase' => ['#1b7b3a', TRUE],
      'uppercase' => ['#1B7B3A', TRUE],
      'shorthand is rejected' => ['#1b7', FALSE],
      'missing hash' => ['1b7b3a', FALSE],
      'named colour' => ['red', FALSE],
      'rgba' => ['rgba(255, 255, 255, 0.75)', FALSE],
      'script injection attempt' => ['#fff;</style><script>', FALSE],
    ];
  }

  // ---------------------------------------------------------------------
  // MD: the effective-theme rule, pinned at BOTH depths, separately.
  // ---------------------------------------------------------------------

  /**
   * The head/dmsm camelCase depth: getEffectiveTheme() reproduces the fixture.
   *
   * This is the contract head consumes. p03-01 asserts the same
   * `expectedEffectiveTheme` values in vitest.
   */
  public function testEffectiveThemeMatchesFixtureAtHeadDepth(): void {
    foreach ($this->fixtureCases() as $id => $case) {
      $this->assertSame(
        $case['expectedEffectiveTheme'],
        $this->effectiveThemeFor($case['input']),
        sprintf('Fixture case "%s" (head camelCase depth) does not match.', $id)
      );
    }
  }

  /**
   * The Drupal snake_case depth: the same four cases mapped to config keys.
   *
   * Deliberately a separate assertion from the head-depth one above: the two
   * spellings are two distinct contracts, and a single test over one of them
   * would let the other drift unnoticed.
   */
  public function testSeedDefaultsMatchFixtureAtDrupalDepth(): void {
    foreach ($this->fixtureCases() as $id => $case) {
      $this->stubDmsmService($case['expectedEffectiveTheme']);

      $this->assertSame(
        $case['expectedSeedDefaults'],
        $this->invoke($this->createForm(), 'seedFromDmsm'),
        sprintf('Fixture case "%s" (Drupal snake_case depth) does not match.', $id)
      );
    }
  }

  /**
   * The `be` case specifically: an authored hero is never recomputed.
   */
  public function testAuthoredHeroIsPreservedNotDerived(): void {
    $case = $this->fixtureCases()['be-dual-block-authored-hero'];
    $effective = $this->effectiveThemeFor($case['input']);

    $this->assertSame(['#565B29', '#CBB279'], $effective['hero']['primary']);
    $this->assertNotSame(
      [$effective['color']['primary'], $effective['color']['secondary']],
      $effective['hero']['primary'],
      'The derive formula would have produced #889262; the authored stop must win.'
    );
  }

  /**
   * The per-leaf merge fills a leaf the site block omits.
   */
  public function testPerLeafMergeFillsOmittedLeaves(): void {
    $case = $this->fixtureCases()['be-dual-block-authored-hero'];
    $this->assertArrayNotHasKey('i18n', $case['input']['theme'], 'Fixture must exercise the fallback.');

    $effective = $this->effectiveThemeFor($case['input']);

    $this->assertSame(6, $effective['i18n']['maxLangBeforeWrap'], 'Omitted leaf falls through to the network leg.');
    $this->assertSame('#565B29', $effective['color']['primary'], 'Present leaf still comes from the site leg.');
  }

  /**
   * An empty site branch does not wipe the network branch.
   *
   * json_decode() renders an empty JSON object (`"megaMenu": {}`) and an
   * empty JSON array as the same PHP `[]`, and array_is_list([]) is TRUE --
   * so a naive list test classifies the empty branch as a leaf and replaces
   * the whole network branch wholesale. That is a wholesale replace where MD
   * ratifies a per-leaf merge: every leaf the network defined disappears.
   */
  public function testEmptySiteBranchDoesNotWipeTheNetworkBranch(): void {
    $effective = $this->effectiveThemeFor([
      'theme' => [
        'color' => ['primary' => '#565B29'],
        // The empty-object case.
        'megaMenu' => [],
      ],
      'runTime' => [
        'theme' => [
          'color' => ['primary' => '#009edb', 'secondary' => '#16c56e'],
          'megaMenu' => [
            'forums' => TRUE,
            'maxColumns' => 4,
            'maxRowsPerColumn' => 0,
            'horizontalCardMax' => 3,
          ],
        ],
      ],
    ]);

    $this->assertSame(
      [
        'forums' => TRUE,
        'maxColumns' => 4,
        'maxRowsPerColumn' => 0,
        'horizontalCardMax' => 3,
      ],
      $effective['megaMenu'],
      'An empty site branch contributes no leaves; the network branch survives intact.'
    );

    // The rest of the merge is unaffected.
    $this->assertSame('#565B29', $effective['color']['primary']);
    $this->assertSame('#16c56e', $effective['color']['secondary']);
  }

  /**
   * An empty NETWORK branch is still filled by the site branch.
   *
   * The `[]`-is-a-branch rule applies to both operands, so an empty base
   * must recurse rather than block the override.
   */
  public function testEmptyNetworkBranchIsFilledBySite(): void {
    $effective = $this->effectiveThemeFor([
      'theme' => ['megaMenu' => ['maxColumns' => 5]],
      'runTime' => [
        'theme' => [
          'color' => ['primary' => '#009edb', 'secondary' => '#16c56e'],
          'megaMenu' => [],
        ],
      ],
    ]);

    $this->assertSame(['maxColumns' => 5], $effective['megaMenu']);
  }

  /**
   * A genuine list leaf is still replaced wholesale, not element-merged.
   *
   * The `[]` carve-out must not turn non-empty lists into merge branches.
   */
  public function testNonEmptyListLeavesAreStillReplacedWholesale(): void {
    $effective = $this->effectiveThemeFor([
      'theme' => ['homePageWidgets' => ['columns' => [['gbif']]]],
      'runTime' => [
        'theme' => [
          'color' => ['primary' => '#009edb', 'secondary' => '#16c56e'],
          'homePageWidgets' => ['columns' => [['panorama'], ['tsc'], ['geobon']]],
        ],
      ],
    ]);

    $this->assertSame([['gbif']], $effective['homePageWidgets']['columns']);
  }

  /**
   * Hero derives only when absent from EVERY source.
   */
  public function testHeroDerivesOnlyWhenAbsentEverywhere(): void {
    $derived = $this->effectiveThemeFor([
      'runTime' => ['theme' => ['color' => ['primary' => '#111111', 'secondary' => '#222222']]],
    ]);
    $this->assertSame(['#111111', '#222222'], $derived['hero']['primary']);

    // Present on the network leg only: still authored, so no derive.
    $networkHero = $this->effectiveThemeFor([
      'theme' => ['color' => ['primary' => '#111111', 'secondary' => '#222222']],
      'runTime' => ['theme' => ['hero' => ['primary' => ['#aaaaaa', '#bbbbbb']]]],
    ]);
    $this->assertSame(['#aaaaaa', '#bbbbbb'], $networkHero['hero']['primary']);
  }

  /**
   * A document with no theme block at either level yields NULL, not a guess.
   */
  public function testMissingThemeBlockYieldsNull(): void {
    $this->assertNull($this->effectiveThemeFor(['siteCode' => 'demo', 'runTime' => []]));
  }

  // ---------------------------------------------------------------------
  // Help text (D6 cross-link + ST staleness).
  // ---------------------------------------------------------------------

  /**
   * The help text cross-links the Home Widgets tab and states the ~5 minute
   * propagation window.
   */
  public function testHelpTextCrossLinksHomeWidgetsAndStatesStaleness(): void {
    $this->stubDmsmService(NULL);
    $form = $this->build($this->config());

    $markup = (string) $form['theme']['help']['#markup'];

    $this->assertStringContainsString('Home Widgets tab', $markup);
    $this->assertStringContainsString('5 minutes', $markup);
  }

}
