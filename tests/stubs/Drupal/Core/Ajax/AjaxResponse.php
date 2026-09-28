<?php

namespace Drupal\Core\Ajax;

/**
 * Stub of core's AjaxResponse (command collection only).
 */
class AjaxResponse {

  /**
   * The queued commands.
   *
   * @var array
   */
  protected $commands = [];

  /**
   * Queues a command.
   */
  public function addCommand($command, $prepend = FALSE) {
    $this->commands[] = $command->render();
    return $this;
  }

  /**
   * Returns the queued commands, rendered.
   */
  public function &getCommands() {
    return $this->commands;
  }

}
