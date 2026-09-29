<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandEmbedPreviewSandbox;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Template\Attribute;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-1218 Drupal-side embed preview sandbox.
 *
 * @coversDefaultClass \Drupal\bioland\BiolandEmbedPreviewSandbox
 * @group bioland
 */
class BiolandEmbedPreviewSandboxTest extends TestCase {

  private const ENTRIES = [
    ['url' => 'https://www.youtube.com', 'sandbox' => ''],
    ['url' => 'https://app.powerbi.com/view', 'sandbox' => ''],
    ['url' => 'https://files.example.org/sites/x/files', 'sandbox' => 'allow-scripts allow-same-origin allow-top-navigation'],
    ['url' => 'https://typo.example', 'sandbox' => 'allow-scriptz'],
  ];

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
   * An iframe formatter element as iframe 3.0.2's iframeIframe() builds it.
   */
  private static function iframe(string $src): array {
    $attributes = new Attribute(['src' => $src, 'width' => '100%', 'height' => '701', 'class' => ['x'], 'allow' => 'accelerometer;autoplay;camera;encrypted-media;geolocation;gyroscope;microphone;payment;picture-in-picture;fullscreen']);
    return ['#theme' => 'iframe', '#src' => $src, '#attributes' => $attributes];
  }

  /**
   * The fallback text and link are dropped; the frame's attributes stay.
   */
  public function testFallbackContentIsRemoved(): void {
    $element = self::iframe('https://app.powerbi.com/view?r=1');
    $element['#attributes'] = new Attribute($element['#attributes']->toArray() + ['title' => 'Report']);
    $element['#text'] = 'Your browser does not support iframes, but you can visit <a href="https://app.powerbi.com/view?r=1">Report</a>';
    $out = BiolandEmbedPreviewSandbox::apply($element, self::ENTRIES);
    $this->assertSame('', $out['#text']);
    $this->assertSame('iframe', $out['#theme']);
    $this->assertSame('https://app.powerbi.com/view?r=1', $out['#src']);
    foreach (['src', 'title', 'width', 'height', 'allowfullscreen'] as $name) {
      $this->assertArrayHasKey($name, $out['#attributes']);
    }
    $this->assertSame('Report', $out['#attributes']['title']);
  }

  /**
   * An unmatched frame loses its fallback too, since the head sanitizes it.
   */
  public function testFallbackRemovedFromUnmatchedFrame(): void {
    $element = self::iframe('https://evil.example/') + ['#text' => 'fallback <a href="x">x</a>'];
    $this->assertSame('', BiolandEmbedPreviewSandbox::apply($element, self::ENTRIES)['#text']);
  }

  /**
   * An item without fallback content gains no text key.
   */
  public function testItemWithoutFallbackHasNoTextKey(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://app.powerbi.com/view'), self::ENTRIES);
    $this->assertArrayNotHasKey('#text', $out);
  }

  /**
   * A player gets the head's player policy and fullscreen, no sandbox.
   */
  public function testMediaPlayerGetsPlayerPolicy(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://www.youtube.com/embed/abc'), self::ENTRIES);
    $this->assertSame(BiolandEmbedPreviewSandbox::PLAYER_ALLOW, $out['#attributes']['allow']);
    $this->assertSame('', $out['#attributes']['allowfullscreen']);
    $this->assertArrayNotHasKey('sandbox', $out['#attributes']);
    $this->assertSame(['x'], $out['#attributes']['class']);
    $this->assertContains('config:bioland.settings', $out['#cache']['tags']);
  }

  /**
   * Any other host loses the permission delegation and keeps fullscreen.
   */
  public function testOtherHostLosesAllowKeepsFullscreen(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://app.powerbi.com/view?r=1'), self::ENTRIES);
    $this->assertArrayNotHasKey('allow', $out['#attributes']);
    $this->assertSame('', $out['#attributes']['allowfullscreen']);
    $this->assertArrayNotHasKey('sandbox', $out['#attributes']);
  }

  /**
   * The entry sandbox is applied with the head's filtering.
   */
  public function testEntrySandboxIsFiltered(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://files.example.org/sites/x/files/a.html'), self::ENTRIES);
    $this->assertSame('allow-scripts', $out['#attributes']['sandbox']);
    $this->assertArrayNotHasKey('allow', $out['#attributes']);
  }

  /**
   * A configured sandbox that filters to nothing stays fully restricted.
   */
  public function testFilteredToNothingIsEmptySandbox(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://typo.example/x'), self::ENTRIES);
    $this->assertSame('', $out['#attributes']['sandbox']);
  }

