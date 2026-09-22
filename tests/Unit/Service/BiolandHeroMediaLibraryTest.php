<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandHeroMediaLibrary;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\MediaLibraryState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the hero "Choose from media library" picker (BL-807).
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandHeroMediaLibrary
 * @group bioland
 */
class BiolandHeroMediaLibraryTest extends TestCase {

  /**
   * Builds the service over a media storage returning $media.
   *
   * File 42 is the image file; $file_uri is its URI (NULL: no such file).
   */
  private function service($media = NULL, array $permissions = ['view media'], ?string $file_uri = 'public://hero/mangroves.jpg'): BiolandHeroMediaLibrary {
    $media_storage = $this->createMock(EntityStorageInterface::class);
    $media_storage->method('load')->willReturn($media);
    $file = $file_uri === NULL ? NULL : new class($file_uri) {

      public function __construct(private string $uri) {
      }

      public function getFileUri() {
        return $this->uri;
      }

    };
    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->method('load')->willReturnCallback(fn ($id) => (int) $id === 42 ? $file : NULL);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([['media', $media_storage], ['file', $file_storage]]);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(fn ($p) => in_array($p, $permissions, TRUE));

    return new BiolandHeroMediaLibrary($manager, $account);
  }

  /**
   * The widget hook context for a field on a media entity.
   */
  private function context(string $bundle, string $field = 'field_media_image', ?int $id = 7): array {
    $entity = new class($bundle, $id) {

      public function __construct(private string $bundle, private ?int $id) {
      }

      public function getEntityTypeId() {
        return 'media';
      }

      public function bundle() {
        return $this->bundle;
      }

      public function isNew() {
        return $this->id === NULL;
      }

      public function id() {
        return $this->id;
      }

    };
    $items = new class($entity, $field) {

      public function __construct(private object $entity, private string $field) {
      }

      public function getEntity() {
        return $this->entity;
      }

      public function getFieldDefinition() {
        $field = $this->field;
        return new class($field) {

          public function __construct(private string $field) {
          }

          public function getName() {
            return $this->field;
          }

        };
      }

    };

    return ['items' => $items, 'default' => FALSE];
  }

  /**
   * An image media stand-in whose source field holds $value.
   */
  private function imageMedia(array $value, string $bundle = 'image', bool $viewable = TRUE, bool $published = TRUE): object {
    return new class($value, $bundle, $viewable, $published) {

      public function __construct(private array $value, private string $bundle, private bool $viewable, private bool $published) {
      }

      public function bundle() {
        return $this->bundle;
      }

      public function isPublished() {
        return $this->published;
      }

      public function access($operation, $account = NULL) {
        return $operation === 'view' && $this->viewable;
      }

      public function getSource() {
        return new class {

          public function getConfiguration() {
            return ['source_field' => 'field_media_image'];
          }

        };
      }

      public function get($field) {
        $values = $field === 'field_media_image' ? [$this->value] : [];
        return new class($values) {

          public function __construct(private array $values) {
          }

          public function getValue() {
            return $this->values;
          }

        };
      }

    };
  }

  /**
   * @covers ::alterWidget
   */
  public function testHeroEditFormGetsPickerRestrictedToOneImage(): void {
    $element = ['widget' => ['#field_parents' => []]];
    $this->service()->alterWidget($element, $this->context('hero'));

    $picker = $element['bioland_media_library'];
    $query = $picker['open']['#url']->getOptions()['query'];
    $this->assertSame('media_library.ui', $picker['open']['#url']->getRouteName());
    $this->assertSame('bioland.opener.hero_image', $query['media_library_opener_id']);
    $this->assertSame(['image'], $query['media_library_allowed_types']);
    $this->assertSame(1, $query['media_library_remaining']);
    $this->assertSame(['widget_id' => 'bioland-hero-image', 'entity_id' => '7'], $query['media_library_opener_parameters']);
    $this->assertContains('use-ajax', $picker['open']['#attributes']['class']);
    $this->assertSame('modal', $picker['open']['#attributes']['data-dialog-type']);

    $this->assertSame('bioland-hero-image', $picker['selection']['#attributes']['data-bioland-hero-media-value']);
    $this->assertSame('bioland-hero-image', $picker['update']['#attributes']['data-bioland-hero-media-update']);
    $this->assertSame([['bioland_hero_media_library']], $picker['update']['#limit_validation_errors']);
    $this->assertSame('bioland-hero-media-image-wrapper', $picker['update']['#ajax']['wrapper']);
    $this->assertStringContainsString('id="bioland-hero-media-image-wrapper"', $element['#prefix']);
    $this->assertSame('</div>', $element['#suffix']);
  }

