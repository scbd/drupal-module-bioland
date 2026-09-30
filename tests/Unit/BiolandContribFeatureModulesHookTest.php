<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the BL-917 contrib feature module enablement.
 *
 * Source-level checks, in the style of SearchApiConvergenceHookTest: the
 * update hook and hook_install() both route through one helper, the helper
 * lists exactly the five modules, and it only installs modules that are in
 * the codebase and not yet enabled.
 *
 * @group bioland
 * @coversNothing
 */
class BiolandContribFeatureModulesHookTest extends TestCase {

  /**
   * Reads a module file relative to the module root.
   */
  private function read(string $path): string {
    $file = dirname(__DIR__, 2) . '/' . $path;
    $this->assertFileExists($file);
    return file_get_contents($file);
  }

  /**
   * Returns one function's source, cut at the next function declaration.
   */
  private function functionBody(string $source, string $name): string {
    $offset = strpos($source, 'function ' . $name . '(');
    $this->assertIsInt($offset, $name . '() must exist.');
    $body = substr($source, $offset);
    if (preg_match('/^function /m', $body, $matches, PREG_OFFSET_CAPTURE, 1)) {
      $body = substr($body, 0, $matches[0][1]);
    }
    return $body;
  }

  /**
   * The helper lists exactly the five BL-917 modules.
   */
  public function testModuleListIsExact(): void {
    $body = $this->functionBody($this->read('includes/bioland.install.editor.inc'), '_bioland_contrib_feature_modules');
    preg_match_all("/'([a-z0-9_]+)'/", $body, $m);
    $this->assertSame(
      ['toast_image_editor', 'ckeditor_media_resizer', 'llms_txt', 'ckeditor5_fullscreen', 'ckeditor5_icons'],
      $m[1]
    );
  }

  /**
   * The helper skips enabled and absent modules and installs the rest.
   */
  public function testHelperOnlyInstallsPresentDisabledModules(): void {
    $body = $this->functionBody($this->read('includes/bioland.install.editor.inc'), '_bioland_enable_contrib_feature_modules');
    $this->assertStringContainsString('->moduleExists($module)', $body);

    // The module list is reset before the presence check, so a cache built
    // on an older image cannot mark present modules missing.
    $reset = strpos($body, "->reset()");
    $exists = strpos($body, '->exists($module)');
    $this->assertIsInt($reset, 'The module list must be reset before checking presence.');
    $this->assertIsInt($exists);
    $this->assertLessThan($exists, $reset);

    // One module per install() call, each guarded, so one bad module
    // cannot block the others or fail the run.
    $this->assertMatchesRegularExpression('/try \{\s*\$installer->install\(\[\$module\]\);/', $body);
    $this->assertStringContainsString('catch (\\Exception $e)', $body);
    $this->assertStringContainsString("\\Drupal::logger('bioland')", $body);

    $this->assertDoesNotMatchRegularExpression('/\bt\(/', $body, 'Plain strings only: translation is not dependable mid-update.');
  }

  /**
   * The update hook and hook_install() both call the helper.
   */
  public function testUpdateHookAndInstallCallHelper(): void {
    $hook = $this->functionBody($this->read('includes/bioland.install.editor.inc'), 'bioland_update_9096');
    $this->assertStringContainsString('_bioland_enable_contrib_feature_modules()', $hook);

    // hook_install() skips it during a config sync, where the synced
    // core.extension decides which modules are enabled.
    $install = $this->functionBody($this->read('bioland.install'), 'bioland_install');
    $this->assertMatchesRegularExpression('/if \(!\\\\Drupal::isConfigSyncing\(\)\) \{\s*_bioland_enable_contrib_feature_modules\(\);/', $install);

    // The resizer's filter is switched on right after the modules, and by
    // its own hook on sites that already ran 9096.
    $this->assertMatchesRegularExpression('/_bioland_enable_contrib_feature_modules\(\);\s*_bioland_enable_media_resize_filter\(\);/', $install);
    $filter_hook = $this->functionBody($this->read('includes/bioland.install.editor.inc'), 'bioland_update_9101');
    $this->assertStringContainsString('_bioland_enable_media_resize_filter()', $filter_hook);
  }

  /**
   * The status report keeps flagging modules the hook could not enable.
   */
  public function testRequirementsFlagDisabledModules(): void {
    $body = $this->functionBody($this->read('bioland.install'), 'bioland_requirements');
    $this->assertStringContainsString("\$requirements['bioland_contrib_feature_modules']", $body);
    $this->assertStringContainsString('_bioland_contrib_feature_modules()', $body);
  }

  /**
   * Bioland never grants the new modules' permissions to its standard roles.
   *
   * toast_image_editor writes edited bytes over media files and llms_txt
   * publishes token-replaced text to anonymous visitors, so both stay with
   * the administrator role only (BL-917).
   */
  public function testStandardRolesGetNoContribFeaturePermissions(): void {
    require_once dirname(__DIR__, 2) . '/includes/bioland.install.roles.inc';
    $blocked = '/toast image editor|llms\.txt/';
    foreach (_bioland_get_standard_permission_matrix() as $role => $permissions) {
      foreach ($permissions as $permission) {
        $this->assertDoesNotMatchRegularExpression($blocked, $permission, "Role $role must not be granted '$permission'.");
      }
    }
  }

