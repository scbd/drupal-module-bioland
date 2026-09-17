<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandThemeContract;
use PHPUnit\Framework\TestCase;

/**
 * Static-pinning test for BiolandThemeContract.
 *
 * Mirrors the idiom in TranslationCatalogIntegrityTest: no Drupal bootstrap,
 * no assumption that a YAML library is installed (none is, in this
 * pure-unit suite). The schema mapping is read with plain PHP string/regex
 * parsing, using indentation to reconstruct the dot-path key structure, and
 * compared against BiolandThemeContract::KEYS so the constant and the
 * schema cannot silently drift apart.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandThemeContractTest extends TestCase {

  /**
   * D4 dead keys that must never reappear under the `theme` mapping.
   *
   * Matched as case-sensitive substrings of any parsed leaf key path.
   */
  private const DEAD_KEY_FRAGMENTS = [
    'text',
    'text_over',
    'can_auto_translate',
    'back_ground.primary',
    'back_ground.tertiary',
    'column_wrap',
    'horizontal_card_wrap',
    'show_empty',
    'colums_width',
  ];

  /**
   * Returns the path to config/schema/bioland.schema.yml.
   */
  private function getSchemaPath(): string {
    return dirname(__DIR__, 2) . '/config/schema/bioland.schema.yml';
  }

  /**
   * Extracts the raw lines of the `theme:` mapping block.
   *
   * The block starts at the 4-space-indented `theme:` key (a direct child
   * of `bioland.settings.mapping`) and ends at the next line with
   * indentation <= 4 spaces that is not blank, or end of file.
   */
  private function extractThemeBlockLines(string $content): array {
    $lines = explode("\n", $content);
    $block = [];
    $inBlock = FALSE;

    foreach ($lines as $line) {
      if (!$inBlock) {
        if (preg_match('/^ {4}theme:\s*$/', $line)) {
          $inBlock = TRUE;
        }
        continue;
      }

      if (trim($line) !== '' && preg_match('/^( {0,4})\S/', $line)) {
        // Dedent to <= 4 spaces: block is over.
        break;
      }

      $block[] = $line;
    }

    return $block;
  }

  /**
   * Parses leaf field dot-paths out of the theme block's YAML lines.
   *
   * Uses indentation to reconstruct nesting; reserved schema words
   * (type/label/mapping/sequence) are structural and never become path
   * segments. A field is a "leaf" if no deeper field is nested under it.
   */
  private function parseLeafKeys(array $lines): array {
    $reserved = ['type', 'label', 'mapping', 'sequence'];
    $leaves = [];
    /** @var array<int, array{indent: int, key: string, path: string, hasChild: bool}> $stack */
    $stack = [];

    $closeTo = function (int $indent) use (&$stack, &$leaves) {
      while (!empty($stack) && $stack[count($stack) - 1]['indent'] >= $indent) {
        $entry = array_pop($stack);
        if (!$entry['hasChild'] && $entry['path'] !== '') {
          $leaves[] = $entry['path'];
        }
      }
    };

    foreach ($lines as $line) {
      // Only bare "key:" lines (no value after the colon) open a new
      // mapping level; "key: value" lines are scalar and carry no children.
      if (!preg_match('/^(\s*)([\'"]?[\w*-]+[\'"]?):\s*$/', $line, $m)) {
        continue;
      }
      $indent = strlen($m[1]);
      $key = trim($m[2], "'\"");

      $closeTo($indent);

      if (in_array($key, $reserved, TRUE)) {
        // Structural word: does not become a path segment.
        continue;
      }

      if (empty($stack)) {
        // A direct child of `theme:` (the block extracted by
        // extractThemeBlockLines() starts *after* the `theme:` line itself).
        $path = $key;
      }
      else {
        $parentPath = $stack[count($stack) - 1]['path'];
        $stack[count($stack) - 1]['hasChild'] = TRUE;
        $path = $parentPath . '.' . $key;
      }

      $stack[] = [
        'indent' => $indent,
        'key' => $key,
        'path' => $path,
        'hasChild' => FALSE,
      ];
    }

    $closeTo(0);

    return $leaves;
  }

  /**
   * Parses EVERY declared dot-path under the theme block, not just leaves.
   *
   * Unlike parseLeafKeys(), this also matches `key: value` scalar lines and
   * records intermediate mapping paths, so a dead key survives here even if
   * it is declared inline or as a container. That is what makes the dead-key
   * assertion able to fail independently of the contract<->schema pin.
   */
  private function parseAllKeyPaths(array $lines): array {
    $reserved = ['type', 'label', 'mapping', 'sequence'];
    $paths = [];
    /** @var array<int, array{indent: int, path: string}> $stack */
    $stack = [];

    foreach ($lines as $line) {
      // Comments are prose, not declarations.
      if (preg_match('/^\s*#/', $line)) {
        continue;
      }
      if (!preg_match('/^(\s*)([\'"]?[\w*-]+[\'"]?):(\s|$)/', $line, $m)) {
        continue;
      }
      $indent = strlen($m[1]);
      $key = trim($m[2], "'\"");

      while (!empty($stack) && $stack[count($stack) - 1]['indent'] >= $indent) {
        array_pop($stack);
      }

      if (in_array($key, $reserved, TRUE)) {
        // Structural word: never a path segment.
        continue;
      }

      $path = empty($stack) ? $key : $stack[count($stack) - 1]['path'] . '.' . $key;
      $paths[] = $path;
      $stack[] = ['indent' => $indent, 'path' => $path];
    }

    return $paths;
  }

  /**
   * Returns the raw lines of the `home_page_widgets.columns` block.
   *
   * Runs from the `columns:` line to the next line indented at or above it.
   */
  private function extractColumnsBlockLines(): array {
    $lines = $this->extractThemeBlockLines(file_get_contents($this->getSchemaPath()));
    $block = [];
    $baseIndent = NULL;

    foreach ($lines as $line) {
      if ($baseIndent === NULL) {
        if (preg_match('/^(\s*)columns:\s*$/', $line, $m)) {
          $baseIndent = strlen($m[1]);
          $block[] = $line;
        }
        continue;
      }
      if (trim($line) === '') {
        continue;
      }
      if (strlen($line) - strlen(ltrim($line)) <= $baseIndent) {
        break;
      }
      $block[] = $line;
    }

    return $block;
  }

  /**
   * Returns the sorted set of leaf dot-path keys declared under `theme`.
   */
  private function getSchemaThemeKeys(): array {
    $content = file_get_contents($this->getSchemaPath());
    $lines = $this->extractThemeBlockLines($content);
    $keys = $this->parseLeafKeys($lines);
    sort($keys);
    return $keys;
  }

  /**
   * The schema file must declare a `theme` mapping to test against.
   */
  public function testSchemaDeclaresThemeMapping(): void {
    $this->assertFileExists($this->getSchemaPath());
    $content = file_get_contents($this->getSchemaPath());
    $this->assertMatchesRegularExpression('/^ {4}theme:\s*$/m', $content, 'Schema must declare a `theme:` mapping.');
  }

  /**
   * The contract's KEYS and the schema's theme leaf keys must match exactly,
   * in both directions.
   */
  public function testContractKeysMatchSchemaExactly(): void {
    $schemaKeys = $this->getSchemaThemeKeys();
    $contractKeys = BiolandThemeContract::KEYS;
    sort($contractKeys);

    $this->assertSame(
      $contractKeys,
      $schemaKeys,
      "BiolandThemeContract::KEYS and the schema `theme` mapping have drifted apart.\n"
      . 'Contract: ' . implode(', ', $contractKeys) . "\n"
      . 'Schema:   ' . implode(', ', $schemaKeys)
    );
  }

  /**
   * The `back_ground` trap: the key must be `back_ground`, and `background`
   * must never appear anywhere in the schema file.
   */
  public function testBackGroundKeyIsCorrectlySpelled(): void {
    // Scoped to the theme block: unrelated keys elsewhere in the 500-line
    // schema must not be able to fail (or accidentally satisfy) this test.
    $themeBlock = implode("\n", $this->extractThemeBlockLines(file_get_contents($this->getSchemaPath())));
    $this->assertStringContainsString('back_ground:', $themeBlock, 'The `back_ground` key must be present.');
    $this->assertStringNotContainsString('background:', $themeBlock, 'The dead `background` key must never appear.');
    $this->assertContains(BiolandThemeContract::KEY_BACK_GROUND_SECONDARY, BiolandThemeContract::KEYS);
  }

  /**
   * None of the D4 dead keys may appear among the theme leaf keys.
   */
  public function testDeadKeysAreAbsent(): void {
    // Deliberately NOT getSchemaThemeKeys(): that set is already pinned to
    // BiolandThemeContract::KEYS by testContractKeysMatchSchemaExactly(), so
    // asserting against it can never fail on its own. Parsing every declared
    // path out of the raw theme block instead catches a dead key added to
    // the schema and the contract together, or declared inline / as a
    // container where the leaf parser would not see it.
    $lines = $this->extractThemeBlockLines(file_get_contents($this->getSchemaPath()));
    $haystack = implode("\n", $this->parseAllKeyPaths($lines));

    foreach (self::DEAD_KEY_FRAGMENTS as $fragment) {
      $this->assertStringNotContainsString(
        $fragment,
        $haystack,
        "Dead key fragment '{$fragment}' must not appear in the theme mapping."
      );
    }
  }

  /**
   * `home_page_widgets.columns` must be a sequence OF SEQUENCES of strings:
   * an outer list of grid columns, each holding widget machine names.
   *
   * Head renders the outer level as the `col-md-4` grid columns and the
   * inner level as the widgets in one column (bioland-head
   * app/components/page/home-chm.vue:18-20); the DMSM prod seed and stg
   * config both carry nested arrays. A flat `sequence of string` would be
   * wrong, so this asserts the inner element type explicitly rather than
   * just "columns is a sequence" -- the latter passes for both shapes.
   */
  public function testHomePageWidgetsColumnsIsASequenceOfWidgetColumns(): void {
    $block = implode("\n", $this->extractColumnsBlockLines());
    $this->assertNotSame('', $block, '`columns` must be declared under the theme block.');
    // Each `.*?` gap below is guarded against skipping over another
    // `sequence:` key line. Without the guard, a THIRD nesting level (an
    // extra `type: sequence` / `sequence:` pair) can be absorbed by one of
    // the lazy gaps and the regex still matches -- it only pins "at least
    // two levels", not "exactly two". The guard forces the two `sequence:`
    // matches onto the two literal occurrences in a doubly-nested shape, so
    // a triple-nested shape (which has three) fails to match.
    $this->assertMatchesRegularExpression(
      '/^\s*columns:\s*$(?:(?!^\s*sequence:\s*$).)*?^\s*type: sequence\s*$(?:(?!^\s*sequence:\s*$).)*?'
      . '^\s*sequence:\s*$(?:(?!^\s*sequence:\s*$).)*?^\s*type: sequence\s*$(?:(?!^\s*sequence:\s*$).)*?'
      . '^\s*sequence:\s*$(?:(?!^\s*sequence:\s*$).)*?^\s*type: string\s*$/ms',
      $block,
      '`columns` must be a sequence whose elements are themselves sequences of strings '
      . '(a list of grid columns, each a list of widget machine names) -- exactly two levels of '
      . 'nesting, neither a flat string sequence nor a triple-nested shape.'
    );
  }

  /**
   * The registry (p01-05) owns the widget vocabulary; no enumerated widget
   * names may be hardcoded in the schema.
   */
  public function testHomePageWidgetsColumnsHasNoEnumeratedVocabulary(): void {
    $content = file_get_contents($this->getSchemaPath());
    $this->assertStringNotContainsString('allowed_values', $content);
    $this->assertStringNotContainsString(
      'panorama',
      implode("\n", $this->extractColumnsBlockLines()),
      'Widget machine names must not be enumerated in the schema.'
    );
  }

  /**
   * No `config/install` defaults ship with this task; lazy-seed (p02-01)
   * owns the first write.
   */
  public function testNoConfigInstallDefaultsExist(): void {
    $installDir = dirname($this->getSchemaPath(), 2) . '/install';
    if (!is_dir($installDir)) {
      $this->assertTrue(TRUE, 'No config/install directory exists.');
      return;
    }
    $files = glob($installDir . '/bioland.settings.yml');
    foreach ($files as $file) {
      $this->assertStringNotContainsString('theme:', file_get_contents($file), basename($file) . ' must not carry theme defaults.');
    }
  }

  /**
   * The CKEditor stylesheet's baked-in primary is the bl2 fallback constant.
   *
   * CSS cannot reference a PHP constant, so the value is written out in both
   * places and this is the only thing keeping them in step. It matters because
   * the stylesheet's declaration is what an editing surface falls back to when
   * bioland_form_alter()'s override is not on the page.
   */
  public function testCkeditorStylesheetDefaultsToTheBl2FallbackPrimary(): void {
    $css = file_get_contents(dirname(__DIR__, 2) . '/css/bioland.ckeditor.css');

    $this->assertMatchesRegularExpression(
      '/\.ck\.ck-content\s*\{\s*--bs-primary:\s*' . preg_quote(BiolandThemeContract::FALLBACK_PRIMARY_BL2, '/') . ';/',
      $css,
      'css/bioland.ckeditor.css must default --bs-primary to BiolandThemeContract::FALLBACK_PRIMARY_BL2.'
    );
  }

  /**
   * The node form injects the per-site primary over that baked-in default.
   *
   * A structural pin, not a behavioural one: bioland_form_alter() is a
   * ~700-line procedural hook that reaches for the current user, the route
   * match and several services long before it gets here, so calling it would
   * cost a container harness far larger than the six lines under test. What
   * can still drift silently is pinned instead — that the colour comes from
   * the validated accessor rather than straight from config, that the style
   * element keeps its html_head key, and that the config cache tag is there so
   * saving the Theme tab actually refreshes the form.
   */
  public function testNodeFormOverridesTheCkeditorPrimaryFromSettings(): void {
    $module = file_get_contents(dirname(__DIR__, 2) . '/bioland.module');
    $attach = strstr($module, "\$form['#attached']['library'][] = 'bioland/ckeditor_content_styles';");
    $this->assertNotFalse($attach, 'The ckeditor_content_styles attach site has moved or gone.');
    // Each marker below appears exactly once in the whole file, so the window
    // is only here to keep the assertions anchored to the attach site rather
    // than passing on something elsewhere in the hook. Sized with room for the
    // comments to grow.
    $block = substr($attach, 0, 2500);

    $this->assertStringContainsString(
      "\\Drupal::service('bioland.component_menu_form_mode')->primaryColor()",
      $block,
      'The colour must come from the validated accessor, never straight from config.'
    );
    $this->assertStringContainsString(
      "'body .ck.ck-content{--bs-primary:'",
      $block,
      'The override must OUT-SPECIFY the stylesheet (body .ck.ck-content, 0,2,1): html_head renders before the library CSS, so at equal specificity the stylesheet wins and the override is inert.'
    );
    $this->assertStringContainsString(
      "'bioland-ckeditor-primary'",
      $block,
      'The html_head element needs its key, or repeated attaches stack up.'
    );
    $this->assertStringContainsString(
      "\$form['#cache']['tags'][] = 'config:bioland.settings';",
      $block,
      'Defensive metadata: the form is currently uncacheable via its CSRF token, but the config dependency should stay declared.'
    );
  }

}
