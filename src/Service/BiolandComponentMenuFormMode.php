<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Turns a menu_link_content form into the Component-menu form.
 *
 * A "component menu link" is an ordinary menu_link_content entity that carries
 * a mega-menu component class token (see BiolandComponentRegistry). Component
 * mode replaces the hand-typed token with a picker, hides the token from the
 * raw class textfield, styles the form distinctly, and merges the picked token
 * back into storage on save.
 *
 * THE DISPATCHER. applies() is the single predicate that decides whether the
 * mode is on:
 *   - the "component" form operation does not exist yet (added in p03-01), but
 *   - self::EDIT_DETECTION is TRUE (activated by p03-02), so an existing link
 *     already carrying a component-shaped token now opens in Component mode
 *     on the core edit form.
 * A regular link, and any edit lacking the required permission or made on a
 * non-default translation, is still declined and left byte-identical.
 *
 * THE SAVE-PATH ORDERING CONTRACT (the reason this is a service called from
 * bioland_form_alter() and not a form class). menu_link_attributes' entity
 * builder (menu_link_attributes.module:135-200) rebuilds
 * link.options.attributes wholesale from ITS OWN form values on every save, so
 * a component token written before it runs is silently discarded. Two facts
 * make the merge safe:
 *   (a) bioland_module_implements_alter() (bioland.module) moves bioland's
 *       hook_form_alter last, after the contrib module's, so anything this
 *       service appends to $form['#entity_builders'] lands after the contrib
 *       module's entry;
 *   (b) #entity_builders run in array order.
 * Consequence: mode application must always flow through bioland_form_alter(),
 * never through a form class's form() method, which runs before all alters.
 * Pinned by BiolandComponentMenuFormModeTest.
 *
 * TRANSLATIONS. The component token is language-independent: the picker is
 * offered on the default translation only, and the merged options are copied
 * onto every translation on save, mirroring menu_link_attributes.module:192-199
 * and composing with the menu_parent hiding already in bioland_form_alter().
 */
class BiolandComponentMenuFormMode {

  use StringTranslationTrait;

  /**
   * Whether an existing component link opens in Component mode on edit.
   *
   * Activation seam: shipped FALSE by p02-01 (inert dispatcher, nothing
   * reachable); flipped TRUE by p03-02 to make edit detection live. Kept as a
   * constant rather than removed so it doubles as a single-line rollback kill
   * switch post-merge. Read through static:: so a test subclass can still
   * force either value in isolation.
   */
  public const EDIT_DETECTION = TRUE;

  /**
   * The entity-form operation that opts a form into Component mode.
   *
   * Registered by task p03-01's dedicated add route; absent until then.
   */
  public const OPERATION = 'component';

  /**
   * The entity type this mode applies to.
   */
  public const ENTITY_TYPE = 'menu_link_content';

  /**
   * The permission required to see and use the picker.
   *
   * The picker writes menu_link_attributes' own storage, so it reuses that
   * module's permission rather than adding one (plan decision #5).
   */
  public const PERMISSION = 'use menu link attributes';

  /**
   * Form array key of the picker element, and of its submitted value.
   */
  public const PICKER_ELEMENT = 'bioland_component';

  /**
   * Form array key of the mode's introduction element.
   */
  public const INTRO_ELEMENT = 'bioland_component_intro';

  /**
   * Form array key of the per-option description list.
   */
  public const DESCRIPTIONS_ELEMENT = 'bioland_component_descriptions';

  /**
   * #entity_builders key this service registers itself under.
   *
   * A named key (rather than an appended numeric one) keeps registration
   * idempotent across form rebuilds without changing the array position.
   */
  public const ENTITY_BUILDER_KEY = 'bioland_component_menu';

  /**
   * The entity-builder callback registered on a Component-mode form.
   */
  public const ENTITY_BUILDER = self::class . '::entityBuilder';

  /**
   * Wrapper class marking a Component-mode form, and the CSS/library hook.
   */
  public const FORM_CLASS = 'bioland-component-menu-form';

