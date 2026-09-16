<?php

namespace Drupal\Tests\bioland\Unit\Controller;

use Drupal\bioland\Controller\BiolandConfigController;
use Drupal\bioland\Service\BiolandConfigDocumentBuilder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Covers the controller's wiring: cache tags, headers and section assembly.
 *
 * @coversDefaultClass \Drupal\bioland\Controller\BiolandConfigController
 */
class BiolandConfigControllerTest extends TestCase {

  /**
   * Every config object name the controller read, in read order.
   *
   * @var string[]
   */
  protected $readConfigNames = [];

  /**
   * Builds a controller over the supplied config data.
   *
   * @param array|null $bioland
   *   The bioland.settings data, or NULL for a site that never saved it.
   * @param array $overrides
   *   Per-language system.site overrides, keyed by langcode.
   *
   * @return \Drupal\bioland\Controller\BiolandConfigController
   *   The controller.
   */
  protected function controller($bioland, array $overrides = [], string $siteName = 'Example Site') {
    $configs = [
      'bioland.settings' => new ImmutableConfig('bioland.settings', $bioland ?? []),
      'system.site' => new ImmutableConfig('system.site', ['name' => $siteName]),
      'system.date' => new ImmutableConfig('system.date', ['timezone' => ['default' => 'America/Montreal']]),
    ];
    $this->readConfigNames = [];
    $recorder = &$this->readConfigNames;
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturnCallback(static function ($name) use ($configs, &$recorder) {
      $recorder[] = $name;
      return $configs[$name] ?? new ImmutableConfig($name, []);
    });

    $languages = [];
    foreach (array_keys($overrides) as $langcode) {
      $languages[$langcode] = $langcode;
    }
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getLanguages')->willReturn($languages);
    $languageManager->method('getLanguageConfigOverride')->willReturnCallback(static function ($langcode) use ($overrides) {
      return new ImmutableConfig('system.site', $overrides[$langcode] ?? []);
    });

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1767225600);

    return new BiolandConfigController($factory, $languageManager, $time, new BiolandConfigDocumentBuilder(), 'sites/example');
  }

  /**
   * The response is cache-tagged so a config save invalidates it.
   */
  public function testResponseCarriesConfigCacheTags() {
    $response = $this->controller(['region' => 'r'])->document();
    $tags = $response->getCacheableMetadata()->getCacheTags();

    $this->assertContains('config:bioland.settings', $tags);
    $this->assertContains('config:system.site', $tags);
    $this->assertContains('config:system.date', $tags);
    $this->assertContains('config:configurable_language_list', $tags);
  }

  /**
   * The response is marked uncacheable at the edge.
   */
  public function testResponseIsNotEdgeCacheable() {
    $response = $this->controller(['region' => 'r'])->document();
    $this->assertSame('private, no-store', $response->headers->get('Cache-Control'));
  }

  /**
   * The site code is derived from the site directory, not from the request.
   */
  public function testSiteCodeAndVersionAndTimestamp() {
    $document = $this->controller(['region' => 'r'])->document()->getPayload();

    $this->assertSame('example', $document['siteCode']);
    $this->assertSame(1, $document['version']);
    $this->assertSame('2026-01-01T00:00:00.000Z', $document['generated']);
  }

  /**
   * Per-language site names come through the language override system.
   */
  public function testPerLanguageSiteNameOverrides() {
    $document = $this->controller(['region' => 'r'], [
      'en' => [],
      'fr' => ['name' => 'Site Exemple'],
      'es' => ['name' => 'Sitio Ejemplo'],
    ])->document()->getPayload();

    $site = $document['config']['systemSite'];
    $this->assertSame('Example Site', $site['name']);
    $this->assertSame(['fr' => ['name' => 'Site Exemple'], 'es' => ['name' => 'Sitio Ejemplo']], $site['translations']);
    $this->assertSame('America/Montreal', $document['config']['systemDate']['timezone']['default']);
  }

  /**
   * A site with no saved bioland.settings gets a document, not a 500.
   */
  public function testMissingBiolandSettingsStillServes() {
    $document = $this->controller(NULL)->document()->getPayload();

    $this->assertArrayNotHasKey('biolandSettings', $document['config']);
    $this->assertSame('Example Site', $document['config']['systemSite']['name']);
  }

  /**
   * Credential-shaped values never survive the controller either.
   */
  public function testCredentialNegativeControlThroughTheController() {
    $control = require __DIR__ . '/../../fixtures/config-contract/credential-negative-control.php';
    $body = $this->controller($control['settings'])->document()->getContent();

    foreach ($control['leaks'] as $leak) {
      $this->assertStringNotContainsString($leak, $body, "Credential-shaped value '$leak' leaked through the controller.");
    }
    $this->assertStringContainsString('G-FAKE123', $body);
  }

  /**
   * Every config object the response depends on is declared as a cache tag.
   *
   * This is what makes Drupal-side invalidation automatic: saving a config
   * object invalidates `config:<name>`, so a response that tags every object
   * it read cannot be served stale. A future read of an untagged config object
   * fails here rather than silently pinning the response to an old value.
   */
  public function testEveryConfigObjectReadIsDeclaredAsACacheTag() {
    $response = $this->controller(['region' => 'r'], ['fr' => ['name' => 'Site Exemple']]);
    $tags = $response->document()->getCacheableMetadata()->getCacheTags();

    $this->assertNotEmpty($this->readConfigNames);
    foreach (array_unique($this->readConfigNames) as $name) {
      $this->assertContains('config:' . $name, $tags, "Config object $name is read but not cache-tagged.");
    }
  }

  /**
   * After an invalidating save, the rebuilt response carries the new value.
   *
   * Cache tags only guarantee the cached copy is dropped; this proves the
   * rebuild is a pure function of current config, so the next request after a
   * `bioland.settings` or `system.site` save serves the saved value.
   */
  public function testRebuiltResponseReflectsTheSavedConfig() {
    $before = $this->controller(['region' => 'before'])->document()->getPayload();
    $after = $this->controller(['region' => 'after'], [], 'Renamed Site')->document()->getPayload();

    $this->assertSame('before', $before['config']['biolandSettings']['region']);
    $this->assertSame('after', $after['config']['biolandSettings']['region']);
    $this->assertSame('Example Site', $before['config']['systemSite']['name']);
    $this->assertSame('Renamed Site', $after['config']['systemSite']['name']);
  }

}
