<?php

namespace Drupal\bioland\Service;

/**
 * Validates the edited image toast_image_editor writes over a media file.
 *
 * toast_image_editor 1.0.1 reads any request field whose name contains
 * 'toast_image_editor_data', checks only the 'data:image/' prefix, then
 * base64-decodes it and replaces the media file with the bytes. None of the
 * upload validators run. Bioland checks the same payload first: it must
 * decode to a real raster image of an allowed type within a size cap. Its
 * editor always exports PNG (toDataURL() with no options), so the payload is
 * then re-encoded to the replaced file's own format, which also drops any
 * bytes trailing the image (BL-917).
 */
final class BiolandToastImageGuard {

  /**
   * The request field name toast_image_editor reads (substring match).
   */
  public const FIELD = 'toast_image_editor_data';

  /**
   * Largest accepted width or height, in pixels.
   */
  public const MAX_DIMENSION = 10000;

  /**
   * Largest accepted image area, in pixels (25 MP).
   */
  public const MAX_PIXELS = 25000000;

  /**
   * Largest accepted decoded payload, in bytes.
   */
  public const MAX_BYTES = 20 * 1024 * 1024;

  /**
   * GD image type flag for each MIME type.
   */
  private const GD_TYPES = [
    'image/jpeg' => 'IMG_JPG',
    'image/png' => 'IMG_PNG',
    'image/gif' => 'IMG_GIF',
    'image/webp' => 'IMG_WEBP',
  ];

  /**
   * Allowed image MIME types.
   */
  private const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  /**
   * Replaceable file extensions and the MIME type each is re-encoded to.
   */
  private const EXTENSION_MIME = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
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
    $extension = strtolower($extension);
    if (!isset(self::EXTENSION_MIME[$extension])) {
      return $extension === ''
        ? 'the media has no source file'
        : 'a .' . $extension . ' file cannot be replaced by an edited image';
    }

    $bytes = self::decode($data_url);
    if ($bytes === NULL) {
      return 'invalid base64 image data';
    }
    if (strlen($bytes) > self::MAX_BYTES) {
      return 'image is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB';
    }

    $info = @getimagesizefromstring($bytes);
    if ($info === FALSE || empty($info['mime'])) {
      return 'data is not an image';
    }

