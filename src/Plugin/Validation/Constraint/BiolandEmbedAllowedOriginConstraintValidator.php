<?php

namespace Drupal\bioland\Plugin\Validation\Constraint;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates BiolandEmbedAllowedOriginConstraint on an iframe field item list.
 */
class BiolandEmbedAllowedOriginConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The Front End General settings form, where admins edit the allowlist.
   */
  public const SETTINGS_PATH = '/admin/config/bioland/settings/front-end/general';

  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    $entries = $this->configFactory->get('bioland.settings')->get('embed.allowed_origins');
    $entries = is_array($entries) ? $entries : [];
    foreach ($items ?? [] as $delta => $item) {
      $url = trim((string) ($item->url ?? ''));
      if ($url === '' || BiolandEmbedAllowlist::matches($url, $entries)) {
        continue;
      }
      $this->context->buildViolation($constraint->message)
        ->setParameter('%url', $url)
        ->setParameter('%hosts', self::hosts($entries))
        ->setParameter('@settings', self::SETTINGS_PATH)
        ->atPath($delta . '.url')
        ->addViolation();
    }
  }

  /**
   * Lists the distinct hosts of the valid entries, or "none".
   */
  public static function hosts(array $entries): string {
    $hosts = [];
    foreach ($entries as $entry) {
      $url = is_array($entry) ? BiolandEmbedAllowlist::normalizeUrl((string) ($entry['url'] ?? '')) : NULL;
      if ($url !== NULL) {
        $hosts[parse_url($url, PHP_URL_HOST)] = TRUE;
      }
    }
    return $hosts ? implode(', ', array_keys($hosts)) : 'none';
  }

}
