<?php

namespace Drupal\bioland;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Resolves the hero image focal point exposed as media.bioland_focal_point.
 *
 * Contract (read by the Nuxt front end over JSON:API): for media of bundle
 * "hero" the value is the focal point of the field_media_image file as
 * relative integer percentages "X,Y" (0-100, e.g. "50,30"). Every other case
 * - another bundle, focal_point not installed, no file, no saved crop, no
 * image dimensions - resolves to NULL.
 */
final class BiolandFocalPoint {

  public const FIELD_NAME = 'bioland_focal_point';
  public const BUNDLE = 'hero';
  public const IMAGE_FIELD = 'field_media_image';

  /**
   * @param object|null $manager
   *   The focal_point.manager service, or NULL when focal_point is absent.
   * @param string $cropType
   *   The crop type focal_point stores its point under.
   */
  public function __construct(
    private readonly ?object $manager,
    private readonly string $cropType = 'focal_point',
  ) {}

  /**
   * Builds a resolver from the container, degrading when focal_point is off.
   */
  public static function fromContainer(): self {
    if (!\Drupal::hasService('focal_point.manager')) {
      return new self(NULL);
    }
    $cropType = \Drupal::config('focal_point.settings')?->get('crop_type') ?: 'focal_point';
    return new self(\Drupal::service('focal_point.manager'), (string) $cropType);
  }

  /**
   * Resolves the focal point of a media entity.
   *
   * @return array{value: ?string, tags: string[]}
   *   The "X,Y" value (or NULL) and the cache tags it depends on. crop_list is
   *   included so a first-ever crop for the file also invalidates the result.
   */
  public function resolve(FieldableEntityInterface $media): array {
    if (!$this->manager || $media->bundle() !== self::BUNDLE || !$media->hasField(self::IMAGE_FIELD)) {
      return ['value' => NULL, 'tags' => []];
    }

    $item = $media->get(self::IMAGE_FIELD)->first();
    $file = $item?->entity;
    if (!$file) {
      return ['value' => NULL, 'tags' => []];
    }

    $tags = array_merge($file->getCacheTags(), ['crop_list']);
    // getCropEntity() returns an unsaved crop when none exists yet.
    $crop = $this->manager->getCropEntity($file, $this->cropType);
    if (!$crop || $crop->isNew()) {
      return ['value' => NULL, 'tags' => $tags];
    }
    $tags = array_values(array_unique(array_merge($tags, $crop->getCacheTags())));

    $width = (int) $item->width;
    $height = (int) $item->height;
    if ($width <= 0 || $height <= 0) {
      return ['value' => NULL, 'tags' => $tags];
    }

    $anchor = $this->manager->absoluteToRelative($crop->x->value, $crop->y->value, $width, $height);
    return ['value' => self::format($anchor), 'tags' => $tags];
  }

  /**
   * Formats a relative anchor as "X,Y", clamped to integer percentages.
   *
   * @param array{x: int|float, y: int|float} $anchor
   *   The output of FocalPointManager::absoluteToRelative().
   */
  public static function format(array $anchor): string {
    $clamp = static fn ($v): int => max(0, min(100, (int) round((float) $v)));
    return $clamp($anchor['x'] ?? 50) . ',' . $clamp($anchor['y'] ?? 50);
  }

}
