<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\bioland\Service\BiolandComponentMenuOverview;
use Drupal\bioland\Service\BiolandComponentRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the menu overview screen additions.
 *
 * The fixture below reproduces core MenuForm::buildOverviewForm()'s table
 * exactly as Drupal 11.4.4 and 10.5.x build it (the two are byte-identical in
 * that region), including the pending-revision variant where core drops the
 * weight column by unsetting '#header'[2] WITHOUT reindexing. Both shapes are
 * exercised, because a column added to one and not the other is precisely the
 * bug that breaks tabledrag.
 *
 * ALIGNMENT IS THE LOAD-BEARING PROPERTY. Every test that adds the column also
 * asserts that the header's total colspan still equals every row's cell count.
 * Core's tabledrag reads the hidden id/parent cells by position, so one stray
 * cell stops an editor reordering the whole menu.
 *
 * @covers \Drupal\bioland\Service\BiolandComponentMenuOverview
 */
class BiolandComponentMenuOverviewTest extends TestCase {

  /**
   * A canonical component token with no content-type binding.
   */
  private const FORUMS = 'bl2-component-forums';

  /**
   * The Content Type Listing component token.
   */
  private const CONTENT_TYPE = 'bl2-component-content-type';

  /**
   * The registry under the service (a real instance; it is pure).
   *
   * @var \Drupal\bioland\Service\BiolandComponentRegistry
   */
  protected $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registry = new BiolandComponentRegistry();
    Url::reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    Url::reset();
    parent::tearDown();
  }

  /**
   * Builds the service for a site flavour.
   *
   * @param bool $isBsl
   *   TRUE to run as a Biosafety Land site.
   * @param bool $withDestination
   *   FALSE to leave the redirect destination unwired.
   */
  protected function createService(bool $isBsl = FALSE, bool $withDestination = TRUE): BiolandComponentMenuOverview {
    $config = new ImmutableConfig('bioland.settings', ['is_biosafety_land' => $isBsl]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('bioland.settings')->willReturn($config);

    $formMode = new BiolandComponentMenuFormMode(
      $this->registry,
      $configFactory,
      $this->createMock(AccountProxyInterface::class)
    );

    $destination = NULL;
    if ($withDestination) {
      $destination = $this->createMock(RedirectDestinationInterface::class);
      $destination->method('getAsArray')->willReturn(['destination' => '/admin/structure/menu/manage/main']);
    }

    return new BiolandComponentMenuOverview($this->registry, $formMode, $destination);
  }

  /**
   * Builds a core-shaped menu overview form.
   *
   * @param array $rows
   *   Plugin id => the link's stored options.attributes.class value, or NULL
   *   for a link with no class attribute at all.
   * @param bool $pending
   *   TRUE for core's pending-revision shape: no weight column, and a sparse
   *   header with index 2 unset.
   * @param bool $addChild
   *   FALSE to omit core's "Add child" operation, as core does at the deepest
   *   allowed level.
   *
   * @return array
   *   The form array.
   */
  protected function coreForm(array $rows, bool $pending = FALSE, bool $addChild = TRUE): array {
    $header = [
      'Menu link',
      ['data' => 'Enabled', 'class' => ['checkbox']],
      'Weight',
      ['data' => 'Operations', 'colspan' => 3],
    ];
    if ($pending) {
      unset($header[2]);
    }

    $table = [
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => $header,
      '#attributes' => ['id' => 'menu-overview'],
      '#empty' => 'There are no menu links yet.',
    ];

    foreach ($rows as $pluginId => $class) {
      $options = $class === NULL ? [] : ['attributes' => ['class' => $class]];
      $operations = ['edit' => ['title' => 'Edit', 'query' => ['destination' => '/d']]];
      if ($addChild) {
        $operations['add-child'] = ['title' => 'Add child', 'query' => ['destination' => '/d']];
      }
      $operations['delete'] = ['title' => 'Delete', 'query' => ['destination' => '/d']];

      $row = [
        '#item' => new OverviewTreeElement($pluginId, $options),
        '#attributes' => ['class' => ['menu-enabled', 'draggable']],
        '#weight' => 0,
        'title' => [['#theme' => 'indentation', '#size' => 0], ['#type' => 'link']],
        'enabled' => ['#type' => 'checkbox', '#default_value' => TRUE],
        'weight' => ['#type' => 'weight', '#default_value' => 0],
        'operations' => ['#type' => 'operations', '#links' => $operations],
        'id' => ['#type' => 'hidden', '#value' => $pluginId],
        'parent' => ['#type' => 'hidden', '#default_value' => ''],
      ];
      if ($pending) {
        unset($row['weight']);
      }

      $table['menu_plugin_id:' . $pluginId] = $row;
    }

    return ['links' => $table, 'actions' => ['submit' => ['#type' => 'submit']]];
  }

  /**
   * Builds a form state over a menu entity.
   */
  protected function formState(string $menuId = 'main'): FormStateInterface {
    return new OverviewFormState(new OverviewFormObject(new OverviewMenu($menuId)));
  }

  /**
   * Runs the service over a form and returns the altered form.
   */
  protected function alter(array $form, ?BiolandComponentMenuOverview $service = NULL, string $menuId = 'main'): array {
    ($service ?? $this->createService())->alterOverviewForm($form, $this->formState($menuId));

    return $form;
  }

  /**
   * Returns the number of columns a header spans, colspans included.
   */
  protected function headerColumns(array $header): int {
    $columns = 0;
    foreach ($header as $cell) {
      $columns += is_array($cell) ? (int) ($cell['colspan'] ?? 1) : 1;
    }

    return $columns;
  }

  /**
   * Returns a render element's child keys, the way the table renderer reads it.
   */
  protected function childKeys(array $element): array {
    $keys = [];
    foreach ($element as $key => $value) {
      if (is_int($key) || $key === '' || $key[0] !== '#') {
        $keys[] = $key;
      }
    }

    return $keys;
  }

  /**
   * Asserts that every row has exactly as many cells as the header has columns.
   *
   * The property that keeps tabledrag working.
   */
  protected function assertTableAligned(array $table): void {
    $columns = $this->headerColumns($table['#header']);
    foreach ($this->childKeys($table) as $key) {
      $this->assertCount(
        $columns,
        $this->childKeys($table[$key]),
        sprintf('Row %s must have one cell per header column.', $key)
      );
    }
  }

  /**
   * Returns a row's indicator cell markup, as a string.
   *
   * The cell carries '#markup' holding whatever t() returned - in production a
   * TranslatableMarkup, which is the point (see the double-escape tests below).
   */
  protected function indicator(array $table, string $pluginId): string {
    return (string) $table['menu_plugin_id:' . $pluginId][BiolandComponentMenuOverview::INDICATOR_ELEMENT]['#markup'];
  }

  /**
   * The indicator column lands immediately before Enabled.
   */
  public function testTheColumnIsInsertedBeforeEnabled(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));
    $header = $form['links']['#header'];

    $this->assertSame(
      ['Menu link', 'Mega Menu', 'Enabled', 'Weight', 'Operations'],
      array_map(static fn ($cell) => is_array($cell) ? $cell['data'] : $cell, $header),
      'The new column must sit between Menu link and Enabled.'
    );
    $this->assertSame(
      [BiolandComponentMenuOverview::INDICATOR_CLASS],
      $header[1]['class'],
      'The header cell carries the styling class, never an inline style.'
    );
  }

  /**
   * The row cell lands at the same position as the header cell.
   */
  public function testTheCellIsInsertedBeforeEnabled(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));

    $this->assertSame(
      ['title', BiolandComponentMenuOverview::INDICATOR_ELEMENT, 'enabled', 'weight', 'operations', 'id', 'parent'],
      $this->childKeys($form['links']['menu_plugin_id:a']),
      'Cells render in array order, so the child must be physically spliced in.'
    );
  }

  /**
   * Header and rows stay aligned in the ordinary shape.
   */
  public function testTheTableStaysAligned(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS], 'b' => NULL, 'c' => ['nav-item']]));

    $this->assertSame(7, $this->headerColumns($form['links']['#header']), 'Six core columns plus one.');
    $this->assertTableAligned($form['links']);
  }

  /**
   * Header and rows stay aligned in core's pending-revision shape.
   *
   * There core drops the weight column and leaves the header keys sparse
   * (0, 1, 3), which is the case a naive array_splice gets wrong.
   */
  public function testTheTableStaysAlignedWithPendingRevisions(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS], 'b' => NULL], TRUE));

    $this->assertSame(
      ['Menu link', 'Mega Menu', 'Enabled', 'Operations'],
      array_map(static fn ($cell) => is_array($cell) ? $cell['data'] : $cell, $form['links']['#header'])
    );
    $this->assertSame(6, $this->headerColumns($form['links']['#header']), 'Five core columns plus one.');
    $this->assertTableAligned($form['links']);
  }

  /**
   * A component link is named by its human label.
   */
  public function testAComponentRowNamesItsType(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));

    $this->assertSame('(Mega Menu: Forums)', $this->indicator($form['links'], 'a'));
  }

  /**
   * A bound Content Type Listing also names the content type.
   */
  public function testABoundContentTypeRowNamesItsSchema(): void {
    $form = $this->alter($this->coreForm([
      'a' => [self::CONTENT_TYPE, 'bl2-content-type-press-release'],
    ]));

    $this->assertSame('(Mega Menu: Content Type: press-release)', $this->indicator($form['links'], 'a'));
  }

  /**
   * The packed menu_link_attributes storage shape reads the same.
   *
   * That module stores every class in one space-separated string inside a
   * one-element array, which is how most real links are stored.
   */
  public function testThePackedStorageShapeIsRead(): void {
    $form = $this->alter($this->coreForm([
      'a' => ['nav-item ' . self::CONTENT_TYPE . ' bl2-content-type-news bl2-2x'],
    ]));

    $this->assertSame('(Mega Menu: Content Type: news)', $this->indicator($form['links'], 'a'));
  }

  /**
   * An unbound Content Type Listing names the type only.
   */
  public function testAnUnboundContentTypeRowNamesTheTypeOnly(): void {
    $form = $this->alter($this->coreForm(['a' => [self::CONTENT_TYPE]]));

    $this->assertSame('(Mega Menu: Content Type)', $this->indicator($form['links'], 'a'));
  }

  /**
   * Only the Content Type Listing reports a schema.
   *
   * A stray binding on another component describes nothing that component
   * renders, so it must not be reported as if it did.
   */
  public function testOnlyTheContentTypeComponentReportsASchema(): void {
    $form = $this->alter($this->coreForm([
      'a' => [self::FORUMS, 'bl2-content-type-news'],
    ]));

    $this->assertSame('(Mega Menu: Forums)', $this->indicator($form['links'], 'a'));
  }

  /**
   * A non-component row gets an empty cell, not a missing one.
   */
  public function testANonComponentRowGetsAnEmptyCell(): void {
    $form = $this->alter($this->coreForm(['a' => ['nav-item', 'bl2-2x'], 'b' => NULL]));

    $this->assertSame('', $this->indicator($form['links'], 'a'));
    $this->assertSame('', $this->indicator($form['links'], 'b'));
    $this->assertTableAligned($form['links']);
  }

  /**
   * Every cell carries the styling class, empty ones included.
   */
  public function testEveryCellCarriesTheStylingClass(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS], 'b' => NULL]));

    foreach (['a', 'b'] as $id) {
      $cell = $form['links']['menu_plugin_id:' . $id][BiolandComponentMenuOverview::INDICATOR_ELEMENT];
      $this->assertSame(
        ['class' => [BiolandComponentMenuOverview::INDICATOR_CLASS]],
        $cell['#wrapper_attributes'],
        'The class goes on the <td> via #wrapper_attributes; no inline style.'
      );
    }
  }

  /**
   * The cell carries what t() returned, never a '#plain_text' copy of it.
   *
   * The escaping contract, pinned structurally. t() has already escaped its
   * @-placeholder - the only attacker-influenced part - and returns a
   * MarkupInterface saying so, which Renderer::xssFilterAdminIfUnsafe() honours
   * for a '#markup' value. A '#plain_text' value is escaped again by the
   * renderer unconditionally, so "AT&T" would reach the screen as "AT&amp;T".
   */
  public function testTheCellCarriesMarkupAndNotPlainText(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS], 'b' => NULL]));

    foreach (['a', 'b'] as $id) {
      $cell = $form['links']['menu_plugin_id:' . $id][BiolandComponentMenuOverview::INDICATOR_ELEMENT];
      $this->assertArrayHasKey('#markup', $cell);
      $this->assertArrayNotHasKey(
        '#plain_text',
        $cell,
        '#plain_text would escape t() output a second time.'
      );
    }
  }

  /**
   * A hostile suffix is escaped exactly once, never twice.
   *
   * The suffix is read straight off a menu link's class attribute, which an
   * editor with "use menu link attributes" controls, and a stale token reaches
   * the cell verbatim (see testAStaleTokenFallsBackToItsSuffix). So the cell is
   * where escaping has to be right: once means safe, twice means the editor
   * reads "&lt;script&gt;" as literal text instead of the token they typed.
   */
  public function testAHostileSuffixIsEscapedExactlyOnce(): void {
    $form = $this->alter($this->coreForm(['a' => ['bl2-component-<script>alert(1)</script>']]));
    $markup = $this->indicator($form['links'], 'a');

    $this->assertStringContainsString('&lt;script&gt;', $markup, 'The tag must be escaped.');
    $this->assertStringNotContainsString('<script>', $markup, 'And never survive raw.');
    $this->assertStringNotContainsString('&amp;lt;', $markup, 'Escaped once, not twice.');
    $this->assertSame(
      1,
      substr_count($markup, '&lt;script&gt;'),
      'Exactly one escaping pass over the opening tag.'
    );
    $this->assertSame(
      '(Mega Menu: &lt;script&gt;alert(1)&lt;/script&gt;)',
      $markup
    );
    $this->assertSame(
      $markup,
      $this->createService()->indicatorText($form['links']['menu_plugin_id:a']['#item']),
      'The string face of the same value agrees with the cell.'
    );
  }

  /**
   * A legacy token spelling is labelled like the canonical one.
   */
  public function testALegacyTokenIsLabelled(): void {
    $form = $this->alter($this->coreForm(['a' => ['mm-component-bch']]));

    $this->assertSame('(Mega Menu: BCH Records)', $this->indicator($form['links'], 'a'));
  }

  /**
   * A site-prefixed token is labelled on the site whose prefix it carries.
   */
  public function testASitePrefixedTokenIsLabelledOnThatSite(): void {
    $form = $this->alter(
      $this->coreForm(['a' => ['bsl-component-content-type']]),
      $this->createService(TRUE)
    );

    $this->assertSame('(Mega Menu: Content Type)', $this->indicator($form['links'], 'a'));
  }

  /**
   * A component the BSL narrowing hides is still labelled.
   *
   * The column reports what a link RENDERS, which does not depend on what the
   * picker currently offers this site.
   */
  public function testABslHiddenComponentIsStillLabelled(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]), $this->createService(TRUE));

    $this->assertSame('(Mega Menu: Forums)', $this->indicator($form['links'], 'a'));
  }

  /**
   * A stale token falls back to its bare suffix rather than an empty cell.
   */
  public function testAStaleTokenFallsBackToItsSuffix(): void {
    $form = $this->alter($this->coreForm(['a' => ['bl2-component-retired-thing']]));

    $this->assertSame('(Mega Menu: retired-thing)', $this->indicator($form['links'], 'a'));
  }

  /**
   * The library is attached once the column has landed.
   */
  public function testTheLibraryIsAttached(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));

    $this->assertContains(BiolandComponentMenuOverview::LIBRARY, $form['#attached']['library']);
  }

  /**
   * The operation is added immediately after core's "Add child".
   */
  public function testTheOperationFollowsAddChild(): void {
    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));
    $links = $form['links']['menu_plugin_id:a']['operations']['#links'];

    $this->assertSame(
      ['edit', 'add-child', BiolandComponentMenuOverview::OPERATION_KEY, 'delete'],
      array_keys($links)
    );
    $this->assertSame('Add Mega Menu Child', $links[BiolandComponentMenuOverview::OPERATION_KEY]['title']);
  }

  /**
   * The operation points at the add route, for this menu, with this parent.
   */
  public function testTheOperationTargetsTheAddRouteWithTheRowsParent(): void {
    $form = $this->alter($this->coreForm(['aaaa-1111' => NULL]), NULL, 'bioland-mega-menu');
    $operation = $form['links']['menu_plugin_id:aaaa-1111']['operations']['#links'][BiolandComponentMenuOverview::OPERATION_KEY];
    $url = $operation['url'];

    $this->assertSame(BiolandComponentMenuOverview::ADD_ROUTE, $url->getRouteName());
    $this->assertSame(['menu' => 'bioland-mega-menu'], $url->getRouteParameters());
    $this->assertSame(['query' => ['parent' => 'aaaa-1111']], $url->getOptions());
    $this->assertSame(['destination' => '/admin/structure/menu/manage/main'], $operation['query']);
  }

  /**
   * Each row preselects its own link as the parent.
   */
  public function testEachRowCarriesItsOwnParent(): void {
    $form = $this->alter($this->coreForm(['a' => NULL, 'b' => [self::FORUMS]]));

    foreach (['a', 'b'] as $id) {
      $url = $form['links']['menu_plugin_id:' . $id]['operations']['#links'][BiolandComponentMenuOverview::OPERATION_KEY]['url'];
      $this->assertSame(['query' => ['parent' => $id]], $url->getOptions());
    }
  }

  /**
   * Without "Add child" no operation is added at all.
   *
   * Core withholds "Add child" at the deepest allowed level, where the row
   * cannot take a child. Offering ours there would open a form whose parent
   * select cannot hold the requested parent, so it would fall back to the menu
   * root and the new link would land somewhere the editor never asked for.
   */
  public function testNoOperationIsAddedWhenAddChildIsAbsent(): void {
    $form = $this->alter($this->coreForm(['a' => NULL], FALSE, FALSE));

    $this->assertSame(
      ['edit', 'delete'],
      array_keys($form['links']['menu_plugin_id:a']['operations']['#links'])
    );
  }

  /**
   * A second alter pass changes nothing.
   *
   * Nothing promises an alter runs once per form array. A second splice would
   * put eight header cells against six row cells, and a header that out-counts
   * its rows is exactly the tabledrag breakage the all-or-nothing rule exists
   * to prevent.
   */
  public function testASecondAlterPassIsANoOp(): void {
    $service = $this->createService();
    $form = $this->coreForm(['a' => [self::FORUMS], 'b' => NULL]);

    $service->alterOverviewForm($form, $this->formState());
    $once = $form['links'];

    $service->alterOverviewForm($form, $this->formState());

    $this->assertSame($once, $form['links'], 'The table must be byte-identical after a second pass.');
    $this->assertTableAligned($form['links']);
    $this->assertSame(
      [BiolandComponentMenuOverview::LIBRARY],
      $form['#attached']['library'],
      'And the library is attached once, not twice.'
    );
  }

  /**
   * Without a destination service the operation still works, minus the return.
   */
  public function testTheOperationOmitsTheDestinationWhenUnwired(): void {
    $form = $this->alter($this->coreForm(['a' => NULL]), $this->createService(FALSE, FALSE));
    $operation = $form['links']['menu_plugin_id:a']['operations']['#links'][BiolandComponentMenuOverview::OPERATION_KEY];

    $this->assertArrayNotHasKey('query', $operation);
    $this->assertSame(BiolandComponentMenuOverview::ADD_ROUTE, $operation['url']->getRouteName());
  }

  /**
   * The operation is gated on the add route's own access decision.
   *
   * Denied means no operation anywhere - and the column is unaffected, since
   * naming what a link renders is not the same permission as creating one.
   */
  public function testTheOperationIsOmittedWhenAccessIsDenied(): void {
    Url::$accessResult = FALSE;

    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));
    $links = $form['links']['menu_plugin_id:a']['operations']['#links'];

    $this->assertSame(['edit', 'add-child', 'delete'], array_keys($links));
    $this->assertSame('(Mega Menu: Forums)', $this->indicator($form['links'], 'a'));
  }

  /**
   * Access is asked of the route, never hand-rolled from config.
   */
  public function testAccessIsCheckedAgainstTheAddRoute(): void {
    $this->alter($this->coreForm(['a' => NULL]), NULL, 'footer');

    $this->assertSame(
      ['route' => BiolandComponentMenuOverview::ADD_ROUTE, 'parameters' => ['menu' => 'footer'], 'options' => []],
      Url::$created[0],
      'The first Url built must be the bare access probe for this menu.'
    );
  }

  /**
   * Access is checked once for the whole table, not once per row.
   */
  public function testAccessIsCheckedOncePerTable(): void {
    $this->alter($this->coreForm(['a' => NULL, 'b' => NULL, 'c' => NULL]));

    $probes = array_filter(Url::$created, static fn ($url) => $url['options'] === []);
    $this->assertCount(1, $probes, 'One access probe, plus one operation Url per row.');
    $this->assertCount(4, Url::$created);
  }

  /**
   * The access decision's cache metadata is folded into the form.
   */
  public function testTheAccessCacheabilityReachesTheForm(): void {
    Url::$accessResult = AccessResult::allowed()
      ->addCacheableDependency(new OverviewCacheableDependency(['config:bioland.settings']));

    $form = $this->alter($this->coreForm(['a' => NULL]));

    $this->assertContains('config:bioland.settings', $form['#cache']['tags']);
  }

  /**
   * A denied decision still contributes its cache metadata.
   *
   * Otherwise turning the feature on would not refresh a cached screen.
   */
  public function testADeniedDecisionStillContributesCacheability(): void {
    Url::$accessResult = AccessResult::forbidden()
      ->addCacheableDependency(new OverviewCacheableDependency(['config:bioland.settings']));

    $form = $this->alter($this->coreForm(['a' => NULL]));

    $this->assertContains('config:bioland.settings', $form['#cache']['tags']);
  }

  /**
   * The indicator column declares its own cacheability.
   *
   * Exercised with the operation DENIED, so the row-operation feature
   * contributes nothing: the column's labels are narrowed by bioland.settings
   * through siteId(), and it must not rely on the other feature's access probe
   * happening to carry the same tag.
   */
  public function testTheIndicatorColumnTagsTheSettingsItReads(): void {
    Url::$accessResult = AccessResult::forbidden();

    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));

    $this->assertSame(
      ['edit', 'add-child', 'delete'],
      array_keys($form['links']['menu_plugin_id:a']['operations']['#links']),
      'The other feature must contribute nothing here.'
    );
    $this->assertSame([BiolandComponentMenuOverview::SETTINGS_CACHE_TAG], $form['#cache']['tags']);
    $this->assertSame('config:bioland.settings', BiolandComponentMenuOverview::SETTINGS_CACHE_TAG);
  }

  /**
   * A column that never landed tags nothing.
   */
  public function testNoColumnMeansNoSettingsTag(): void {
    Url::$accessResult = AccessResult::forbidden();

    $form = $this->coreForm(['a' => [self::FORUMS]]);
    unset($form['links']['#header']);

    $altered = $this->alter($form);

    $this->assertArrayNotHasKey('#cache', $altered);
  }

  /**
   * The access decision's cache CONTEXTS are merged, not just its tags.
   */
  public function testTheAccessCacheContextsAreMerged(): void {
    Url::$accessResult = AccessResult::allowed()->addCacheContexts(['user.permissions']);

    $form = $this->coreForm(['a' => NULL]);
    $form['#cache']['contexts'] = ['user.roles:authenticated'];
    $form = $this->alter($form);

    $this->assertSame(
      ['user.roles:authenticated', 'user.permissions'],
      $form['#cache']['contexts'],
      'The form keeps the context it had and gains the decision\'s.'
    );
  }

  /**
   * A permanent decision writes no max-age at all.
   *
   * Cache::PERMANENT is the absence of a constraint; writing -1 onto a form
   * that never had a max-age would state one where there is none.
   */
  public function testAPermanentDecisionWritesNoMaxAge(): void {
    Url::$accessResult = AccessResult::allowed()->setCacheMaxAge(-1);

    $form = $this->alter($this->coreForm(['a' => [self::FORUMS]]));

    $this->assertArrayNotHasKey('max-age', $form['#cache']);
  }

  /**
   * Between two real max-ages the shorter one wins.
   *
   * Only ever shortens. Core's CSRF guard has already put a max-age of 0 on
   * this form before the alter runs, so nothing written here can lengthen it -
   * a merge that overwrote instead of min()-ing would disable that guard.
   */
  public function testTheShorterMaxAgeWins(): void {
    Url::$accessResult = AccessResult::allowed()->setCacheMaxAge(300);

    $form = $this->coreForm(['a' => NULL]);
    $form['#cache']['max-age'] = 60;
    $form = $this->alter($form);

    $this->assertSame(60, $form['#cache']['max-age'], 'The form already had the shorter one.');

    // The other direction, so the assertion above cannot pass merely because
    // the merge was skipped and the existing value left alone.
    Url::reset();
    Url::$accessResult = AccessResult::allowed()->setCacheMaxAge(300);

    $form = $this->coreForm(['a' => NULL]);
    $form['#cache']['max-age'] = 600;
    $form = $this->alter($form);

    $this->assertSame(300, $form['#cache']['max-age'], 'The decision brought the shorter one.');
  }

  /**
   * A decision's max-age lands on a form that had none.
   */
  public function testARealMaxAgeReachesAFormWithoutOne(): void {
    Url::$accessResult = AccessResult::allowed()->setCacheMaxAge(300);

    $form = $this->alter($this->coreForm(['a' => NULL]));

    $this->assertSame(300, $form['#cache']['max-age']);
  }

  /**
   * Existing cache tags on the form are kept, not replaced.
   */
  public function testExistingCacheTagsSurvive(): void {
    Url::$accessResult = AccessResult::allowed()
      ->addCacheableDependency(new OverviewCacheableDependency(['config:bioland.settings']));

    $form = $this->coreForm(['a' => NULL]);
    $form['#cache']['tags'] = ['config:system.menu.main'];
    $form = $this->alter($form);

    $this->assertSame(['config:system.menu.main', 'config:bioland.settings'], $form['#cache']['tags']);
  }

  /**
   * A form with no links table is left byte-identical.
   *
   * That is the menu ADD form, where core never builds the overview.
   */
  public function testAFormWithoutTheTableIsUntouched(): void {
    $form = ['label' => ['#type' => 'textfield'], 'actions' => []];

    $this->assertSame($form, $this->alter($form));
  }

  /**
   * An empty table is left byte-identical.
   */
  public function testAnEmptyTableIsUntouched(): void {
    $form = $this->coreForm([]);

    $this->assertSame($form, $this->alter($form));
  }

  /**
   * A table with no header is left byte-identical.
   */
  public function testATableWithoutAHeaderAddsNoColumn(): void {
    $form = $this->coreForm(['a' => [self::FORUMS]]);
    unset($form['links']['#header']);

    $altered = $this->alter($form);

    $this->assertArrayNotHasKey('#header', $altered['links'], 'No header may be invented.');
    $this->assertArrayNotHasKey(
      BiolandComponentMenuOverview::INDICATOR_ELEMENT,
      $altered['links']['menu_plugin_id:a'],
      'A cell with no header column to sit under would misalign the table.'
    );
    $this->assertNotContains(
      BiolandComponentMenuOverview::LIBRARY,
      $altered['#attached']['library'] ?? [],
      'No stylesheet for a column that was never added.'
    );
  }

  /**
   * A row missing the Enabled cell aborts the whole column.
   *
   * Adding it to the other rows would misalign the table, which breaks
   * tabledrag for the entire menu - strictly worse than showing no column.
   */
  public function testARowWithoutEnabledAbortsTheColumn(): void {
    $form = $this->coreForm(['a' => [self::FORUMS], 'b' => NULL]);
    unset($form['links']['menu_plugin_id:b']['enabled']);

    $altered = $this->alter($form);

    $this->assertSame($form['links']['#header'], $altered['links']['#header']);
    $this->assertArrayNotHasKey(
      BiolandComponentMenuOverview::INDICATOR_ELEMENT,
      $altered['links']['menu_plugin_id:a'],
      'No row may gain a cell when any row cannot.'
    );
  }

  /**
   * Rows that disagree on where Enabled sits abort the column.
   */
  public function testRowsDisagreeingOnTheColumnAbortIt(): void {
    $form = $this->coreForm(['a' => [self::FORUMS], 'b' => NULL]);
    // Re-order one row so 'enabled' moves to a different column.
    $row = $form['links']['menu_plugin_id:b'];
    $form['links']['menu_plugin_id:b'] = [
      '#item' => $row['#item'],
      'enabled' => $row['enabled'],
      'title' => $row['title'],
      'weight' => $row['weight'],
      'operations' => $row['operations'],
      'id' => $row['id'],
      'parent' => $row['parent'],
    ];

    $altered = $this->alter($form);

    $this->assertArrayNotHasKey(
      BiolandComponentMenuOverview::INDICATOR_ELEMENT,
      $altered['links']['menu_plugin_id:a']
    );
  }

  /**
   * A header whose colspans straddle the Enabled column aborts it.
   *
   * There is no honest place to insert a cell inside a spanning header cell,
   * so the column is dropped rather than guessed at.
   */
  public function testAStraddlingHeaderAbortsTheColumn(): void {
    $form = $this->coreForm(['a' => [self::FORUMS]]);
    $form['links']['#header'] = [
      ['data' => 'Menu link and Enabled', 'colspan' => 2],
      'Weight',
      ['data' => 'Operations', 'colspan' => 3],
    ];
    $before = $form['links']['#header'];

    $altered = $this->alter($form);

    $this->assertSame($before, $altered['links']['#header']);
    $this->assertArrayNotHasKey(
      BiolandComponentMenuOverview::INDICATOR_ELEMENT,
      $altered['links']['menu_plugin_id:a']
    );
  }

  /**
   * A row with no tree element keeps its operations untouched.
   */
  public function testARowWithoutATreeElementGetsNoOperation(): void {
    $form = $this->coreForm(['a' => NULL]);
    unset($form['links']['menu_plugin_id:a']['#item']);

    $altered = $this->alter($form);

    $this->assertSame(
      ['edit', 'add-child', 'delete'],
      array_keys($altered['links']['menu_plugin_id:a']['operations']['#links'])
    );
    $this->assertSame('', $this->indicator($altered['links'], 'a'), 'And its indicator cell is empty.');
  }

  /**
   * A form state with no menu entity adds no operation, but keeps the column.
   */
  public function testAFormStateWithoutAMenuAddsNoOperation(): void {
    $form = $this->coreForm(['a' => [self::FORUMS]]);
    $this->createService()->alterOverviewForm($form, new OverviewFormState(NULL));

    $this->assertSame('(Mega Menu: Forums)', $this->indicator($form['links'], 'a'));
    $this->assertSame(
      ['edit', 'add-child', 'delete'],
      array_keys($form['links']['menu_plugin_id:a']['operations']['#links'])
    );
  }

  /**
   * A link whose plugin cannot report its options is treated as plain.
   */
  public function testALinkWithoutReadableOptionsIsTreatedAsPlain(): void {
    $form = $this->coreForm(['a' => NULL]);
    $form['links']['menu_plugin_id:a']['#item'] = new OverviewTreeElement('a', 'not-an-array');

    $altered = $this->alter($form);

    $this->assertSame('', $this->indicator($altered['links'], 'a'));
    $this->assertTableAligned($altered['links']);
  }

}

