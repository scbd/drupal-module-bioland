<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandHomeWidgetsForm;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the BSL content type selections on BiolandHomeWidgetsForm.
 *
 * @covers \Drupal\bioland\Form\BiolandHomeWidgetsForm
 */
class BiolandHomeWidgetsFormTest extends TestCase {

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
   * @return \Drupal\bioland\Form\BiolandHomeWidgetsForm
   *   The form instance.
   */
  protected function createForm(): BiolandHomeWidgetsForm {
    return new BiolandHomeWidgetsForm(
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
   * @param \Drupal\bioland\Form\BiolandHomeWidgetsForm $form
   *   The form instance.
   * @param string $name
   *   The method name.
   * @param array $args
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invoke(BiolandHomeWidgetsForm $form, string $name, array $args = []) {
    $method = (new \ReflectionClass($form))->getMethod($name);
    $method->setAccessible(TRUE);

    return $method->invokeArgs($form, $args);
  }

  /**
   * Tests the form ID is unchanged.
   */
  public function testGetFormIdReturnsCorrectId(): void {
    $this->assertSame(
      'bioland_settings_front_end_home_widgets_form',
      $this->createForm()->getFormId()
    );
  }

  /**
   * Tests a submitted multi-select selection is normalized to term IDs.
   */
  public function testNormalizeContentTypesReturnsCleanTermIdList(): void {
    $form = $this->createForm();

    // Drupal's multi-select returns values keyed by option key, and unselected
    // options arrive as 0/''.
    $submitted = [
      '44' => '44',
      '5' => '5',
      '46' => 0,
      '47' => '',
      '56' => '56',
    ];

    $this->assertSame(
      [44, 5, 56],
      $this->invoke($form, 'normalizeContentTypes', [$submitted])
    );
  }

  /**
   * Tests duplicate selections are collapsed.
   */
  public function testNormalizeContentTypesRemovesDuplicates(): void {
    $form = $this->createForm();

    $this->assertSame(
      [2, 3],
      $this->invoke($form, 'normalizeContentTypes', [[2, '2', 3]])
    );
  }

  /**
   * Tests a non-array value normalizes to an empty selection.
   */
  public function testNormalizeContentTypesRejectsNonArray(): void {
    $form = $this->createForm();

    $this->assertSame([], $this->invoke($form, 'normalizeContentTypes', [NULL]));
    $this->assertSame([], $this->invoke($form, 'normalizeContentTypes', ['44']));
  }

  /**
   * Tests unconfigured widgets fall back to their front end defaults.
   */
  public function testGetWidgetContentTypesFallsBackToDefaults(): void {
    $form = $this->createForm();
    $config = $this->createConfigStub([]);

    $this->assertSame([56, 44, 5, 45, 46, 47], $this->invoke($form, 'getWidgetContentTypes', [$config, 'nbf_widget']));
    $this->assertSame([2, 3, 49], $this->invoke($form, 'getWidgetContentTypes', [$config, 'bch_news_widget']));
    $this->assertSame([15, 48, 43, 16, 6, 12], $this->invoke($form, 'getWidgetContentTypes', [$config, 'bch_resources_widget']));
  }

  /**
   * Tests a configured selection wins over the defaults.
   */
  public function testGetWidgetContentTypesReturnsConfiguredSelection(): void {
    $form = $this->createForm();
    $config = $this->createConfigStub([
      'home_widgets.nbf_widget.content_types' => ['44', '45'],
    ]);

    $this->assertSame([44, 45], $this->invoke($form, 'getWidgetContentTypes', [$config, 'nbf_widget']));
  }

  /**
   * Tests an explicitly emptied selection is respected, not re-defaulted.
   */
  public function testGetWidgetContentTypesRespectsEmptySelection(): void {
    $form = $this->createForm();
    $config = $this->createConfigStub([
      'home_widgets.bch_news_widget.content_types' => [],
    ]);

    $this->assertSame([], $this->invoke($form, 'getWidgetContentTypes', [$config, 'bch_news_widget']));
  }

  /**
   * Tests the select element is a multi-select over the supplied options.
   */
  public function testBuildContentTypeSelectIsMultiSelect(): void {
    $form = $this->createForm();
    $options = [2 => 'News', 3 => 'Events', 49 => 'Announcements'];

    $element = $this->invoke($form, 'buildContentTypeSelect', [$options, [2, 49], 'Pick some.']);

    $this->assertSame('select', $element['#type']);
    $this->assertTrue($element['#multiple']);
    $this->assertSame($options, $element['#options']);
    $this->assertSame([2, 49], $element['#default_value']);
  }

  /**
   * Tests every BSL widget has a default content type list.
   */
  public function testEveryBslWidgetHasDefaultContentTypes(): void {
    $reflection = new \ReflectionClass(BiolandHomeWidgetsForm::class);
    $widgets = $reflection->getConstant('BSL_WIDGETS');
    $defaults = $reflection->getConstant('BSL_WIDGET_DEFAULT_CONTENT_TYPES');

    foreach ($widgets as $widget) {
      $this->assertArrayHasKey($widget, $defaults, "Missing default content types for {$widget}.");
      $this->assertNotEmpty($defaults[$widget], "Empty default content types for {$widget}.");
    }
  }

  /**
   * Creates a config double backed by a flat key => value map.
   *
   * @param array $values
   *   Config values keyed by their dotted config path.
   *
   * @return object
   *   A config-like object exposing get().
   */
  protected function createConfigStub(array $values) {
    return new class($values) {
      /**
       * The backing config values.
       *
       * @var array
       */
      protected $values;

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function get($key = '') {
        return $this->values[$key] ?? NULL;
      }

    };
  }

}
