<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\bioland\BiolandThemeContract;
use Drupal\bioland\Service\BiolandHeroEditorPreview;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the hero editor preview colour-resolution service.
 *
 * Pins three things beyond ordinary happy-path coverage:
 *   1. a valid, exactly-six-hex-digit authored colour always passes through;
 *   2. a malformed or empty value falls back to the site's flavour-dependent
 *      network default, never to an arbitrary or unvalidated config string;
 *   3. the BL2/BSL selection is driven by is_biosafety_land alone.
 *
 * @covers \Drupal\bioland\Service\BiolandHeroEditorPreview
 */
class BiolandHeroEditorPreviewTest extends TestCase {

  /**
   * Builds the service against a `bioland.settings` config fixture.
   *
   * @param bool $isBsl
   *   The `is_biosafety_land` config value.
   * @param array|null $hero
   *   The `theme.hero` config sub-array, or NULL to omit `theme` entirely
   *   (simulating an unauthored site).
   */
  protected function createService(bool $isBsl, ?array $hero = NULL): BiolandHeroEditorPreview {
    $settings = ['is_biosafety_land' => $isBsl];
    if ($hero !== NULL) {
      $settings['theme'] = ['hero' => $hero];
    }

    $config = new ImmutableConfig('bioland.settings', $settings);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('bioland.settings')->willReturn($config);

    return new BiolandHeroEditorPreview($configFactory);
  }

  /**
   * A valid hex colour passes through unchanged, for both flavours.
   */
  public function testValidHexPassesThrough(): void {
    $service = $this->createService(FALSE, ['primary' => '#1b7b3a', 'secondary' => '#a1b2c3']);

    $this->assertSame(['primary' => '#1b7b3a', 'secondary' => '#a1b2c3'], $service->settings());
  }

  /**
   * A malformed or empty authored value falls back to the flavour default.
   *
   * @dataProvider malformedValueProvider
   */
  public function testMalformedValueFallsBackToFlavourDefault($value): void {
    $service = $this->createService(FALSE, ['primary' => $value, 'secondary' => $value]);

    $this->assertSame(
      [
        'primary' => BiolandThemeContract::FALLBACK_PRIMARY_BL2,
        'secondary' => BiolandThemeContract::FALLBACK_HERO_SECONDARY_BL2,
      ],
      $service->settings()
    );
  }

  /**
   * Data provider of values that must never survive validation.
   *
   * @return array
   *   Each row is a single malformed/unusable raw config value.
   */
  public static function malformedValueProvider(): array {
    return [
      'empty string' => [''],
      'not a colour' => ['not-a-colour'],
      'missing hash' => ['1b7b3a'],
      'too short' => ['#fff'],
      'non-string' => [12345],
      'null' => [NULL],
    ];
  }

  /**
   * An entirely unauthored `theme` block falls back to the flavour default.
   */
  public function testMissingThemeBlockFallsBackToFlavourDefault(): void {
    $service = $this->createService(FALSE);

    $this->assertSame(
      [
        'primary' => BiolandThemeContract::FALLBACK_PRIMARY_BL2,
        'secondary' => BiolandThemeContract::FALLBACK_HERO_SECONDARY_BL2,
      ],
      $service->settings()
    );
  }

  /**
   * A BSL site falls back to the BSL pair, never the bl2 one.
   */
  public function testBslSiteFallsBackToBslPair(): void {
    $service = $this->createService(TRUE);

    $this->assertSame(
      [
        'primary' => BiolandThemeContract::FALLBACK_PRIMARY_BSL,
        'secondary' => BiolandThemeContract::FALLBACK_HERO_SECONDARY_BSL,
      ],
      $service->settings()
    );
  }

  /**
   * A BSL site's own authored colours still pass through unchanged.
   */
  public function testBslSiteAuthoredColoursPassThrough(): void {
    $service = $this->createService(TRUE, ['primary' => '#123456', 'secondary' => '#abcdef']);

    $this->assertSame(['primary' => '#123456', 'secondary' => '#abcdef'], $service->settings());
  }

}
