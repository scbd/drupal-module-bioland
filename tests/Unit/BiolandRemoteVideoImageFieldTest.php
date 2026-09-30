<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandRemoteVideoImageField;
use PHPUnit\Framework\TestCase;

/**
 * Tests the BL-815 remote video image field decision logic.
 *
 * @group bioland
 * @coversDefaultClass \Drupal\bioland\BiolandRemoteVideoImageField
 */
class BiolandRemoteVideoImageFieldTest extends TestCase {

  /**
   * @covers ::qualifies
   */
  public function testQualifiesOnlyImageFieldsOnRemoteVideoMedia(): void {
    $this->assertTrue(BiolandRemoteVideoImageField::qualifies('media', 'remote_video', 'image'));

    $this->assertFalse(BiolandRemoteVideoImageField::qualifies('node', 'remote_video', 'image'));
    $this->assertFalse(BiolandRemoteVideoImageField::qualifies('media', 'image', 'image'));
    $this->assertFalse(BiolandRemoteVideoImageField::qualifies('media', 'remote_video', 'file'));
    $this->assertFalse(BiolandRemoteVideoImageField::qualifies('media', 'remote_video', 'string'));
  }

  /**
   * @covers ::shouldProcess
   */
  public function testShouldProcessPrimaryAlwaysOthersOnlyWhenRequired(): void {
    $this->assertTrue(BiolandRemoteVideoImageField::shouldProcess('field_media_image', TRUE));
    $this->assertTrue(BiolandRemoteVideoImageField::shouldProcess('field_media_image', FALSE));
    $this->assertTrue(BiolandRemoteVideoImageField::shouldProcess('field_poster', TRUE));
    $this->assertFalse(BiolandRemoteVideoImageField::shouldProcess('field_poster', FALSE));
  }

  /**
   * @covers ::plan
   */
  public function testPlanMakesImageAndAltOptionalButKeepsAltField(): void {
    $settings = ['alt_field' => TRUE, 'alt_field_required' => TRUE, 'max_filesize' => '2 MB'];
    $plan = BiolandRemoteVideoImageField::plan(TRUE, $settings, '');

    $this->assertFalse($plan['required']);
    $this->assertFalse($plan['settings']['alt_field_required']);
    $this->assertTrue($plan['settings']['alt_field']);
    $this->assertSame('2 MB', $plan['settings']['max_filesize']);
    $this->assertSame(BiolandRemoteVideoImageField::DESCRIPTION, $plan['description']);
    $this->assertTrue($plan['changed']);
  }

  /**
   * @covers ::plan
   */
  public function testPlanSetsDescriptionOnlyWhenEmpty(): void {
    $settings = ['alt_field' => TRUE, 'alt_field_required' => TRUE];

    $this->assertSame(
      BiolandRemoteVideoImageField::DESCRIPTION,
      BiolandRemoteVideoImageField::plan(TRUE, $settings, "  \n")['description']
    );
    $this->assertSame(
      'Site-authored help.',
      BiolandRemoteVideoImageField::plan(TRUE, $settings, 'Site-authored help.')['description']
    );
  }

  /**
   * @covers ::plan
   */
  public function testPlanIsIdempotent(): void {
    $settings = ['alt_field' => TRUE, 'alt_field_required' => FALSE];
    $plan = BiolandRemoteVideoImageField::plan(FALSE, $settings, BiolandRemoteVideoImageField::DESCRIPTION);

    $this->assertFalse($plan['changed']);
    $this->assertSame($settings, $plan['settings']);
  }

  /**
   * @covers ::plan
   */
  public function testPlanReportsChangeWhenOnlyAltWasRequired(): void {
    $plan = BiolandRemoteVideoImageField::plan(FALSE, ['alt_field' => TRUE, 'alt_field_required' => TRUE], 'Help.');

    $this->assertTrue($plan['changed']);
    $this->assertFalse($plan['settings']['alt_field_required']);
  }

}
