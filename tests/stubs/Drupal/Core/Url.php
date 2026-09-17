<?php

namespace Drupal\Core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;

/**
 * Stub class for Url.
 *
 * Reproduces only what the module uses: the fromRoute() factory, read-back of
 * the route name, parameters and options, and the access() check.
 *
 * The real Url::access() resolves the route through the access manager, which
 * needs a container. A unit test has none, so the decision is supplied here
 * instead: set self::$accessResult before exercising code that builds a Url.
 * The knob lives in this stub and nowhere in the module - production code calls
 * the real access manager.
 */
class Url {

  /**
   * The decision access() returns.
   *
   * A bool, or an AccessResultInterface to also exercise cache metadata.
   * Tests that build Urls should reset this in setUp().
   *
   * @var mixed
   */
  public static $accessResult = TRUE;

  /**
   * Every fromRoute() call, in order, as route/parameters/options arrays.
   *
   * @var array
   */
  public static $created = [];

  /**
   * The route name.
   *
   * @var string
   */
  protected $routeName;

  /**
   * The route parameters.
   *
   * @var array
   */
  protected $routeParameters;

  /**
   * The URL options.
   *
   * @var array
   */
  protected $options;

  /**
   * Constructs the URL stub.
   *
   * @param string $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param array $options
   *   The options.
   */
  public function __construct($route_name, array $route_parameters = [], array $options = []) {
    $this->routeName = $route_name;
    $this->routeParameters = $route_parameters;
    $this->options = $options;
  }

  /**
   * Resets the static test state.
   */
  public static function reset() {
    self::$accessResult = TRUE;
    self::$created = [];
  }

  /**
   * Builds a URL from a route.
   *
   * @param string $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param array $options
   *   The options.
   *
   * @return static
   *   The URL.
   */
  public static function fromRoute($route_name, $route_parameters = [], $options = []) {
    self::$created[] = [
      'route' => $route_name,
      'parameters' => $route_parameters,
      'options' => $options,
    ];

    return new static($route_name, (array) $route_parameters, (array) $options);
  }

  /**
   * Returns the route name.
   *
   * @return string
   *   The route name.
   */
  public function getRouteName() {
    return $this->routeName;
  }

  /**
   * Returns the route parameters.
   *
   * @return array
   *   The route parameters.
   */
  public function getRouteParameters() {
    return $this->routeParameters;
  }

  /**
   * Returns the URL options.
   *
   * @return array
   *   The options.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Returns the configured access decision.
   *
   * @param mixed $account
   *   Unused; the real signature takes an account.
   * @param bool $return_as_object
   *   TRUE to return an access result object rather than a bool.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The decision.
   */
  public function access($account = NULL, $return_as_object = FALSE) {
    $result = self::$accessResult;

    if ($result instanceof AccessResultInterface) {
      return $return_as_object ? $result : $result->isAllowed();
    }

    if (!$return_as_object) {
      return (bool) $result;
    }

    return $result ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Returns a printable form of the URL.
   *
   * @return string
   *   The route name and its parameters, which is all a unit test can know.
   */
  public function toString() {
    return $this->routeParameters === []
      ? $this->routeName
      : $this->routeName . ':' . implode(',', $this->routeParameters);
  }

}
