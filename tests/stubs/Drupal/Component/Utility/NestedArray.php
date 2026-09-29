<?php

namespace Drupal\Component\Utility;

/**
 * Stub of core's NestedArray: getValue(), setValue(), unsetValue().
 */
class NestedArray {

  /**
   * Retrieves a value from a nested array with variable depth.
   */
  public static function &getValue(array &$array, array $parents, &$key_exists = NULL) {
    $ref = &$array;
    foreach ($parents as $parent) {
      if (is_array($ref) && array_key_exists($parent, $ref)) {
        $ref = &$ref[$parent];
      }
      else {
        $key_exists = FALSE;
        $null = NULL;
        return $null;
      }
    }
    $key_exists = TRUE;
    return $ref;
  }

  /**
   * Sets a value in a nested array with variable depth.
   */
  public static function setValue(array &$array, array $parents, $value, $force = FALSE) {
    $ref = &$array;
    foreach ($parents as $parent) {
      if ($force && isset($ref) && !is_array($ref)) {
        $ref = [];
      }
      $ref = &$ref[$parent];
    }
    $ref = $value;
  }

  /**
   * Unsets a value in a nested array with variable depth.
   */
  public static function unsetValue(array &$array, array $parents, &$key_existed = NULL) {
    $unset_key = array_pop($parents);
    $ref = &self::getValue($array, $parents, $key_existed);
    if ($key_existed && is_array($ref) && array_key_exists($unset_key, $ref)) {
      $key_existed = TRUE;
      unset($ref[$unset_key]);
    }
    else {
      $key_existed = FALSE;
    }
  }

}
