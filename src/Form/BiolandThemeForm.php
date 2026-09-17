<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\bioland\BiolandHomeWidgetRegistry;
use Drupal\bioland\BiolandThemeContract;
use Drupal\bioland\Service\BiolandDmsmConfigService;

/**
 * Configure the per-site theme stored at `bioland.settings:theme`.
 *
 * ## What this tab authors (plan decision D4)
 *
 * Exactly the eight LIVE keys named by \Drupal\bioland\BiolandThemeContract,
 * and nothing else:
 *
 * - color.primary, color.secondary            (hex colour pickers, required)
 * - back_ground.secondary                     (hex colour picker, required)
 * - hero.primary, hero.secondary              (hex colour pickers, required)
 * - home_page_widgets.columns                 (widget selects; hidden on BSL)
 * - mega_menu.max_columns                     (1-6)
 * - mega_menu.max_rows_per_column             (>= 0; 0 means unlimited)
 * - mega_menu.horizontal_card_max             (1-6)
 * - i18n.max_lang_before_wrap                 (integer, required)
 *
 * `hero` IS authored here as of BL-1011, as two scalar hex keys. It used to
 * be derived downstream only when no source defined it, which left an editor
 * with no way to set the hero banner at all: the head compensated by letting a
 * saved brand colour override the inherited hero slot, so the two could never
 * be set independently. Two named colour pickers replace that guess.
 *
 * Note the shape change. The legacy network theme stores the hero as ONE key
 * holding an ordered pair, `hero.primary: [primary, secondary]`. This tab
 * writes two scalars instead, `hero.primary` and `hero.secondary`, because a
 * colour picker authors one colour and the second slot deserves a name. The
 * head accepts both: authored scalars win, and a site that has never saved
 * this tab still resolves the inherited array exactly as before. The seed
 * bridges the two shapes -- see self::HERO_SEED_PATHS.
 *
 * The dead legacy keys (text.*, *_text_over, can_auto_translate, back_ground.primary,
 * back_ground.tertiary, mega_menu.column_wrap, mega_menu.horizontal_card_wrap,
 * mega_menu.show_empty, colums_width) are deliberately absent and must never
 * be reintroduced. A conformance test pins the writer's key set against
 * BiolandThemeContract::KEYS and against config/schema/bioland.schema.yml.
 *
 * Config keys are snake_case. `back_ground` is two words, never `background`:
 * bioland-head camelCases the payload at depth 7 into `backGround`, the exact
 * property its language bar reads. That head-facing camelCase depth is a
 * separate contract from this Drupal snake_case depth; the two are pinned by
 * separate assertions.
 *
 * ## Seeding: lazy, and ON SAVE ONLY (plan decision D5)
 *
 * When `bioland.settings:theme` is absent, the fields pre-populate from the
 * site's effective dmsm theme (BiolandDmsmConfigService::getEffectiveTheme()).
 * Building the form performs ZERO config writes -- the pre-filled values reach
 * config only when an editor submits.
 *
 * This is a DELIBERATE DEVIATION from the house precedent next door:
 * BiolandHomeWidgetsForm::ensureHomeWidgetDefaults() is called from its
 * buildSectionForm() (:54) and calls $config->save() during the build (:433-435),
 * so merely opening that tab writes config. The Theme tab must not do that,
 * for two reasons:
 *
 * 1. A GET must not mutate state. The module ships to ~211 sites; a save on
 *    build would silently convert every site an administrator merely LOOKED at
 *    from "tracking the network default" to "pinned to a private copy".
 * 2. The 46 default-tracking sites are supposed to keep live inheritance until
 *    an editor deliberately acts. Writing on build would end that inheritance
 *    invisibly, and D2's fall-through chain would stop being reachable.
 *
 * When the seed cannot be read at all -- getEffectiveTheme() returns NULL on
 * any HTTP or parse error -- the colour fields fall back to this flavor's
 * built-in network defaults (self::FALLBACK_COLORS_BL2 /
 * self::FALLBACK_COLORS_BSL) instead of rendering empty. Empty is the
 * dangerous outcome, not the safe one: core's Color::validateColor() turns an
 * empty `#type => color` value into #000000, so a silent seed failure followed
 * by one Save would black out the site's brand colours permanently. This
 * changes defaults only -- still zero config writes on GET, so D5 holds.
 *
 * The reset control (plan decision RS) is the way back: it clears the whole
 * `theme` key so the site falls through the D2 chain again
 * (biolandSettings.theme > site config.theme > runTime.theme > code defaults)
 * and renders exactly as an unseeded site does.
 *
 * ## BSL (plan decision D7)
 *
 * On `is_biosafety_land` sites the columns field is absent from the build leg,
 * skipped in the submit leg, and its exactly-3 validator never runs. The BSL
 * home page does not use the column mechanism at all, and its live one-column
 * layout would fail the W2a bound. Precedent: BiolandHomeWidgetsForm:56 (build)
 * and :379 (submit).
 *
 * The same flag also picks the built-in colour fallbacks: a BSL site with no
 * authored theme and no reachable seed shows the BSL palette, never bl2's.
 * See self::FALLBACK_COLORS_BSL.
 *
 * @see \Drupal\bioland\BiolandThemeContract
 * @see \Drupal\bioland\BiolandHomeWidgetRegistry
 * @see \Drupal\Tests\bioland\Unit\BiolandThemeFormTest
 */
class BiolandThemeForm extends BiolandSettingsFormBase {

  /**
   * The `bioland.settings` key holding the whole authored theme subtree.
   */
  public const CONFIG_KEY = 'theme';

  /**
   * The dmsm config service id, resolved lazily.
   *
   * Resolved through the container rather than injected through the
   * constructor so every settings form keeps the base class's six-argument
   * signature (BiolandSettingsRoutingWiringTest instantiates all of them
   * uniformly).
   */
  public const DMSM_SERVICE_ID = 'bioland.dmsm_config';

