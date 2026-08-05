<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\Form\BiolandComponentMenuLinkForm;
use Drupal\bioland\Service\BiolandComponentMenuFormMode;
use Drupal\menu_link_content\Form\MenuLinkContentForm;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pins the two contracts the Component form class exists to preserve.
 *
 * 1. THE BASE FORM ID IS UNCHANGED. Registering an entity-form operation
 *    changes the concrete form id but not the base one, so
 *    menu_link_attributes_form_menu_link_content_form_alter() and
 *    bioland_form_alter() still fire on the Component form. If they stopped,
 *    the class textfield would vanish, the contrib entity builder would never
 *    run, and Component mode - which is applied from bioland_form_alter() -
 *    would never be applied at all.
 * 2. THE CLASS STAYS EMPTY. The activation seam says the operation string is
 *    the only thing this class contributes; all behaviour lives in
 *    BiolandComponentMenuFormMode, applied at alter time. A form() override
 *    here would run before every alter, and menu_link_attributes' entity
 *    builder would discard whatever it wrote on save.
 *
 * @covers \Drupal\bioland\Form\BiolandComponentMenuLinkForm
 */
class BiolandComponentMenuLinkFormTest extends TestCase {

  /**
   * Builds the form under test for an operation.
   *
   * @param string $operation
   *   The entity form operation.
   *
   * @return \Drupal\bioland\Form\BiolandComponentMenuLinkForm
   *   The form, bound to a menu_link_content entity double.
   */
  private function createForm(string $operation): BiolandComponentMenuLinkForm {
    $form = new BiolandComponentMenuLinkForm();
    $form->setEntity(new TestFormMenuLinkEntity());
    $form->setOperation($operation);

    return $form;
  }

  /**
   * Returns the class source.
   */
  private function source(): string {
    return file_get_contents((new ReflectionClass(BiolandComponentMenuLinkForm::class))->getFileName());
  }

  /**
   * The class is the core menu link form, specialised only by operation.
   */
  public function testExtendsTheCoreMenuLinkContentForm(): void {
    $this->assertInstanceOf(MenuLinkContentForm::class, $this->createForm(BiolandComponentMenuFormMode::OPERATION));
  }

  /**
   * The base form id survives the operation, so both alters still fire.
   */
  public function testBaseFormIdIsStillMenuLinkContentForm(): void {
    $form = $this->createForm(BiolandComponentMenuFormMode::OPERATION);

    $this->assertSame(
      'menu_link_content_form',
      $form->getBaseFormId(),
      'menu_link_attributes.module:16 and bioland_form_alter() both key on this base form id.'
    );
  }

  /**
   * The concrete form id carries the operation and keeps the module prefix.
   */
  public function testFormIdCarriesTheOperationAndKeepsTheModulePrefix(): void {
    $form = $this->createForm(BiolandComponentMenuFormMode::OPERATION);

    $this->assertSame('menu_link_content_menu_link_content_component_form', $form->getFormId());
    $this->assertStringStartsWith(
      'menu_link_content_',
      $form->getFormId(),
      'bioland_form_alter() tests this prefix to attach the cache-busting submit handler and hide menu_parent on translations.'
    );
  }

  /**
   * getFormId() concatenates entity type before bundle, matching core.
   *
   * menu_link_content's entity type id and bundle are both "menu_link_content",
   * so testFormIdCarriesTheOperationAndKeepsTheModulePrefix() cannot tell the
   * two possible concatenation orders apart. This double uses a distinct
   * bundle so a swap in EntityForm::getFormId() (bundle before entity type,
   * instead of core's entity type before bundle) would fail this assertion.
   */
  public function testFormIdConcatenatesEntityTypeBeforeBundle(): void {
    $form = new BiolandComponentMenuLinkForm();
    $form->setEntity(new TestFormMenuLinkEntityWithDistinctBundle());
    $form->setOperation(BiolandComponentMenuFormMode::OPERATION);

    $this->assertSame(
      'menu_link_content_custom_bundle_component_form',
      $form->getFormId(),
      'Core concatenates entity-type-id then bundle; a swap would produce "custom_bundle_menu_link_content_component_form" instead.'
    );
  }

  /**
   * The default operation is untouched - the regular add/edit form is intact.
   */
  public function testDefaultOperationFormIdIsUnchanged(): void {
    $form = $this->createForm('default');

    $this->assertSame('menu_link_content_menu_link_content_form', $form->getFormId());
    $this->assertSame('menu_link_content_form', $form->getBaseFormId());
  }

  /**
   * The form object reports the operation the dispatcher keys on.
   */
  public function testOperationIsVisibleToTheDispatcher(): void {
    $form = $this->createForm(BiolandComponentMenuFormMode::OPERATION);

    $this->assertSame(BiolandComponentMenuFormMode::OPERATION, $form->getOperation());
    $this->assertSame('component', BiolandComponentMenuFormMode::OPERATION);
  }

  /**
   * The class declares no methods of its own.
   *
   * Overriding form(), getFormId() or getBaseFormId() would break one of the
   * two contracts in the class docblock; declaring anything else means logic
   * has crept in that belongs in the service.
   */
  public function testTheClassDeclaresNoMethodsOfItsOwn(): void {
    $reflection = new ReflectionClass(BiolandComponentMenuLinkForm::class);
    $declared = array_filter(
      $reflection->getMethods(),
      fn($method): bool => $method->getDeclaringClass()->getName() === BiolandComponentMenuLinkForm::class
    );

    $this->assertSame(
      [],
      array_map(fn($method): string => $method->getName(), $declared),
      'BiolandComponentMenuLinkForm must stay empty: mode behaviour belongs in BiolandComponentMenuFormMode, applied at alter time.'
    );
  }

  /**
   * No picker, token or registry logic has leaked into the form class.
   */
  public function testNoPickerOrTokenLogicInTheFormClass(): void {
    $source = $this->source();

    $forbidden = [
      'component_registry',
      'BiolandComponentRegistry',
      'optionsFor',
      'stripComponentTokens',
      'mergeComponentToken',
      '#entity_builders',
      "'#type'",
      'bl2-component-',
      'mm-component-',
    ];

    foreach ($forbidden as $needle) {
      $this->assertStringNotContainsString(
        $needle,
        $source,
        sprintf('"%s" must not appear in BiolandComponentMenuLinkForm - the activation seam allows the operation marker only.', $needle)
      );
    }
  }

  /**
   * The "do not override form()" warning stays in the PHPDoc.
   */
  public function testTheDoNotOverrideWarningIsDocumented(): void {
    $this->assertStringContainsString('DO NOT OVERRIDE form()', $this->source());
  }

}

/**
 * menu_link_content entity double, enough for core's form-id derivation.
 */
class TestFormMenuLinkEntity {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return 'menu_link_content';
  }

  /**
   * The single (implicit) bundle, which shares the entity type's name.
   */
  public function bundle() {
    return 'menu_link_content';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return new TestFormMenuLinkEntityType();
  }

}

/**
 * Entity type double reporting the bundle key menu_link_content declares.
 */
class TestFormMenuLinkEntityType {

  /**
   * {@inheritdoc}
   */
  public function hasKey($key) {
    return $key === 'bundle';
  }

}

/**
 * Entity double whose bundle differs from its entity type id.
 *
 * TestFormMenuLinkEntity above shares the same string for both, which cannot
 * distinguish EntityForm::getFormId()'s concatenation order. This double can.
 */
class TestFormMenuLinkEntityWithDistinctBundle extends TestFormMenuLinkEntity {

  /**
   * {@inheritdoc}
   */
  public function bundle() {
    return 'custom_bundle';
  }

}
