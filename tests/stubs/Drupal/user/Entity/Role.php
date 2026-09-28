<?php

namespace Drupal\user\Entity;

/**
 * Stub for Drupal\user\Entity\Role (only the members this module touches).
 *
 * Saved roles live in a static in-memory store so Role::load() returns what a
 * test seeded; call Role::resetStore() from tearDown().
 */
class Role {

  /**
   * Saved roles keyed by role ID.
   *
   * @var \Drupal\user\Entity\Role[]
   */
  protected static array $store = [];

  protected string $id;

  protected array $permissions;

  /**
   * How many times save() ran on this role (test introspection).
   */
  public int $saveCount = 0;

  public function __construct(string $id, array $permissions = []) {
    $this->id = $id;
    $this->permissions = $permissions;
  }

  public static function create(array $values) {
    return new static($values['id'], $values['permissions'] ?? []);
  }

  public static function load($id) {
    return static::$store[$id] ?? NULL;
  }

  /**
   * Empties the in-memory role store.
   */
  public static function resetStore(): void {
    static::$store = [];
  }

  public function id() {
    return $this->id;
  }

  public function save() {
    $this->saveCount++;
    static::$store[$this->id] = $this;
  }

  public function hasPermission($permission) {
    return in_array($permission, $this->permissions, TRUE);
  }

  public function grantPermission($permission) {
    if (!$this->hasPermission($permission)) {
      $this->permissions[] = $permission;
    }
    return $this;
  }

  public function revokePermission($permission) {
    $this->permissions = array_values(array_diff($this->permissions, [$permission]));
    return $this;
  }

  public function getPermissions() {
    return $this->permissions;
  }

}
