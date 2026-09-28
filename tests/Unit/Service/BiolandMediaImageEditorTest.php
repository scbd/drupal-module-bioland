<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\bioland\Service\BiolandMediaImageEditor;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests BiolandMediaImageEditor (BL-917).
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandMediaImageEditor
 */
class BiolandMediaImageEditorTest extends TestCase {

  /**
   * Messages the fake logger received, by level.
   */
  private array $logged = [];

  /**
   * Calls the fake file system received.
   */
  private array $fsCalls = [];

  /**
   * An image source edits its source field, others their first image field.
   *
   * @dataProvider fieldProvider
   * @covers ::pickImageField
   */
  public function testPickImageField(string $plugin, ?string $source, array $images, ?string $expected): void {
    $this->assertSame($expected, BiolandMediaImageEditor::pickImageField($plugin, $source, $images));
  }

  public static function fieldProvider(): array {
    return [
      'image type' => ['image', 'field_media_image', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'hero type' => ['image', 'field_media_image', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'remote video' => ['oembed:video', 'field_media_oembed_video', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'document' => ['file', 'field_media_document', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'thumbnail only is never offered' => ['file', 'field_media_document', ['thumbnail'], NULL],
      'no image field' => ['audio_file', 'field_media_audio_file', [], NULL],
      'image source not an image field' => ['image', 'field_missing', ['thumbnail', 'field_media_image'], 'field_media_image'],
      'first non-thumbnail wins' => ['file', 'field_media_document', ['thumbnail', 'field_preview', 'field_media_image'], 'field_preview'],
    ];
  }

  /**
   * The freshly uploaded file id is found in the widget, values, then input.
   *
   * @covers ::widgetFileId
   */
  public function testWidgetFileId(): void {
    $field = 'field_media_image';
    $widget = ['widget' => [0 => ['#default_value' => ['fids' => [12]]]]];
    $this->assertSame(12, BiolandMediaImageEditor::widgetFileId($widget, [], [], $field));

    $empty = ['widget' => [0 => ['#default_value' => ['fids' => []]]]];
    $values = [$field => [0 => ['fids' => [34]]]];
    $this->assertSame(34, BiolandMediaImageEditor::widgetFileId($empty, $values, [], $field));

    // managed_file posts fids as a space-separated string.
    $input = [$field => [0 => ['fids' => '56']]];
    $this->assertSame(56, BiolandMediaImageEditor::widgetFileId($empty, [], $input, $field));

    // Nested under the widget's #field_parents (inline entity forms).
    $nested = ['wrapper' => [$field => [0 => ['fids' => [78]]]]];
    $this->assertSame(78, BiolandMediaImageEditor::widgetFileId($empty, $nested, [], $field, ['wrapper']));

    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [], $field));
    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [$field => [0 => ['fids' => '']]], $field));
    $this->assertNull(BiolandMediaImageEditor::widgetFileId($empty, [], [$field => [0 => ['fids' => 'abc']]], $field));
  }

  /**
   * Only the media's stored file or the user's own temporary upload qualify.
   *
   * A permanent file another user owns, reachable by typing its fid into the
   * widget input, is never editable: overwriting it would change every
   * other entity that references it.
   *
   * @covers ::fileEditable
   */
  public function testFileEditable(): void {
    // The file the saved media already holds, whoever owns it.
    $this->assertTrue(BiolandMediaImageEditor::fileEditable(7, 7, FALSE, 99, 5));
    // The current user's own temporary upload (new media, or a replacement).
    $this->assertTrue(BiolandMediaImageEditor::fileEditable(NULL, 8, TRUE, 5, 5));
    $this->assertTrue(BiolandMediaImageEditor::fileEditable(7, 8, TRUE, 5, 5));
    // Someone else's permanent file named by fid.
    $this->assertFalse(BiolandMediaImageEditor::fileEditable(NULL, 8, FALSE, 99, 5));
    $this->assertFalse(BiolandMediaImageEditor::fileEditable(7, 8, FALSE, 5, 5));
    // Someone else's temporary upload.
    $this->assertFalse(BiolandMediaImageEditor::fileEditable(NULL, 8, TRUE, 99, 5));
    // Anonymous never owns an upload.
    $this->assertFalse(BiolandMediaImageEditor::fileEditable(NULL, 8, TRUE, 0, 0));
  }

