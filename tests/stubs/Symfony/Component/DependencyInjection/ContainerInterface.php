<?php

namespace Symfony\Component\DependencyInjection;

/**
 * Test stub for the Symfony service container interface.
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

}
