<?php

namespace Drupal\bioland\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Canonical vocabulary of Bioland mega-menu components.
 *
 * A "component" is a mega-menu section that the decoupled frontend renders with
 * a dedicated Vue component instead of a plain link list. Which component runs
 * is decided by a CSS class token stored on the menu link at
 * menu_link_content.link.options.attributes.class — storage owned by the
 * contrib module menu_link_attributes.
 *
 * This service owns the component list and every pure rule over it: which
 * tokens are component-shaped, which are known, and how a token is parsed out
 * of — or merged back into — a stored class value. It holds no state, reads no
 * configuration and has no side effects.
 *
 * A stored class value carries tokens with several unrelated jobs (child-menu
 * binding, content-type binding, layout flags, the "login" and "our-targets"
 * markers). Two token families belong to this service: the component token and
 * the "bl2-content-type-<slug>" binding token that tells the Content Type
 * Listing component what to list. Every other token must survive a merge
 * untouched, in its original order and spelling.
 *
 * SYNC CHECKLIST: self::COMPONENTS mirrors the components wired into the
 * frontend dispatcher at
 * bioland-head/app/components/page/header/mega-menu/custom/index.vue.
 * When that file gains or loses a component:
 *   1. update self::COMPONENTS below;
 *   2. update the pinning test in
 *      tests/Unit/Service/BiolandComponentRegistryTest.php;
 *   3. add or remove the label and description msgids in all 67
 *      translations/bioland.<langcode>.po catalogs;
 *   4. update TranslationCatalogIntegrityTest::REQUIRED_MSGIDS to match.
 * A stale token is not a fatal error at render time — the frontend resolves it
 * to a component that does not exist and emits an empty section, so the menu
 * degrades to a blank column rather than breaking.
 */
class BiolandComponentRegistry {

  use StringTranslationTrait;

  /**
   * Class prefix the component picker writes.
   *
   * The frontend also accepts two legacy spellings; see self::tokenPrefixes().
   */
  public const CANONICAL_PREFIX = 'bl2-component-';

  /**
   * Site-agnostic legacy class prefix, still read by the frontend.
   */
  public const LEGACY_PREFIX = 'mm-component-';

  /**
   * Infix appended to a runtime site identifier to build its class prefix.
   */
  private const SITE_PREFIX_INFIX = '-component-';

  /**
   * The component suffix that takes a content-type binding.
   *
   * The frontend's Content Type Listing reads its subject from a sibling
   * "bl2-content-type-<slug>" class on the same link (bioland-head
   * mega-menu/custom/content-type/index.vue getContentType()).
   */
  public const CONTENT_TYPE_SUFFIX = 'content-type';

  /**
   * Class prefix of a content-type binding token.
   *
   * Unlike component tokens this family has a single spelling — the frontend
   * matches exactly this prefix.
   */
  public const CONTENT_TYPE_BINDING_PREFIX = 'bl2-content-type-';

  /**
   * The thumbnail toggle token the picker writes.
   *
   * bioland-head drop-down.vue showThumbs() shows a thumbnail beside each
   * child link when the section carries this class (or the legacy spelling).
   */
  public const THUMBS_TOKEN = 'bl2-show-thumbs';

  /**
   * Legacy thumbnail spelling, still read by the frontend but never written.
   */
  public const LEGACY_THUMBS_TOKEN = 'mm-show-thumbs';

  /**
   * The column-width tokens, widest last.
   *
   * bioland-head drop-down.vue getSectionScaleClasses(): a bare token flexes
   * the section to that many columns at every breakpoint; the "-xl" variants
   * apply only on xl/xxl viewports. Absent any of these a section spans one
   * column.
   */
  public const WIDTH_TOKENS = [
    'bl2-2x',
    'bl2-3x',
    'bl2-4x',
    'bl2-2x-xl',
    'bl2-3x-xl',
    'bl2-4x-xl',
  ];