  /**
   * An element's own sandbox is filtered when the entry sets none.
   */
  public function testElementSandboxFilteredWhenEntryHasNone(): void {
    $element = ['#theme' => 'iframe', '#src' => 'https://app.powerbi.com/view', '#attributes' => ['sandbox' => 'allow-scripts allow-same-origin']];
    $this->assertSame('allow-scripts', BiolandEmbedPreviewSandbox::apply($element, self::ENTRIES)['#attributes']['sandbox']);
    $element['#attributes']['sandbox'] = '';
    $this->assertSame('', BiolandEmbedPreviewSandbox::apply($element, self::ENTRIES)['#attributes']['sandbox']);
  }

  /**
   * The formatter's Attribute object is read and replaced by a plain array.
   */
  public function testAttributeObjectInput(): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe('https://files.example.org/sites/x/files/a.html'), self::ENTRIES);
    $this->assertIsArray($out['#attributes']);
    $this->assertSame([
      'src' => 'https://files.example.org/sites/x/files/a.html',
      'width' => '100%',
      'height' => '701',
      'class' => ['x'],
      'sandbox' => 'allow-scripts',
      'allowfullscreen' => '',
    ], $out['#attributes']);
  }

  /**
   * In the CKEditor preview an unmatched URL renders a notice, not an iframe.
   *
   * @dataProvider blockedProvider
   */
  public function testUnmatchedInEditorPreviewRendersNotice(string $src, array $entries): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe($src), $entries, TRUE);
    $this->assertArrayNotHasKey('#theme', $out);
    $this->assertSame('p', $out['#tag']);
    $this->assertStringContainsString('not on the site embed allowlist', (string) $out['#value']);
    $this->assertSame(['tags' => ['config:bioland.settings'], 'contexts' => ['route']], $out['#cache']);
  }

  /**
   * Elsewhere an unmatched iframe is left for the head, varying by route.
   *
   * @dataProvider blockedProvider
   */
  public function testUnmatchedElsewhereIsUntouched(string $src, array $entries): void {
    $element = self::iframe($src) + ['#cache' => ['tags' => ['media:1']]];
    $out = BiolandEmbedPreviewSandbox::apply($element, $entries);
    $this->assertSame(['tags' => ['media:1', 'config:bioland.settings'], 'contexts' => ['route']], $out['#cache']);
    unset($out['#cache'], $element['#cache']);
    $this->assertSame($element, $out);
  }

  /**
   * Blocked sources.
   */
  public static function blockedProvider(): array {
    return [
      'other host' => ['https://evil.example/', self::ENTRIES],
      'path outside entry' => ['https://app.powerbi.com/view-evil', self::ENTRIES],
      'empty list' => ['https://www.youtube.com/embed/abc', []],
      'no src' => ['', self::ENTRIES],
    ];
  }

  /**
   * Registers config and the current route for bioland_preprocess_field().
   */
  private function services(?string $route): void {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn(new ImmutableConfig('bioland.settings', ['embed' => ['allowed_origins' => self::ENTRIES]]));
    \Drupal::setService('config.factory', $factory);
    \Drupal::setService('current_route_match', new class($route) {

      public function __construct(private ?string $route) {}

      public function getRouteName(): ?string {
        return $this->route;
      }

    });
  }

  /**
   * Field variables for an embed with an unmatched, a matched and a non-iframe item.
   */
  private static function fieldVariables(string $bundle = 'embed'): array {
    return [
      'element' => ['#entity_type' => 'media', '#bundle' => $bundle],
      'items' => [
        ['content' => self::iframe('https://evil.example/')],
        ['content' => self::iframe('https://app.powerbi.com/view')],
        ['content' => ['#markup' => 'not an iframe']],
      ],
    ];
  }

  /**
   * On the CKEditor preview route the unmatched item becomes the notice.
   */
  public function testPreprocessFieldOnEditorPreview(): void {
    $this->services(BiolandEmbedPreviewSandbox::EDITOR_PREVIEW_ROUTE);
    $variables = self::fieldVariables();
    bioland_preprocess_field($variables);
    $this->assertSame('p', $variables['items'][0]['content']['#tag']);
    $this->assertSame('iframe', $variables['items'][1]['content']['#theme']);
    $this->assertArrayNotHasKey('allow', $variables['items'][1]['content']['#attributes']);
    $this->assertSame(['#markup' => 'not an iframe'], $variables['items'][2]['content']);
  }

  /**
   * On any other route the unmatched item stays an iframe; matched ones are
   * still sandboxed; other bundles are not touched.
   */
  public function testPreprocessFieldElsewhere(): void {
    $this->services('entity.node.canonical');
    $variables = self::fieldVariables();
    bioland_preprocess_field($variables);
    $this->assertSame('iframe', $variables['items'][0]['content']['#theme']);
    $this->assertSame(['route'], $variables['items'][0]['content']['#cache']['contexts']);
    $this->assertArrayNotHasKey('allow', $variables['items'][1]['content']['#attributes']);

    $other = self::fieldVariables('document');
    $before = $other;
    bioland_preprocess_field($other);
    $this->assertSame($before, $other);
  }

}
