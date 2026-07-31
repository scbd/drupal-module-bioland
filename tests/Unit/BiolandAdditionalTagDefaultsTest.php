<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Service\BiolandAdditionalTagDefaults;

/**
 * Unit tests for the fixed additional tag content type mappings.
 *
 * The mappings are stated twice by necessity - once in code (for the settings
 * form and the update hook) and once in config/install (for fresh installs).
 * These tests pin the two together so they cannot silently drift.
 *
 * @covers \Drupal\bioland\Service\BiolandAdditionalTagDefaults
 */
class BiolandAdditionalTagDefaultsTest extends TestCase {

  /**
   * Parses a nested "key:" -> "  key:" -> "    - int" YAML block.
   *
   * Hand-rolled because symfony/yaml is not a dependency of this standalone
   * unit suite (same approach as BiolandLocalTasksTest::parseFlatYaml()).
   *
   * @param string $path
   *   Path to the YAML file, relative to the module root.
   * @param string $section
   *   The top-level key whose integer-sequence children are wanted.
   *
   * @return array<string, int[]>
   *   The section's children, each an array of integers.
   */
  private function parseIntSequenceSection(string $path, string $section): array {
    $lines = file(dirname(__DIR__, 2) . '/' . $path, FILE_IGNORE_NEW_LINES);
    $result = [];
    $inSection = FALSE;
    $currentKey = NULL;

    foreach ($lines as $line) {
      if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
        continue;
      }
      // A new top-level key ends the section we care about.
      if (preg_match('/^(\S[^:]*):/', $line, $matches)) {
        $inSection = ($matches[1] === $section);
        $currentKey = NULL;
        continue;
      }
      if (!$inSection) {
        continue;
      }
      // "  some_key:" - a child of the section.
      if (preg_match('/^  ([A-Za-z0-9_]+):\s*$/', $line, $matches)) {
        $currentKey = $matches[1];
        $result[$currentKey] = [];
        continue;
      }
      // "    - 3" - a sequence item under the current child.
      if ($currentKey !== NULL && preg_match('/^\s+-\s*(\d+)\s*$/', $line, $matches)) {
        $result[$currentKey][] = (int) $matches[1];
      }
    }

    return $result;
  }

  /**
   * Tests the install config carries exactly the fixed mappings.
   */
  public function testInstallConfigMatchesDefaults(): void {
    $installed = $this->parseIntSequenceSection('config/install/bioland.settings.yml', 'additional_tags');

    $this->assertSame(
      BiolandAdditionalTagDefaults::CONTENT_TYPES,
      $installed,
      'config/install/bioland.settings.yml must match BiolandAdditionalTagDefaults::CONTENT_TYPES - a fresh install and an updated site would otherwise disagree.'
    );
  }

  /**
   * Tests the documented content type values are the ones BL-832 fixed.
   */
  public function testContentTypesAreTheFixedValues(): void {
    $this->assertSame([
      'event_status_content_types' => [3],
      'project_status_content_types' => [5],
      'organization_types_content_types' => [8],
      'ecosystem_types_content_types' => [9],
      'document_types_content_types' => [12],
    ], BiolandAdditionalTagDefaults::CONTENT_TYPES);
  }

  /**
   * Tests every mapping key has a drupalSettings counterpart, and vice versa.
   */
  public function testJsKeysCoverEveryMapping(): void {
    $this->assertSame(
      array_keys(BiolandAdditionalTagDefaults::CONTENT_TYPES),
      array_keys(BiolandAdditionalTagDefaults::JS_KEYS),
      'Every fixed mapping needs a JS key (and no orphan JS keys), or getJavaScriptSettings() would emit an incomplete payload.'
    );

    foreach (BiolandAdditionalTagDefaults::JS_KEYS as $key => $js_key) {
      $this->assertSame(
        lcfirst(str_replace('_', '', ucwords($key, '_'))),
        $js_key,
        sprintf('The JS key for "%s" is the camelCase form of the config key.', $key)
      );
    }
  }

  /**
   * Tests the config schema declares every fixed mapping.
   */
  public function testConfigSchemaDeclaresEveryMapping(): void {
    $path = dirname(__DIR__, 2) . '/config/schema/bioland.schema.yml';
    $this->assertFileExists($path);

    $schema = file_get_contents($path);

    foreach (array_keys(BiolandAdditionalTagDefaults::CONTENT_TYPES) as $key) {
      $this->assertMatchesRegularExpression(
        '/^\s+' . preg_quote($key, '/') . ':\s*$/m',
        $schema,
        sprintf('"%s" must be declared in the config schema, or Config::save() would not cast it.', $key)
      );
    }
  }

}
