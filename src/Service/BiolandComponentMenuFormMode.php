<?php

namespace Drupal\bioland\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\bioland\BiolandThemeContract;

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
   * Form array key of the per-option description container.
   */
  public const DESCRIPTIONS_ELEMENT = 'bioland_component_descriptions';

  /**
   * Form array key of the content-type sub-select, and its submitted value.
   *
   * Shown (and required) only while the picker selects the Content Type
   * Listing component; its value becomes the "bl2-content-type-<slug>"
   * binding class the frontend reads.
   */
  public const CONTENT_TYPE_ELEMENT = 'bioland_component_content_type';

  /**
   * Form array key of the thumbnails checkbox, and its submitted value.
   */
  public const THUMBS_ELEMENT = 'bioland_component_thumbs';

  /**
   * Form array key of the title-arrow checkbox, and its submitted value.
   *
   * bioland-head header.vue reads the arrow class off every section's link,
   * but the checkbox is offered only while the picker selects the Content
   * Type Listing component (a product scoping, not a frontend limit); other
   * components' stored arrow tokens are preserved untouched on save.
   */
  public const ARROW_ELEMENT = 'bioland_component_arrow';

  /**
   * Form array key of the column-width select, and its submitted value.
   */
  public const WIDTH_ELEMENT = 'bioland_component_width';

  /**
   * Form array key of the max-rows-per-column select, and its submitted value.
   *
   * Shown only for the Content Type component — the only Vue component that
   * reads the "bl2-ct-max-row-per-column-<n>" token.
   */
  public const MAX_ROWS_ELEMENT = 'bioland_component_max_rows';

  /**
   * The largest rows-per-column cap the form offers.
   */
  public const MAX_ROWS_LIMIT = 12;

  /**
   * Form state key holding the stored binding slugs, for change detection.
   *
   * The entity builder rewrites bindings only when the editor actually picked
   * a different content type; an unchanged save must leave a stored
   * multi-binding link (the frontend accepts several) byte-identical.
   */
  public const STORED_BINDINGS_KEY = 'bioland_component_stored_bindings';

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
   * The entity type manager, read for the content-type terms.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * The language manager, for translated content-type labels.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|null
   */
  protected $languageManager;

  /**
   * Constructs the Component-menu form mode service.
   *
   * @param \Drupal\bioland\Service\BiolandComponentRegistry $registry
   *   The component registry — the only source of component-token rules.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager. Nullable only so the pure token/save rules stay
   *   unit-testable without a container; the wired service always has it, and
   *   without it the content-type sub-select is simply not offered.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $language_manager
   *   The language manager, same contract.
   */
  public function __construct(BiolandComponentRegistry $registry, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user, ?EntityTypeManagerInterface $entity_type_manager = NULL, ?LanguageManagerInterface $language_manager = NULL) {
    $this->registry = $registry;
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
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
    $form_state->set(self::STORED_BINDINGS_KEY, $this->registry->findContentTypeBindings($stored_class));

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

    $form[self::CONTENT_TYPE_ELEMENT] = $this->contentTypeElement($stored_class, $form_state);

    // Presentation controls for the classes the frontend styles a section by
    // (bioland-head drop-down.vue and the per-component Vue files). Only the
    // Content Type and All Content Types components read the thumbnail token
    // off their own link, so the checkbox is gated to those; the column span
    // applies to every section.
    $form[self::THUMBS_ELEMENT] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show thumbnails'),
      '#description' => $this->t('Show a thumbnail image beside each entry.'),
      '#default_value' => $this->registry->hasThumbsToken($stored_class),
      '#weight' => -7,
      '#states' => [
        'visible' => $this->pickerAnyOf(array_intersect($this->registry->thumbsSupportingTokens(), array_keys($options))),
      ],
    ];

    // header.vue reads the arrow class off EVERY section's own link, but the
    // checkbox is offered on the Content Type Listing only (product call, not
    // a frontend limit): that is the section editors author by hand here, so
    // the control lives where the decision is made, and buildEntity() leaves
    // other components' stored arrow tokens strictly untouched. A link with
    // nothing stored yet starts checked: every hand-authored Content Type
    // section carries the arrow, so the default reproduces the house style
    // instead of quietly dropping it on links created through this form.
    // The preview glyph rides in #description rather than #field_suffix —
    // form-element.html.twig prints suffixes BEFORE a checkbox's after-title
    // label, which would put the arrow on the wrong side of the text.
    $is_new_link = (method_exists($entity, 'isNew') && $entity->isNew()) || $stored_token === '';
    $form[self::ARROW_ELEMENT] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Arrow'),
      '#description' => [
        'text' => ['#markup' => $this->t('Show an arrow after the section title.')],
        'preview' => $this->arrowPreview(),
      ],
      '#default_value' => $is_new_link ? TRUE : $this->registry->hasArrowToken($stored_class),
      '#weight' => -6,
      '#states' => [
        'visible' => $this->pickerAnyOf([$this->registry->canonicalToken(BiolandComponentRegistry::CONTENT_TYPE_SUFFIX)]),
      ],
    ];

    $form[self::WIDTH_ELEMENT] = [
      '#type' => 'select',
      '#title' => $this->t('Mega menu columns'),
      '#options' => $this->widthOptions(),
      '#default_value' => $this->registry->findWidthToken($stored_class),
      '#description' => $this->t('How many columns of the mega menu this section spans.'),
      '#weight' => -5,
    ];

    $max_rows_default = $this->registry->findMaxRowsValue($stored_class);
    $form[self::MAX_ROWS_ELEMENT] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum rows per column'),
      '#options' => ['' => $this->t('Site default')] + array_combine(
        array_map('strval', range(1, self::MAX_ROWS_LIMIT)),
        array_map('strval', range(1, self::MAX_ROWS_LIMIT))
      ),
      '#default_value' => ctype_digit($max_rows_default) && (int) $max_rows_default <= self::MAX_ROWS_LIMIT ? $max_rows_default : '',
      '#description' => $this->t('Maximum entries listed per column; the site default applies when unset.'),
      '#weight' => -4,
      '#states' => [
        'visible' => $this->pickerAnyOf([$this->registry->canonicalToken(BiolandComponentRegistry::CONTENT_TYPE_SUFFIX)]),
      ],
    ];

    // Show (or, hidden below, round-trip) only the classes the picker does
    // not own; the component token is merged back by the entity builder.
    if (isset($form['options']['attributes']['class'])) {
      $form['options']['attributes']['class']['#default_value'] = implode(
        ' ',
        $this->registry->stripComponentTokens($stored_class, $site_id)
      );
    }

    // A component link is a mega-menu heading, not a destination: every
    // existing one stores route:<nolink>. Prefill (never force — the editor
    // may still point it somewhere) the empty link field of a new link.
    if (method_exists($entity, 'isNew') && $entity->isNew()
      && isset($form['link']['widget'][0]['uri'])
      && empty($form['link']['widget'][0]['uri']['#default_value'])) {
      $form['link']['widget'][0]['uri']['#default_value'] = '<nolink>';
    }

    // The picker owns the component token, so by default the whole contrib
    // Attributes fieldset stays out of the editor's way; the "Show Attributes"
    // admin setting opts a site back in. Hidden via #access FALSE, never
    // unset: Form API still processes denied elements and submits their
    // #default_value, so menu_link_attributes' entity builder keeps rebuilding
    // link.options.attributes from the stored values and nothing is lost on
    // save. (The class default above was already stripped of the component
    // token, and the entity builder below merges the picked token back -
    // exactly as on a visible fieldset.)
    if (isset($form['options']['attributes']) && !$this->showAttributes()) {
      $form['options']['attributes']['#access'] = FALSE;
    }

    $form['#attributes']['class'][] = self::FORM_CLASS;
    $form['#attributes'][self::FORM_MODE_ATTRIBUTE] = self::OPERATION;
    $form['#attached']['library'][] = self::LIBRARY;

    // The form's shape depends on bioland.settings (showAttributes() above),
    // so saving that config must invalidate any cached copy of this form.
    $form['#cache']['tags'][] = 'config:bioland.settings';

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
    $merged = $this->mergeContentTypeBinding($merged, $token, $form_state);

    $content_type_token = $this->registry->canonicalToken(BiolandComponentRegistry::CONTENT_TYPE_SUFFIX);

    // A thumbnail token on a component whose Vue file never reads it is dead
    // weight; strip it on the way through, whatever the (states-hidden)
    // checkbox submitted.
    $thumbs = $this->registry->componentSupportsThumbs($token, $site_id)
      ? $this->submittedThumbs($form_state)
      : FALSE;
    // NOT the thumbs rule: header.vue reads the arrow class off EVERY
    // section's link, this form just only offers the checkbox on the Content
    // Type Listing. So a non-Content-Type save passes NULL — leave whatever
    // arrow token the link already carries strictly untouched — where FALSE
    // would silently strip a live arrow off e.g. a Forums link on an
    // unrelated edit.
    $arrow = $token === $content_type_token ? $this->submittedArrow($form_state) : NULL;
    $merged = $this->registry->mergeStyleTokens($merged, $thumbs, $this->submittedWidthToken($form_state), $arrow);

    // Same rule for the rows cap: only the Content Type component reads it.
    $max_rows = $token === $content_type_token ? $this->submittedMaxRows($form_state) : '';
    $merged = $this->registry->mergeMaxRows($merged, $max_rows);

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
   * Tells whether the Attributes fieldset stays visible in Component mode.
   *
   * Reads bioland.settings:component_menu_show_attributes, the "Show
   * Attributes" sub-setting on the admin settings form. Cast rather than
   * compared to TRUE: a settings.php override bypasses config schema casting,
   * so an int 1 must still count as opted in. An absent key (sites configured
   * before the setting existed) casts to FALSE and keeps the default hidden
   * state.
   *
   * @return bool
   *   TRUE when the site opted in to showing the raw Attributes fieldset.
   */
  public function showAttributes(): bool {
    return (bool) $this->configFactory->get('bioland.settings')->get('component_menu_show_attributes');
  }

  /**
   * Returns the site's primary brand colour as a "#rrggbb" string.
   *
   * Reads bioland.settings:theme.color.primary — the value BiolandThemeForm
   * writes — and re-validates its shape here rather than trusting it: the
   * colour is interpolated into an inline style attribute, and config can also
   * arrive from a settings.php override or a hand-edited export, neither of
   * which passes through that form's validator.
   *
   * Anything not exactly six hex digits falls back to this site's network
   * default, which is flavor-dependent: BSL orange, never bl2 blue, on a BSL
   * site. The pair is BiolandThemeContract's, the same one BiolandThemeForm's
   * colour pickers fall back to, so an unauthored site previews here exactly
   * what that tab would offer it.
   *
   * @return string
   *   A validated "#rrggbb" colour, never an arbitrary config string.
   */
  public function primaryColor(): string {
    $value = $this->configFactory->get('bioland.settings')->get('theme.color.primary');
    $value = is_string($value) ? trim($value) : '';

    if (preg_match('/^#[0-9A-Fa-f]{6}\z/', $value) === 1) {
      return $value;
    }

    return $this->isBsl()
      ? BiolandThemeContract::FALLBACK_PRIMARY_BSL
      : BiolandThemeContract::FALLBACK_PRIMARY_BL2;
  }

  /**
   * Builds the coloured arrow glyph shown beside the "Show Arrow" checkbox.
   *
   * A preview of what the frontend renders, not a label: it is a bare glyph
   * with no words, so it stays out of the translation catalogs and is hidden
   * from assistive technology, which reads the checkbox title instead.
   *
   * Best effort until the theme precedence leg lands: head still resolves its
   * colours from config.theme || config.runTime.theme (see the note on the
   * theme mapping in config/schema/bioland.schema.yml), so a site whose
   * primary comes from the DMSM runTime block previews this flavor's network
   * default here while the frontend renders the DMSM colour.
   *
   * @return array
   *   An html_tag render array.
   */
  protected function arrowPreview(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '→',
      '#attributes' => [
        'class' => [self::FORM_CLASS . '__arrow-preview'],
        'style' => 'color: ' . $this->primaryColor() . ';',
        'aria-hidden' => 'true',
      ],
    ];
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
   * Builds the per-option description shown under the picker.
   *
   * One container per offered component, each visible only while its option
   * is selected (#states on the picker), so the editor reads a single
   * sentence about the current choice instead of opening a collapsed list of
   * all of them — the earlier details element read as a second, redundant
   * "Mega-menu component" box. Rendered as escaped plain text (no markup, no
   * #allowed_tags widening); core select options cannot carry per-option
   * descriptions themselves.
   *
   * @param array $options
   *   The picker options, canonical token => label.
   *
   * @return array
   *   A render array; empty when no offered option has a description.
   */
  protected function descriptionsElement(array $options): array {
    $element = [
      '#type' => 'container',
      '#weight' => -9,
      '#attributes' => ['class' => [self::FORM_CLASS . '__descriptions']],
    ];

    $found = FALSE;
    foreach (array_keys($this->registry->getComponents()) as $suffix) {
      $token = $this->registry->canonicalToken($suffix);
      $description = $this->registry->getDescription($suffix);
      if (!isset($options[$token]) || $description === '') {
        continue;
      }
      $found = TRUE;
      $element[$suffix] = [
        '#type' => 'container',
        '#attributes' => ['class' => [self::FORM_CLASS . '__description']],
        '#states' => [
          'visible' => [
            ':input[name="' . self::PICKER_ELEMENT . '"]' => ['value' => $token],
          ],
        ],
        'text' => ['#plain_text' => $description],
      ];
    }

    return $found ? $element : [];
  }

  /**
   * Builds the content-type sub-select for the Content Type Listing component.
   *
   * Offered whenever that component is offered; visible and required (both by
   * #states, on the picker's value) only while it is the selected component.
   * The options are the same published content types the mega-menu settings
   * form configures under Content Type Menus, keyed by the frontend slug the
   * binding class carries.
   *
   * @param array|string $stored_class
   *   The entity's stored class value, read for the current binding.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   A render array; empty when no content types can be offered (the
   *   component itself then still saves, just unbound — matching a hand-typed
   *   component class before this form existed).
   */
  protected function contentTypeElement($stored_class, FormStateInterface $form_state): array {
    $options = $this->contentTypeOptions();
    if ($options === []) {
      return [];
    }

    $stored = $this->registry->findContentTypeBindings($stored_class);
    $default = $stored[0] ?? NULL;
    if ($default !== NULL && !isset($options[$default])) {
      // A binding whose term is gone (or unpublished) is never dropped or
      // swapped silently: offer the stored slug as the selected current value
      // so an unchanged save keeps it verbatim.
      $options = [$default => $this->t('Legacy: @class', ['@class' => $this->registry->contentTypeBindingToken($default)])] + $options;
    }

    $component_token = $this->registry->canonicalToken(BiolandComponentRegistry::CONTENT_TYPE_SUFFIX);

    return [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $options,
      '#default_value' => $default,
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('The content type this listing shows.'),
      '#weight' => -8,
      '#states' => [
        'visible' => [
          ':input[name="' . self::PICKER_ELEMENT . '"]' => ['value' => $component_token],
        ],
        'required' => [
          ':input[name="' . self::PICKER_ELEMENT . '"]' => ['value' => $component_token],
        ],
      ],
    ];
  }

  /**
   * Returns the offerable content types, frontend slug => translated label.
   *
   * The same source the mega-menu settings form lists under Content Type
   * Menus: published terms of the "tags" vocabulary. The slug is derived from
   * the term's untranslated name exactly as the frontend derives it, so the
   * binding class written here is the one the Content Type Listing component
   * looks up; the label prefers the plural form in the current language.
   *
   * @return array
   *   Slug => label, sorted by label; empty when the storage is unavailable
   *   (no container half-wired, no fatals on a broken vocabulary).
   */
  protected function contentTypeOptions(): array {
    if ($this->entityTypeManager === NULL) {
      return [];
    }

    $options = [];
    try {
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'tags', 'status' => 1]);

      $langcode = $this->languageManager !== NULL
        ? $this->languageManager->getCurrentLanguage()->getId()
        : NULL;

      foreach ($terms as $term) {
        $slug = $this->contentTypeSlug((string) $term->label());
        if ($slug === '') {
          continue;
        }
        $translated = ($langcode !== NULL && method_exists($term, 'hasTranslation') && $term->hasTranslation($langcode))
          ? $term->getTranslation($langcode)
          : $term;
        $label = (method_exists($translated, 'hasField') && $translated->hasField('field_plural') && !$translated->get('field_plural')->isEmpty())
          ? $translated->get('field_plural')->value
          : $translated->label();
        $options[$slug] = (string) $label;
      }
      asort($options);
    }
    catch (\Exception $e) {
      return [];
    }

    return $options;
  }

  /**
   * Derives the frontend content-type slug from a term's untranslated name.
   *
   * Kebab-case of the singular name — "Government Ministry or Institute"
   * becomes "government-ministry-or-institute" — matching the slugs in
   * BiolandMegaMenuForm::getContentTypeReference() and the classes existing
   * links already store.
   *
   * @param string $name
   *   The term's untranslated (default language) name.
   *
   * @return string
   *   The slug, or an empty string for a name with no usable characters.
   */
  protected function contentTypeSlug(string $name): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
  }

  /**
   * Applies the submitted content-type binding to a merged class value.
   *
   * Called with the value mergeComponentToken() produced, so the component
   * token is already in place. Three cases:
   *   - the saved component is not the Content Type Listing: every binding
   *     token is stripped — it can only ever have described a component the
   *     link no longer renders;
   *   - it is, and the editor picked a different content type than the first
   *     stored binding: the bindings are rewritten to that one slug;
   *   - it is, and the selection is unchanged (or nothing valid was
   *     submitted): the stored bindings pass through byte-identically, which
   *     is what keeps a hand-authored multi-binding link intact.
   *
   * @param array|string $classValue
   *   The class value with the component token already merged.
   * @param string $componentToken
   *   The component token that was merged.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state carrying the sub-select value and the stored bindings.
   *
   * @return array|string
   *   The class value with bindings applied, same shape as $classValue.
   */
  protected function mergeContentTypeBinding(array|string $classValue, string $componentToken, FormStateInterface $form_state): array|string {
    $content_type_token = $this->registry->canonicalToken(BiolandComponentRegistry::CONTENT_TYPE_SUFFIX);
    if ($componentToken !== $content_type_token) {
      return $this->registry->findContentTypeBindings($classValue) === []
        ? $classValue
        : $this->registry->mergeContentTypeBinding($classValue, '');
    }

    $slug = $this->submittedContentTypeSlug($form_state);
    if ($slug === NULL) {
      return $classValue;
    }

    $stored = $form_state->get(self::STORED_BINDINGS_KEY);
    $stored = is_array($stored) ? $stored : [];
    if (($stored[0] ?? NULL) === $slug) {
      return $classValue;
    }

    return $this->registry->mergeContentTypeBinding($classValue, $slug);
  }

  /**
   * Builds a #states visibility condition matching any of the given tokens.
   *
   * @param array $tokens
   *   Canonical component tokens; empty hides the element outright.
   *
   * @return array
   *   A #states condition list on the picker's value (single condition, or
   *   the OR-list form for several).
   */
  protected function pickerAnyOf(array $tokens): array {
    $tokens = array_values($tokens);
    $selector = ':input[name="' . self::PICKER_ELEMENT . '"]';
    if ($tokens === []) {
      return [$selector => ['value' => '']];
    }
    if (count($tokens) === 1) {
      return [$selector => ['value' => $tokens[0]]];
    }

    $conditions = [];
    foreach ($tokens as $index => $token) {
      if ($index > 0) {
        $conditions[] = 'or';
      }
      $conditions[] = [$selector => ['value' => $token]];
    }
    return $conditions;
  }

  /**
   * Builds the column-width options: the default plus every frontend token.
   *
   * @return array
   *   Token (or empty string) => translated label, narrowest first.
   */
  protected function widthOptions(): array {
    $options = ['' => $this->t('Default (1 column)')];
    foreach (BiolandComponentRegistry::WIDTH_TOKENS as $token) {
      // The multiplier is the digits before the "x" ("bl2-2x-xl" spans 2
      // columns) - never every digit in the token, which also carries the
      // "bl2" prefix.
      preg_match('/-(\d+)x/', $token, $matches);
      $count = (int) ($matches[1] ?? 1);
      $options[$token] = str_ends_with($token, '-xl')
        ? $this->t('@count columns (extra-large screens only)', ['@count' => $count])
        : $this->t('@count columns', ['@count' => $count]);
    }
    return $options;
  }

  /**
   * Returns the submitted thumbnails state, or NULL when it must be ignored.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool|null
   *   The checkbox state, or NULL when the element never rendered (leaving
   *   stored thumbnail tokens untouched).
   */
  protected function submittedThumbs(FormStateInterface $form_state): ?bool {
    $value = $form_state->getValue(self::THUMBS_ELEMENT);

    return is_scalar($value) ? (bool) $value : NULL;
  }

  /**
   * Returns the submitted arrow state, or NULL when it must be ignored.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool|null
   *   The checkbox state, or NULL when the element never rendered (leaving
   *   stored arrow tokens untouched).
   */
  protected function submittedArrow(FormStateInterface $form_state): ?bool {
    $value = $form_state->getValue(self::ARROW_ELEMENT);

    return is_scalar($value) ? (bool) $value : NULL;
  }

  /**
   * Returns the submitted width token, or NULL when it must be ignored.
   *
   * The same storage-side gate as submittedToken(): only a token the frontend
   * actually reads (or the empty default) may be written.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   A WIDTH_TOKENS entry, '' for the one-column default, or NULL to leave
   *   stored width tokens untouched.
   */
  protected function submittedWidthToken(FormStateInterface $form_state): ?string {
    $value = $form_state->getValue(self::WIDTH_ELEMENT);
    if (!is_scalar($value)) {
      return NULL;
    }

    $token = trim((string) $value);
    if ($token === '' || in_array($token, BiolandComponentRegistry::WIDTH_TOKENS, TRUE)) {
      return $token;
    }

    return NULL;
  }

  /**
   * Returns the submitted rows cap, or NULL when it must be ignored.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   Digits within the offered range, '' for the site default, or NULL when
   *   the element never rendered (leaving a stored cap untouched).
   */
  protected function submittedMaxRows(FormStateInterface $form_state): ?string {
    $value = $form_state->getValue(self::MAX_ROWS_ELEMENT);
    if (!is_scalar($value)) {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '' || (ctype_digit($value) && (int) $value >= 1 && (int) $value <= self::MAX_ROWS_LIMIT)) {
      return $value;
    }

    return NULL;
  }

  /**
   * Returns the slug the sub-select submitted, or NULL when it must be ignored.
   *
   * The same storage-side gate as submittedToken(): only a slug the site
   * offers, or the stored binding apply() chose to preserve, may become a
   * binding class.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The slug to bind, or NULL to leave stored bindings untouched.
   */
  protected function submittedContentTypeSlug(FormStateInterface $form_state): ?string {
    $value = $form_state->getValue(self::CONTENT_TYPE_ELEMENT);
    if (!is_scalar($value)) {
      return NULL;
    }

    $slug = trim((string) $value);
    if ($slug === '') {
      return NULL;
    }

    if (isset($this->contentTypeOptions()[$slug])) {
      return $slug;
    }

    $stored = $form_state->get(self::STORED_BINDINGS_KEY);

    return (is_array($stored) && in_array($slug, $stored, TRUE)) ? $slug : NULL;
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
