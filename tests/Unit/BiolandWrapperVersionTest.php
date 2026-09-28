<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandWrapperVersion;
use PHPUnit\Framework\TestCase;

/**
 * Tests BiolandWrapperVersion::parse(), compare() and evaluate() (BL-1189).
 *
 * @group bioland
 * @coversDefaultClass \Drupal\bioland\BiolandWrapperVersion
 */
class BiolandWrapperVersionTest extends TestCase {

  /**
   * @covers ::parse
   */
  public function testParseValidVersion(): void {
    $this->assertSame(
      ['core' => [11, 4, 7], 'revision' => 7],
      BiolandWrapperVersion::parse('11.4.7-v7')
    );
  }

  /**
   * @covers ::parse
   */
  public function testParseNull(): void {
    $this->assertNull(BiolandWrapperVersion::parse(NULL));
  }

  /**
   * @covers ::parse
   *
   * @dataProvider malformedVersionProvider
   */
  public function testParseMalformed(string $version): void {
    $this->assertNull(BiolandWrapperVersion::parse($version));
  }

  /**
   * @return array<string, array{0: string}>
   */
  public static function malformedVersionProvider(): array {
    return [
      'empty string' => [''],
      'no revision' => ['11.4.7'],
      'no v prefix' => ['11.4.7-7'],
      'non-numeric revision' => ['11.4.7-vx'],
      'garbage' => ['not-a-version'],
      'two-part core' => ['11.4-v7'],
    ];
  }

  /**
   * @covers ::compare
   */
  public function testCompareEqual(): void {
    $this->assertSame(0, BiolandWrapperVersion::compare('11.4.7-v7', '11.4.7-v7'));
  }

  /**
   * @covers ::compare
   */
  public function testCompareHigherPatch(): void {
    $this->assertSame(1, BiolandWrapperVersion::compare('11.4.8-v1', '11.4.7-v7'));
    $this->assertSame(-1, BiolandWrapperVersion::compare('11.4.7-v7', '11.4.8-v1'));
  }

  /**
   * @covers ::compare
   */
  public function testCompareHigherRevision(): void {
    $this->assertSame(1, BiolandWrapperVersion::compare('11.4.7-v8', '11.4.7-v7'));
  }

  /**
   * @covers ::compare
   */
  public function testCompareLowerRevision(): void {
    $this->assertSame(-1, BiolandWrapperVersion::compare('11.4.7-v6', '11.4.7-v7'));
  }

  /**
   * A lower core version outranks a higher revision on that lower core.
   *
   * @covers ::compare
   */
  public function testCompareLowerCoreOutweighsHigherRevision(): void {
    $this->assertSame(-1, BiolandWrapperVersion::compare('11.4.5-v99', '11.4.7-v1'));
  }

  /**
   * @covers ::compare
   */
  public function testCompareThrowsOnMalformedInput(): void {
    $this->expectException(\InvalidArgumentException::class);
    BiolandWrapperVersion::compare('not-a-version', BiolandWrapperVersion::MINIMUM);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateEqualToMinimumIsOk(): void {
    $result = BiolandWrapperVersion::evaluate(BiolandWrapperVersion::MINIMUM);
    $this->assertSame(BiolandWrapperVersion::SEVERITY_OK, $result['severity']);
    $this->assertSame(BiolandWrapperVersion::MINIMUM, $result['value']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateHigherPatchIsOk(): void {
    $result = BiolandWrapperVersion::evaluate('11.4.8-v1');
    $this->assertSame(BiolandWrapperVersion::SEVERITY_OK, $result['severity']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateHigherRevisionIsOk(): void {
    $result = BiolandWrapperVersion::evaluate('11.4.7-v8');
    $this->assertSame(BiolandWrapperVersion::SEVERITY_OK, $result['severity']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateLowerRevisionIsWarning(): void {
    $result = BiolandWrapperVersion::evaluate('11.4.7-v6');
    $this->assertSame(BiolandWrapperVersion::SEVERITY_WARNING, $result['severity']);
    $this->assertStringContainsString('toast_image_editor', $result['message']);
    $this->assertStringContainsString('llms_txt', $result['message']);
    $this->assertStringContainsString('ckeditor5_fullscreen', $result['message']);
    $this->assertStringContainsString('ckeditor5_icons', $result['message']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateLowerCoreIsWarning(): void {
    $result = BiolandWrapperVersion::evaluate('11.4.5-v2');
    $this->assertSame(BiolandWrapperVersion::SEVERITY_WARNING, $result['severity']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateMalformedIsWarningNotError(): void {
    $result = BiolandWrapperVersion::evaluate('garbage');
    $this->assertSame(BiolandWrapperVersion::SEVERITY_WARNING, $result['severity']);
    $this->assertStringContainsString('cannot verify', $result['message']);
  }

  /**
   * @covers ::evaluate
   */
  public function testEvaluateUnsetIsWarningNotError(): void {
    $result = BiolandWrapperVersion::evaluate(NULL);
    $this->assertSame(BiolandWrapperVersion::SEVERITY_WARNING, $result['severity']);
    $this->assertSame('Not set', $result['value']);
    $this->assertStringContainsString('cannot verify', $result['message']);
  }

}
