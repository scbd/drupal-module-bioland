<?php

namespace Symfony\Component\DependencyInjection;

/**
 * Stub interface for the Symfony service container.
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

  /**
   * Gets a container parameter.
   *
   * @param string $name
   *   The parameter name.
   *
   * @return mixed
   *   The parameter value.
   */
  public function getParameter($name);

}
