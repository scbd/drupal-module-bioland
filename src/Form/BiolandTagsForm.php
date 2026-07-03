<?php

namespace Drupal\bioland\Form;

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

    // Get content type options for tag configuration
    $content_types = $this->getContentTypeOptions();

    // Event Status tag settings
    $form['tags_settings']['event_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Event Status'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags_settings']['event_status']['event_status_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Event Status tags for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('additional_tags.event_status_content_types') ?: [3],
    ];

    // Project Status tag settings
    $form['tags_settings']['project_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Project Status'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags_settings']['project_status']['project_status_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Project Status tags for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('additional_tags.project_status_content_types') ?: [5],
    ];

    // Organization Type tag settings
    $form['tags_settings']['organization_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Organization Type'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags_settings']['organization_types']['organization_types_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Organization Type tags for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('additional_tags.organization_types_content_types') ?: [8],
    ];

    // Ecosystem Type tag settings
    $form['tags_settings']['ecosystem_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Ecosystem Type'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags_settings']['ecosystem_types']['ecosystem_types_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Ecosystem Type tags for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('additional_tags.ecosystem_types_content_types') ?: [9],
    ];

    // Document Type tag settings
    $form['tags_settings']['document_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Document Type'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_additional_fields"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags_settings']['document_types']['document_types_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Document Type tags for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('additional_tags.document_types_content_types') ?: [12],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

    // Process additional tags content type selections
    $event_status_content_types = array_values(array_filter($values['event_status_content_types']));
    $project_status_content_types = array_values(array_filter($values['project_status_content_types']));
    $organization_types_content_types = array_values(array_filter($values['organization_types_content_types']));
    $ecosystem_types_content_types = array_values(array_filter($values['ecosystem_types_content_types']));
    $document_types_content_types = array_values(array_filter($values['document_types_content_types']));

    $config
      ->set('enable_additional_fields', $values['enable_additional_fields'])
      ->set('additional_tags.event_status_content_types', $event_status_content_types)
      ->set('additional_tags.project_status_content_types', $project_status_content_types)
      ->set('additional_tags.organization_types_content_types', $organization_types_content_types)
      ->set('additional_tags.ecosystem_types_content_types', $ecosystem_types_content_types)
      ->set('additional_tags.document_types_content_types', $document_types_content_types);
  }

}