  /**
   * Marker attribute set on a Component-mode form element.
   */
  public const FORM_MODE_ATTRIBUTE = 'data-bioland-form-mode';

  /**
   * The admin library this service owns and is the only place that attaches.
   */
  public const LIBRARY = 'bioland/component_menu_form';

  /**
   * Form state key holding a stored token the picker must preserve verbatim.
   */
  public const PRESERVED_TOKEN_KEY = 'bioland_component_preserved_token';

  /**
   * The component vocabulary and every token rule.
   *
   * @var \Drupal\bioland\Service\BiolandComponentRegistry
   */
  protected $registry;

  /**
   * The configuration factory, read for bioland.settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user, checked for self::PERMISSION.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the Component-menu form mode service.
   *
   * @param \Drupal\bioland\Service\BiolandComponentRegistry $registry
   *   The component registry — the only source of component-token rules.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(BiolandComponentRegistry $registry, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user) {
    $this->registry = $registry;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * Decides whether Component mode applies to a form.
   *
   * The single dispatcher; bioland_form_alter() calls nothing else. TRUE only
   * for a menu_link_content entity form whose editor may use menu link
   * attributes, on the default translation, and either
   *   - opened with the "component" operation (the dedicated add flow), or
   *   - an existing link already carrying a component-shaped token, when
   *     static::EDIT_DETECTION is on.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form being altered.
   *
   * @return bool
   *   TRUE when apply() should run.
   */
  public function applies(FormStateInterface $form_state): bool {
    $entity = $this->formEntity($form_state);
    if ($entity === NULL) {
      return FALSE;
    }

    if (!$this->currentUser->hasPermission(self::PERMISSION)) {
      return FALSE;
    }

    // The component token is language-independent and is copied onto every
    // translation on save, so it is managed from the source language only.
    // Strictly narrower than menu_link_attributes.module:30-32, which also
    // shows its fieldset on a non-default translation when translations are
    // affected independently; there, the contrib module round-trips the token
    // through its own textfield and this service stays out of the way.
    if (method_exists($entity, 'isDefaultTranslation') && !$entity->isDefaultTranslation()) {
      return FALSE;
    }

    $form_object = $form_state->getFormObject();
    if (method_exists($form_object, 'getOperation') && $form_object->getOperation() === self::OPERATION) {
      return TRUE;
    }

    if (!static::EDIT_DETECTION) {
      return FALSE;
    }

    if (method_exists($entity, 'isNew') && $entity->isNew()) {
      return FALSE;
    }

    return $this->registry->findComponentTokens($this->storedClassValue($entity), $this->siteId()) !== [];
  }