  /**
   * @covers ::alterWidget
   */
  public function testHeroAddFormPassesNoEntityId(): void {
    $element = [];
    $this->service()->alterWidget($element, $this->context('hero', 'field_media_image', NULL));

    $query = $element['bioland_media_library']['open']['#url']->getOptions()['query'];
    $this->assertSame(['widget_id' => 'bioland-hero-image'], $query['media_library_opener_parameters']);
  }

  /**
   * The link's query survives the URL round trip with a valid hash.
   *
   * What MediaLibraryState::fromRequest() rebuilds from the query string must
   * hash like what alterWidget() created, or the library rejects the request.
   *
   * @covers ::alterWidget
   * @dataProvider entityIdProvider
   */
  public function testOpenerStateSurvivesTheQueryStringRoundTrip(?int $entity_id): void {
    $element = ['widget' => ['#field_parents' => []]];
    $this->service()->alterWidget($element, $this->context('hero', 'field_media_image', $entity_id));
    $query = $element['bioland_media_library']['open']['#url']->getOptions()['query'];

    parse_str(http_build_query($query), $parsed);
    $state = MediaLibraryState::fromRequest(new Request($parsed));

    $this->assertTrue($state->isValidHash($parsed['hash']));
    $this->assertSame($query['media_library_opener_parameters'], $state->getOpenerParameters());
  }

  /**
   * Saved and unsaved heroes.
   */
  public function entityIdProvider(): array {
    return ['saved hero' => [7], 'new hero' => [NULL]];
  }

  /**
   * Opener parameters core cannot rebuild from the query string are caught.
   *
   * Guards the round-trip test itself: an int or NULL entity_id (what the
   * picker once sent) must fail the hash check.
   *
   * @coversNothing
   * @dataProvider entityIdProvider
   */
  public function testRoundTripRejectsParametersTheQueryStringChanges(?int $entity_id): void {
    $state = MediaLibraryState::create('bioland.opener.hero_image', ['image'], 'image', 1, ['widget_id' => 'bioland-hero-image', 'entity_id' => $entity_id]);
    parse_str(http_build_query($state->all()), $parsed);

    $this->expectException(BadRequestException::class);
    MediaLibraryState::fromRequest(new Request($parsed));
  }

  /**
   * Ids, keys and names carry a #field_parents suffix, as core's widget does.
   *
   * @covers ::alterWidget
   */
  public function testNestedHeroFormsGetDistinctIds(): void {
    $element = ['widget' => ['#field_parents' => ['field_heroes', 0, 'subform']]];
    $this->service()->alterWidget($element, $this->context('hero'));
    $picker = $element['bioland_media_library'];

    $this->assertSame('bioland-hero-image-field_heroes-0-subform', $picker['open']['#url']->getOptions()['query']['media_library_opener_parameters']['widget_id']);
    $this->assertSame('bioland-hero-image-field_heroes-0-subform', $picker['selection']['#attributes']['data-bioland-hero-media-value']);
    $this->assertSame('bioland-hero-image-field_heroes-0-subform', $picker['update']['#attributes']['data-bioland-hero-media-update']);
    $this->assertSame(['bioland_hero_media_library_field_heroes_0_subform', 'selection'], $picker['selection']['#parents']);
    $this->assertSame([['bioland_hero_media_library_field_heroes_0_subform']], $picker['update']['#limit_validation_errors']);
    $this->assertSame('bioland_hero_media_library_field_heroes_0_subform', $picker['update']['#bioland_input_key']);
    $this->assertSame('bioland-hero-media-library-update-field_heroes-0-subform', $picker['update']['#name']);
    $this->assertSame('bioland-hero-media-image-wrapper-field_heroes-0-subform', $picker['update']['#ajax']['wrapper']);
    $this->assertSame(['field_heroes', 0, 'subform'], $picker['update']['#bioland_field_parents']);
  }

  /**
   * @covers ::alterWidget
   */
  public function testOtherFormsAndUsersWithoutViewMediaAreUntouched(): void {
    $cases = [
      [$this->service(), $this->context('image')],
      [$this->service(), $this->context('hero', 'field_other_image')],
      [$this->service(NULL, []), $this->context('hero')],
      [$this->service(), ['default' => TRUE] + $this->context('hero')],
    ];
    foreach ($cases as [$service, $context]) {
      $element = ['widget' => []];
      $service->alterWidget($element, $context);
      $this->assertSame(['widget' => []], $element);
    }
  }

