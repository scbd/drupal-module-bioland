<?php

namespace Drupal\Component\Plugin;

/**
 * Test stub for PluginBase.
 */
abstract class PluginBase {

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The plugin definition.
   *
   * @var mixed
   */
  protected $pluginDefinition;

  /**
   * Constructs a PluginBase.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
  }

  /**
   * Gets the plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId() {
    return $this->pluginId;
  }

}