  /**
   * Maps each D4 config key to the head/dmsm camelCase path it seeds from.
   *
   * Left: the snake_case `theme` sub-path this form writes to Drupal config.
   * Right: the camelCase sub-path of the effective dmsm theme it reads.
   * These are two distinct contracts at two distinct depths -- this constant
   * is the only place they meet.
   */
  protected const SEED_PATHS = [
    BiolandThemeContract::KEY_COLOR_PRIMARY => 'color.primary',
    BiolandThemeContract::KEY_COLOR_SECONDARY => 'color.secondary',
    BiolandThemeContract::KEY_BACK_GROUND_SECONDARY => 'backGround.secondary',
    BiolandThemeContract::KEY_HOME_PAGE_WIDGETS_COLUMNS => 'homePageWidgets.columns',
    BiolandThemeContract::KEY_MEGA_MENU_MAX_COLUMNS => 'megaMenu.maxColumns',
    BiolandThemeContract::KEY_MEGA_MENU_MAX_ROWS_PER_COLUMN => 'megaMenu.maxRowsPerColumn',
    BiolandThemeContract::KEY_MEGA_MENU_HORIZONTAL_CARD_MAX => 'megaMenu.horizontalCardMax',
    BiolandThemeContract::KEY_I18N_MAX_LANG_BEFORE_WRAP => 'i18n.maxLangBeforeWrap',
  ];

  /**
   * The hero half of the seed map, kept separate because it changes shape.
   *
   * Every other entry in self::SEED_PATHS is a like-for-like leaf copy. These
   * two are not: the effective dmsm theme stores the hero as the legacy
   * ordered pair `hero.primary: [primary, secondary]`, and this tab authors it
   * as two scalars. So the seed reads INDEX 0 and INDEX 1 of that list and
   * lands them on two separate config keys. leaf() walks a numeric segment the
   * same way it walks a string one (array_key_exists on a list), so the paths
   * below need no special reader.
   *
   * Merging these into SEED_PATHS would hide the shape change behind two paths
   * that look like every other entry; splitting them is what makes it
   * reviewable. Both maps are consumed by the same loop in seedFromDmsm().
   *
   * Applied by withHeroPair(), NOT by the generic loop, because the pair is
   * validated as a UNIT -- see that method for why half of it is worse than
   * none of it. A site whose seed carries no usable hero pair simply gets no
   * hero default from here and falls through to withFallbackColors().
   */
  protected const HERO_SEED_PATHS = [
    BiolandThemeContract::KEY_HERO_PRIMARY => 'hero.primary.0',
    BiolandThemeContract::KEY_HERO_SECONDARY => 'hero.primary.1',
  ];

  /**
   * Last-resort colours for a bl2 site when the dmsm seed cannot be read.
   *
   * getEffectiveTheme() returns NULL on any HTTP or parse error, leaving an
   * unseeded colour field with no default. These fields are `#type => color`
   * and `#required`, and the browser's native `<input type="color">` has no
   * empty state: an unset picker posts `#000000`, so the required check sees
   * a perfectly valid value and passes it straight through. The editor's very
   * first Save would write black brand colours and D5's seed-on-save would
   * make that permanent. A dmsm hiccup during authoring must not be able to
   * black out a site.
   *
   * There is one set per network flavor, chosen by isBiosafetyLand(): a BSL
   * site falling back to bl2 blue would be just as wrong as falling back to
   * black, only harder to notice. See self::FALLBACK_COLORS_BSL.
   *
   * These are the live Bioland network defaults, read from the network
   * document itself rather than from a test fixture. Fetched 2026-08-06 from
   * the public-allowlisted projection
   * `GET https://dmsm.cbddev.xyz/api/config/prod/bl2`, whose network-level
   * `config.theme` block reads `color.primary` #009edb, `color.secondary`
   * #16c56e and `backGround.secondary` #F2F2F2 -- kept lower-case here
   * because `<input type="color">` normalizes to lower-case and the stored
   * value must round-trip. bl2 is the only prod network (`prod/bsl`,
   * `prod/chm` and `prod/bch` all return HTTP 204).
   *
   * The primary is NOT spelled out here: this tab is no longer its only
   * consumer, so it lives on BiolandThemeContract::FALLBACK_PRIMARY_BL2 and is
   * referenced from there. The other two are read by this tab alone and stay
   * local.
   *
   * They deliberately do NOT live in config/install/bioland.settings.yml.
   * That file ships no `theme` block at all, and adding one would make
   * $config->get('theme') non-empty on every install, so themeDefaults()
   * would read every site as already authored and the D5 dmsm seed would
   * never run -- pinning all ~211 sites to a private copy of the defaults,
   * which is the exact failure D5 exists to prevent.
   */
  protected const FALLBACK_COLORS_BL2 = [
    BiolandThemeContract::KEY_COLOR_PRIMARY => BiolandThemeContract::FALLBACK_PRIMARY_BL2,
    BiolandThemeContract::KEY_COLOR_SECONDARY => '#16c56e',
    BiolandThemeContract::KEY_BACK_GROUND_SECONDARY => '#f2f2f2',
    // The same two values again, not a copy-paste slip. The prod/bl2 network
    // document's `hero.primary` pair is ['#009edb', '#16c56e'] -- byte-identical
    // to its brand pair -- so the flavor's last-resort hero IS its brand pair.
    // They are listed separately rather than aliased because the two keys are
    // independently authorable from here on, and a future network document
    // that diverges them must only have to change this table.
    BiolandThemeContract::KEY_HERO_PRIMARY => BiolandThemeContract::FALLBACK_PRIMARY_BL2,
    BiolandThemeContract::KEY_HERO_SECONDARY => '#16c56e',
  ];

