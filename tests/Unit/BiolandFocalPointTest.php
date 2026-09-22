<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandFocalPoint;
use Drupal\Core\Entity\FieldableEntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the media.bioland_focal_point value contract ("X,Y" percentages).
 *
 * @coversDefaultClass \Drupal\bioland\BiolandFocalPoint
 * @group bioland
 */
class BiolandFocalPointTest extends TestCase {

  protected function tearDown(): void {
    \Drupal::resetContainer();
    parent::tearDown();
  }

  /**
   * Mirrors FocalPointManager::absoluteToRelative() from focal_point 2.1.x.
   */
  private function manager(?object $crop): object {
    return new class($crop) {
      public array $calls = [];

      public function __construct(private ?object $crop) {}

      public function getCropEntity($file, $crop_type) {
        $this->calls[] = $crop_type;
        return $this->crop;
      }

      public function absoluteToRelative($x, $y, $width, $height) {
        return [
          'x' => $width ? (int) round($x / $width * 100) : 0,
          'y' => $height ? (int) round($y / $height * 100) : 0,
        ];
      }
    };
  }

  private function crop(float $x, float $y, bool $isNew = FALSE): object {
    return new class($x, $y, $isNew) {
      public object $x;
      public object $y;

      public function __construct(float $x, float $y, private bool $isNew) {
        $this->x = (object) ['value' => $x];
        $this->y = (object) ['value' => $y];
      }

      public function isNew() {
        return $this->isNew;
      }

      public function getCacheTags() {
        return ['crop:7'];
      }
    };
  }

  private function media(string $bundle = 'hero', bool $hasFile = TRUE, int $width = 2000, int $height = 1000, bool $hasField = TRUE): FieldableEntityInterface {
    $file = $hasFile ? new class {
      public function getCacheTags() {
        return ['file:3'];
      }
    } : NULL;
    $item = (object) ['entity' => $file, 'width' => $width, 'height' => $height];
    $items = new class($item) {
      public function __construct(private object $item) {}

      public function first() {
        return $this->item;
      }
    };

    $media = $this->createMock(FieldableEntityInterface::class);
    $media->method('bundle')->willReturn($bundle);
    $media->method('hasField')->willReturn($hasField);
    $media->method('get')->with('field_media_image')->willReturn($items);
    return $media;
  }

  /**
   * @covers ::resolve
   */
  public function testHeroResolvesRelativePercentagesAndCacheTags(): void {
    $manager = $this->manager($this->crop(1000, 300));
    $result = (new BiolandFocalPoint($manager, 'focal_point'))->resolve($this->media());

    $this->assertSame('50,30', $result['value']);
    $this->assertSame(['file:3', 'crop_list', 'crop:7'], $result['tags']);
    $this->assertSame(['focal_point'], $manager->calls);
  }

  /**
   * @covers ::resolve
   */
  public function testOtherBundlesResolveToNull(): void {
    $result = (new BiolandFocalPoint($this->manager($this->crop(1, 1))))->resolve($this->media('image'));
    $this->assertSame(['value' => NULL, 'tags' => []], $result);
  }

  /**
   * @covers ::resolve
   * @covers ::fromContainer
   */
  public function testMissingFocalPointModuleResolvesToNull(): void {
    $this->assertFalse(\Drupal::hasService('focal_point.manager'));
    $result = BiolandFocalPoint::fromContainer()->resolve($this->media());
    $this->assertSame(['value' => NULL, 'tags' => []], $result);
  }

  /**
   * @covers ::resolve
   */
  public function testMissingFieldOrFileResolvesToNull(): void {
    $resolver = new BiolandFocalPoint($this->manager($this->crop(1, 1)));
    $this->assertNull($resolver->resolve($this->media('hero', TRUE, 10, 10, FALSE))['value']);
    $this->assertNull($resolver->resolve($this->media('hero', FALSE))['value']);
  }

  /**
   * An unsaved crop (no point chosen yet) is NULL but still tagged crop_list.
   *
   * @covers ::resolve
   */
  public function testNoSavedCropResolvesToNullWithListTag(): void {
    $result = (new BiolandFocalPoint($this->manager($this->crop(1, 1, TRUE))))->resolve($this->media());
    $this->assertNull($result['value']);
    $this->assertSame(['file:3', 'crop_list'], $result['tags']);
  }

  /**
   * @covers ::resolve
   */
  public function testMissingDimensionsResolveToNull(): void {
    $result = (new BiolandFocalPoint($this->manager($this->crop(1, 1))))->resolve($this->media('hero', TRUE, 0, 0));
    $this->assertNull($result['value']);
  }

  /**
   * @covers ::fromContainer
   */
  public function testFromContainerUsesConfiguredCropType(): void {
    $manager = $this->manager($this->crop(0, 0));
    $config = new \Drupal\Core\Config\Config('focal_point.settings', ['crop_type' => 'custom_crop']);
    $factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $factory->method('get')->willReturn($config);
    \Drupal::setService('config.factory', $factory);
    \Drupal::setService('focal_point.manager', $manager);

    $this->assertSame('0,0', BiolandFocalPoint::fromContainer()->resolve($this->media())['value']);
    $this->assertSame(['custom_crop'], $manager->calls);
  }

  /**
   * @covers ::format
   * @dataProvider formatProvider
   */
  public function testFormatClampsToIntegerPercentages(array $anchor, string $expected): void {
    $this->assertSame($expected, BiolandFocalPoint::format($anchor));
  }

  public static function formatProvider(): array {
    return [
      'plain' => [['x' => 50, 'y' => 30], '50,30'],
      'rounded floats' => [['x' => 12.6, 'y' => 99.4], '13,99'],
      'clamped' => [['x' => -5, 'y' => 140], '0,100'],
      'missing keys default to centre' => [[], '50,50'],
    ];
  }

}
