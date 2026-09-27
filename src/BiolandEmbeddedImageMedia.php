<?php

namespace Drupal\bioland;

/**
 * Strips field text from image media embedded in a text body (BL-1188).
 *
 * Core's media_embed filter renders the media entity through its view
 * display, so every extra field on an image media type (for example a
 * "Contains machine translations?" flag) prints under the embedded image in
 * both the CKEditor preview and the published page. An embed should show the
 * image only, so every field except the source image and the thumbnail is
 * hidden. Non-field children such as the filter's `:media_embed` asset
 * holder are kept.
 *
 * Only applies to render arrays flagged `#embed` by the filter. The filter
 * also removes the embed's render-cache keys, so this never leaks into the
 * cached output of the same view mode rendered elsewhere.
 *
 * Kept free of Drupal APIs so it is unit-testable without a bootstrap.
 *
 * @see bioland_media_view_alter()
 * @see \Drupal\media\Plugin\Filter\MediaEmbed::renderMedia()
 */
final class BiolandEmbeddedImageMedia {

  private const SOURCE_PLUGIN = 'image';

  /**
   * Hides every non-image field on an embedded image media build.
   *
   * @param array $build
   *   The media entity render array.
   * @param string $source_plugin_id
   *   The media type's source plugin id.
   * @param string|null $source_field
   *   The media type's source field name.
   * @param array $extra_fields
   *   Names of the display's pseudo/extra field components (registered via
   *   hook_entity_extra_field_info(), so they have no #field_name) to hide
   *   alongside the real fields.
   *
   * @return array
   *   The render array with the extra fields hidden.
   */
  public static function strip(array $build, string $source_plugin_id, ?string $source_field, array $extra_fields = []): array {
    if (empty($build['#embed']) || $source_plugin_id !== self::SOURCE_PLUGIN || empty($source_field)) {
      return $build;
    }

    $keep = array_filter([$source_field, 'thumbnail']);
    foreach ($build as $key => $child) {
      if (!is_array($child)) {
        continue;
      }
      $is_hidden_field = isset($child['#field_name']) && !in_array($child['#field_name'], $keep, TRUE);
      $is_hidden_extra_field = !isset($child['#field_name']) && in_array($key, $extra_fields, TRUE);
      if ($is_hidden_field || $is_hidden_extra_field) {
        // A boolean FALSE (rather than an AccessResult) drops the hidden
        // child's cache metadata, which is fine here: the hidden output is
        // an empty string regardless of the viewing user or entity state,
        // so there is nothing cacheable to preserve.
        $build[$key]['#access'] = FALSE;
      }
    }

    return $build;
  }

}