  /**
   * Applies Component mode to a form.
   *
   * Injects the picker (narrowed on BSL sites), preserves a legacy or
   * BSL-unavailable stored token as a selected current-value option, strips
   * component tokens from the raw class textfield so the picker is the only
   * writer, attaches this service's library, and registers the entity builder
   * that merges the picked token back on save.
   *
   * Only ever called for a form applies() accepted, so a declined form is left
   * byte-identical.
   *
   * @param array $form
   *   The form array, altered in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function apply(array &$form, FormStateInterface $form_state): void {
    $entity = $this->formEntity($form_state);
    if ($entity === NULL) {
      return;
    }

    $site_id = $this->siteId();
    $stored_class = $this->storedClassValue($entity);
    $stored_token = $this->registry->findComponentTokens($stored_class, $site_id)[0] ?? '';

    $options = $this->registry->optionsFor($this->isBsl());
    $default_value = NULL;
    $preserved_token = '';

    if ($stored_token !== '') {
      $canonical = $this->canonicalEquivalent($stored_token, $site_id);
      if ($canonical !== '' && isset($options[$canonical])) {
        // A known, offered component: a legacy spelling resolves to its
        // canonical entry, so an unchanged save normalizes the token.
        $default_value = $canonical;
      }
      else {
        // Either an unknown suffix (a component removed from the registry) or
        // one the BSL narrowing hides. Never drop or silently swap it: offer
        // it as the selected current value, keyed by the stored spelling, so
        // an unchanged save writes it back verbatim.
        $preserved_token = $stored_token;
        $default_value = $stored_token;
        $options = [$stored_token => $this->preservedOptionLabel($stored_token, $site_id)] + $options;
      }
    }

    // The builder reads this to tell an intentionally preserved token from an
    // arbitrary submitted value.
    $form_state->set(self::PRESERVED_TOKEN_KEY, $preserved_token);

    $form[self::INTRO_ELEMENT] = [
      '#type' => 'container',
      '#weight' => -20,
      '#attributes' => ['class' => [self::FORM_CLASS . '__intro']],
      'text' => [
        '#plain_text' => $this->t('This menu link renders a mega-menu component instead of a plain list of child links.'),
      ],
    ];

    // Plain select with plain string labels: option text is escaped by the
    // default Form API/Twig handling and never carries markup. Core cannot
    // render a per-option "disabled" attribute (select options take no
    // attributes), so a preserved token is marked in its label instead; it is
    // only ever present on the one link that already stores it.
    $form[self::PICKER_ELEMENT] = [
      '#type' => 'select',
      '#title' => $this->t('Mega-menu component'),
      '#options' => $options,
      '#default_value' => $default_value,
      '#description' => $this->t('The component this menu link renders in the mega menu.'),
      '#required' => TRUE,
      '#weight' => -10,
    ];

    $form[self::DESCRIPTIONS_ELEMENT] = $this->descriptionsElement($options);

    // Show the editor only the classes the picker does not own; the component
    // token is merged back by the entity builder. Skipped when the contrib
    // fieldset is absent (the editor lacks the permission, or the attribute is
    // not configured), in which case there is nothing to strip.
    if (isset($form['options']['attributes']['class'])) {
      $form['options']['attributes']['class']['#default_value'] = implode(
        ' ',
        $this->registry->stripComponentTokens($stored_class, $site_id)
      );
    }

    $form['#attributes']['class'][] = self::FORM_CLASS;
    $form['#attributes'][self::FORM_MODE_ATTRIBUTE] = self::OPERATION;
    $form['#attached']['library'][] = self::LIBRARY;

    // ORDERING PIN: appending here, from bioland_form_alter(), is what puts
    // this builder after menu_link_attributes' — see the class docblock. A
    // named key keeps a rebuild from registering it twice.
    $form['#entity_builders'][self::ENTITY_BUILDER_KEY] = self::ENTITY_BUILDER;
  }

  /**
   * Entity builder: merges the picked component token into link.options.
   *
   * Registered by apply() and therefore never reached on a form the dispatcher
   * declined. It MUST run after menu_link_attributes' builder, which has by
   * then rebuilt link.options.attributes from the (component-stripped) class
   * textfield — the token this writes back would otherwise be discarded. See
   * the class docblock for how that order is guaranteed.
   *
   * @param string $entity_type_id
   *   The entity type id, unused (the signature is Drupal's).
   * @param mixed $entity
   *   The menu link content entity being built.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function entityBuilder($entity_type_id, $entity, array &$form, FormStateInterface $form_state): void {
    $service = \Drupal::service('bioland.component_menu_form_mode');
    if ($service instanceof self) {
      $service->buildEntity($entity, $form_state);
    }
  }

  /**
   * Merges the picked component token into the entity's link options.
   *
   * The instance half of entityBuilder(), split out so the save path is unit
   * testable without a container. Preserves the stored class value's shape,
   * leaves every non-component token byte-identical, and copies the result
   * onto every translation exactly as menu_link_attributes.module:192-199
   * does.
   *
   * @param mixed $entity
   *   The menu link content entity being built.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state carrying the submitted picker value.
   */
  public function buildEntity($entity, FormStateInterface $form_state): void {
    $item = $this->linkItem($entity);
    if ($item === NULL) {
      return;
    }

    $token = $this->submittedToken($form_state);
    if ($token === NULL) {
      return;
    }

    $site_id = $this->siteId();
    $options = is_array($item->options) ? $item->options : [];
    $class = $options['attributes']['class'] ?? [];
    $merged = $this->registry->mergeComponentToken($class, $token, $site_id);

    if ($this->registry->extractClasses($merged) === []) {
      unset($options['attributes']['class']);
      if (isset($options['attributes']) && $options['attributes'] === []) {
        unset($options['attributes']);
      }
    }
    else {
      $options['attributes']['class'] = $merged;
    }

    $item->options = $options;
    $this->copyOptionsToTranslations($entity, $options);
  }

