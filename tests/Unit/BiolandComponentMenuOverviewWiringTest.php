<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Service\BiolandComponentMenuOverview;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guards the wiring of the menu overview screen additions.
 *
 * Four declarations have to agree, and none can see the others at runtime:
 *   1. bioland.services.yml registers bioland.component_menu_overview with the
 *      dependencies the class constructor actually takes;
 *   2. bioland.module implements hook_form_menu_edit_form_alter() and hands the
 *      form to that service;
 *   3. bioland.libraries.yml declares the library the service attaches, and the
 *      stylesheet it names exists;
 *   4. the operation points at a route bioland.routing.yml really declares.
 * Every one of these fails silently in the browser - a missing column, an
 * unstyled column, or a 404 behind the operation - so each edge is pinned here.
 *
 * @group bioland
 * @covers ::bioland_form_menu_edit_form_alter
 */
class BiolandComponentMenuOverviewWiringTest extends TestCase {

  /**
   * The service id under test.
   */
  private const SERVICE = 'bioland.component_menu_overview';

  /**
   * The hook that dispatches to it.
   */
  private const HOOK = 'bioland_form_menu_edit_form_alter';

  /**
   * The form id the hook is named for.
   *
   * Core derives it in EntityForm::getFormId() from the menu entity type and
   * the "edit" operation; the menu entity declares no bundle key, so there is
   * no bundle segment. Get this wrong and the hook simply never fires.
   */
  private const FORM_ID = 'menu_edit_form';

