<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Adds Bioland's two mega-menu affordances to the core menu overview screen.
 *
 * The screen is core's \Drupal\menu_ui\MenuForm (form id "menu_edit_form") at
 * /admin/structure/menu/manage/{menu}. Two additions, both read-only with
 * respect to core's own state:
 *   - a greyed indicator column, immediately before "Enabled", naming the
 *     mega-menu component a row's link renders (and, for the Content Type
 *     Listing, the content type it is bound to);
 *   - an "Add Mega Menu Child" row operation, immediately after core's
 *     "Add child", opening the dedicated component add flow with that row's
 *     link preselected as the parent.
 *
 * A SERVICE, NOT PROCEDURAL CODE, for the reason given in
 * BiolandComponentMenuFormMode's class docblock: a \Drupal:: static in a .module
 * function cannot be unit tested in this suite. bioland.module holds only a thin
 * hook_form_menu_edit_form_alter() dispatcher.
 *
 * WHY A DEDICATED hook_form_FORM_ID_alter AND NOT bioland_form_alter(). The
 * generic hook carries an ordering contract - bioland_module_implements_alter()
 * moves it last so BiolandComponentMenuFormMode's entity builder lands after
 * menu_link_attributes' - and nothing here needs or should inherit it. In
 * Drupal 11 the two hooks share one listener list per alter-type combination
 * (ModuleHandler::getCombinedListeners()), and the legacy reordering is applied
 * using the FIRST hook in that list, "form_alter"; bioland's listeners are
 * grouped by module, so they still move together and this one still runs last.
 * The combination is cached under its own cid ("form,form_menu_form,
 * form_menu_edit_form"), so adding this hook cannot disturb the menu_link_content
 * ordering contract, which resolves under a different cid entirely.
 *
 * DEFENSIVE BY CONSTRUCTION. Every read of the form array is guarded and every
 * failure degrades to a no-op rather than a broken screen: a misaligned table
 * breaks tabledrag, which is how an editor reorders the whole menu. The column
 * is spliced only when every row agrees on where "Enabled" sits and the header
 * has a cell boundary at exactly that column; otherwise nothing is touched.
 *
 * VERIFIED CORE SHAPE (Drupal 11.4.4 and 10.5.x, byte-identical in this
 * region - MenuForm::buildOverviewForm()):
 *   - $form['links'] is '#type' => 'table', '#theme' => 'table__menu_overview';
 *   - '#header' holds four cells: "Menu link", "Enabled", "Weight", and
 *     "Operations" with 'colspan' => 3;
 *   - each row is keyed "menu_plugin_id:<plugin_id>" and carries '#item' (the
 *     MenuLinkTreeElement) plus the children title, enabled, weight,
 *     operations, id, parent - six cells against the header's six columns;
 *   - when any link has a pending revision core drops the weight column,
 *     unsetting '#header'[2] WITHOUT reindexing and omitting the row's 'weight'
 *     child, leaving five cells against five columns. Both shapes are handled.
 *   - Table::preRenderTable() turns row children into cells in ARRAY KEY ORDER
 *     (Element::children() without $sort), so a '#weight' on the new cell would
 *     do nothing - the child has to be physically spliced in before 'enabled'.
 *
 * @see \Drupal\bioland\Service\BiolandComponentRegistry
 * @see \Drupal\bioland\Controller\BiolandMenuController::addComponentLink()
 */
class BiolandComponentMenuOverview {

  use StringTranslationTrait;

  /**
   * Form array key of the table holding the menu links.
   */
  public const TABLE_ELEMENT = 'links';

  /**
   * Row child key of core's Enabled checkbox - the column to splice before.
   */
  public const ENABLED_CHILD = 'enabled';

  /**
   * Row child key of core's operations dropbutton.
   */
  public const OPERATIONS_CHILD = 'operations';

  /**
   * Row child key this service adds, and its table column.
   */
  public const INDICATOR_ELEMENT = 'bioland_mega_menu';

  /**
   * CSS class on the indicator header cell and every indicator body cell.
   */
  public const INDICATOR_CLASS = 'bioland-mega-menu-indicator';

  /**
   * Operations key of core's "Add child", the operation to splice after.
   */
  public const CORE_ADD_CHILD = 'add-child';

  /**
   * Operations key this service adds.
   */
  public const OPERATION_KEY = 'bioland-add-component-child';

  /**
   * The dedicated component add route the new operation links to.
   */
  public const ADD_ROUTE = 'bioland.menu_link_component.add';

  /**
   * Query parameter carrying the preselected parent plugin id.
   *
   * The same spelling core's own "Add child" uses on
   * entity.menu.add_link_form, so the two operations behave identically.
   */
  public const PARENT_QUERY = 'parent';

  /**
   * The admin library this service owns and is the only place that attaches.
   */
  public const LIBRARY = 'bioland/menu_overview';

  /**
   * Cache tag of the settings the indicator column's labels are narrowed by.
   */
  public const SETTINGS_CACHE_TAG = 'config:bioland.settings';

  /**
   * The component vocabulary and every token rule.
   *
   * @var \Drupal\bioland\Service\BiolandComponentRegistry
   */
  protected $registry;

  /**
   * The Component-mode service, read for the runtime site identifier.
   *
   * @var \Drupal\bioland\Service\BiolandComponentMenuFormMode
   */
  protected $formMode;

  /**
   * The redirect destination, for the new operation's return path.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface|null
   */
  protected $redirectDestination;

  /**
   * Constructs the menu overview service.
   *
   * @param \Drupal\bioland\Service\BiolandComponentRegistry $registry
   *   The component registry - the only source of component-token rules.
   * @param \Drupal\bioland\Service\BiolandComponentMenuFormMode $form_mode
   *   The Component-mode service, reused for siteId() rather than re-reading
   *   bioland.settings here: one place decides what flavour this site is.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface|null $redirect_destination
   *   The redirect destination. Nullable only so the pure table rules stay
   *   unit-testable without a container; without it the new operation simply
   *   carries no destination and the editor lands wherever the saved link
   *   points, instead of back on this screen.
   */
  public function __construct(BiolandComponentRegistry $registry, BiolandComponentMenuFormMode $form_mode, ?RedirectDestinationInterface $redirect_destination = NULL) {
    $this->registry = $registry;
    $this->formMode = $form_mode;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * Adds both affordances to a menu overview form.
   *
   * The single entry point; bioland_form_menu_edit_form_alter() calls nothing
   * else. A form without the links table - the menu ADD form, where core skips
   * buildOverviewForm() entirely - is left byte-identical.
   *
   * @param array $form
   *   The form array, altered in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form being altered.
   */
  public function alterOverviewForm(array &$form, FormStateInterface $form_state): void {
    if (!isset($form[self::TABLE_ELEMENT]) || !is_array($form[self::TABLE_ELEMENT])) {
      return;
    }

    if ($this->addIndicatorColumn($form[self::TABLE_ELEMENT])) {
      // Attached only when the column actually landed, so a screen this
      // service declined to touch loads no stylesheet it has no use for.
      $form['#attached']['library'][] = self::LIBRARY;
      // And the column's OWN cacheability, declared here rather than left to
      // ride on the row operation's access probe: which labels this column
      // prints depends on bioland.settings (is_biosafety_land, reached through
      // siteId()), the two features land independently, and the operation is
      // gone entirely when its route denies access.
      $this->addCacheTags($form, [self::SETTINGS_CACHE_TAG]);
    }

    $this->addChildOperations($form, $this->menuId($form_state));
  }

  /**
   * Splices the indicator column in immediately before Enabled.
   *
   * All-or-nothing: the header cell and every row cell are added together, or
   * neither is. Header and body must stay aligned - core's tabledrag reads the
   * hidden id/parent cells by position, so one stray cell breaks reordering for
   * the whole menu.
   *
   * @param array $table
   *   The $form['links'] table element, altered in place.
   *
   * @return bool
   *   TRUE when the column was added.
   */
  protected function addIndicatorColumn(array &$table): bool {
    $header = $table['#header'] ?? NULL;
    if (!is_array($header) || $header === []) {
      return FALSE;
    }

    $row_keys = $this->childKeys($table);
    if ($row_keys === []) {
      return FALSE;
    }

    // IDEMPOTENT. Nothing promises an alter runs exactly once over one form
    // array - a rebuild, or a second dispatcher wired by mistake, would splice
    // a second header cell and a second cell into every row, and the header
    // would then out-count the rows by one, which is the tabledrag breakage
    // this whole method exists to avoid. Read off the first row only: the
    // column is added all-or-nothing, so if one row carries it they all do.
    if (isset($table[$row_keys[0]][self::INDICATOR_ELEMENT])) {
      return FALSE;
    }

    $column = $this->enabledColumnIndex($table, $row_keys);
    if ($column === NULL) {
      return FALSE;
    }

    $header_key = $this->headerKeyForColumn($header, $column);
    if ($header_key === NULL) {
      return FALSE;
    }

    // Rebuilt rather than array_spliced: core leaves the header keys sparse
    // (0, 1, 3) after dropping the weight column, which array_splice would
    // silently renumber anyway. Rebuilding states the intent and is correct for
    // both shapes; the header is rendered as a plain list, so reindexing it is
    // safe.
    $spliced = [];
    foreach ($header as $key => $cell) {
      if ($key === $header_key) {
        $spliced[] = [
          'data' => $this->t('Mega Menu'),
          'class' => [self::INDICATOR_CLASS],
        ];
      }
      $spliced[] = $cell;
    }
    $table['#header'] = $spliced;

    foreach ($row_keys as $key) {
      $table[$key] = $this->rowWithIndicator($table[$key]);
    }

    return TRUE;
  }

  /**
   * Returns the column index of Enabled, or NULL when the rows disagree.
   *
   * Read off the rows rather than matched against the header's translated
   * "Enabled" string: the row child key is a stable machine name, the header
   * cell is a translatable value whose shape (string or render array) is not
   * guaranteed. Every row must place it identically, which is what makes a
   * single splice position correct for the whole table.
   *
   * @param array $table
   *   The table element.
   * @param array $rowKeys
   *   The table's row keys.
   *
   * @return int|null
   *   The shared column index, or NULL when any row lacks an Enabled child or
   *   places it somewhere else.
   */
  protected function enabledColumnIndex(array $table, array $rowKeys): ?int {
    $index = NULL;
    foreach ($rowKeys as $key) {
      if (!is_array($table[$key])) {
        return NULL;
      }
      $position = array_search(self::ENABLED_CHILD, $this->childKeys($table[$key]), TRUE);
      if ($position === FALSE) {
        return NULL;
      }
      if ($index === NULL) {
        $index = $position;
      }
      elseif ($index !== $position) {
        return NULL;
      }
    }

    return $index;
  }

  /**
   * Returns the header key starting at a column, or NULL when none does.
   *
   * A header cell may span several columns ("Operations" spans three), so the
   * header index and the column index are not the same number. Walking the
   * colspans is what keeps the splice honest; a column index that falls INSIDE
   * a spanning cell has no insertion point and returns NULL, which aborts the
   * whole column.
   *
   * @param array $header
   *   The table's '#header'.
   * @param int $column
   *   The column index to insert before.
   *
   * @return int|string|null
   *   The header key to insert before, or NULL.
   */
  protected function headerKeyForColumn(array $header, int $column) {
    $offset = 0;
    foreach ($header as $key => $cell) {
      if ($offset === $column) {
        return $key;
      }
      $span = is_array($cell) ? (int) ($cell['colspan'] ?? 1) : 1;
      $offset += max(1, $span);
    }

    return NULL;
  }

  /**
   * Returns a row with the indicator cell spliced in before Enabled.
   *
   * @param array $row
   *   The row element.
   *
   * @return array
   *   The rebuilt row. Every existing key keeps its value and its relative
   *   position, so the other cells - and core's '#item', '#attributes' and
   *   '#weight' properties - are untouched.
   */
  protected function rowWithIndicator(array $row): array {
    $cell = [
      // '#markup' CARRYING THE TranslatableMarkup ITSELF, never '#plain_text'
      // and never a string. t() has already escaped the placeholder - which is
      // the only attacker-influenced part, a suffix read off a user-editable
      // class attribute - and it returns a MarkupInterface saying so.
      // Renderer::xssFilterAdminIfUnsafe() skips a MarkupInterface '#markup',
      // so the value is escaped exactly once. A '#plain_text' value, or a
      // '#markup' string cast off the same object, is escaped a SECOND time by
      // the renderer: a component named "AT&T" reaches the screen as
      // "AT&amp;T". Verified against Drupal 11.4.4.
      '#markup' => $this->indicatorMarkup($row['#item'] ?? NULL),
      // Becomes the <td> attributes: Table::preRenderTable() folds a child's
      // '#wrapper_attributes' into its cell. One class carries the greying, so
      // no inline style and no wrapper element are needed.
      '#wrapper_attributes' => ['class' => [self::INDICATOR_CLASS]],
    ];

    $rebuilt = [];
    foreach ($row as $key => $value) {
      if ($key === self::ENABLED_CHILD) {
        $rebuilt[self::INDICATOR_ELEMENT] = $cell;
      }
      $rebuilt[$key] = $value;
    }

    return $rebuilt;
  }

  /**
   * Builds the indicator markup for a row's menu link tree element.
   *
   * Hands back what t() returned rather than a string: the object IS the
   * "already escaped" signal the renderer reads, and casting it away is what
   * causes the double escaping rowWithIndicator() documents. No return type is
   * declared for the same reason the empty case is a string - the two arms have
   * no common Drupal type, and TranslatableMarkup is not a string subtype.
   *
   * @param mixed $item
   *   The row's '#item', a \Drupal\Core\Menu\MenuLinkTreeElement.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   "(Mega Menu: <type>)", or "(Mega Menu: <type>: <slug>)" for a bound
   *   Content Type Listing; an empty string for a row whose link carries no
   *   component token, which is what keeps that row's cell empty.
   */
  public function indicatorMarkup($item) {
    $class = $this->storedClassValue($item);
    if ($class === NULL) {
      return '';
    }

    $site_id = $this->siteId();
    $token = $this->registry->findComponentTokens($class, $site_id)[0] ?? '';
    if ($token === '') {
      return '';
    }

    $suffix = $this->registry->componentSuffix($token, $site_id);
    if ($suffix === NULL || $suffix === '') {
      return '';
    }

    // Un-narrowed on purpose (optionsFor(FALSE), every component rather than
    // the site's offered subset): a BSL site can still hold a link authored
    // before the narrowing, and that link must read as what it renders rather
    // than fall back to its raw suffix. Same precedent as
    // BiolandComponentMenuFormMode::preservedOptionLabel().
    $labels = $this->registry->optionsFor(FALSE);
    // A stale token - a component since removed from the registry - has no
    // label. Show the bare suffix rather than an empty cell: the row IS a
    // mega-menu link, and naming the token it carries is what lets an editor
    // find and fix it.
    //
    // The (string) here is NOT the cast rowWithIndicator() warns about: both
    // arms are already plain strings (optionsFor() casts its labels, and a
    // suffix is a substring of a class token), and this value is about to
    // become a t() ARGUMENT, where escaping is correct and required - the
    // suffix arm is the attacker-influenced one. The cast that matters is the
    // one on t()'s RESULT, and there is none.
    $type = (string) ($labels[$this->registry->canonicalToken($suffix)] ?? $suffix);

    if ($suffix === BiolandComponentRegistry::CONTENT_TYPE_SUFFIX) {
      $schema = $this->registry->findContentTypeBindings($class)[0] ?? '';
      if ($schema !== '') {
        return $this->t('(Mega Menu: @type: @schema)', [
          '@type' => $type,
          '@schema' => $schema,
        ]);
      }
    }

    return $this->t('(Mega Menu: @type)', ['@type' => $type]);
  }

  /**
   * Returns the indicator as a plain string.
   *
   * The string face of indicatorMarkup(), for a caller that needs one - a log
   * line, a comparison, a test. NOT what the cell carries; see
   * rowWithIndicator() for why that has to stay an object.
   *
   * @param mixed $item
   *   The row's '#item', a \Drupal\Core\Menu\MenuLinkTreeElement.
   *
   * @return string
   *   The rendered indicator, or an empty string.
   */
  public function indicatorText($item): string {
    return (string) $this->indicatorMarkup($item);
  }

  /**
   * Adds the "Add Mega Menu Child" operation to every row.
   *
   * Takes the whole form rather than the table so the access result's cache
   * metadata lands on the form. Rows are written through the form by index
   * rather than through a reference to the table, so no second live alias into
   * the array is ever created.
   *
   * @param array $form
   *   The form, altered in place.
   * @param string|null $menuId
   *   The menu being edited.
   */
  protected function addChildOperations(array &$form, ?string $menuId): void {
    if ($menuId === NULL) {
      return;
    }

    // Checked ONCE for the whole table, not per row: route access depends on
    // the route and its {menu} parameter, and the only thing that differs
    // between rows is the ?parent query, which no access check reads. N
    // identical checks would buy nothing.
    $access = $this->addAccess($menuId);
    // ORDERING IS LOAD-BEARING, AND IT IS NOT OURS TO CHANGE. Core's
    // FormBuilder has already stamped this form with a max-age of 0 for the
    // CSRF token (FormBuilder::prepareForm() -> setCache/'form_token', for any
    // authenticated user) BEFORE the alter hooks run. applyCacheability() only
    // ever shortens a max-age, so nothing written below can lengthen or
    // disable that guard - a future edit that starts overwriting rather than
    // min()-ing would.
    $this->applyCacheability($form, $access);
    if (!$access->isAllowed()) {
      return;
    }

    foreach ($this->childKeys($form[self::TABLE_ELEMENT]) as $key) {
      $row = $form[self::TABLE_ELEMENT][$key];
      if (!is_array($row)) {
        continue;
      }

      $links = $row[self::OPERATIONS_CHILD]['#links'] ?? NULL;
      $plugin_id = $this->pluginId($row['#item'] ?? NULL);
      if (!is_array($links) || $plugin_id === NULL) {
        continue;
      }

      $form[self::TABLE_ELEMENT][$key][self::OPERATIONS_CHILD]['#links'] =
        $this->withComponentOperation($links, $menuId, $plugin_id);
    }
  }

  /**
   * Returns an operations list with the component operation added.
   *
   * Positioned by ARRAY ORDER, immediately after core's "Add child". A 'weight'
   * would be inert: MenuForm sorts the operations by weight while building the
   * row, long before any alter runs, and nothing sorts them again.
   *
   * @param array $links
   *   The row's existing '#links'.
   * @param string $menuId
   *   The menu being edited.
   * @param string $pluginId
   *   The row's link plugin id, to preselect as the new link's parent.
   *
   * @return array
   *   The operations list, unchanged when core omitted "Add child". Core omits
   *   it at the deepest allowed level (depth >= MenuTreeStorage::MAX_DEPTH),
   *   where the row cannot take a child at all. Offering ours there would open
   *   a form whose parent select cannot hold the requested parent, so it falls
   *   back to the menu root and the new link silently lands somewhere the
   *   editor did not ask for. Core withholds the affordance for a reason;
   *   mirroring that is the whole rule.
   */
  protected function withComponentOperation(array $links, string $menuId, string $pluginId): array {
    if (!array_key_exists(self::CORE_ADD_CHILD, $links)) {
      return $links;
    }

    $operation = [
      'title' => $this->t('Add Mega Menu Child'),
      'url' => Url::fromRoute(
        self::ADD_ROUTE,
        ['menu' => $menuId],
        ['query' => [self::PARENT_QUERY => $pluginId]]
      ),
    ];

    // Core gives every operation it builds a 'query' holding the destination,
    // so each one returns here after saving; ours is added after that loop has
    // run, so it sets its own. Merged (not overwritten) into the Url's options
    // by Url::mergeOptions(), leaving ?parent=... intact.
    $destination = $this->destinationQuery();
    if ($destination !== []) {
      $operation['query'] = $destination;
    }

    $rebuilt = [];
    foreach ($links as $key => $link) {
      $rebuilt[$key] = $link;
      if ($key === self::CORE_ADD_CHILD) {
        $rebuilt[self::OPERATION_KEY] = $operation;
      }
    }

    return $rebuilt;
  }

  /**
   * Returns the add route's own access decision for a menu.
   *
   * Deliberately NOT a hand-rolled read of the component_menu_add_enabled flag:
   * the route already carries that check plus _entity_create_access, and asking
   * the route is what keeps the operation and the URL it points at from ever
   * disagreeing. Requested as an object so the decision's cache metadata can be
   * folded into the form.
   *
   * @param string $menuId
   *   The menu being edited.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The decision; forbidden when the route resolver returns anything else.
   */
  protected function addAccess(string $menuId): AccessResultInterface {
    $result = Url::fromRoute(self::ADD_ROUTE, ['menu' => $menuId])->access(NULL, TRUE);

    return $result instanceof AccessResultInterface ? $result : AccessResult::forbidden();
  }

  /**
   * Folds an access result's cache metadata into the form.
   *
   * The decision depends on bioland.settings and on the user's permissions, so
   * a cached copy of this screen has to carry both or it can outlive them.
   * Spelled out rather than routed through Cache/CacheableMetadata so the
   * service needs no container to unit test; same precedent as the plain
   * '#cache' append in BiolandComponentMenuFormMode::apply().
   *
   * @param array $form
   *   The form, altered in place.
   * @param mixed $access
   *   The access result.
   */
  protected function applyCacheability(array &$form, $access): void {
    if (!is_object($access)) {
      return;
    }

    foreach (['tags' => 'getCacheTags', 'contexts' => 'getCacheContexts'] as $key => $getter) {
      if (!method_exists($access, $getter)) {
        continue;
      }
      $added = $access->{$getter}();
      if (is_array($added)) {
        $this->addCacheKeys($form, $key, $added);
      }
    }

    if (!method_exists($access, 'getCacheMaxAge')) {
      return;
    }
    // INSURANCE, NOT DEAD WEIGHT - AND CURRENTLY A NO-OP. For any
    // authenticated user this branch cannot lower anything: core's CSRF guard
    // already put a max-age of 0 on the form before the alter ran (see
    // addChildOperations()), and 0 is the floor. It is kept because core plans
    // to drop that blanket guard once forms stop being uncacheable by default
    // (drupal.org issue #3395157); the day it lands, an access result with a
    // real max-age has to keep shortening this form's, and a branch added
    // later, under pressure, is a branch nobody tests.
    //
    // Cache::PERMANENT is -1, "forever"; any real max-age beats it, and between
    // two real ones the shorter wins.
    $permanent = -1;
    $added = (int) $access->getCacheMaxAge();
    if ($added === $permanent) {
      return;
    }
    $existing = $form['#cache']['max-age'] ?? $permanent;
    $existing = is_int($existing) ? $existing : $permanent;
    $form['#cache']['max-age'] = $existing === $permanent ? $added : min($existing, $added);
  }

  /**
   * Merges cache tags into the form.
   *
   * @param array $form
   *   The form, altered in place.
   * @param array $tags
   *   The tags to add.
   */
  protected function addCacheTags(array &$form, array $tags): void {
    $this->addCacheKeys($form, 'tags', $tags);
  }

  /**
   * Merges values into one of the form's '#cache' list keys.
   *
   * @param array $form
   *   The form, altered in place.
   * @param string $key
   *   The '#cache' key, "tags" or "contexts".
   * @param array $added
   *   The values to add.
   */
  protected function addCacheKeys(array &$form, string $key, array $added): void {
    // Nothing to add means nothing to write: a run that changes no
    // cacheability must not leave an empty '#cache' key behind on a form it
    // otherwise did not touch.
    if ($added === []) {
      return;
    }

    $existing = $form['#cache'][$key] ?? [];
    if (!is_array($existing)) {
      return;
    }

    $form['#cache'][$key] = array_values(array_unique(array_merge($existing, $added)));
  }

  /**
   * Returns the destination query for the new operation.
   *
   * @return array
   *   A ['destination' => ...] array, or empty when unavailable.
   */
  protected function destinationQuery(): array {
    if ($this->redirectDestination === NULL) {
      return [];
    }

    $destination = $this->redirectDestination->getAsArray();

    return is_array($destination) ? $destination : [];
  }

  /**
   * Returns the id of the menu the form is editing, or NULL.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The menu id.
   */
  protected function menuId(FormStateInterface $form_state): ?string {
    $form_object = $form_state->getFormObject();
    if (!is_object($form_object) || !method_exists($form_object, 'getEntity')) {
      return NULL;
    }

    $entity = $form_object->getEntity();
    if (!is_object($entity) || !method_exists($entity, 'id')) {
      return NULL;
    }

    $id = $entity->id();

    return (is_string($id) && $id !== '') ? $id : NULL;
  }

  /**
   * Returns a tree element's link plugin id, or NULL.
   *
   * @param mixed $item
   *   The row's '#item'.
   *
   * @return string|null
   *   The plugin id.
   */
  protected function pluginId($item): ?string {
    if (!is_object($item) || !isset($item->link) || !is_object($item->link)) {
      return NULL;
    }
    if (!method_exists($item->link, 'getPluginId')) {
      return NULL;
    }

    $id = $item->link->getPluginId();

    return (is_string($id) && $id !== '') ? $id : NULL;
  }

  /**
   * Returns a tree element's stored options.attributes.class value, or NULL.
   *
   * For a menu_link_content link the plugin definition's 'options' IS the
   * entity's link options (MenuLinkContent::getPluginDefinition() copies them
   * off the Url object), so this reaches the same stored class value
   * BiolandComponentMenuFormMode reads off the entity on the edit form.
   *
   * @param mixed $item
   *   The row's '#item'.
   *
   * @return array|string|null
   *   The raw stored value in whichever shape it was stored, or NULL when the
   *   element carries no readable link.
   */
  protected function storedClassValue($item) {
    if (!is_object($item) || !isset($item->link) || !is_object($item->link)) {
      return NULL;
    }
    if (!method_exists($item->link, 'getOptions')) {
      return NULL;
    }

    $options = $item->link->getOptions();
    if (!is_array($options)) {
      return NULL;
    }

    $class = $options['attributes']['class'] ?? [];

    return (is_array($class) || is_string($class)) ? $class : [];
  }

  /**
   * Returns the runtime multisite identifier for token matching.
   *
   * @return string
   *   The site identifier, "bsl" or "bl2".
   */
  protected function siteId(): string {
    return $this->formMode->siteId();
  }

  /**
   * Returns an element's child keys, in array order.
   *
   * The same rule as \Drupal\Core\Render\Element::children() with $sort off -
   * which is exactly how Table::preRenderTable() reads rows and cells - kept
   * local so the service stays a plain object with no render-layer dependency
   * to stub. Order is array order, never '#weight'.
   *
   * @param array $element
   *   A render array.
   *
   * @return array
   *   The child keys.
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

}
