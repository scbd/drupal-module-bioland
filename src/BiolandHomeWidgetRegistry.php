<?php

namespace Drupal\bioland;

/**
 * The single source of truth for the widget key <-> theme-name mapping and
 * classification.
 *
 * Not yet consolidated: three other enumerations of the same 13 keys survive
 * untouched and are not (yet) derived from this class —
 * \Drupal\bioland\Service\BiolandSettingsManager::getHomeWidgetSettings()
 * (the block that actually ships `home_widgets.*.enable` to head),
 * `config/schema/bioland.schema.yml`, and `config/install/bioland.settings.yml`.
 * See docs/debt.md for the consolidation debt this leaves open.
 *
 * Two vocabularies describe the same widgets from opposite ends of the stack,
 * and until now neither end knew about the other:
 *
 * - Drupal widget keys (snake_case) — 13 of them, 10 CHM + 3 BSL. Extracted
 *   from \Drupal\bioland\Form\BiolandHomeWidgetsForm, which owns the per-site
 *   `home_widgets.<key>.enable` flags in `bioland.settings`.
 * - Head theme-names (camelCase) — 9 of them. Source of truth is the head
 *   repo's `app/components/page/home-page-widget-selection.vue:21-29`, where
 *   each name is matched by a `computed()` against either the literal name or
 *   a substring of the component name. `home-chm.vue:18-20` feeds those names
 *   in from `siteStore.theme.homePageWidgets.columns`.
 *
 * The correspondence is deliberately NOT a bijection, and this class encodes
 * that rather than forcing the counts to line up:
 *
 * - 9 theme-names map to 9 of the 10 CHM keys.
 * - `national_targets_widget` has NO theme-name at all: the head renders it
 *   unconditionally as `SwiperNt7` (`home-chm.vue:15-17`), outside the column
 *   mechanism entirely.
 * - The 3 BSL keys have no theme-names: the BSL home page does not use the
 *   column mechanism. They stay here as legacy mappings (plan decision D7) so
 *   the vocabulary is complete, but they are never authorable.
 *
 * Classifications (exactly one per entry):
 *
 * - self::CLASSIFICATION_AUTHORABLE — the widget may be placed in a Theme-tab
 *   column. This is the set the Theme tab validates `columns` against.
 * - self::CLASSIFICATION_PLACEMENT_FIXED — the head already renders the widget
 *   unconditionally outside the column mechanism, so placing it in a column
 *   would render it twice. `latest_news_widget` (`LazySwiperNewsUpdates`,
 *   `home-chm.vue:12-14`) and `national_targets_widget` (`SwiperNt7`,
 *   `home-chm.vue:15-17`).
 * - self::CLASSIFICATION_LEGACY_NON_AUTHORABLE — the 3 BSL keys (D7).
 *
 * Known pre-existing head defect, recorded here and deliberately NOT fixed
 * (out of scope for this change): the `news` and `panorama` branches at
 * `home-page-widget-selection.vue:3-4` render with a bare `v-if="isNews"` /
 * `v-if="isPanorama"` and never consult an enable flag, unlike every other
 * branch which is `v-if="isX && xEnabled"`. `panoramaEnabled` is even computed
 * at line 31 and then left unused. So toggling those two enable flags in
 * Drupal has no effect on the head. See self::UNGATED_THEME_NAMES.
 *
 * Consumers:
 *
 * - \Drupal\bioland\Form\BiolandHomeWidgetsForm — key lists only; the enable
 *   flags stay on the Home Widgets tab.
 * - The Theme tab (phase 02) — the authorable set and the flavor split.
 * - The head is unaffected: this class ships dark and changes no render path.
 */
final class BiolandHomeWidgetRegistry {

  /**
   * Flavor for CHM (Bioland) sites.
   */
  public const FLAVOR_CHM = 'chm';

  /**
   * Flavor for BSL (Biosafety Clearing-House) sites.
   */
  public const FLAVOR_BSL = 'bsl';

  /**
   * Placeable in a Theme-tab column.
   */
  public const CLASSIFICATION_AUTHORABLE = 'authorable';

  /**
   * Rendered unconditionally by the head outside the column mechanism.
   */
  public const CLASSIFICATION_PLACEMENT_FIXED = 'placement_fixed';

  /**
   * Kept as a mapping only; never authorable (D7).
   */
  public const CLASSIFICATION_LEGACY_NON_AUTHORABLE = 'legacy_non_authorable';

