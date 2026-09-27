<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests _bioland_wrapper_requirements() severity per BL-1189's status table.
 *
 * bioland_requirements() itself calls the \Drupal facade with no kernel
 * bootstrap available (see phpunit.xml.dist), but this helper does not: it
 * only reads getenv(), calls the stubbed global t(), and defers all version
 * logic to BiolandWrapperVersion, which is itself a pure PHP class. That
 * makes it safe to require the include directly and call the function, per
 * the established pattern in BiolandDmsmBaseUrlSeedTest.
 *
 * @group bioland
 */
class BiolandWrapperVersionRequirementsTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../includes/bioland.install.helpers.inc';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    putenv('DRUPAL_DOCKER_WRAPPER_VERSION');
  }

  /**
   * Absent for the 'install' phase: the env var describes the running
   * container, not something an install phase can meaningfully assess.
   */
  public function testAbsentDuringInstallPhase(): void {
    putenv('DRUPAL_DOCKER_WRAPPER_VERSION=11.4.7-v7');
    $this->assertSame([], _bioland_wrapper_requirements('install'));
  }

  /**
   * @dataProvider severityProvider
   */
  public function testSeverityPerRow(?string $envValue, int $expectedSeverity, string $phase): void {
    if ($envValue === NULL) {
      putenv('DRUPAL_DOCKER_WRAPPER_VERSION');
    }
    else {
      putenv('DRUPAL_DOCKER_WRAPPER_VERSION=' . $envValue);
    }

    $requirements = _bioland_wrapper_requirements($phase);
    $this->assertArrayHasKey('bioland_docker_wrapper_version', $requirements);
    $this->assertSame($expectedSeverity, $requirements['bioland_docker_wrapper_version']['severity']);
  }

  /**
   * @return array<string, array{0: string|null, 1: int, 2: string}>
   */
  public static function severityProvider(): array {
    return [
      'equal to minimum, runtime' => ['11.4.7-v7', REQUIREMENT_OK, 'runtime'],
      'equal to minimum, update' => ['11.4.7-v7', REQUIREMENT_OK, 'update'],
      'higher revision' => ['11.4.7-v8', REQUIREMENT_OK, 'runtime'],
      'higher patch' => ['11.4.8-v1', REQUIREMENT_OK, 'runtime'],
      'lower revision' => ['11.4.7-v6', REQUIREMENT_WARNING, 'runtime'],
      'lower core' => ['11.4.5-v2', REQUIREMENT_WARNING, 'runtime'],
      'malformed' => ['not-a-version', REQUIREMENT_WARNING, 'runtime'],
      'unset' => [NULL, REQUIREMENT_WARNING, 'runtime'],
    ];
  }

  /**
   * The four BL-917 modules are named for an operator to act on, never
   * silently dropped from the warning message.
   */
  public function testWarningNamesTheFourGuardedModules(): void {
    putenv('DRUPAL_DOCKER_WRAPPER_VERSION=11.4.5-v2');
    $requirements = _bioland_wrapper_requirements('runtime');
    $description = (string) $requirements['bioland_docker_wrapper_version']['description'];

    $this->assertStringContainsString('toast_image_editor', $description);
    $this->assertStringContainsString('llms_txt', $description);
    $this->assertStringContainsString('ckeditor5_fullscreen', $description);
    $this->assertStringContainsString('ckeditor5_icons', $description);
  }

}
