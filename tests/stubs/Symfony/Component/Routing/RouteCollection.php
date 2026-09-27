<?php

namespace Symfony\Component\Routing;

/**
 * Minimal stub of Symfony's RouteCollection for standalone unit tests.
 */
class RouteCollection {

  private array $routes = [];

  public function add(string $name, Route $route): void {
    $this->routes[$name] = $route;
  }

  public function get(string $name): ?Route {
    return $this->routes[$name] ?? NULL;
  }

}
