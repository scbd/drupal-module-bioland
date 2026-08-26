<?php

namespace Drupal\Component\Plugin\Derivative;

/**
 * Stub class for DeriverBase.
 */
abstract class DeriverBase implements DeriverInterface {

  /**
   * List of derivatives.
   *
   * @var array
   */
  protected $derivatives = [];

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    $derivatives = $this->getDerivativeDefinitions($base_plugin_definition);
    return $derivatives[$derivative_id] ?? NULL;
  }

}
