<?php

namespace Drupal\bioland\MediaLibrary;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
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
   * Whoever may edit (or create) the hero and view media may pick (BL-387).
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    $entity_id = $state->getOpenerParameters()['entity_id'] ?? NULL;

    if ($entity_id) {
      $hero = $this->entityTypeManager->getStorage('media')->load($entity_id);
      if (!$hero || $hero->bundle() !== self::HERO_BUNDLE) {
        return AccessResult::forbidden('The hero media item does not exist.');
      }
      $access = $hero->access('update', $account, TRUE);
    }
    else {
      $access = $this->entityTypeManager->getAccessControlHandler('media')
        ->createAccess(self::HERO_BUNDLE, $account, [], TRUE);
    }

    return $access->andIf(AccessResult::allowedIfHasPermission($account, 'view media'));
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
