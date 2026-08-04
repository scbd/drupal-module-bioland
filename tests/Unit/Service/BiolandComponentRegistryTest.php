<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandComponentRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BiolandComponentRegistry.
 *
 * The first test is a deliberate pinning/drift alarm: the component map
 * mirrors the frontend dispatcher in bioland-head
 * (app/components/page/header/mega-menu/custom/index.vue) and must only change
 * when that file does. Update it deliberately, together with the catalogs and
 * TranslationCatalogIntegrityTest::REQUIRED_MSGIDS.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandComponentRegistry
 * @group bioland
 */
class BiolandComponentRegistryTest extends TestCase {

  /**
   * The expected component map: suffix => [label, bsl].
   *
   * Order matters — it is the picker's display order.
   */
  private const EXPECTED_COMPONENTS = [
    'national-report' => ['National Reports', FALSE],
    'national-report-six' => ['National Report (6th)', FALSE],
    'bch' => ['BCH Records', TRUE],
    'absch' => ['ABS-CH Records', TRUE],
    'focal-points' => ['National Focal Points', FALSE],
    'country-profiles' => ['Country Profiles', FALSE],
    'content-type' => ['Content Type Listing', TRUE],
    'forums' => ['Forums', TRUE],
    'national-targets-7' => ['National Targets (GBF 7)', FALSE],
    'all-content-types' => ['All Content Types', TRUE],
  ];

