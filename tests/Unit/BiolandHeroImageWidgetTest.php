<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\bioland\Form\BiolandHeroImageWidget;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BiolandHeroImageWidget (BL-841).
 *
 * @covers \Drupal\bioland\Form\BiolandHeroImageWidget
 */
class BiolandHeroImageWidgetTest extends TestCase {

  /**
   * alterForm() appends the #process callback when the widget is present.
   */
  public function testAlterFormAppendsProcessCallbackWhenWidgetPresent(): void {
    $form = [
      'field_media_image' => [
        'widget' => [
          0 => ['#type' => 'managed_file'],
        ],
      ],
    ];

    BiolandHeroImageWidget::alterForm($form);

    $this->assertSame(
      [[BiolandHeroImageWidget::class, 'processElement']],
      $form['field_media_image']['widget'][0]['#process']
    );
  }

  /**
   * alterForm() no-ops when the field/widget structure is absent.
   *
   * This is what keeps the alter safe to run unconditionally: it must never
   * affect a bundle or form that has no field_media_image widget.
   */
  public function testAlterFormNoOpsWhenFieldAbsent(): void {
    $form = ['some_other_field' => ['widget' => [0 => []]]];
    $original = $form;

    BiolandHeroImageWidget::alterForm($form);

    $this->assertSame($original, $form);
  }

  /**
   * alterForm() no-ops when the widget delta 0 is not an array.
   */
  public function testAlterFormNoOpsWhenWidgetDeltaNotArray(): void {
    $form = ['field_media_image' => ['widget' => [0 => 'not-an-array']]];
    $original = $form;

    BiolandHeroImageWidget::alterForm($form);

    $this->assertSame($original, $form);
  }

  /**
   * processElement() relabels the remove button and adds help text.
   *
   * The remove_button sub-element only exists once ManagedFile has built it
   * for a widget that already has a file, so its presence is the signal
   * used to decide whether a file is present.
   */
  public function testProcessElementRelabelsRemoveButtonWhenFilePresent(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $completeForm = [];

    $element = [
      '#type' => 'managed_file',
      'remove_button' => [
        '#type' => 'submit',
        '#value' => 'Remove',
      ],
    ];

    $result = BiolandHeroImageWidget::processElement($element, $formState, $completeForm);

    $this->assertSame('Replace image', (string) $result['remove_button']['#value']);
    $this->assertArrayHasKey('bioland_hero_replace_help', $result);
    $this->assertSame('html_tag', $result['bioland_hero_replace_help']['#type']);
    $this->assertSame('div', $result['bioland_hero_replace_help']['#tag']);
    $this->assertStringContainsString('Replace image', (string) $result['bioland_hero_replace_help']['#value']);
    $this->assertContains('bioland-hero-image-replace-help', $result['bioland_hero_replace_help']['#attributes']['class']);
  }

  /**
   * processElement() leaves the element untouched when no file is present.
   */
  public function testProcessElementLeavesElementUnchangedWhenNoFilePresent(): void {
    $formState = $this->createMock(FormStateInterface::class);
    $completeForm = [];

    $element = [
      '#type' => 'managed_file',
      'upload' => ['#type' => 'file'],
    ];

    $result = BiolandHeroImageWidget::processElement($element, $formState, $completeForm);

    $this->assertSame($element, $result);
    $this->assertArrayNotHasKey('bioland_hero_replace_help', $result);
  }

}
