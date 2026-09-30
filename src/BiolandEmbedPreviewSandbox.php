<?php

namespace Drupal\bioland;

use Drupal\bioland\Service\BiolandEmbedAllowlist;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Frames Drupal-side embed previews the way the head does (BL-1218).
 *
 * The iframe module's formatters emit no sandbox and delegate camera,
 * microphone, geolocation and payment, so media pages, the media library and
 * CKEditor previews would frame allowlisted pages more loosely than the
 * public site. This applies the matching allowlist entry's sandbox and the
 * head's allow policy (bioland-head app/utils/html.js applyEmbedEntry) to
 * every matched iframe. An unmatched iframe is left as is, since that markup
 * also feeds the head, which drops it; only the CKEditor preview replaces it
 * with a notice, and that output varies by route.
 */
final class BiolandEmbedPreviewSandbox implements TrustedCallbackInterface {

  /**
   * Hosts that get the player permission policy (html.js mediaPlayerHost).
   */
  public const MEDIA_PLAYER_HOST = '/^(?:(?:www\.)?youtube(?:-nocookie)?\.com|player\.vimeo\.com)$/';

  /**
   * The CKEditor 5 media preview route, the only place a notice replaces an
   * unmatched iframe.
   */
  public const EDITOR_PREVIEW_ROUTE = 'media.filter.preview';

  /**
   * The head's permission policy for the players above.
   */
  public const PLAYER_ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;';

  /**
   * Sandboxes one matched '#theme' => 'iframe' element.
   *
   * An unmatched one comes back unchanged, or as a notice when
   * $editor_preview is set; either way it carries the route cache context.
   */
  public static function apply(array $element, array $entries, bool $editor_preview = FALSE): array {
    $src = (string) ($element['#src'] ?? '');
    $entry = BiolandEmbedAllowlist::findEntry($src, $entries);
    if ($entry === NULL) {
      $cache = ['tags' => ['config:bioland.settings'], 'contexts' => ['route']];
      if (!$editor_preview) {
        $element['#cache'] = array_merge_recursive($element['#cache'] ?? [], $cache);
        return $element;
      }
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t('This embed is not shown: its URL is not on the site embed allowlist.'),
        '#attributes' => ['class' => ['bioland-embed-blocked']],
        '#cache' => $cache,
      ];
    }

    $attributes = $element['#attributes'] ?? [];
    $attributes = is_object($attributes) && method_exists($attributes, 'toArray') ? $attributes->toArray() : (array) $attributes;
    $fullscreen = isset($attributes['allowfullscreen']) || str_contains(strtolower((string) ($attributes['allow'] ?? '')), 'fullscreen');
    $existing = $attributes['sandbox'] ?? NULL;
    unset($attributes['allow'], $attributes['allowfullscreen'], $attributes['sandbox']);

    // A configured sandbox always wins; without one the element's own is
    // filtered the same way, and '' stays fully restricted.
    $sandbox = BiolandEmbedAllowlist::frameSandbox($entry);
    if ($sandbox === NULL && $existing !== NULL) {
      $sandbox = BiolandEmbedAllowlist::frameSandbox(['sandbox' => (string) $existing]) ?? '';
    }
    if ($sandbox !== NULL) {
      $attributes['sandbox'] = $sandbox;
    }

    if (preg_match(self::MEDIA_PLAYER_HOST, strtolower((string) parse_url($src, PHP_URL_HOST)))) {
      $attributes['allow'] = self::PLAYER_ALLOW;
      $fullscreen = TRUE;
    }
    if ($fullscreen) {
      $attributes['allowfullscreen'] = '';
    }

    $element['#attributes'] = $attributes;
    $element['#cache']['tags'][] = 'config:bioland.settings';
    // The iframe template hard-codes "Your browser does not support iframes ...
    // <a>" inside the tag. DOMPurify in the head deletes any element whose
    // text looks like markup, so the whole frame is lost (BL-1270). Strip it
    // after rendering; #text is the title heading and must stay.
    $element['#post_render'][] = [self::class, 'stripIframeFallback'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['stripIframeFallback'];
  }

  /**
   * Empties the <iframe> element, leaving the title, style and attributes.
   *
   * A #post_render callback: receives the rendered markup and the element.
   */
  public static function stripIframeFallback(mixed $children, array $element): Markup {
    $stripped = preg_replace('#(<iframe\b[^>]*>).*?(</iframe\s*>)#is', '$1$2', (string) $children);
    return Markup::create($stripped ?? (string) $children);
  }

}