  /**
   * The registry under test.
   *
   * @var \Drupal\bioland\Service\BiolandComponentRegistry
   */
  private $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registry = new BiolandComponentRegistry();
  }

  /**
   * Pins the component map: exactly these suffixes, labels and BSL flags.
   */
  public function testComponentMapIsPinned(): void {
    $components = $this->registry->getComponents();

    $this->assertCount(10, $components, 'The registry ships exactly 10 components.');
    $this->assertSame(
      array_keys(self::EXPECTED_COMPONENTS),
      array_keys($components),
      'Component suffixes and their display order are pinned.'
    );

    foreach (self::EXPECTED_COMPONENTS as $suffix => [$label, $isBsl]) {
      $this->assertSame($label, $components[$suffix]['label'], "Label pinned for $suffix.");
      $this->assertSame($isBsl, $components[$suffix]['bsl'], "BSL flag pinned for $suffix.");
    }
  }

  /**
   * Every component ships a non-empty, sentence-shaped description.
   */
  public function testEveryComponentHasADescription(): void {
    foreach ($this->registry->getComponents() as $suffix => $component) {
      $this->assertArrayHasKey('description', $component, "Description present for $suffix.");
      $this->assertNotSame('', $component['description'], "Description not empty for $suffix.");
      $this->assertStringEndsWith('.', $component['description'], "Description is a sentence for $suffix.");
    }
  }

  /**
   * BSL sites are offered only the narrowed subset.
   */
  public function testOptionsForBslSite(): void {
    $expected = [
      'bl2-component-bch' => 'BCH Records',
      'bl2-component-absch' => 'ABS-CH Records',
      'bl2-component-content-type' => 'Content Type Listing',
      'bl2-component-forums' => 'Forums',
      'bl2-component-all-content-types' => 'All Content Types',
    ];

    $this->assertSame($expected, $this->registry->optionsFor(TRUE));
  }

  /**
   * Bioland (CHM) sites are offered every component, in map order.
   */
  public function testOptionsForNonBslSite(): void {
    $expected = [];
    foreach (self::EXPECTED_COMPONENTS as $suffix => [$label, $isBsl]) {
      $expected['bl2-component-' . $suffix] = $label;
    }

    $this->assertSame($expected, $this->registry->optionsFor(FALSE));
  }

  /**
   * Descriptions resolve by suffix; unknown suffixes degrade to an empty string.
   */
  public function testGetDescription(): void {
    $this->assertSame(
      $this->registry->getComponents()['forums']['description'],
      $this->registry->getDescription('forums')
    );
    $this->assertSame('', $this->registry->getDescription('was-removed'));
    $this->assertSame('', $this->registry->getDescription(''));
    // A full token is not a suffix.
    $this->assertSame('', $this->registry->getDescription('bl2-component-forums'));
  }

  /**
   * The picker only ever writes the canonical spelling.
   */
  public function testCanonicalToken(): void {
    $this->assertSame('bl2-component-bch', $this->registry->canonicalToken('bch'));
    $this->assertSame('bl2-component-national-targets-7', $this->registry->canonicalToken('national-targets-7'));
  }

  /**
   * Tests the component-shaped and known predicates over all three families.
   *
   * @dataProvider tokenPredicateProvider
   */
  public function testTokenPredicates(string $token, ?string $siteId, bool $shaped, bool $known): void {
    $this->assertSame($shaped, $this->registry->isComponentToken($token, $siteId), "isComponentToken($token)");
    $this->assertSame($known, $this->registry->isKnownComponentToken($token, $siteId), "isKnownComponentToken($token)");
  }

  /**
   * Data provider for testTokenPredicates().
   */
  public function tokenPredicateProvider(): array {
    return [
      'canonical known' => ['bl2-component-bch', NULL, TRUE, TRUE],
      'legacy mm known' => ['mm-component-bch', NULL, TRUE, TRUE],
      'site family without site id' => ['xyz-component-bch', NULL, FALSE, FALSE],
      'site family with site id' => ['xyz-component-bch', 'xyz', TRUE, TRUE],
      'site family with other site id' => ['xyz-component-bch', 'bsl', FALSE, FALSE],
      'canonical still matches under a site id' => ['bl2-component-bch', 'bsl', TRUE, TRUE],
      'shaped but unknown suffix' => ['bl2-component-was-removed', NULL, TRUE, FALSE],
      'legacy shaped but unknown suffix' => ['mm-component-was-removed', NULL, TRUE, FALSE],
      'empty suffix is not a component' => ['bl2-component-', NULL, FALSE, FALSE],
      'content type token' => ['bl2-content-type-news', NULL, FALSE, FALSE],
      'scale token' => ['bl2-2x', NULL, FALSE, FALSE],
      'login marker' => ['login', NULL, FALSE, FALSE],
      'child menu binding' => ['cooperation', NULL, FALSE, FALSE],
      'empty token' => ['', NULL, FALSE, FALSE],
      'empty site id behaves like none' => ['xyz-component-bch', '', FALSE, FALSE],
    ];
  }

  /**
   * A site id colliding with a built-in prefix changes nothing.
   *
   * The registry's first caller (BiolandComponentMenuFormMode) derives the
   * site id from the site flavour, so it passes "bl2" on every Bioland site -
   * exactly the canonical prefix's own leading segment. tokenPrefixes()
   * deduplicates, so the predicates must answer identically to passing no site
   * id at all; the same holds for a site id colliding with the legacy prefix.
   */
  public function testCollidingSiteIdIsDeduplicated(): void {
    foreach (['bl2-component-bch', 'mm-component-bch', 'bl2-component-was-removed', 'xyz-component-bch'] as $token) {
      foreach (['bl2', 'mm'] as $siteId) {
        $this->assertSame(
          $this->registry->isComponentToken($token, NULL),
          $this->registry->isComponentToken($token, $siteId),
          "isComponentToken($token) must ignore the colliding site id $siteId"
        );
        $this->assertSame(
          $this->registry->isKnownComponentToken($token, NULL),
          $this->registry->isKnownComponentToken($token, $siteId),
          "isKnownComponentToken($token) must ignore the colliding site id $siteId"
        );
      }
    }
  }

  /**
   * Both storage shapes normalize to the same ordered token list.
   *
   * @dataProvider extractClassesProvider
   */
  public function testExtractClasses($classValue, array $expected): void {
    $this->assertSame($expected, $this->registry->extractClasses($classValue));
  }

  /**
   * Data provider for testExtractClasses().
   */
  public function extractClassesProvider(): array {
    return [
      'menu_link_attributes packed shape' => [
        ['login our-targets bl2-component-forums bl2-2x'],
        ['login', 'our-targets', 'bl2-component-forums', 'bl2-2x'],
      ],
      'true array shape' => [
        ['login', 'bl2-component-forums'],
        ['login', 'bl2-component-forums'],
      ],
      'plain string shape' => [
        'login bl2-component-forums',
        ['login', 'bl2-component-forums'],
      ],
      'collapses repeated and mixed whitespace' => [
        ["login  \t our-targets\nbl2-2x"],
        ['login', 'our-targets', 'bl2-2x'],
      ],
      'trims surrounding whitespace per element' => [
        ['  login  ', ' bl2-2x '],
        ['login', 'bl2-2x'],
      ],
      'empty array' => [[], []],
      'empty string' => ['', []],
      'whitespace only' => [['   '], []],
      'skips non-scalar members' => [
        [['nested'], 'login'],
        ['login'],
      ],
    ];
  }

  /**
   * Component tokens are found in stored order, across families.
   */
  public function testFindComponentTokens(): void {
    $this->assertSame(
      ['mm-component-bch', 'bl2-component-forums'],
      $this->registry->findComponentTokens(['login mm-component-bch bl2-2x bl2-component-forums'])
    );
    $this->assertSame(
      ['bl2-component-forums'],
      $this->registry->findComponentTokens(['login', 'bl2-component-forums'])
    );
    $this->assertSame([], $this->registry->findComponentTokens(['login our-targets bl2-2x']));
    // The site family only counts when the site id is supplied.
    $this->assertSame([], $this->registry->findComponentTokens(['bsl-component-bch']));
    $this->assertSame(['bsl-component-bch'], $this->registry->findComponentTokens(['bsl-component-bch'], 'bsl'));
  }

  /**
   * Stripping removes component tokens only, preserving the rest verbatim.
   */
  public function testStripComponentTokensPreservesEveryOtherToken(): void {
    $this->assertSame(
      ['login', 'our-targets', 'bl2-2x'],
      $this->registry->stripComponentTokens(['login our-targets bl2-component-forums bl2-2x']),
      'Packed shape: order and spelling of non-component tokens survive.'
    );
    $this->assertSame(
      ['login'],
      $this->registry->stripComponentTokens(['login', 'bl2-component-forums']),
      'Array shape: order and spelling of non-component tokens survive.'
    );
    $this->assertSame(
      ['Login', 'OUR-Targets', 'bl2-content-type-news', 'bl2-2x', 'mm-show-thumbs'],
      $this->registry->stripComponentTokens(
        ['Login OUR-Targets bl2-content-type-news bl2-component-bch bl2-2x mm-show-thumbs']
      ),
      'Casing and lookalike token families are untouched.'
    );
  }

  /**
   * A double-tokened link is cleaned of every component family at once.
   */
  public function testStripComponentTokensRemovesAllFamilies(): void {
    $this->assertSame(
      ['login', 'bl2-2x'],
      $this->registry->stripComponentTokens(['login mm-component-bch bl2-component-forums bl2-2x'])
    );
    $this->assertSame(
      ['login'],
      $this->registry->stripComponentTokens(
        ['login bsl-component-bch mm-component-forums bl2-component-absch'],
        'bsl'
      ),
      'All three families go, so a merge can never double-token a link.'
    );
    $this->assertSame(
      ['login'],
      $this->registry->stripComponentTokens(['login bl2-component-was-removed-x']),
      'A component-shaped token whose component no longer exists is stripped too.'
    );
  }

  /**
   * Merging returns the very shape it was handed.
   *
   * @dataProvider mergeShapeProvider
   */
  public function testMergeComponentTokenKeepsShape($classValue, string $token, ?string $siteId, $expected): void {
    $this->assertSame($expected, $this->registry->mergeComponentToken($classValue, $token, $siteId));
  }

  /**
   * Data provider for testMergeComponentTokenKeepsShape().
   */
  public function mergeShapeProvider(): array {
    return [
      'packed shape in, packed shape out' => [
        ['login our-targets bl2-component-forums bl2-2x'],
        'bl2-component-bch',
        NULL,
        ['login our-targets bl2-2x bl2-component-bch'],
      ],
      'true array in, true array out' => [
        ['login', 'bl2-component-forums'],
        'bl2-component-bch',
        NULL,
        ['login', 'bl2-component-bch'],
      ],
      'plain string in, plain string out' => [
        'login our-targets bl2-component-forums bl2-2x',
        'bl2-component-bch',
        NULL,
        'login our-targets bl2-2x bl2-component-bch',
      ],
      'empty array is the packed shape' => [
        [],
        'bl2-component-bch',
        NULL,
        ['bl2-component-bch'],
      ],
      'empty string stays a string' => [
        '',
        'bl2-component-bch',
        NULL,
        'bl2-component-bch',
      ],
      'double-tokened input is cleaned before appending' => [
        ['login mm-component-bch bl2-component-forums'],
        'bl2-component-absch',
        NULL,
        ['login bl2-component-absch'],
      ],
      'site family is cleaned when the site id is known' => [
        ['bsl-component-bch bl2-2x'],
        'bl2-component-forums',
        'bsl',
        ['bl2-2x bl2-component-forums'],
      ],
      'stale unknown token is replaced, not kept' => [
        ['login bl2-component-was-removed-x'],
        'bl2-component-bch',
        NULL,
        ['login bl2-component-bch'],
      ],
      'empty token clears the component' => [
        ['login bl2-component-forums bl2-2x'],
        '',
        NULL,
        ['login bl2-2x'],
      ],
      'whitespace token clears the component' => [
        ['login', 'bl2-component-forums'],
        '   ',
        NULL,
        ['login'],
      ],
    ];
  }

  /**
   * Merging the same token twice is a no-op the second time.
   */
  public function testMergeComponentTokenIsIdempotent(): void {
    $stored = ['login our-targets bl2-component-forums bl2-2x'];
    $once = $this->registry->mergeComponentToken($stored, 'bl2-component-bch');
    $twice = $this->registry->mergeComponentToken($once, 'bl2-component-bch');

    $this->assertSame($once, $twice);
    $this->assertSame(['login our-targets bl2-2x bl2-component-bch'], $twice);
  }

  /**
   * A round trip through the registry never disturbs unrelated tokens.
   */
  public function testMergeRoundTripPreservesNonComponentTokens(): void {
    $stored = ['login cooperation bl2-content-type-news bl2-2x mm-show-thumbs main-nav-sub-heading'];
    $merged = $this->registry->mergeComponentToken($stored, $this->registry->canonicalToken('bch'));

    $this->assertSame(
      ['login', 'cooperation', 'bl2-content-type-news', 'bl2-2x', 'mm-show-thumbs', 'main-nav-sub-heading'],
      $this->registry->stripComponentTokens($merged),
      'Every non-component token survives the round trip, in order and spelling.'
    );
    $this->assertSame(['bl2-component-bch'], $this->registry->findComponentTokens($merged));
  }

}
