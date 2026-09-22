<?php

namespace Drupal\bioland\Plugin\Field;

use Drupal\bioland\BiolandFocalPoint;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed item list for media.bioland_focal_point.
 *
 * Carries no cacheability of its own: JSON:API caches the media resource under
 * the media:ID tag, and editors set the focal point on the hero media form,
 * which re-saves the media and invalidates that tag.
 */
class BiolandFocalPointItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if ($this->getEntity()->bundle() !== BiolandFocalPoint::BUNDLE) {
      return;
    }
    $value = BiolandFocalPoint::fromContainer()->resolve($this->getEntity());
    if ($value !== NULL) {
      $this->list[0] = $this->createItem(0, $value);
    }
  }

}
