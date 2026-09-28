<?php

namespace Drupal\bioland\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Puts the toast_image_editor under every media image field (BL-917).
 *
 * toast_image_editor 1.0.1 only edits a media type's *source* field, only
 * once the media is saved, and appends its editor at the bottom of the form
 * (a fieldset at weight 10). Bioland instead:
 *
 * - targets one image field per media type: the source field when the source
 *   plugin is 'image' (image, hero), otherwise the first image field that is
 *   not the 'thumbnail' base field (remote_video, document);
 * - renders the editor inside that field's managed_file element, so the
 *   upload button's AJAX callback (which re-renders only that element)
 *   delivers it as soon as a new file has been uploaded, before the first
 *   save; on media that already holds its file the editor starts collapsed
 *   behind an "Edit image" button;
 * - reuses the contrib JS, CSS and DOM ids unchanged, so its form-submit hook
 *   still posts the edited PNG in the 'toast_image_editor_data' request
 *   field; BiolandToastImageGuard keeps validating that payload;
 * - writes the edited bytes itself when the target is not the source field,
 *   because MediaPresaveService::processMediaPresave() returns early for
 *   every non-image source. For image sources the contrib presave still does
 *   the write, on insert too.
 *
 * A file may only be edited when it is the media's own: the one it already
 * stores, or a temporary upload owned by the current user. Any other fid the
 * widget input can name (a permanent file shared with other content) is
 * refused, both when rendering and when writing (fileEditable()).
 *
 * The contrib fieldset, when it was added, is removed in an #after_build so
 * the DOM ids stay unique.
 */
class BiolandMediaImageEditor {

  use StringTranslationTrait;

  /**
   * Base field every media type has; never offered for editing.
   */
  public const THUMBNAIL_FIELD = 'thumbnail';

  /**
   * Render-array key of the editor placed inside the image widget element.
   */
  public const ELEMENT_KEY = 'bioland_image_editor';

  /**
   * Form-state temporary flag: bioland placed an editor on this build.
   */
  public const STATE_KEY = 'bioland_image_editor_placed';

  /**
   * Media source plugin whose source field toast_image_editor edits itself.
   */
  public const IMAGE_SOURCE = 'image';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected FileSystemInterface $fileSystem,
    protected TimeInterface $time,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Whether the editor can be offered at all.
   */
  public function enabled(): bool {
    return $this->moduleHandler->moduleExists('toast_image_editor')
      && $this->currentUser->hasPermission('use toast image editor');
  }

  /**
   * Picks the image field to edit on a media type.
   *
   * @param string $source_plugin
   *   The media source plugin id.
   * @param string|null $source_field
   *   The source field name, if configured.
   * @param string[] $image_fields
   *   Names of the type's image-type fields, in definition order.
   *
   * @return string|null
   *   The field name, or NULL when the type has no editable image field.
   */
  public static function pickImageField(string $source_plugin, ?string $source_field, array $image_fields): ?string {
    if ($source_plugin === self::IMAGE_SOURCE && $source_field !== NULL && in_array($source_field, $image_fields, TRUE)) {
      return $source_field;
    }
    foreach ($image_fields as $name) {
      if ($name !== self::THUMBNAIL_FIELD && $name !== $source_field) {
        return $name;
      }
    }
    return NULL;
  }

