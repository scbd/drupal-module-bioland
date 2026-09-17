<?php

namespace GuzzleHttp\Exception;

/**
 * Stub for Guzzle's RequestException.
 *
 * The real exception's message carries the full request URI, which is what
 * makes the service's log sanitisation necessary; the stub keeps that shape
 * so the sanitisation is testable without pulling in Guzzle.
 */
class RequestException extends \RuntimeException {
}
