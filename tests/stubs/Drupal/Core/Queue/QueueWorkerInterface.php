<?php

namespace Drupal\Core\Queue;

/**
 * Test stub for QueueWorkerInterface.
 */
interface QueueWorkerInterface {

  /**
   * Works on one queue item.
   *
   * @param mixed $data
   *   The item payload.
   */
  public function processItem($data);

}
