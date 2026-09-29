<?php

namespace Drupal\Component\Serialization;

/**
 * Stub of core's Json serializer (same HTML-safe flags as core).
 */
class Json {

  /**
   * Mirrors Json::encode().
   */
  public static function encode($variable) {
    return json_encode($variable, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
  }

}
