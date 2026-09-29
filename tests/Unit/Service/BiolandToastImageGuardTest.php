<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandToastImageGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @coversDefaultClass \Drupal\bioland\Service\BiolandToastImageGuard
 * @group bioland
 */
class BiolandToastImageGuardTest extends TestCase {

  /**
   * A 1x1 PNG, what the editor exports.
   */
  private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

  /**
   * A 1x1 GIF.
   */
  private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Returns the PNG as a data URL, optionally with bytes appended.
   */
  private static function png(string $suffix = ''): string {
    return 'data:image/png;base64,' . base64_encode(base64_decode(self::PNG) . $suffix);
  }

  /**
   * The editor's PNG may replace any allowed raster file type.
   */
  public function testAcceptsEditorPngForEveryImageType(): void {
    foreach (['png', 'jpg', 'JPEG', 'gif', 'webp'] as $extension) {
      $this->assertNull(BiolandToastImageGuard::validate(self::png(), $extension), $extension);
    }
    $this->assertNull(BiolandToastImageGuard::validate('data:image/gif;base64,' . self::GIF, 'png'));
  }

  /**
   * Anything else is rejected with a reason.
   *
   * @dataProvider invalidProvider
   */
  public function testRejectsInvalidPayload(string $data_url, string $extension, string $reason): void {
    $this->assertStringContainsString($reason, (string) BiolandToastImageGuard::validate($data_url, $extension));
  }

  /**
   * Invalid payloads.
   */
  public static function invalidProvider(): array {
    // The PNG with its IHDR width and height rewritten to 60000 x 60000.
    $huge = base64_decode(self::PNG);
    $huge = substr($huge, 0, 16) . pack('NN', 60000, 60000) . substr($huge, 24);

    // A 6000 x 5000 canvas: under the per-side cap, over 25 MP.
    $wide = base64_decode(self::PNG);
    $wide = substr($wide, 0, 16) . pack('NN', 6000, 5000) . substr($wide, 24);

    return [
      'over 25 megapixels' => ['data:image/png;base64,' . base64_encode($wide), 'png', 'megapixels'],
      'non-image bytes' => ['data:image/png;base64,' . base64_encode('<?php echo 1;'), 'png', 'not an image'],
      'bad base64' => ['data:image/png;base64,%%%', 'png', 'invalid base64'],
      'empty body' => ['data:image/png;base64,', 'png', 'invalid base64'],
      'svg header' => ['data:image/svg+xml;base64,' . base64_encode('<svg/>'), 'png', 'invalid base64'],
      'oversized dimensions' => ['data:image/png;base64,' . base64_encode($huge), 'png', 'pixels on a side'],
      'svg target file' => [self::png(), 'svg', 'a .svg file cannot be replaced'],
      'no source file' => [self::png(), '', 'no source file'],
    ];
  }

  /**
   * Re-encoding writes the replaced file's own format.
   */
  public function testReencodesToTargetFormat(): void {
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'] as $extension => $mime) {
      $out = BiolandToastImageGuard::reencode(self::png(), $extension);
      $this->assertIsString($out, $extension);
      $this->assertStringStartsWith('data:' . $mime . ';base64,', $out);
      $bytes = base64_decode(substr($out, strpos($out, ',') + 1), TRUE);
      $this->assertSame($mime, getimagesizefromstring($bytes)['mime'], $extension);
    }
  }

  /**
   * A polyglot's trailing bytes do not survive re-encoding.
   */
  public function testReencodeDropsTrailingBytes(): void {
    $polyglot = self::png('<?php system($_GET["c"]); ?>');
    $this->assertNull(BiolandToastImageGuard::validate($polyglot, 'png'), 'Header-only checks pass the polyglot.');

    $out = BiolandToastImageGuard::reencode($polyglot, 'png');
    $this->assertStringNotContainsString('<?php', base64_decode(substr($out, strpos($out, ',') + 1)));
  }

  /**
   * Sanitize removes invalid payloads and rewrites valid ones in place.
   */
  public function testSanitizeRemovesInvalidAndReencodesValid(): void {
    $post = new ParameterBag([
      'toast_image_editor_data' => self::png(),
      'x_toast_image_editor_data' => [['value' => 'data:image/png;base64,' . base64_encode('not an image')]],
      'title' => 'kept',
    ]);

    $dropped = BiolandToastImageGuard::sanitize($post, 'jpg', TRUE);

    $this->assertSame(['x_toast_image_editor_data' => 'data is not an image'], $dropped);
    $this->assertFalse($post->has('x_toast_image_editor_data'));
    $this->assertStringStartsWith('data:image/jpeg;base64,', $post->get('toast_image_editor_data'));
    $this->assertSame('kept', $post->get('title'));
  }

  /**
   * Payload discovery mirrors toast_image_editor's request lookup.
   */
  public function testPayloadsMirrorModuleLookup(): void {
    $png = self::png();
    $input = [
      'toast_image_editor_data' => $png,
      'x_toast_image_editor_data' => [['value' => $png]],
      'toast_image_editor_data_2' => ['value' => $png],
      'toast_image_editor_data_empty' => '',
      'toast_image_editor_data_text' => 'hello',
      'title' => $png,
    ];

    $this->assertSame(
      ['toast_image_editor_data', 'x_toast_image_editor_data', 'toast_image_editor_data_2'],
      array_keys(BiolandToastImageGuard::payloads($input))
    );
  }

  /**
   * A user who cannot edit the image never gets a payload decoded.
   */
  public function testSanitizeDropsEveryPayloadWithoutPermission(): void {
    $post = new ParameterBag(['toast_image_editor_data' => self::png(), 'title' => 'kept']);

    $dropped = BiolandToastImageGuard::sanitize($post, 'png', FALSE);

    $this->assertSame(['toast_image_editor_data' => 'no permission to edit this image'], $dropped);
    $this->assertSame(['title' => 'kept'], $post->all());
  }

  /**
   * A decode that would not fit in free memory is refused before GD runs.
   */
  public function testRejectsDecodeLargerThanFreeMemory(): void {
    $previous = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(TRUE) + 1024 * 1024));
    try {
      // 4000 x 4000 = 16 MP, about 80 MB decoded: within the caps, over 1 MB free.
      $png = base64_decode(self::PNG);
      $png = substr($png, 0, 16) . pack('NN', 4000, 4000) . substr($png, 24);
      $reason = BiolandToastImageGuard::validate('data:image/png;base64,' . base64_encode($png), 'png');
    }
    finally {
      ini_set('memory_limit', $previous);
    }
    $this->assertSame('image is too large for the server memory', $reason);
  }

}
