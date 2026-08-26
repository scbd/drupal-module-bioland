<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Derivative\DeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stub interface for ContainerDeriverInterface.
 */
interface ContainerDeriverInterface extends DeriverInterface {

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services.
   * @param string $base_plugin_id
   *   The base plugin ID.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, $base_plugin_id);

}
