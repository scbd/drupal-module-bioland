<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Plugin\Field\FieldWidget\BiolandIframeWidget;
use Drupal\Core\Form\FormStateInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Guards the BL-1273 embed widget error mapping and its info alter wiring.
 *
 * @covers \Drupal\bioland\Plugin\Field\FieldWidget\BiolandIframeWidget
 */
class BiolandIframeWidgetTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../bioland.module';
  }

  /**
   * A delta element shaped like the contrib widget's fieldset.
   */
  private static function element(): array {
    return [
      '#type' => 'fieldset',
      'title' => ['#type' => 'textfield', '#title' => 'Iframe Title'],
      'url' => ['#type' => 'textfield', '#title' => 'Iframe URL'],
      'width' => ['#type' => 'textfield'],
      'height' => ['#type' => 'textfield'],
    ];
  }

  /**
   * A violation carrying the path WidgetBase::flagErrors() leaves on it.
   */
  private function violation(mixed $path): ConstraintViolationInterface {
    $violation = $this->createMock(ConstraintViolationInterface::class);
    if ($path !== NULL) {
      $violation->arrayPropertyPath = $path;
    }
    return $violation;
  }

  /**
   * A "N.url" violation is flagged on the URL sub-field alone.
   */
  public function testUrlViolationFlagsUrlSubField(): void {
    $widget = new BiolandIframeWidget();
    $element = self::element();
    $flagged = $widget->errorElement($element, $this->violation(['url']), [], $this->createMock(FormStateInterface::class));
    $this->assertSame($element['url'], $flagged);
  }

  /**
   * A violation with no path, or naming an unknown child, flags the item.
   *
   * @dataProvider wholeItemProvider
   */
  public function testOtherViolationsFlagWholeItem(mixed $path): void {
    $widget = new BiolandIframeWidget();
    $element = self::element();
    $flagged = $widget->errorElement($element, $this->violation($path), [], $this->createMock(FormStateInterface::class));
    $this->assertSame($element, $flagged);
  }

  /**
   * Paths that must fall back to the whole delta element.
   */
  public static function wholeItemProvider(): array {
    return [
      'no path' => [NULL],
      'empty path' => [[]],
      'unknown child' => [['sandbox']],
      'not a string' => [[0]],
      'not an array' => ['url'],
    ];
  }

  /**
   * The info alter swaps only the iframe_default class, and only when present.
   */
  public function testInfoAlterSwapsIframeDefaultClass(): void {
    $info = [
      'iframe_default' => ['id' => 'iframe_default', 'class' => 'Drupal\iframe\Plugin\Field\FieldWidget\IframeUrlwidthheightWidget'],
      'iframe_url' => ['id' => 'iframe_url', 'class' => 'Drupal\iframe\Plugin\Field\FieldWidget\IframeUrlWidget'],
    ];
    bioland_field_widget_info_alter($info);
    $this->assertSame(BiolandIframeWidget::class, $info['iframe_default']['class']);
    $this->assertSame('iframe_default', $info['iframe_default']['id']);
    $this->assertSame('Drupal\iframe\Plugin\Field\FieldWidget\IframeUrlWidget', $info['iframe_url']['class']);

    $without = ['link_default' => ['class' => 'X']];
    bioland_field_widget_info_alter($without);
    $this->assertSame(['link_default' => ['class' => 'X']], $without, 'Nothing is added when iframe is absent.');
  }

}
