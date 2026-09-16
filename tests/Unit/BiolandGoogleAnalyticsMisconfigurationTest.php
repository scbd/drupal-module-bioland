<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Exercises the real hook with an isolated, non-networked Drupal facade.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandGoogleAnalyticsMisconfigurationTest extends TestCase {

  /**
   * @dataProvider storedValues
   */
  public function testRuntimeWarningMatchesHeadTruthiness($stored, ?string $type): void {
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

  public static function storedValues(): array {
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
  public function testNonRuntimeDoesNotReadAnalyticsConfiguration($stored, string $phase): void {
    $requirements = $this->requirements($stored, $phase);
    $this->assertArrayNotHasKey('bioland_google_analytics_enabled', $requirements);
    $this->assertArrayNotHasKey('bioland_google_analytics_misconfigured', $requirements);
  }

  public static function nonRuntimeValues(): array {
    $cases = [];
    foreach (['install', 'update', 'uninstall'] as $phase) {
      foreach (self::storedValues() as $name => [$value]) {
        $cases[$phase . ': ' . $name] = [$value, $phase];
      }
    }
    return $cases;
  }

  private function requirements($stored, string $phase): array {
    $process = proc_open(
      [PHP_BINARY, __DIR__ . '/../fixtures/google-analytics-requirements.php'],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes
    );
    $this->assertIsResource($process);
    fwrite($pipes[0], json_encode(['value' => $stored, 'phase' => $phase], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
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
