<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stub interface for ContainerInjectionInterface.
 *
 * The ContainerInterface type hint is never resolved: PHP compares inherited
 * parameter types by name, and no test calls create(). This matches the
 * existing precedent in src/Form/BiolandSettingsFormBase.php, which type-hints
 * the same class without a stub for it.
 */
interface ContainerInjectionInterface {

  /**
   * Instantiates a new instance of this class from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The instantiated object.
   */
  public static function create(ContainerInterface $container);

}
