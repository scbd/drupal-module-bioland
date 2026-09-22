<?php

namespace Drupal\bioland\MediaLibrary;

use Drupal\bioland\Service\BiolandHeroMediaLibrary;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_library\MediaLibraryOpenerInterface;
use Drupal\media_library\MediaLibraryState;

/**
 * Media Library opener for the hero media image picker (BL-807).
 *
 * The hero bundle's field_media_image stays a plain image (file) field, so the
 * JSON:API shape the front end reads is untouched. This opener only hands the
 * chosen media id back to BiolandHeroMediaLibrary's hidden controls on the
 * hero form, which copy the media's source file into that field.
 *
 * Only instantiated by media_library's opener resolver, so the interface it
 * implements never has to exist on a site without media_library.
 */
class BiolandHeroImageOpener implements MediaLibraryOpenerInterface {

  /**
   * The media bundle whose form carries the picker.
   */
  public const HERO_BUNDLE = 'hero';

  /**
   * Constructs the opener.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   *
   * Mirrors core's MediaLibraryFieldWidgetOpener: update (or create) access
   * on the hero, then edit access on its image field. 'view media' is not
   * checked here; MediaLibraryUiBuilder::checkAccess() ANDs it in (BL-387).
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    $entity_id = $state->getOpenerParameters()['entity_id'] ?? NULL;
    $storage = $this->entityTypeManager->getStorage('media');
    $access_handler = $this->entityTypeManager->getAccessControlHandler('media');

    if ($entity_id) {
      $hero = $storage->load($entity_id);
      if (!$hero) {
        return AccessResult::forbidden('The hero media item does not exist.')->addCacheableDependency($state);
      }
      if ($hero->bundle() !== self::HERO_BUNDLE) {
        return AccessResult::forbidden('The media item is not a hero media item.')->addCacheableDependency($state);
      }
      $entity_access = $access_handler->access($hero, 'update', $account, TRUE);
    }
    else {
      $entity_access = $access_handler->createAccess(self::HERO_BUNDLE, $account, [], TRUE);
    }

    if (!$entity_access->isAllowed()) {
      if ($entity_access instanceof RefinableCacheableDependencyInterface) {
        $entity_access->addCacheableDependency($state);
      }
      return $entity_access;
    }

    $hero ??= $storage->create(['bundle' => self::HERO_BUNDLE]);
    $items = $hero->get(BiolandHeroMediaLibrary::FIELD_NAME);
    $access = $entity_access->andIf($access_handler->fieldAccess('edit', $items->getFieldDefinition(), $account, $items, TRUE));
    if ($access instanceof RefinableCacheableDependencyInterface) {
      $access->addCacheableDependency($state);
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   *
   * Mirrors core's field widget opener: fill the hidden input, then fire the
   * hidden AJAX button (bound to mousedown) that applies the selection.
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids) {
    $widget_id = $state->getOpenerParameters()['widget_id'] ?? '';
    if (!is_string($widget_id) || !preg_match('/^[a-z0-9_-]+$/', $widget_id)) {
      throw new \InvalidArgumentException('The hero image opener requires a valid widget_id.');
    }

    $ids = implode(',', array_map('intval', $selected_ids));

    return (new AjaxResponse())
      ->addCommand(new InvokeCommand("[data-bioland-hero-media-value=\"$widget_id\"]", 'val', [$ids]))
      ->addCommand(new InvokeCommand("[data-bioland-hero-media-update=\"$widget_id\"]", 'trigger', ['mousedown']));
  }

}
