<?php

namespace Drupal\Core\TypedData;

/**
 * Minimal stub of the core computed item list trait.
 */
trait ComputedItemListTrait {

  public function getValue() {
    $this->computeValue();
    return array_map(static fn ($item) => ['value' => $item->value], $this->list);
  }

}
