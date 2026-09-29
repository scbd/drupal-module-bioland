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
   * Deletes an item from the queue.
   *
   * @param object $item
   *   The item, carrying an item_id property.
   */
  public function deleteItem($item);

  /**
   * Counts the items in the queue.
   *
   * @return int
   *   The number of items.
   */
  public function numberOfItems();

}
