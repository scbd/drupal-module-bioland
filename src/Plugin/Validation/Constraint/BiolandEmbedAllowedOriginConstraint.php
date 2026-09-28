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
   * Shown when the URL matches no allowlist entry.
   *
   * @var string
   */
  public $message = 'The URL %url must be on an allowed host: %hosts. Site administrators can add a host under Front End General settings (@settings). The embedded site must also allow being framed: an X-Frame-Options or Content-Security-Policy frame-ancestors header that blocks this site leaves the embed blank.';

}