  /**
   * The render array carries the contrib DOM ids and its own assets.
   *
   * The contrib JS looks these ids up by name, so a rename would silently
   * disable the editor. The libraries and settings ride on the element so
   * the managed_file AJAX response delivers them.
   *
   * @covers ::build
   */
  public function testBuildKeepsContribIdsAndAttachesAssets(): void {
    $editor = $this->editor();
    $build = $editor->build(TRUE, 'https://example.test/files/a.jpg?v=1');

    $ids = [];
    array_walk_recursive($build, function ($value, $key) use (&$ids) {
      if ($key === 'id') {
        $ids[] = $value;
      }
    });
    foreach (['toast-image-editor', 'toast-image-editor-loading', 'toast-image-editor-status', 'toast-image-editor-container', 'toast-image-editor-description', 'bioland-image-editor'] as $id) {
      $this->assertContains($id, $ids);
    }
    $this->assertSame(['toast_image_editor/toast-image-editor-integration', 'bioland/media_image_editor'], $build['#attached']['library']);
    $settings = $build['#attached']['drupalSettings']['toastImageEditor'];
    $this->assertSame('https://example.test/files/a.jpg?v=1', $settings['imageUrl']);
    $this->assertSame('100%', $settings['width']);
    $this->assertSame('600px', $settings['height']);
    $this->assertSame('white', $settings['theme']);

    // Collapsed: hidden class, button announces closed and offers to open.
    $this->assertContains('bioland-image-editor--collapsed', $build['editor']['#attributes']['class']);
    $this->assertSame('false', $build['toggle']['#attributes']['aria-expanded']);
    $this->assertSame('Edit image', (string) $build['toggle']['#value']);
    $this->assertSame('bioland-image-editor', $build['toggle']['#attributes']['aria-controls']);
    $this->assertSame('button', $build['toggle']['#attributes']['type']);

    $open = $editor->build(FALSE, 'x');
    $this->assertNotContains('bioland-image-editor--collapsed', $open['editor']['#attributes']['class']);
    $this->assertSame('true', $open['toggle']['#attributes']['aria-expanded']);
  }

  /**
   * A valid payload replaces the file and bumps a revision on saved media.
   *
   * @covers ::writeEditedImage
   */
  public function testWriteEditedImageReplacesFile(): void {
    $real = tempnam(sys_get_temp_dir(), 'bioland');
    file_put_contents($real, 'old');
    $file = $this->file(3, 'public://thumbs/a.jpg', 1, FALSE);
    $media = $this->media(41, FALSE, $file);
    $editor = $this->editor($real);

    $png = 'data:image/png;base64,' . base64_encode("\x89PNGnew-bytes");
    $this->assertTrue($editor->writeEditedImage($media, 'field_media_image', $png));

    $this->assertSame(["\x89PNGnew-bytes", 'public://thumbs/a.jpg'], $this->fsCalls['saveData']);
    $this->assertSame(strlen("\x89PNGnew-bytes"), $file->size);
    $this->assertSame(1700000000, $file->changed);
    $this->assertTrue($file->saved);
    $this->assertSame(['public://thumbs/a.jpg'], $editor->flushed);
    $this->assertTrue($media->newRevision);
    $this->assertSame(5, $media->revisionUser);
    $this->assertSame(1700000000, $media->revisionCreated);
    $this->assertSame('Image edited with Toast Image Editor', $media->revisionLog);
    $this->assertSame([], $this->logged);
    unlink($real);
  }