  /**
   * @covers ::resolveSelection
   */
  public function testSelectionCopiesFileAndAltWhenHeroAltIsEmpty(): void {
    $media = $this->imageMedia(['target_id' => 42, 'alt' => 'Mangroves', 'title' => '', 'width' => 800, 'height' => 600]);

    $this->assertSame(['item' => [
      'target_id' => 42,
      'fids' => [42],
      'alt' => 'Mangroves',
      'title' => '',
      'width' => 800,
      'height' => 600,
    ]], $this->service($media)->resolveSelection('9', '', ''));
  }

  /**
   * @covers ::resolveSelection
   */
  public function testSelectionKeepsExistingHeroAlt(): void {
    $media = $this->imageMedia(['target_id' => 42, 'alt' => 'Mangroves']);

    $this->assertSame('Coral reef', $this->service($media)->resolveSelection('9,3', 'Coral reef', '')['item']['alt']);
  }

  /**
   * @covers ::resolveSelection
   */
  public function testSelectionKeepsExistingHeroTitleAndFillsAnEmptyOne(): void {
    $media = $this->imageMedia(['target_id' => 42, 'alt' => 'Mangroves', 'title' => 'Mangrove forest']);

    $this->assertSame('Our coast', $this->service($media)->resolveSelection('9', '', 'Our coast')['item']['title']);
    $this->assertSame('Mangrove forest', $this->service($media)->resolveSelection('9', '', '')['item']['title']);
  }

  /**
   * @covers ::resolveSelection
   */
  public function testEmptySelectionIsANoOp(): void {
    $this->assertSame([], $this->service()->resolveSelection('', '', ''));
  }

  /**
   * @covers ::resolveSelection
   */
  public function testRejectedSelectionsReturnAnError(): void {
    $image = ['target_id' => 42];
    $cases = [
      [NULL, '9'],
      [$this->imageMedia($image), 'abc'],
      [$this->imageMedia($image, 'document'), '9'],
      [$this->imageMedia($image, 'image', FALSE), '9'],
      [$this->imageMedia([]), '9'],
      [$this->imageMedia(['target_id' => 99]), '9'],
    ];
    foreach ($cases as [$media, $selection]) {
      $this->assertArrayHasKey('error', $this->service($media)->resolveSelection($selection, '', ''), "Selection '$selection' must be rejected.");
    }
  }

  /**
   * A restricted or private image cannot become public through the hero.
   *
   * @covers ::resolveSelection
   */
  public function testOnlyViewablePublishedPublicImagesCanBeChosen(): void {
    $image = ['target_id' => 42];
    $rejected = [
      'not viewable by the user' => $this->service($this->imageMedia($image, 'image', FALSE)),
      'unpublished' => $this->service($this->imageMedia($image, 'image', TRUE, FALSE)),
      'private file' => $this->service($this->imageMedia($image), ['view media'], 'private://hero/secret.jpg'),
      'other scheme' => $this->service($this->imageMedia($image), ['view media'], 's3://bucket/hero.jpg'),
    ];
    foreach ($rejected as $case => $service) {
      $this->assertArrayHasKey('error', $service->resolveSelection('9', '', ''), "Media that is $case must be rejected.");
    }

    $this->assertSame(42, $this->service($this->imageMedia($image))->resolveSelection('9', '', '')['item']['target_id']);
  }

