<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the runtime requirements warning for a restored GA-enabled database.
 *
 * BL-1015 made the "Enable Google Analytics" switch the sole server-side
 * control over whether the configured Google tags load. That means a
 * production database copy restored onto a staging or dev host silently
 * starts loading the production property for every visitor. bioland_install()
 * cannot be exercised under plain PHPUnit (it calls the \Drupal facade with
 * no kernel bootstrap - see phpunit.xml.dist), so - matching the established
 * pattern in SearchApiConvergenceHookTest and BiolandGoogleAnalyticsToggleTest
 * - these tests assert against the source of bioland_requirements() itself.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandGoogleAnalyticsRequirementsTest extends TestCase {

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
   * Returns the source of bioland_requirements() alone.
   *
   * Cut at the next function declaration rather than at the end of the file,
   * so a later addition below it cannot satisfy these assertions on its
   * behalf.
   *
   * @return string
   *   The function body.
   */
  private function functionBody(): string {
    $file = $this->moduleRoot() . '/bioland.install';
    $this->assertFileExists($file);
    $source = file_get_contents($file);

    $offset = strpos($source, 'function bioland_requirements(');
    $this->assertIsInt($offset, 'bioland_requirements() must exist in bioland.install.');

    $body = substr($source, $offset);
    if (preg_match('/^function /m', $body, $matches, PREG_OFFSET_CAPTURE, 1)) {
      $body = substr($body, 0, $matches[0][1]);
    }

    return $body;
  }

  /**
   * Isolates the array literal for the new requirement key.
   */
  private function requirementBlock(): string {
    $body = $this->functionBody();
    $offset = strpos($body, "\$requirements['bioland_google_analytics_enabled']");
    $this->assertIsInt($offset, 'The requirement key bioland_google_analytics_enabled must exist.');

    $end = strpos($body, '];', $offset);
    $this->assertIsInt($end);

    return substr($body, $offset, $end - $offset);
  }

  /**
   * The requirement is nested inside both the runtime-phase and the
   * strict-TRUE config guards, so it is absent whenever either is false:
   * disabled config, missing config, or the 'install' phase all produce no
   * warning at all - only a genuinely-enabled runtime site does.
   */
  public function testRequirementOnlyAddedWhenRuntimeAndEnabled(): void {
    $this->assertMatchesRegularExpression(
      "/if\s*\(\s*\\\$phase\s*===\s*'runtime'\s*\)\s*\{.*?"
      . "if\s*\(\s*\\\$config->get\('google_analytics_enabled'\)\s*===\s*TRUE\s*\)\s*\{.*?"
      . "\\\$requirements\['bioland_google_analytics_enabled'\]/s",
      $this->functionBody(),
      'The bioland_google_analytics_enabled requirement must be nested inside BOTH the '
      . "\$phase === 'runtime' guard and the google_analytics_enabled === TRUE guard, so it "
      . 'is never added for a disabled/absent switch or during install.'
    );
  }

  /**
   * The stored value is read strictly, matching BiolandFrontEndGeneralForm.
   *
   * Drupal does not enforce the boolean schema on a write, so a hand-edited
   * import can store the string 'false'; (bool) would misread that as
   * enabled. The requirements check and the settings form must agree.
   */
  public function testValueIsReadStrictly(): void {
    $this->assertMatchesRegularExpression(
      "/->get\('google_analytics_enabled'\)\s*===\s*TRUE/",
      $this->functionBody(),
      'bioland_requirements() must read google_analytics_enabled with a strict === TRUE comparison.'
    );
  }

  /**
   * A genuinely enabled production site is warned, never hard-errored.
   */
  public function testSeverityIsWarningNotError(): void {
    $block = $this->requirementBlock();

    $this->assertStringContainsString(
      "'severity' => REQUIREMENT_WARNING,",
      $block,
      'The Google Analytics requirement must use REQUIREMENT_WARNING, never REQUIREMENT_ERROR.'
    );
    $this->assertStringNotContainsString('REQUIREMENT_ERROR', $block);
  }

  /**
   * The tag IDs themselves are never rendered into the status report.
   */
  public function testTagIdsAreNeverRendered(): void {
    $this->assertStringNotContainsString(
      'google_analytics_ids',
      $this->requirementBlock(),
      'The requirements check must never read or render the configured Google tag IDs.'
    );
  }

  /**
   * The description tells the operator what to do about it.
   */
  public function testDescriptionTellsOperatorToDisableOnNonProduction(): void {
    $block = $this->requirementBlock();

    $this->assertStringContainsString('Front End > General', $block);
    $this->assertStringContainsString('turn the switch off', $block);
  }

}
