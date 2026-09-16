<?php

namespace Drupal\bioland\Service;

/**
 * Composes the Bioland site configuration document served over HTTP.
 *
 * This class is deliberately free of Drupal dependencies: it takes plain
 * arrays and returns a plain array, so the allowlist, the camelCase transform
 * and the credential scrubber are all unit-testable without a bootstrap.
 *
 * What is included:
 * - `bioland.settings`, built ADDITIVELY from ::BIOLAND_SETTINGS_ALLOWLIST.
 *   Only a key named in that constant can ever reach the wire. A key added
 *   upstream in config/schema/bioland.schema.yml does NOT ship until it is
 *   added here on purpose.
 * - `system.site` name plus its per-language overrides.
 * - `system.date` default timezone.
 *
 * What is deliberately excluded:
 * - Everything not in the allowlist, by construction rather than by filtering.
 * - Every never-ship key name from the site-config contract (R2), matched at
 *   any depth in a punctuation- and case-insensitive form, so `dataBase`,
 *   `data_base` and `DATABASE` are all caught.
 * - Every credential-SHAPED value, at any depth, whatever the key is called.
 *   A site administrator can paste a password into a benign free-text admin
 *   field such as `help_comments.body_text`; no key-name pattern catches that,
 *   so values are matched too (PEM blocks, credential URIs, JWTs, and
 *   high-entropy opaque strings).
 *
 * @see \Drupal\bioland\Controller\BiolandConfigController
 */
final class BiolandConfigDocumentBuilder {

  /**
   * The document schema version. Consumers reject a non-integer version.
   */
  public const DOCUMENT_VERSION = 1;

  /**
   * The explicit include-list of `bioland.settings` top-level keys.
   *
   * Sourced from config/schema/bioland.schema.yml plus the three keys the
   * site-config contract names that this module version does not yet declare
   * in its schema (`google_analytics_ids`, `component_menu_*`, `theme`).
   * Listing them now means a site that already stores them serves them, and a
   * later schema addition needs no second change here.
   *
   * No key on this list can hold a credential. Secret-bearing names have
   * nowhere to go because they are simply not named.
   */
  public const BIOLAND_SETTINGS_ALLOWLIST = [
    'countries',
    'region',
    'continent',
    'is_biosafety_land',
    'enable_field_visibility',
    'field_visibility',
    'enable_additional_fields',
    'additional_tags',
    'enable_auto_summary',
    'enable_help_comments',
    'help_comments',
    'field_visibility_rules',
    'config',
    'google_analytics_ids',
    'enable_debug_logging',
    'debug_log_areas',
    'main_menu_lock',
    'component_menu_add_enabled',
    'component_menu_show_attributes',
    'mega_menu',
    'home_widgets',
    'translation',
    'theme',
  ];

  /**
   * Never-ship key names (contract R2), normalized to lowercase alphanumerics.
   */
  private const DENY_KEYS = [
    'database', 'databasename', 'dns', 'drupal', 'drupalroot', 'siteroot',
    'root', 'auth', 'meta', 'panoramakey', 'smtpcredentials',
    'defaultsmtpcredentials',
  ];

  /**
   * Never-ship key substrings, matched against the same normalized form.
   */
  private const DENY_KEY_SUBSTRINGS = [
    'password', 'passwd', 'secret', 'token', 'apikey', 'privatekey',
    'credential', 'hashsalt', 'dsn',
  ];

  /**
   * Count of values dropped by the credential scrubber on the last build.
   *
   * @var int
   */
  private int $scrubbed = 0;

  /**
   * Builds the full document envelope.
   *
   * @param array|null $biolandSettings
   *   Raw `bioland.settings` data, or NULL/[] on a site that never saved it.
   * @param array $systemSite
   *   `['name' => string, 'translations' => [langcode => ['name' => string]]]`.
   * @param array $systemDate
   *   `['timezone' => ['default' => string]]`.
   * @param string $siteCode
   *   The site code this Drupal install serves.
   * @param string $generated
   *   An ISO-8601 UTC timestamp.
   *
   * @return array
   *   The document, ready to serialize.
   */
  public function build(?array $biolandSettings, array $systemSite, array $systemDate, string $siteCode, string $generated): array {
    $this->scrubbed = 0;

    $config = [];
    // A site with no saved settings gets the section omitted, not a 500.
    if (!empty($biolandSettings)) {
      $projected = $this->projectBiolandSettings($biolandSettings);
      if ($projected !== []) {
        $config['biolandSettings'] = $projected;
      }
    }
    $config['systemSite'] = $this->scrub($this->camelCaseKeys($systemSite));
    $config['systemDate'] = $this->scrub($this->camelCaseKeys($systemDate));

    return [
      'version' => self::DOCUMENT_VERSION,
      'generated' => $generated,
      'siteCode' => $siteCode,
      'config' => $config,
    ];
  }

  /**
   * Number of values the credential scrubber dropped during the last build.
   */
  public function getScrubbedCount(): int {
    return $this->scrubbed;
  }

