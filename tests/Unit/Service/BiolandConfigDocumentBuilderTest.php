<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandConfigDocumentBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
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
    $examplePath = __DIR__ . '/../../fixtures/config-contract/drupal-config-document.example.json';
    $this->assertFileExists($examplePath);
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

    // json_decode(..., FALSE) preserves the {} vs [] distinction that
    // decoding to an array (as fixture() does for other tests) would erase -
    // both an empty JSON object and an empty JSON array decode to the same
    // PHP []. Re-encoding both sides through the identical json_encode() call
    // CacheableJsonResponse::getContent() uses catches a served empty object
    // silently regressing to an empty array (or vice versa), which an
    // array-based assertSame() cannot.
    $expectedCanonical = json_encode(json_decode(file_get_contents($examplePath), FALSE));
    $this->assertSame($expectedCanonical, json_encode($document), 'The served document must match the contract example document, including {} vs [] wire types.');
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
      ['google_analytics_enabled', 'googleAnalyticsEnabled'],
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
   * A duplicate spelling resolves toward the canonical one, never a 500.
   *
   * A site holding both `back_ground` and `backGround` under the unschema'd
   * `theme` subtree is a real state — collapsing those duplicates is what the
   * head-side BL-890 work exists for. Throwing would return HTTP 500 for that
   * tenant's whole config document, so the collision resolves instead, and
   * resolves the same way every time whatever order Drupal serialized the row
   * in.
   *
   * @dataProvider duplicateSpellingProvider
   */
  public function testDuplicateSpellingsResolveTowardTheCanonicalKey(array $input, string $wire, $expected) {
    $out = $this->builder->camelCaseKeys($input);

    $this->assertSame([$wire], array_keys($out));
    $this->assertSame($expected, $out[$wire]);
  }

  /**
   * Data provider for ::testDuplicateSpellingsResolveTowardTheCanonicalKey.
   *
   * @return array
   *   Input, wire key, and expected surviving value. The canonical spelling
   *   wins whatever order Drupal serialized the duplicates in.
   */
  public static function duplicateSpellingProvider() {
    return [
      'canonical first' => [['backGround' => 'canonical', 'back_ground' => 'snake'], 'backGround', 'canonical'],
      'canonical last' => [['back_ground' => 'snake', 'backGround' => 'canonical'], 'backGround', 'canonical'],
      'another pair' => [['main_menu_lock' => 'snake', 'mainMenuLock' => 'canonical'], 'mainMenuLock', 'canonical'],
    ];
  }

  /**
   * A spelling that maps to no canonical name is passed through, not merged.
   *
   * ::camelCase() only rewrites strict lowercase snake_case, so `BACK_GROUND`
   * and `-back-ground-` are returned unchanged and collide with nothing. They
   * therefore ship as their own keys rather than displacing the canonical one.
   * That is deliberate — normalizing case and separators away would also
   * rewrite langcode-shaped map keys such as `zh-hans` — and it is pinned here
   * so it stays a decision rather than an accident.
   */
  public function testOddSpellingsDoNotDisplaceTheCanonicalKey() {
    $out = $this->builder->camelCaseKeys(['BACK_GROUND' => 'shout', 'backGround' => 'canonical', '-back-ground-' => 'punct']);

    $this->assertSame('canonical', $out['backGround']);
    $this->assertSame(['BACK_GROUND', 'backGround', '-back-ground-'], array_keys($out));
  }

  /**
   * A duplicate spelling inside a real build does not break the document.
   */
  public function testDuplicateSpellingDoesNotBreakTheDocument() {
    $document = $this->builder->build(
      ['region' => 'r', 'theme' => ['back_ground' => ['primary' => '#fafafa'], 'backGround' => ['primary' => '#000000']]],
      ['name' => 'N', 'translations' => []],
      ['timezone' => ['default' => 'UTC']],
      'abc',
      '2026-01-01T00:00:00.000Z'
    );

    $this->assertSame('#000000', $document['config']['biolandSettings']['theme']['backGround']['primary']);
    $this->assertSame('r', $document['config']['biolandSettings']['region']);
  }

  /**
   * The dropped duplicate is logged by key path, and never by value.
   */
  public function testDuplicateSpellingIsLoggedWithoutTheValue() {
    $logged = [];
    $builder = new BiolandConfigDocumentBuilder($this->loggerFactory($logged));
    $builder->build(
      ['theme' => ['back_ground' => 'SECRETVALUE1', 'backGround' => 'SECRETVALUE2']],
      ['name' => 'N', 'translations' => []],
      ['timezone' => ['default' => 'UTC']],
      'abc',
      '2026-01-01T00:00:00.000Z'
    );

    $this->assertNotEmpty($logged);
    $rendered = json_encode($logged);
    $this->assertStringContainsString('biolandSettings.theme.backGround', $rendered);
    $this->assertStringNotContainsString('SECRETVALUE1', $rendered);
    $this->assertStringNotContainsString('SECRETVALUE2', $rendered);
  }

  /**
   * A scrubbed value is reported by key path at warning level, never silently.
   */
  public function testScrubbedValuesAreLoggedByKeyPathOnly() {
    $logged = [];
    $builder = new BiolandConfigDocumentBuilder($this->loggerFactory($logged));
    $builder->build(
      ['help_comments' => ['body_text' => 'Summer' . '2024' . '!bioland'], 'panorama_key' => 'x'],
      ['name' => 'N', 'translations' => []],
      ['timezone' => ['default' => 'UTC']],
      'abc',
      '2026-01-01T00:00:00.000Z'
    );

    $rendered = json_encode($logged);
    $this->assertStringContainsString('biolandSettings.helpComments.bodyText', $rendered);
    $this->assertStringNotContainsString('Summer' . '2024', $rendered);
    $this->assertSame(1, $builder->getScrubbedCount());
  }

  /**
   * A logger factory whose channel appends every warning to $logged.
   *
   * @param array $logged
   *   Collector, by reference.
   *
   * @return \Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   The factory.
   */
  protected function loggerFactory(array &$logged) {
    $channel = $this->createMock(LoggerChannelInterface::class);
    $channel->method('warning')->willReturnCallback(static function ($message, array $context = []) use (&$logged) {
      $logged[] = [$message, $context];
    });
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($channel);
    return $factory;
  }

  /**
   * A never-ship key name is matched on a word boundary, not as a substring.
   *
   * `dsn` as a naive substring of the punctuation-stripped key would also
   * match a future innocent key such as `fields_name` ("fieldsname"). No
   * current schema key collides, so this is a latent trap rather than a live
   * bug — which is exactly when it is cheap to close.
   */
  public function testDsnIsMatchedOnAWordBoundary() {
    $projected = $this->builder->projectBiolandSettings([
      'config' => [
        'db_dsn' => 'kept?',
        'dsnUrl' => 'kept?',
        'dsn' => 'kept?',
        'fields_name' => 'field label',
        'fieldsname' => 'field label',
      ],
    ]);

    $this->assertSame(['fieldsName', 'fieldsname'], array_keys($projected['config']));
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
    $this->assertSame(
      $leaks,
      array_merge($control['layers']['allowlist'], $control['layers']['deny_key'], $control['layers']['value_scrubber']),
      'Every leak string must be attributed to the defence layer that removes it.'
    );
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
   * Each leak string is removed by the layer the fixture attributes it to.
   *
   * Of the original 13 leak strings, five were removed by the allowlist or by
   * a key name before the value scrubber ever ran, so asserting their absence
   * said nothing about the scrubber. This pins each string to one layer and
   * proves the attribution rather than assuming it.
   */
  public function testEachLeakIsRemovedByTheLayerItIsAttributedTo() {
    $control = require __DIR__ . '/../../fixtures/config-contract/credential-negative-control.php';
    $source = $control['settings'];
    $layers = $control['layers'];

    // Layer 1: the top-level key is simply not on the allowlist.
    foreach ($layers['allowlist'] as $leak) {
      $top = $this->topLevelKeyHolding($source, $leak);
      $this->assertNotNull($top, "Fixture no longer carries '$leak'.");
      $this->assertNotContains($top, BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST, "'$leak' is attributed to the allowlist layer but its top-level key $top IS allowlisted.");
    }

    // Layer 2: the key name is denied while nested inside an ALLOWLISTED key,
    // and the value itself is not credential-shaped, so only the key-name
    // layer can be what removes it.
    foreach ($layers['deny_key'] as $leak) {
      $top = $this->topLevelKeyHolding($source, $leak);
      $this->assertContains($top, BiolandConfigDocumentBuilder::BIOLAND_SETTINGS_ALLOWLIST, "'$leak' is attributed to the key-name layer but its top-level key $top is not allowlisted.");
      foreach ($this->valuesContaining($source, $leak) as $value) {
        $this->assertFalse(BiolandConfigDocumentBuilder::isCredentialShaped($value), "'$leak' is attributed to the key-name layer but its value is credential-shaped.");
      }
    }

    // Layer 3: the value scrubber. The holding key is allowlisted and benign,
    // and the value is credential-shaped on its own.
    foreach ($layers['value_scrubber'] as $leak) {
      $values = $this->valuesContaining($source, $leak);
      $this->assertNotEmpty($values, "Fixture no longer carries '$leak'.");
      $caught = FALSE;
      foreach ($values as $value) {
        $caught = $caught || BiolandConfigDocumentBuilder::isCredentialShaped($value);
      }
      $this->assertTrue($caught, "'$leak' is attributed to the value scrubber but no value holding it is credential-shaped.");
    }
  }

  /**
   * The five shapes a reviewer measured escaping the anchored detector.
   *
   * Each is asserted against the value scrubber directly, so it cannot pass
   * because some other layer happened to remove its container.
   */
  public function testTheMeasuredEscapingShapesAreCaught() {
    $control = require __DIR__ . '/../../fixtures/config-contract/credential-negative-control.php';

    $this->assertCount(5, $control['shapes']);
    foreach ($control['shapes'] as $label => $value) {
      $this->assertTrue(BiolandConfigDocumentBuilder::isCredentialShaped($value), "A $label must be caught by the value scrubber.");
    }
  }

  /**
   * The top-level fixture key whose subtree contains $needle, or NULL.
   */
  protected function topLevelKeyHolding(array $source, string $needle) {
    foreach ($source as $key => $value) {
      if (str_contains(json_encode($value), $needle)) {
        return (string) $key;
      }
    }
    return NULL;
  }

  /**
   * Every string value in $data containing $needle, at any depth.
   *
   * @return string[]
   *   The matching values.
   */
  protected function valuesContaining(array $data, string $needle): array {
    $found = [];
    array_walk_recursive($data, static function ($value) use ($needle, &$found) {
      if (is_string($value) && str_contains($value, $needle)) {
        $found[] = $value;
      }
    });
    return $found;
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
      // The five shapes the anchored detector let through. Anchoring is why
      // the prose case escaped every branch; single-case is why the vendor
      // tokens did.
      'password-shaped value' => ['Summer' . '2024' . '!bioland', TRUE],
      'credential inside prose' => ['Login to the mailer with user admin and password ' . 'Tr0ub' . '4dor' . '3xyz', TRUE],
      'slack-shaped token' => ['xox' . 'b-2222222222-3333333333-abcdefghijklmnopqrstuvwx', TRUE],
      'github-shaped pat' => ['gh' . 'p_16c7e42fa9b8c7d6e5f4a3b2c1d0e9f8a7b6c5d4', TRUE],
      'single-case opaque token' => ['a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', TRUE],
      'aws access key id' => ['AKIA' . 'IOSFODNN7EXAMPLE', TRUE],
      'google api key' => ['AIza' . str_repeat('aB3', 11), TRUE],
      'password inside markup' => ['<p>' . 'Summer' . '2024' . '!bioland' . '</p>', TRUE],
      'credential inside a json rules string' => ['[{"bundle":"page","pass":"' . 'Summer' . '2024' . '!bioland' . '"}]', TRUE],
      'empty' => ['', FALSE],
      'hex colour' => ['#101010', FALSE],
      'ga id' => ['G-EXAMPLE0', FALSE],
      'iana timezone' => ['America/Argentina/Buenos_Aires', FALSE],
      'prose' => ['Please upload the images for the hero banner here, one per language.', FALSE],
      'plain https url' => ['https://www.example.invalid/some/rather/long/path/segment', FALSE],
      'minified json rules' => ['[{"bundle":"page","field":"field_url","visible":true,"weight":10}]', FALSE],
      // Editorial prose keeps mixed-case-with-digits words: dropping a help
      // text because it cites a numbered article would be its own outage.
      'prose citing a numbered article' => ['<p>See Article15Paragraph2 of the 2026 protocol for details.</p>', FALSE],
      'ga4 measurement id' => ['G-AB12CD34EF', FALSE],
      'universal analytics id' => ['UA-12345678-1', FALSE],
      'image url with a year in the path' => ['https://cdn.example.invalid/2026/hero-banner-image-v2-large.png', FALSE],
    ];
  }

}
