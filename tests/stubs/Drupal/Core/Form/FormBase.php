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
   * The messenger.
   *
   * @var object|null
   */
  protected $messenger;

  /**
   * Gets the messenger service.
   *
   * Mirrors \Drupal\Core\Messenger\MessengerTrait: an injected messenger
   * wins, otherwise the container's is resolved once and cached. Tests that
   * need to assert on the messages a form raises inject a recording double
   * through setMessenger().
   *
   * @return object
   *   The messenger.
   */
  protected function messenger() {
    if (!isset($this->messenger)) {
      $this->messenger = \Drupal::messenger();
    }

    return $this->messenger;
  }

  /**
   * Sets the messenger.
   *
   * @param object $messenger
   *   The messenger.
   *
   * @return $this
   */
  public function setMessenger($messenger) {
    $this->messenger = $messenger;

    return $this;
  }

}