  /**
   * The BSL widget keys, in their historical form order.
   *
   * Exposed as a constant so BiolandHomeWidgetsForm can keep its BSL_WIDGETS
   * class constant (a constant expression cannot call a method).
   */
  public const BSL_WIDGET_KEYS = [
    'nbf_widget',
    'bch_news_widget',
    'bch_resources_widget',
  ];

  /**
   * Theme-names the head renders without consulting their enable flag.
   *
   * A pre-existing head defect, classified here and not fixed. See the class
   * docblock.
   */
  public const UNGATED_THEME_NAMES = [
    'news',
    'panorama',
  ];

  /**
   * The widget definitions, keyed by Drupal widget key.
   *
   * Ordered exactly as BiolandHomeWidgetsForm::ensureHomeWidgetDefaults()
   * enumerated them, so the extraction is order-preserving.
   *
   * Each definition carries:
   * - theme_name: the head theme-name, or NULL when the widget is not part of
   *   the head's column vocabulary.
   * - head_matcher: the component substring matched at
   *   `home-page-widget-selection.vue:21-29`, or NULL when there is no matcher.
   * - flavor: self::FLAVOR_CHM or self::FLAVOR_BSL.
   * - classification: exactly one of the self::CLASSIFICATION_* constants.
   * - note: why the entry is not plain authorable, or '' when it is.
   *
   * head_matcher and note have no runtime reader, and neither does
   * self::UNGATED_THEME_NAMES above: all three exist as executable
   * documentation for tests and humans, so a future reader does not go
   * hunting for a consumer that was never meant to exist.
   */
  public const WIDGETS = [
    'gbif_widget' => [
      'theme_name' => 'gbif',
      'head_matcher' => 'WidgetGbif',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'latest_news_widget' => [
      'theme_name' => 'news',
      'head_matcher' => 'SwiperNewsUpdates',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_PLACEMENT_FIXED,
      'note' => 'Already rendered unconditionally at home-chm.vue:12-14; a column placement would double-render it. Its enable flag is also ungated in head.',
    ],
    'national_targets_widget' => [
      'theme_name' => NULL,
      'head_matcher' => NULL,
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_PLACEMENT_FIXED,
      'note' => 'No theme-name exists: rendered unconditionally as SwiperNt7 at home-chm.vue:15-17, outside the column mechanism.',
    ],
    'panorama_solutions_widget' => [
      'theme_name' => 'panorama',
      'head_matcher' => 'WidgetPanorama',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => 'Authorable, but its enable flag is ungated in head (panoramaEnabled is computed and unused).',
    ],
    'elearning_widget' => [
      'theme_name' => 'eLearning',
      'head_matcher' => 'WidgetELearning',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'implementation_widget' => [
      'theme_name' => 'implementation',
      'head_matcher' => 'WidgetImplementation',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'technical_cooperation_widget' => [
      'theme_name' => 'tsc',
      'head_matcher' => 'WidgetTsc',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'latest_discussions_widget' => [
      'theme_name' => 'forums',
      'head_matcher' => 'WidgetForums',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'content_statistics_widget' => [
      'theme_name' => 'contentStats',
      'head_matcher' => 'WidgetContentTypesStats',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'geobon_widget' => [
      'theme_name' => 'geobon',
      'head_matcher' => 'WidgetGeobon',
      'flavor' => self::FLAVOR_CHM,
      'classification' => self::CLASSIFICATION_AUTHORABLE,
      'note' => '',
    ],
    'nbf_widget' => [
      'theme_name' => NULL,
      'head_matcher' => NULL,
      'flavor' => self::FLAVOR_BSL,
      'classification' => self::CLASSIFICATION_LEGACY_NON_AUTHORABLE,
      'note' => 'BSL home page does not use the column mechanism (D7).',
    ],
    'bch_news_widget' => [
      'theme_name' => NULL,
      'head_matcher' => NULL,
      'flavor' => self::FLAVOR_BSL,
      'classification' => self::CLASSIFICATION_LEGACY_NON_AUTHORABLE,
      'note' => 'BSL home page does not use the column mechanism (D7).',
    ],
    'bch_resources_widget' => [
      'theme_name' => NULL,
      'head_matcher' => NULL,
      'flavor' => self::FLAVOR_BSL,
      'classification' => self::CLASSIFICATION_LEGACY_NON_AUTHORABLE,
      'note' => 'BSL home page does not use the column mechanism (D7).',
    ],
  ];

  /**
   * All Drupal widget keys.
   *
   * @return string[]
   *   The widget keys, CHM first then BSL.
   */
  public static function allKeys(): array {
    return array_keys(self::WIDGETS);
  }

  /**
   * The widget keys for one flavor.
   *
   * @param string $flavor
   *   self::FLAVOR_CHM or self::FLAVOR_BSL.
   *
   * @return string[]
   *   The matching widget keys.
   */
  public static function keysForFlavor(string $flavor): array {
    return array_keys(array_filter(
      self::WIDGETS,
      static fn(array $definition): bool => $definition['flavor'] === $flavor
    ));
  }

  /**
   * The CHM (Bioland) widget keys.
   *
   * @return string[]
   *   The CHM widget keys.
   */
  public static function chmKeys(): array {
    return self::keysForFlavor(self::FLAVOR_CHM);
  }

  /**
   * The BSL (Biosafety Clearing-House) widget keys.
   *
   * @return string[]
   *   The BSL widget keys.
   */
  public static function bslKeys(): array {
    return self::keysForFlavor(self::FLAVOR_BSL);
  }

  /**
   * Every head theme-name known to the registry.
   *
   * @return string[]
   *   The theme-names, including placement-fixed ones.
   */
  public static function themeNames(): array {
    return self::keepNonNullThemeNames(array_column(self::WIDGETS, 'theme_name'));
  }

  /**
   * Removes NULL entries from a theme-name list, keeping `''` and `'0'`.
   *
   * Extracted from self::themeNames() so this NULL-only semantics — deliberately
   * distinct from array_filter()'s default falsy-value semantics, which would
   * also drop `''` and `'0'` — can be pinned by a unit test against a fixture,
   * independent of self::WIDGETS (which never contains those edge values, so a
   * test against the real constant cannot catch a regression here).
   *
   * @param array<int, string|null> $theme_names
   *   Theme-names, possibly containing NULL for keyless widgets.
   *
   * @return string[]
   *   The non-NULL theme-names, re-indexed.
   */
  private static function keepNonNullThemeNames(array $theme_names): array {
    return array_values(array_filter(
      $theme_names,
      static fn(?string $name): bool => $name !== NULL
    ));
  }

  /**
   * The widget keys an editor may place in a Theme-tab column.
   *
   * @return string[]
   *   The authorable widget keys.
   */
  public static function authorableKeys(): array {
    return array_keys(array_filter(
      self::WIDGETS,
      static fn(array $definition): bool => $definition['classification'] === self::CLASSIFICATION_AUTHORABLE
    ));
  }

  /**
   * The theme-names an editor may place in a Theme-tab column.
   *
   * This is the vocabulary the Theme tab validates `columns` against.
   *
   * @return string[]
   *   The authorable theme-names.
   */
  public static function authorableThemeNames(): array {
    return array_values(array_map(
      static fn(string $key): string => self::WIDGETS[$key]['theme_name'],
      self::authorableKeys()
    ));
  }

  /**
   * The definition for one widget key.
   *
   * @param string $key
   *   The Drupal widget key.
   *
   * @return array|null
   *   The definition, or NULL when the key is unknown.
   */
  public static function definition(string $key): ?array {
    return self::WIDGETS[$key] ?? NULL;
  }

  /**
   * The head theme-name for a Drupal widget key.
   *
   * @param string $key
   *   The Drupal widget key.
   *
   * @return string|null
   *   The theme-name, or NULL when the widget has none or the key is unknown.
   */
  public static function themeNameFor(string $key): ?string {
    return self::WIDGETS[$key]['theme_name'] ?? NULL;
  }

  /**
   * The Drupal widget key for a head theme-name.
   *
   * @param string $theme_name
   *   The head theme-name.
   *
   * @return string|null
   *   The widget key, or NULL when the theme-name is unknown.
   */
  public static function keyForThemeName(string $theme_name): ?string {
    foreach (self::WIDGETS as $key => $definition) {
      if ($definition['theme_name'] === $theme_name) {
        return $key;
      }
    }

    return NULL;
  }

  /**
   * Whether a theme-name may be placed in a Theme-tab column.
   *
   * @param string $theme_name
   *   The head theme-name.
   *
   * @return bool
   *   TRUE when the theme-name is authorable.
   */
  public static function isAuthorableThemeName(string $theme_name): bool {
    return in_array($theme_name, self::authorableThemeNames(), TRUE);
  }

}
