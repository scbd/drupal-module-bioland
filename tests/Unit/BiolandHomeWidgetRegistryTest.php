<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\BiolandHomeWidgetRegistry;
use Drupal\bioland\Form\BiolandHomeWidgetsForm;

/**
 * Static pinning tests for the home widget vocabulary.
 *
 * Every pairing asserted here was verified against the head matchers at
 * `app/components/page/home-page-widget-selection.vue:21-29` and the
 * unconditional renders at `app/components/page/home-chm.vue:12-17`.
 *
 * @covers \Drupal\bioland\BiolandHomeWidgetRegistry
 */
class BiolandHomeWidgetRegistryTest extends TestCase {

  /**
   * The verified pairing table: widget key => [theme-name, head matcher].
   *
   * Held here independently of the registry so a change to either side has to
   * be made deliberately in two places.
   *
   * @var array<string, array{0: string|null, 1: string|null}>
   */
  protected const VERIFIED_PAIRINGS = [
    'gbif_widget' => ['gbif', 'WidgetGbif'],
    'latest_news_widget' => ['news', 'SwiperNewsUpdates'],
    'national_targets_widget' => [NULL, NULL],
    'panorama_solutions_widget' => ['panorama', 'WidgetPanorama'],
    'elearning_widget' => ['eLearning', 'WidgetELearning'],
    'implementation_widget' => ['implementation', 'WidgetImplementation'],
    'technical_cooperation_widget' => ['tsc', 'WidgetTsc'],
    'latest_discussions_widget' => ['forums', 'WidgetForums'],
    'content_statistics_widget' => ['contentStats', 'WidgetContentTypesStats'],
    'geobon_widget' => ['geobon', 'WidgetGeobon'],
    'nbf_widget' => [NULL, NULL],
    'bch_news_widget' => [NULL, NULL],
    'bch_resources_widget' => [NULL, NULL],
  ];

  /**
   * Tests the vocabulary counts: 9 theme-names, 10 CHM + 3 BSL keys.
   */
  public function testVocabularyCounts(): void {
    $this->assertCount(13, BiolandHomeWidgetRegistry::allKeys());
    $this->assertCount(10, BiolandHomeWidgetRegistry::chmKeys());
    $this->assertCount(3, BiolandHomeWidgetRegistry::bslKeys());
    $this->assertCount(9, BiolandHomeWidgetRegistry::themeNames());
  }

