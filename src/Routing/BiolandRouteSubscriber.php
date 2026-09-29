<?php

namespace Drupal\bioland\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\Routing\RouteCollection;

/**
 * Hardens routes provided by contrib modules Bioland enables.
 */
class BiolandRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // toast_image_editor 1.0.1 exposes POST /media/{media}/save-image with no
    // CSRF token, and it overwrites the media file with the posted bytes
    // unchecked. Its own UI saves through the media form (which carries the
    // form token) and never calls this route, so deny it outright (BL-917).
    if ($route = $collection->get('toast_image_editor.media_save')) {
      $route->setRequirement('_access', 'FALSE');
    }

    // llms_txt serves /llms.txt to anonymous visitors on every site, staging
    // included. An environment that should not publish it sets
    //   $settings['bioland_llms_txt_enabled'] = FALSE;
    // in settings.php (0, '0', 'false' and 'off' also count), then rebuilds
    // caches: routes are compiled, and the page cache holds the anonymous
    // response. Purge any CDN copy too. An empty string also turns it off.
    // Unset, or any other non-boolean value, keeps the module's default:
    // served (BL-917).
    $enabled = filter_var(Settings::get('bioland_llms_txt_enabled', TRUE), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($enabled === FALSE && ($route = $collection->get('llms_txt.llms_txt'))) {
      $route->setRequirement('_access', 'FALSE');
    }
  }

}
