<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Search API convergence "single switch".
 *
 * Two Search API configuration paths exist (v1 in bioland.install.search.inc,
 * v2 in bioland.install.search.v2.inc). To stop a site's update history from
 * determining its final index state, the highest-numbered update hook
 * (currently bioland_update_9074()) must re-apply the canonical v2 config.
 *
 * @group bioland
 * @coversNothing
 */
class SearchApiConvergenceHookTest extends TestCase {

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
   * Collect every bioland_update_N number across all install files.
   *
   * @return int[]
   */
  private function allUpdateHookNumbers(): array {
    $root = $this->moduleRoot();
    $files = [$root . '/bioland.install'];
    $files = array_merge($files, glob($root . '/includes/bioland.install.*.inc') ?: []);

    $numbers = [];
    foreach ($files as $file) {
      if (!file_exists($file)) {
        continue;
      }
      preg_match_all('/^function\s+bioland_update_(\d+)\s*\(/m', file_get_contents($file), $m);
      foreach ($m[1] as $n) {
        $numbers[] = (int) $n;
      }
    }
    return $numbers;
  }

  /**
   * The convergence hook exists and delegates to the canonical v2 config.
   */
  public function testConvergenceHookAppliesCanonicalV2Config(): void {
    $file = $this->moduleRoot() . '/includes/bioland.install.search.v2.inc';
    $this->assertFileExists($file);
    $content = file_get_contents($file);

    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9064\s*\(/',
      $content,
      'The Search API convergence hook bioland_update_9064() must exist.'
    );

    // Isolate the body of bioland_update_9064() and assert it calls the
    // canonical v2 config function (not a v1 helper).
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9064\s*\([^)]*\)\s*\{[^}]*_bioland_v2_update_search_and_facets_config\s*\(/s',
      $content,
      'bioland_update_9064() must apply the canonical v2 config via _bioland_v2_update_search_and_facets_config().'
    );

    // bioland_update_9065() (translation include) held the convergence duty
    // while it was the highest-numbered hook and must keep converging for
    // sites whose update run ends there.
    $translationFile = $this->moduleRoot() . '/includes/bioland.install.translation.inc';
    $this->assertFileExists($translationFile);
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9065\s*\([^)]*\)\s*\{.*_bioland_v2_update_search_and_facets_config\s*\(/s',
      file_get_contents($translationFile),
      'bioland_update_9065() must re-apply the canonical v2 config via _bioland_v2_update_search_and_facets_config() so the last-running hook converges.'
    );

    // The current highest-numbered hook (bioland_update_9074()) must ALSO
    // converge on the canonical v2 config, since Drupal runs it last for
    // every site (9071-9073 re-import translations and backfill taxonomy
    // translations / settings defaults after 9065).
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9074\s*\([^)]*\)\s*\{[^}]*_bioland_v2_update_search_and_facets_config\s*\(/s',
      $content,
      'bioland_update_9074() must re-apply the canonical v2 config via _bioland_v2_update_search_and_facets_config() so the last-running hook converges.'
    );

    // The new highest-numbered hook (bioland_update_9075(), added for the 1.1.2
    // interface-string + zh-hans translation corrections) is the last writer
    // for every site, so it must ALSO converge on the canonical v2 config.
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9075\s*\([^)]*\)\s*\{.*_bioland_v2_update_search_and_facets_config\s*\(/s',
      file_get_contents($translationFile),
      'bioland_update_9075() must re-apply the canonical v2 config via _bioland_v2_update_search_and_facets_config() so the last-running hook converges.'
    );

    // The new highest-numbered hook (bioland_update_9076(), added to re-import
    // the BSL home-widget section strings that predated the .po catalogs) is
    // the last writer for every site, so it must ALSO converge on the v2 config.
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9076\s*\([^)]*\)\s*\{.*_bioland_v2_update_search_and_facets_config\s*\(/s',
      file_get_contents($translationFile),
      'bioland_update_9076() must re-apply the canonical v2 config via _bioland_v2_update_search_and_facets_config() so the last-running hook converges.'
    );

    // The new highest-numbered hook (bioland_update_9077(), added for BL-832 to
    // pin the fixed additional tag content type mappings) is the last writer
    // for every site, so it must ALSO converge on the canonical v2 config.
    $fieldsFile = $this->moduleRoot() . '/includes/bioland.install.fields.inc';
    $this->assertFileExists($fieldsFile);
    $this->assertMatchesRegularExpression(
      '/function\s+bioland_update_9077\s*\([^)]*\)\s*\{.*_bioland_v2_update_search_and_facets_config\s*\(/s',
      file_get_contents($fieldsFile),
      'bioland_update_9077() must re-apply the canonical v2 config via _bioland_v2_update_search_and_facets_config() so the last-running hook converges.'
    );
  }

  /**
   * The convergence hook must be the highest-numbered update hook.
   *
   * Drupal runs update hooks in ascending numeric order, so only the highest
   * number is guaranteed to run last for every site. If a higher-numbered hook
   * is added later it could become the last writer and reopen the "half
   * configured index" risk — such a hook must itself converge on the v2 config
   * (and this test updated to point at it).
   */
  public function testConvergenceHookRunsLast(): void {
    $numbers = $this->allUpdateHookNumbers();
    $this->assertNotEmpty($numbers, 'Expected to find update hooks.');
    $this->assertSame(
      9077,
      max($numbers),
      'The highest-numbered update hook must converge every site last. bioland_update_9077() now holds that role (it pins the fixed additional tag content type mappings and re-applies the canonical v2 config after the 9071-9076 corrective hooks); if you add a higher-numbered hook it must itself converge on the v2 config and this test must be updated to point at it.'
    );
  }

}
