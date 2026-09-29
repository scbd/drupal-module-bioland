<?php

namespace Symfony\Component\Validator;

/**
 * Stub of Symfony's ConstraintViolationInterface: only what the widget reads.
 */
interface ConstraintViolationInterface {

  /**
   * Returns the violation message.
   */
  public function getMessage(): string|\Stringable;

  /**
   * Returns the property path, e.g. "0.url".
   */
  public function getPropertyPath(): string;

}
