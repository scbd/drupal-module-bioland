<?php

namespace Drupal\bioland\Form;

use Drupal\bioland\Service\BiolandAdditionalTagDefaults;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Additional Tags settings for the Bioland module.
 */
class BiolandTagsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_tags_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'tags';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $form['tags_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional Tags Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Enable checkbox in its own box
    $form['tags_settings']['enable'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enable'),
      '#collapsible' => FALSE,
    ];

    $form['tags_settings']['enable']['enable_additional_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Additional Tags'),
      '#description' => $this->t('Add content-type specific additional tags (event statuses, project statuses, etc.) based on thesaurus content type.'),
      '#default_value' => $config->get('enable_additional_fields') !== FALSE,
    ];

    // The per-tag-group content type mappings are fixed for every Bioland site
    // (see BiolandAdditionalTagDefaults::CONTENT_TYPES), so they are not
    // exposed here; submitSectionForm() keeps them in config.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

    $config->set('enable_additional_fields', $values['enable_additional_fields']);

    // Re-assert the fixed content type mappings so config stays authoritative
    // even though the mappings are no longer editable through this form.
    foreach (BiolandAdditionalTagDefaults::CONTENT_TYPES as $key => $content_types) {
      $config->set('additional_tags.' . $key, $content_types);
    }
  }

}