  /**
   * Tells whether this site is a Biosafety Land (BSL) site.
   *
   * @return bool
   *   TRUE on a BSL site, which is offered the narrowed component list.
   */
  public function isBsl(): bool {
    return (bool) $this->configFactory->get('bioland.settings')->get('is_biosafety_land');
  }

  /**
   * Returns the runtime multisite identifier for token matching.
   *
   * Derived from the site flavour, the only multisite signal this module
   * stores (bioland.settings:is_biosafety_land, written from the DMSM
   * multiSiteCode). It enables the "<siteId>-component-*" token family, so a
   * BSL site also recognises "bsl-component-*". On a Bioland site the value
   * collides with the registry's canonical prefix, which the registry
   * deduplicates.
   *
   * @return string
   *   The site identifier, "bsl" or "bl2".
   */
  public function siteId(): string {
    return $this->isBsl() ? 'bsl' : 'bl2';
  }

  /**
   * Returns the entity of a menu_link_content entity form, or NULL.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed|null
   *   The entity, or NULL when the form is not a menu_link_content entity
   *   form.
   */
  protected function formEntity(FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    if (!is_object($form_object) || !method_exists($form_object, 'getEntity')) {
      return NULL;
    }

    $entity = $form_object->getEntity();
    if (!is_object($entity) || !method_exists($entity, 'getEntityTypeId')) {
      return NULL;
    }

    return $entity->getEntityTypeId() === self::ENTITY_TYPE ? $entity : NULL;
  }

  /**
   * Returns the entity's stored options.attributes.class value.
   *
   * @param mixed $entity
   *   The menu link content entity.
   *
   * @return array|string
   *   The raw stored value, in whichever shape it was stored; an empty array
   *   when the link has no class attribute.
   */
  protected function storedClassValue($entity) {
    $item = $this->linkItem($entity);
    $options = ($item !== NULL && is_array($item->options)) ? $item->options : [];
    $class = $options['attributes']['class'] ?? [];

    return (is_array($class) || is_string($class)) ? $class : [];
  }

  /**
   * Returns the entity's first link field item, or NULL.
   *
   * @param mixed $entity
   *   The menu link content entity.
   *
   * @return object|null
   *   The field item carrying the options array, or NULL when the link field
   *   is missing or empty.
   */
  protected function linkItem($entity) {
    if (!is_object($entity) || !isset($entity->link) || !is_object($entity->link)) {
      return NULL;
    }
    if (method_exists($entity->link, 'isEmpty') && $entity->link->isEmpty()) {
      return NULL;
    }

    $item = $entity->link->first();

    return is_object($item) ? $item : NULL;
  }

  /**
   * Returns the canonical token equivalent to a stored one, or an empty string.
   *
   * Resolves by construction, never by parsing: every spelling the registry's
   * public prefixes can produce for a known component is built and compared
   * for equality, so no token-shape rule lives outside the registry. A
   * site-prefixed spelling ("<siteId>-component-*", which the registry accepts
   * but cannot be rebuilt from its public API) therefore does not resolve, and
   * is preserved verbatim as a current-value option instead of being
   * normalized — safe either way, since the frontend reads all three families.
   *
   * @param string $token
   *   A stored component-shaped token, in any of the accepted spellings.
   * @param string $site_id
   *   The runtime multisite identifier.
   *
   * @return string
   *   The canonical token when the stored one is a canonical or legacy
   *   spelling of a known component, an empty string otherwise.
   */
  protected function canonicalEquivalent(string $token, string $site_id): string {
    if (!$this->registry->isKnownComponentToken($token, $site_id)) {
      return '';
    }

    foreach (array_keys($this->registry->getComponents()) as $suffix) {
      $canonical = $this->registry->canonicalToken($suffix);
      if ($token === $canonical || $token === BiolandComponentRegistry::LEGACY_PREFIX . $suffix) {
        return $canonical;
      }
    }

    return '';
  }

