<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test for translation catalog completeness.
 *
 * This test prevents the regression fixed in BUG 4 ("missing UI
 * translations"): several admin UI strings (route titles, local task
 * titles, menu link descriptions, and a handful of form labels) had no
 * msgid/msgstr entry in one or more translations/bioland.<langcode>.po
 * files, or had a placeholder msgstr identical to the English source, so
 * they always rendered in English regardless of the active site language.
 *
 * @group bioland
 * @coversNothing
 */
class TranslationCatalogIntegrityTest extends TestCase {

  /**
   * Strings that must exist, translated, in every non-English .po file.
   *
   * Keyed by the exact English source msgid; the value is unused (kept as
   * a comment of where the string originates) but documents intent.
   */
  private const REQUIRED_MSGIDS = [
    // bioland.links.menu.yml: menu link description.
    'Configure settings including locales, countries, and region.',
    // src/Plugin/Derivative/BiolandMenuLink.php: branded menu link description.
    'Configure @branding settings including locales, countries, and region.',
    // bioland.routing.yml: route _title.
    'Home Page',
    // bioland.links.task.yml: local task title.
    'Home Hero(s)',
    // bioland.routing.yml: route _title / bioland.links.task.yml: local task title.
    'Home Widgets',
    // bioland.links.task.yml: local task title.
    'Mega Menu',
    // bioland.routing.yml: route _title / bioland.links.task.yml: local task title.
    'Tags',
    // src/Form/BiolandSettingsForm.php: fieldset title.
    'Home Page Heros',
    // src/Form/BiolandSettingsForm.php: details title.
    'Order Override Help',
    // src/Form/BiolandSettingsForm.php: text_format field title.
    'Order Override Help Text',
    // src/Form/BiolandSettingsForm.php: text_format field description.
    'Help text explaining the Order Override field and content sorting priority.',
    // src/Form/BiolandSettingsForm.php: details title.
    'Translate Order Override Help Text',
    // src/Form/BiolandSettingsForm.php: submit button value.
    'Reset Sticky',
    // src/Service/BiolandComponentRegistry.php: mega-menu component labels.
    'National Reports',
    'National Report (6th)',
    'BCH Records',
    'ABS-CH Records',
    'National Focal Points',
    'Country Profiles',
    'Content Type',
    'Forums',
    'National Targets (GBF 7)',
    'All Content Types',
    // src/Service/BiolandComponentRegistry.php: mega-menu component descriptions.
    'List of national report links, in tabs by country; hidden when the country has no reports.',
    "Sixth national report links for the site country, plus the link's own children; hidden when empty.",
    'Biosafety Clearing-House records for the country, such as laws and decisions; hidden when empty.',
    'Access and Benefit-sharing Clearing-House records, such as measures and permits; hidden when empty.',
    'List of national focal points, in tabs by country; hidden when empty.',
    'Links to CBD country profile pages, in tabs by country; always shown.',
    'Latest site content of the content types set on this link; hidden when there are no records.',
    "Latest forum threads, plus the link's own children; hidden when empty.",
    'National target cards for GBF target 7, in tabs by country; always shown.',
    "One link per content type that has records, plus the link's own children; always shown.",
    // src/Service/BiolandComponentMenuFormMode.php: Component-mode picker
    // chrome - field label, help text, the two preserved current-value option
    // labels, and the form intro.
    'Mega-menu component',
    'The component this menu link renders in the mega menu.',
    'Legacy: @class',
    '@label (not available on this site)',
    'This menu link renders a mega-menu component instead of a plain list of child links.',
    // bioland.routing.yml: route _title / bioland.links.action.yml: local
    // action title. One string, two declaration sites - both must stay in
    // sync with this entry or the action renders untranslated.
    'Add Mega Menu component',
    // bioland.routing.yml: route _title / bioland.links.task.yml: local task
    // title for the Theme tab.
    'Theme',
    // src/Form/BiolandThemeForm.php: the Theme tab's own strings. Its widget
    // option labels are deliberately NOT listed - that form reuses
    // BiolandHomeWidgetsForm's existing labels verbatim rather than adding
    // parallel wording for the same widgets.
    'Widgets are turned on and off on the Home Widgets tab. Saved changes appear on the public site within about 5 minutes.',
    'Colors',
    'Primary brand color',
    'Secondary brand color',
    'Secondary background color',
    'Home Page Widget Columns',
    'Column @number',
    'Maximum columns',
    'Maximum rows per column (0 for no limit)',
    'Maximum horizontal cards',
    'Languages',
    'Maximum languages shown before the language bar wraps',
    'Reset to network default',
    'This deletes the theme settings for this site. It cannot be undone.',
    'The theme settings for this site have been reset to the network default.',
    'Enter a valid hex color, for example #1B7B3A.',
    'The home page widgets must be arranged in exactly @count columns.',
    // D3's server-side numeric bound. An unreadable dmsm seed is deliberately
    // silent -- the built-in flavor colour defaults are the safety net, so
    // there is no seed-failure message to translate.
    'Enter a number between @min and @max.',
    // The #description on the three optional mega-menu numbers, telling the
    // editor that blanking one keeps the current value rather than clearing
    // it. @reset interpolates the already-listed 'Reset to network default'
    // button label, so the sentence names the control in the same words the
    // button itself uses in every catalog.
    'Leave blank to keep the current value. Use @reset to remove it.',
    // src/Service/BiolandComponentMenuFormMode.php: the content-type
    // sub-select of the Content Type component, its empty option included.
    'Content type',
    '- Select -',
    'The content type this listing shows.',
    // src/Form/BiolandAdminSettingsForm.php: the on/off switch for the
    // component-menu add flow (checkbox title and description).
    'Enable Mega Menu components',
    'Shows the "Add Mega Menu component" action on the menu manage screen. When unchecked, the action and its form are unavailable.',
    // src/Service/BiolandComponentMenuFormMode.php: the thumbnails and
    // column-width presentation controls.
    'Show thumbnails',
    'Show a thumbnail image beside each entry.',
    'Mega menu columns',
    'How many columns of the mega menu this section spans.',
    'Default (1 column)',
    '@count columns',
    '@count columns (extra-large screens only)',
    // src/Service/BiolandComponentMenuFormMode.php: the Content Type rows cap.
    'Maximum rows per column',
    'Site default',
    'Maximum entries listed per column; the site default applies when unset.',
    // src/Service/BiolandComponentMenuFormMode.php: the Content Type title
    // arrow. The preview glyph beside the checkbox is deliberately absent -
    // it is a bare arrow character with no words to translate.
    'Show Arrow',
    'Show an arrow after the section title.',
    // src/Service/BiolandComponentMenuOverview.php: the menu overview screen.
    // The indicator column's two formats and the row operation. The column's
    // own header deliberately reuses the 'Mega Menu' msgid already listed
    // above for the settings tab, so it needs no entry of its own. The
    // component names interpolated into @type are the registry labels, already
    // listed; @schema is a machine slug and is never translated.
    '(Mega Menu: @type)',
    '(Mega Menu: @type: @schema)',
    'Add Mega Menu Child',
  ];

