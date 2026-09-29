<?php

namespace Drupal\bioland\Plugin\Validation\Constraint;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use Drupal\bioland\Service\BiolandEmbedFrameability;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
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

  /**
   * Lets a user save an embed on an unlisted host, adding it to the list.
   */
  public const AUTO_ALLOW_PERMISSION = 'auto allow embed origins';

  public function __construct(protected ConfigFactoryInterface $configFactory, protected ?AccountInterface $currentUser = NULL, protected ?BiolandEmbedFrameability $frameability = NULL) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('current_user'), $container->get('bioland.embed_frameability'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $items, Constraint $constraint): void {
    $entries = self::entries($this->configFactory->get('bioland.settings'));
    $previous = $this->previousUrls($items);
    foreach ($items ?? [] as $delta => $item) {
      $url = (string) ($item->url ?? '');
      $violation = self::violation($url, self::entriesFor($url, $entries, $this->currentUser), $constraint);
      if ($violation === NULL) {
        // Allowlist passed: only now is the page fetched, and only for a URL
        // the embed did not already hold, so old embeds never re-fetch.
        $error = trim($url) === '' || in_array($url, $previous, TRUE) ? NULL : $this->frameability?->error($url);
        if ($error !== NULL) {
          $this->context->buildViolation((string) $error)->atPath($delta . '.url')->addViolation();
        }
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
   * The URLs the field held before this save (none for a new entity).
   */
  protected function previousUrls(mixed $items): array {
    $original = is_object($items) && method_exists($items, 'getEntity') ? ($items->getEntity()->original ?? NULL) : NULL;
    $urls = [];
    foreach ($original ? $original->get($items->getName()) : [] as $item) {
      $urls[] = (string) ($item->url ?? '');
    }
    return $urls;
  }

  /**
   * The allowlist entries stored in bioland.settings, or [].
   */
  public static function entries(?object $config): array {
    $entries = $config ? $config->get('embed.allowed_origins') : NULL;
    return is_array($entries) ? $entries : [];
  }

  /**
   * The entries a URL is checked against for an account.
   *
   * A user with AUTO_ALLOW_PERMISSION may save a URL that is not on the list;
   * _bioland_embed_auto_allow() adds it once the media is saved.
   */
  public static function entriesFor(string $url, array $entries, ?AccountInterface $account): array {
    if ($account === NULL || !$account->hasPermission(self::AUTO_ALLOW_PERMISSION)) {
      return $entries;
    }
    return BiolandEmbedAllowlist::withAutoEntry($url, $entries) ?? $entries;
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