  /**
   * The mega-menu components, keyed by canonical class suffix.
   *
   * Each entry holds the untranslated English source strings plus the BSL
   * availability flag:
   *   - label: short picker label (msgid).
   *   - description: one editor-facing sentence, including the render
   *     condition (msgid).
   *   - bsl: TRUE when the component is offered on Biosafety Land (BSL) sites.
   *     The frontend gates nothing per flavour; this narrowing is a Drupal
   *     authoring decision only, mirroring the mega-menu settings form — which
   *     on a BSL site exposes ONLY the Content Type Menus section
   *     (BiolandMegaMenuForm returns early for every other section), so only
   *     the Content Type Listing component is offered there.
   *
   * Order is the picker's display order and is pinned by the unit test.
   */
  private const COMPONENTS = [
    'national-report' => [
      'label' => 'National Reports',
      'bsl' => FALSE,
      'description' => "List of national report links, in tabs by country; hidden when the country has no reports.",
    ],
    'national-report-six' => [
      'label' => 'National Report (6th)',
      'bsl' => FALSE,
      'description' => "Sixth national report links for the site country, plus the link's own children; hidden when empty.",
    ],
    'bch' => [
      'label' => 'BCH Records',
      'bsl' => FALSE,
      'description' => "Biosafety Clearing-House records for the country, such as laws and decisions; hidden when empty.",
    ],
    'absch' => [
      'label' => 'ABS-CH Records',
      'bsl' => FALSE,
      'description' => "Access and Benefit-sharing Clearing-House records, such as measures and permits; hidden when empty.",
    ],
    'focal-points' => [
      'label' => 'National Focal Points',
      'bsl' => FALSE,
      'description' => "List of national focal points, in tabs by country; hidden when empty.",
    ],
    'country-profiles' => [
      'label' => 'Country Profiles',
      'bsl' => FALSE,
      'description' => "Links to CBD country profile pages, in tabs by country; always shown.",
    ],
    'content-type' => [
      'label' => 'Content Type',
      'bsl' => TRUE,
      'description' => "Latest site content of the content types set on this link; hidden when there are no records.",
    ],
    'forums' => [
      'label' => 'Forums',
      'bsl' => FALSE,
      'description' => "Latest forum threads, plus the link's own children; hidden when empty.",
    ],
    'national-targets-7' => [
      'label' => 'National Targets (GBF 7)',
      'bsl' => FALSE,
      'description' => "National target cards for GBF target 7, in tabs by country; always shown.",
    ],
    'all-content-types' => [
      'label' => 'All Content Types',
      'bsl' => FALSE,
      'description' => "One link per content type that has records, plus the link's own children; always shown.",
    ],
  ];

  /**
   * Returns the whole component map.
   *
   * @return array
   *   Keyed by canonical class suffix; each value has 'label', 'bsl' and
   *   'description'. Label and description are the untranslated English
   *   source strings — use optionsFor() and getDescription() for UI output.
   */
  public function getComponents(): array {
    return self::COMPONENTS;
  }

  /**
   * Returns the picker options for a site flavour.
   *
   * @param bool $isBsl
   *   TRUE for a Biosafety Land (BSL) site, which is offered the narrowed
   *   subset; FALSE for a Bioland (CHM) site, which is offered every
   *   component.
   *
   * @return array
   *   Canonical class token => translated label, in the map's display order.
   */
  public function optionsFor(bool $isBsl): array {
    $options = [];
    foreach (self::COMPONENTS as $suffix => $component) {
      if (!$isBsl || $component['bsl']) {
        $options[$this->canonicalToken($suffix)] = (string) $this->t($component['label']);
      }
    }
    return $options;
  }

  /**
   * Returns the translated editor-facing description of a component.
   *
   * @param string $suffix
   *   Canonical class suffix, for example "bch".
   *
   * @return string
   *   The translated description, or an empty string when the suffix is not a
   *   known component (a stale token must not break the form).
   */
  public function getDescription(string $suffix): string {
    if (!isset(self::COMPONENTS[$suffix])) {
      return '';
    }
    return (string) $this->t(self::COMPONENTS[$suffix]['description']);
  }

