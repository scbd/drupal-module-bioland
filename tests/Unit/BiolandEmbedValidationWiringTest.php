<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Plugin\Validation\Constraint\BiolandEmbedAllowedOriginConstraint;
use Drupal\bioland\Service\BiolandEmbedFrameability;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\MediaInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Guards the BL-1218 embed URL constraint and media library form wiring.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEmbedValidationWiringTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../bioland.module';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Runs the bundle field alter with an embed type using $source_field.
   */
  private function alter(string $entity_type_id, string $bundle, array $fields, ?string $source_field = 'field_media_iframe'): void {
    $type = $source_field === NULL ? NULL : new class($source_field) {

      public function __construct(private string $field) {}

      public function get(string $key) {
        return $key === 'source_configuration' ? ['source_field' => $this->field] : NULL;
      }

    };
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('embed')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('media_type')->willReturn($storage);
    \Drupal::setService('entity_type.manager', $manager);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('id')->willReturn($entity_type_id);
    bioland_entity_bundle_field_info_alter($fields, $entity_type, $bundle);
  }

  /**
   * Returns a field definition double expecting $times addConstraint calls.
   */
  private function field(int $times): object {
    $field = $this->getMockBuilder(\stdClass::class)->addMethods(['addConstraint'])->getMock();
    $field->expects($this->exactly($times))->method('addConstraint')->with(BiolandEmbedAllowedOriginConstraint::PLUGIN_ID);
    return $field;
  }

  /**
   * The constraint lands on the embed type's source field only.
   */
  public function testConstraintAttachesToEmbedSourceField(): void {
    $this->alter('media', 'embed', ['field_media_iframe' => $this->field(1), 'name' => $this->field(0)]);
  }

  /**
   * Other bundles, other entity types, a missing type or field: no constraint.
   */
  public function testConstraintSkipsEverythingElse(): void {
    $this->alter('media', 'document', ['field_media_iframe' => $this->field(0)]);
    $this->alter('node', 'embed', ['field_media_iframe' => $this->field(0)]);
    $this->alter('media', 'embed', ['field_media_iframe' => $this->field(0)], NULL);
    $this->alter('media', 'embed', ['field_other' => $this->field(0)]);
  }

  /**
   * The media library iframe add form validates its URL element.
   */
  public function testMediaLibraryAddFormGetsElementValidate(): void {
    $form = ['container' => ['url' => ['#type' => 'url']]];
    bioland_form_media_library_add_form_iframe_alter($form, $this->createMock(FormStateInterface::class), 'media_library_add_form_iframe');
    $this->assertSame(['bioland_media_library_embed_url_validate'], $form['container']['url']['#element_validate']);

    // After "Add" the form shows the new media item and has no URL element.
    $form = ['media' => []];
    bioland_form_media_library_add_form_iframe_alter($form, $this->createMock(FormStateInterface::class), 'media_library_add_form_iframe');
    $this->assertSame(['media' => []], $form);
  }

  /**
   * The element validate sets the constraint's message on the url element.
   *
   * @dataProvider elementValidateProvider
   */
  public function testMediaLibraryUrlElementValidate(string $url, ?string $expected): void {
    // Only an allowlisted URL reaches the frameability check (BL-1273).
    $this->setFrameability($expected === NULL && $url !== '' ? 1 : 0);
    $config = new ImmutableConfig('bioland.settings', ['embed' => ['allowed_origins' => [['url' => 'https://app.powerbi.com/view']]]]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('bioland.settings')->willReturn($config);
    \Drupal::setService('config.factory', $factory);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('url')->willReturn($url);
    if ($expected === NULL) {
      $form_state->expects($this->never())->method('setErrorByName');
    }
    else {
      $form_state->expects($this->once())->method('setErrorByName')->with('url', $this->callback(
        fn ($message) => str_contains((string) $message, $expected) && str_contains((string) $message, 'https://app.powerbi.com/view')
      ));
    }
    $element = [];
    bioland_media_library_embed_url_validate($element, $form_state);
  }

  /**
   * Registers a frameability double that allows every URL, expecting $calls.
   */
  private function setFrameability(int $calls, ?string $error = NULL): void {
    $service = $this->createMock(BiolandEmbedFrameability::class);
    $service->expects($this->exactly($calls))->method('error')->willReturn($error === NULL ? NULL : new TranslatableMarkup($error));
    \Drupal::setService('bioland.embed_frameability', $service);
  }

  /**
   * An allowlisted URL that refuses framing fails the picker's URL element.
   */
  public function testMediaLibraryUrlElementSurfacesFrameabilityError(): void {
    $config = new ImmutableConfig('bioland.settings', ['embed' => ['allowed_origins' => [['url' => 'https://app.powerbi.com/view']]]]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('bioland.settings')->willReturn($config);
    \Drupal::setService('config.factory', $factory);
    $this->setFrameability(1, 'This page cannot be embedded: app.powerbi.com');
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('url')->willReturn('https://app.powerbi.com/view?r=1');
    $form_state->expects($this->once())->method('setErrorByName')->with('url', $this->callback(fn ($m) => str_contains((string) $m, 'cannot be embedded')));
    $element = [];
    bioland_media_library_embed_url_validate($element, $form_state);
  }

  /**
   * URLs typed into the picker and the message fragment each should raise.
   */
  public static function elementValidateProvider(): array {
    return [
      'allowed' => ['https://app.powerbi.com/view?r=1', NULL],
      'empty' => ['', NULL],
      'host not allowed' => ['https://evil.example/view', 'not on an allowed embed host'],
      'path not allowed' => ['https://app.powerbi.com/view-evil', 'not under an allowed path'],
    ];
  }

  /**
   * Registers a current user that has, or lacks, the auto-allow permission.
   */
  private function setCurrentUser(bool $auto_allow): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->willReturnCallback(fn ($permission) => $auto_allow && $permission === 'auto allow embed origins');
    \Drupal::setService('current_user', $account);
  }

  /**
   * The picker lets a trusted user add a URL on an unlisted host.
   */
  public function testMediaLibraryUrlElementValidateAutoAllow(): void {
    $config = new ImmutableConfig('bioland.settings', ['embed' => ['allowed_origins' => [['url' => 'https://app.powerbi.com/view']]]]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('bioland.settings')->willReturn($config);
    \Drupal::setService('config.factory', $factory);
    $this->setCurrentUser(TRUE);
    $this->setFrameability(1);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('url')->willReturn('https://claude.ai/artifact/x');
    $form_state->expects($this->never())->method('setErrorByName');
    $element = [];
    bioland_media_library_embed_url_validate($element, $form_state);
  }

  /**
   * A media double whose embed source field holds $urls.
   */
  private function embedMedia(array $urls): MediaInterface {
    $source = new class {

      public function getConfiguration(): array {
        return ['source_field' => 'field_media_inline_frame'];
      }

    };
    $media = $this->createMock(MediaInterface::class);
    $media->method('id')->willReturn(12);
    $media->method('bundle')->willReturn('embed');
    $media->method('getSource')->willReturn($source);
    $media->method('hasField')->willReturnCallback(fn ($name) => $name === 'field_media_inline_frame');
    $media->method('get')->with('field_media_inline_frame')->willReturn(array_map(fn ($url) => (object) ['url' => $url], $urls));
    return $media;
  }

  /**
   * Runs the auto-allow for a saved embed; returns the settings.
   */
  private function autoAllow(array $urls, bool $auto_allow, array $original_urls = NULL): Config {
    $settings = new Config('bioland.settings', ['embed' => ['allowed_origins' => [
      ['url' => 'https://app.powerbi.com/view', 'label' => 'Power BI', 'sandbox' => ''],
    ]]]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('getEditable')->with('bioland.settings')->willReturn($settings);
    \Drupal::setService('config.factory', $factory);
    $this->setCurrentUser($auto_allow);
    $this->pushRequest();
    _bioland_embed_auto_allow($this->embedMedia($urls), $original_urls === NULL ? NULL : $this->embedMedia($original_urls));
    return $settings;
  }

  /**
   * A trusted user's save appends each new URL's scoped entry once.
   */
  public function testAutoAllowAddsUnlistedUrlsForTrustedUser(): void {
    $settings = $this->autoAllow(['https://claude.ai/artifact/a', 'https://claude.ai/artifact/b', 'https://app.powerbi.com/view?r=1'], TRUE);
    $this->assertTrue($settings->saved);
    $this->assertSame([
      ['url' => 'https://app.powerbi.com/view', 'label' => 'Power BI', 'sandbox' => ''],
      ['url' => 'https://claude.ai/artifact', 'label' => 'claude.ai/artifact', 'sandbox' => ''],
    ], $settings->get('embed.allowed_origins'));
  }

  /**
   * Nothing is written without the permission, for listed URLs, or for a
   * URL the embed already had (a reviewer's removal sticks).
   */
  public function testAutoAllowLeavesListAlone(): void {
    $this->assertFalse($this->autoAllow(['https://claude.ai/artifact/a'], FALSE)->saved);
    $this->assertFalse($this->autoAllow(['https://app.powerbi.com/view?r=1'], TRUE)->saved);
    $this->assertFalse($this->autoAllow(['https://claude.ai/artifact/a'], TRUE, ['https://claude.ai/artifact/a'])->saved);
    $this->assertFalse($this->autoAllow(['https://claude.ai/artifact/b?x=2'], TRUE, ['https://claude.ai/artifact/a'])->saved);
    $this->assertTrue($this->autoAllow(['https://claude.ai/other/b'], TRUE, ['https://claude.ai/artifact/a'])->saved);
  }

  /**
   * The insert and update hooks run the auto-allow for embeds, after save.
   */
  public function testInsertAndUpdateHooksCallAutoAllow(): void {
    $source = file_get_contents(__DIR__ . '/../../bioland.module');
    foreach (['bioland_media_insert', 'bioland_media_update'] as $hook) {
      $body = substr($source, strpos($source, "function $hook("));
      $this->assertMatchesRegularExpression('/^[^}]*\$media->bundle\(\) === \'embed\'\) \{[^}]*_bioland_embed_auto_allow\(\$media/', $body, $hook);
    }
    $presave = substr($source, strpos($source, 'function bioland_media_presave('));
    $this->assertStringNotContainsString('_bioland_embed_auto_allow(', substr($presave, 0, strpos($presave, "\n}\n")));
  }

  /**
   * Registers a request stack whose current request has $query.
   */
  private function pushRequest(array $query = []): Request {
    $request = new Request($query);
    $stack = new RequestStack();
    $stack->push($request);
    \Drupal::setService('request_stack', $stack);
    return $request;
  }

  /**
   * BL-1289: only a save that added an entry flags the request.
   */
  public function testAutoAllowFlagsRequestOnlyWhenListChanged(): void {
    $this->autoAllow(['https://claude.ai/artifact/a'], TRUE);
    $this->assertTrue(\Drupal::requestStack()->getCurrentRequest()->attributes->get('_bioland_embed_allowlist_changed'));

    $this->autoAllow(['https://app.powerbi.com/view?r=1'], TRUE);
    $this->assertNull(\Drupal::requestStack()->getCurrentRequest()->attributes->get('_bioland_embed_allowlist_changed'));
  }

  /**
   * Runs the embed media submit handler; returns the redirect URL options
   * (NULL when no redirect was set) and the request it ran under.
   */
  private function submitEmbedForm(bool $changed): array {
    $request = $this->pushRequest(['destination' => '/admin/content/media']);
    if ($changed) {
      $request->attributes->set('_bioland_embed_allowlist_changed', TRUE);
    }
    $options = NULL;
    $media = $this->getMockBuilder(MediaInterface::class)->addMethods(['toUrl'])->getMockForAbstractClass();
    $media->method('toUrl')->willReturnCallback(function ($rel, array $opts) use (&$options) {
      $this->assertSame('canonical', $rel);
      $options = $opts;
      return (object) ['options' => $opts];
    });
    $form_object = new class($media) {

      public function __construct(private $entity) {}

      public function getEntity() {
        return $this->entity;
      }

    };
    $form_state = $this->getMockBuilder(FormStateInterface::class)->addMethods(['getFormObject', 'setRedirectUrl'])->getMockForAbstractClass();
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->expects($changed ? $this->once() : $this->never())->method('setRedirectUrl');
    $form = [];
    _bioland_embed_media_form_submit($form, $form_state);
    return [$options, $request];
  }

  /**
   * BL-1289: a save that changed the list lands on the media's own page with
   * a fresh head cache-clear nonce, replacing ?destination=.
   */
  public function testEmbedFormSubmitRedirectsThroughHeadCacheClear(): void {
    [$first, $request] = $this->submitEmbedForm(TRUE);
    $this->assertSame(['seachain-taisce'], array_keys($first['query']));
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9-]{1,64}$/', $first['query']['seachain-taisce']);
    $this->assertFalse($request->query->has('destination'));

    [$second] = $this->submitEmbedForm(TRUE);
    $this->assertNotSame($first['query']['seachain-taisce'], $second['query']['seachain-taisce'], 'The head clears once per nonce.');
  }

  /**
   * BL-1289: a save that left the list alone keeps its normal destination.
   */
  public function testEmbedFormSubmitLeavesRedirectWhenListUnchanged(): void {
    [$options, $request] = $this->submitEmbedForm(FALSE);
    $this->assertNull($options);
    $this->assertSame('/admin/content/media', $request->query->get('destination'));
  }

  /**
   * BL-1289: both embed media forms get the handler after the save.
   */
  public function testEmbedMediaFormsWireSubmitHandler(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    foreach (['media_embed_add_form', 'media_embed_edit_form'] as $form_id) {
      $form = ['actions' => ['submit' => ['#submit' => ['::submitForm', '::save']]]];
      ('bioland_form_' . $form_id . '_alter')($form, $form_state, $form_id);
      $this->assertSame(['::submitForm', '::save', '_bioland_embed_media_form_submit'], $form['actions']['submit']['#submit'], $form_id);
    }
  }

}
