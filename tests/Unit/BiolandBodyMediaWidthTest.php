<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandBodyMediaWidth;
use PHPUnit\Framework\TestCase;

/**
 * Pins the wrapper class the Nuxt head sizes Body media presets by (BL-917).
 *
 * @group bioland
 * @coversDefaultClass \Drupal\bioland\BiolandBodyMediaWidth
 */
class BiolandBodyMediaWidthTest extends TestCase {

  /**
   * @covers ::viewModeClass
   */
  public function testPresetViewModeMapsToDashedClass(): void {
    $this->assertSame('media--view-mode-bioland-width-50', BiolandBodyMediaWidth::viewModeClass('bioland_width_50'));
    $this->assertSame('media--view-mode-bioland-width-100', BiolandBodyMediaWidth::viewModeClass('bioland_width_100'));
  }

  /**
   * @covers ::viewModeClass
   */
  public function testOtherViewModesGetNoClass(): void {
    foreach (['default', 'full', 'bioland_width_', 'bioland_width_50x', 'xbioland_width_50'] as $view_mode) {
      $this->assertNull(BiolandBodyMediaWidth::viewModeClass($view_mode), $view_mode);
    }
  }

}