/**
 * Menu link tree element double: an object with a ->link plugin.
 */
class OverviewTreeElement {

  /**
   * The link plugin double.
   *
   * @var \Drupal\Tests\bioland\Unit\Service\OverviewLink
   */
  public $link;

  /**
   * Constructs the tree element.
   *
   * @param string $pluginId
   *   The link plugin id.
   * @param mixed $options
   *   The link options.
   */
  public function __construct(string $pluginId, $options) {
    $this->link = new OverviewLink($pluginId, $options);
  }

}

/**
 * Menu link plugin double exposing the two methods the service reads.
 */
class OverviewLink {

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The link options.
   *
   * @var mixed
   */
  protected $options;

  /**
   * Constructs the link.
   *
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $options
   *   The link options.
   */
  public function __construct(string $pluginId, $options) {
    $this->pluginId = $pluginId;
    $this->options = $options;
  }

  /**
   * Returns the plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * Returns the link options.
   *
   * @return mixed
   *   The options.
   */
  public function getOptions() {
    return $this->options;
  }

}

/**
 * Menu entity double.
 */
class OverviewMenu {

  /**
   * The menu id.
   *
   * @var string
   */
  protected $id;

  /**
   * Constructs the menu.
   *
   * @param string $id
   *   The menu id.
   */
  public function __construct(string $id) {
    $this->id = $id;
  }

