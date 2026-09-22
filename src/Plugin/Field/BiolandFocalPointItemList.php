<?php

namespace Drupal\bioland\Plugin\Field;

use Drupal\bioland\BiolandFocalPoint;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed item list for media.bioland_focal_point.
 *
 * Carries the file and crop cache tags so cacheable consumers (JSON:API)
 * invalidate when an editor moves the point.
 */
class BiolandFocalPointItemList extends FieldItemList implements CacheableDependencyInterface {

  use ComputedItemListTrait;
  use RefinableCacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $result = BiolandFocalPoint::fromContainer()->resolve($this->getEntity());
    $this->addCacheTags($result['tags']);
    if ($result['value'] !== NULL) {
      $this->list[0] = $this->createItem(0, $result['value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $this->ensureComputedValue();
    return $this->cacheTags;
  }

}