  /**
   * Builds the canonical class token for a component suffix.
   *
   * @param string $suffix
   *   Canonical class suffix, for example "bch".
   *
   * @return string
   *   The canonical token, for example "bl2-component-bch". The picker only
   *   ever writes this spelling, never a legacy one.
   */
  public function canonicalToken(string $suffix): string {
    return self::CANONICAL_PREFIX . $suffix;
  }

  /**
   * Tells whether a class token is component-shaped.
   *
   * Component-shaped means it carries one of the three prefixes the frontend
   * accepts and a non-empty suffix. The suffix need not be a known component:
   * a token left behind by a removed component is still component-shaped, and
   * must still be stripped so a link never ends up with two component tokens.
   *
   * @param string $token
   *   A single class token.
   * @param string|null $siteId
   *   Optional runtime multisite identifier (drupalMultisiteIdentifier, for
   *   example "bsl"), enabling the "<siteId>-component-*" family. When NULL or
   *   empty only the canonical and legacy families match.
   *
   * @return bool
   *   TRUE when the token is component-shaped.
   */
  public function isComponentToken(string $token, ?string $siteId = NULL): bool {
    return $this->tokenSuffix($token, $siteId) !== NULL;
  }

  /**
   * Tells whether a class token names a component this module knows.
   *
   * @param string $token
   *   A single class token.
   * @param string|null $siteId
   *   Optional runtime multisite identifier; see isComponentToken().
   *
   * @return bool
   *   TRUE when the token is component-shaped and its suffix exists in the
   *   map. Legacy spellings of a known suffix count as known.
   */
  public function isKnownComponentToken(string $token, ?string $siteId = NULL): bool {
    $suffix = $this->tokenSuffix($token, $siteId);
    return $suffix !== NULL && isset(self::COMPONENTS[$suffix]);
  }

  /**
   * Normalizes a stored class value into a flat list of tokens.
   *
   * Handles both storage shapes: the menu_link_attributes shape (a one-element
   * array holding the raw space-separated string) and a true array of tokens.
   * A plain string is accepted too. Token spelling and order are preserved
   * exactly; only the whitespace between tokens is normalized away.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   *
   * @return array
   *   Zero-indexed list of non-empty class tokens, in stored order.
   */
  public function extractClasses(array|string $classValue): array {
    $parts = is_array($classValue) ? $classValue : [$classValue];
    $tokens = [];
    foreach ($parts as $part) {
      if (!is_scalar($part)) {
        continue;
      }
      foreach (preg_split('/\s+/', (string) $part, -1, PREG_SPLIT_NO_EMPTY) as $token) {
        $tokens[] = $token;
      }
    }
    return $tokens;
  }

  /**
   * Returns the component-shaped tokens of a stored class value.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   * @param string|null $siteId
   *   Optional runtime multisite identifier; see isComponentToken().
   *
   * @return array
   *   Zero-indexed list of component-shaped tokens, in stored order. The
   *   frontend uses the first one and ignores the rest.
   */
  public function findComponentTokens(array|string $classValue, ?string $siteId = NULL): array {
    $found = [];
    foreach ($this->extractClasses($classValue) as $token) {
      if ($this->isComponentToken($token, $siteId)) {
        $found[] = $token;
      }
    }
    return $found;
  }

  /**
   * Removes every component-shaped token from a stored class value.
   *
   * All three prefix families are removed, known or not, so merging can never
   * leave a link double-tokened. Every other token survives byte-identically,
   * in its original order.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   * @param string|null $siteId
   *   Optional runtime multisite identifier; see isComponentToken().
   *
   * @return array
   *   Zero-indexed list of the surviving tokens, in stored order.
   */
  public function stripComponentTokens(array|string $classValue, ?string $siteId = NULL): array {
    $kept = [];
    foreach ($this->extractClasses($classValue) as $token) {
      if (!$this->isComponentToken($token, $siteId)) {
        $kept[] = $token;
      }
    }
    return $kept;
  }

