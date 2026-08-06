<?php

namespace Drupal\Core\Access;

/**
 * Stub class for AccessResult.
 *
 * Reproduces only what the module uses: the three constructors, the reason
 * string, cacheability collected from addCacheableDependency(), and the three
 * predicates. Enough for a unit test to assert that a check allowed or forbade
 * access AND that it carried the config cache tag that makes the decision
 * refresh on save.
 */
class AccessResult implements AccessResultInterface {

  /**
   * Whether access is allowed.
   *
   * @var bool
   */
  protected $allowed = FALSE;

  /**
   * Whether access is forbidden.
   *
   * @var bool
   */
  protected $forbidden = FALSE;

  /**
   * The reason recorded with a forbidden or neutral result.
   *
   * @var string|null
   */
  protected $reason = NULL;

  /**
   * Cache tags collected from dependencies.
   *
   * @var array
   */
  protected $cacheTags = [];

  /**
   * Creates an allowed result.
   *
   * @return static
   *   The result.
   */
  public static function allowed() {
    $result = new static();
    $result->allowed = TRUE;

    return $result;
  }

  /**
   * Creates a forbidden result.
   *
   * @param string|null $reason
   *   Why access is forbidden.
   *
   * @return static
   *   The result.
   */
  public static function forbidden($reason = NULL) {
    $result = new static();
    $result->forbidden = TRUE;
    $result->reason = $reason;

    return $result;
  }

  /**
   * Creates a neutral result.
   *
   * @param string|null $reason
   *   Why no opinion is expressed.
   *
   * @return static
   *   The result.
   */
  public static function neutral($reason = NULL) {
    $result = new static();
    $result->reason = $reason;

    return $result;
  }

  /**
   * Creates an allowed result when the condition holds, neutral otherwise.
   *
   * @param bool $condition
   *   The condition.
   *
   * @return static
   *   The result.
   */
  public static function allowedIf($condition) {
    return $condition ? static::allowed() : static::neutral();
  }

  /**
   * Creates a forbidden result when the condition holds, neutral otherwise.
   *
   * @param bool $condition
   *   The condition.
   * @param string|null $reason
   *   Why access is forbidden.
   *
   * @return static
   *   The result.
   */
  public static function forbiddenIf($condition, $reason = NULL) {
    return $condition ? static::forbidden($reason) : static::neutral();
  }

  /**
   * Merges another object's cacheability into this result.
   *
   * @param mixed $other
   *   Any object exposing getCacheTags(), as config objects do.
   *
   * @return $this
   *   The result.
   */
  public function addCacheableDependency($other) {
    if (is_object($other) && method_exists($other, 'getCacheTags')) {
      $this->cacheTags = array_values(array_unique(array_merge($this->cacheTags, $other->getCacheTags())));
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    return $this->allowed && !$this->forbidden;
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden() {
    return $this->forbidden;
  }

  /**
   * {@inheritdoc}
   */
  public function isNeutral() {
    return !$this->allowed && !$this->forbidden;
  }

  /**
   * Returns the recorded reason.
   *
   * @return string|null
   *   The reason, if any.
   */
  public function getReason() {
    return $this->reason;
  }

  /**
   * Returns the collected cache tags.
   *
   * @return array
   *   The cache tags.
   */
  public function getCacheTags() {
    return $this->cacheTags;
  }

}
