<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stub interface for container-injected classes.
 */
interface ContainerInjectionInterface {

  /**
   * Instantiates a new instance of this class from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The new instance.
   */
  public static function create(ContainerInterface $container);

}
