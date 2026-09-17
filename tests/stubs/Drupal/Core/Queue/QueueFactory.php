<?php

namespace Drupal\Core\Queue;

/**
 * Test stub for QueueFactory.
 */
class QueueFactory {

  /**
   * Gets a queue by name.
   *
   * @param string $name
   *   The queue name.
   * @param bool $reliable
   *   Whether a reliable queue is required.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  public function get($name, $reliable = FALSE) {
    throw new \LogicException('QueueFactory stub must be mocked in tests.');
  }

}
