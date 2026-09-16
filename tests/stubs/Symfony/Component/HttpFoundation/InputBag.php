<?php

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Test stub for Symfony InputBag.
 *
 * Only get(), and it REJECTS rather than ignores. The real InputBag::get()
 * throws BadRequestException the moment a stored value is neither scalar nor
 * \Stringable, which is how "?parent[]=x" becomes a 400 in the request layer,
 * before any controller is reached. An earlier version of this stub returned
 * the default instead; that quietly told the controller's tests a lie about
 * where array input is stopped.
 */
class InputBag {

  /**
   * The parameters.
   *
   * @var array
   */
  protected $parameters;

  /**
   * Constructs the bag.
   *
   * @param array $parameters
   *   The parameters.
   */
  public function __construct(array $parameters = []) {
    $this->parameters = $parameters;
  }

  /**
   * Returns a parameter value.
   *
   * @param string $key
   *   The parameter name.
   * @param mixed $default
   *   The value to return when the parameter is absent.
   *
   * @return mixed
   *   The value.
   *
   * @throws \InvalidArgumentException
   *   When the default itself is neither scalar nor \Stringable.
   * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
   *   When the stored value is neither scalar nor \Stringable - an array or an
   *   object, as "?parent[]=x" produces.
   */
  public function get($key, $default = NULL) {
    if ($default !== NULL && !is_scalar($default) && !$default instanceof \Stringable) {
      throw new \InvalidArgumentException(sprintf('Expected a scalar value as a 2nd argument to "%s()", "%s" given.', __METHOD__, get_debug_type($default)));
    }

    if (!array_key_exists($key, $this->parameters)) {
      return $default;
    }

    $value = $this->parameters[$key];
    if ($value !== NULL && !is_scalar($value) && !$value instanceof \Stringable) {
      throw new BadRequestException(sprintf('Input value "%s" contains a non-scalar value.', $key));
    }

    return $value;
  }

  /**
   * Returns every parameter.
   *
   * @return array
   *   The parameters.
   */
  public function all() {
    return $this->parameters;
  }

}
