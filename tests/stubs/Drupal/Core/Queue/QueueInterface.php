<?php

namespace Drupal\Core\Queue;

/**
 * Test stub for QueueInterface.
 */
interface QueueInterface {

  /**
   * Adds an item to the queue.
   *
   * @param mixed $data
   *   The item payload.
   *
   * @return mixed
   *   The item id, or FALSE on failure.
   */
  public function createItem($data);

  /**
   * Counts the items in the queue.
   *
   * @return int
   *   The number of items.
   */
  public function numberOfItems();

}
