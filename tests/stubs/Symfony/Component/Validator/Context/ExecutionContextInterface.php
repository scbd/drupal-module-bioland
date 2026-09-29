<?php

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Stub interface for the validation execution context.
 */
interface ExecutionContextInterface {

  /**
   * Starts building a violation.
   */
  public function buildViolation(string $message, array $parameters = []): ConstraintViolationBuilderInterface;

}
