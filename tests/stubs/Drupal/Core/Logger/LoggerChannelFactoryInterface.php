<?php

namespace Drupal\Core\Logger;

/**
 * Stub interface for LoggerChannelFactoryInterface.
 */
interface LoggerChannelFactoryInterface {

  /**
   * Gets a logger channel.
   *
   * @param string $channel
   *   The channel name.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger channel.
   */
  public function get($channel);

}
