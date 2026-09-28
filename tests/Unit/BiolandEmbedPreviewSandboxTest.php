<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandEmbedPreviewSandbox;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
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
    $attributes = new class(['src' => $src, 'width' => '100%', 'height' => '701', 'class' => ['x'], 'allow' => 'accelerometer;autoplay;camera;encrypted-media;geolocation;gyroscope;microphone;payment;picture-in-picture;fullscreen']) {

      public function __construct(private array $values) {}

      public function toArray(): array {
        return $this->values;
      }

    };
    return ['#theme' => 'iframe', '#src' => $src, '#attributes' => $attributes];
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
   * An unmatched URL renders a notice, never an iframe.
   *
   * @dataProvider blockedProvider
   */
  public function testUnmatchedRendersNoIframe(string $src, array $entries): void {
    $out = BiolandEmbedPreviewSandbox::apply(self::iframe($src), $entries);
    $this->assertArrayNotHasKey('#theme', $out);
    $this->assertSame('p', $out['#tag']);
    $this->assertStringContainsString('not on the site embed allowlist', (string) $out['#value']);
    $this->assertSame(['config:bioland.settings'], $out['#cache']['tags']);
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
   * The field preprocess touches embed media iframes only.
   */
  public function testPreprocessFieldScope(): void {
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn(new ImmutableConfig('bioland.settings', ['embed' => ['allowed_origins' => self::ENTRIES]]));
    \Drupal::setService('config.factory', $factory);

    $variables = [
      'element' => ['#entity_type' => 'media', '#bundle' => 'embed'],
      'items' => [
        ['content' => self::iframe('https://evil.example/')],
        ['content' => self::iframe('https://app.powerbi.com/view')],
        ['content' => ['#markup' => 'not an iframe']],
      ],
    ];
    bioland_preprocess_field($variables);
    $this->assertSame('p', $variables['items'][0]['content']['#tag']);
    $this->assertSame('iframe', $variables['items'][1]['content']['#theme']);
    $this->assertArrayNotHasKey('allow', $variables['items'][1]['content']['#attributes']);
    $this->assertSame(['#markup' => 'not an iframe'], $variables['items'][2]['content']);

    $other = ['element' => ['#entity_type' => 'media', '#bundle' => 'document'], 'items' => [['content' => self::iframe('https://evil.example/')]]];
    $before = $other;
    bioland_preprocess_field($other);
    $this->assertSame($before, $other);
  }

}
