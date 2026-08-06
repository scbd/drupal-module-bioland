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
    $path = dirname(__DIR__, 2) . '/config/schema/bioland.schema.yml';
    if (!is_file($path)) {
      $path = __DIR__ . '/../../config/schema/bioland.schema.yml';
    }
    return $path;
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
    $content = file_get_contents($this->getSchemaPath());
    $this->assertStringContainsString('back_ground:', $content, 'The `back_ground` key must be present.');
    $this->assertStringNotContainsString('background:', $content, 'The dead `background` key must never appear.');
    $this->assertContains(BiolandThemeContract::KEY_BACK_GROUND_SECONDARY, BiolandThemeContract::KEYS);
  }

  /**
   * None of the D4 dead keys may appear among the theme leaf keys.
   */
  public function testDeadKeysAreAbsent(): void {
    $schemaKeys = $this->getSchemaThemeKeys();
    $haystack = implode("\n", $schemaKeys);

    foreach (self::DEAD_KEY_FRAGMENTS as $fragment) {
      $this->assertStringNotContainsString(
        $fragment,
        $haystack,
        "Dead key fragment '{$fragment}' must not appear in the theme mapping."
      );
    }
  }

  /**
   * `home_page_widgets.columns` must be a plain string sequence -- no
   * enumerated widget vocabulary declared in the schema.
   */
  public function testHomePageWidgetsColumnsHasNoEnumeratedVocabulary(): void {
    $content = file_get_contents($this->getSchemaPath());
    $this->assertMatchesRegularExpression(
      '/columns:\s*\n\s*type: sequence/',
      $content,
      '`columns` must be declared as a sequence.'
    );
    // The registry (p01-05) owns widget names; none should be hardcoded
    // as YAML enum-style values here.
    $this->assertStringNotContainsString('allowed_values', $content);
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

}
