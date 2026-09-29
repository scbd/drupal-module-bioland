<?php

namespace Drupal\bioland\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iframe\Plugin\Field\FieldWidget\IframeUrlwidthheightWidget;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * The iframe_default widget, flagging a violation on the sub-field it names.
 *
 * Contrib's widget inherits WidgetBase::errorElement(), which flags the whole
 * delta fieldset, so the embed URL violations raised at "N.url" repeated
 * under URL, title, width and height. Core's LinkWidget maps the path the
 * same way this does (BL-1273).
 *
 * Not a plugin of its own: bioland_field_widget_info_alter() swaps it in
 * for the contrib class, so form displays keep the iframe_default id.
 */
class BiolandIframeWidget extends IframeUrlwidthheightWidget {

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    $child = self::childFor($violation);
    return $child !== NULL && isset($element[$child]) ? $element[$child] : $element;
  }

  /**
   * The sub-field a violation names, or NULL when it targets the whole item.
   *
   * WidgetBase::flagErrors() sets arrayPropertyPath to the property path with
   * the delta removed, so "0.url" arrives as ['url'].
   */
  public static function childFor(ConstraintViolationInterface $violation): ?string {
    $path = $violation->arrayPropertyPath ?? [];
    $child = is_array($path) ? ($path[0] ?? NULL) : NULL;
    return is_string($child) && $child !== '' ? $child : NULL;
  }

}
