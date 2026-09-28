<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandFocalPoint;
use Drupal\bioland\Plugin\Field\BiolandFocalPointItemList;
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
    };
  }

  private function media(string $bundle = 'hero', bool $hasFile = TRUE, int $width = 2000, int $height = 1000, bool $hasField = TRUE): FieldableEntityInterface {
    $file = $hasFile ? new \stdClass() : NULL;
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
  public function testHeroResolvesRelativePercentages(): void {
    $manager = $this->manager($this->crop(1000, 300));

    $this->assertSame('50,30', (new BiolandFocalPoint($manager, 'focal_point'))->resolve($this->media()));
    $this->assertSame(['focal_point'], $manager->calls);
  }

  /**
   * @covers ::resolve
   */
  public function testOtherBundlesResolveToNull(): void {
    $this->assertNull((new BiolandFocalPoint($this->manager($this->crop(1, 1))))->resolve($this->media('image')));
  }

  /**
   * @covers ::resolve
   * @covers ::fromContainer
   */
  public function testMissingFocalPointModuleResolvesToNull(): void {
    $this->assertFalse(\Drupal::hasService('focal_point.manager'));
    $this->assertNull(BiolandFocalPoint::fromContainer()->resolve($this->media()));
  }

  /**
   * @covers ::resolve
   */
  public function testMissingFieldOrFileResolvesToNull(): void {
    $resolver = new BiolandFocalPoint($this->manager($this->crop(1, 1)));
    $this->assertNull($resolver->resolve($this->media('hero', TRUE, 10, 10, FALSE)));
    $this->assertNull($resolver->resolve($this->media('hero', FALSE)));
  }

  /**
   * An unsaved crop (no point chosen yet) resolves to NULL.
   *
   * @covers ::resolve
   */
  public function testNoSavedCropResolvesToNull(): void {
    $this->assertNull((new BiolandFocalPoint($this->manager($this->crop(1, 1, TRUE))))->resolve($this->media()));
  }

  /**
   * @covers ::resolve
   */
  public function testMissingDimensionsResolveToNull(): void {
    $this->assertNull((new BiolandFocalPoint($this->manager($this->crop(1, 1))))->resolve($this->media('hero', TRUE, 0, 0)));
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

    $this->assertSame('0,0', BiolandFocalPoint::fromContainer()->resolve($this->media()));
    $this->assertSame(['custom_crop'], $manager->calls);
  }

  /**
   * The computed field stays empty for non-hero media and entities without
   * the image field, and holds the "X,Y" value for a hero.
   *
   * @covers \Drupal\bioland\Plugin\Field\BiolandFocalPointItemList::computeValue
   */
  public function testComputedFieldEarlyReturnsAndHeroValue(): void {
    \Drupal::setService('focal_point.manager', $this->manager($this->crop(1000, 300)));

    $this->assertSame([], (new BiolandFocalPointItemList($this->media('image')))->getValue());
    $this->assertSame([], (new BiolandFocalPointItemList($this->media('hero', TRUE, 10, 10, FALSE)))->getValue());
    $this->assertSame([['value' => '50,30']], (new BiolandFocalPointItemList($this->media()))->getValue());
  }

  /**
   * @covers ::baseFieldDefinition
   */
  public function testBaseFieldDefinitionIsComputedReadOnlyString(): void {
    $definition = BiolandFocalPoint::baseFieldDefinition();

    $this->assertSame('string', $definition->type);
    $this->assertTrue($definition->values['setComputed']);
    $this->assertTrue($definition->values['setReadOnly']);
    $this->assertFalse($definition->values['setTranslatable']);
    $this->assertSame(BiolandFocalPointItemList::class, $definition->values['setClass']);
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
