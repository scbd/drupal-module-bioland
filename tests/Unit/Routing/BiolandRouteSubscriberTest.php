<?php

namespace Drupal\Tests\bioland\Unit\Routing;

use Drupal\bioland\Routing\BiolandRouteSubscriber;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @coversDefaultClass \Drupal\bioland\Routing\BiolandRouteSubscriber
 * @group bioland
 */
class BiolandRouteSubscriberTest extends TestCase {

  /**
   * The unprotected toast_image_editor save route is denied.
   */
  public function testDeniesToastImageEditorSaveRoute(): void {
    $collection = new RouteCollection();
    $collection->add('toast_image_editor.media_save', new Route('/media/{media}/save-image', [], [
      '_permission' => 'use toast image editor',
      '_entity_access' => 'media.update',
      '_method' => 'POST',
    ]));

    (new BiolandRouteSubscriber())->onAlterRoutes($collection);

    $route = $collection->get('toast_image_editor.media_save');
    $this->assertSame('FALSE', $route->getRequirement('_access'));
    // The module's own checks stay in place alongside the denial.
    $this->assertSame('use toast image editor', $route->getRequirement('_permission'));
  }

  /**
   * Nothing breaks when toast_image_editor is not enabled.
   */
  public function testNoopWithoutToastImageEditor(): void {
    $collection = new RouteCollection();
    $collection->add('system.admin', new Route('/admin', [], ['_permission' => 'access administration pages']));

    (new BiolandRouteSubscriber())->onAlterRoutes($collection);

    $this->assertNull($collection->get('system.admin')->getRequirement('_access'));
    $this->assertNull($collection->get('toast_image_editor.media_save'));
  }

  /**
   * /llms.txt stays public unless settings.php turns it off.
   */
  public function testLlmsTxtFollowsSetting(): void {
    foreach ([[NULL, NULL], [TRUE, NULL], [FALSE, 'FALSE']] as [$setting, $expected]) {
      new Settings($setting === NULL ? [] : ['bioland_llms_txt_enabled' => $setting]);
      $collection = new RouteCollection();
      $collection->add('llms_txt.llms_txt', new Route('/llms.txt', [], ['_access' => 'TRUE']));

      (new BiolandRouteSubscriber())->onAlterRoutes($collection);

      $this->assertSame($expected ?? 'TRUE', $collection->get('llms_txt.llms_txt')->getRequirement('_access'));
    }
    new Settings([]);
  }

}
