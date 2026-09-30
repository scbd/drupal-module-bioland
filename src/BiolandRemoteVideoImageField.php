<?php

namespace Drupal\bioland;

/**
 * Pure decision logic for making the remote video image optional (BL-815).
 *
 * Sites carry a custom image field (field_media_image) on the remote_video
 * media type that is required, with a required alt text. Core already
 * populates the `thumbnail` base field from the oEmbed provider, so a name
 * and a video URL are enough; the custom image becomes an optional override.
 *
 * The field config lives on each site, not in this module, so
 * bioland_update_9088() applies the plan computed here to the live config.
 * Kept free of Drupal APIs so it is unit-testable without a bootstrap.
 *
 * Description policy: the help text is only written when the field has no
 * description (empty or whitespace). Drupal ships no stock description for
 * an image field config, so an existing description is always site-authored
 * and is left untouched.
 *
 * @see bioland_update_9088()
 */
final class BiolandRemoteVideoImageField {

  public const ENTITY_TYPE = 'media';

  public const BUNDLE = 'remote_video';

  public const FIELD_TYPE = 'image';

  public const PRIMARY_FIELD = 'field_media_image';

  /**
   * Help text set on an image field that has no description.
   *
   * A plain string, not t(): it is stored as the config's source-language
   * value (translatable via config translation), and the translation system
   * is not dependable mid-update.
   */
  public const DESCRIPTION = 'Optional. If left empty, the thumbnail supplied by the video provider is used.';

  /**
   * Whether a field definition is an image field on the remote_video media type.
   */
  public static function qualifies(string $entityType, string $bundle, string $fieldType): bool {
    return $entityType === self::ENTITY_TYPE
      && $bundle === self::BUNDLE
      && $fieldType === self::FIELD_TYPE;
  }

  /**
   * Whether a qualifying field should be processed.
   *
   * The primary field is always processed (its alt text may still be required
   * even when the field itself is not); any other image field only when it is
   * required, so optional fields a site configured on purpose are not touched.
   */
  public static function shouldProcess(string $fieldName, bool $required): bool {
    return $fieldName === self::PRIMARY_FIELD || $required;
  }

  /**
   * Compute the updated values for a qualifying image field.
   *
   * The `alt_field` setting is preserved as-is so editors can still supply
   * alternative text; only its requiredness is dropped.
   *
   * @param bool $required
   *   The field's current required flag.
   * @param array $settings
   *   The field's current settings.
   * @param string $description
   *   The field's current description.
   *
   * @return array
   *   Keys: required (bool), settings (array), description (string) and
   *   changed (bool, TRUE when any value differs from the input).
   */
  public static function plan(bool $required, array $settings, string $description): array {
    $newSettings = $settings;
    $newSettings['alt_field_required'] = FALSE;

    $newDescription = trim($description) === '' ? self::DESCRIPTION : $description;

    return [
      'required' => FALSE,
      'settings' => $newSettings,
      'description' => $newDescription,
      'changed' => $required
        || ($settings['alt_field_required'] ?? FALSE) !== FALSE
        || $newDescription !== $description,
    ];
  }

}
