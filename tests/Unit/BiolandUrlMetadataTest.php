<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandUrlMetadata;
use PHPUnit\Framework\TestCase;

/**
 * BL-1191: parsing and SSRF helpers for the URL title/description lookup.
 *
 * @covers \Drupal\bioland\BiolandUrlMetadata
 * @group bioland
 */
class BiolandUrlMetadataTest extends TestCase {

  public function testOpenGraphWinsOverTitleAndMetaDescription(): void {
    $html = '<html><head><title>Plain</title><meta name="description" content="Meta desc">'
      . '<meta property="og:title" content="OG &amp; Title"><meta property="og:description" content="OG desc"></head></html>';
    $this->assertSame(['title' => 'OG & Title', 'description' => 'OG desc'], BiolandUrlMetadata::parse($html));
  }

  public function testFallsBackToTitleTagAndMetaDescription(): void {
    $html = "<html><head><title>\n  Biosafety   Clearing-House </title><meta name=\"Description\" content=\"The BCH.\"></head></html>";
    $this->assertSame(['title' => 'Biosafety Clearing-House', 'description' => 'The BCH.'], BiolandUrlMetadata::parse($html));
  }

  public function testKeepsUtf8AndConvertsDeclaredCharset(): void {
    $this->assertSame('Convention sur la diversité', BiolandUrlMetadata::parse('<title>Convention sur la diversité</title>')['title']);
    $latin1 = mb_convert_encoding('<title>Diversité</title>', 'ISO-8859-1', 'UTF-8');
    $this->assertSame('Diversité', BiolandUrlMetadata::parse($latin1, 'ISO-8859-1')['title']);
  }

  public function testUnknownCharsetIsParsedUnconverted(): void {
    $this->assertSame('Plain', BiolandUrlMetadata::parse('<title>Plain</title>', 'bogus')['title']);
  }

  public function testStripsMarkupAndTruncates(): void {
    $long = str_repeat('a', 700);
    $result = BiolandUrlMetadata::parse('<meta name="description" content="' . $long . '"><title><b>x</b></title>');
    $this->assertSame(BiolandUrlMetadata::DESCRIPTION_MAX, mb_strlen($result['description']));
    $this->assertStringEndsWith('…', $result['description']);
  }

  public function testEmptyHtml(): void {
    $this->assertSame(['title' => '', 'description' => ''], BiolandUrlMetadata::parse('  '));
  }

  public function testDetectCharset(): void {
    $this->assertSame('ISO-8859-1', BiolandUrlMetadata::detectCharset('text/html; charset=ISO-8859-1'));
    $this->assertSame('', BiolandUrlMetadata::detectCharset('text/html'));
  }

  /**
   * @dataProvider ipProvider
   */
  public function testIsPublicIp(string $ip, bool $expected): void {
    $this->assertSame($expected, BiolandUrlMetadata::isPublicIp($ip));
  }

  public static function ipProvider(): array {
    return [
      'public v4' => ['93.184.216.34', TRUE],
      'loopback' => ['127.0.0.1', FALSE],
      'rfc1918' => ['10.1.2.3', FALSE],
      'metadata' => ['169.254.169.254', FALSE],
      'cgnat' => ['100.64.1.1', FALSE],
      'v6 loopback' => ['::1', FALSE],
      'v6 public' => ['2606:4700::1111', TRUE],
      'garbage' => ['not-an-ip', FALSE],
      'this network' => ['0.1.2.3', FALSE],
      'ietf protocol' => ['192.0.0.8', FALSE],
      'benchmarking' => ['198.18.0.1', FALSE],
      'multicast v4' => ['224.0.0.1', FALSE],
      'reserved v4' => ['240.0.0.1', FALSE],
      'broadcast' => ['255.255.255.255', FALSE],
      'v6 unspecified' => ['::', FALSE],
      'mapped loopback dotted' => ['::ffff:127.0.0.1', FALSE],
      'mapped loopback hex' => ['::ffff:7f00:1', FALSE],
      'mapped metadata' => ['::ffff:169.254.169.254', FALSE],
      'mapped public' => ['::ffff:93.184.216.34', TRUE],
      'compatible loopback' => ['::127.0.0.1', FALSE],
      'compatible rfc1918' => ['::10.0.0.1', FALSE],
      'compatible public' => ['::93.184.216.34', TRUE],
      'nat64 metadata' => ['64:ff9b::a9fe:a9fe', FALSE],
      'nat64 public' => ['64:ff9b::5db8:d822', TRUE],
      'nat64 local-use' => ['64:ff9b:1::1', FALSE],
      '6to4 rfc1918' => ['2002:c0a8:0101::1', FALSE],
      '6to4 public' => ['2002:5db8:d822::1', TRUE],
      'site-local' => ['fec0::1', FALSE],
      'multicast v6' => ['ff02::1', FALSE],
      'unique local' => ['fd00::1', FALSE],
      'link-local v6' => ['fe80::1', FALSE],
    ];
  }

  /**
   * @dataProvider redirectProvider
   */
  public function testResolveRedirect(string $base, string $location, string $expected): void {
    $this->assertSame($expected, BiolandUrlMetadata::resolveRedirect($base, $location));
  }

  public static function redirectProvider(): array {
    return [
      'absolute' => ['http://a.org/x', 'https://b.org/y', 'https://b.org/y'],
      'protocol relative' => ['https://a.org/x', '//b.org/y', 'https://b.org/y'],
      'root relative' => ['https://a.org:8443/x/y', '/en/', 'https://a.org:8443/en/'],
      'path relative' => ['https://a.org/x/y', 'z', 'https://a.org/x/z'],
      'query only keeps path' => ['https://a.org/x/y?old=1', '?lang=en', 'https://a.org/x/y?lang=en'],
      'fragment only keeps path and query' => ['https://a.org/x/y?old=1', '#top', 'https://a.org/x/y?old=1#top'],
      'dot segments' => ['https://a.org/x/y/z', '../w?q=1', 'https://a.org/x/w?q=1'],
      'above root' => ['https://a.org/x', '../../w', 'https://a.org/w'],
      'empty base path' => ['https://a.org', 'z', 'https://a.org/z'],
    ];
  }

}
