<?php

namespace Symfony\Component\HttpFoundation\Exception;

/**
 * Test stub for Symfony's BadRequestException.
 *
 * The real class extends \UnexpectedValueException and implements
 * RequestExceptionInterface, which is what makes Symfony's HttpKernel turn it
 * into a 400 response. Only the parent class matters to a unit test - the
 * interface exists to route the exception, and nothing here routes anything.
 */
class BadRequestException extends \UnexpectedValueException {
}
