<?php

namespace Drupal\bioland\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires every embed URL to match bioland.settings embed.allowed_origins.
 *
 * Attached to the embed media type's source field by
 * bioland_entity_bundle_field_info_alter() (BL-1218). The head drops any
 * iframe outside the list, so without this an editor's embed saves fine and
 * renders as nothing.
 */
#[Constraint(
  id: 'BiolandEmbedAllowedOrigin',
  label: new TranslatableMarkup('Embed URL on the allowlist', [], ['context' => 'Validation'])
)]
class BiolandEmbedAllowedOriginConstraint extends SymfonyConstraint {

  public const PLUGIN_ID = 'BiolandEmbedAllowedOrigin';

  /**
   * Shown when no entry has the URL's scheme, host and port.
   *
   * @var string
   */
  public $message = 'The URL %url is not on an allowed embed host. It must start with one of: %allowed. Site administrators can add an entry under Front End General settings (@settings); the embedded site must also allow being framed (no X-Frame-Options or Content-Security-Policy frame-ancestors header blocking this site).';

  /**
   * Shown when the host is allowed but the path is not, or is malformed.
   *
   * @var string
   */
  public $pathMessage = 'The URL %url is on an allowed embed host but not under an allowed path, or it is malformed (encoded slashes, dot segments, backslashes or spaces). It must start with one of: %allowed.';

}
