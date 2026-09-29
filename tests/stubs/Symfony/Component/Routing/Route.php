<?php

namespace Symfony\Component\Routing;

/**
 * Minimal stub of Symfony's Route for standalone unit tests.
 */
class Route {

  private array $requirements;

  public function __construct(private string $path, array $defaults = [], array $requirements = []) {
    $this->requirements = $requirements;
  }

  public function setRequirement(string $key, string $regex): static {
    $this->requirements[$key] = $regex;
    return $this;
  }

  public function getRequirement(string $key): ?string {
    return $this->requirements[$key] ?? NULL;
  }

  public function getRequirements(): array {
    return $this->requirements;
  }

}
