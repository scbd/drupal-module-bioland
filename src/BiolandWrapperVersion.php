<?php

namespace Drupal\bioland;

/**
 * Parses and evaluates the drupal-docker-wrapper image version (BL-1189).
 *
 * BL-917 enables toast_image_editor, llms_txt, ckeditor5_fullscreen and
 * ckeditor5_icons, but they only ship in the drupal-docker-wrapper image
 * from version 11.4.7-v7 onward. The wrapper exposes its own version via the
 * DRUPAL_DOCKER_WRAPPER_VERSION environment variable (X.Y.Z-vN: the wrapper's
 * own core version, then an image revision number), and this class is the
 * single place that parses and compares it. Deliberately a plain PHP class
 * with no Drupal dependency so it can run under PHPUnit without a kernel
 * bootstrap; bioland_requirements() reads getenv(self::ENV_VAR) and passes
 * the result to evaluate().
 */
class BiolandWrapperVersion {

  /**
   * The minimum wrapper version the four BL-917 modules require.
   */
  const MINIMUM = '11.4.7-v7';

  /**
   * The environment variable the wrapper image sets.
   */
  const ENV_VAR = 'DRUPAL_DOCKER_WRAPPER_VERSION';

  /**
   * Severity: the reported version meets the minimum.
   */
  const SEVERITY_OK = 'ok';

  /**
   * Severity: the reported version is below the minimum, or unverifiable.
   */
  const SEVERITY_WARNING = 'warning';

  /**
   * Parses a wrapper version string of the form X.Y.Z-vN.
   *
   * @param string|null $version
   *   The raw version string, typically read via getenv(self::ENV_VAR).
   *
   * @return array{core: array{0:int,1:int,2:int}, revision: int}|null
   *   The parsed core version and image revision, or NULL when $version is
   *   NULL, empty, or does not match the expected shape.
   */
  public static function parse(?string $version): ?array {
    if ($version === NULL) {
      return NULL;
    }

    $version = trim($version);
    if ($version === '' || !preg_match('/^(\d+)\.(\d+)\.(\d+)-v(\d+)$/', $version, $matches)) {
      return NULL;
    }

    return [
      'core' => [(int) $matches[1], (int) $matches[2], (int) $matches[3]],
      'revision' => (int) $matches[4],
    ];
  }

  /**
   * Compares two well-formed wrapper version strings.
   *
   * Core version (major.minor.patch) is compared first, then the image
   * revision, so a higher core version always outranks a lower one
   * regardless of revision, and revision only breaks a tie on equal core.
   *
   * @param string $a
   *   The first version string.
   * @param string $b
   *   The second version string.
   *
   * @return int
   *   -1 if $a < $b, 0 if equal, 1 if $a > $b.
   *
   * @throws \InvalidArgumentException
   *   If either argument does not parse as a valid wrapper version.
   */
  public static function compare(string $a, string $b): int {
    $parsedA = self::parse($a);
    $parsedB = self::parse($b);

    if ($parsedA === NULL) {
      throw new \InvalidArgumentException(sprintf('Malformed wrapper version: "%s".', $a));
    }
    if ($parsedB === NULL) {
      throw new \InvalidArgumentException(sprintf('Malformed wrapper version: "%s".', $b));
    }

    for ($i = 0; $i < 3; $i++) {
      if ($parsedA['core'][$i] !== $parsedB['core'][$i]) {
        return $parsedA['core'][$i] <=> $parsedB['core'][$i];
      }
    }

    return $parsedA['revision'] <=> $parsedB['revision'];
  }

  /**
   * Evaluates a raw wrapper version against the minimum for the status report.
   *
   * @param string|null $version
   *   The raw value of getenv(self::ENV_VAR), or NULL when unset.
   *
   * @return array{severity: string, value: string, message: string}
   *   'severity' is one of the SEVERITY_* constants; 'value' is a
   *   human-readable rendering of $version for the status report's value
   *   column; 'message' explains the finding, naming the four BL-917 modules
   *   whenever readiness for them cannot be confirmed.
   */
  public static function evaluate(?string $version): array {
    $parsed = self::parse($version);

    if ($parsed === NULL) {
      return [
        'severity' => self::SEVERITY_WARNING,
        'value' => ($version === NULL || trim($version) === '') ? 'Not set' : $version,
        'message' => sprintf(
          'Wrapper image version is not set or could not be parsed (expected %s=X.Y.Z-vN); cannot verify whether toast_image_editor, llms_txt, ckeditor5_fullscreen and ckeditor5_icons are supported.',
          self::ENV_VAR
        ),
      ];
    }

    if (self::compare($version, self::MINIMUM) >= 0) {
      return [
        'severity' => self::SEVERITY_OK,
        'value' => $version,
        'message' => sprintf('Wrapper image version %s meets the minimum required %s.', $version, self::MINIMUM),
      ];
    }

    return [
      'severity' => self::SEVERITY_WARNING,
      'value' => $version,
      'message' => sprintf(
        'Wrapper image version %s is older than the required %s; toast_image_editor, llms_txt, ckeditor5_fullscreen and ckeditor5_icons may not be available.',
        $version,
        self::MINIMUM
      ),
    ];
  }

}
