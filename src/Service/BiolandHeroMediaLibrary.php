<?php

namespace Drupal\bioland\Service;

use Drupal\bioland\MediaLibrary\BiolandHeroImageOpener;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;

/**
 * "Choose from media library" for the hero media image field (BL-807).
 *
 * Adds, at the top of the hero's field_media_image image box, a link opening
 * the core Media Library modal (image media only, one slot) plus the hidden input
 * and hidden AJAX button BiolandHeroImageOpener fills and fires. The button
 * copies the chosen media's source file (and its alt and title text, where the
 * hero's are empty) into the widget state and rebuilds, so the preview updates
 * before the editor saves. The field itself stays a plain image field. Only a
 * published image whose file is in public:// can be chosen, so the hero never
 * republishes a restricted or private image.
 *
 * bioland.module only calls alterWidget() when media_library is enabled, so
 * nothing here loads a media_library class on a site without it.
 */
class BiolandHeroMediaLibrary implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * The image field on the hero bundle.
   */
  public const FIELD_NAME = 'field_media_image';

  /**
   * Opener service id, as media_library resolves it.
   */
  public const OPENER_ID = 'bioland.opener.hero_image';

  /**
   * Media types the library may offer.
   */
  public const ALLOWED_TYPES = ['image'];

  /**
   * Value tying the opener's AJAX commands to the hidden controls.
   *
   * WIDGET_ID, INPUT_KEY and WRAPPER_ID are prefixes: each form instance adds
   * the suffix idSuffix() derives from its #field_parents, as core's
   * MediaLibraryWidget does, so two hero forms on one page never collide.
   */
  public const WIDGET_ID = 'bioland-hero-image';

  /**
   * Top-level form value key for the hidden controls.
   */
  public const INPUT_KEY = 'bioland_hero_media_library';

  /**
   * Id of the element the AJAX refresh replaces.
   */
  public const WRAPPER_ID = 'bioland-hero-media-image-wrapper';

  /**
   * The only file scheme a hero image may be copied from.
   */
  private const PUBLIC_SCHEME = 'public://';

  /**
   * Form state key carrying the resolved item from validate to submit.
   */
  private const STATE_KEY = 'bioland_hero_media_item';

  /**
   * Constructs the service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {
  }

  /**
   * Adds the picker to the hero field_media_image complete widget form.
   *
   * @param array $element
   *   The complete widget form (hook_field_widget_complete_form_alter()).
   * @param array $context
   *   The hook context; 'items' is the field item list.
   */
  public function alterWidget(array &$element, array $context): void {
    $items = $context['items'] ?? NULL;
    if (!empty($context['default']) || !$items || $items->getFieldDefinition()->getName() !== self::FIELD_NAME) {
      return;
    }
    $entity = $items->getEntity();
    if ($entity->getEntityTypeId() !== 'media' || $entity->bundle() !== BiolandHeroImageOpener::HERO_BUNDLE
      || !$this->currentUser->hasPermission('view media')) {
      return;
    }

    $field_parents = $element['widget']['#field_parents'] ?? [];
    $suffix = self::idSuffix($field_parents);
    $widget_id = self::WIDGET_ID . $suffix;
    $input_key = self::INPUT_KEY . str_replace('-', '_', $suffix);
    $wrapper_id = self::WRAPPER_ID . $suffix;

    // Only what MediaLibraryState::fromRequest() rebuilds from the query
    // string hashes the same: every value a string, and no NULL (which
    // http_build_query() drops), as core's MediaLibraryWidget does.
    $opener_parameters = ['widget_id' => $widget_id];
    if (!$entity->isNew()) {
      $opener_parameters['entity_id'] = (string) $entity->id();
    }
    $state = MediaLibraryState::create(self::OPENER_ID, self::ALLOWED_TYPES, self::ALLOWED_TYPES[0], 1, $opener_parameters);

    $open = [
      '#type' => 'link',
      '#title' => $this->t('Choose from media library'),
      '#url' => Url::fromRoute('media_library.ui', [], ['query' => $state->all()]),
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--small'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(MediaLibraryUiBuilder::dialogOptions()),
      ],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];

    $element['#prefix'] = '<div id="' . $wrapper_id . '">' . ($element['#prefix'] ?? '');
    $element['#suffix'] = ($element['#suffix'] ?? '') . '</div>';
    $element['bioland_media_library'] = [
      '#type' => 'container',
      '#weight' => 10,
      'selection' => [
        '#type' => 'hidden',
        '#parents' => [$input_key, 'selection'],
        '#attributes' => ['data-bioland-hero-media-value' => $widget_id],
      ],
      'update' => [
        '#type' => 'submit',
        '#value' => $this->t('Use selected image'),
        '#name' => 'bioland-hero-media-library-update' . $suffix,
        '#parents' => [$input_key, 'update'],
        '#attributes' => ['data-bioland-hero-media-update' => $widget_id, 'class' => ['js-hide']],
        '#limit_validation_errors' => [[$input_key]],
        '#validate' => [[static::class, 'validateSelection']],
        '#submit' => [[static::class, 'submitSelection']],
        '#ajax' => ['callback' => [static::class, 'ajaxRefresh'], 'wrapper' => $wrapper_id],
        '#bioland_field_parents' => $field_parents,
        '#bioland_input_key' => $input_key,
      ],
    ];

    // The link goes inside the image box, under its title and above "Add a
    // new file". Appending to the upload element's #process keeps its
    // element-info #process and #pre_render defaults; without that element
    // the link stays with the hidden controls.
    if (isset($element['widget'][0]['#process'])) {
      $element['widget'][0]['#bioland_media_library_open'] = $open;
      $element['widget'][0]['#process'][] = [static::class, 'processPicker'];
    }
    else {
      $element['bioland_media_library']['open'] = $open;
    }
  }

  /**
   * #process for the upload element: queue preRenderPicker() last.
   *
   * By now the element-info defaults are merged in, so appending runs after
   * the admin theme's own pre-render (Claro's adds the image box there).
   */
  public static function processPicker(array $element): array {
    $element['#pre_render'][] = [static::class, 'preRenderPicker'];

    return $element;
  }

  /**
   * #pre_render: put the link, and any AJAX messages, at the top of the box.
   *
   * Claro wraps a single image widget in a details element at render time
   * and leaves its description empty; that slot renders between the title
   * and "Add a new file". Other themes get the link (then the messages) as
   * the first children. ajaxRefresh() sets #bioland_media_library_messages
   * on this same element when the link lives here, so the status messages
   * from the AJAX rebuild land next to it instead of in the container below.
   */
  public static function preRenderPicker(array $element): array {
    $open = $element['#bioland_media_library_open'] ?? NULL;
    if (!$open) {
      return $element;
    }
    $messages = $element['#bioland_media_library_messages'] ?? NULL;
    if (isset($element['#theme_wrappers']['details'])) {
      $element['#theme_wrappers']['details']['#description'] = $messages ? ['open' => $open, 'messages' => $messages] : $open;
    }
    else {
      $element['bioland_media_library_open'] = $open + ['#weight' => -100];
      if ($messages) {
        $element['bioland_media_library_messages'] = $messages + ['#weight' => -99];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderPicker'];
  }

  /**
   * Suffix unique to one widget instance, like core MediaLibraryWidget's.
   *
   * Lowercased and reduced to [a-z0-9_-] so it stays a valid id, form key and
   * the widget_id BiolandHeroImageOpener accepts.
   */
  private static function idSuffix(array $field_parents): string {
    return $field_parents ? '-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower(implode('-', $field_parents))) : '';
  }

  /**
   * Resolves a Media Library selection into a field_media_image item.
   *
   * Beyond view access, the media must be published and its file must live in
   * public://: the hero is public, so a restricted or private image chosen
   * here would otherwise become public through it.
   *
   * @param string $selection
   *   Comma-separated media ids posted by the hidden input.
   * @param string $current_alt
   *   The hero's current alt text; kept when not empty.
   * @param string $current_title
   *   The hero's current title text; kept when not empty.
   *
   * @return array
   *   [] for an empty selection, ['error' => string] for a rejected one, or
   *   ['item' => array] holding the widget item values.
   */
  public function resolveSelection(string $selection, string $current_alt, string $current_title): array {
    $id = trim(explode(',', $selection)[0]);
    if ($id === '') {
      return [];
    }

    $media = ctype_digit($id) ? $this->entityTypeManager->getStorage('media')->load($id) : NULL;
    if (!$media || !in_array($media->bundle(), self::ALLOWED_TYPES, TRUE) || !$media->isPublished()
      || !$media->access('view', $this->currentUser)) {
      return ['error' => $this->t('The selected media item is not an available image.')];
    }

    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? '';
    $value = $source_field !== '' ? ($media->get($source_field)->getValue()[0] ?? []) : [];
    $file = empty($value['target_id']) ? NULL : $this->entityTypeManager->getStorage('file')->load($value['target_id']);
    if (!$file) {
      return ['error' => $this->t('The selected media item has no image file.')];
    }
    if (!str_starts_with((string) $file->getFileUri(), self::PUBLIC_SCHEME)) {
      return ['error' => $this->t('The selected image is not public, so it cannot be used as a hero image.')];
    }

    return [
      'item' => [
        'target_id' => $value['target_id'],
        'fids' => [$value['target_id']],
        'alt' => $current_alt !== '' ? $current_alt : (string) ($value['alt'] ?? ''),
        'title' => $current_title !== '' ? $current_title : (string) ($value['title'] ?? ''),
        'width' => $value['width'] ?? NULL,
        'height' => $value['height'] ?? NULL,
      ],
    ];
  }

  /**
   * #validate for the hidden update button.
   */
  public static function validateSelection(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#bioland_field_parents'] ?? [];
    $input_key = $trigger['#bioland_input_key'] ?? self::INPUT_KEY;
    $input = $form_state->getUserInput();
    $current = static fn (string $key): string => trim((string) NestedArray::getValue($input, array_merge($parents, [self::FIELD_NAME, 0, $key])));
    $result = \Drupal::service('bioland.hero_media_library')
      ->resolveSelection((string) $form_state->getValue([$input_key, 'selection'], ''), $current('alt'), $current('title'));

    if (isset($result['error'])) {
      $form_state->setErrorByName($input_key . '][selection', $result['error']);
    }
    $form_state->set(self::STATE_KEY, $result['item'] ?? NULL);
  }

  /**
   * #submit for the hidden update button: load the item into the widget.
   *
   * Same technique as core's FileWidget::submit(): the widget state items are
   * what FileWidget renders on rebuild, and the stale input is dropped so it
   * cannot override them.
   */
  public static function submitSelection(array &$form, FormStateInterface $form_state): void {
    $item = $form_state->get(self::STATE_KEY);
    if ($item) {
      $trigger = $form_state->getTriggeringElement();
      $parents = $trigger['#bioland_field_parents'] ?? [];
      $field_state = WidgetBase::getWidgetState($parents, self::FIELD_NAME, $form_state);
      $field_state['items'] = [$item];
      WidgetBase::setWidgetState($parents, self::FIELD_NAME, $form_state, $field_state);

      $input = $form_state->getUserInput();
      NestedArray::unsetValue($input, array_merge($parents, [self::FIELD_NAME]));
      unset($input[$trigger['#bioland_input_key'] ?? self::INPUT_KEY]);
      $form_state->setUserInput($input);
    }
    $form_state->setRebuild();
  }

  /**
   * AJAX callback: re-render the whole field so the preview shows the image.
   *
   * When the "Choose from media library" link moved onto the upload element
   * (see alterWidget()), the messages go there too, so preRenderPicker() can
   * render them next to the link instead of in the container below the box.
   * Otherwise they stay in the container, as before.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    $element = NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -2));
    $messages = ['#type' => 'status_messages'];
    if (isset($element['widget'][0]['#bioland_media_library_open'])) {
      $element['widget'][0]['#bioland_media_library_messages'] = $messages;
    }
    else {
      $element['bioland_media_library']['messages'] = $messages + ['#weight' => -10];
    }

    return $element;
  }

}
