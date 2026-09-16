<?php

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * Stub for AccessResult, carrying just enough for unit assertions.
 */
class AccessResult implements AccessResultInterface {

  /**
   * The verdict: 'allowed', 'neutral' or 'forbidden'.
   *
   * @var string
   */
  protected $verdict;

  /**
   * The forbidden reason, when any.
   *
   * @var string|null
   */
  protected $reason;

  /**
   * Cache contexts.
   *
   * @var string[]
   */
  protected $contexts = [];

  /**
   * Cache max age.
   *
   * @var int
   */
  protected $maxAge = -1;

  /**
   * Constructs a result.
   *
   * @param string $verdict
   *   The verdict.
   * @param string|null $reason
   *   The reason.
   */
  protected function __construct($verdict, $reason = NULL) {
    $this->verdict = $verdict;
    $this->reason = $reason;
  }

  /**
   * Allowed result.
   *
   * @return static
   *   The result.
   */
  public static function allowed() {
    return new static('allowed');
  }

  /**
   * Neutral result.
   *
   * @return static
   *   The result.
   */
  public static function neutral($reason = NULL) {
    return new static('neutral', $reason);
  }

  /**
   * Forbidden result.
   *
   * @param string|null $reason
   *   The reason.
   *
   * @return static
   *   The result.
   */
  public static function forbidden($reason = NULL) {
    return new static('forbidden', $reason);
  }

  /**
   * Allowed when the account holds the permission, neutral otherwise.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $permission
   *   The permission.
   *
   * @return static
   *   The result.
   */
  public static function allowedIfHasPermission(AccountInterface $account, $permission) {
    return $account->hasPermission($permission) ? static::allowed() : static::neutral("The '$permission' permission is required.");
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed() {
    return $this->verdict === 'allowed';
  }

  /**
   * {@inheritdoc}
   */
  public function isForbidden() {
    return $this->verdict === 'forbidden';
  }

  /**
   * Whether the result is neutral.
   *
   * @return bool
   *   TRUE when neutral.
   */
  public function isNeutral() {
    return $this->verdict === 'neutral';
  }

  /**
   * Gets the reason, when any.
   *
   * @return string|null
   *   The reason.
   */
  public function getReason() {
    return $this->reason;
  }

  /**
   * Adds cache contexts.
   *
   * @param string[] $contexts
   *   The contexts.
   *
   * @return $this
   *   This object.
   */
  public function addCacheContexts(array $contexts) {
    $this->contexts = array_values(array_unique(array_merge($this->contexts, $contexts)));
    return $this;
  }

  /**
   * Gets the cache contexts.
   *
   * @return string[]
   *   The contexts.
   */
  public function getCacheContexts() {
    return $this->contexts;
  }

  /**
   * Sets the cache max age.
   *
   * @param int $max_age
   *   The max age.
   *
   * @return $this
   *   This object.
   */
  public function setCacheMaxAge($max_age) {
    $this->maxAge = $max_age;
    return $this;
  }

  /**
   * Gets the cache max age.
   *
   * @return int
   *   The max age.
   */
  public function getCacheMaxAge() {
    return $this->maxAge;
  }

}
