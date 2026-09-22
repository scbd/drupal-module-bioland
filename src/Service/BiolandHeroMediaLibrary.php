<?php

namespace Drupal\bioland\Service;

use Drupal\bioland\MediaLibrary\BiolandHeroImageOpener;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;

/**
 * "Choose from media library" for the hero media image field (BL-807).
 *
 * Adds, beside the hero's field_media_image image widget, a link opening the
 * core Media Library modal (image media only, one slot) plus the hidden input
 * and hidden AJAX button BiolandHeroImageOpener fills and fires. The button
 * copies the chosen media's source file (and its alt text, when the hero's is
 * empty) into the widget state and rebuilds, so the preview updates before the
 * editor saves. The field itself stays a plain image field.
 *
 * bioland.module only calls alterWidget() when media_library is enabled, so
 * nothing here loads a media_library class on a site without it.
 */
class BiolandHeroMediaLibrary {

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

    $state = MediaLibraryState::create(self::OPENER_ID, self::ALLOWED_TYPES, self::ALLOWED_TYPES[0], 1, [
      'widget_id' => self::WIDGET_ID,
      'entity_id' => $entity->isNew() ? NULL : $entity->id(),
    ]);

    $element['#prefix'] = '<div id="' . self::WRAPPER_ID . '">' . ($element['#prefix'] ?? '');
    $element['#suffix'] = ($element['#suffix'] ?? '') . '</div>';
    $element['bioland_media_library'] = [
      '#type' => 'container',
      '#weight' => 10,
      'open' => [
        '#type' => 'link',
        '#title' => $this->t('Choose from media library'),
        '#url' => Url::fromRoute('media_library.ui', [], ['query' => $state->all()]),
        '#attributes' => [
          'class' => ['use-ajax', 'button', 'button--small'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(MediaLibraryUiBuilder::dialogOptions()),
        ],
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ],
      'selection' => [
        '#type' => 'hidden',
        '#parents' => [self::INPUT_KEY, 'selection'],
        '#attributes' => ['data-bioland-hero-media-value' => self::WIDGET_ID],
      ],
      'update' => [
        '#type' => 'submit',
        '#value' => $this->t('Use selected image'),
        '#name' => 'bioland-hero-media-library-update',
        '#parents' => [self::INPUT_KEY, 'update'],
        '#attributes' => ['data-bioland-hero-media-update' => self::WIDGET_ID, 'class' => ['js-hide']],
        '#limit_validation_errors' => [[self::INPUT_KEY]],
        '#validate' => [[static::class, 'validateSelection']],
        '#submit' => [[static::class, 'submitSelection']],
        '#ajax' => ['callback' => [static::class, 'ajaxRefresh'], 'wrapper' => self::WRAPPER_ID],
        '#bioland_field_parents' => $element['widget']['#field_parents'] ?? [],
      ],
    ];
  }

  /**
   * Resolves a Media Library selection into a field_media_image item.
   *
   * @param string $selection
   *   Comma-separated media ids posted by the hidden input.
   * @param string $current_alt
   *   The hero's current alt text; kept when not empty.
   *
   * @return array
   *   [] for an empty selection, ['error' => string] for a rejected one, or
   *   ['item' => array] holding the widget item values.
   */
  public function resolveSelection(string $selection, string $current_alt): array {
    $id = trim(explode(',', $selection)[0]);
    if ($id === '') {
      return [];
    }

    $media = ctype_digit($id) ? $this->entityTypeManager->getStorage('media')->load($id) : NULL;
    if (!$media || !in_array($media->bundle(), self::ALLOWED_TYPES, TRUE) || !$media->access('view', $this->currentUser)) {
      return ['error' => $this->t('The selected media item is not an available image.')];
    }

    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? '';
    $value = $source_field !== '' ? ($media->get($source_field)->getValue()[0] ?? []) : [];
    if (empty($value['target_id'])) {
      return ['error' => $this->t('The selected media item has no image file.')];
    }

    return [
      'item' => [
        'target_id' => $value['target_id'],
        'fids' => [$value['target_id']],
        'alt' => $current_alt !== '' ? $current_alt : (string) ($value['alt'] ?? ''),
        'title' => (string) ($value['title'] ?? ''),
        'width' => $value['width'] ?? NULL,
        'height' => $value['height'] ?? NULL,
      ],
    ];
  }

  /**
   * #validate for the hidden update button.
   */
  public static function validateSelection(array &$form, FormStateInterface $form_state): void {
    $parents = $form_state->getTriggeringElement()['#bioland_field_parents'] ?? [];
    $alt = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, [self::FIELD_NAME, 0, 'alt']));
    $result = \Drupal::service('bioland.hero_media_library')
      ->resolveSelection((string) $form_state->getValue([self::INPUT_KEY, 'selection'], ''), trim((string) $alt));

    if (isset($result['error'])) {
      $form_state->setErrorByName(self::INPUT_KEY . '][selection', $result['error']);
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
      $parents = $form_state->getTriggeringElement()['#bioland_field_parents'] ?? [];
      $field_state = WidgetBase::getWidgetState($parents, self::FIELD_NAME, $form_state);
      $field_state['items'] = [$item];
      WidgetBase::setWidgetState($parents, self::FIELD_NAME, $form_state, $field_state);

      $input = $form_state->getUserInput();
      NestedArray::unsetValue($input, array_merge($parents, [self::FIELD_NAME]));
      unset($input[self::INPUT_KEY]);
      $form_state->setUserInput($input);
    }
    $form_state->setRebuild();
  }

  /**
   * AJAX callback: re-render the whole field so the preview shows the image.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    $element = NestedArray::getValue($form, array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -2));
    $element['bioland_media_library']['messages'] = ['#type' => 'status_messages', '#weight' => -10];

    return $element;
  }

}
