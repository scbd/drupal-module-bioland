<?php

namespace Drupal\bioland\Plugin\Validation\Constraint;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    $entries = self::entries($this->configFactory->get('bioland.settings'));
    foreach ($items ?? [] as $delta => $item) {
      $violation = self::violation((string) ($item->url ?? ''), $entries, $constraint);
      if ($violation === NULL) {
        continue;
      }
      $builder = $this->context->buildViolation($violation[0]);
      foreach ($violation[1] as $key => $value) {
        $builder->setParameter($key, $value);
      }
      $builder->atPath($delta . '.url')->addViolation();
    }
  }

  /**
   * The allowlist entries stored in bioland.settings, or [].
   */
  public static function entries(?object $config): array {
    $entries = $config ? $config->get('embed.allowed_origins') : NULL;
    return is_array($entries) ? $entries : [];
  }

  /**
   * The message and parameters for a rejected URL, or NULL when it is fine.
   *
   * Shared by this validator and the media library add form, so both paths
   * say the same thing. An empty URL is left to the field's required check.
   *
   * @return array|null
   *   [message template, parameters] or NULL.
   */
  public static function violation(string $url, array $entries, ?BiolandEmbedAllowedOriginConstraint $constraint = NULL): ?array {
    $url = trim($url);
    $status = $url === '' ? BiolandEmbedAllowlist::MATCH : BiolandEmbedAllowlist::classify($url, $entries);
    if ($status === BiolandEmbedAllowlist::MATCH) {
      return NULL;
    }
    $constraint ??= new BiolandEmbedAllowedOriginConstraint();
    $allowed = BiolandEmbedAllowlist::entryUrls($entries);
    return [
      $status === BiolandEmbedAllowlist::HOST_NOT_ALLOWED ? $constraint->message : $constraint->pathMessage,
      [
        '%url' => $url,
        '%allowed' => $allowed ? implode(', ', $allowed) : 'none',
        '@settings' => self::SETTINGS_PATH,
      ],
    ];
  }

  /**
   * The translated form error for a rejected URL, or NULL when it is fine.
   *
   * The literals repeat the constraint's two messages so they are extracted
   * and translated once for both paths; a test keeps them identical.
   */
  public static function formError(string $url, array $entries): ?TranslatableMarkup {
    $violation = self::violation($url, $entries);
    if ($violation === NULL) {
      return NULL;
    }
    return $violation[0] === (new BiolandEmbedAllowedOriginConstraint())->message
      ? new TranslatableMarkup('The URL %url is not on an allowed embed host. It must start with one of: %allowed. Site administrators can add an entry under Front End General settings (@settings); the embedded site must also allow being framed (no X-Frame-Options or Content-Security-Policy frame-ancestors header blocking this site).', $violation[1])
      : new TranslatableMarkup('The URL %url is on an allowed embed host but not under an allowed path, or it is malformed (encoded slashes, dot segments, backslashes or spaces). It must start with one of: %allowed.', $violation[1]);
  }

}
