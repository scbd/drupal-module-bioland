<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins the Google Analytics switch default, schema, and seeding hook.
 *
 * The switch is stated in three places by necessity - config/install (fresh
 * sites), config/schema (so the key is typed and translatable), and an update
 * hook (already-installed sites). "Disabled everywhere by default" is only
 * true if all three agree, so these tests hold them together.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandGoogleAnalyticsToggleTest extends TestCase {

  /**
   * The setting's key, as Drupal stores it and as the head consumes it.
   */
  private const KEY = 'google_analytics_enabled';

  /**
   * Resolve the module root in both module-root and tests contexts.
   */
  private function moduleRoot(): string {
    $root = dirname(__DIR__, 2);
    if (!file_exists($root . '/bioland.install')) {
      $root = __DIR__ . '/../..';
    }
    return $root;
  }

  /**
   * Reads one module file.
   *
   * @param string $path
   *   Path relative to the module root.
   *
   * @return string
   *   The file contents.
   */
  private function read(string $path): string {
    $file = $this->moduleRoot() . '/' . $path;
    $this->assertFileExists($file);

    return file_get_contents($file);
  }

  /**
   * A fresh install starts with the switch off.
   */
  public function testInstallDefaultIsDisabled(): void {
    $this->assertMatchesRegularExpression(
      '/^' . self::KEY . ':\s*false\s*$/m',
      $this->read('config/install/bioland.settings.yml'),
      'config/install/bioland.settings.yml must ship ' . self::KEY . ': false so a fresh site has Google Analytics off.'
    );
  }

  /**
   * The install default sits above the tag IDs it controls.
   */
  public function testInstallDefaultPrecedesTheTagIds(): void {
    $yaml = $this->read('config/install/bioland.settings.yml');

    $enabled = strpos($yaml, self::KEY . ':');
    $ids = strpos($yaml, 'google_analytics_ids:');

    $this->assertIsInt($enabled);
    $this->assertIsInt($ids);
    $this->assertLessThan($ids, $enabled, 'The switch is declared above the tag IDs it gates.');
  }

  /**
   * The key is schema-declared as a boolean, not an untyped string.
   */
  public function testSchemaDeclaresABoolean(): void {
    $this->assertMatchesRegularExpression(
      '/^\s+' . self::KEY . ':\s*\n\s+type:\s*boolean\s*$/m',
      $this->read('config/schema/bioland.schema.yml'),
      'config/schema/bioland.schema.yml must type ' . self::KEY . ' as boolean.'
    );
  }

  /**
   * The seeding hook exists and writes FALSE.
   */
  public function testUpdateHookSeedsDisabled(): void {
    $this->assertMatchesRegularExpression(
      "/->set\('" . self::KEY . "',\s*FALSE\)/",
      $this->hookBody(),
      'bioland_update_9081() must seed the switch as FALSE for already-installed sites.'
    );
  }

  /**
   * The hook seeds only when the key is absent, so an authored TRUE survives.
   */
  public function testUpdateHookIsSetIfMissing(): void {
    $this->assertMatchesRegularExpression(
      "/if\s*\(\\\$config->get\('" . self::KEY . "'\)\s*===\s*NULL\)/",
      $this->hookBody(),
      'bioland_update_9081() must guard on === NULL so re-running it never turns a site back off.'
    );
  }

  /**
   * Returns the source of bioland_update_9081().
   *
   * @return string
   *   The hook body, from its declaration to the end of the file.
   */
  private function hookBody(): string {
    $source = $this->read('includes/bioland.install.helpers.inc');
    $offset = strpos($source, 'function bioland_update_9081(');

    $this->assertIsInt($offset, 'bioland_update_9081() must exist in includes/bioland.install.helpers.inc.');

    return substr($source, $offset);
  }

}
