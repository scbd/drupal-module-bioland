<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandToastImageGuard;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\bioland\Service\BiolandToastImageGuard
 * @group bioland
 */
class BiolandToastImageGuardTest extends TestCase {

  /**
   * A 1x1 PNG.
   */
  private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

  /**
   * A 1x1 GIF.
   */
  private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Real images of the file's own type pass.
   */
  public function testAcceptsMatchingImage(): void {
    $this->assertNull(BiolandToastImageGuard::validate('data:image/png;base64,' . self::PNG, 'png'));
    $this->assertNull(BiolandToastImageGuard::validate('data:image/gif;base64,' . self::GIF, 'GIF'));
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
    return [
      'non-image bytes' => ['data:image/png;base64,' . base64_encode('<?php echo 1;'), 'png', 'not an image'],
      'bad base64' => ['data:image/png;base64,%%%', 'png', 'invalid base64'],
      'empty body' => ['data:image/png;base64,', 'png', 'invalid base64'],
      'svg header' => ['data:image/svg+xml;base64,' . base64_encode('<svg/>'), 'svg', 'not a base64 image data URL'],
      'png over a jpg' => ['data:image/png;base64,' . self::PNG, 'jpg', 'does not match the .jpg file'],
      'no source file' => ['data:image/png;base64,' . self::PNG, '', 'does not match'],
    ];
  }

  /**
   * Payload discovery mirrors toast_image_editor's request lookup.
   */
  public function testPayloadsMirrorModuleLookup(): void {
    $png = 'data:image/png;base64,' . self::PNG;
    $input = [
      'toast_image_editor_data' => $png,
      'toast_image_editor_data[0][value]' => $png,
      'x_toast_image_editor_data' => [['value' => $png]],
      'toast_image_editor_data_2' => ['value' => $png],
      'toast_image_editor_data_empty' => '',
      'toast_image_editor_data_text' => 'hello',
      'title' => $png,
    ];

    $this->assertSame(
      ['toast_image_editor_data', 'toast_image_editor_data[0][value]', 'x_toast_image_editor_data', 'toast_image_editor_data_2'],
      array_keys(BiolandToastImageGuard::payloads($input))
    );
  }

}
