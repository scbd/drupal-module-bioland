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
   * Home page widget column order. Vocabulary owned by the widget registry
   * (p01-05), not this contract or the schema.
   */
  public const KEY_HOME_PAGE_WIDGETS_COLUMNS = 'home_page_widgets.columns';

  /**
   * Whether Forums is shown in the mega menu.
   */
  public const KEY_MEGA_MENU_FORUMS = 'mega_menu.forums';

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
    self::KEY_MEGA_MENU_FORUMS,
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

}
