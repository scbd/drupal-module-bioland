<?php

namespace Drupal\Core\Plugin;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test stub for ContainerFactoryPluginInterface.
 */
interface ContainerFactoryPluginInterface {

  /**
   * Creates an instance of the plugin from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The plugin instance.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition);

}
