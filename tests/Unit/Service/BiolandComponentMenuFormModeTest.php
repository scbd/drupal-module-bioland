<?php

namespace Drupal\Tests\bioland\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\bioland\Service\BiolandComponentRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the inert Component-menu form mode service.
 *
 * Three things are pinned here beyond ordinary behaviour coverage:
 *   1. the dispatcher declines every form reachable today, so merging this
 *      task changes nothing a user can see;
 *   2. the entity builder is registered AFTER menu_link_attributes', which is
 *      the only reason the picked token survives a save;
 *   3. a declined form is left byte-identical, form array and stored options
 *      alike.
 *
 * @covers \Drupal\bioland\Service\BiolandComponentMenuFormMode
 */
class BiolandComponentMenuFormModeTest extends TestCase {

  /**
   * The registry under the service (a real instance; it is pure).
   *
   * @var \Drupal\bioland\Service\BiolandComponentRegistry
   */
  protected $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->registry = new BiolandComponentRegistry();
  }

  /**
   * Builds the service for a site flavour and permission state.
   */
  protected function createService(bool $isBsl = FALSE, bool $hasPermission = TRUE, string $class = BiolandComponentMenuFormMode::class): BiolandComponentMenuFormMode {
    $config = new ImmutableConfig('bioland.settings', ['is_biosafety_land' => $isBsl]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('bioland.settings')->willReturn($config);

    return new $class($this->registry, $configFactory, new TestAccount($hasPermission));
  }

  /**
   * Builds a form state over a menu link entity.
   */
  protected function createFormState($classValue = NULL, string $operation = 'default', bool $isNew = FALSE, bool $isDefaultTranslation = TRUE): TestFormState {
    $options = $classValue === NULL ? [] : ['attributes' => ['class' => $classValue]];
    $entity = new TestMenuLink($options, $isNew, $isDefaultTranslation);

    return new TestFormState(new TestFormObject($entity, $operation));
  }

  /**
   * Builds the form array a contrib-altered menu link form would carry.
   */
  protected function contribAlteredForm($classDefault = ''): array {
    return [
      '#entity_builders' => [
        'menu_link_attributes' => 'menu_link_attributes_menu_link_content_form_entity_builder',
      ],
      'title' => ['#type' => 'textfield', '#title' => 'Menu link title'],
      'options' => [
        'attributes' => [
          '#type' => 'details',
          '#title' => 'Attributes',
          '#tree' => TRUE,
          'class' => ['#type' => 'textfield', '#default_value' => $classDefault],
          'target' => ['#type' => 'select', '#default_value' => ''],
        ],
      ],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];
  }

  /**
   * Runs a faithful stand-in for menu_link_attributes' entity builder.
   *
   * Mirrors menu_link_attributes.module:135-200: link.options.attributes is
   * rebuilt wholesale from the contrib form values, class is wrapped as an
   * array, empty values are pruned, and the result is copied onto every
   * translation. Any bioland merge has to survive this.
   */
  protected function runContribEntityBuilder(TestMenuLink $entity, TestFormState $formState): void {
    $attributes = $formState->getValue('attributes') ?: [];
    foreach ($attributes as $name => $value) {
      if ($name === 'class' && !is_array($value)) {
        $attributes[$name] = [(string) $value];
      }
    }

    $grouped = ['attributes' => $attributes];
    foreach ($grouped as $group => $groupAttributes) {
      foreach ($groupAttributes as $name => $value) {
        if (is_array($value)) {
          $filtered = array_filter($value);
          if ($filtered) {
            $grouped[$group][$name] = $filtered;
          }
          else {
            unset($grouped[$group][$name]);
          }
        }
        elseif (mb_strlen((string) $value) === 0) {
          unset($grouped[$group][$name]);
        }
      }
    }

    $options = array_filter($grouped);
    $entity->link->first()->options = $options;
    foreach ($entity->getTranslationLanguages() as $language) {
      if ($language->getId() !== $entity->language()->getId()) {
        $entity->getTranslation($language->getId())->link->first()->options = $options;
      }
    }
  }

  /* ------------------------------------------------------------------ */
  /* Dispatcher.                                                         */
  /* ------------------------------------------------------------------ */

  /**
   * The shipped edit-detection gate is off: nothing reachable today applies.
   */
  public function testEditDetectionShipsDisabled(): void {
    $this->assertFalse(
      BiolandComponentMenuFormMode::EDIT_DETECTION,
      'p02-01 must ship inert; task p03-02 flips this constant.'
    );
  }

  /**
   * The dispatcher declines every form that exists today.
   *
   * @dataProvider declinedFormProvider
   */
  public function testDispatcherDeclines(string $operation, $classValue, bool $isNew, bool $isDefaultTranslation, bool $hasPermission): void {
    $service = $this->createService(FALSE, $hasPermission);
    $formState = $this->createFormState($classValue, $operation, $isNew, $isDefaultTranslation);

    $this->assertFalse($service->applies($formState));
  }

  /**
   * Data provider for testDispatcherDeclines().
   */
  public function declinedFormProvider(): array {
    return [
      'regular add form' => ['default', NULL, TRUE, TRUE, TRUE],
      'regular edit form' => ['edit', ['login cooperation'], FALSE, TRUE, TRUE],
      'edit form of an existing component link, detection off' => ['edit', ['bl2-component-bch'], FALSE, TRUE, TRUE],
      'legacy component link, detection off' => ['edit', ['mm-component-bch'], FALSE, TRUE, TRUE],
      'component operation on a non-default translation' => ['component', NULL, TRUE, FALSE, TRUE],
      'component operation without the permission' => ['component', NULL, TRUE, TRUE, FALSE],
    ];
  }

  /**
   * A form that is not a menu_link_content entity form is declined.
   */
  public function testDispatcherDeclinesForeignForms(): void {
    $service = $this->createService();

    $otherEntity = new TestFormState(new TestFormObject(new TestMenuLink([], TRUE, TRUE, 'node'), 'component'));
    $this->assertFalse($service->applies($otherEntity), 'A node form must never enter Component mode.');

    $plainForm = new TestFormState(new \stdClass());
    $this->assertFalse($service->applies($plainForm), 'A non-entity form has no entity to inspect.');

    $noForm = new TestFormState(NULL);
    $this->assertFalse($service->applies($noForm));
  }

  /**
   * The dedicated add flow's operation switches the mode on.
   */
  public function testDispatcherAcceptsComponentOperation(): void {
    $service = $this->createService();

    $this->assertTrue($service->applies($this->createFormState(NULL, 'component', TRUE)));
  }

  /**
   * With edit detection forced on, a stored component token switches it on.
   */
  public function testDispatcherAcceptsDetectedComponentLink(): void {
    $service = $this->createService(FALSE, TRUE, EditDetectingComponentMenuFormMode::class);

    $this->assertTrue(
      $service->applies($this->createFormState(['mm-component-bch login'], 'edit')),
      'A legacy-spelled token must be detected too.'
    );
    $this->assertTrue($service->applies($this->createFormState(['login bl2-component-was-removed'], 'edit')));
    $this->assertFalse(
      $service->applies($this->createFormState(['login cooperation'], 'edit')),
      'A regular link carries no component token.'
    );
    $this->assertFalse(
      $service->applies($this->createFormState(NULL, 'default', TRUE)),
      'Detection only applies to an existing link.'
    );
  }

  /**
   * On a BSL site the site-prefixed token family is detected as well.
   */
  public function testDispatcherDetectsSitePrefixedTokenOnBslSite(): void {
    $bsl = $this->createService(TRUE, TRUE, EditDetectingComponentMenuFormMode::class);
    $chm = $this->createService(FALSE, TRUE, EditDetectingComponentMenuFormMode::class);

    $this->assertTrue($bsl->applies($this->createFormState(['bsl-component-bch'], 'edit')));
    $this->assertFalse(
      $chm->applies($this->createFormState(['bsl-component-bch'], 'edit')),
      'The site family only exists for the running site identifier.'
    );
  }

  /* ------------------------------------------------------------------ */
  /* apply(): picker injection.                                          */
  /* ------------------------------------------------------------------ */

  /**
   * A Bioland site is offered every component, in registry order.
   */
  public function testPickerOffersEveryComponentOnChmSite(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $picker = $form[BiolandComponentMenuFormMode::PICKER_ELEMENT];
    $this->assertSame('select', $picker['#type']);
    $this->assertSame('Mega-menu component', $picker['#title']);
    $this->assertSame('The component this menu link renders in the mega menu.', $picker['#description']);
    $this->assertTrue($picker['#required']);
    $this->assertNull($picker['#default_value']);
    $this->assertSame($this->registry->optionsFor(FALSE), $picker['#options']);
  }

  /**
   * A BSL site is offered the narrowed list only.
   */
  public function testPickerNarrowsOnBslSite(): void {
    $service = $this->createService(TRUE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $options = $form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#options'];
    $this->assertSame($this->registry->optionsFor(TRUE), $options);
    $this->assertArrayHasKey('bl2-component-bch', $options);
    $this->assertArrayNotHasKey('bl2-component-national-report', $options);
  }

  /**
   * A stored canonical token preselects its own option.
   */
  public function testPickerPreselectsStoredCanonicalToken(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login bl2-component-forums bl2-2x'], 'component');
    $form = $this->contribAlteredForm('login bl2-component-forums bl2-2x');

    $service->apply($form, $formState);

    $this->assertSame('bl2-component-forums', $form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#default_value']);
  }

  /**
   * A legacy mm- spelling of a known component preselects its canonical entry.
   */
  public function testPickerMapsLegacySpellingToCanonicalOption(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['mm-component-bch login'], 'component');
    $form = $this->contribAlteredForm('mm-component-bch login');

    $service->apply($form, $formState);

    $picker = $form[BiolandComponentMenuFormMode::PICKER_ELEMENT];
    $this->assertSame('bl2-component-bch', $picker['#default_value']);
    $this->assertArrayNotHasKey('mm-component-bch', $picker['#options'], 'No stale option is added for a resolvable spelling.');
  }

  /**
   * An unknown component-shaped token survives as a marked current value.
   */
  public function testPickerPreservesUnknownTokenAsLegacyOption(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login bl2-component-was-removed'], 'component');
    $form = $this->contribAlteredForm('login bl2-component-was-removed');

    $service->apply($form, $formState);

    $picker = $form[BiolandComponentMenuFormMode::PICKER_ELEMENT];
    $this->assertSame('bl2-component-was-removed', $picker['#default_value']);
    $this->assertSame('Legacy: bl2-component-was-removed', $picker['#options']['bl2-component-was-removed']);
    $this->assertSame(
      'bl2-component-was-removed',
      array_key_first($picker['#options']),
      'The preserved current value is listed first.'
    );
  }

  /**
   * A known component hidden by the BSL narrowing is never silently dropped.
   */
  public function testPickerPreservesBslHiddenTokenAsCurrentValue(): void {
    $service = $this->createService(TRUE);
    $formState = $this->createFormState(['bl2-component-national-report'], 'component');
    $form = $this->contribAlteredForm('bl2-component-national-report');

    $service->apply($form, $formState);

    $picker = $form[BiolandComponentMenuFormMode::PICKER_ELEMENT];
    $this->assertSame('bl2-component-national-report', $picker['#default_value']);
    $this->assertSame(
      'National Reports (not available on this site)',
      $picker['#options']['bl2-component-national-report']
    );
    $this->assertArrayHasKey('bl2-component-bch', $picker['#options'], 'The narrowed list is still offered alongside it.');
  }

  /**
   * The raw class textfield shows only the classes the picker does not own.
   */
  public function testClassTextfieldIsStrippedOfComponentTokens(): void {
    $service = $this->createService(FALSE);
    $stored = ['login cooperation bl2-component-forums bl2-2x mm-component-bch'];
    $formState = $this->createFormState($stored, 'component');
    $form = $this->contribAlteredForm('login cooperation bl2-component-forums bl2-2x mm-component-bch');

    $service->apply($form, $formState);

    $this->assertSame(
      'login cooperation bl2-2x',
      $form['options']['attributes']['class']['#default_value']
    );
  }

  /**
   * With no contrib fieldset there is nothing to strip and nothing is created.
   */
  public function testClassTextfieldStripIsSkippedWhenAbsent(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login bl2-component-forums'], 'component');
    $form = ['#entity_builders' => []];

    $service->apply($form, $formState);

    $this->assertArrayNotHasKey('options', $form);
  }

  /**
   * The mode owns its styling: wrapper class, marker, intro and library.
   */
  public function testModeAttachesItsOwnChrome(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $this->assertContains(BiolandComponentMenuFormMode::FORM_CLASS, $form['#attributes']['class']);
    $this->assertSame('component', $form['#attributes'][BiolandComponentMenuFormMode::FORM_MODE_ATTRIBUTE]);
    $this->assertSame(['bioland/component_menu_form'], $form['#attached']['library']);
    $this->assertSame(
      'This menu link renders a mega-menu component instead of a plain list of child links.',
      $form[BiolandComponentMenuFormMode::INTRO_ELEMENT]['text']['#plain_text']
    );
  }

  /**
   * Per-option descriptions are listed, in registry order, as escaped text.
   */
  public function testPerOptionDescriptionsAreRenderedAsPlainText(): void {
    $service = $this->createService(TRUE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $items = $form[BiolandComponentMenuFormMode::DESCRIPTIONS_ELEMENT]['list']['#items'];
    $this->assertCount(count($this->registry->optionsFor(TRUE)), $items);
    $this->assertSame('BCH Records', $items[0]['label']['#plain_text']);
    $this->assertSame(
      $this->registry->getDescription('bch'),
      $items[0]['description']['#plain_text']
    );
  }

  /**
   * Picker chrome relies on default Form API escaping, never on raw markup.
   *
   * Option labels and descriptions come from the registry and are emitted as
   * plain translatable strings; nothing widens #allowed_tags, sets #markup or
   * marks anything safe.
   */
  public function testPickerChromeCarriesNoRawMarkup(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['bl2-component-<script>'], 'component');
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $keys = $this->collectKeys([
      $form[BiolandComponentMenuFormMode::PICKER_ELEMENT],
      $form[BiolandComponentMenuFormMode::DESCRIPTIONS_ELEMENT],
      $form[BiolandComponentMenuFormMode::INTRO_ELEMENT],
    ]);
    foreach (['#allowed_tags', '#markup', '#children', '#printed'] as $unsafe) {
      $this->assertNotContains($unsafe, $keys, "Component mode must not set $unsafe.");
    }
    $this->assertContains('#plain_text', $keys, 'Text output goes through the escaping render property.');

    foreach ($form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#options'] as $label) {
      $this->assertIsString($label, 'Option labels are plain translatable strings.');
    }
    $this->assertSame(
      'Legacy: bl2-component-<script>',
      $form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#options']['bl2-component-<script>'],
      'A hostile stored token is passed through untouched for the renderer to escape.'
    );
  }

  /**
   * Collects every array key in a nested render array, at any depth.
   */
  protected function collectKeys(array $subtree): array {
    $keys = [];
    foreach ($subtree as $key => $value) {
      $keys[] = $key;
      if (is_array($value)) {
        $keys = array_merge($keys, $this->collectKeys($value));
      }
    }
    return $keys;
  }

  /* ------------------------------------------------------------------ */
  /* Entity builder registration order.                                  */
  /* ------------------------------------------------------------------ */

  /**
   * The bioland builder is registered after menu_link_attributes'.
   *
   * #entity_builders run in array order, and the contrib builder rebuilds
   * link.options.attributes wholesale — running before it would lose the
   * picked token. bioland_module_implements_alter() is what makes this hold in
   * a real request; here the alter-processed fixture stands in for it.
   */
  public function testEntityBuilderRunsAfterContribBuilder(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);

    $builders = array_values($form['#entity_builders']);
    $contrib = array_search('menu_link_attributes_menu_link_content_form_entity_builder', $builders, TRUE);
    $ours = array_search(BiolandComponentMenuFormMode::ENTITY_BUILDER, $builders, TRUE);

    $this->assertIsInt($contrib);
    $this->assertIsInt($ours);
    $this->assertLessThan($ours, $contrib, 'The bioland builder must run after the contrib builder.');
  }

  /**
   * Re-applying the mode on a form rebuild registers one builder, not two.
   */
  public function testEntityBuilderRegistrationIsIdempotent(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $form = $this->contribAlteredForm();

    $service->apply($form, $formState);
    $service->apply($form, $formState);

    $this->assertCount(2, $form['#entity_builders']);
  }

  /**
   * bioland.module wires the dispatcher inside the menu link form block.
   */
  public function testModuleWiresDispatcherWithOrderingPin(): void {
    $module = file_get_contents(dirname(__DIR__, 3) . '/bioland.module');

    $this->assertMatchesRegularExpression(
      '/strpos\(\$form_id, \'menu_link_content_\'\) === 0.*bioland\.component_menu_form_mode.*->applies\(\$form_state\).*->apply\(\$form, \$form_state\)/s',
      $module,
      'The dispatcher must be called from the menu_link_content_ block of bioland_form_alter().'
    );
    $this->assertStringContainsString(
      'ORDERING PIN',
      $module,
      'The reliance on bioland_module_implements_alter() running bioland last must stay documented.'
    );
    $this->assertMatchesRegularExpression(
      '/function bioland_module_implements_alter\(&\$implementations, \$hook\)/',
      $module
    );
  }

  /* ------------------------------------------------------------------ */
  /* Save path.                                                          */
  /* ------------------------------------------------------------------ */

  /**
   * The picked token survives the contrib builder, on both storage shapes.
   *
   * @dataProvider mergeSurvivalProvider
   */
  public function testPickedTokenSurvivesContribEntityBuilder($classFormValue, array $expectedClass): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login cooperation bl2-component-forums bl2-2x'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $form = $this->contribAlteredForm();
    $service->apply($form, $formState);

    // The editor picks a different component and saves.
    $formState->setValue('attributes', ['class' => $classFormValue, 'target' => '']);
    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, 'bl2-component-bch');

    $this->runContribEntityBuilder($entity, $formState);
    $service->buildEntity($entity, $formState);

    $stored = $entity->link->first()->options['attributes']['class'];
    $this->assertSame($expectedClass, $stored);
    $this->assertSame(
      ['bl2-component-bch'],
      $this->registry->findComponentTokens($stored),
      'Exactly one component token is stored.'
    );
    $this->assertSame(
      ['login', 'cooperation', 'bl2-2x'],
      $this->registry->stripComponentTokens($stored),
      'Every foreign token survives byte-identically, in order.'
    );
  }

  /**
   * Data provider for testPickedTokenSurvivesContribEntityBuilder().
   *
   * The first row is the menu_link_attributes shape (a one-element array
   * holding the space-separated string); the second is a true token array.
   */
  public function mergeSurvivalProvider(): array {
    return [
      'packed string shape' => [
        'login cooperation bl2-2x',
        ['login cooperation bl2-2x bl2-component-bch'],
      ],
      'token array shape' => [
        ['login', 'cooperation', 'bl2-2x'],
        ['login', 'cooperation', 'bl2-2x', 'bl2-component-bch'],
      ],
    ];
  }

  /**
   * A link with no other classes gets the contrib storage shape created.
   */
  public function testPickedTokenIsStoredWhenNoOtherClassesExist(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(NULL, 'component', TRUE);
    $entity = $formState->getFormObject()->getEntity();
    $form = $this->contribAlteredForm();
    $service->apply($form, $formState);

    $formState->setValue('attributes', ['class' => '', 'target' => '']);
    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, 'bl2-component-forums');

    $this->runContribEntityBuilder($entity, $formState);
    $service->buildEntity($entity, $formState);

    $this->assertSame(['bl2-component-forums'], $entity->link->first()->options['attributes']['class']);
  }

  /**
   * An unchanged save of a legacy token writes the legacy spelling back.
   */
  public function testUnchangedSavePreservesLegacyTokenVerbatim(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login bl2-component-was-removed'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $form = $this->contribAlteredForm();
    $service->apply($form, $formState);

    // The editor changes nothing: the picker resubmits the preserved value and
    // the class textfield resubmits its stripped display value.
    $formState->setValue('attributes', ['class' => 'login', 'target' => '']);
    $formState->setValue(
      BiolandComponentMenuFormMode::PICKER_ELEMENT,
      $form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#default_value']
    );

    $this->runContribEntityBuilder($entity, $formState);
    $service->buildEntity($entity, $formState);

    $this->assertSame(
      ['login bl2-component-was-removed'],
      $entity->link->first()->options['attributes']['class']
    );
  }

  /**
   * An unchanged save of a BSL-hidden token keeps it on the link.
   */
  public function testUnchangedSavePreservesBslHiddenToken(): void {
    $service = $this->createService(TRUE);
    $formState = $this->createFormState(['bl2-component-national-report login'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $form = $this->contribAlteredForm();
    $service->apply($form, $formState);

    $formState->setValue('attributes', ['class' => 'login', 'target' => '']);
    $formState->setValue(
      BiolandComponentMenuFormMode::PICKER_ELEMENT,
      $form[BiolandComponentMenuFormMode::PICKER_ELEMENT]['#default_value']
    );

    $this->runContribEntityBuilder($entity, $formState);
    $service->buildEntity($entity, $formState);

    $this->assertSame(
      ['login bl2-component-national-report'],
      $entity->link->first()->options['attributes']['class']
    );
  }

  /**
   * A submitted value that is neither offered nor preserved is ignored.
   *
   * Core already rejects an out-of-#options select value; this is the second
   * gate, so nothing arbitrary can reach a class attribute.
   */
  public function testUnofferedSubmittedValueIsIgnored(): void {
    $service = $this->createService(TRUE);
    $formState = $this->createFormState(['login'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $form = $this->contribAlteredForm();
    $service->apply($form, $formState);

    $formState->setValue('attributes', ['class' => 'login', 'target' => '']);
    // Hidden on BSL, and not the token this link stores.
    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, 'bl2-component-national-report');

    $this->runContribEntityBuilder($entity, $formState);
    $service->buildEntity($entity, $formState);

    $this->assertSame(['login'], $entity->link->first()->options['attributes']['class']);
  }

  /**
   * An empty picker value leaves the stored options alone.
   */
  public function testEmptySubmittedValueLeavesOptionsUntouched(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login'], 'component');
    $entity = $formState->getFormObject()->getEntity();

    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, '');
    $service->buildEntity($entity, $formState);

    $this->assertSame(['attributes' => ['class' => ['login']]], $entity->link->first()->options);
  }

  /**
   * The merged options are copied onto every translation.
   */
  public function testMergedOptionsAreCopiedToTranslations(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['login'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $entity->addTestTranslation('fr', ['attributes' => ['class' => ['stale']]]);
    $entity->addTestTranslation('es', ['attributes' => ['class' => ['stale']]]);

    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, 'bl2-component-bch');
    $service->buildEntity($entity, $formState);

    $expected = ['attributes' => ['class' => ['login bl2-component-bch']]];
    $this->assertSame($expected, $entity->getTranslation('fr')->link->first()->options);
    $this->assertSame($expected, $entity->getTranslation('es')->link->first()->options);
  }

  /**
   * The static builder adapter delegates to the container service.
   */
  public function testStaticEntityBuilderDelegatesToTheService(): void {
    $service = $this->createService(FALSE);
    \Drupal::setService('bioland.component_menu_form_mode', $service);

    $formState = $this->createFormState(['login'], 'component');
    $entity = $formState->getFormObject()->getEntity();
    $formState->setValue(BiolandComponentMenuFormMode::PICKER_ELEMENT, 'bl2-component-bch');
    $form = [];

    BiolandComponentMenuFormMode::entityBuilder('menu_link_content', $entity, $form, $formState);
    \Drupal::resetContainer();

    $this->assertSame(
      ['login bl2-component-bch'],
      $entity->link->first()->options['attributes']['class']
    );
  }

  /* ------------------------------------------------------------------ */
  /* Byte-identical round trip for regular links.                        */
  /* ------------------------------------------------------------------ */

  /**
   * A declined form is left byte-identical, form array and storage alike.
   *
   * Reproduces the module wiring: dispatcher first, apply() only if it says
   * yes. Nothing about a regular menu link may change.
   */
  public function testDeclinedFormIsByteIdentical(): void {
    $service = $this->createService(FALSE);
    $stored = ['login cooperation bl2-content-type-news bl2-2x'];
    $formState = $this->createFormState($stored, 'edit');
    $entity = $formState->getFormObject()->getEntity();

    $form = $this->contribAlteredForm('login cooperation bl2-content-type-news bl2-2x');
    $before = $form;
    $storedBefore = $entity->link->first()->options;

    if ($service->applies($formState)) {
      $service->apply($form, $formState);
    }

    $this->assertSame($before, $form, 'The form array is untouched, key order included.');
    $this->assertSame($storedBefore, $entity->link->first()->options);

    // And the save path is untouched too: only the contrib builder runs.
    $formState->setValue('attributes', ['class' => 'login cooperation bl2-content-type-news bl2-2x', 'target' => '']);
    $this->runContribEntityBuilder($entity, $formState);

    $this->assertSame($storedBefore, $entity->link->first()->options);
    $this->assertArrayNotHasKey('bioland_component', $form);
    $this->assertSame(['menu_link_attributes'], array_keys($form['#entity_builders']));
  }

  /**
   * An existing component link is equally untouched while detection is off.
   */
  public function testExistingComponentLinkIsUntouchedWhileDetectionIsOff(): void {
    $service = $this->createService(FALSE);
    $formState = $this->createFormState(['bl2-component-bch login'], 'edit');
    $form = $this->contribAlteredForm('bl2-component-bch login');
    $before = $form;

    if ($service->applies($formState)) {
      $service->apply($form, $formState);
    }

    $this->assertSame($before, $form);
  }

}

/**
 * Test subclass with the edit-detection gate forced on (p03-02's flip).
 */
class EditDetectingComponentMenuFormMode extends BiolandComponentMenuFormMode {

  public const EDIT_DETECTION = TRUE;

}

/**
 * Minimal account double: the service only asks about one permission.
 */
class TestAccount implements AccountProxyInterface {

  /**
   * Whether the account holds the menu link attributes permission.
   *
   * @var bool
   */
  protected $granted;

  public function __construct(bool $granted) {
    $this->granted = $granted;
  }

  public function hasPermission($permission) {
    return $permission === BiolandComponentMenuFormMode::PERMISSION && $this->granted;
  }

  public function getAccount() {
    return $this;
  }

  public function id() {
    return 1;
  }

}

/**
 * Form state double exposing the entity form object the dispatcher inspects.
 */
class TestFormState implements FormStateInterface {

  /**
   * The form object, or NULL for a form that has none.
   *
   * @var mixed
   */
  protected $formObject;

  /**
   * Submitted values.
   *
   * @var array
   */
  protected $values = [];

  /**
   * Arbitrary form state storage.
   *
   * @var array
   */
  protected $storage = [];

  public function __construct($formObject) {
    $this->formObject = $formObject;
  }

  public function getFormObject() {
    return $this->formObject;
  }

  public function getValues() {
    return $this->values;
  }

  public function getValue($key, $default = NULL) {
    return $this->values[$key] ?? $default;
  }

  public function setValue($key, $value) {
    $this->values[$key] = $value;
    return $this;
  }

  public function get($key) {
    return $this->storage[$key] ?? NULL;
  }

  public function set($key, $value) {
    $this->storage[$key] = $value;
    return $this;
  }

  public function setErrorByName($name, $message = '') {
    return $this;
  }

  public function getErrors() {
    return [];
  }

  public function setRedirect($route_name, array $route_parameters = [], array $options = []) {
    return $this;
  }

}

/**
 * Entity form double.
 */
class TestFormObject {

  /**
   * The entity the form edits.
   *
   * @var mixed
   */
  protected $entity;

  /**
   * The form operation.
   *
   * @var string
   */
  protected $operation;

  public function __construct($entity, string $operation) {
    $this->entity = $entity;
    $this->operation = $operation;
  }

  public function getEntity() {
    return $this->entity;
  }

  public function getOperation() {
    return $this->operation;
  }

}

/**
 * Menu link content double carrying a link field with an options array.
 */
class TestMenuLink {

  /**
   * The link field.
   *
   * @var \Drupal\Tests\bioland\Unit\Service\TestLinkField
   */
  public $link;

  /**
   * Whether the entity is unsaved.
   *
   * @var bool
   */
  protected $isNew;

  /**
   * Whether this is the default translation.
   *
   * @var bool
   */
  protected $defaultTranslation;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Translations keyed by langcode.
   *
   * @var array
   */
  protected $translations = [];

  /**
   * This object's langcode.
   *
   * @var string
   */
  protected $langcode;

  public function __construct(array $options, bool $isNew = FALSE, bool $defaultTranslation = TRUE, string $entityTypeId = 'menu_link_content', string $langcode = 'en') {
    $this->link = new TestLinkField($options);
    $this->isNew = $isNew;
    $this->defaultTranslation = $defaultTranslation;
    $this->entityTypeId = $entityTypeId;
    $this->langcode = $langcode;
  }

  /**
   * Adds a translation of this link, for the mirroring tests.
   */
  public function addTestTranslation(string $langcode, array $options): void {
    $this->translations[$langcode] = new self($options, FALSE, FALSE, $this->entityTypeId, $langcode);
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  public function isNew() {
    return $this->isNew;
  }

  public function isDefaultTranslation() {
    return $this->defaultTranslation;
  }

  public function isDefaultTranslationAffectedOnly() {
    return TRUE;
  }

  public function language() {
    return new Language($this->langcode, strtoupper($this->langcode));
  }

  public function getTranslationLanguages() {
    $languages = [$this->langcode => $this->language()];
    foreach ($this->translations as $langcode => $translation) {
      $languages[$langcode] = $translation->language();
    }
    return $languages;
  }

  public function getTranslation($langcode) {
    return $this->translations[$langcode] ?? $this;
  }

}

/**
 * Link field double.
 */
class TestLinkField {

  /**
   * The single field item.
   *
   * @var \Drupal\Tests\bioland\Unit\Service\TestLinkItem
   */
  protected $item;

  public function __construct(array $options) {
    $this->item = new TestLinkItem($options);
  }

  public function isEmpty() {
    return FALSE;
  }

  public function first() {
    return $this->item;
  }

}

/**
 * Link field item double holding the options array.
 */
class TestLinkItem {

  /**
   * The link options.
   *
   * @var array
   */
  public $options;

  public function __construct(array $options) {
    $this->options = $options;
  }

}