  /**
   * Tests the 9 head theme-names are exactly the ones the head matches on.
   */
  public function testThemeNamesMatchHeadSourceOfTruth(): void {
    // home-page-widget-selection.vue:21-29, in declaration order.
    $expected = [
      'news',
      'panorama',
      'gbif',
      'eLearning',
      'implementation',
      'tsc',
      'forums',
      'geobon',
      'contentStats',
    ];

    $actual = BiolandHomeWidgetRegistry::themeNames();
    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);
  }

  /**
   * Tests every verified pairing round-trips through the registry.
   */
  public function testEveryPairingIsAsVerified(): void {
    $this->assertSame(
      array_keys(self::VERIFIED_PAIRINGS),
      BiolandHomeWidgetRegistry::allKeys(),
      'The registry key set (and order) drifted from the verified pairing table.'
    );

    foreach (self::VERIFIED_PAIRINGS as $key => [$theme_name, $matcher]) {
      $definition = BiolandHomeWidgetRegistry::definition($key);

      $this->assertSame($theme_name, $definition['theme_name'], "Wrong theme-name for {$key}.");
      $this->assertSame($matcher, $definition['head_matcher'], "Wrong head matcher for {$key}.");
      $this->assertSame($theme_name, BiolandHomeWidgetRegistry::themeNameFor($key));

      if ($theme_name !== NULL) {
        $this->assertSame($key, BiolandHomeWidgetRegistry::keyForThemeName($theme_name));
      }
    }
  }

  /**
   * Tests the mapping is not forced into a bijection.
   */
  public function testMappingIsNotABijection(): void {
    // No theme-name: rendered unconditionally as SwiperNt7, home-chm.vue:15-17.
    $this->assertNull(BiolandHomeWidgetRegistry::themeNameFor('national_targets_widget'));

    // The 3 BSL keys are mappings only — the BSL home page has no columns.
    foreach (BiolandHomeWidgetRegistry::bslKeys() as $key) {
      $this->assertNull(BiolandHomeWidgetRegistry::themeNameFor($key), "{$key} must not carry a theme-name.");
    }

    // 13 keys, 9 theme-names: 4 keys map to nothing on the head side.
    $this->assertCount(
      4,
      array_filter(
        BiolandHomeWidgetRegistry::allKeys(),
        static fn(string $key): bool => BiolandHomeWidgetRegistry::themeNameFor($key) === NULL
      )
    );

    // An unknown theme-name resolves to nothing rather than guessing.
    $this->assertNull(BiolandHomeWidgetRegistry::keyForThemeName('nationalTargets'));
  }

  /**
   * Tests every entry has a valid classification, flavor, and definition
   * key order.
   */
  public function testEveryEntryHasAValidClassificationAndFlavor(): void {
    $classifications = [
      BiolandHomeWidgetRegistry::CLASSIFICATION_AUTHORABLE,
      BiolandHomeWidgetRegistry::CLASSIFICATION_PLACEMENT_FIXED,
      BiolandHomeWidgetRegistry::CLASSIFICATION_LEGACY_NON_AUTHORABLE,
    ];
    $flavors = [
      BiolandHomeWidgetRegistry::FLAVOR_CHM,
      BiolandHomeWidgetRegistry::FLAVOR_BSL,
    ];

    foreach (BiolandHomeWidgetRegistry::WIDGETS as $key => $definition) {
      $this->assertSame(
        ['theme_name', 'head_matcher', 'flavor', 'classification', 'note'],
        array_keys($definition),
        "Definition shape drifted for {$key}."
      );
      $this->assertContains($definition['classification'], $classifications, "Bad classification for {$key}.");
      $this->assertContains($definition['flavor'], $flavors, "Bad flavor for {$key}.");
      $this->assertIsString($definition['note']);
    }
  }

  /**
   * Tests the authorable set excludes placement-fixed and legacy entries.
   */
  public function testAuthorableSetExcludesPlacementFixedAndLegacy(): void {
    $authorable = BiolandHomeWidgetRegistry::authorableKeys();

    // Placement-fixed: the head already renders these outside the columns.
    $this->assertNotContains('latest_news_widget', $authorable);
    $this->assertNotContains('national_targets_widget', $authorable);

    // Legacy non-authorable (D7).
    foreach (BiolandHomeWidgetRegistry::bslKeys() as $key) {
      $this->assertNotContains($key, $authorable, "{$key} must never be authorable.");
    }

    $this->assertSame([
      'gbif_widget',
      'panorama_solutions_widget',
      'elearning_widget',
      'implementation_widget',
      'technical_cooperation_widget',
      'latest_discussions_widget',
      'content_statistics_widget',
      'geobon_widget',
    ], $authorable);
  }

  /**
   * Tests the authorable theme-name vocabulary the Theme tab validates against.
   */
  public function testAuthorableThemeNames(): void {
    $names = BiolandHomeWidgetRegistry::authorableThemeNames();

    $this->assertCount(8, $names);
    $this->assertNotContains('news', $names, 'Placing news in a column would double-render it.');

    foreach ($names as $name) {
      $this->assertTrue(BiolandHomeWidgetRegistry::isAuthorableThemeName($name));
    }

    $this->assertFalse(BiolandHomeWidgetRegistry::isAuthorableThemeName('news'));
    $this->assertFalse(BiolandHomeWidgetRegistry::isAuthorableThemeName('nbf'));
  }

  /**
   * Tests every authorable entry carries a non-NULL theme-name.
   *
   * authorableThemeNames() maps theme_name through a closure declared to
   * return `string`. An authorable entry with a NULL theme_name would throw
   * a runtime TypeError there instead of failing this test.
   */
  public function testEveryAuthorableEntryHasANonNullThemeName(): void {
    foreach (BiolandHomeWidgetRegistry::authorableKeys() as $key) {
      $this->assertNotNull(
        BiolandHomeWidgetRegistry::definition($key)['theme_name'],
        "{$key} is authorable but has no theme_name."
      );
    }
  }

  /**
   * Tests the flavor split matches D7: 3 BSL keys, everything else CHM.
   */
  public function testFlavorSplitMatchesD7(): void {
    $this->assertSame([
      'nbf_widget',
      'bch_news_widget',
      'bch_resources_widget',
    ], BiolandHomeWidgetRegistry::bslKeys());

    foreach (BiolandHomeWidgetRegistry::chmKeys() as $key) {
      $this->assertSame(
        BiolandHomeWidgetRegistry::FLAVOR_CHM,
        BiolandHomeWidgetRegistry::definition($key)['flavor']
      );
    }

    // Only CHM widgets are authorable — BSL sites hide the columns field.
    foreach (BiolandHomeWidgetRegistry::authorableKeys() as $key) {
      $this->assertSame(
        BiolandHomeWidgetRegistry::FLAVOR_CHM,
        BiolandHomeWidgetRegistry::definition($key)['flavor']
      );
    }
  }

  /**
   * Tests the known ungated-in-head theme-names are classified, not silent.
   */
  public function testUngatedHeadThemeNamesAreRecorded(): void {
    // home-page-widget-selection.vue:3-4 render these without an enable check.
    $this->assertSame(['news', 'panorama'], BiolandHomeWidgetRegistry::UNGATED_THEME_NAMES);

    foreach (BiolandHomeWidgetRegistry::UNGATED_THEME_NAMES as $name) {
      $key = BiolandHomeWidgetRegistry::keyForThemeName($name);
      $this->assertNotNull($key);
      $this->assertNotSame('', BiolandHomeWidgetRegistry::definition($key)['note']);
    }
  }

  /**
   * Tests the form's BSL constant still resolves to the registry's BSL keys.
   */
  public function testFormBslConstantIsTheRegistryVocabulary(): void {
    $constant = (new \ReflectionClass(BiolandHomeWidgetsForm::class))->getConstant('BSL_WIDGETS');

    $this->assertSame(BiolandHomeWidgetRegistry::BSL_WIDGET_KEYS, $constant);
    $this->assertSame(BiolandHomeWidgetRegistry::bslKeys(), $constant);
  }

  /**
   * Tests the registry preserves the key order the form defaulted in.
   */
  public function testAllKeysPreserveTheFormDefaultingOrder(): void {
    $this->assertSame([
      'gbif_widget',
      'latest_news_widget',
      'national_targets_widget',
      'panorama_solutions_widget',
      'elearning_widget',
      'implementation_widget',
      'technical_cooperation_widget',
      'latest_discussions_widget',
      'content_statistics_widget',
      'geobon_widget',
      'nbf_widget',
      'bch_news_widget',
      'bch_resources_widget',
    ], BiolandHomeWidgetRegistry::allKeys());
  }

}
