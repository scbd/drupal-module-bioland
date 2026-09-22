<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Relabels the hero media image widget's remove button.
 *
 * BL-841: on the hero media bundle's add/edit forms, users did not realise
 * they had to click "Remove" on the field_media_image widget before they
 * could upload a replacement image. This appends a #process callback to
 * that widget element (rather than editing bioland_form_alter() or
 * ManagedFile::processManagedFile() directly) so the relabel/help text stay
 * isolated in their own file and merge cleanly with sibling changes to
 * bioland.module.
 */
class BiolandHeroImageWidget {

  /**
   * The image field on the hero media bundle this alter targets.
   */
  const FIELD_NAME = 'field_media_image';

  /**
   * Appends the relabeling #process callback to the widget element.
   *
   * Safe to call for any form: it no-ops when the expected field/widget
   * structure is absent, so it never affects bundles other than hero.
   *
   * @param array $form
   *   The form render array, altered by reference.
   */
  public static function alterForm(array &$form): void {
    if (!isset($form[self::FIELD_NAME]['widget'][0]) || !is_array($form[self::FIELD_NAME]['widget'][0])) {
      return;
    }
    $form[self::FIELD_NAME]['widget'][0]['#process'][] = [self::class, 'processElement'];
  }

  /**
   * #process callback: relabels the remove button when a file is present.
   *
   * The remove_button sub-element only exists once ManagedFile::
   * processManagedFile() has built it for a widget that already has a file,
   * so its presence is the signal a file is present.
   *
   * @param array $element
   *   The image widget element, already processed by ManagedFile.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The altered element.
   */
  public static function processElement(array $element, FormStateInterface $form_state, array &$complete_form): array {
    if (!isset($element['remove_button'])) {
      return $element;
    }

    $element['remove_button']['#value'] = t('Replace image');
    $element['bioland_hero_replace_help'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t('Clicking "Replace image" clears the current image so a new one can be uploaded.'),
      '#attributes' => ['class' => ['description', 'bioland-hero-image-replace-help']],
      '#weight' => 100,
    ];

    return $element;
  }

}
