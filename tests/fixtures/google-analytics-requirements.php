<?php

/**
 * Isolated facade for executing the real requirements hook without a Drupal site.
 * Run only by BiolandGoogleAnalyticsMisconfigurationTest in a fresh PHP process.
 */
const REQUIREMENT_WARNING = 1;
const REQUIREMENT_ERROR = 2;

class Drupal
{

  // The override-applied value, as a settings.php override would present
  // it through Config::get(). Defaults to the raw value when the caller
  // supplies no separate override, so the un-overridden test cases need
  // only one value.
  public static $override;

  // The override-free, raw active-storage value, as Config::getOriginal($key,
  // FALSE) returns it and as dmsm's direct-SQL read and the settings-form
  // save/clear actually see it.
  public static $raw;

  public static $phase;

  public static function moduleHandler()
  {
    return new class {
      public function moduleExists($name)
      {
        // Avoid loading node storage; unrelated dependency requirements are kept.
        return FALSE;
      }
    };
  }

  public static function config($name)
  {
    if (self::$phase !== 'runtime' || $name !== 'bioland.settings') {
      throw new RuntimeException('Unexpected configuration read.');
    }
    return new class {
      public function get($key)
      {
        if ($key !== 'google_analytics_enabled') {
          throw new RuntimeException('Unexpected configuration key.');
        }
        return Drupal::$override;
      }

      public function getOriginal($key, $apply_overrides = TRUE)
      {
        if ($key !== 'google_analytics_enabled') {
          throw new RuntimeException('Unexpected configuration key.');
        }
        if ($apply_overrides) {
          // The misconfigured-type check must always request the
          // override-free value; a caller that forgets the FALSE flag
          // would silently fall back to the override-applied value again.
          throw new RuntimeException('getOriginal() must be called with $apply_overrides = FALSE.');
        }
        return Drupal::$raw;
      }
    };
  }

  public static function hasRequest()
  {
    return FALSE;
  }

}

function t($text, array $args = [])
{
  foreach ($args as $key => $value) {
    $text = str_replace($key, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $text);
  }
  return $text;
}

$input = json_decode(stream_get_contents(STDIN), FALSE, 512, JSON_THROW_ON_ERROR);
Drupal::$raw = $input->value;
Drupal::$override = property_exists($input, 'override') ? $input->override : $input->value;
Drupal::$phase = $input->phase;
require dirname(__DIR__, 2) . '/bioland.install';
print json_encode(bioland_requirements(Drupal::$phase), JSON_THROW_ON_ERROR);
