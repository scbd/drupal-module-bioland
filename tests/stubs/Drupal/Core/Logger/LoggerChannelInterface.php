<?php

namespace Drupal\Core\Logger;

/**
 * Stub interface for LoggerChannelInterface.
 */
interface LoggerChannelInterface {

  /**
   * Logs a message.
   *
   * @param mixed $level
   *   The log level.
   * @param string $message
   *   The log message.
   * @param array $context
   *   The context.
   */
  public function log($level, $message, array $context = []);

  /**
   * Logs a debug message.
   *
   * @param string $message
   *   The log message.
   * @param array $context
   *   The context.
   */
  public function debug($message, array $context = []);

  /**
   * Logs an info message.
   *
   * @param string $message
   *   The log message.
   * @param array $context
   *   The context.
   */
  public function info($message, array $context = []);

  /**
   * Logs a warning message.
   *
   * @param string $message
   *   The log message.
   * @param array $context
   *   The context.
   */
  public function warning($message, array $context = []);

  /**
   * Logs an error message.
   *
   * @param string $message
   *   The log message.
   * @param array $context
   *   The context.
   */
  public function error($message, array $context = []);

}