  /**
   * Builds the `biolandSettings` section additively from the allowlist.
   *
   * @param array $raw
   *   Raw `bioland.settings` data.
   *
   * @return array
   *   camelCased, scrubbed, allowlisted settings.
   */
  public function projectBiolandSettings(array $raw): array {
    $included = [];
    foreach (self::BIOLAND_SETTINGS_ALLOWLIST as $key) {
      if (array_key_exists($key, $raw)) {
        $included[$key] = $raw[$key];
      }
    }
    return $this->scrub($this->camelCaseKeys($included));
  }

  /**
   * Converts one snake_case key to camelCase.
   *
   * A key that contains no underscore, or that does not match the snake_case
   * grammar, is returned unchanged. That keeps langcode-shaped map keys such
   * as `fr` or `zh-hans` and already-camel keys such as `backGround` intact.
   */
  public static function camelCase(string $key): string {
    if (!preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)+$/', $key)) {
      return $key;
    }
    return lcfirst(str_replace('_', '', ucwords($key, '_')));
  }

  /**
   * Recursively camelCases array keys, failing loudly on a collision.
   *
   * @throws \RuntimeException
   *   When two distinct source keys map onto one wire name, which would
   *   silently drop one of them.
   */
  public function camelCaseKeys(array $data): array {
    $out = [];
    $origin = [];
    foreach ($data as $key => $value) {
      if (is_int($key)) {
        $out[$key] = is_array($value) ? $this->camelCaseKeys($value) : $value;
        continue;
      }
      $mapped = self::camelCase((string) $key);
      if (isset($origin[$mapped])) {
        throw new \RuntimeException(sprintf('camelCase collision: "%s" and "%s" both map to "%s".', $origin[$mapped], $key, $mapped));
      }
      $origin[$mapped] = $key;
      $out[$mapped] = is_array($value) ? $this->camelCaseKeys($value) : $value;
    }
    return $out;
  }

  /**
   * Drops never-ship keys and credential-shaped values at any depth.
   */
  private function scrub(array $data): array {
    $out = [];
    foreach ($data as $key => $value) {
      if (is_string($key) && $this->isDeniedKey($key)) {
        $this->scrubbed++;
        continue;
      }
      if (is_array($value)) {
        $out[$key] = $this->scrub($value);
        continue;
      }
      if (is_string($value) && self::isCredentialShaped($value)) {
        $this->scrubbed++;
        continue;
      }
      $out[$key] = $value;
    }
    return $out;
  }

  /**
   * Whether a key name is on the never-ship list, ignoring case and separators.
   */
  private function isDeniedKey(string $key): bool {
    $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($key));
    if (in_array($normalized, self::DENY_KEYS, TRUE)) {
      return TRUE;
    }
    foreach (self::DENY_KEY_SUBSTRINGS as $needle) {
      if (str_contains($normalized, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Whether a string value looks like a credential regardless of its key.
   *
   * Catches PEM blocks, credential-bearing URIs, JWTs, and long opaque
   * high-entropy strings. Prose is exempt by construction: the entropy test
   * only fires on a whitespace-free token of 32+ characters.
   */
  public static function isCredentialShaped(string $value): bool {
    $trimmed = trim($value);
    if ($trimmed === '') {
      return FALSE;
    }
    if (str_contains($trimmed, '-----BEGIN')) {
      return TRUE;
    }
    if (preg_match('~\b(mysql|mariadb|postgres|postgresql|mongodb|mongodb\+srv|redis|rediss|amqp|amqps|smtp|smtps|ftp|ldap)://~i', $trimmed)) {
      return TRUE;
    }
    // Any URI carrying userinfo with a password component.
    if (preg_match('~\b[a-z][a-z0-9+.\-]*://[^/\s:@]+:[^/\s@]+@~i', $trimmed)) {
      return TRUE;
    }
    if (preg_match('~^ey[A-Za-z0-9_\-]+\.ey[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$~', $trimmed)) {
      return TRUE;
    }
    // A long unbroken hex run is a hash, key or token; nothing an editor types.
    if (preg_match('~^[a-f0-9]{32,}$~i', $trimmed)) {
      return TRUE;
    }
    // Otherwise an opaque token: long, no whitespace, mixed case with digits,
    // and high entropy. The mixed-case-plus-digit requirement is what keeps
    // ordinary long editorial strings out of the net.
    if (
      preg_match('~^[A-Za-z0-9+/=_.\-]{32,}$~', $trimmed)
      && preg_match('~[a-z]~', $trimmed)
      && preg_match('~[A-Z]~', $trimmed)
      && preg_match('~[0-9]~', $trimmed)
      && self::shannonEntropy($trimmed) >= 4.0
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Shannon entropy of a string in bits per character.
   */
  private static function shannonEntropy(string $value): float {
    $length = strlen($value);
    $entropy = 0.0;
    foreach (count_chars($value, 1) as $count) {
      $p = $count / $length;
      $entropy -= $p * log($p, 2);
    }
    return $entropy;
  }

}
