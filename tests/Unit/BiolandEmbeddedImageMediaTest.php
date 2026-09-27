<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandEmbeddedImageMedia;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\bioland\BiolandEmbeddedImageMedia
 */
class BiolandEmbeddedImageMediaTest extends TestCase {

  /**
   * A media build like the one the media_embed filter produces.
   */
  private function build(bool $embed = TRUE): array {
    $build = [
      '#theme' => 'media',
      'field_media_image' => ['#field_name' => 'field_media_image'],
      'thumbnail' => ['#field_name' => 'thumbnail'],
      'field_machine_translated' => ['#field_name' => 'field_machine_translated'],
      'name' => ['#field_name' => 'name'],
      ':media_embed' => ['#attached' => ['library' => ['media/filter.caption']]],
    ];
    if ($embed) {
      $build['#embed'] = TRUE;
    }
    return $build;
  }

  /**
   * Only the image stays visible on an embedded image media.
   */
  public function testEmbeddedImageHidesExtraFields(): void {
    $result = BiolandEmbeddedImageMedia::strip($this->build(), 'image', 'field_media_image');

    $this->assertFalse($result['field_machine_translated']['#access']);
    $this->assertFalse($result['name']['#access']);
    $this->assertArrayNotHasKey('#access', $result['field_media_image']);
    $this->assertArrayNotHasKey('#access', $result['thumbnail']);
    $this->assertSame($this->build()[':media_embed'], $result[':media_embed']);
  }

  /**
   * Non-image media embeds (remote video, document) are untouched.
   */
  public function testNonImageSourceIsUntouched(): void {
    $build = $this->build();
    $this->assertSame($build, BiolandEmbeddedImageMedia::strip($build, 'oembed:video', 'field_media_oembed_video'));
    $this->assertSame($build, BiolandEmbeddedImageMedia::strip($build, 'file', 'field_media_document'));
  }

  /**
   * Image media rendered outside an embed keeps all its fields.
   */
  public function testImageWithoutEmbedIsUntouched(): void {
    $build = $this->build(FALSE);
    $this->assertSame($build, BiolandEmbeddedImageMedia::strip($build, 'image', 'field_media_image'));
  }

  /**
   * A media source with no source field configured leaves the build alone.
   *
   * Without a source field, `strip()` cannot tell which child is the image,
   * so hiding fields would risk hiding the image itself; better to render
   * every field, as before this fix, than hide the image entirely.
   */
  public function testNullSourceFieldIsUntouched(): void {
    $build = $this->build();
    $this->assertSame($build, BiolandEmbeddedImageMedia::strip($build, 'image', NULL));
    $this->assertSame($build, BiolandEmbeddedImageMedia::strip($build, 'image', ''));
  }

  /**
   * A pseudo/extra field listed in $extra_fields is hidden, others are kept.
   */
  public function testExtraFieldsAreHidden(): void {
    $build = $this->build();
    $build['computed_summary'] = ['#markup' => 'Summary'];
    $build['field_group'] = ['#type' => 'container'];

    $result = BiolandEmbeddedImageMedia::strip($build, 'image', 'field_media_image', ['computed_summary']);

    $this->assertFalse($result['computed_summary']['#access']);
    $this->assertArrayNotHasKey('#access', $result['field_group']);
  }

}
