<?php

namespace Drupal\bioland;

/**
 * Canonical key set for the `bioland.settings:theme` config mapping.
 *
 * This is the single PHP source of truth for the theme config keys declared
 * in config/schema/bioland.schema.yml. Nothing writes this config yet (this
 * task ships dark); p02-01's Theme tab form is the first writer, and its
 * schema<->writer conformance test asserts that the writer's key set, this
 * contract's KEYS, and the schema mapping's keys are all identical.
 *
 * Only the live keys per plan `index.md` Decision D4 are listed. Dead keys
 * from the legacy/pre-plan surface (text.*, *_text_over, can_auto_translate,
 * back_ground.primary, back_ground.tertiary, mega_menu.column_wrap,
 * mega_menu.horizontal_card_wrap, mega_menu.show_empty, colums_width) are
 * deliberately absent and must never be reintroduced.
 *
 * The `back_ground` trap: the key is spelled `back_ground` (snake_case,
 * two words), never `background`. bioland-head camelCases the whole
 * `biolandSettings` payload at depth 7
 * (server/utils/context-unified.ts:354,356), turning `back_ground` into
 * `backGround` -- the exact property head's language bar reads
 * (app/components/page/header/language-bar.vue:59,
 * `siteStore.theme.backGround.secondary`). Spelling this key `background`
 * would camelCase to `background` and silently miss that consumer.
 *
 * That consumer is not wired to this config yet. `siteStore.theme` today
 * resolves `config.theme || config.runTime.theme` (bioland-head
 * app/stores/site.js:145-147), not `biolandSettings.theme`, and the depth-7
 * camelCase only rewrites `config.runTime.biolandSettings`. These keys reach
 * head only once p03-01 lands the precedence leg.
 *
 * Besides the key names, this class also owns the two per-flavor fallback
 * PRIMARY colours (self::FALLBACK_PRIMARY_BL2 / self::FALLBACK_PRIMARY_BSL).
 * The pair is shared by the Theme tab's pickers and by
 * BiolandComponentMenuFormMode::primaryColor(); the bl2 value is additionally
 * baked into css/bioland.ckeditor.css. None of those consumers owns the
 * others, so the constants live here; see them for why only `color.primary`
 * was hoisted.
 *
 * @see \Drupal\Tests\bioland\Unit\BiolandThemeContractTest
 */
final class BiolandThemeContract {

  /**
   * Primary brand color.
   */
  public const KEY_COLOR_PRIMARY = 'color.primary';

  /**
   * Secondary brand color.
   */
  public const KEY_COLOR_SECONDARY = 'color.secondary';

  /**
   * Secondary background color. See the `back_ground` trap above.
   */
  public const KEY_BACK_GROUND_SECONDARY = 'back_ground.secondary';

  /**
   * Home page widget columns: a list of grid columns, each an ordered list
   * of widget machine names (a sequence of sequences, not a flat list -- see
   * bioland-head app/components/page/home-chm.vue:18-20). Vocabulary owned by
   * the widget registry (p01-05), not this contract or the schema.
   */
  public const KEY_HOME_PAGE_WIDGETS_COLUMNS = 'home_page_widgets.columns';

  /**
   * Maximum mega menu columns.
   */
  public const KEY_MEGA_MENU_MAX_COLUMNS = 'mega_menu.max_columns';

  /**
   * Maximum mega menu rows per column.
   */
  public const KEY_MEGA_MENU_MAX_ROWS_PER_COLUMN = 'mega_menu.max_rows_per_column';

  /**
   * Maximum horizontal cards in the mega menu.
   */
  public const KEY_MEGA_MENU_HORIZONTAL_CARD_MAX = 'mega_menu.horizontal_card_max';

  /**
   * Maximum languages shown before the language bar wraps.
   */
  public const KEY_I18N_MAX_LANG_BEFORE_WRAP = 'i18n.max_lang_before_wrap';