  /**
   * Builds the label of a preserved current-value option.
   *
   * @param string $token
   *   The stored token being preserved.
   * @param string $site_id
   *   The runtime multisite identifier.
   *
   * @return string
   *   "<label> (not available on this site)" for a known component the BSL
   *   narrowing hides, "Legacy: <token>" otherwise. Plain text either way —
   *   never markup.
   */
  protected function preservedOptionLabel(string $token, string $site_id): string {
    $canonical = $this->canonicalEquivalent($token, $site_id);
    $every_component = $this->registry->optionsFor(FALSE);
    if ($canonical !== '' && isset($every_component[$canonical])) {
      return (string) $this->t('@label (not available on this site)', ['@label' => $every_component[$canonical]]);
    }

    return (string) $this->t('Legacy: @class', ['@class' => $token]);
  }

  /**
   * Builds the per-option description list shown under the picker.
   *
   * Rendered as escaped plain text (no markup, no #allowed_tags widening);
   * core select options cannot carry per-option descriptions themselves.
   *
   * @param array $options
   *   The picker options, canonical token => label.
   *
   * @return array
   *   A render array; empty when no offered option has a description.
   */
  protected function descriptionsElement(array $options): array {
    $items = [];
    foreach (array_keys($this->registry->getComponents()) as $suffix) {
      $token = $this->registry->canonicalToken($suffix);
      $description = $this->registry->getDescription($suffix);
      if (!isset($options[$token]) || $description === '') {
        continue;
      }
      $items[] = [
        'label' => ['#plain_text' => $options[$token]],
        'separator' => ['#plain_text' => ': '],
        'description' => ['#plain_text' => $description],
      ];
    }

    if ($items === []) {
      return [];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Mega-menu component'),
      '#open' => FALSE,
      '#weight' => -9,
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Returns the token the picker submitted, or NULL when it must be ignored.
   *
   * Core already rejects a select value outside #options ("illegal choice"),
   * so this is a second, storage-side gate: only a canonical token the site
   * offers, or the exact token apply() chose to preserve, may be written into
   * a class attribute.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The token to merge, or NULL to leave stored options untouched.
   */
  protected function submittedToken(FormStateInterface $form_state): ?string {
    $value = $form_state->getValue(self::PICKER_ELEMENT);
    if (!is_scalar($value)) {
      return NULL;
    }

    $token = trim((string) $value);
    if ($token === '') {
      return NULL;
    }

    if (isset($this->registry->optionsFor($this->isBsl())[$token])) {
      return $token;
    }

    return $token === (string) $form_state->get(self::PRESERVED_TOKEN_KEY) ? $token : NULL;
  }

  /**
   * Copies the merged options onto every other translation.
   *
   * Mirrors menu_link_attributes.module:192-199 so the two writers agree: the
   * class set is language-independent and always follows the translation the
   * form was saved from.
   *
   * @param mixed $entity
   *   The menu link content entity.
   * @param array $options
   *   The merged link options.
   */
  protected function copyOptionsToTranslations($entity, array $options): void {
    if (!method_exists($entity, 'getTranslationLanguages') || !method_exists($entity, 'getTranslation')) {
      return;
    }
    if (method_exists($entity, 'isDefaultTranslation') && !$entity->isDefaultTranslation()
      && method_exists($entity, 'isDefaultTranslationAffectedOnly') && $entity->isDefaultTranslationAffectedOnly()) {
      return;
    }

    $langcode = method_exists($entity, 'language') ? $entity->language()->getId() : NULL;
    foreach ($entity->getTranslationLanguages() as $language) {
      if ($language->getId() === $langcode) {
        continue;
      }
      $item = $this->linkItem($entity->getTranslation($language->getId()));
      if ($item !== NULL) {
        $item->options = $options;
      }
    }
  }

}