  /**
   * Merges a component token into a stored class value.
   *
   * Strips every component-shaped token, then appends the given one last. The
   * result keeps the shape it was given, so a value read from storage can be
   * written straight back:
   *   - a string returns a space-separated string;
   *   - an array of two or more tokens returns one token per element;
   *   - an array of one or zero elements is treated as the packed
   *     menu_link_attributes shape and returns a one-element array holding the
   *     space-separated string.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   * @param string $canonicalToken
   *   The token to store, normally from canonicalToken(). An empty or
   *   whitespace-only token appends nothing, which is how a component is
   *   cleared from a link.
   * @param string|null $siteId
   *   Optional runtime multisite identifier; see isComponentToken().
   *
   * @return array|string
   *   The merged class value, in the same shape as $classValue.
   */
  public function mergeComponentToken(array|string $classValue, string $canonicalToken, ?string $siteId = NULL): array|string {
    $tokens = $this->stripComponentTokens($classValue, $siteId);
    $canonicalToken = trim($canonicalToken);
    if ($canonicalToken !== '') {
      $tokens[] = $canonicalToken;
    }

    if (!is_array($classValue)) {
      return implode(' ', $tokens);
    }
    if (count($classValue) > 1) {
      return $tokens;
    }
    return [implode(' ', $tokens)];
  }

  /**
   * Builds the content-type binding token for a content-type slug.
   *
   * @param string $slug
   *   The frontend content-type slug, for example "news".
   *
   * @return string
   *   The binding token, for example "bl2-content-type-news".
   */
  public function contentTypeBindingToken(string $slug): string {
    return self::CONTENT_TYPE_BINDING_PREFIX . $slug;
  }

  /**
   * Tells whether a class token is a content-type binding.
   *
   * @param string $token
   *   A single class token.
   *
   * @return bool
   *   TRUE for "bl2-content-type-<non-empty slug>".
   */
  public function isContentTypeBindingToken(string $token): bool {
    return strpos($token, self::CONTENT_TYPE_BINDING_PREFIX) === 0
      && strlen($token) > strlen(self::CONTENT_TYPE_BINDING_PREFIX);
  }

  /**
   * Returns the content-type slugs bound by a stored class value.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   *
   * @return array
   *   Zero-indexed list of slugs, in stored order. The frontend accepts
   *   several bindings on one link; most links carry exactly one.
   */
  public function findContentTypeBindings(array|string $classValue): array {
    $slugs = [];
    foreach ($this->extractClasses($classValue) as $token) {
      if ($this->isContentTypeBindingToken($token)) {
        $slugs[] = substr($token, strlen(self::CONTENT_TYPE_BINDING_PREFIX));
      }
    }
    return $slugs;
  }

  /**
   * Replaces the content-type bindings of a stored class value.
   *
   * Strips every binding token, then appends one for the given slug (or none,
   * when the slug is empty — how bindings are cleared from a link whose
   * component no longer takes one). Every other token, the component token
   * included, survives byte-identically; the value keeps the shape it was
   * given under the same rules as mergeComponentToken().
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   * @param string $slug
   *   The frontend content-type slug to bind, or an empty string to unbind.
   *
   * @return array|string
   *   The merged class value, in the same shape as $classValue.
   */
  public function mergeContentTypeBinding(array|string $classValue, string $slug): array|string {
    $tokens = [];
    foreach ($this->extractClasses($classValue) as $token) {
      if (!$this->isContentTypeBindingToken($token)) {
        $tokens[] = $token;
      }
    }
    $slug = trim($slug);
    if ($slug !== '') {
      $tokens[] = $this->contentTypeBindingToken($slug);
    }

    if (!is_array($classValue)) {
      return implode(' ', $tokens);
    }
    if (count($classValue) > 1) {
      return $tokens;
    }
    return [implode(' ', $tokens)];
  }

