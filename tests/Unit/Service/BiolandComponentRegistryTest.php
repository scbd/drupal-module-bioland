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
   * The expected component map: suffix => [label, bsl, thumbs].
   *
   * Order matters — it is the picker's display order. The thumbs flag mirrors
   * which Vue components read bl2-show-thumbs off their own link
   * (content-type/index.vue and all-content-types.vue only).
   */
  private const EXPECTED_COMPONENTS = [
    'national-report' => ['National Reports', FALSE, FALSE],
    'national-report-six' => ['National Report (6th)', FALSE, FALSE],
    'bch' => ['BCH Records', FALSE, FALSE],
    'absch' => ['ABS-CH Records', FALSE, FALSE],
    'focal-points' => ['National Focal Points', FALSE, FALSE],
    'country-profiles' => ['Country Profiles', FALSE, FALSE],
    'content-type' => ['Content Type', TRUE, TRUE],
    'forums' => ['Forums', FALSE, FALSE],
    'national-targets-7' => ['National Targets (GBF 7)', FALSE, FALSE],
    'all-content-types' => ['All Content Types', FALSE, TRUE],
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

    foreach (self::EXPECTED_COMPONENTS as $suffix => [$label, $isBsl, $thumbs]) {
      $this->assertSame($label, $components[$suffix]['label'], "Label pinned for $suffix.");
      $this->assertSame($isBsl, $components[$suffix]['bsl'], "BSL flag pinned for $suffix.");
      $this->assertSame($thumbs, $components[$suffix]['thumbs'], "Thumbs flag pinned for $suffix.");
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
   * BSL sites are offered only the Content Type Listing.
   *
   * Mirrors the BSL mega-menu settings form, which exposes only the Content
   * Type Menus section (BiolandMegaMenuForm returns early for everything
   * else).
   */
  public function testOptionsForBslSite(): void {
    $this->assertSame(
      ['bl2-component-content-type' => 'Content Type'],
      $this->registry->optionsFor(TRUE)
    );
  }

  /**
   * Bioland (CHM) sites are offered every component, in map order.
   */
  public function testOptionsForNonBslSite(): void {
    $expected = [];
    foreach (self::EXPECTED_COMPONENTS as $suffix => [$label, $isBsl, $thumbs]) {
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

  /* ------------------------------------------------------------------ */
  /* Content-type binding tokens.                                        */
  /* ------------------------------------------------------------------ */

  /**
   * Binding-token shape rules: single spelling, non-empty slug.
   */
  public function testContentTypeBindingTokenRules(): void {
    $this->assertSame('bl2-content-type-news', $this->registry->contentTypeBindingToken('news'));
    $this->assertTrue($this->registry->isContentTypeBindingToken('bl2-content-type-news'));
    $this->assertFalse($this->registry->isContentTypeBindingToken('bl2-content-type-'), 'An empty slug is not a binding.');
    $this->assertFalse($this->registry->isContentTypeBindingToken('bl2-component-content-type'), 'The component token is not a binding.');
    $this->assertFalse($this->registry->isContentTypeBindingToken('mm-content-type-news'), 'No legacy spelling exists for bindings.');
  }

  /**
   * Bindings are found in stored order, from either storage shape.
   */
  public function testFindContentTypeBindings(): void {
    $this->assertSame(
      ['news', 'event'],
      $this->registry->findContentTypeBindings(['bl2-component-content-type bl2-content-type-news login bl2-content-type-event'])
    );
    $this->assertSame([], $this->registry->findContentTypeBindings(['login bl2-component-forums']));
  }

  /**
   * Merging a binding replaces existing ones and keeps the storage shape.
   */
  public function testMergeContentTypeBinding(): void {
    $stored = ['login bl2-component-content-type bl2-content-type-news'];
    $this->assertSame(
      ['login bl2-component-content-type bl2-content-type-event'],
      $this->registry->mergeContentTypeBinding($stored, 'event'),
      'The component token and every other class survive; only the binding changes.'
    );
    $this->assertSame(
      ['login bl2-component-content-type'],
      $this->registry->mergeContentTypeBinding($stored, ''),
      'An empty slug unbinds.'
    );
    $this->assertSame(
      'login bl2-content-type-event',
      $this->registry->mergeContentTypeBinding('login bl2-content-type-news', 'event'),
      'A string value stays a string.'
    );
    $this->assertSame(
      ['login', 'bl2-content-type-event'],
      $this->registry->mergeContentTypeBinding(['login', 'bl2-content-type-news'], 'event'),
      'A multi-element array stays one token per element.'
    );
  }

  /* ------------------------------------------------------------------ */
  /* Style tokens: thumbnails, column width and the title arrow.         */
  /* ------------------------------------------------------------------ */

  /**
   * Thumbnail detection accepts both spellings; writing is canonical only.
   */
  public function testThumbsTokenRules(): void {
    $this->assertTrue($this->registry->hasThumbsToken(['login bl2-show-thumbs']));
    $this->assertTrue($this->registry->hasThumbsToken(['mm-show-thumbs']), 'The legacy spelling still counts when reading.');
    $this->assertFalse($this->registry->hasThumbsToken(['login bl2-component-forums']));

    $written = $this->registry->mergeStyleTokens(['login mm-show-thumbs'], TRUE, NULL, NULL);
    $this->assertSame(['login bl2-show-thumbs'], $written, 'Writing normalizes a legacy spelling to the canonical token.');
  }

  /**
   * The arrow token is unprefixed, and detection accepts both spellings.
   *
   * The bare "arrow" spelling is the frontend contract (bioland-head
   * header.vue), not an oversight - a "bl2-arrow" token would never match.
   */
  public function testArrowTokenRules(): void {
    $this->assertSame('arrow', BiolandComponentRegistry::ARROW_TOKEN);
    $this->assertSame('mm-arrow', BiolandComponentRegistry::LEGACY_ARROW_TOKEN);

    $this->assertTrue($this->registry->hasArrowToken(['login arrow bl2-2x']));
    $this->assertTrue($this->registry->hasArrowToken(['mm-arrow']), 'The legacy spelling still counts when reading.');
    $this->assertTrue($this->registry->hasArrowToken('arrow'), 'A plain string value is read too.');
    $this->assertFalse($this->registry->hasArrowToken(['login bl2-component-forums']));
    $this->assertFalse($this->registry->hasArrowToken(['arrows']), 'Detection is per token, never a substring match.');

    $written = $this->registry->mergeStyleTokens(['login mm-arrow'], NULL, NULL, TRUE);
    $this->assertSame(['login arrow'], $written, 'Writing normalizes a legacy spelling to the canonical token.');
  }

  /**
   * The first stored width token wins; none means the one-column default.
   */
  public function testFindWidthToken(): void {
    $this->assertSame('bl2-3x', $this->registry->findWidthToken(['login bl2-3x bl2-show-thumbs']));
    $this->assertSame('bl2-2x-xl', $this->registry->findWidthToken(['bl2-2x-xl']));
    $this->assertSame('', $this->registry->findWidthToken(['login cooperation']));
  }

  /**
   * Style merging: NULL leaves a family untouched, non-NULL owns it.
   */
  public function testMergeStyleTokens(): void {
    $stored = ['login bl2-component-content-type bl2-content-type-news bl2-2x bl2-show-thumbs'];

    $this->assertSame(
      ['login bl2-component-content-type bl2-content-type-news bl2-3x bl2-show-thumbs'],
      $this->registry->mergeStyleTokens($stored, TRUE, 'bl2-3x', NULL),
      'A new width replaces the old; every other token survives in place or is re-appended.'
    );
    $this->assertSame(
      ['login bl2-component-content-type bl2-content-type-news'],
      $this->registry->mergeStyleTokens($stored, FALSE, '', NULL),
      'FALSE thumbs and the empty width clear both families.'
    );
    $this->assertSame(
      ['login bl2-component-content-type bl2-content-type-news bl2-2x bl2-show-thumbs'],
      $this->registry->mergeStyleTokens($stored, NULL, NULL, NULL),
      'NULL controls leave the stored tokens byte-identical.'
    );
    $this->assertSame(
      ['login'],
      $this->registry->mergeStyleTokens(['login'], NULL, 'not-a-width', NULL),
      'An unknown width token is never written.'
    );
  }

  /**
   * The arrow control owns its family under the same NULL contract.
   */
  public function testMergeStyleTokensArrow(): void {
    $stored = ['login mm-arrow bl2-component-content-type bl2-2x'];

    $this->assertSame(
      ['login bl2-component-content-type bl2-2x arrow'],
      $this->registry->mergeStyleTokens($stored, NULL, NULL, TRUE),
      'TRUE strips the legacy spelling and appends the canonical token last.'
    );
    $this->assertSame(
      ['login bl2-component-content-type bl2-2x'],
      $this->registry->mergeStyleTokens($stored, NULL, NULL, FALSE),
      'FALSE clears the family, legacy spelling included.'
    );
    $this->assertSame(
      $stored,
      $this->registry->mergeStyleTokens($stored, NULL, NULL, NULL),
      'NULL leaves a stored arrow byte-identical, in place.'
    );
    $this->assertSame(
      ['login bl2-component-content-type arrow'],
      $this->registry->mergeStyleTokens(['login mm-arrow bl2-component-content-type bl2-2x'], NULL, '', TRUE),
      'The arrow and width families are cleared independently.'
    );

    // Shape preservation follows mergeComponentToken()'s rules.
    $this->assertSame(
      'login arrow',
      $this->registry->mergeStyleTokens('login', NULL, NULL, TRUE),
      'A string value stays a string.'
    );
    $this->assertSame(
      ['login', 'cooperation', 'arrow'],
      $this->registry->mergeStyleTokens(['login', 'cooperation'], NULL, NULL, TRUE),
      'A multi-element array stays one token per element.'
    );
  }

  /**
   * Thumbs support resolves per component, across every token spelling.
   */
  public function testComponentSupportsThumbs(): void {
    $this->assertSame(
      ['bl2-component-content-type', 'bl2-component-all-content-types'],
      $this->registry->thumbsSupportingTokens()
    );
    $this->assertTrue($this->registry->componentSupportsThumbs('bl2-component-content-type'));
    $this->assertTrue($this->registry->componentSupportsThumbs('mm-component-all-content-types'));
    $this->assertTrue($this->registry->componentSupportsThumbs('bsl-component-content-type', 'bsl'));
    $this->assertFalse($this->registry->componentSupportsThumbs('bl2-component-forums'));
    $this->assertFalse($this->registry->componentSupportsThumbs('bl2-component-was-removed'));
  }

  /**
   * The rows-cap token round-trips and only digits are ever written.
   */
  public function testMaxRowsTokenRules(): void {
    $stored = ['login bl2-component-content-type bl2-ct-max-row-per-column-4'];

    $this->assertSame('4', $this->registry->findMaxRowsValue($stored));
    $this->assertSame('', $this->registry->findMaxRowsValue(['login']));

    $this->assertSame(
      ['login bl2-component-content-type bl2-ct-max-row-per-column-6'],
      $this->registry->mergeMaxRows($stored, '6')
    );
    $this->assertSame(
      ['login bl2-component-content-type'],
      $this->registry->mergeMaxRows($stored, ''),
      'The empty value clears the cap back to the site default.'
    );
    $this->assertSame($stored, $this->registry->mergeMaxRows($stored, NULL), 'NULL leaves the family untouched.');
    $this->assertSame(
      ['login bl2-component-content-type'],
      $this->registry->mergeMaxRows($stored, '6; DROP'),
      'A non-digit value is never written.'
    );
  }

}
