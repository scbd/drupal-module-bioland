<?php

namespace Symfony\Component\DependencyInjection;

/**
 * Stub interface for the Symfony service container.
 *
 * Only the two accessors Drupal's create() factories use are declared.
 */
interface ContainerInterface {

  /**
   * Gets a service by id.
   *
   * @param string $id
   *   The service id.
   *
   * @return mixed
   *   The service.
   */
  public function get($id);

  /**
   * Tells whether a service id is defined.
   *
   * @param string $id
   *   The service id.
   *
   * @return bool
   *   TRUE when the service exists.
   */
  public function has($id);

}
