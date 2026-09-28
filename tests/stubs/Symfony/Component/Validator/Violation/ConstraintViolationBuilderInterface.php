<?php

namespace Symfony\Component\Validator\Violation;

/**
 * Stub interface for the constraint violation builder.
 */
interface ConstraintViolationBuilderInterface {

  /**
   * Sets a message parameter.
   */
  public function setParameter(string $key, string $value): static;

  /**
   * Sets the property path.
   */
  public function atPath(string $path): static;

  /**
   * Adds the violation to the context.
   */
  public function addViolation(): void;

}
