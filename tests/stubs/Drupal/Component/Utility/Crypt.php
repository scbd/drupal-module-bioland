<?php

namespace Drupal\Component\Utility;

/**
 * Stub of core's Crypt: only hmacBase64(), byte-for-byte as core computes it.
 */
class Crypt {

  /**
   * Calculates a base-64 encoded, URL-safe sha-256 hmac.
   *
   * @param mixed $data
   *   Scalar value to be validated with the hmac.
   * @param mixed $key
   *   A secret key, this can be any scalar value.
   *
   * @return string
   *   A base-64 encoded sha-256 hmac, with + replaced with -, / with _ and
   *   any = padding characters removed.
   */
  public static function hmacBase64($data, $key) {
    if (!is_scalar($data) || !is_scalar($key)) {
      throw new \InvalidArgumentException('Both parameters passed to \Drupal\Component\Utility\Crypt::hmacBase64 must be scalar values.');
    }

    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', $data, $key, TRUE)));
  }

}