  /**
   * @covers ::submitSelection
   */
  public function testSubmitLoadsTheItemIntoWidgetStateAndDropsStaleInput(): void {
    $item = ['target_id' => 42, 'fids' => [42], 'alt' => 'Mangroves', 'title' => '', 'width' => 800, 'height' => 600];
    $parents = ['field_heroes', 0, 'subform'];
    $form_state = new HeroFormState(
      ['#bioland_field_parents' => $parents, '#bioland_input_key' => 'bioland_hero_media_library_field_heroes_0_subform'],
      [
        'field_heroes' => [0 => ['subform' => ['field_media_image' => [0 => ['fids' => '5', 'alt' => 'Old']], 'name' => [0 => ['value' => 'Hero']]]]],
        'bioland_hero_media_library_field_heroes_0_subform' => ['selection' => '9'],
        'other' => 'kept',
      ]
    );
    $form_state->set('bioland_hero_media_item', $item);
    $form_state->getStorage()['field_storage']['#parents']['field_heroes'][0]['subform']['#fields']['field_media_image'] = ['items_count' => 1, 'items' => [['fids' => [5]]]];
    $form = [];

    BiolandHeroMediaLibrary::submitSelection($form, $form_state);

    $field_state = $form_state->getStorage()['field_storage']['#parents']['field_heroes'][0]['subform']['#fields']['field_media_image'];
    $this->assertSame([$item], $field_state['items']);
    $this->assertSame(1, $field_state['items_count']);
    $this->assertSame([
      'field_heroes' => [0 => ['subform' => ['name' => [0 => ['value' => 'Hero']]]]],
      'other' => 'kept',
    ], $form_state->getUserInput());
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * @covers ::submitSelection
   */
  public function testSubmitWithoutAResolvedItemOnlyRebuilds(): void {
    $input = ['field_media_image' => [0 => ['alt' => 'Old']], 'bioland_hero_media_library' => ['selection' => '']];
    $form_state = new HeroFormState(['#bioland_field_parents' => [], '#bioland_input_key' => 'bioland_hero_media_library'], $input);
    $form = [];

    BiolandHeroMediaLibrary::submitSelection($form, $form_state);

    $this->assertSame($input, $form_state->getUserInput());
    $this->assertSame([], $form_state->getStorage());
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * The services and the guarded widget hook are wired.
   *
   * @coversNothing
   */
  public function testServicesAndHookAreWired(): void {
    $root = dirname(__DIR__, 3);
    $services = file_get_contents($root . '/bioland.services.yml');
    $module = file_get_contents($root . '/bioland.module');

    // media_library's opener resolver only knows openers collected by this tag.
    $this->assertStringContainsString("bioland.opener.hero_image:\n    class: Drupal\\bioland\\MediaLibrary\\BiolandHeroImageOpener\n    arguments: ['@entity_type.manager']\n    tags:\n      - { name: media_library.opener }\n", $services);
    $this->assertStringContainsString("bioland.hero_media_library:\n    class: Drupal\\bioland\\Service\\BiolandHeroMediaLibrary", $services);
    $this->assertMatchesRegularExpression(
      "/function bioland_field_widget_complete_image_image_form_alter\\([^)]*\\) \\{\\s*if \\(\\\\Drupal::moduleHandler\\(\\)->moduleExists\\('media_library'\\)\\)/",
      $module
    );
  }

}

/**
 * Form state double for the picker's #submit: storage, input, rebuild.
 *
 * Implements only the suite's FormStateInterface stub plus the core methods
 * submitSelection() and WidgetBase's state accessors use, so the shared
 * interface (and every other double implementing it) stays unchanged.
 */
class HeroFormState implements FormStateInterface {

  /**
   * Form state storage, as core's FormState::$storage.
   *
   * @var array
   */
  protected $storage = [];

  /**
   * Whether the form will be rebuilt.
   *
   * @var bool
   */
  protected $rebuild = FALSE;

  /**
   * Constructs the double.
   *
   * @param array $triggeringElement
   *   The triggering element.
   * @param array $userInput
   *   The raw user input.
   */
  public function __construct(protected array $triggeringElement, protected array $userInput) {
  }

  /**
   * {@inheritdoc}
   */
  public function getValues() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($key, $default = NULL) {
    return $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($key, $value) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    return $this->storage[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->storage[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorByName($name, $message = '') {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirect($route_name, array $route_parameters = [], array $options = []) {
    return $this;
  }

  /**
   * Mirrors FormStateInterface::getStorage(), by reference.
   */
  public function &getStorage() {
    return $this->storage;
  }

  /**
   * Mirrors FormStateInterface::getUserInput(), by reference.
   */
  public function &getUserInput() {
    return $this->userInput;
  }

  /**
   * Mirrors FormStateInterface::setUserInput().
   */
  public function setUserInput(array $user_input) {
    $this->userInput = $user_input;
    return $this;
  }

  /**
   * Mirrors FormStateInterface::getTriggeringElement().
   */
  public function &getTriggeringElement() {
    return $this->triggeringElement;
  }

  /**
   * Mirrors FormStateInterface::setRebuild().
   */
  public function setRebuild($rebuild = TRUE) {
    $this->rebuild = $rebuild;
    return $this;
  }

  /**
   * Mirrors FormStateInterface::isRebuilding().
   */
  public function isRebuilding() {
    return $this->rebuild;
  }

}
