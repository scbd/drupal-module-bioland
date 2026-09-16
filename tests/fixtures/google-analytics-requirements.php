<?php

/**
 * Isolated facade for executing the real requirements hook without a Drupal site.
 * Run only by BiolandGoogleAnalyticsMisconfigurationTest in a fresh PHP process.
 */
const REQUIREMENT_WARNING = 1;
const REQUIREMENT_ERROR = 2;

class Drupal {

  public static $stored;
  public static $phase;

  public static function moduleHandler() {
    return new class {
      public function moduleExists($name) {
        // Avoid loading node storage; unrelated dependency requirements are kept.
        return FALSE;
      }
    };
  }

  public static function config($name) {
    if (self::$phase !== 'runtime' || $name !== 'bioland.settings') {
      throw new RuntimeException('Unexpected configuration read.');
    }
    return new class {
      public function get($key) {
        if ($key !== 'google_analytics_enabled') {
          throw new RuntimeException('Unexpected configuration key.');
        }
        return Drupal::$stored;
      }
    };
  }

  public static function hasRequest() {
    return FALSE;
  }

}

function t($text, array $args = []) {
  foreach ($args as $key => $value) {
    $text = str_replace($key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $text);
  }
  return $text;
}

$input = json_decode(stream_get_contents(STDIN), FALSE, 512, JSON_THROW_ON_ERROR);
Drupal::$stored = $input->value;
Drupal::$phase = $input->phase;
require dirname(__DIR__, 2) . '/bioland.install';
print json_encode(bioland_requirements(Drupal::$phase), JSON_THROW_ON_ERROR);
