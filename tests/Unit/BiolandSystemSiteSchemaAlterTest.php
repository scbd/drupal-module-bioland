<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-1255 system.site nameEnglish schema declaration.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandSystemSiteSchemaAlterTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 2) . '/bioland.module';
  }

  /**
   * The key is declared as an optional string.
   */
  public function testNameEnglishDeclaredOptionalString(): void {
    $definitions = ['system.site' => ['mapping' => ['name' => ['type' => 'label']]]];
    bioland_config_schema_info_alter($definitions);

    $key = $definitions['system.site']['mapping']['nameEnglish'];
    $this->assertSame('string', $key['type']);
    $this->assertFalse($key['requiredKey']);
    $this->assertTrue($key['nullable']);
  }

  /**
   * Existing mapping keys are preserved.
   */
  public function testExistingKeysPreserved(): void {
    $definitions = ['system.site' => ['mapping' => ['name' => ['type' => 'label'], 'mail' => ['type' => 'email']]]];
    bioland_config_schema_info_alter($definitions);

    $this->assertSame(['type' => 'label'], $definitions['system.site']['mapping']['name']);
    $this->assertSame(['type' => 'email'], $definitions['system.site']['mapping']['mail']);
  }

  /**
   * The hook is a no-op when system.site is not defined.
   */
  public function testNoOpWithoutSystemSite(): void {
    $definitions = ['other.thing' => ['mapping' => ['a' => ['type' => 'string']]]];
    $before = $definitions;
    bioland_config_schema_info_alter($definitions);
    $this->assertSame($before, $definitions);
  }

}
