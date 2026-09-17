<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Composes the Bioland site configuration document served over HTTP.
 *
 * This class takes plain arrays and returns a plain array, so the allowlist,
 * the camelCase transform and the credential scrubber are all unit-testable
 * without a bootstrap. Its one optional dependency is a logger channel, used
 * only to report WHAT was dropped and WHERE — never the dropped value.
 *
 * What is included:
 * - `bioland.settings`, built ADDITIVELY from ::BIOLAND_SETTINGS_ALLOWLIST at
 *   the TOP LEVEL. Only a top-level key named in that constant can reach the
 *   wire; a key added upstream in config/schema/bioland.schema.yml does NOT
 *   ship until it is added here on purpose.
 * - `system.site` name plus its per-language overrides.
 * - `system.date` default timezone.
 *
 * NESTED SUBTREES ARE COPY-THEN-STRIP, NOT ALLOWLISTED. Below depth 1 the
 * whole subtree of an allowlisted key is copied and then ::scrub() deletes
 * deny-matched keys and credential-shaped values from it. A new SUBKEY written
 * under, say, `theme` or `help_comments` by a future admin form therefore
 * ships by default unless its name or its value trips the scrubber. A nested
 * allowlist is the correct long-term fix and is carried as debt; until then,
 * treat any new nested subkey as shipped and review it accordingly.
 *
 * SCHEMA COVERAGE. `theme` is written by DMSM and is deliberately not declared
 * in config/schema/bioland.schema.yml: it is a free-form subtree whose shape
 * DMSM owns. Every other allowlisted key is schema-declared. The "no
 * allowlisted key can hold a credential" claim is a claim about the schema-
 * backed keys; the `theme` subtree is covered by the value scrubber alone.
 *
 * What is deliberately excluded:
 * - Everything not in the top-level allowlist, by construction.
 * - Every never-ship key name from the site-config contract (R2), matched at
 *   any depth in a punctuation- and case-insensitive form, so `dataBase`,
 *   `data_base` and `DATABASE` are all caught.
 * - Every credential-SHAPED value, at any depth, whatever the key is called.
 *   A site administrator can paste a password into a benign free-text admin
 *   field such as `help_comments.body_text`; no key-name pattern catches that,
 *   so values are matched too — and because those fields are WYSIWYG prose and
 *   `field_visibility_rules` is one long JSON string, matching is per TOKEN
 *   rather than against the whole value. See ::isCredentialShaped().
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
   * Top-level keys whose schema type is `mapping` (a JSON object) but whose
   * stored value can legitimately be an empty PHP array — a fresh install's
   * `help_comments` starts as an empty mapping in
   * config/install/bioland.settings.yml. An empty array and an empty object
   * both decode to PHP `[]`, so nothing catches the wire type silently
   * flipping to `[]` unless it is cast back to an object on the way out.
   *
   * Not every schema `mapping` key needs this: most (e.g. `additional_tags`,
   * `translation`) always ship with their fixed sub-keys populated by
   * defaults, so they can never actually be empty. Only list a key here once
   * it is confirmed to reach build() empty in real config.
   */
  private const OBJECT_SHAPED_WHEN_EMPTY = ['help_comments'];

  /**
   * Never-ship key names (contract R2), normalized to lowercase alphanumerics.
   */
  private const DENY_KEYS = [
    'database', 'databasename', 'dns', 'dsn', 'drupal', 'drupalroot',
    'siteroot', 'root', 'auth', 'meta', 'panoramakey', 'smtpcredentials',
    'defaultsmtpcredentials',
  ];

  /**
   * Never-ship key substrings, matched against the same normalized form.
   *
   * `dsn` is deliberately NOT here: as a substring of the punctuation-stripped
   * key it would also match a future innocent key such as `fields_name`
   * ("fieldsname"). It is an exact key above and a word-boundary match in
   * ::isDeniedKey() instead, so `db_dsn` and `dsnUrl` are still caught.
   */
  private const DENY_KEY_SUBSTRINGS = [
    'password', 'passwd', 'secret', 'token', 'apikey', 'privatekey',
    'credential', 'hashsalt',
  ];

  /**
   * Never-ship key names matched on a word boundary rather than a substring.
   */
  private const DENY_KEY_WORDS = ['dsn'];

  /**
   * Count of values dropped by the credential scrubber on the last build.
   *
   * @var int
   */
  private int $scrubbed = 0;

  /**
   * The logger channel, or NULL when the builder runs without a container.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  private ?LoggerChannelInterface $logger = NULL;

  /**
   * Constructs the builder.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $logger_factory
   *   The logger channel factory, or NULL in a plain unit test.
   */
  public function __construct(?LoggerChannelFactoryInterface $logger_factory = NULL) {
    if ($logger_factory !== NULL) {
      $this->logger = $logger_factory->get('bioland');
    }
  }

  /**
   * Logs a drop at warning level. Key paths only: never a value.
   *
   * @param string $message
   *   The message, with an `@path`/`@key` placeholder.
   * @param array $context
   *   Placeholder replacements, each already bounded by ::logSafe().
   */
  private function warn(string $message, array $context): void {
    if ($this->logger !== NULL) {
      $this->logger->warning($message, $context);
    }
  }

  /**
   * Renders a key or key path safe and bounded for a log line.
   *
   * Authored key text is attacker-influenced: it can carry a newline or an
   * ANSI escape into the log, or flood it with kilobytes of padding. Matches
   * the head-side BL-890 treatment: JSON-escaped and capped.
   */
  private static function logSafe(string $text): string {
    $encoded = json_encode($text, JSON_UNESCAPED_SLASHES);
    return mb_strimwidth((string) $encoded, 0, 80, '...');
  }

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
    $config['systemSite'] = $this->scrub($this->camelCaseKeys($systemSite, 'systemSite'), 'systemSite');
    $config['systemDate'] = $this->scrub($this->camelCaseKeys($systemDate, 'systemDate'), 'systemDate');

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
    $out = $this->scrub($this->camelCaseKeys($included, 'biolandSettings'), 'biolandSettings');
    foreach (self::OBJECT_SHAPED_WHEN_EMPTY as $key) {
      $camelKey = self::camelCase($key);
      if (array_key_exists($camelKey, $out) && $out[$camelKey] === []) {
        $out[$camelKey] = (object) [];
      }
    }
    return $out;
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
   * Recursively camelCases array keys, resolving collisions deterministically.
   *
   * `bioland.settings` is known to carry duplicate snake/camel spellings of
   * one setting, and the unschema'd `theme` subtree is written by DMSM, so a
   * site holding both `back_ground` and `backGround` is a real state. Throwing
   * here would return HTTP 500 for that tenant's entire config document and
   * break its front end with no fallback, so the duplicate is resolved instead,
   * matching the head-side BL-890 behaviour: the spelling CLOSEST to the
   * canonical wire name wins (fewest letters cased differently, then fewest
   * separator characters, then lowest code-point order). The canonical
   * spelling scores zero on both counts, uniquely, so it always wins when
   * authored. The loser is dropped and named in a warning — key path only,
   * never the value — rather than silently merged.
   *
   * @param array $data
   *   The data to transform.
   * @param string $path
   *   The dotted key path of $data, for log messages.
   *
   * @return array
   *   The transformed data.
   */
  public function camelCaseKeys(array $data, string $path = ''): array {
    $out = [];
    $origin = [];
    foreach ($data as $key => $value) {
      $child = $path === '' ? (string) $key : $path . '.' . $key;
      if (is_int($key)) {
        $out[$key] = is_array($value) ? $this->camelCaseKeys($value, $child) : $value;
        continue;
      }
      $key = (string) $key;
      $mapped = self::camelCase($key);
      $mappedPath = $path === '' ? $mapped : $path . '.' . $mapped;
      if (isset($origin[$mapped])) {
        $kept = self::closerToCanonical($origin[$mapped], $key, $mapped);
        $dropped = $kept === $origin[$mapped] ? $key : $origin[$mapped];
        $this->warn('Bioland config document: duplicate spellings of @path; kept @kept, dropped @dropped. Value not logged.', [
          '@path' => self::logSafe($mappedPath),
          '@kept' => self::logSafe($kept),
          '@dropped' => self::logSafe($dropped),
        ]);
        if ($kept !== $key) {
          continue;
        }
      }
      $origin[$mapped] = $key;
      $out[$mapped] = is_array($value) ? $this->camelCaseKeys($value, $mappedPath) : $value;
    }
    return $out;
  }

  /**
   * Picks the authored spelling closest to the canonical wire name.
   *
   * Both spellings share the canonical key's normalized identity, so they can
   * differ only by letter casing and by inserted separator characters.
   *
   * @param string $a
   *   One authored spelling.
   * @param string $b
   *   The other authored spelling.
   * @param string $canonical
   *   The canonical (wire) spelling both map to.
   *
   * @return string
   *   The winning spelling.
   */
  private static function closerToCanonical(string $a, string $b, string $canonical): string {
    [$aCase, $aSeparators] = self::spellingDeviation($a, $canonical);
    [$bCase, $bSeparators] = self::spellingDeviation($b, $canonical);
    if ($aCase !== $bCase) {
      return $aCase < $bCase ? $a : $b;
    }
    if ($aSeparators !== $bSeparators) {
      return $aSeparators < $bSeparators ? $a : $b;
    }
    return strcmp($a, $b) <= 0 ? $a : $b;
  }

  /**
   * How far an authored spelling sits from the canonical one.
   *
   * @return array
   *   `[case mismatches, separator characters]`. The canonical spelling, and
   *   only the canonical spelling, scores `[0, 0]`.
   */
  private static function spellingDeviation(string $key, string $canonical): array {
    $alphanumeric = preg_replace('/[^A-Za-z0-9]/', '', $key);
    $mismatches = 0;
    $length = strlen($canonical);
    for ($i = 0; $i < $length; $i++) {
      if (($alphanumeric[$i] ?? NULL) !== $canonical[$i]) {
        $mismatches++;
      }
    }
    return [$mismatches, strlen($key) - strlen($alphanumeric)];
  }

  /**
   * Drops never-ship keys and credential-shaped values at any depth.
   *
   * Every drop is logged at warning level with its key path, so a scrub is
   * never silent: an administrator who pasted a password into a help field
   * needs to know the field is not being served. The value itself is never
   * logged — writing a credential into dblog would defeat the scrubber.
   *
   * @param array $data
   *   The data to scrub.
   * @param string $path
   *   The dotted key path of $data, for log messages.
   *
   * @return array
   *   The scrubbed data.
   */
  private function scrub(array $data, string $path = ''): array {
    $out = [];
    foreach ($data as $key => $value) {
      $child = $path === '' ? (string) $key : $path . '.' . $key;
      if (is_string($key) && $this->isDeniedKey($key)) {
        $this->scrubbed++;
        $this->warn('Bioland config document: dropped never-ship key @path. Value not logged.', ['@path' => self::logSafe($child)]);
        continue;
      }
      if (is_array($value)) {
        $out[$key] = $this->scrub($value, $child);
        continue;
      }
      if (is_string($value) && self::isCredentialShaped($value)) {
        $this->scrubbed++;
        $this->warn('Bioland config document: dropped credential-shaped value at @path. Value not logged.', ['@path' => self::logSafe($child)]);
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
    // Word-boundary names: split the authored key on separators and on
    // camelCase humps, so `db_dsn` and `dsnUrl` match but `fields_name` does
    // not.
    $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($words as $word) {
      if (in_array(strtolower($word), self::DENY_KEY_WORDS, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Whether a string value looks like a credential regardless of its key.
   *
   * The scenario this exists for is an administrator pasting a password into a
   * benign free-text admin field. Those fields are WYSIWYG (`help_comments.*`)
   * or one long serialized string (`field_visibility_rules`), so the credential
   * is almost never the whole value — it is a word inside markup, prose or
   * JSON. Anchoring a pattern to the whole value therefore misses exactly the
   * case it was written for, so the value is TOKENIZED and each token tested.
   *
   * Applied to every token:
   * - vendor-prefixed tokens (`ghp_`, `xox[baprs]-`, `sk-`, `AKIA`, `AIza`);
   * - JWTs;
   * - long unbroken hex runs;
   * - long opaque high-entropy tokens.
   *
   * Applied only when the value is a single bare token (markup stripped), or
   * when the surrounding text carries a credential cue word such as
   * "password", "token" or "api key":
   * - password-shaped tokens: 12+ characters mixing letters with digits, plus
   *   either mixed case or a password special character.
   *
   * Cue-gating that last rule is what keeps ordinary editorial prose whole:
   * "Article15Paragraph2" in a sentence is kept, the same shape next to the
   * word "password" is dropped. A dropped value is logged by ::scrub().
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

    // WYSIWYG values arrive wrapped in markup; the text is what is pasted.
    $text = trim(strip_tags($trimmed));
    if ($text === '') {
      $text = $trimmed;
    }
    $tokens = self::tokenize($text);
    if ($tokens === []) {
      return FALSE;
    }
    // A value that is one bare token is a value, not prose. Otherwise the
    // password-shaped rule needs a credential cue somewhere in the text.
    $aggressive = count($tokens) === 1 || preg_match('~\b(pass|passwd|password|passphrase|pwd|secret|token|credential|credentials|apikey|api[ _\-]?key|auth|login|bearer)\b~i', $text) === 1;
    foreach ($tokens as $token) {
      if (self::isCredentialToken($token, $aggressive)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Splits a value into candidate credential tokens.
   *
   * Splits on whitespace, quotes and the structural punctuation of JSON and
   * markup, then also offers each token stripped of trailing sentence
   * punctuation, so "...password Tr0ub4dor3xyz." yields the bare token too.
   *
   * @return string[]
   *   The candidate tokens.
   */
  private static function tokenize(string $text): array {
    $parts = preg_split('~[\s"\'`<>(){}\[\],;:]+~u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $tokens = [];
    foreach ($parts as $part) {
      $tokens[$part] = TRUE;
      $bare = trim($part, ".!?-_=+*&");
      if ($bare !== '') {
        $tokens[$bare] = TRUE;
      }
    }
    return array_keys($tokens);
  }

  /**
   * Whether one token looks like a credential.
   *
   * @param string $token
   *   The token to test.
   * @param bool $aggressive
   *   Whether the password-shaped rule applies (see ::isCredentialShaped()).
   *
   * @return bool
   *   TRUE when the token is credential-shaped.
   */
  private static function isCredentialToken(string $token, bool $aggressive): bool {
    $length = strlen($token);
    if ($length < 12) {
      return FALSE;
    }
    // Vendor-prefixed tokens. These are lowercase or single-case by design,
    // which is why no case-mixing rule can be relied on to catch them.
    if (preg_match('~^(gh[pousr]_[A-Za-z0-9]{16,}|xox[baprs]-[A-Za-z0-9-]{10,}|sk-[A-Za-z0-9_\-]{16,}|AKIA[0-9A-Z]{12,}|AIza[0-9A-Za-z_\-]{30,})$~', $token)) {
      return TRUE;
    }
    if (preg_match('~^ey[A-Za-z0-9_\-]+\.ey[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$~', $token)) {
      return TRUE;
    }
    // A long unbroken hex run is a hash, key or token; nothing an editor types.
    if (preg_match('~^[a-f0-9]{32,}$~i', $token)) {
      return TRUE;
    }
    // A long opaque token. `/` is excluded from the charset on purpose: with
    // it, any long URL path carrying a digit would match.
    if (
      $length >= 32
      && preg_match('~^[A-Za-z0-9+=_.\-]{32,}$~', $token)
      && preg_match('~[0-9A-Z]~', $token)
      && self::shannonEntropy($token) >= 3.0
    ) {
      return TRUE;
    }
    // The same, for base64 that does contain `/`; the mixed-case-plus-digit
    // and higher entropy bar is what keeps URL paths out of this branch.
    if (
      $length >= 32
      && preg_match('~^[A-Za-z0-9+/=_.\-]{32,}$~', $token)
      && preg_match('~[a-z]~', $token)
      && preg_match('~[A-Z]~', $token)
      && preg_match('~[0-9]~', $token)
      && self::shannonEntropy($token) >= 4.0
    ) {
      return TRUE;
    }
    // Password-shaped: letters with digits, plus mixed case or a password
    // special character. Only where the caller says the context warrants it.
    if (
      $aggressive
      && $length <= 128
      && preg_match('~[A-Za-z]~', $token)
      && preg_match('~[0-9]~', $token)
      && ((preg_match('~[a-z]~', $token) && preg_match('~[A-Z]~', $token)) || preg_match('/[!@#$%^&*()+=?~|]/', $token))
      && self::shannonEntropy($token) >= 3.0
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
