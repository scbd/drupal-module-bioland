<?php

namespace Drupal\Core\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Stub class for FormBase.
 */
abstract class FormBase implements FormInterface {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  abstract public function getFormId();

  /**
   * {@inheritdoc}
   */
  abstract public function buildForm(array $form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Default implementation does nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default implementation does nothing.
  }

  /**
   * Gets the messenger service.
   *
   * @return object
   *   The messenger.
   */
  protected function messenger() {
    return new class {
      public function addMessage($message, $type = 'status') {}
      public function addStatus($message) {}
      public function addWarning($message) {}
      public function addError($message) {}
    };
  }

}