  /**
   * Last-resort colours for a BSL site when the dmsm seed cannot be read.
   *
   * The BSL counterpart of self::FALLBACK_COLORS_BL2 -- every reason given
   * there for having built-in colours at all, for keeping them lower-case,
   * and for keeping them out of config/install applies here unchanged.
   *
   * Fetched 2026-08-07 from `GET https://dmsm.cbddev.xyz/api/config/dev/bsl`,
   * whose network-level `config.theme.color` block reads `primary` #fa6938
   * and `secondary` #428BCA. dev is the only DMSM environment publishing a
   * bsl network config -- `prod/bsl` and `staging/bsl` both return HTTP 204 --
   * so unlike the bl2 set these come from the dev projection of the same
   * document, not from prod. The primary is referenced from
   * BiolandThemeContract::FALLBACK_PRIMARY_BSL for the reason given on the bl2
   * set above.
   *
   * `back_ground.secondary` is #f2f2f2 on both networks: the bsl document
   * carries the same neutral grey, so this is a shared value rather than a
   * bl2 leak.
   */
  protected const FALLBACK_COLORS_BSL = [
    BiolandThemeContract::KEY_COLOR_PRIMARY => BiolandThemeContract::FALLBACK_PRIMARY_BSL,
    BiolandThemeContract::KEY_COLOR_SECONDARY => '#428bca',
    BiolandThemeContract::KEY_BACK_GROUND_SECONDARY => '#f2f2f2',
    // As on the bl2 set: dev/bsl's `hero.primary` pair is ['#fa6938', '#428BCA'],
    // the same two colours as its brand pair, lower-cased here to round-trip
    // through `<input type="color">`.
    BiolandThemeContract::KEY_HERO_PRIMARY => BiolandThemeContract::FALLBACK_PRIMARY_BSL,
    BiolandThemeContract::KEY_HERO_SECONDARY => '#428bca',
  ];

  /**
   * The memoized PRE-fallback seed map, or NULL before the first call.
   *
   * getEffectiveTheme() is a synchronous HTTP call with a 10 second timeout.
   * Without this, an unseeded tab would pay for it on the initial GET and
   * again on every validation rebuild within the same request.
   *
   * What is cached is deliberately the seed BEFORE withFallbackColors() runs,
   * because the fallbacks are flavor-dependent and this cache is not keyed by
   * flavor: caching the finished map would let a second seedFromDmsm() call
   * with a different $isBiosafetyLand hand back the first flavor's palette.
   * seedFromDmsm() therefore applies the fallbacks on every call, cache hit
   * included. `[]` is a real cached value (the seed was unreadable, or defined
   * none of the D4 keys) and is distinct from NULL, so an unreadable seed is
   * still fetched only once.
   *
   * @var array|null
   */
  protected $seedCache = NULL;

  /**
   * The raw effective dmsm theme, memoized separately from self::$seedCache.
   *
   * self::$seedCache stores the translated D4 defaults; the authored branch
   * of themeDefaults() needs the raw camelCase effective theme instead, to
   * seed only the hero pair without disturbing colours the editor already
   * saved. Both branches share this cache so a request that touches both
   * (authored-with-no-hero, then a later unrelated seedFromDmsm() call)
   * still makes the 10 second HTTP call only once.
   *
   * `[]` is a real cached value (unreadable seed) distinct from the initial
   * NULL, exactly like self::$seedCache.
   *
   * @var array|null
   */
  protected $effectiveThemeCache = NULL;

  /**
   * Whether self::$effectiveThemeCache has been populated yet.
   *
   * Needed because `[]` (an unreadable seed) and NULL-not-yet-fetched are
   * both falsy the same way self::$seedCache distinguishes them, but here
   * a fetch failure and "never asked" are both represented as NULL in the
   * cache itself, so a separate flag is required to tell them apart.
   *
   * @var bool
   */
  protected $effectiveThemeFetched = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_theme_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end_theme';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    // NOTE: no $config->save() anywhere in this method, or anything it calls.
    // See the class docblock -- this is the deliberate deviation from
    // BiolandHomeWidgetsForm::ensureHomeWidgetDefaults().
    $defaults = $this->themeDefaults($config);

