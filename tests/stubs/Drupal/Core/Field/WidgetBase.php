<?php

namespace Drupal\Core\Field;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Stub of core's WidgetBase: only the static widget state accessors.
 *
 * Same storage path as core: field_storage > #parents > ...$parents > #fields
 * > $field_name, read and written through $form_state->getStorage() by
 * reference, so the form state double must declare &getStorage().
 */
abstract class WidgetBase {

  /**
   * Retrieves processing information about the widget from $form_state.
   */
  public static function getWidgetState(array $parents, $field_name, FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), static::getWidgetStateParents($parents, $field_name));
  }

  /**
   * Stores processing information about the widget in $form_state.
   */
  public static function setWidgetState(array $parents, $field_name, FormStateInterface $form_state, array $field_state) {
    NestedArray::setValue($form_state->getStorage(), static::getWidgetStateParents($parents, $field_name), $field_state);
  }

  /**
   * Returns the location of processing information within $form_state.
   */
  protected static function getWidgetStateParents(array $parents, $field_name) {
    return array_merge(['field_storage', '#parents'], $parents, ['#fields', $field_name]);
  }

}
