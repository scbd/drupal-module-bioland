<?php

namespace Drupal\Core\Routing\Access;

/**
 * Stub interface for route access checks.
 *
 * Core declares no method here on purpose: the access manager discovers the
 * check's arguments by reflection on access(). The stub therefore stays empty
 * so an implementation is free to declare whatever parameters it needs.
 */
interface AccessInterface {

}