  /**
   * The image field the editor targets on a media entity.
   */
  public function editableField(MediaInterface $media): ?string {
    $image_fields = [];
    foreach ($media->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() === 'image') {
        $image_fields[] = $name;
      }
    }
    $source = $media->getSource();
    return self::pickImageField($source->getPluginId(), $source->getConfiguration()['source_field'] ?? NULL, $image_fields);
  }

  /**
   * Whether toast_image_editor writes this field's file itself.
   */
  public function contribWrites(MediaInterface $media, string $field): bool {
    $source = $media->getSource();
    return $source->getPluginId() === self::IMAGE_SOURCE
      && ($source->getConfiguration()['source_field'] ?? NULL) === $field;
  }

  /**
   * Whether a file may be edited as this media's image.
   *
   * The widget input can name any permanent fid the user can reference, and
   * a permanent file may be shared with other content, so only two files
   * qualify: the one the saved media already stores, or a temporary upload
   * the current user made.
   *
   * @param int|null $stored_fid
   *   The fid the saved media holds in the field (NULL for new media).
   * @param int $fid
   *   The file about to be edited.
   * @param bool $temporary
   *   Whether that file is still temporary.
   * @param int $owner
   *   That file's owner uid.
   * @param int $uid
   *   The current user.
   */
  public static function fileEditable(?int $stored_fid, int $fid, bool $temporary, int $owner, int $uid): bool {
    if ($stored_fid !== NULL && $stored_fid === $fid) {
      return TRUE;
    }
    return $temporary && $uid > 0 && $owner === $uid;
  }

  /**
   * The file id currently held by a single-value image widget.
   *
   * Right after the managed_file AJAX upload the entity is still empty: the
   * new (temporary) file id lives only in the widget's #default_value and in
   * the submitted values. Checked in that order.
   *
   * @param array $element
   *   The complete widget form element.
   * @param array $values
   *   $form_state->getValues().
   * @param array $input
   *   $form_state->getUserInput().
   * @param string $field
   *   The field name.
   * @param array $parents
   *   The widget's #field_parents.
   *
   * @return int|null
   *   The file id, or NULL when the widget is empty.
   */
  public static function widgetFileId(array $element, array $values, array $input, string $field, array $parents = []): ?int {
    $fid = $element['widget'][0]['#default_value']['fids'][0] ?? NULL;
    if ($fid === NULL) {
      foreach ([$values, $input] as $source) {
        foreach ($parents as $parent) {
          $source = is_array($source) ? ($source[$parent] ?? []) : [];
        }
        $fids = $source[$field][0]['fids'] ?? NULL;
        if (is_string($fids)) {
          $fids = explode(' ', trim($fids));
        }
        $fid = is_array($fids) ? ($fids[0] ?? NULL) : NULL;
        if ($fid !== NULL && $fid !== '') {
          break;
        }
      }
    }
    return is_numeric($fid) && (int) $fid > 0 ? (int) $fid : NULL;
  }

  /**
   * The fid a saved media holds in a field, as last stored.
   *
   * The form's entity is mutated on every rebuild, so the stored value is
   * read from an unchanged copy.
   */
  public function storedFileId(MediaInterface $media, string $field): ?int {
    if ($media->isNew()) {
      return NULL;
    }
    $stored = $this->entityTypeManager->getStorage('media')->loadUnchanged($media->id());
    if (!$stored || !$stored->hasField($field) || $stored->get($field)->isEmpty()) {
      return NULL;
    }
    $fid = $stored->get($field)->target_id;
    return $fid ? (int) $fid : NULL;
  }

  /**
   * Adds the editor inside an image widget element on a media form.
   *
   * Called from hook_field_widget_complete_WIDGET_TYPE_form_alter() for the
   * image and image_focal_point widgets. Only the media entity's own form
   * qualifies: the media library upload form and inline entity forms build
   * the same widgets, but bioland_form_media_form_alter() never runs there,
   * so the editor would be markup without its library.
   *
   * @param array $element
   *   The complete widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $context
   *   The widget alter context.
   */
  public function alterWidget(array &$element, FormStateInterface $form_state, array $context): void {
    $items = $context['items'] ?? NULL;
    if (!empty($context['default']) || !$items || !$this->enabled()) {
      return;
    }
    $media = $items->getEntity();
    $field = $items->getFieldDefinition()->getName();
    $form_object = $form_state->getFormObject();
    if (!$media instanceof MediaInterface || !$form_object instanceof EntityFormInterface
      || $form_object->getEntity() !== $media || $this->editableField($media) !== $field
      || !isset($element['widget'][0])) {
      return;
    }

    $fid = self::widgetFileId($element, $form_state->getValues(), $form_state->getUserInput(), $field, $element['widget']['#field_parents'] ?? []);
    if ($fid === NULL && !$media->get($field)->isEmpty()) {
      $fid = (int) $media->get($field)->target_id;
    }
    $file = $fid ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    if (!$file instanceof FileInterface || !str_starts_with((string) $file->getMimeType(), 'image/')) {
      return;
    }

    $stored_fid = $this->storedFileId($media, $field);
    if (!self::fileEditable($stored_fid, (int) $file->id(), $file->isTemporary(), (int) $file->getOwnerId(), (int) $this->currentUser->id())) {
      return;
    }

    // The file the media already stores starts collapsed behind the button;
    // a file uploaded in this form session opens straight away.
    $form_state->setTemporaryValue(self::STATE_KEY, TRUE);
    $element['widget'][0][self::ELEMENT_KEY] = $this->build($stored_fid === (int) $file->id(), $this->imageUrl($file));
  }

  /**
   * Finishes the form once every alter has run (#after_build).
   *
   * Drops the contrib fieldset when bioland placed its own editor on this
   * build, so the shared DOM ids stay unique.
   */
  public function afterBuild(array $form, FormStateInterface $form_state): array {
    if ($form_state->getTemporaryValue(self::STATE_KEY)) {
      unset($form['toast_image_editor']);
    }
    return $form;
  }

  /**
   * Writes an edited image over a non-source image field's file.
   *
   * Mirrors ImageProcessorService::saveEditedImage() for the field
   * toast_image_editor will not touch. The payload has already passed
   * BiolandToastImageGuard::sanitize(), so it is a real image in the file's
   * own format.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media being saved.
   * @param string $field
   *   The image field whose file is replaced.
   * @param string $data_url
   *   The sanitized 'data:image/...;base64,...' payload.
   *
   * @return bool
   *   TRUE when the file was replaced.
   */
  public function writeEditedImage(MediaInterface $media, string $field, string $data_url): bool {
    $logger = $this->loggerFactory->get('bioland');
    $context = ['@id' => $media->id() ?? 'new', '@field' => $field];

    $file = $media->hasField($field) && !$media->get($field)->isEmpty() ? $media->get($field)->entity : NULL;
    if (!$file instanceof FileInterface) {
      $logger->warning('Edited image for media @id dropped: @field holds no file.', $context);
      return FALSE;
    }
    if (!preg_match('#^data:image/\w+;base64,#i', $data_url, $match)) {
      $logger->warning('Edited image for media @id dropped: payload is not an image data URL.', $context);
      return FALSE;
    }
    $bytes = base64_decode(substr($data_url, strlen($match[0])), TRUE);
    if ($bytes === FALSE || $bytes === '') {
      $logger->warning('Edited image for media @id dropped: invalid base64.', $context);
      return FALSE;
    }

    // Only replace a file that exists under the field's own scheme.
    $uri = (string) $file->getFileUri();
    $scheme = parse_url($uri, PHP_URL_SCHEME) ?: '';
    $allowed_scheme = $media->get($field)->getFieldDefinition()->getSetting('uri_scheme') ?: 'public';
    $real = $this->fileSystem->realpath($uri);
    if ($scheme !== $allowed_scheme || !$real || !is_file($real)) {
      $logger->warning('Edited image for media @id dropped: @uri is not an existing @scheme:// file.', $context + ['@uri' => $uri, '@scheme' => $allowed_scheme]);
      return FALSE;
    }

    $replace = class_exists('\Drupal\Core\File\FileExists')
      ? \Drupal\Core\File\FileExists::Replace
      : FileSystemInterface::EXISTS_REPLACE;
    if (!$this->fileSystem->saveData($bytes, $uri, $replace)) {
      $logger->error('Could not write the edited image for media @id to @uri.', $context + ['@uri' => $uri]);
      return FALSE;
    }

    $file->setSize(strlen($bytes));
    $file->setChangedTime($this->time->getRequestTime());
    $file->save();
    foreach ($this->entityTypeManager->getStorage('image_style')->loadMultiple() as $style) {
      $style->flush($uri);
    }
    if (!$media->isNew()) {
      $media->setNewRevision();
      $media->setRevisionUserId((int) $this->currentUser->id());
      $media->setRevisionCreationTime($this->time->getRequestTime());
      $media->setRevisionLogMessage('Image edited with Toast Image Editor');
    }
    return TRUE;
  }

  /**
   * The editor render array placed inside the widget element.
   *
   * Same ids and classes as MediaFormAlterService::alterMediaForm() so the
   * contrib JS finds them. The libraries and settings ride on this element's
   * #attached, so the managed_file AJAX response carries them too.
   *
   * @param bool $collapsed
   *   Whether the editor starts hidden behind the "Edit image" button.
   * @param string $image_url
   *   The image the editor loads.
   */
  public function build(bool $collapsed, string $image_url): array {
    $classes = ['bioland-image-editor'];
    if ($collapsed) {
      $classes[] = 'bioland-image-editor--collapsed';
    }
    return [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['class' => ['bioland-image-editor-wrapper']],
      '#attached' => [
        'library' => [
          'toast_image_editor/toast-image-editor-integration',
          'bioland/media_image_editor',
        ],
        'drupalSettings' => ['toastImageEditor' => $this->settings($image_url)],
      ],
      'toggle' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $collapsed ? $this->t('Edit image') : $this->t('Hide image editor'),
        '#attributes' => [
          'type' => 'button',
          'class' => ['button', 'button--small', 'bioland-image-editor-toggle'],
          'aria-expanded' => $collapsed ? 'false' : 'true',
          'aria-controls' => 'bioland-image-editor',
          'data-bioland-image-editor-toggle' => 'true',
          'data-label-show' => $this->t('Edit image'),
          'data-label-hide' => $this->t('Hide image editor'),
        ],
      ],
      'editor' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Image Editor'),
        '#attributes' => [
          'id' => 'bioland-image-editor',
          'class' => $classes,
          'role' => 'region',
          'aria-label' => $this->t('Image Editor'),
        ],
        'loading_indicator' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Preparing editor...'),
          '#attributes' => [
            'id' => 'toast-image-editor-loading',
            'class' => ['toast-image-editor-loading'],
            'aria-live' => 'polite',
            'aria-busy' => 'true',
          ],
        ],
        'status_messages' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => '',
          '#attributes' => [
            'id' => 'toast-image-editor-status',
            'class' => ['toast-image-editor-status', 'visually-hidden'],
            'aria-live' => 'polite',
            'aria-atomic' => 'true',
            'role' => 'status',
          ],
        ],
        'editor_container' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'toast-image-editor-container',
            'class' => ['toast-image-editor-wrapper'],
          ],
        ],
        'editor_placeholder' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'id' => 'toast-image-editor',
            'role' => 'application',
            'aria-label' => $this->t('Image editor'),
            'aria-describedby' => 'toast-image-editor-description',
          ],
        ],
        'editor_description' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Select tools from the toolbar below to edit your image. Your changes will save when you submit this form.'),
          '#attributes' => [
            'id' => 'toast-image-editor-description',
            'class' => ['visually-hidden'],
          ],
        ],
      ],
    ];
  }

  /**
   * drupalSettings.toastImageEditor, as the contrib JS expects them.
   */
  protected function settings(string $image_url): array {
    $config = $this->configFactory->get('toast_image_editor.settings');
    $tools = $config->get('enabled_tools');
    if (!$tools && class_exists('\Drupal\toast_image_editor\Form\SettingsForm')) {
      $tools = \Drupal\toast_image_editor\Form\SettingsForm::defaultTools();
    }
    return [
      'width' => $config->get('editor_width') ? $config->get('editor_width') . 'px' : '100%',
      'height' => $config->get('editor_height') ? $config->get('editor_height') . 'px' : '600px',
      'theme' => $config->get('theme') ?: 'white',
      'enabledTools' => $tools ?: [],
      'imageUrl' => $image_url,
    ];
  }

  /**
   * Cache-busted URL of a file on the current request's host.
   *
   * Same host as the page, as ImageProcessorService::getImageUrl() does, so
   * the editor canvas stays same-origin and toDataURL() is allowed.
   */
  protected function imageUrl(FileInterface $file): string {
    $url = $this->fileUrlGenerator->generateString($file->getFileUri());
    $request = $this->requestStack->getCurrentRequest();
    if ($request && str_starts_with($url, '/')) {
      $url = $request->getSchemeAndHttpHost() . $url;
    }
    return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $file->getChangedTime();
  }

}
