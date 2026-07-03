<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Field Visibility settings for the Bioland module.
 */
class BiolandFieldVisibilityForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_field_visibility_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'field_visibility';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $form['field_visibility'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field Visibility Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Enable checkbox in its own box
    $form['field_visibility']['enable'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enable'),
      '#collapsible' => FALSE,
    ];

    $form['field_visibility']['enable']['enable_field_visibility'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Field Visibility Control'),
      '#description' => $this->t('Show/hide fields based on content type selection. Configure which fields are visible for each content type below.'),
      '#default_value' => $config->get('enable_field_visibility') !== FALSE,
    ];

    // Get content types from taxonomy vocabulary 'tags'.
    $content_types = $this->getContentTypeOptions();

    // URL Field visibility settings - as collapsible details
    $form['field_visibility']['url_field'] = [
      '#type' => 'details',
      '#title' => $this->t('URL Field'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['field_visibility']['url_field']['url_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show URL field for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('field_visibility.url_content_types') ?: [2, 3, 5, 12, 13, 15, 16, 43, 44, 45, 46, 47, 48, 49, 50],
    ];

    // Published Field visibility settings - as collapsible details
    $form['field_visibility']['published_field'] = [
      '#type' => 'details',
      '#title' => $this->t('Published Field'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['field_visibility']['published_field']['published_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Published field for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('field_visibility.published_content_types') ?: [3, 5, 12],
    ];

    // Date Range Field visibility settings - as collapsible details
    $form['field_visibility']['date_range_field'] = [
      '#type' => 'details',
      '#title' => $this->t('Date Range Field (Start & End Date)'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="enable_field_visibility"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['field_visibility']['date_range_field']['date_range_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show Date Range fields (Start & End Date) for these content types:'),
      '#options' => $content_types,
      '#default_value' => $config->get('field_visibility.date_range_content_types') ?: [2, 3, 13],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

    // Process field visibility content type selections
    $url_content_types = array_values(array_filter($values['url_content_types']));
    $published_content_types = array_values(array_filter($values['published_content_types']));
    $date_range_content_types = array_values(array_filter($values['date_range_content_types']));

    $config
      ->set('enable_field_visibility', $values['enable_field_visibility'])
      ->set('field_visibility.url_content_types', $url_content_types)
      ->set('field_visibility.published_content_types', $published_content_types)
      ->set('field_visibility.date_range_content_types', $date_range_content_types);
  }

}
