<?php

namespace Drupal\Component\Plugin\Derivative;

/**
 * Stub interface for DeriverInterface.
 */
interface DeriverInterface {

  /**
   * Gets a derivative definition.
   *
   * @param string $derivative_id
   *   The derivative ID.
   * @param mixed $base_plugin_definition
   *   The base plugin definition.
   *
   * @return mixed
   *   The derivative definition.
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition);

  /**
   * Gets all derivative definitions.
   *
   * @param mixed $base_plugin_definition
   *   The base plugin definition.
   *
   * @return array
   *   An array of derivatives.
   */
  public function getDerivativeDefinitions($base_plugin_definition);

}
