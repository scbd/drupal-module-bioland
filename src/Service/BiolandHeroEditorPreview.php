<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\bioland\BiolandThemeContract;

/**
 * Resolves the hero editor preview colours passed to drupalSettings.
 *
 * Split out of bioland.module so the colour/fallback logic — previously
 * bioland_hero_editor_preview_settings() and _bioland_hero_preview_color() —
 * is unit testable without bootstrapping a form alter. The .module hook stays
 * a thin call into this service, matching the pattern set by
 * BiolandComponentMenuFormMode::primaryColor().
 *
 * @see \Drupal\Tests\bioland\Unit\Service\BiolandHeroEditorPreviewTest
 */
class BiolandHeroEditorPreview {

  /**
   * The configuration factory, read for bioland.settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the hero editor preview service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Returns the hero preview colours for drupalSettings.
   *
   * Reads bioland.settings:theme.hero.primary / .secondary — the scalars
   * BiolandThemeForm writes — re-validating their shape exactly as
   * BiolandComponentMenuFormMode::primaryColor() does, since config can also
   * arrive from a settings.php override or a hand-edited export. Anything not
   * exactly six hex digits falls back to this site's network default; the
   * fallback pairs are BiolandThemeContract's, the same ones BiolandThemeForm's
   * colour pickers fall back to.
   *
   * @return array
   *   `['primary' => '#rrggbb', 'secondary' => '#rrggbb']`.
   */
  public function settings(): array {
    $config = $this->configFactory->get('bioland.settings');
    $is_bsl = (bool) $config->get('is_biosafety_land');

    return [
      'primary' => $this->color(
        $config->get('theme.hero.primary'),
        $is_bsl ? BiolandThemeContract::FALLBACK_PRIMARY_BSL : BiolandThemeContract::FALLBACK_PRIMARY_BL2
      ),
      'secondary' => $this->color(
        $config->get('theme.hero.secondary'),
        $is_bsl ? BiolandThemeContract::FALLBACK_HERO_SECONDARY_BSL : BiolandThemeContract::FALLBACK_HERO_SECONDARY_BL2
      ),
    ];
  }

  /**
   * Validates a hex colour config value, falling back when it is unusable.
   *
   * @param mixed $value
   *   The raw config value.
   * @param string $fallback
   *   A validated "#rrggbb" fallback.
   *
   * @return string
   *   A validated "#rrggbb" colour, never an arbitrary config string.
   */
  protected function color($value, string $fallback): string {
    $value = is_string($value) ? trim($value) : '';
    return preg_match('/^#[0-9A-Fa-f]{6}\z/', $value) === 1 ? $value : $fallback;
  }

}
