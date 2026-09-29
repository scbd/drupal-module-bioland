<?php

namespace Drupal\bioland;

/**
 * BL-1191: an editor-typed URL or its address failed the SSRF guard.
 *
 * Distinct from ordinary fetch failures so the lookup route can log refusals
 * (a probe signal) without logging every unreachable site.
 */
class BiolandUrlRefusedException extends \RuntimeException {

}
