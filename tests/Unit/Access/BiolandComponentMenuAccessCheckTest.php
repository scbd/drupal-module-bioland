<?php

namespace Drupal\Tests\bioland\Unit\Access;

use Drupal\bioland\Access\BiolandComponentMenuAccessCheck;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests the on/off switch behind the "Add component menu link" action.
 *
 * The switch is a route access check precisely so that one decision hides the
 * local action and closes its URL. Three things therefore have to hold, and
 * each is pinned below:
 *   1. OFF means forbidden, not neutral - a neutral result would leave the
 *      outcome dependent on how the access manager folds it in with the
 *      route's _entity_create_access check;
 *   2. an absent key means ON, so sites that installed before the flag existed
 *      keep the button until an admin turns it off;
 *   3. the result carries the config:bioland.settings cache tag, without which
 *      the menu manage screen would keep serving the old button state from the
 *      render cache after the setting is saved.
 *
 * @group bioland
 * @coversDefaultClass \Drupal\bioland\Access\BiolandComponentMenuAccessCheck
 */
class BiolandComponentMenuAccessCheckTest extends TestCase {

  /**
   * Builds the check over a bioland.settings double holding $data.
   *
   * @param array $data
   *   The settings data.
   *
   * @return \Drupal\bioland\Access\BiolandComponentMenuAccessCheck
   *   The check under test.
   */
  private function createCheck(array $data): BiolandComponentMenuAccessCheck {
    $config = new ImmutableConfig(BiolandComponentMenuAccessCheck::CONFIG_NAME, $data);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnCallback(function ($name) use ($config) {
      $this->assertSame(
        BiolandComponentMenuAccessCheck::CONFIG_NAME,
        $name,
        'The flag must be read from bioland.settings, the object the admin form writes.'
      );

      return $config;
    });

    return new BiolandComponentMenuAccessCheck($factory);
  }

  /**
   * The flag on allows access, leaving the route's own check to decide.
   *
   * @covers ::access
   */
  public function testEnabledAllowsAccess(): void {
    $result = $this->createCheck([BiolandComponentMenuAccessCheck::FLAG => TRUE])->access();

    $this->assertTrue($result->isAllowed());
    $this->assertFalse($result->isForbidden());
  }

  /**
   * The flag off forbids access, which is what hides the local action.
   *
   * @covers ::access
   */
  public function testDisabledForbidsAccess(): void {
    $result = $this->createCheck([BiolandComponentMenuAccessCheck::FLAG => FALSE])->access();

    $this->assertTrue($result->isForbidden());
    $this->assertFalse($result->isAllowed());
    $this->assertFalse(
      $result->isNeutral(),
      'A neutral result would make the outcome depend on the route\'s other access check.'
    );
    $this->assertNotNull($result->getReason(), 'A forbidden result must say why, for the access log.');
  }

  /**
   * An absent flag behaves as on, so existing sites are unaffected.
   *
   * @covers ::access
   */
  public function testMissingFlagDefaultsToEnabled(): void {
    $result = $this->createCheck([])->access();

    $this->assertTrue(
      $result->isAllowed(),
      'Sites upgraded from before the flag existed have no key and must keep the button.'
    );
  }

  /**
   * Only an explicit FALSE turns the feature off.
   *
   * Config booleans arrive from a checkbox as 0/1 through the form API, but the
   * saved value is cast to boolean by the schema; anything else (a stray
   * string, NULL) must not silently disable a working feature.
   *
   * @covers ::access
   */
  public function testOnlyExplicitFalseDisables(): void {
    foreach ([TRUE, 1, '1', NULL] as $value) {
      $result = $this->createCheck([BiolandComponentMenuAccessCheck::FLAG => $value])->access();
      $this->assertTrue(
        $result->isAllowed(),
        sprintf('Value %s must not disable the feature.', var_export($value, TRUE))
      );
    }
  }

  /**
   * Both outcomes carry the settings cache tag.
   *
   * @covers ::access
   */
  public function testTheDecisionIsInvalidatedWhenTheSettingIsSaved(): void {
    $expected = ['config:' . BiolandComponentMenuAccessCheck::CONFIG_NAME];

    foreach ([TRUE, FALSE] as $flag) {
      $result = $this->createCheck([BiolandComponentMenuAccessCheck::FLAG => $flag])->access();
      $this->assertSame(
        $expected,
        $result->getCacheTags(),
        'Without the config cache tag the menu screen keeps rendering the previous button state.'
      );
    }
  }

}
