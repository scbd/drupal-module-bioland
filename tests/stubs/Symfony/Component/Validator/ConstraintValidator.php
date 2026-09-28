<?php

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Stub base class for Symfony constraint validators.
 */
abstract class ConstraintValidator {

  /**
   * The execution context.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  protected $context;

  /**
   * Initializes the validator with an execution context.
   */
  public function initialize(ExecutionContextInterface $context): void {
    $this->context = $context;
  }

  /**
   * Validates a value against a constraint.
   */
  abstract public function validate(mixed $value, Constraint $constraint): void;

}