  /**
   * Returns the path to the translations directory.
   */
  private function getTranslationsPath(): string {
    $path = dirname(__DIR__, 2) . '/translations';
    if (!is_dir($path)) {
      $path = __DIR__ . '/../../translations';
    }
    return $path;
  }

  /**
   * Parses a .po file into an associative array of msgid => msgstr.
   *
   * This is a minimal parser sufficient for this module's .po files
   * (single-line msgid/msgstr values only; no plural forms).
   */
  private function parsePoFile(string $file): array {
    $content = file_get_contents($file);
    $entries = [];
    // Single-line msgid/msgstr pairs only; multi-line entries are intentionally skipped (none exist among the required strings).
    $pattern = '/^msgid "((?:[^"\\\\]|\\\\.)*)"\nmsgstr "((?:[^"\\\\]|\\\\.)*)"/m';
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $msgid = stripcslashes($match[1]);
      $msgstr = stripcslashes($match[2]);
      $entries[$msgid] = $msgstr;
    }
    return $entries;
  }

  /**
   * Test that the translations directory exists and contains locale files.
   */
  public function testTranslationsDirectoryExists(): void {
    $path = $this->getTranslationsPath();
    $this->assertDirectoryExists($path, 'translations directory should exist');

    $files = glob($path . '/bioland.*.po');
    $this->assertNotEmpty($files, 'Should find .po files in translations directory');
  }

  /**
   * Test that every required msgid exists in every shipped .po file.
   *
   * The English (en) file legitimately has msgstr === msgid for most
   * strings (that is a correct identity translation for the English
   * locale), so it is checked for presence only, not for a distinct
   * translation.
   */
  public function testRequiredMsgidsPresentInAllLocales(): void {
    $path = $this->getTranslationsPath();
    $files = glob($path . '/bioland.*.po');
    $this->assertNotEmpty($files, 'Should find .po files in translations directory');

    $missing = [];

    foreach ($files as $file) {
      $entries = $this->parsePoFile($file);
      foreach (self::REQUIRED_MSGIDS as $msgid) {
        if (!array_key_exists($msgid, $entries)) {
          $missing[] = basename($file) . ': ' . $msgid;
        }
      }
    }

    $this->assertEmpty(
      $missing,
      "The following .po files are missing required msgid entries:\n" . implode("\n", $missing)
    );
  }

  /**
   * Short technical nouns that are legitimately kept as English loanwords
   * in some locales' existing translation style (e.g. "Admin" in German,
   * "Tags" in Dutch, "Mega Menu" in Malay/Maltese/Tagalog, "Forums" in
   * French/Dutch/Afrikaans, "Colors" in Catalan). These are excluded from the
   * "must differ from English" check below, since an identical msgstr there is
   * a valid stylistic choice already present elsewhere in these catalogs, not
   * a missed translation.
   */
  private const LOANWORD_TOLERANT_MSGIDS = [
    'Tags',
    'Mega Menu',
    'Forums',
    // Catalan spells this exactly as English does.
    'Colors',
  ];

  /**
   * Test that every required msgid is actually translated (not just present)
   * in every non-English .po file.
   *
   * A msgstr identical to the English msgid is treated as untranslated,
   * mirroring the exact regression this test guards against, except for
   * the short technical nouns in self::LOANWORD_TOLERANT_MSGIDS.
   */
  public function testRequiredMsgidsAreTranslatedInNonEnglishLocales(): void {
    $path = $this->getTranslationsPath();
    $files = glob($path . '/bioland.*.po');
    $this->assertNotEmpty($files, 'Should find .po files in translations directory');

    $untranslated = [];

    foreach ($files as $file) {
      // The English catalog is expected to have msgstr === msgid.
      if (basename($file) === 'bioland.en.po') {
        continue;
      }

      $entries = $this->parsePoFile($file);
      foreach (self::REQUIRED_MSGIDS as $msgid) {
        if (in_array($msgid, self::LOANWORD_TOLERANT_MSGIDS, TRUE)) {
          continue;
        }
        if (!array_key_exists($msgid, $entries)) {
          // Already reported by testRequiredMsgidsPresentInAllLocales().
          continue;
        }
        if ($entries[$msgid] === $msgid) {
          $untranslated[] = basename($file) . ': ' . $msgid;
        }
      }
    }

    $this->assertEmpty(
      $untranslated,
      "The following .po files have an untranslated (msgstr === msgid) required entry:\n" . implode("\n", $untranslated)
    );
  }

}
