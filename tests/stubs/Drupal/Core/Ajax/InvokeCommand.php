<?php

namespace Drupal\Core\Ajax;

/**
 * Stub of core's InvokeCommand; exposes what render() would send.
 */
class InvokeCommand {

  /**
   * Constructs the command.
   */
  public function __construct(public $selector, public $method, public array $arguments = []) {
  }

  /**
   * Mirrors InvokeCommand::render().
   */
  public function render() {
    return ['command' => 'invoke', 'selector' => $this->selector, 'method' => $this->method, 'args' => $this->arguments];
  }

}