  /**
   * On new media the file is written without touching revisions.
   *
   * @covers ::writeEditedImage
   */
  public function testWriteEditedImageOnNewMediaSkipsRevision(): void {
    $real = tempnam(sys_get_temp_dir(), 'bioland');
    $file = $this->file(3, 'public://a.png', 1, TRUE);
    $media = $this->media(NULL, TRUE, $file);
    $editor = $this->editor($real);

    $this->assertTrue($editor->writeEditedImage($media, 'field_media_image', 'data:image/png;base64,' . base64_encode('png')));
    $this->assertFalse($media->newRevision);
    $this->assertTrue($file->saved);
    unlink($real);
  }

  /**
   * Every refusal is logged and leaves the file alone.
   *
   * @covers ::writeEditedImage
   */
  public function testWriteEditedImageRefusals(): void {
    $real = tempnam(sys_get_temp_dir(), 'bioland');
    $good = 'data:image/png;base64,' . base64_encode('png');

    // Field holds no file.
    $editor = $this->editor($real);
    $this->assertFalse($editor->writeEditedImage($this->media(1, FALSE, NULL), 'field_media_image', $good));
    $this->assertStringContainsString('holds no file', $this->logged['warning'][0]);

    // Not a data URL / bad base64.
    $editor = $this->editor($real);
    $file = $this->file(3, 'public://a.png', 1, FALSE);
    $this->assertFalse($editor->writeEditedImage($this->media(1, FALSE, $file), 'field_media_image', 'hello'));
    $this->assertFalse($editor->writeEditedImage($this->media(1, FALSE, $file), 'field_media_image', 'data:image/png;base64,@@@'));
    $this->assertCount(2, $this->logged['warning']);
    $this->assertFalse($file->saved);

    // Wrong scheme for the field.
    $editor = $this->editor($real);
    $file = $this->file(3, 'private://a.png', 1, FALSE);
    $this->assertFalse($editor->writeEditedImage($this->media(1, FALSE, $file), 'field_media_image', $good));
    $this->assertStringContainsString('not an existing @scheme:// file', $this->logged['warning'][0]);

    // File missing on disk.
    $editor = $this->editor(FALSE);
    $file = $this->file(3, 'public://a.png', 1, FALSE);
    $this->assertFalse($editor->writeEditedImage($this->media(1, FALSE, $file), 'field_media_image', $good));
    $this->assertArrayNotHasKey('saveData', $this->fsCalls);

    // saveData fails.
    $editor = $this->editor($real, FALSE);
    $file = $this->file(3, 'public://a.png', 1, FALSE);
    $media = $this->media(1, FALSE, $file);
    $this->assertFalse($editor->writeEditedImage($media, 'field_media_image', $good));
    $this->assertStringContainsString('Could not write', $this->logged['error'][0]);
    $this->assertFalse($file->saved);
    $this->assertFalse($media->newRevision);
    unlink($real);
  }

