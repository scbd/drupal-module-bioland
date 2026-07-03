<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Configure Front End General settings for the Bioland module.
 */
class BiolandFrontEndGeneralForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_front_end_general_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'front_end_general';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $form['front_end_general_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Front End General'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Promote and Sticky section (moved from system_functions)
    $form['front_end_general_settings']['promote_sticky_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Promote and Sticky Icon Visibility'),
      '#open' => FALSE,
    ];

    $form['front_end_general_settings']['promote_sticky_section']['promote_sticky_description'] = [
      '#markup' => '<p>' . $this->t('Promote and Sticky flags on records can be seen by public or anonymous users.') . '</p>',
    ];

    $form['front_end_general_settings']['promote_sticky_section']['promote_and_sticky_public'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Promote and Sticky flags to public users'),
      '#default_value' => $config->get('config.promote_and_sticky_public') !== FALSE,
    ];

    // Content Ordering Reset section (moved from system_functions)
    $form['front_end_general_settings']['ordering_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Ordering Reset'),
      '#open' => FALSE,
    ];

    // Wrapper for AJAX messages
    $form['front_end_general_settings']['ordering_section']['messages_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'front-end-general-messages'],
    ];

    $form['front_end_general_settings']['ordering_section']['ordering_description'] = [
      '#markup' => '<p>' . $this->t('Resets the field_order value to 10000 for all nodes. This creates a new revision for each node with the revision message "Reset ordering". The original last updated date and user are preserved. Use this to normalize content ordering across the site.') . '</p>',
    ];

    $form['front_end_general_settings']['ordering_section']['reset_ordering'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Ordering'),
      '#submit' => ['::submitResetOrdering'],
      '#ajax' => [
        'callback' => '::ajaxResetOrdering',
        'wrapper' => 'front-end-general-messages',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Resetting ordering values...'),
        ],
      ],
    ];

    // Reset Promoted Content section
    $form['front_end_general_settings']['promoted_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Reset Promoted Content'),
      '#open' => FALSE,
    ];

    $form['front_end_general_settings']['promoted_section']['promoted_description'] = [
      '#markup' => '<p>' . $this->t('Removes all the promoted flags from every content entry.') . '</p>',
    ];

    $form['front_end_general_settings']['promoted_section']['reset_promoted'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Promoted'),
      '#submit' => ['::submitResetPromoted'],
      '#ajax' => [
        'callback' => '::ajaxResetPromoted',
        'wrapper' => 'front-end-general-messages',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Resetting promoted flags...'),
        ],
      ],
    ];

    // Reset Sticky Content section
    $form['front_end_general_settings']['sticky_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Reset Sticky Content'),
      '#open' => FALSE,
    ];

    $form['front_end_general_settings']['sticky_section']['sticky_description'] = [
      '#markup' => '<p>' . $this->t('Removes all the sticky flags from every content entry.') . '</p>',
    ];

    $form['front_end_general_settings']['sticky_section']['reset_sticky'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Sticky'),
      '#submit' => ['::submitResetSticky'],
      '#ajax' => [
        'callback' => '::ajaxResetSticky',
        'wrapper' => 'front-end-general-messages',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Resetting sticky flags...'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();
    // Save Promote and Sticky settings (moved from system_functions)
    $config
      ->set('config.promote_and_sticky_public', (bool) ($values['promote_and_sticky_public'] ?? TRUE));
  }

  /**
   * Submit handler for reset ordering.
   */
  public function submitResetOrdering(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllNodeOrdering();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_ordering_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset field_order to 1000 for @count nodes.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset ordering.
   */
  public function ajaxResetOrdering(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_ordering_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset field_order to 1000 for @count nodes.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for reset promoted.
   */
  public function submitResetPromoted(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllPromotedFlags();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_promoted_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset promoted flag to 0 for @count content entries.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset promoted.
   */
  public function ajaxResetPromoted(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_promoted_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset promoted flag to 0 for @count content entries.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Submit handler for reset sticky.
   */
  public function submitResetSticky(array &$form, FormStateInterface $form_state) {
    $count = $this->resetAllStickyFlags();
    // Store count in form state for AJAX callback.
    $form_state->set('reset_sticky_count', $count);
    $this->messenger()->addStatus($this->t('Successfully reset sticky flag to 0 for @count content entries.', ['@count' => $count]));
  }

  /**
   * AJAX callback for reset sticky.
   */
  public function ajaxResetSticky(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $count = $form_state->get('reset_sticky_count') ?: 0;
    $response->addCommand(new MessageCommand($this->t('Successfully reset sticky flag to 0 for @count content entries.', ['@count' => $count]), NULL, ['type' => 'status']));
    return $response;
  }

  /**
   * Resets field_order to 1000 for all nodes while preserving timestamps.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllNodeOrdering() {
    $count = 0;
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Load all nodes that have field_order.
    $query = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->exists('field_order');

    $nids = $query->execute();

    if (empty($nids)) {
      return 0;
    }

    // Process nodes in batches to avoid memory issues.
    $batch_size = 50;
    $nid_chunks = array_chunk($nids, $batch_size);

    foreach ($nid_chunks as $chunk) {
      $nodes = $node_storage->loadMultiple($chunk);

      foreach ($nodes as $node) {
        // Check if field_order exists and if value is different from 1000.
        if (!$node->hasField('field_order')) {
          continue;
        }

        $current_value = $node->get('field_order')->value;
        if ($current_value == 1000) {
          // Already set to 1000, skip.
          continue;
        }

        // Store original values.
        $original_changed = $node->getChangedTime();
        $original_uid = $node->getRevisionUserId();

        // Set new revision.
        $node->setNewRevision(TRUE);
        $node->set('field_order', 1000);
        $node->setRevisionLogMessage($this->t('Reset ordering'));
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());

        // Save the node (this will trigger hooks).
        $node->save();

        // Now restore the original changed time and uid using direct database update.
        // This preserves the "last updated" display while having proper revision history.
        $this->database->update('node_field_data')
          ->fields([
            'changed' => $original_changed,
            'uid' => $original_uid,
          ])
          ->condition('nid', $node->id())
          ->execute();

        // Also update for translations if any.
        $this->database->update('node_field_data')
          ->fields([
            'changed' => $original_changed,
          ])
          ->condition('nid', $node->id())
          ->execute();

        $count++;
      }
    }

    // Clear node cache after bulk updates.
    $node_storage->resetCache($nids);

    \Drupal::logger('bioland')->notice('Reset field_order to 1000 for @count nodes.', ['@count' => $count]);

    return $count;
  }

  /**
   * Resets promoted flag to 0 for all content nodes.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllPromotedFlags() {
    try {
      // Use direct database update for efficiency.
      $count = $this->database->update('node_field_data')
        ->fields(['promoted' => 0])
        ->condition('type', 'content')
        ->condition('promoted', 1)
        ->execute();

      \Drupal::logger('bioland')->info('Reset promoted flag for @count content entries.', ['@count' => $count]);
      return $count;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to reset promoted flags: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while resetting promoted flags. Please check the logs.'));
      return 0;
    }
  }

  /**
   * Resets sticky flag to 0 for all content nodes.
   *
   * @return int
   *   The number of nodes updated.
   */
  protected function resetAllStickyFlags() {
    try {
      // Use direct database update for efficiency.
      $count = $this->database->update('node_field_data')
        ->fields(['sticky' => 0])
        ->condition('type', 'content')
        ->condition('sticky', 1)
        ->execute();

      \Drupal::logger('bioland')->info('Reset sticky flag for @count content entries.', ['@count' => $count]);
      return $count;
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to reset sticky flags: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while resetting sticky flags. Please check the logs.'));
      return 0;
    }
  }

}