  /**
   * Returns the module root directory.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 2);
  }

  /**
   * Returns the contents of a file in the module root.
   */
  private function moduleFile(string $relativePath): string {
    $path = $this->moduleRoot() . '/' . $relativePath;
    $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));

    return file_get_contents($path);
  }

  /**
   * The service is registered against the class that implements it.
   */
  public function testTheServiceIsRegistered(): void {
    $services = $this->moduleFile('bioland.services.yml');

    $this->assertStringContainsString(self::SERVICE . ':', $services);
    $this->assertStringContainsString(
      'class: Drupal\bioland\Service\BiolandComponentMenuOverview',
      $services
    );
    $this->assertTrue(
      class_exists(BiolandComponentMenuOverview::class),
      'The class named in bioland.services.yml must exist.'
    );
  }

  /**
   * The registered arguments match the constructor, in order.
   *
   * A drifted argument list is a container build failure at deploy time, not a
   * test failure, so it is worth pinning cheaply here.
   */
  public function testTheServiceArgumentsMatchTheConstructor(): void {
    $services = $this->moduleFile('bioland.services.yml');
    $block = substr($services, strpos($services, self::SERVICE . ':'));

    foreach (['@bioland.component_registry', '@bioland.component_menu_form_mode', '@redirect.destination'] as $argument) {
      $this->assertStringContainsString($argument, $block, sprintf('%s must be injected.', $argument));
    }
    $this->assertStringContainsString('setStringTranslation', $block, 'The service renders translatable strings.');

    $constructor = (new ReflectionClass(BiolandComponentMenuOverview::class))->getConstructor();
    $this->assertSame(
      ['registry', 'form_mode', 'redirect_destination'],
      array_map(static fn ($parameter) => $parameter->getName(), $constructor->getParameters()),
      'The YAML argument order must match the constructor signature.'
    );
  }

  /**
   * The hook exists, is named for the right form, and dispatches to the service.
   */
  public function testTheHookDispatchesToTheService(): void {
    $module = $this->moduleFile('bioland.module');

    $this->assertStringContainsString('function ' . self::HOOK . '(', $module);
    $this->assertSame(
      'bioland_form_' . self::FORM_ID . '_alter',
      self::HOOK,
      'The hook must be named for the menu edit form id.'
    );
    $this->assertMatchesRegularExpression(
      '/function ' . self::HOOK . '\([^)]*\)\s*\{\s*\\\\Drupal::service\(\'' . preg_quote(self::SERVICE, '/') . '\'\)\s*->alterOverviewForm\(\$form, \$form_state\);\s*\}/',
      $module,
      'The hook must be a thin dispatcher and nothing more.'
    );
  }

  /**
   * The hook is separate from the generic form_alter ordering contract.
   *
   * bioland_form_alter() is moved last by bioland_module_implements_alter() so
   * Component mode's entity builder outruns menu_link_attributes'. This screen
   * needs none of that, and folding it into that hook would tie an unrelated
   * change to the menu link save path.
   */
  public function testTheOverviewIsNotHandledByTheGenericFormAlter(): void {
    $module = $this->moduleFile('bioland.module');
    $start = strpos($module, 'function bioland_form_alter(');
    $end = strpos($module, 'function bioland_form_menu_edit_form_alter(');
    $this->assertIsInt($start);
    $this->assertIsInt($end);

    $this->assertStringNotContainsString(
      self::SERVICE,
      substr($module, $start, $end - $start),
      'The overview service must not be dispatched from the generic form_alter.'
    );
  }

  /**
   * The library the service attaches exists and names a stylesheet that does.
   */
  public function testTheLibraryAndItsStylesheetExist(): void {
    $libraries = $this->moduleFile('bioland.libraries.yml');

    [, $name] = explode('/', BiolandComponentMenuOverview::LIBRARY, 2);
    $this->assertStringContainsString($name . ':', $libraries, 'The library must be declared.');
    $this->assertStringContainsString('css/bioland-menu-overview.css', $libraries);
    $this->assertFileExists($this->moduleRoot() . '/css/bioland-menu-overview.css');
  }

  /**
   * The stylesheet only ever targets the class the service adds.
   *
   * A rule leaking outside it would restyle core's menu screen for every site.
   */
  public function testTheStylesheetIsScopedToTheIndicatorClass(): void {
    $css = $this->moduleFile('css/bioland-menu-overview.css');
    $css = preg_replace('#/\*.*?\*/#s', '', $css);

    preg_match_all('/([^{}]+)\{/', $css, $matches);
    $this->assertNotEmpty($matches[1], 'The stylesheet must contain at least one rule.');
    foreach ($matches[1] as $selector) {
      $this->assertStringContainsString(
        BiolandComponentMenuOverview::INDICATOR_CLASS,
        trim($selector),
        sprintf('Selector "%s" escapes the indicator column.', trim($selector))
      );
    }
  }

  /**
   * The operation targets a route the module actually declares.
   */
  public function testTheOperationTargetsADeclaredRoute(): void {
    $this->assertStringContainsString(
      BiolandComponentMenuOverview::ADD_ROUTE . ':',
      $this->moduleFile('bioland.routing.yml'),
      'The operation would 404 without this route.'
    );
  }

  /**
   * The parent query key matches the one core's own "Add child" uses.
   *
   * Core's MenuLinkContentForm reads ?parent= as the parent-select fallback, so
   * any other spelling would silently prefill nothing.
   */
  public function testTheParentQueryKeyMatchesCore(): void {
    $this->assertSame('parent', BiolandComponentMenuOverview::PARENT_QUERY);
  }

  /**
   * Both indicator strings are present in the English translation catalog.
   *
   * The catalogs are the drift detector: rewording one without updating them
   * silently un-translates it in 66 locales.
   */
  public function testTheNewStringsArePresentInTheEnglishCatalog(): void {
    $catalog = $this->moduleFile('translations/bioland.en.po');

    foreach (['Add Mega Menu Child', '(Mega Menu: @type)', '(Mega Menu: @type: @schema)'] as $msgid) {
      $this->assertStringContainsString(
        sprintf('msgid "%s"', $msgid),
        $catalog,
        sprintf('"%s" must be registered in the translation catalogs.', $msgid)
      );
    }
  }

  /**
   * The column header reuses an msgid every catalog already carries.
   *
   * "Mega Menu" is already shipped for the settings tab, so the new column
   * needs no new header string and no translator round trip for it.
   */
  public function testTheColumnHeaderReusesAnExistingMsgid(): void {
    $catalog = $this->moduleFile('translations/bioland.en.po');

    $this->assertSame(
      1,
      substr_count($catalog, "\nmsgid \"Mega Menu\"\n"),
      'The header string must resolve to the single existing "Mega Menu" entry.'
    );
  }

}
