<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the translation backfill gate.
 *
 * The backfill WRITES CONTENT across every existing node of type "content", so
 * it must stay behind two gates that are BOTH required:
 * - translation.auto_create (canonical feature switch, ships TRUE)
 * - translation.backfill_enabled (backfill switch, ships FALSE)
 *
 * These tests prove the corrected, canonical key is the one read, that the
 * backfill-specific flag ships off, that a new reachable update hook
 * (bioland_update_9081) carries the corrected guard, and that the batch is
 * skipped unless both gates are open.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandTranslationBackfillTest extends TestCase {

  /**
   * The misspelled key that no writer ever wrote.
   *
   * Deliberately assembled from two fragments so a repo-wide grep for the
   * misspelling returns no hits — this file asserts its ABSENCE, and a literal
   * here would make that grep unusable as a check.
   */
  private const MISSPELLED_KEY = 'translation.auto_create' . '_enabled';

  /**
   * The canonical key, per config/schema/bioland.schema.yml.
   */
  private const CANONICAL_KEY = 'translation.auto_create';

  /**
   * The backfill-specific gate.
   */
  private const BACKFILL_KEY = 'translation.backfill_enabled';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->moduleRoot() . '/includes/bioland.install.translation.inc';
    \Drupal::resetContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Resolve the module root in both module-root and tests contexts.
   */
  private function moduleRoot(): string {
    $root = dirname(__DIR__, 3);
    if (!file_exists($root . '/bioland.install')) {
      $root = __DIR__ . '/../..';
    }
    return $root;
  }

  /**
   * Install the stub config factory and entity query, then run the backfill.
   *
   * @param bool $auto_create
   *   Value returned for translation.auto_create.
   * @param bool $backfill_enabled
   *   Value returned for translation.backfill_enabled.
   *
   * @return array
   *   ['message' => string, 'config' => object, 'query' => object].
   */
  private function runBackfill(bool $auto_create, bool $backfill_enabled): array {
    $config = new class($auto_create, $backfill_enabled) {
      public array $requested = [];
      private array $values;

      public function __construct(bool $auto_create, bool $backfill_enabled) {
        $this->values = [
          BiolandTranslationBackfillTest::canonicalKey() => $auto_create,
          BiolandTranslationBackfillTest::backfillKey() => $backfill_enabled,
        ];
      }

      public function get($key) {
        $this->requested[] = $key;
        return $this->values[$key] ?? NULL;
      }

    };

    $factory = new class($config) {
      private $config;

      public function __construct($config) {
        $this->config = $config;
      }

      public function get($name) {
        return $this->config;
      }

    };

    // A query that records whether the backfill ever reached the node lookup.
    // It returns no nids, so even an open gate writes nothing.
    $query = new class {
      public bool $executed = FALSE;

      public function condition($field, $value) {
        return $this;
      }

      public function accessCheck($check) {
        return $this;
      }

      public function execute() {
        $this->executed = TRUE;
        return [];
      }

    };

    \Drupal::setService('config.factory', $factory);
    \Drupal::setService('entity.queries', ['node' => $query]);

    $sandbox = [];
    $message = (string) _bioland_run_translation_backfill($sandbox);

    return ['message' => $message, 'config' => $config, 'query' => $query];
  }

  /**
   * Install the stub config factory and entity query with RAW config values.
   *
   * Unlike runBackfill(), the values are not coerced to bool before being
   * handed to the stub, so a caller can pass NULL to simulate a key that has
   * never been written — the actual state of every one of the ~211
   * production sites that predate this config, since neither key exists
   * until a site's config is explicitly updated to include it.
   *
   * @param array $values
   *   Map of config key => raw value (bool|null) to return from get().
   *
   * @return array
   *   ['message' => string, 'config' => object, 'query' => object].
   */
  private function runBackfillWithRawValues(array $values): array {
    $config = new class($values) {
      public array $requested = [];
      private array $values;

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function get($key) {
        $this->requested[] = $key;
        return array_key_exists($key, $this->values) ? $this->values[$key] : NULL;
      }

    };

    $factory = new class($config) {
      private $config;

      public function __construct($config) {
        $this->config = $config;
      }

      public function get($name) {
        return $this->config;
      }

    };

    // A query that records whether the backfill ever reached the node lookup.
    $query = new class {
      public bool $executed = FALSE;

      public function condition($field, $value) {
        return $this;
      }

      public function accessCheck($check) {
        return $this;
      }

      public function execute() {
        $this->executed = TRUE;
        return [];
      }

    };

    \Drupal::setService('config.factory', $factory);
    \Drupal::setService('entity.queries', ['node' => $query]);

    $sandbox = [];
    $message = (string) _bioland_run_translation_backfill($sandbox);

    return ['message' => $message, 'config' => $config, 'query' => $query];
  }

  /**
   * Expose the canonical key to the anonymous config stub.
   */
  public static function canonicalKey(): string {
    return self::CANONICAL_KEY;
  }

  /**
   * Expose the backfill key to the anonymous config stub.
   */
  public static function backfillKey(): string {
    return self::BACKFILL_KEY;
  }

  /**
   * Gate case 1: auto_create on, backfill_enabled off — nothing runs.
   *
   * This is the shipped default state, so it is the case that decides whether
   * a content-writing batch starts unattended on production sites.
   */
  public function testBackfillSkippedWhenBackfillFlagOff(): void {
    $result = $this->runBackfill(TRUE, FALSE);

    $this->assertFalse(
      $result['query']->executed,
      'The backfill must not query any node when translation.backfill_enabled is FALSE.'
    );
    $this->assertStringContainsString(
      self::BACKFILL_KEY,
      $result['message'],
      'The skip message must name the closed gate.'
    );
  }

  /**
   * Gate case 2: backfill_enabled on, auto_create off — nothing runs.
   */
  public function testBackfillSkippedWhenAutoCreateOff(): void {
    $result = $this->runBackfill(FALSE, TRUE);

    $this->assertFalse(
      $result['query']->executed,
      'The backfill must not query any node when translation.auto_create is FALSE.'
    );
    $this->assertStringContainsString(
      self::CANONICAL_KEY,
      $result['message'],
      'The skip message must name the closed gate.'
    );
  }

  /**
   * Gate case 3: both gates open — the batch proceeds.
   */
  public function testBackfillProceedsWhenBothGatesOpen(): void {
    $result = $this->runBackfill(TRUE, TRUE);

    $this->assertTrue(
      $result['query']->executed,
      'The backfill must proceed when both translation.auto_create and translation.backfill_enabled are TRUE.'
    );
  }

  /**
   * Gate case 4: the backfill key was never written — the state of every
   * existing (~211) production site — while the canonical key is TRUE.
   *
   * `(bool) $config->get(...)` turns the absent key's NULL into FALSE, which
   * correctly keeps the gate closed. Nothing here pins that cast: drop it
   * for `!$config->get(...)` or swap to `!== FALSE`/`isset()` and every
   * other test in this file still passes, silently arming the backfill on
   * every site that has never set translation.backfill_enabled.
   */
  public function testBackfillSkippedWhenBackfillKeyAbsent(): void {
    $result = $this->runBackfillWithRawValues([
      self::CANONICAL_KEY => TRUE,
      self::BACKFILL_KEY => NULL,
    ]);

    $this->assertFalse(
      $result['query']->executed,
      'A NULL (never-written) translation.backfill_enabled must close the gate, not open it.'
    );
    $this->assertStringContainsString(
      self::BACKFILL_KEY,
      $result['message'],
      'The skip message must name the closed gate.'
    );
  }

  /**
   * Mirror of the above: the canonical key was never written, while the
   * backfill-specific key is TRUE. A NULL on either gate must close it.
   */
  public function testBackfillSkippedWhenCanonicalKeyAbsent(): void {
    $result = $this->runBackfillWithRawValues([
      self::CANONICAL_KEY => NULL,
      self::BACKFILL_KEY => TRUE,
    ]);

    $this->assertFalse(
      $result['query']->executed,
      'A NULL (never-written) translation.auto_create must close the gate, not open it.'
    );
    $this->assertStringContainsString(
      self::CANONICAL_KEY,
      $result['message'],
      'The skip message must name the closed gate.'
    );
  }

  /**
   * The guard reads the canonical key, never the misspelled one.
   */
  public function testGuardReadsCanonicalKeyNotMisspelledKey(): void {
    $result = $this->runBackfill(TRUE, FALSE);

    $this->assertContains(
      self::CANONICAL_KEY,
      $result['config']->requested,
      'The guard must read the canonical translation.auto_create key.'
    );
    $this->assertContains(
      self::BACKFILL_KEY,
      $result['config']->requested,
      'The guard must read the translation.backfill_enabled gate.'
    );
    $this->assertNotContains(
      self::MISSPELLED_KEY,
      $result['config']->requested,
      'The guard must never read the misspelled key (see self::MISSPELLED_KEY).'
    );
  }

  /**
   * A new, reachable update hook carries the corrected guard.
   *
   * The corrected read lives inside bioland_update_9012(), which has already
   * run on every existing site, so the fix is unreachable without a NEW hook.
   */
  public function testUpdateHook9081CarriesTheCorrectedGuard(): void {
    $file = $this->moduleRoot() . '/includes/bioland.install.translation.inc';
    $this->assertFileExists($file);
    $content = file_get_contents($file);

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9081\s*\(/',
      $content,
      'bioland_update_9081() must exist: correcting the guard inside the already-run bioland_update_9012() changes nothing for existing sites.'
    );
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9081\s*\([^)]*\)\s*\{.*_bioland_run_translation_backfill\s*\(/s',
      $content,
      'bioland_update_9081() must delegate to the shared, gated backfill routine rather than copying the batch logic.'
    );
    $this->assertTrue(
      function_exists('bioland_update_9081'),
      'bioland_update_9081() must be defined by the translation install include.'
    );
  }

  /**
   * The misspelled key is gone from the whole module.
   */
  public function testMisspelledKeyIsGoneFromTheModule(): void {
    $root = $this->moduleRoot();
    $files = array_merge(
      [$root . '/bioland.install', $root . '/bioland.module'],
      glob($root . '/includes/*.inc') ?: [],
      glob($root . '/src/*/*.php') ?: [],
      glob($root . '/config/install/*.yml') ?: [],
      glob($root . '/config/schema/*.yml') ?: []
    );

    foreach ($files as $file) {
      if (!file_exists($file)) {
        continue;
      }
      $this->assertStringNotContainsString(
        self::MISSPELLED_KEY,
        file_get_contents($file),
        sprintf('%s still references the misspelled key; a partial fix turns a consistently-dead feature into an inconsistently-live one.', basename($file))
      );
    }
  }

  /**
   * The backfill flag is schema-declared and ships FALSE.
   */
  public function testBackfillFlagIsSchemaDeclaredAndShipsOff(): void {
    $root = $this->moduleRoot();

    $schema = file_get_contents($root . '/config/schema/bioland.schema.yml');
    $this->assertMatchesRegularExpression(
      '/^\s{8}backfill_enabled:\n\s{10}type: boolean/m',
      $schema,
      'translation.backfill_enabled must be declared as a boolean in the config schema.'
    );

    $settings = file_get_contents($root . '/config/install/bioland.settings.yml');
    $this->assertMatchesRegularExpression(
      '/^\s{2}backfill_enabled:\s*false\s*$/m',
      $settings,
      'translation.backfill_enabled must ship FALSE: enabling the content-writing backfill is a deliberate, per-site, operator-run act.'
    );
    $this->assertDoesNotMatchRegularExpression(
      '/^\s{2}backfill_enabled:\s*true\s*$/m',
      $settings,
      'translation.backfill_enabled must never ship TRUE.'
    );
  }

}