  /**
   * The complete, canonical set of `theme` config keys (dot-path notation).
   *
   * This is the list p02-01's writer must produce exactly, and the schema's
   * `theme` mapping must declare exactly -- no more, no fewer.
   */
  public const KEYS = [
    self::KEY_COLOR_PRIMARY,
    self::KEY_COLOR_SECONDARY,
    self::KEY_BACK_GROUND_SECONDARY,
    self::KEY_HOME_PAGE_WIDGETS_COLUMNS,
    self::KEY_MEGA_MENU_MAX_COLUMNS,
    self::KEY_MEGA_MENU_MAX_ROWS_PER_COLUMN,
    self::KEY_MEGA_MENU_HORIZONTAL_CARD_MAX,
    self::KEY_I18N_MAX_LANG_BEFORE_WRAP,
  ];

  /**
   * Keys D3 requires to be present with a non-empty value.
   *
   * D3's validation logic itself (the actual required-field check) belongs
   * to p02-01's form validators, not this contract -- these constants only
   * pin which keys D3 names as required.
   */
  public const REQUIRED_KEYS = [
    self::KEY_BACK_GROUND_SECONDARY,
    self::KEY_I18N_MAX_LANG_BEFORE_WRAP,
    self::KEY_COLOR_PRIMARY,
    self::KEY_COLOR_SECONDARY,
  ];

  /**
   * D3's numeric bounds for the mega menu integer keys.
   *
   * Trivially expressible as constants; the bounds-checking logic itself
   * belongs to p02-01's form validators, not here.
   */
  public const MEGA_MENU_MAX_COLUMNS_MIN = 1;

  public const MEGA_MENU_MAX_COLUMNS_MAX = 6;

  public const MEGA_MENU_MAX_ROWS_PER_COLUMN_UNLIMITED = 0;

  public const MEGA_MENU_HORIZONTAL_CARD_MAX_MIN = 1;

  public const MEGA_MENU_HORIZONTAL_CARD_MAX_MAX = 6;

  /**
   * W2a's required number of home page widget columns.
   *
   * Counts the OUTER length of `home_page_widgets.columns` -- the number of
   * grid columns -- not the total widget count. As with the bounds above,
   * the enforcement logic lives in p02-01's validators, not here.
   */
  public const HOME_PAGE_WIDGETS_COLUMN_COUNT = 3;

  /**
   * Last-resort `color.primary` for a bl2 site with nothing authored.
   *
   * The live Bioland network default (UN blue), read from the network document
   * itself rather than from a test fixture. Fetched 2026-08-06 from the
   * public-allowlisted projection `GET https://dmsm.cbddev.xyz/api/config/prod/bl2`,
   * whose network-level `config.theme` block reads `color.primary` #009edb --
   * kept lower-case because `<input type="color">` normalizes to lower-case and
   * the stored value must round-trip. bl2 is the only prod network
   * (`prod/bsl`, `prod/chm` and `prod/bch` all return HTTP 204).
   *
   * Hoisted here, out of BiolandThemeForm::FALLBACK_COLORS_BL2, because three
   * consumers that do not own each other need the identical value: that form's
   * colour pickers, BiolandComponentMenuFormMode::primaryColor() (the arrow
   * preview and the CKEditor override), and css/bioland.ckeditor.css's baked-in
   * `--bs-primary`. Only `color.primary` is hoisted -- `color.secondary` and
   * `back_ground.secondary` are read by the Theme tab alone, so duplicating
   * them here would add a second source of truth for no consumer.
   *
   * @see \Drupal\bioland\Form\BiolandThemeForm::FALLBACK_COLORS_BL2
   */
  public const FALLBACK_PRIMARY_BL2 = '#009edb';

  /**
   * Last-resort `color.primary` for a BSL site with nothing authored.
   *
   * The BSL counterpart of self::FALLBACK_PRIMARY_BL2; every reason given
   * there for having a built-in colour at all, and for keeping it lower-case,
   * applies unchanged. A BSL site falling back to bl2 blue would be wrong in
   * the same way a black fallback is, only harder to notice.
   *
   * Fetched 2026-08-07 from `GET https://dmsm.cbddev.xyz/api/config/dev/bsl`,
   * whose network-level `config.theme.color` block reads `primary` #fa6938.
   * dev is the only DMSM environment publishing a bsl network config --
   * `prod/bsl` and `staging/bsl` both return HTTP 204 -- so unlike the bl2
   * value this comes from the dev projection of the same document, not prod.
   *
   * @see \Drupal\bioland\Form\BiolandThemeForm::FALLBACK_COLORS_BSL
   */
  public const FALLBACK_PRIMARY_BSL = '#fa6938';

}
