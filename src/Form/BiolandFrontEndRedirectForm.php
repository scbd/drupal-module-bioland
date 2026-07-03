<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Redirects the parent Front End tab to the Front End General sub-tab.
 */
class BiolandFrontEndRedirectForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Redirect to the first sub-tab (Front End General) when accessing the parent Front End tab
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
      \Drupal\Core\Url::fromRoute('bioland.settings.front_end.general')->toString()
    );
    $response->send();
    exit;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    // Unreachable: buildForm() redirects and exits before this is ever called.
    // Required by the abstract base class.
    return $form;
  }

}
