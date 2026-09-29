<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the CKEditor inline image upload limit (BL-1202).
 *
 * Two install helpers used to disagree about the limit:
 * _bioland_configure_full_html_format() wrote a dead
 * settings.plugins.ckeditor5_imageUpload.max_size = 250kb key that core never
 * reads, while _bioland_configure_full_html_editor_toolbar() wrote the real
 * limit, image_upload.max_size, as 50kb. Both literals, and every reference
 * to the dead ckeditor5_imageUpload key other than the unset that clears it,
 * must never reappear in includes/bioland.install.editor.inc.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandEditorImageUploadLimitTest extends TestCase {

  /**
   * Resolve the module root in both module-root and tests contexts.
   */
  private function moduleRoot(): string {
    $root = dirname(__DIR__, 2);
    if (!file_exists($root . '/bioland.install')) {
      $root = __DIR__ . '/../..';
    }
    return $root;
  }

  /**
   * The contents of includes/bioland.install.editor.inc.
   */
  private function editorIncContents(): string {
    $file = $this->moduleRoot() . '/includes/bioland.install.editor.inc';
    $this->assertFileExists($file);
    return file_get_contents($file);
  }

  /**
   * Neither retired size literal may remain in the file.
   */
  public function testNeitherRetiredSizeLiteralRemains(): void {
    $content = $this->editorIncContents();

    $this->assertDoesNotMatchRegularExpression(
      '/[\'"]250kb[\'"]/',
      $content,
      'The dead 250kb image upload literal must not remain in bioland.install.editor.inc.'
    );

    $this->assertDoesNotMatchRegularExpression(
      '/[\'"]50kb[\'"]/',
      $content,
      'The superseded 50kb image upload literal must not remain in bioland.install.editor.inc.'
    );
  }

  /**
   * The canonical 10 MB limit is defined once and referenced by both helpers.
   */
  public function testCanonicalLimitConstantIsDefinedAndUsed(): void {
    $content = $this->editorIncContents();

    $this->assertMatchesRegularExpression(
      '/const\s+BIOLAND_EDITOR_IMAGE_MAX_SIZE\s*=\s*[\'"]10 MB[\'"]/',
      $content,
      'BIOLAND_EDITOR_IMAGE_MAX_SIZE must be defined as \'10 MB\'.'
    );

    // Extract just the target function's body (up to the next top-level
    // function declaration, or end of file) so the assertion cannot match
    // BIOLAND_EDITOR_IMAGE_MAX_SIZE usages that live in unrelated functions.
    $this->assertMatchesRegularExpression(
      '/function\s+_bioland_configure_full_html_editor_toolbar\s*\([^)]*\)\s*\{.*?(?=\n^function\s|\z)/ms',
      $content,
      '_bioland_configure_full_html_editor_toolbar() must be defined in bioland.install.editor.inc.'
    );

    preg_match(
      '/function\s+_bioland_configure_full_html_editor_toolbar\s*\([^)]*\)\s*\{.*?(?=\n^function\s|\z)/ms',
      $content,
      $matches
    );
    $function_body = $matches[0] ?? '';

    $this->assertStringContainsString(
      'BIOLAND_EDITOR_IMAGE_MAX_SIZE',
      $function_body,
      '_bioland_configure_full_html_editor_toolbar() must write BIOLAND_EDITOR_IMAGE_MAX_SIZE.'
    );
  }

  /**
   * The dead ckeditor5_imageUpload key is referenced only by its own unset.
   *
   * The explicit unset() call that clears it from already-installed sites
   * must exist, and nothing may WRITE into the key again - no assignment of
   * the form $settings['plugins']['ckeditor5_imageUpload'][...] = ... or
   * $settings['plugins']['ckeditor5_imageUpload'] = ... may reappear.
   */
  public function testCkeditor5ImageUploadKeyIsOnlyEverUnset(): void {
    $content = $this->editorIncContents();

    $this->assertMatchesRegularExpression(
      '/unset\s*\(\s*\$settings\[[\'"]plugins[\'"]\]\[[\'"]ckeditor5_imageUpload[\'"]\]\s*\)/',
      $content,
      '_bioland_configure_full_html_format() must explicitly unset the dead ckeditor5_imageUpload key.'
    );

    // Only real code (strip line comments and docblocks) may contain a write
    // into the key; a mention in prose/comments is fine.
    $codeOnly = preg_replace('#/\*.*?\*/#s', '', $content);
    $codeOnly = preg_replace('#//.*#', '', $codeOnly);

    $this->assertDoesNotMatchRegularExpression(
      '/\$settings\[[\'"]plugins[\'"]\]\[[\'"]ckeditor5_imageUpload[\'"]\][^;]*=(?!=)/',
      $codeOnly,
      'No code may assign into $settings[\'plugins\'][\'ckeditor5_imageUpload\'] - the key must only ever be unset.'
    );
  }

}
