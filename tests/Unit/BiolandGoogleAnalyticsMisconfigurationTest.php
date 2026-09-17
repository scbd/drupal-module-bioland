<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Exercises the real hook with an isolated, non-networked Drupal facade.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandGoogleAnalyticsMisconfigurationTest extends TestCase
{

  /**
   * @dataProvider storedValues
   */
  public function testRuntimeWarningMatchesHeadTruthiness($stored, ?string $type): void
  {
    $requirements = $this->requirements($stored, 'runtime');
    $key = 'bioland_google_analytics_misconfigured';
    $this->assertSame($stored === TRUE, isset($requirements['bioland_google_analytics_enabled']));
    if ($type === NULL) {
      $this->assertArrayNotHasKey($key, $requirements);
      return;
    }

    $this->assertArrayHasKey($key, $requirements);
    $warning = $requirements[$key];
    $this->assertSame(1, $warning['severity']);
    $this->assertSame('Stored type: ' . $type, $warning['value']);
    $this->assertStringContainsString('boolean true', $warning['description']);
    $this->assertStringContainsString('Front End > General', $warning['description']);
    $this->assertStringContainsString('will not load', $warning['description']);
    $this->assertStringNotContainsString('UNSAFE_CONFIG_VALUE', json_encode($warning));
    $this->assertStringNotContainsString('<script>', json_encode($warning));
  }

  public static function storedValues(): array
  {
    return [
      'boolean true retains enabled warning' => [TRUE, NULL],
      'boolean false' => [FALSE, NULL],
      'missing or null' => [NULL, NULL],
      'string one' => ['1', 'string'],
      'string true' => ['true', 'string'],
      'integer one' => [1, 'integer'],
      'integer zero' => [0, NULL],
      'float zero' => [0.0, NULL],
      'negative zero' => [-0.0, NULL],
      'empty string' => ['', NULL],
      // JS Boolean differs from PHP for both the string zero and empty arrays.
      'string zero is JS truthy' => ['0', 'string'],
      'empty array is JS truthy' => [[], 'array'],
      'nonempty array' => [['UNSAFE_CONFIG_VALUE'], 'array'],
      'object' => [(object) ['unsafe' => 'UNSAFE_CONFIG_VALUE'], 'object'],
      'string false is truthy' => ['false', 'string'],
      'whitespace is truthy' => [' ', 'string'],
      'negative integer' => [-1, 'integer'],
      'float one' => [1.0, 'double'],
      'fraction' => [0.5, 'double'],
      'unsafe string is never rendered' => ['<script>UNSAFE_CONFIG_VALUE</script>', 'string'],
    ];
  }

  /**
   * @dataProvider nonRuntimeValues
   */
  public function testNonRuntimeDoesNotReadAnalyticsConfiguration($stored, string $phase): void
  {
    $requirements = $this->requirements($stored, $phase);
    $this->assertArrayNotHasKey('bioland_google_analytics_enabled', $requirements);
    $this->assertArrayNotHasKey('bioland_google_analytics_misconfigured', $requirements);
  }

  public static function nonRuntimeValues(): array
  {
    $cases = [];
    foreach (['install', 'update', 'uninstall'] as $phase) {
      foreach (self::storedValues() as $name => [$value]) {
        $cases[$phase . ': ' . $name] = [$value, $phase];
      }
    }
    return $cases;
  }

  /**
   * The misconfigured-type warning must diagnose the override-free raw
   * stored value, not whatever a settings.php override presents through
   * Config::get() - in both directions, since either one masking the other
   * would report something the settings-form save/clear cannot fix.
   *
   * @dataProvider overrideContrastValues
   */
  public function testMisconfiguredWarningIgnoresConfigOverrides(
    $raw,
    $override,
    bool $misconfiguredExpected,
    bool $enabledExpected,
  ): void
  {
    $requirements = $this->requirements($raw, 'runtime', $override);
    $this->assertSame(
      $misconfiguredExpected,
      isset($requirements['bioland_google_analytics_misconfigured']),
      'The misconfigured-type warning must follow the raw stored value, never the override.'
    );
    $this->assertSame(
      $enabledExpected,
      isset($requirements['bioland_google_analytics_enabled']),
      'The enabled warning intentionally stays override-aware.'
    );
  }

  public static function overrideContrastValues(): array
  {
    return [
      // A FALSE override must not suppress the warning for a truthy raw
      // value: dmsm's raw-table read still serves the misconfigured value.
      'FALSE override cannot suppress a truthy raw value' => ['1', FALSE, TRUE, FALSE],
      // A truthy non-boolean override must not force the warning open for
      // a raw value that is already a clean boolean - it would report a
      // problem the settings form has nothing left to fix.
      'truthy non-boolean override cannot taint a clean raw value' => [TRUE, ['UNSAFE_CONFIG_VALUE'], FALSE, FALSE],
      // A TRUE override forces the enabled warning even though the raw
      // stored value is a clean FALSE - the enabled check stays
      // override-aware while the misconfigured check still reads raw.
      'TRUE override enables independently of a clean raw value' => [FALSE, TRUE, FALSE, TRUE],
    ];
  }

  private function requirements($stored, string $phase, $override = NULL): array
  {
    $process = proc_open(
      [PHP_BINARY, __DIR__ . '/../fixtures/google-analytics-requirements.php'],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes
    );
    $this->assertIsResource($process);
    $payload = ['value' => $stored, 'phase' => $phase];
    if ($override !== NULL) {
      $payload['override'] = $override;
    }
    fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $this->assertSame(0, proc_close($process), $errors);
    $this->assertSame('', $errors);
    return json_decode($output, TRUE, 512, JSON_THROW_ON_ERROR);
  }

}