  /**
   * The toast_image_editor payload check is wired on form and presave.
   */
  public function testToastImageGuardIsWired(): void {
    $module = $this->read('bioland.module');
    $alter = $this->functionBody($module, 'bioland_form_media_form_alter');
    $this->assertStringContainsString("'_bioland_toast_image_editor_validate'", $alter);

    $validate = $this->functionBody($module, '_bioland_toast_image_editor_validate');
    $this->assertStringContainsString('getUserInput()', $validate);
    $this->assertStringContainsString('setErrorByName(', $validate);

    $presave = $this->functionBody($module, 'bioland_media_presave');
    $this->assertStringContainsString('$post = \\Drupal::request()->request;', $presave);
    $this->assertStringContainsString('BiolandToastImageGuard::sanitize($post', $presave);
    // Permission is checked before any payload is decoded.
    $this->assertStringContainsString("hasPermission('use toast image editor')", $presave);
    $this->assertStringContainsString("\$media->access('update', \$user)", $presave);
  }

  /**
   * The editor under the image widget is wired on widget, form and presave.
   *
   * @see \Drupal\bioland\Service\BiolandMediaImageEditor
   */
  public function testMediaImageEditorIsWired(): void {
    $module = $this->read('bioland.module');

    $widget = $this->functionBody($module, 'bioland_field_widget_complete_image_image_form_alter');
    $this->assertStringContainsString("service('bioland.media_image_editor')->alterWidget(", $widget);
    // The hero focal point widget routes through the same alter.
    $focal = $this->functionBody($module, 'bioland_field_widget_complete_image_focal_point_form_alter');
    $this->assertStringContainsString('bioland_field_widget_complete_image_image_form_alter(', $focal);

    $alter = $this->functionBody($module, 'bioland_form_media_form_alter');
    $this->assertStringContainsString("\$form['#after_build'][] = '_bioland_media_image_editor_after_build';", $alter);
    $after = $this->functionBody($module, '_bioland_media_image_editor_after_build');
    $this->assertStringContainsString("service('bioland.media_image_editor')->afterBuild(", $after);

    // Non-source image fields are written by bioland, after sanitize() and
    // with the payload removed from the request.
    $presave = $this->functionBody($module, 'bioland_media_presave');
    $this->assertStringContainsString('$editor->contribWrites($media, $field)', $presave);
    $this->assertStringContainsString('$post->remove($key);', $presave);
    $this->assertStringContainsString('$editor->writeEditedImage($media, $field, $payload)', $presave);
    // Only the media's own file may be overwritten (IDOR by fid otherwise).
    $this->assertStringContainsString('BiolandMediaImageEditor::fileEditable(', $presave);
    $this->assertLessThan(strpos($presave, 'BiolandToastImageGuard::sanitize('), strpos($presave, 'fileEditable('));

    // The form validator keys on the same target field as presave.
    $validate = $this->functionBody($module, '_bioland_toast_image_editor_validate');
    $this->assertStringContainsString("service('bioland.media_image_editor')->editableField(", $validate);
    $this->assertStringContainsString('_bioland_media_image_extension($media, $field)', $validate);
    $this->assertStringNotContainsString('_bioland_media_source_extension(', $validate);
    $this->assertLessThan(strpos($presave, 'writeEditedImage('), strpos($presave, 'BiolandToastImageGuard::sanitize('));

    $services = $this->read('bioland.services.yml');
    $this->assertStringContainsString('bioland.media_image_editor:', $services);
    $this->assertStringContainsString('Drupal\\bioland\\Service\\BiolandMediaImageEditor', $services);

    $libraries = $this->read('bioland.libraries.yml');
    $this->assertStringContainsString('media_image_editor:', $libraries);
    $this->assertStringContainsString('js/bioland-media-image-editor-1-1-13.js', $libraries);
    $this->assertFileExists(dirname(__DIR__, 2) . '/js/bioland-media-image-editor-1-1-13.js');
    $this->assertFileExists(dirname(__DIR__, 2) . '/css/bioland-media-image-editor.css');
  }

  /**
   * bioland's media presave runs before toast_image_editor's.
   */
  public function testMediaPresaveRunsBeforeToastImageEditor(): void {
    require_once dirname(__DIR__, 2) . '/bioland.module';

    $implementations = ['media' => FALSE, 'toast_image_editor' => FALSE, 'bioland' => FALSE];
    bioland_module_implements_alter($implementations, 'media_presave');
    $this->assertSame(['bioland', 'media', 'toast_image_editor'], array_keys($implementations));

    // form_alter still moves bioland last.
    $implementations = ['bioland' => FALSE, 'toast_image_editor' => FALSE];
    bioland_module_implements_alter($implementations, 'form_alter');
    $this->assertSame(['toast_image_editor', 'bioland'], array_keys($implementations));
  }

}