  /**
   * Returns the menu id.
   *
   * @return string
   *   The id.
   */
  public function id() {
    return $this->id;
  }

}

/**
 * Menu form object double.
 */
class OverviewFormObject {

  /**
   * The menu entity.
   *
   * @var \Drupal\Tests\bioland\Unit\Service\OverviewMenu
   */
  protected $entity;

  /**
   * Constructs the form object.
   *
   * @param \Drupal\Tests\bioland\Unit\Service\OverviewMenu $entity
   *   The menu entity.
   */
  public function __construct(OverviewMenu $entity) {
    $this->entity = $entity;
  }

  /**
   * Returns the menu entity.
   *
   * @return \Drupal\Tests\bioland\Unit\Service\OverviewMenu
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

}

/**
 * Cacheable dependency double, for feeding tags into an access result.
 */
class OverviewCacheableDependency {

  /**
   * The cache tags.
   *
   * @var array
   */
  protected $tags;

  /**
   * Constructs the dependency.
   *
   * @param array $tags
   *   The cache tags.
   */
  public function __construct(array $tags) {
    $this->tags = $tags;
  }

  /**
   * Returns the cache tags.
   *
   * @return array
   *   The tags.
   */
  public function getCacheTags() {
    return $this->tags;
  }

}

/**
 * Form state double over a menu form object.
 */
class OverviewFormState implements FormStateInterface {

  /**
   * The form object, or NULL.
   *
   * @var mixed
   */
  protected $formObject;

  /**
   * Arbitrary storage.
   *
   * @var array
   */
  protected $storage = [];

  /**
   * Constructs the form state.
   *
   * @param mixed $formObject
   *   The form object.
   */
  public function __construct($formObject) {
    $this->formObject = $formObject;
  }

  /**
   * Returns the form object.
   *
   * @return mixed
   *   The form object.
   */
  public function getFormObject() {
    return $this->formObject;
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

}
