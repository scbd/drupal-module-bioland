<?php

namespace Drupal\Tests\bioland\Unit\Routing;

use Drupal\bioland\Routing\BiolandRouteSubscriber;
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

}