    $mime = strtolower($info['mime']);
    if (!in_array($mime, self::MIMES, TRUE)) {
      return 'image type ' . $mime . ' is not allowed';
    }
    if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
      return 'image is larger than ' . self::MAX_DIMENSION . ' pixels on a side';
    }
    // The header is all that has been read so far. A tiny, highly compressed
    // file can still claim a huge canvas, so bound the decode before GD
    // allocates it.
    if ($info[0] * $info[1] > self::MAX_PIXELS) {
      return 'image is larger than ' . (self::MAX_PIXELS / 1000000) . ' megapixels';
    }

    $target = self::EXTENSION_MIME[$extension];
    if (!self::gdSupports($mime) || !self::gdSupports($target)) {
      return 'this server cannot convert ' . $mime . ' to ' . $target;
    }
    if (!self::fitsInMemory($info[0] * $info[1], $target === 'image/jpeg')) {
      return 'image is too large for the server memory';
    }

    return NULL;
  }

  /**
   * Whether GD can read and write a MIME type on this server.
   */
  private static function gdSupports(string $mime): bool {
    return function_exists('imagetypes') && isset(self::GD_TYPES[$mime])
      && (imagetypes() & constant(self::GD_TYPES[$mime]));
  }

  /**
   * Whether decoding (and, for JPEG, flattening) fits in free memory.
   *
   * GD holds about 5 bytes per pixel for a truecolor image; the JPEG path
   * keeps a second copy while it flattens alpha onto white.
   */
  private static function fitsInMemory(int $pixels, bool $flatten): bool {
    $limit = self::bytes((string) ini_get('memory_limit'));
    if ($limit <= 0) {
      return TRUE;
    }
    return $pixels * 5 * ($flatten ? 2 : 1) < ($limit - memory_get_usage(TRUE));
  }

  /**
   * Converts a php.ini size ('256M') to bytes; -1 means unlimited.
   */
  private static function bytes(string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '-1') {
      return -1;
    }
    $number = (int) $value;
    return match (strtolower(substr($value, -1))) {
      'g' => $number * 1024 ** 3,
      'm' => $number * 1024 ** 2,
      'k' => $number * 1024,
      default => $number,
    };
  }

  /**
   * Re-encodes a valid payload to the replaced file's own format.
   *
   * Call only after validate() returned NULL. The fresh encode carries pixel
   * data only, so a polyglot's trailing bytes do not survive.
   *
   * @param string $data_url
   *   A payload that passed validate().
   * @param string $extension
   *   Extension of the media file being replaced.
   *
   * @return string|null
   *   A data URL in the target format, or NULL when GD cannot decode or
   *   encode it.
   */
  public static function reencode(string $data_url, string $extension): ?string {
    $mime = self::EXTENSION_MIME[strtolower($extension)] ?? NULL;
    $bytes = self::decode($data_url);
    if ($mime === NULL || $bytes === NULL || !function_exists('imagecreatefromstring')) {
      return NULL;
    }

    $image = @imagecreatefromstring($bytes);
    if ($image === FALSE) {
      return NULL;
    }

    if ($mime === 'image/jpeg') {
      // JPEG has no alpha: flatten onto white, not the default black.
      $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
      imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
      imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
      imagedestroy($image);
      $image = $flat;
    }
    elseif ($mime !== 'image/gif') {
      imagealphablending($image, FALSE);
      imagesavealpha($image, TRUE);
    }

    ob_start();
    try {
      $ok = match ($mime) {
        'image/jpeg' => imagejpeg($image, NULL, 90),
        'image/png' => imagepng($image),
        'image/gif' => imagegif($image),
        'image/webp' => imagewebp($image, NULL, 90),
      };
    }
    finally {
      $encoded = ob_get_clean();
      imagedestroy($image);
    }

    return $ok && $encoded !== '' ? 'data:' . $mime . ';base64,' . base64_encode($encoded) : NULL;
  }

  /**
   * Cleans the POST bag toast_image_editor will read.
   *
   * Removes every invalid payload (fail closed: the module then finds nothing
   * and writes nothing) and replaces every valid one with its re-encoded
   * form.
   *
   * @param \Symfony\Component\HttpFoundation\ParameterBag $post
   *   The current request's POST bag (the same object the module reads).
   * @param string $extension
   *   Extension of the media file being replaced.
   * @param bool $allowed
   *   Whether the current user may edit this image (toast_image_editor's own
   *   permission and media update access). When FALSE every payload is
   *   removed without being decoded.
   *
   * @return string[]
   *   Reasons for each removed payload, keyed by request key.
   */
  public static function sanitize(object $post, string $extension, bool $allowed): array {
    $dropped = [];
    foreach (self::payloads($post->all()) as $key => $payload) {
      // toast_image_editor would refuse this user anyway; never decode a
      // payload they cannot save.
      $reason = $allowed ? self::validate($payload, $extension) : 'no permission to edit this image';
      $reencoded = $reason === NULL ? self::reencode($payload, $extension) : NULL;
      if ($reencoded === NULL) {
        $post->remove($key);
        $dropped[$key] = $reason ?? 'could not re-encode the image';
        continue;
      }
      $post->set($key, $reencoded);
    }
    return $dropped;
  }

  /**
   * Decodes a data URL exactly as toast_image_editor does.
   *
   * @return string|null
   *   The bytes, or NULL when the header or base64 is invalid.
   */
  private static function decode(string $data_url): ?string {
    if (!preg_match('#^data:image/\w+;base64,#i', $data_url, $match)) {
      return NULL;
    }
    $bytes = base64_decode(substr($data_url, strlen($match[0])), TRUE);
    return $bytes === FALSE || $bytes === '' ? NULL : $bytes;
  }

}
