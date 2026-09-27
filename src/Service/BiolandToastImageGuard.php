<?php

namespace Drupal\bioland\Service;

/**
 * Validates the edited image toast_image_editor writes over a media file.
 *
 * toast_image_editor 1.0.1 reads any request field whose name contains
 * 'toast_image_editor_data', checks only the 'data:image/' prefix, then
 * base64-decodes it and replaces the media file with the bytes. None of the
 * upload validators run. Bioland checks the same payload first: it must
 * decode to a real image whose MIME type is allowed and matches the file it
 * replaces (BL-917).
 */
final class BiolandToastImageGuard {

  /**
   * The request field name toast_image_editor reads (substring match).
   */
  public const FIELD = 'toast_image_editor_data';

  /**
   * Allowed image MIME types and the file extensions each may replace.
   */
  private const MIME_EXTENSIONS = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
    'image/gif' => ['gif'],
    'image/webp' => ['webp'],
  ];

  /**
   * Returns the submitted payloads toast_image_editor would act on.
   *
   * Mirrors MediaPresaveService::processMediaPresave(): every top-level key
   * containing FIELD, with array values read from [0]['value'] or ['value'].
   * Values without the 'data:image/' prefix are left out, because the
   * module ignores them.
   *
   * @param array $input
   *   Raw request input (POST parameters or form user input).
   *
   * @return string[]
   *   Payloads keyed by request key.
   */
  public static function payloads(array $input): array {
    $payloads = [];
    foreach ($input as $key => $value) {
      if (!is_string($key) || !str_contains($key, self::FIELD)) {
        continue;
      }
      if (is_array($value)) {
        $value = $value[0]['value'] ?? $value['value'] ?? '';
      }
      if (is_string($value) && str_starts_with($value, 'data:image/')) {
        $payloads[$key] = $value;
      }
    }
    return $payloads;
  }

  /**
   * Checks one payload against the file it would replace.
   *
   * @param string $data_url
   *   A 'data:image/...;base64,...' URL.
   * @param string $extension
   *   Extension of the media file being replaced.
   *
   * @return string|null
   *   NULL when valid, otherwise a short reason.
   */
  public static function validate(string $data_url, string $extension): ?string {
    if (!preg_match('#^data:image/\w+;base64,#i', $data_url, $match)) {
      return 'not a base64 image data URL';
    }

    $bytes = base64_decode(substr($data_url, strlen($match[0])), TRUE);
    if ($bytes === FALSE || $bytes === '') {
      return 'invalid base64 data';
    }

    $info = @getimagesizefromstring($bytes);
    if ($info === FALSE || empty($info['mime'])) {
      return 'data is not an image';
    }

    $mime = strtolower($info['mime']);
    if (!isset(self::MIME_EXTENSIONS[$mime])) {
      return 'image type ' . $mime . ' is not allowed';
    }

    if (!in_array(strtolower($extension), self::MIME_EXTENSIONS[$mime], TRUE)) {
      return 'image type ' . $mime . ' does not match the .' . strtolower($extension) . ' file it replaces';
    }

    return NULL;
  }

}