  /**
   * Tells whether a stored class value shows thumbnails.
   *
   * Either spelling counts when reading; writing always uses THUMBS_TOKEN.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   *
   * @return bool
   *   TRUE when a thumbnail token is present.
   */
  public function hasThumbsToken(array|string $classValue): bool {
    $tokens = $this->extractClasses($classValue);

    return in_array(self::THUMBS_TOKEN, $tokens, TRUE) || in_array(self::LEGACY_THUMBS_TOKEN, $tokens, TRUE);
  }

  /**
   * Returns the stored column-width token, or an empty string for one column.
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   *
   * @return string
   *   The first width token found, in stored order.
   */
  public function findWidthToken(array|string $classValue): string {
    foreach ($this->extractClasses($classValue) as $token) {
      if (in_array($token, self::WIDTH_TOKENS, TRUE)) {
        return $token;
      }
    }
    return '';
  }

  /**
   * Replaces the style tokens (thumbnails, column width) of a class value.
   *
   * A NULL control leaves that family byte-identical — the caller had no
   * submitted value for it. A non-NULL control owns its family: existing
   * tokens (legacy spellings included) are stripped and the requested one
   * appended. Every other token survives verbatim; the value keeps the shape
   * it was given under the same rules as mergeComponentToken().
   *
   * @param array|string $classValue
   *   The raw value of options.attributes.class.
   * @param bool|null $thumbs
   *   TRUE to show thumbnails, FALSE to hide them, NULL to leave untouched.
   * @param string|null $widthToken
   *   A WIDTH_TOKENS entry, an empty string for the one-column default, or
   *   NULL to leave untouched.
   *
   * @return array|string
   *   The merged class value, in the same shape as $classValue.
   */
  public function mergeStyleTokens(array|string $classValue, ?bool $thumbs, ?string $widthToken): array|string {
    $tokens = [];
    foreach ($this->extractClasses($classValue) as $token) {
      if ($thumbs !== NULL && ($token === self::THUMBS_TOKEN || $token === self::LEGACY_THUMBS_TOKEN)) {
        continue;
      }
      if ($widthToken !== NULL && in_array($token, self::WIDTH_TOKENS, TRUE)) {
        continue;
      }
      $tokens[] = $token;
    }
    if ($widthToken !== NULL && $widthToken !== '' && in_array($widthToken, self::WIDTH_TOKENS, TRUE)) {
      $tokens[] = $widthToken;
    }
    if ($thumbs === TRUE) {
      $tokens[] = self::THUMBS_TOKEN;
    }

    if (!is_array($classValue)) {
      return implode(' ', $tokens);
    }
    if (count($classValue) > 1) {
      return $tokens;
    }
    return [implode(' ', $tokens)];
  }

  /**
   * Returns the component suffix of a token, or NULL when not component-shaped.
   *
   * @param string $token
   *   A single class token.
   * @param string|null $siteId
   *   Optional runtime multisite identifier; see isComponentToken().
   *
   * @return string|null
   *   The non-empty suffix, or NULL.
   */
  private function tokenSuffix(string $token, ?string $siteId = NULL): ?string {
    foreach ($this->tokenPrefixes($siteId) as $prefix) {
      if (strpos($token, $prefix) === 0) {
        $suffix = substr($token, strlen($prefix));
        return $suffix === '' ? NULL : $suffix;
      }
    }
    return NULL;
  }

  /**
   * Returns the class prefixes the frontend resolves to a component.
   *
   * Mirrors the three families accepted by
   * bioland-head mega-menu/drop-down.vue componentName().
   *
   * @param string|null $siteId
   *   Optional runtime multisite identifier.
   *
   * @return array
   *   The prefixes to test, canonical first.
   */
  private function tokenPrefixes(?string $siteId = NULL): array {
    $prefixes = [self::CANONICAL_PREFIX, self::LEGACY_PREFIX];
    $siteId = trim((string) $siteId);
    if ($siteId !== '') {
      $sitePrefix = $siteId . self::SITE_PREFIX_INFIX;
      if (!in_array($sitePrefix, $prefixes, TRUE)) {
        $prefixes[] = $sitePrefix;
      }
    }
    return $prefixes;
  }

}