    $form['theme'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['theme']['help'] = [
      '#type' => 'markup',
      // D6 cross-link (enable flags stay on the Home Widgets tab) plus the ST
      // staleness statement, in one string so it is one catalog entry.
      '#markup' => '<p class="description">' . $this->t('Widgets are turned on and off on the Home Widgets tab. Saved changes appear on the public site within about 5 minutes.') . '</p>',
    ];

    $form['theme']['color'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Colors'),
      '#tree' => TRUE,
    ];
    $form['theme']['color']['primary'] = [
      '#type' => 'color',
      '#title' => $this->t('Primary brand color'),
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'color.primary'),
    ];
    $form['theme']['color']['secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary brand color'),
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'color.secondary'),
    ];

    // A bare container, not a second fieldset: `back_ground` has to be its own
    // level in the value tree to produce the `theme.back_ground.secondary`
    // config path, but it is not a second group as far as the editor is
    // concerned, and a duplicate "Colors" heading would only confuse. The
    // field's own title carries the meaning.
    $form['theme']['back_ground'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['theme']['back_ground']['secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary background color'),
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'back_ground.secondary'),
    ];

    // A real fieldset, unlike `back_ground` above: this is a second group as
    // far as the editor is concerned -- the hero banner is a distinct surface
    // from the brand palette, and the two are now set independently.
    $form['theme']['hero'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Hero Banner Colors'),
      '#tree' => TRUE,
    ];
    $form['theme']['hero']['primary'] = [
      '#type' => 'color',
      '#title' => $this->t('Primary hero color'),
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'hero.primary'),
    ];
    $form['theme']['hero']['secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary hero color'),
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'hero.secondary'),
    ];

    // D7: the columns field does not exist at all on BSL sites. Its validator
    // is scoped to the element's presence, so hiding it here also disables the
    // validation and the write.
    if (!$this->isBiosafetyLand($config)) {
      $form['theme']['home_page_widgets'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Home Page Widget Columns'),
        '#tree' => TRUE,
      ];

      $options = $this->columnWidgetOptions();
      $columns = $this->leaf($defaults, 'home_page_widgets.columns');
      $columns = is_array($columns) ? array_values($columns) : [];

      for ($i = 0; $i < BiolandThemeContract::HOME_PAGE_WIDGETS_COLUMN_COUNT; $i++) {
        $column = isset($columns[$i]) && is_array($columns[$i]) ? $columns[$i] : [];
        $form['theme']['home_page_widgets']['columns'][$i] = [
          '#type' => 'select',
          '#title' => $this->t('Column @number', ['@number' => $i + 1]),
          '#options' => $options,
          '#multiple' => TRUE,
          '#default_value' => array_values(array_intersect($column, array_keys($options))),
          '#size' => min(max(count($options), 5), 12),
        ];
      }
    }

    $form['theme']['mega_menu'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mega Menu'),
      '#tree' => TRUE,
    ];

    // Blanking one of the three optional numbers is a deliberate no-op rather
    // than a clear -- submitSectionForm() skips a blank so an unrelated save
    // cannot discard a bound the editor set earlier. Without saying so the
    // editor blanks the field, sees "saved", and watches the old value come
    // back unexplained. Chosen over a post-save status message because it
    // pre-empts the confusion instead of explaining it afterwards, needs no
    // was-it-authored bookkeeping in the writer, and is the only variant that
    // also reaches an editor who never presses Save. @reset carries the
    // already-translated label of the control that DOES un-author (RS), so
    // the sentence stays consistent with the button in every catalog.
    $keepsCurrentValue = $this->t('Leave blank to keep the current value. Use @reset to remove it.', [
      '@reset' => $this->t('Reset to network default'),
    ]);
    $form['theme']['mega_menu']['max_columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum columns'),
      '#min' => BiolandThemeContract::MEGA_MENU_MAX_COLUMNS_MIN,
      '#max' => BiolandThemeContract::MEGA_MENU_MAX_COLUMNS_MAX,
      '#step' => 1,
      '#description' => $keepsCurrentValue,
      '#default_value' => $this->leaf($defaults, 'mega_menu.max_columns'),
    ];
    $form['theme']['mega_menu']['max_rows_per_column'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum rows per column (0 for no limit)'),
      '#min' => BiolandThemeContract::MEGA_MENU_MAX_ROWS_PER_COLUMN_UNLIMITED,
      '#step' => 1,
      '#description' => $keepsCurrentValue,
      // Presence, not truthiness: 0 means "unlimited" and is a real value.
      '#default_value' => $this->leaf($defaults, 'mega_menu.max_rows_per_column'),
    ];
    $form['theme']['mega_menu']['horizontal_card_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum horizontal cards'),
      '#min' => BiolandThemeContract::MEGA_MENU_HORIZONTAL_CARD_MAX_MIN,
      '#max' => BiolandThemeContract::MEGA_MENU_HORIZONTAL_CARD_MAX_MAX,
      '#step' => 1,
      '#description' => $keepsCurrentValue,
      '#default_value' => $this->leaf($defaults, 'mega_menu.horizontal_card_max'),
    ];

    $form['theme']['i18n'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Languages'),
      '#tree' => TRUE,
    ];
    $form['theme']['i18n']['max_lang_before_wrap'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum languages shown before the language bar wraps'),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
      '#default_value' => $this->leaf($defaults, 'i18n.max_lang_before_wrap'),
    ];

    // RS: the way back to the D2 fall-through. #limit_validation_errors is
    // empty so a site with an incomplete form can still reset, and the confirm
    // dialog makes the deletion deliberate. The message is a static, translated
    // literal passed through json_encode(), so it is a well-formed JS string
    // literal whatever the active catalog contains. ConfigFormBase::buildForm()
    // adds `actions.submit` afterwards without replacing this array, and the
    // weight keeps Save first.
    //
    // KNOWN GAP, accepted: the confirmation is client-side only, so with
    // JavaScript disabled the POST deletes the theme without prompting. RS
    // asks for a confirm dialog and this is one, and the blast radius is
    // bounded -- the handler clears `theme` and nothing else, the site falls
    // straight back through the D2 chain, and it renders exactly as an
    // unseeded site, so an accidental reset costs re-authoring rather than
    // data loss. Closing the gap properly means a ConfirmFormBase on its own
    // route with its own form class, cancel link, routing entry and catalog
    // strings -- a change far larger than the risk it removes, and out of
    // scope for this task.
    $confirm = json_encode((string) $this->t('This deletes the theme settings for this site. It cannot be undone.'));
    $form['actions']['theme_reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset to network default'),
      '#submit' => ['::submitResetToNetworkDefault'],
      '#limit_validation_errors' => [],
      '#weight' => 10,
      '#attributes' => ['onclick' => 'return confirm(' . $confirm . ');'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValue('theme');
    $values = is_array($values) ? $values : [];

    // Defence in depth, deliberately NOT reachable through the UI. Core's
    // Color::validateColor() runs as the element's own #element_validate and
    // normalizes or rejects anything malformed before this ever sees it, so
    // in a real request this loop only ever confirms an already-valid value.
    // It is kept, and exercised by the unit harness, because it is the only
    // assertion that survives if the element type is ever changed from
    // `color` to `textfield` -- a one-word edit that would otherwise silently
    // remove all hex validation. Do not delete it as dead code.
    // Derived from the colour table, never restated: a sixth colour key added
    // to the contract would otherwise ship with no server-side hex check, and
    // surviving exactly that kind of edit is this loop's whole purpose. The
    // two flavor tables carry identical key sets, pinned by
    // testFallbackColorsAreLowerCase().
    foreach (array_keys(self::FALLBACK_COLORS_BL2) as $path) {
      $value = $this->leaf($values, $path);
      if ($value === NULL || $value === '') {
        // Emptiness is #required's business, not this validator's.
        continue;
      }
      if (!is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
        $form_state->setErrorByName(
          'theme][' . str_replace('.', '][', $path),
          $this->t('Enter a valid hex color, for example #1B7B3A.')
        );
      }
    }

    // D3 bounds, re-checked here. Core's Number::validateNumber() enforces
    // #min/#max for a submitted number, but it EARLY-RETURNS on '' -- and an
    // empty optional field is exactly the case that used to reach the writer
    // and be cast to 0, landing `max_columns: 0` outside D3's 1-6. The writer
    // now skips a blank value instead of coercing it (see
    // submitSectionForm()), and this loop is the matching server-side bound
    // for everything that is not blank, so no path can author an
    // out-of-range value.
    foreach ([
      'mega_menu.max_columns' => [
        BiolandThemeContract::MEGA_MENU_MAX_COLUMNS_MIN,
        BiolandThemeContract::MEGA_MENU_MAX_COLUMNS_MAX,
      ],
      'mega_menu.horizontal_card_max' => [
        BiolandThemeContract::MEGA_MENU_HORIZONTAL_CARD_MAX_MIN,
        BiolandThemeContract::MEGA_MENU_HORIZONTAL_CARD_MAX_MAX,
      ],
    ] as $path => [$min, $max]) {
      $value = $this->leaf($values, $path);
      if ($this->isBlank($value)) {
        // Optional (D3 names neither as required): blank means "not
        // authored", and the writer leaves the key alone.
        continue;
      }
      if (!is_numeric($value) || (int) $value < $min || (int) $value > $max) {
        $form_state->setErrorByName(
          'theme][' . str_replace('.', '][', $path),
          $this->t('Enter a number between @min and @max.', ['@min' => $min, '@max' => $max])
        );
      }
    }

    // D7 / W2a: scoped to the element actually being present. On a BSL site the
    // build leg never creates it, so this validator never runs there -- the
    // scope cannot drift away from the build leg, because it IS the build leg.
    if (!isset($form['theme']['home_page_widgets']['columns'])) {
      return;
    }

    $columns = $values['home_page_widgets']['columns'] ?? NULL;
    $columns = is_array($columns) ? $columns : [];

    // W2a counts the OUTER length -- the number of grid columns -- never the
    // total number of widgets across them.
    if (count($columns) !== BiolandThemeContract::HOME_PAGE_WIDGETS_COLUMN_COUNT) {
      $form_state->setErrorByName(
        'theme][home_page_widgets][columns',
        $this->t('The home page widgets must be arranged in exactly @count columns.', [
          '@count' => BiolandThemeContract::HOME_PAGE_WIDGETS_COLUMN_COUNT,
        ])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValue('theme');
    $values = is_array($values) ? $values : [];

    $config->set('theme.color.primary', $this->normalizeHex($this->leaf($values, 'color.primary')));
    $config->set('theme.color.secondary', $this->normalizeHex($this->leaf($values, 'color.secondary')));
    // Literally `back_ground`, never `background`. See the class docblock.
    $config->set('theme.back_ground.secondary', $this->normalizeHex($this->leaf($values, 'back_ground.secondary')));
    // Two scalars, never the legacy `hero.primary: [primary, secondary]` list.
    // See the class docblock for why the shape differs from the network theme
    // and how the head reconciles the two.
    $config->set('theme.hero.primary', $this->normalizeHex($this->leaf($values, 'hero.primary')));
    $config->set('theme.hero.secondary', $this->normalizeHex($this->leaf($values, 'hero.secondary')));

    // D7: never written where the field was never rendered.
    if (isset($form['theme']['home_page_widgets']['columns'])) {
      $columns = $this->normalizeColumns($values['home_page_widgets']['columns'] ?? []);
      // Three empty selects mean "the editor authored nothing", not "the
      // editor chose no widgets" -- the same blank-guard doctrine the three
      // optional numerics use below, applied to the one field whose blank
      // shape is not '' but [[], [], []].
      //
      // The case this exists for: when the dmsm seed cannot be read, the three
      // column selects render with no options selected. A blind Save then
      // posts three empty columns, which passes every validator (W2a counts
      // the OUTER length, and three empty columns is still three columns) and
      // pins the site to zero home page widgets with no error shown. Skipping
      // the set() leaves whatever was authored exactly as it was, and RS's
      // "Reset to network default" stays the deliberate way to un-author.
      if (array_filter($columns)) {
        $config->set('theme.home_page_widgets.columns', $columns);
      }
    }

    // The three optional numerics: a blank field is NOT a zero. `(int) ''` is
    // 0, which for max_columns and horizontal_card_max is outside D3's 1-6
    // entirely, and for max_rows_per_column silently means "unlimited" -- a
    // real setting the editor never chose. Skipping the set() is what "the
    // editor did not author this" looks like in a partial config update: the
    // key stays exactly as it was, so nothing is fabricated and nothing
    // already authored is destroyed. Skip rather than clear() deliberately --
    // clearing would let an unrelated save (a colour tweak on a form whose
    // number failed to populate) silently discard a bound the editor set
    // earlier, and RS's "Reset to network default" is the deliberate,
    // confirmed way to un-author.
    //
    // isBlank(), never empty(): a submitted 0 is a real authored value --
    // max_rows_per_column: 0 IS "unlimited" -- and must be written.
    foreach ([
      'mega_menu.max_columns',
      'mega_menu.max_rows_per_column',
      'mega_menu.horizontal_card_max',
      // #required, so core rejects a blank before submit runs; guarded the
      // same way anyway so no numeric key can regress to the (int) '' cast.
      'i18n.max_lang_before_wrap',
    ] as $path) {
      $value = $this->leaf($values, $path);
      if ($this->isBlank($value)) {
        continue;
      }
      $config->set('theme.' . $path, (int) $value);
    }
  }

  /**
   * Whether a submitted value is "not authored" rather than a real value.
   *
   * NULL (the field was never present) and '' (the field was rendered and
   * left empty) are blank. `0`, `'0'` and FALSE are NOT -- they are real
   * authored values, and mega_menu.max_rows_per_column: 0 in particular is
   * the documented way to say "no limit".
   *
   * @param mixed $value
   *   The raw submitted value.
   *
   * @return bool
   *   TRUE when nothing was authored.
   */
  protected function isBlank($value): bool {
    return $value === NULL || $value === '';
  }

  /**
   * Deletes the authored theme so the site falls back to the network default.
   *
   * Clears the `theme` key of `bioland.settings` -- not the config object --
   * restoring the D2 fall-through chain. After this the site renders exactly
   * as a site that never opened this tab.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitResetToNetworkDefault(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('bioland.settings');
    $config->clear(self::CONFIG_KEY);
    $config->save();

    $this->messenger()->addStatus($this->t('The theme settings for this site have been reset to the network default.'));
  }

  /**
   * Whether this site is a Biosafety Clearing-House site.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The bioland.settings configuration object.
   *
   * @return bool
   *   TRUE on a BSL site.
   */
  protected function isBiosafetyLand($config): bool {
    return (bool) $config->get('is_biosafety_land');
  }

  /**
   * The form's default values: the authored theme, else the dmsm seed.
   *
   * Presence, not truthiness: a stored theme subtree wins as soon as it
   * exists, even if every value in it is falsy.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The bioland.settings configuration object.
   *
   * @return array
   *   The snake_case theme defaults. Never empty: when nothing is authored
   *   seedFromDmsm() takes over, and that always returns at least the three
   *   colour keys (from this flavor's built-in fallbacks when the seed cannot
   *   be read).
   */
  protected function themeDefaults($config): array {
    $authored = $config->get(self::CONFIG_KEY);

    if (is_array($authored) && $authored !== []) {
      // Through withFallbackColors(), NOT returned verbatim. Every site that
      // saved this tab before BL-1011 has colours but no `hero` key at all, so
      // a verbatim return leaves both new pickers with a NULL #default_value.
      // `<input type="color">` has no empty state: the editor sees black,
      // #required is satisfied by the #000000 it posts, and the next Save --
      // even one that only touches the mega menu -- writes a black hero and
      // ends the site's inherited hero downstream. That is the exact outcome
      // the class docblock calls dangerous, and it was guarded only on the
      // unseeded path.
      //
      // Seed the hero from the site's own effective dmsm theme first, so a
      // site whose hero deliberately differs from its brand palette (e.g.
      // Belgium's) keeps that hero rather than being handed the flavor's
      // network default. Only the missing/unusable hero is touched here --
      // withHeroPair() is skipped entirely once a usable pair is already
      // authored, so an editor's own choice is never overwritten by a seed
      // fetched on an unrelated Save.
      if (!$this->hasUsableHeroPair($authored)) {
        $effective = $this->effectiveDmsmTheme();
        if (is_array($effective)) {
          $authored = $this->withHeroPair($authored, $effective);
        }
      }

      // The helper fills any colour key still missing or empty (including a
      // hero the site never had and this dmsm fetch could not supply), so it
      // is a no-op for every colour these sites did author. A site that
      // wants its network-supplied pair back has "Reset to network default".
      return $this->withFallbackColors($authored, $this->isBiosafetyLand($config));
    }

    return $this->seedFromDmsm($this->isBiosafetyLand($config));
  }

  /**
   * Translates the effective dmsm theme into snake_case D4 defaults.
   *
   * Read-only -- still no config write on GET, so D5 is intact. Anything the
   * effective theme does not define is absent from the result and the
   * corresponding field falls back to its own empty default, with ONE
   * exception: the three colour keys always end up populated, from this
   * flavor's built-in fallbacks when the seed does not supply them. See
   * self::FALLBACK_COLORS_BL2 for why an empty colour field is not a safe
   * outcome.
   *
   * An unreadable seed is SILENT by design. Sites are pre-seeded by an ops
   * script, so a missing seed here is a rare infrastructure blip rather than
   * something the editor can act on, and the built-in flavor defaults already
   * put a correct network palette in front of them -- a warning telling them
   * to "check every value" would be advice about values that are already
   * right, on every unseeded page load.
   *
   * Memoized (M5): the underlying call is a synchronous 10 second HTTP
   * request, and the build leg runs again on every validation rebuild. Only
   * the PRE-fallback seed is cached; withFallbackColors() runs on every call,
   * cache hit included, so the flavor argument always decides the palette.
   * See self::$seedCache.
   *
   * @param bool $isBiosafetyLand
   *   TRUE on a BSL site, which picks the BSL colour fallbacks. Required
   *   rather than defaulted: a caller that forgets would silently hand a BSL
   *   editor the bl2 palette.
   *
   * @return array
   *   The snake_case defaults. Never empty: the colour keys are always
   *   present.
   */
  protected function seedFromDmsm(bool $isBiosafetyLand): array {
    if ($this->seedCache !== NULL) {
      return $this->withFallbackColors($this->seedCache, $isBiosafetyLand);
    }

    $effective = $this->effectiveDmsmTheme();

    if (!is_array($effective)) {
      $this->seedCache = [];

      return $this->withFallbackColors($this->seedCache, $isBiosafetyLand);
    }

    $defaults = [];
    foreach (self::SEED_PATHS as $configPath => $headPath) {
      $value = $this->leaf($effective, $headPath);
      if ($value === NULL) {
        continue;
      }
      // Build the snake_case nesting one segment at a time.
      $segments = explode('.', $configPath);
      $cursor = &$defaults;
      foreach ($segments as $index => $segment) {
        if ($index === count($segments) - 1) {
          $cursor[$segment] = $value;
          break;
        }
        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
          $cursor[$segment] = [];
        }
        $cursor = &$cursor[$segment];
      }
      unset($cursor);
    }

    $defaults = $this->withHeroPair($defaults, $effective);

    $this->seedCache = $defaults;

    return $this->withFallbackColors($this->seedCache, $isBiosafetyLand);
  }

  /**
   * Applies the seed's hero pair to the defaults, both slots or neither.
   *
   * The hero is the one seed value read out of a LIST by index rather than
   * off a named string key, so it is also the one with no type guarantee: a
   * short pair, a null slot, or a non-colour string all reach here. Anything
   * that is not a renderable hex colour has to be dropped, because these are
   * `#type => color` elements and core turns an empty picker into #000000 on
   * the first Save.
   *
   * It is dropped as a PAIR, deliberately, which is why this does not live in
   * the generic per-leaf seed loop. Filling only the broken slot from the
   * flavor palette would pair a site's real hero primary with a network
   * default secondary -- a combination no document anywhere defines, and one
   * an editor has no way to recognise as wrong. Falling back to the flavor's
   * own pair is at least a palette that exists. Either way withFallbackColors()
   * supplies what this method declines to.
   *
   * @param array $defaults
   *   The snake_case defaults built from the seed so far.
   * @param array $effective
   *   The effective camelCase dmsm theme.
   *
   * @return array
   *   The defaults, with the hero pair applied only if both slots are usable.
   */
  protected function withHeroPair(array $defaults, array $effective): array {
    $pair = [];

    foreach (self::HERO_SEED_PATHS as $configPath => $headPath) {
      $value = $this->leaf($effective, $headPath);
      if (!$this->isHexColor($value)) {
        // One unusable slot disqualifies the whole pair.
        return $defaults;
      }
      [, $key] = explode('.', $configPath);
      // Lower-cased, like the fallback tables and like normalizeHex() on
      // submit. `<input type="color">` reports a lower-case value, so an
      // upper-case default would claim a change the editor never made.
      $pair[$key] = $this->normalizeHex($value);
    }

    $defaults['hero'] = $pair;

    return $defaults;
  }

  /**
   * Fills any colour key the seed did not supply from the flavor's fallbacks.
   *
   * Per leaf, and only for the five colour keys (the three brand/background
   * ones plus the two hero ones): a seed that carries
   * `color.primary` but not `back_ground.secondary` keeps its own primary and
   * gains only the missing background. Nothing else is fabricated -- the
   * numeric and widget fields are safe to leave empty, because an empty
   * numeric is now skipped by the writer rather than cast to 0.
   *
   * @param array $defaults
   *   The snake_case defaults built from the seed.
   * @param bool $isBiosafetyLand
   *   TRUE on a BSL site, selecting self::FALLBACK_COLORS_BSL over
   *   self::FALLBACK_COLORS_BL2.
   *
   * @return array
   *   The same defaults with every colour key present.
   */
  protected function withFallbackColors(array $defaults, bool $isBiosafetyLand): array {
    $fallbacks = $isBiosafetyLand ? self::FALLBACK_COLORS_BSL : self::FALLBACK_COLORS_BL2;

    foreach ($fallbacks as $path => $color) {
      [$group, $key] = explode('.', $path);
      if (!isset($defaults[$group]) || !is_array($defaults[$group])) {
        $defaults[$group] = [];
      }
      if (!array_key_exists($key, $defaults[$group]) || $defaults[$group][$key] === '') {
        $defaults[$group][$key] = $color;
      }
    }

    return $defaults;
  }

  /**
   * The dmsm config service, or NULL when the container has none.
   *
   * \Drupal::hasService() first, deliberately: \Drupal::service() THROWS
   * ServiceNotFoundException for an unregistered id rather than returning
   * NULL, so without this guard the caller's `instanceof` check could never
   * see a missing service and the tab would fatal instead of degrading. The
   * guard is what makes this method's documented NULL return real.
   *
   * @return \Drupal\bioland\Service\BiolandDmsmConfigService|null
   *   The service, or NULL when the container has none.
   */
  protected function dmsmConfigService() {
    if (!\Drupal::hasService(self::DMSM_SERVICE_ID)) {
      return NULL;
    }

    return \Drupal::service(self::DMSM_SERVICE_ID);
  }

  /**
   * The raw effective dmsm theme, fetched and memoized at most once.
   *
   * Shared by seedFromDmsm() (unseeded sites) and themeDefaults()'s authored
   * branch (a site with saved colours but no hero yet), so a request that
   * needs the seed from both paths still makes the 10 second HTTP call only
   * once. See self::$effectiveThemeCache.
   *
   * @return array|null
   *   The camelCase effective theme, or NULL when it could not be read.
   */
  protected function effectiveDmsmTheme(): ?array {
    if ($this->effectiveThemeFetched) {
      return $this->effectiveThemeCache;
    }

    $service = $this->dmsmConfigService();
    $effective = $service instanceof BiolandDmsmConfigService
      ? $service->getEffectiveTheme()
      : NULL;

    if (!is_array($effective)) {
      // Silent to the editor, never to the operator: the built-in defaults
      // shown in their place are correct for this network, so a warning on
      // screen would be noise, but nothing else records that the seed failed.
      \Drupal::logger('bioland')->warning('Could not read the dmsm theme seed for this site; the Theme tab is falling back to the built-in network colour defaults for this flavor.');

      $effective = NULL;
    }

    $this->effectiveThemeCache = $effective;
    $this->effectiveThemeFetched = TRUE;

    return $this->effectiveThemeCache;
  }

  /**
   * Whether an authored theme already carries a renderable hero pair.
   *
   * Guards withHeroPair() on the authored path: that method unconditionally
   * overwrites `hero` once it finds a usable pair in the seed, which is
   * correct when building fresh defaults but would clobber an editor's own
   * saved hero if applied unconditionally here. Both slots are required --
   * a half-authored hero is exactly the "one unusable slot disqualifies the
   * pair" case withHeroPair() itself already treats as absent.
   *
   * @param array $authored
   *   The site's saved theme subtree.
   *
   * @return bool
   *   TRUE when both hero slots are present and render-safe.
   */
  protected function hasUsableHeroPair(array $authored): bool {
    return $this->isHexColor($this->leaf($authored, 'hero.primary'))
      && $this->isHexColor($this->leaf($authored, 'hero.secondary'));
  }

  /**
   * The widget options an editor may place in a column.
   *
   * Keyed by head theme-name (what gets stored), labelled with the same
   * wording the Home Widgets tab uses. Only registry entries classified
   * AUTHORABLE appear: the PLACEMENT_FIXED ones (latest_news_widget,
   * national_targets_widget) are already rendered unconditionally outside the
   * column mechanism, so offering them here would double-render them, and the
   * LEGACY_NON_AUTHORABLE BSL entries have no theme-name at all.
   *
   * @return array
   *   Option labels keyed by theme-name.
   */
  protected function columnWidgetOptions(): array {
    // Every label below is byte-identical to the one BiolandHomeWidgetsForm
    // already uses for the same widget, so the option list reuses translations
    // that exist in all 67 catalogs instead of inventing parallel wording. The
    // registry, not this map, decides which of them are actually offered.
    $labels = [
      'gbif_widget' => $this->t('GBIF Widget'),
      'panorama_solutions_widget' => $this->t('Panorama Solutions Widget'),
      'elearning_widget' => $this->t('E-Learning Widget'),
      'implementation_widget' => $this->t('Implementation Widget'),
      'technical_cooperation_widget' => $this->t('Technical & Scientific Cooperation Widget'),
      'latest_discussions_widget' => $this->t('Latest Discussions Widget'),
      'content_statistics_widget' => $this->t('Content Statistics Widget'),
      'geobon_widget' => $this->t('GEOBON Widget'),
    ];

    $options = [];

    foreach (BiolandHomeWidgetRegistry::authorableKeys() as $key) {
      $themeName = BiolandHomeWidgetRegistry::themeNameFor($key);
      if ($themeName === NULL) {
        continue;
      }
      $options[$themeName] = $labels[$key] ?? $themeName;
    }

    return $options;
  }

  /**
   * Normalizes a submitted column set to a list of lists of theme-names.
   *
   * Two things matter here (M4). First, every level is re-indexed with
   * array_values(): removing or reordering a widget leaves gaps in the PHP
   * array, and a gapped array serializes to a JSON object rather than an
   * array, which the head then cannot iterate. The precedent is
   * BiolandHomeWidgetsForm::normalizeContentTypes(). Second, values are
   * filtered against the registry's authorable set, so a stale or forged
   * submission can never write a placement-fixed or unknown widget name.
   *
   * @param mixed $columns
   *   The raw submitted columns.
   *
   * @return array
   *   A re-indexed list of re-indexed lists of authorable theme-names.
   */
  protected function normalizeColumns($columns): array {
    if (!is_array($columns)) {
      return [];
    }

    $authorable = BiolandHomeWidgetRegistry::authorableThemeNames();
    $normalized = [];

    foreach ($columns as $column) {
      if (!is_array($column)) {
        $normalized[] = [];
        continue;
      }
      $names = array_filter(
        array_values($column),
        static fn($name): bool => is_string($name) && in_array($name, $authorable, TRUE)
      );
      $normalized[] = array_values(array_unique($names));
    }

    return array_values($normalized);
  }

  /**
   * Normalizes a submitted hex colour to lower-case `#rrggbb`.
   *
   * @param mixed $value
   *   The raw submitted value.
   *
   * @return string
   *   The normalized colour, or '' when there was nothing to normalize.
   */
  protected function normalizeHex($value): string {
    return is_string($value) ? strtolower(trim($value)) : '';
  }

  /**
   * Whether a value is a six-digit hex colour a picker can render.
   *
   * Used on the hero seed only, where the value is read out of a list by index
   * and so carries no type guarantee. The brand colour seeds land on named
   * string keys and need no such check.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return bool
   *   TRUE when the value is a string of the form #RRGGBB.
   */
  protected function isHexColor($value): bool {
    return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value)) === 1;
  }

  /**
   * Reads a dot-path leaf out of a nested array.
   *
   * Presence-aware: returns NULL only when the path is genuinely absent, so
   * `0`, `false` and `''` survive as the real values they are.
   *
   * @param array $data
   *   The nested array.
   * @param string $path
   *   The dot-separated path.
   *
   * @return mixed
   *   The value, or NULL when the path is absent.
   */
  protected function leaf(array $data, string $path) {
    $cursor = $data;

    foreach (explode('.', $path) as $segment) {
      if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
        return NULL;
      }
      $cursor = $cursor[$segment];
    }

    return $cursor;
  }

}