  /**
   * Builds the service with fakes; $realpath is what the file system resolves.
   */
  private function editor($realpath = '/dev/null', bool $save_ok = TRUE) {
    $this->logged = [];
    $this->fsCalls = [];
    $test = $this;

    $logger = new class($test) {
      public function __construct(private $test) {}
      public function __call($level, $args) {
        $this->test->log($level, $args[0]);
      }
    };
    $loggerFactory = new class($logger) implements LoggerChannelFactoryInterface {
      public function __construct(private $logger) {}
      public function get($channel) { return $this->logger; }
    };
    $styles = new class implements EntityStorageInterface {
      public array $flushed = [];
      public function load($id) { return NULL; }
      public function getQuery() { return NULL; }
      public function loadMultiple() {
        $store = $this;
        return [new class($store) {
          public function __construct(private $store) {}
          public function flush($uri) { $this->store->flushed[] = $uri; }
        }];
      }
    };
    $etm = new class($styles) implements EntityTypeManagerInterface {
      public function __construct(public $styles) {}
      public function getStorage($entity_type_id) { return $this->styles; }
      public function getDefinitions() { return []; }
      public function getDefinition($entity_type_id) { return NULL; }
      public function hasDefinition($entity_type_id) { return FALSE; }
      public function getAccessControlHandler($entity_type_id) { return NULL; }
    };
    $fs = new class($test, $realpath, $save_ok) implements FileSystemInterface {
      public function __construct(private $test, private $realpath, private $ok) {}
      public function prepareDirectory($directory, $options = self::MODIFY_PERMISSIONS) { return TRUE; }
      public function realpath($uri) { return $this->realpath; }
      public function saveData($data, $destination, $replace) {
        $this->test->fsCall('saveData', [$data, $destination]);
        return $this->ok ? $destination : FALSE;
      }
    };
    $user = new class implements AccountProxyInterface {
      public function getAccount() { return $this; }
      public function id() { return 5; }
      public function hasPermission($permission) { return TRUE; }
    };
    $modules = new class implements ModuleHandlerInterface {
      public function moduleExists($module) { return TRUE; }
    };
    $config = new class implements ConfigFactoryInterface {
      public function get($name) { return new ImmutableConfig($name, []); }
      public function getEditable($name) { return $this->get($name); }
    };
    $urls = new class implements FileUrlGeneratorInterface {
      public function generateString(string $uri) { return '/files/' . basename($uri); }
      public function generateAbsoluteString(string $uri) { return 'https://example.test' . $this->generateString($uri); }
    };
    $time = new class implements TimeInterface {
      public function getRequestTime() { return 1700000000; }
    };

    $editor = new class($etm, $user, $modules, $config, $urls, $fs, $time, $loggerFactory, new RequestStack()) extends BiolandMediaImageEditor {
      public array $flushed = [];
      public function writeEditedImage(MediaInterface $media, string $field, string $data_url): bool {
        $ok = parent::writeEditedImage($media, $field, $data_url);
        $this->flushed = $this->entityTypeManager->styles->flushed;
        return $ok;
      }
    };
    return $editor;
  }

  public function log(string $level, string $message): void {
    $this->logged[$level][] = $message;
  }

  public function fsCall(string $name, array $args): void {
    $this->fsCalls[$name] = $args;
  }

  /**
   * A file entity fake recording what the writer sets on it.
   */
  private function file(int $fid, string $uri, int $owner, bool $temporary) {
    return new class($fid, $uri, $owner, $temporary) implements FileInterface {
      public $size;
      public $changed;
      public bool $saved = FALSE;
      public function __construct(private int $fid, private string $uri, private int $owner, private bool $temporary) {}
      public function id() { return $this->fid; }
      public function getFileUri() { return $this->uri; }
      public function getFilename() { return basename($this->uri); }
      public function delete() {}
      public function getOwnerId() { return $this->owner; }
      public function isTemporary() { return $this->temporary; }
      public function getMimeType() { return 'image/png'; }
      public function getChangedTime() { return 1; }
      public function setSize($size) { $this->size = $size; }
      public function setChangedTime($time) { $this->changed = $time; }
      public function save() { $this->saved = TRUE; }
    };
  }

  /**
   * A media fake with one image field holding $file (or nothing).
   */
  private function media(?int $id, bool $new, $file) {
    return new class($id, $new, $file) implements MediaInterface {
      public bool $newRevision = FALSE;
      public $revisionUser;
      public $revisionCreated;
      public $revisionLog;
      public function __construct(private ?int $id, private bool $new, private $file) {}
      public function id() { return $this->id; }
      public function isNew() { return $this->new; }
      public function bundle() { return 'remote_video'; }
      public function hasField($field_name) { return $field_name === 'field_media_image'; }
      public function getSource() { return NULL; }
      public function get($field_name) {
        $file = $this->file;
        return new class($file) {
          public function __construct(public $entity) {}
          public function isEmpty() { return $this->entity === NULL; }
          public function getFieldDefinition() {
            return new class {
              public function getSetting($name) { return $name === 'uri_scheme' ? 'public' : NULL; }
            };
          }
        };
      }
      public function setNewRevision($value = TRUE) { $this->newRevision = $value; }
      public function setRevisionUserId($uid) { $this->revisionUser = $uid; }
      public function setRevisionCreationTime($time) { $this->revisionCreated = $time; }
      public function setRevisionLogMessage($message) { $this->revisionLog = $message; }
    };
  }

}
