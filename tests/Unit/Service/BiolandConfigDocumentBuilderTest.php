<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandConfigDocumentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Covers the config document composition, transform and leak defences.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandConfigDocumentBuilder
 */
class BiolandConfigDocumentBuilderTest extends TestCase {

  /**
   * The builder under test.
   *
   * @var \Drupal\bioland\Service\BiolandConfigDocumentBuilder
   */
  protected $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = new BiolandConfigDocumentBuilder();
  }

  /**
   * Loads a JSON fixture.
   *
   * @param string $name
   *   The fixture basename.
   *
   * @return array
   *   The decoded fixture.
   */
  protected function fixture(string $name): array {
    $path = __DIR__ . '/../../fixtures/config-contract/' . $name;
    $this->assertFileExists($path);
    $decoded = json_decode(file_get_contents($path), TRUE);
    $this->assertIsArray($decoded, "Fixture $name must decode to an array.");
    return $decoded;
  }

  /**
   * The built document reproduces p01-01's contract example exactly.
   */
  public function testMatchesContractExampleDocument() {
    $example = $this->fixture('drupal-config-document.example.json');
    $source = $this->fixture('bioland-settings.source.json');

    $document = $this->builder->build(
      $source,
      [
        'name' => 'Example Site',
        'translations' => [
          'fr' => ['name' => 'Site Exemple'],
          'es' => ['name' => 'Sitio Ejemplo'],
        ],
      ],
      ['timezone' => ['default' => 'UTC']],
      'example',
      '2026-01-01T00:00:00.000Z'
    );

    $this->assertSame($example, $document, 'The served document must match the contract example document.');
  }

  /**
   * The envelope carries an integer version, a timestamp and a site code.
   */
  public function testEnvelopeShape() {
    $document = $this->builder->build(['region' => 'r'], ['name' => 'N', 'translations' => []], ['timezone' => ['default' => 'UTC']], 'abc', '2026-01-01T00:00:00.000Z');

    $this->assertSame(['version', 'generated', 'siteCode', 'config'], array_keys($document));
    $this->assertIsInt($document['version']);
    $this->assertSame(1, $document['version']);
    $this->assertSame('abc', $document['siteCode']);
    $this->assertSame('2026-01-01T00:00:00.000Z', $document['generated']);
  }

  /**
   * Every allowlisted key present in config is emitted, camelCased.
   */
  public function testAllowlistCompositionIncludesEveryAllowlistedKey() {
    $source = [];
    foreach (BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST as $key) {
      $source[$key] = 'value-' . $key;
    }
    $projected = $this->builder->projectBiolandSettings($source);

    foreach (BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST as $key) {
      $wire = BiolandConfigDocumentBuilder::camelCase($key);
      $this->assertArrayHasKey($wire, $projected, "Allowlisted key $key must ship as $wire.");
    }
    $this->assertCount(count(BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST), $projected);
  }

  /**
   * A key not on the allowlist never ships, however innocuous it looks.
   */
  public function testKeysOutsideTheAllowlistAreNeverEmitted() {
    $projected = $this->builder->projectBiolandSettings([
      'region' => 'r',
      'a_brand_new_upstream_key' => 'surprise',
      'panorama_key' => 'x',
      'data_base' => ['host' => 'h'],
      'dns' => ['a' => 'b'],
      'drupal' => ['a' => 'b'],
      'auth' => ['a' => 'b'],
      'meta' => ['createdBy' => ['email' => 'a@b.invalid']],
      'default_smtp_credentials' => ['user' => 'u'],
    ]);

    $this->assertSame(['region'], array_keys($projected));
  }

  /**
   * The allowlist names no key that could hold a credential.
   */
  public function testAllowlistNamesNoCredentialShapedKey() {
    foreach (BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST as $key) {
      $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($key));
      foreach (['password', 'passwd', 'secret', 'token', 'apikey', 'privatekey', 'credential', 'database', 'smtp', 'dsn'] as $needle) {
        $this->assertStringNotContainsString($needle, $normalized, "Allowlisted key $key looks credential-bearing.");
      }
    }
  }

  /**
   * camelCase leaves non-snake keys, including langcodes, untouched.
   *
   * @dataProvider camelCaseProvider
   */
  public function testCamelCase($input, $expected) {
    $this->assertSame($expected, BiolandConfigDocumentBuilder::camelCase($input));
  }

  /**
   * Data provider for ::testCamelCase.
   *
   * @return array
   *   Input/expected pairs.
   */
  public static function camelCaseProvider() {
    return [
      ['google_analytics_ids', 'googleAnalyticsIds'],
      ['is_biosafety_land', 'isBiosafetyLand'],
      ['url_content_types', 'urlContentTypes'],
      ['region', 'region'],
      ['fr', 'fr'],
      ['zh-hans', 'zh-hans'],
      ['backGround', 'backGround'],
      ['i18n', 'i18n'],
      ['max_lang_before_wrap', 'maxLangBeforeWrap'],
    ];
  }

  /**
   * The transform fails loudly when two keys would collide on the wire.
   */
  public function testCamelCaseCollisionThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('camelCase collision');
    $this->builder->camelCaseKeys(['main_menu_lock' => 1, 'mainMenuLock' => 2]);
  }

  /**
   * The contract example's key set contains no snake_case top-level key.
   */
  public function testNoSnakeCaseKeysSurvive() {
    $source = $this->fixture('bioland-settings.source.json');
    foreach (array_keys($this->builder->projectBiolandSettings($source)) as $key) {
      $this->assertStringNotContainsString('_', $key, "Wire key $key must be camelCase.");
    }
  }

  /**
   * A site that never saved bioland.settings gets a valid document.
   */
  public function testMissingBiolandSettingsIsOmittedNotFatal() {
    foreach ([NULL, []] as $empty) {
      $document = $this->builder->build($empty, ['name' => 'N', 'translations' => []], ['timezone' => ['default' => 'UTC']], 'abc', '2026-01-01T00:00:00.000Z');
      $this->assertArrayNotHasKey('biolandSettings', $document['config']);
      $this->assertSame('N', $document['config']['systemSite']['name']);
      $this->assertSame('UTC', $document['config']['systemDate']['timezone']['default']);
      $this->assertSame(1, $document['version']);
    }
  }

  /**
   * system.site per-language overrides and system.date timezone are present.
   */
  public function testSystemSiteAndSystemDateSections() {
    $document = $this->builder->build(['region' => 'r'], [
      'name' => 'Example Site',
      'translations' => ['fr' => ['name' => 'Site Exemple'], 'zh-hans' => ['name' => 'Zhongwen']],
    ], ['timezone' => ['default' => 'America/Montreal']], 'abc', '2026-01-01T00:00:00.000Z');

    $this->assertSame('Example Site', $document['config']['systemSite']['name']);
    $this->assertSame('Site Exemple', $document['config']['systemSite']['translations']['fr']['name']);
    $this->assertSame('Zhongwen', $document['config']['systemSite']['translations']['zh-hans']['name']);
    $this->assertSame('America/Montreal', $document['config']['systemDate']['timezone']['default']);
  }

  /**
   * Negative control: no fake credential value reaches the serialized body.
   *
   * The fixture pastes obviously-fake credentials into benign allowlisted
   * admin fields such as help_comments.*, which no key-name pattern catches.
   */
  public function testCredentialShapedValuesNeverReachTheWire() {
    $control = require __DIR__ . '/../../fixtures/config-contract/credential-negative-control.php';
    $source = $control['settings'];
    $document = $this->builder->build($source, ['name' => 'N', 'translations' => []], ['timezone' => ['default' => 'UTC']], 'abc', '2026-01-01T00:00:00.000Z');
    $serialized = json_encode($document);

    // Guard the guard: the expanded fixture must genuinely carry credential
    // shapes, or the assertions below would pass vacuously.
    $this->assertTrue(BiolandConfigDocumentBuilder::isCredentialShaped($source['help_comments']['body_text']));
    $this->assertTrue(BiolandConfigDocumentBuilder::isCredentialShaped($source['help_comments']['summary_text']));

    $leaks = $control['leaks'];
    $this->assertNotEmpty($leaks);
    foreach ($leaks as $leak) {
      $this->assertStringNotContainsString($leak, $serialized, "Credential-shaped value '$leak' leaked into the response.");
    }

    // The scrubber must not simply blank the document.
    $settings = $document['config']['biolandSettings'];
    $this->assertSame('G-FAKE123', $settings['googleAnalyticsIds']);
    $this->assertSame('example-region', $settings['region']);
    $this->assertTrue($settings['config']['promoteAndStickyPublic']);
    $this->assertSame('full_html', $settings['helpComments']['bodyTextFormat']);
    $this->assertSame('#101010', $settings['theme']['color']['primary']);
    $this->assertStringContainsString('factice', $settings['helpComments']['bodyTextTranslations']['fr']);
    $this->assertGreaterThan(0, $this->builder->getScrubbedCount());
  }

  /**
   * Value-shaped detection fires on credentials and spares ordinary content.
   *
   * @dataProvider credentialShapeProvider
   */
  public function testIsCredentialShaped($value, $expected) {
    $this->assertSame($expected, BiolandConfigDocumentBuilder::isCredentialShaped($value));
  }

  /**
   * Data provider for ::testIsCredentialShaped.
   *
   * @return array
   *   Value/expected pairs.
   */
  public static function credentialShapeProvider() {
    return [
      // The PEM and JWT samples are assembled from fragments rather than
      // written out: a complete credential shape committed anywhere in the
      // tree trips the secret scanners, which cannot tell a deliberate
      // synthetic test sample from a real leak.
      'pem block' => ['-----BEGIN OPENSSH ' . 'PRIVATE KEY-----FAKE', TRUE],
      'mysql uri' => ['mysql://u:p@host.invalid/db', TRUE],
      'smtp uri' => ['smtp://u:p@host.invalid:587', TRUE],
      'https with userinfo' => ['https://user:hunter2@host.invalid/x', TRUE],
      'jwt' => ['ey' . 'JhbGciOiJIUzI1NiJ9' . '.' . 'ey' . 'JzdWIiOiIxIn0' . '.c2lnbmF0dXJlRkFLRQ', TRUE],
      'high entropy blob' => ['Zm9vYmFyRkFLRTEyMzQ1Njc4OTBhYmNkZWZnaGlqa2xtbm9w', TRUE],
      'empty' => ['', FALSE],
      'hex colour' => ['#101010', FALSE],
      'ga id' => ['G-EXAMPLE0', FALSE],
      'iana timezone' => ['America/Argentina/Buenos_Aires', FALSE],
      'prose' => ['Please upload the images for the hero banner here, one per language.', FALSE],
      'plain https url' => ['https://www.example.invalid/some/rather/long/path/segment', FALSE],
      'minified json rules' => ['[{"bundle":"page","field":"field_url","visible":true,"weight":10}]', FALSE],
    ];
  }

}
