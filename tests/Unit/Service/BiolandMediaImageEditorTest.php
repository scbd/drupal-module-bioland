<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandMediaImageEditor;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure helpers of BiolandMediaImageEditor (BL-917).
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandMediaImageEditor
 */
class BiolandMediaImageEditorTest extends TestCase {

  /**
   * An image source edits its source field, others their first image field.
   *
   * @dataProvider fieldProvider
   * @covers ::pickImageField
   */
  public function testPickImageField(string $plugin, ?string $source, array $images, ?string $expected): void {
    $this->assertSame($expected, BiolandMediaImageEditor::pickImageField($plugin, $source, $images));
  }

  public static function fieldProvider(): array {
    return [
      'image type' => ['image', 'field_media_image', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'hero type' => ['image', 'field_media_image', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'remote video' => ['oembed:video', 'field_media_oembed_video', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'document' => ['file', 'field_media_document', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'thumbnail only is never offered' => ['file', 'field_media_document', ['thumbnail'], NULL],
      'no image field' => ['audio_file', 'field_media_audio_file', [], NULL],
      'image source not an image field' => ['image', 'field_missing', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'first non-thumbnail wins' => ['file', 'field_media_document', ['thumbnail', 'field_preview', 'field_media_image'], 'field_preview'],
    ];
  }

  /**
   * The freshly uploaded file id is found in the widget, values, then input.
   *
   * @covers ::widgetFileId
   */
  public function testWidgetFileId(): void {
    $field = 'field_media_image';
    $widget = ['widget' => [0 => ['#default_value' => ['fids' => [12]]]]];
    $this->assertSame(12, BiolandMediaImageEditor::widgetFileId($widget, [], [], $field));

    $empty = ['widget' => [0 => ['#default_value' => ['fids' => []]]]];
    $values = [$field => [0 => ['fids' => [34]]]];
    $this->assertSame(34, BiolandMediaImageEditor::widgetFileId($empty, $values, [], $field));

    // managed_file posts fids as a space-separated string.
    $input = [$field => [0 => ['fids' => '56']]];
    $this->assertSame(56, BiolandMediaImageEditor::widgetFileId($empty, [], $input, $field));

    // Nested under the widget's #field_parents (inline entity forms).
    $nested = ['wrapper' => [$field => [0 => ['fids' => [78]]]]];
    $this->assertSame(78, BiolandMediaImageEditor::widgetFileId($empty, $nested, [], $field, ['wrapper']));

    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [], $field));
    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [$field => [0 => ['fids' => '']]], $field));
    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [$field => [0 => ['fids' => 'abc']]], $field));
  }

  /**
   * The editor markup reuses the contrib module's DOM ids.
   *
   * The contrib JS looks these up by id, so a rename would silently disable
   * the editor.
   */
  public function testContribIdsAreKept(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/BiolandMediaImageEditor.php');
    foreach (['toast-image-editor-loading', 'toast-image-editor-status', 'toast-image-editor-container', 'toast-image-editor-description'] as $id) {
      $this->assertStringContainsString("'id' => '" . $id . "'", $source);
    }
    $this->assertStringContainsString("'id' => 'toast-image-editor',", $source);
    $this->assertStringContainsString("'toast_image_editor/toast-image-editor-integration'", $source);
    // The contrib fieldset is dropped so those ids stay unique.
    $this->assertStringContainsString("unset(\$form['toast_image_editor']);", $source);
  }

}
