<?php

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * Stub of Symfony's ServiceNotFoundException.
 *
 * The real container throws this from get()/\Drupal::service() when a service
 * id is not registered. The stub exists so tests/stubs/Drupal.php can throw
 * the same way instead of returning NULL: a stub that returns NULL for an
 * unknown id makes every \Drupal::hasService() guard in the module untestable,
 * because removing the guard would still leave the suite green.
 *
 * Only the parts the stub container needs are modelled -- the id, and the
 * message shape. Symfony's real class also carries $sourceId and
 * $alternatives; nothing under test reads them.
 */
class ServiceNotFoundException extends \InvalidArgumentException {

  /**
   * The unregistered service id.
   *
   * @var string
   */
  protected $id;

  /**
   * Constructs the exception.
   *
   * @param string $id
   *   The service ID that was not found.
   * @param \Throwable|null $previous
   *   The previous exception.
   */
  public function __construct($id, ?\Throwable $previous = NULL) {
    parent::__construct(sprintf('You have requested a non-existent service "%s".', $id), 0, $previous);
    $this->id = $id;
  }

  /**
   * Returns the unregistered service id.
   *
   * @return string
   *   The service ID.
   */
  public function getId() {
    return $this->id;
  }

}
