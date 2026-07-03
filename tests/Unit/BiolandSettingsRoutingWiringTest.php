<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;
use Drupal\bioland\Form\BiolandSettingsFormBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandTranslationBatchService;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Guards the wiring between bioland.routing.yml and the split settings forms.
 *
 * After BiolandSettingsForm was decomposed into an abstract base plus one
 * per-section form class, every settings route must point at a real form
 * class that extends the shared base, and each class must expose a distinct
 * form id (Drupal keys form state by form id). The historical
 * bioland.settings route must keep the original 'bioland_settings_form' id so
 * saved third-party form-alter hooks and tests still target it.
 */
class BiolandSettingsRoutingWiringTest extends TestCase {

  /**
   * The mock language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mock translation batch service.
   *
   * @var \Drupal\bioland\Service\BiolandTranslationBatchService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $translationBatchService;

  /**
   * The mock database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The mock current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The mock request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->translationBatchService = $this->createMock(BiolandTranslationBatchService::class);
    $this->database = $this->createMock(Connection::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);

    $this->languageManager->method('getDefaultLanguage')
      ->willReturn(new Language('en', 'English'));
    $this->languageManager->method('getLanguages')
      ->willReturn([
        'en' => new Language('en', 'English'),
        'fr' => new Language('fr', 'French'),
        'es' => new Language('es', 'Spanish'),
      ]);
    $this->languageManager->method('getLanguageConfigOverride')
      ->willReturn(new ImmutableConfig('system.site', []));
  }

  /**
   * Parses bioland.routing.yml into route => [property => value] arrays.
   *
   * Reuses the dependency-free flat reader from BiolandLocalTasksTest: only
   * top-level keys (route machine names) start at column 0 and end with a
   * bare colon; indented "key: value" lines (including the nested `_form`
   * under `defaults:`) are collected onto the current route. The nested-line
   * pass picks up `_form` even though it sits one level below `defaults:`,
   * because the flat reader keys every indented scalar property by name onto
   * the current top-level route.
   *
   * @return array
   *   Associative array keyed by route machine name; each value an
   *   associative array of its scalar properties (path, _form, _title, ...).
   */
  private function parseRouting(): array {
    $path = __DIR__ . '/../../bioland.routing.yml';
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $result = [];
    $currentKey = NULL;

    foreach ($lines as $line) {
      if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
        continue;
      }
      // Top-level key: starts at column 0 and ends with a colon (route name).
      if (preg_match('/^(\S[^:]*):\s*$/', $line, $matches)) {
        $currentKey = $matches[1];
        $result[$currentKey] = [];
        continue;
      }
      // Indented property: "  key: value" (e.g. `_form: '...'`).
      if ($currentKey !== NULL && preg_match('/^\s+([A-Za-z0-9_]+):\s*(.*)$/', $line, $matches)) {
        $value = trim($matches[2]);
        // Strip surrounding quotes.
        $value = trim($value, "'\"");
        // Skip container lines that have no scalar value (e.g. `defaults:`).
        if ($value === '') {
          continue;
        }
        $result[$currentKey][$matches[1]] = $value;
      }
    }

    return $result;
  }

  /**
   * Returns every bioland.settings* route that declares a _form class.
   *
   * @return array
   *   Associative array of route machine name => fully-qualified form class.
   */
  private function getSettingsFormRoutes(): array {
    $routes = [];
    foreach ($this->parseRouting() as $routeName => $props) {
      if (!str_starts_with($routeName, 'bioland.settings')) {
        continue;
      }
      if (empty($props['_form'])) {
        continue;
      }
      $routes[$routeName] = $props['_form'];
    }

    return $routes;
  }

  /**
   * Instantiates a settings form class with the shared six mocks.
   *
   * @param string $class
   *   The fully-qualified form class name.
   *
   * @return \Drupal\bioland\Form\BiolandSettingsFormBase
   *   The instantiated form.
   */
  private function instantiate(string $class): BiolandSettingsFormBase {
    return new $class(
      $this->languageManager,
      $this->entityTypeManager,
      $this->translationBatchService,
      $this->database,
      $this->currentUser,
      $this->requestStack
    );
  }

  /**
   * Every settings route's _form must resolve to a subclass of the base.
   */
  public function testEverySettingsRouteFormExtendsBase(): void {
    $routes = $this->getSettingsFormRoutes();
    $this->assertNotEmpty($routes, 'No bioland.settings* routes with a _form were parsed - bioland.routing.yml may be malformed.');

    foreach ($routes as $routeName => $class) {
      $this->assertTrue(
        class_exists($class),
        sprintf('Route "%s" points at _form class "%s", which does not exist.', $routeName, $class)
      );
      $this->assertTrue(
        is_subclass_of($class, BiolandSettingsFormBase::class),
        sprintf('Route "%s" _form class "%s" must be a subclass of BiolandSettingsFormBase.', $routeName, $class)
      );
    }
  }

  /**
   * All 11 settings forms expose distinct form ids; the base route keeps
   * the historical 'bioland_settings_form' id.
   */
  public function testSettingsFormIdsAreUniqueAndBaseRouteIsPreserved(): void {
    $routes = $this->getSettingsFormRoutes();
    $this->assertNotEmpty($routes, 'No bioland.settings* routes with a _form were parsed - bioland.routing.yml may be malformed.');

    $formIds = [];
    foreach ($routes as $routeName => $class) {
      $formIds[$routeName] = $this->instantiate($class)->getFormId();
    }

    // Every route maps to a unique form id.
    $this->assertCount(
      count($formIds),
      array_unique($formIds),
      sprintf('Settings form ids must be unique across routes; got: %s', implode(', ', $formIds))
    );

    // The historical base route keeps the original form id.
    $this->assertArrayHasKey('bioland.settings', $formIds, 'The base bioland.settings route must be defined.');
    $this->assertSame('bioland_settings_form', $formIds['bioland.settings']);
  }

}
