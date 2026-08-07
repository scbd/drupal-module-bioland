<?php

namespace Drupal\bioland\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Admin settings for the Bioland module.
 */
class BiolandAdminSettingsForm extends BiolandSettingsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bioland_settings_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getSection(): string {
    return 'admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSectionForm(array $form, FormStateInterface $form_state, $config): array {
    $form['admin_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Admin Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // Site email configuration
    $site_config = $this->configFactory()->getEditable('system.site');
    $form['admin_settings']['site_email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site Email'),
      '#collapsible' => FALSE,
    ];

    $form['admin_settings']['site_email']['site_mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => $site_config->get('mail'),
      '#description' => $this->t("The <em>From</em> address in automated e-mails sent during registration and new password requests, and other notifications. (Use an address ending in your site's domain to help prevent this e-mail being flagged as spam.)"),
      '#required' => TRUE,
    ];

    // Auto Summary functionality
    $form['admin_settings']['auto_summary'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Auto Summary'),
      '#collapsible' => FALSE,
    ];

    $form['admin_settings']['auto_summary']['enable_auto_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Auto Summary'),
      '#description' => $this->t('Automatically generate summary from body content as user types.'),
      '#default_value' => $config->get('enable_auto_summary') !== FALSE,
    ];

    // Biosafety Land setting
    $form['admin_settings']['biosafety_land'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Biosafety Land'),
      '#collapsible' => FALSE,
    ];

    $form['admin_settings']['biosafety_land']['is_biosafety_land'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is Biosafety Land?'),
      '#description' => $this->t('Indicates whether this is a Biosafety Land instance.'),
      '#default_value' => $config->get('is_biosafety_land') ?: FALSE,
    ];

    // Countries configuration
    $form['admin_settings']['countries'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Countries'),
      '#collapsible' => FALSE,
    ];

    $form['admin_settings']['countries']['countries'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Countries'),
      '#description' => $this->t('Enter one country code per line.'),
      '#default_value' => implode("\n", (array) ($config->get('countries') ?: ['lk'])),
      '#required' => TRUE,
    ];

    // Debug Logging settings
    $form['admin_settings']['debug_logging'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Debug Logging'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['admin_settings']['debug_logging']['enable_debug_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debug Logging'),
      '#description' => $this->t('Enable console.log output for Bioland JavaScript features. Useful for debugging.'),
      '#default_value' => $config->get('enable_debug_logging') ?: FALSE,
    ];

    $form['admin_settings']['debug_logging']['debug_log_areas'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Debug Log Areas'),
      '#description' => $this->t('Select which areas should output debug logs. Only effective when Debug Logging is enabled.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_debug_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_field_visibility'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Field Visibility'),
      '#description' => $this->t('Log field visibility show/hide operations.'),
      '#default_value' => $config->get('debug_log_areas.field_visibility') !== FALSE,
    ];

    $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_additional_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Additional Fields'),
      '#description' => $this->t('Log additional fields mounting and content type changes.'),
      '#default_value' => $config->get('debug_log_areas.additional_fields') !== FALSE,
    ];

    $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_auto_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto Summary'),
      '#description' => $this->t('Log auto summary generation and CKEditor connections.'),
      '#default_value' => $config->get('debug_log_areas.auto_summary') !== FALSE,
    ];

    $form['admin_settings']['debug_logging']['debug_log_areas']['debug_log_help_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Help Comments'),
      '#description' => $this->t('Log help comment insertion and visibility changes.'),
      '#default_value' => $config->get('debug_log_areas.help_comments') !== FALSE,
    ];

    // Main Menu Lock settings
    $form['admin_settings']['main_menu_lock'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Main Menu Lock'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['admin_settings']['main_menu_lock']['main_menu_lock_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lock Main Menu Editing'),
      '#description' => $this->t('Prevents scbd_staff, site_manager and content_manager from editing the main menu. When unchecked, these roles will be granted the "Administer Main navigation menu items" permission.'),
      '#default_value' => $config->get('main_menu_lock') !== FALSE,
    ];

    // Component menu link authoring. The flag is read by
    // \Drupal\bioland\Access\BiolandComponentMenuAccessCheck, which gates the
    // add-component route; core computes each local action's #access from that
    // route, so denying it both hides the action and blocks the URL.
    $form['admin_settings']['component_menu'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mega Menu'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['admin_settings']['component_menu']['component_menu_add_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Component Menu Links'),
      '#description' => $this->t('Shows the "Add Mega Menu component" action on the menu manage screen. When unchecked, the action and its form are unavailable.'),
      '#default_value' => $config->get('component_menu_add_enabled') !== FALSE,
    ];

    // Sub-setting of the switch above, but deliberately NOT gated on it with
    // '#states': turning the add flow off does not retire the component menu
    // links a site already has, and this setting still governs their edit
    // form, so it has to stay visible and reachable either way.
    // Default OFF - the picker owns the component token, so the raw contrib
    // Attributes fieldset is hidden from the component form unless a site
    // explicitly opts back in (read by BiolandComponentMenuFormMode::apply()).
    // Cast rather than compared to TRUE: a settings.php override bypasses
    // config schema casting, so an int 1 must still count as on.
    $form['admin_settings']['component_menu']['component_menu_show_attributes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Attributes'),
      '#description' => $this->t('Shows the Attributes section on the component menu link form. Hidden by default; the component picker manages these values.'),
      '#default_value' => (bool) $config->get('component_menu_show_attributes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitSectionForm(array &$form, FormStateInterface $form_state, $config): void {
    $values = $form_state->getValues();

    // Update system.site mail configuration
    $site_config = $this->configFactory()->getEditable('system.site');
    $site_config->set('mail', $values['site_mail'])->save();

    // Handle countries field - normalize textarea input
    $countries = array_filter(array_map('trim', explode("\n", $values['countries'])));

    $config
      ->set('enable_auto_summary', $values['enable_auto_summary'])
      ->set('is_biosafety_land', $values['is_biosafety_land'])
      ->set('countries', array_values($countries))
      ->set('enable_debug_logging', $values['enable_debug_logging'])
      ->set('debug_log_areas.field_visibility', $values['debug_log_field_visibility'])
      ->set('debug_log_areas.additional_fields', $values['debug_log_additional_fields'])
      ->set('debug_log_areas.auto_summary', $values['debug_log_auto_summary'])
      ->set('debug_log_areas.help_comments', $values['debug_log_help_comments'])
      ->set('main_menu_lock', $values['main_menu_lock_enabled'])
      ->set('component_menu_add_enabled', $values['component_menu_add_enabled'])
      ->set('component_menu_show_attributes', $values['component_menu_show_attributes']);

    // Handle Main Menu Lock permission changes
    $this->handleMainMenuLockPermissions($values['main_menu_lock_enabled']);
  }

  /**
   * Handle Main Menu Lock permission changes.
   *
   * @param bool $lock_enabled
   *   TRUE if main menu lock is enabled, FALSE if disabled.
   */
  protected function handleMainMenuLockPermissions($lock_enabled) {
    $permission = 'administer main menu items';
    $roles_to_manage = ['scbd_staff', 'site_manager'];

    try {
      $role_storage = $this->entityTypeManager->getStorage('user_role');

      foreach ($roles_to_manage as $role_id) {
        /** @var \Drupal\user\RoleInterface $role */
        $role = $role_storage->load($role_id);

        if ($role) {
          if ($lock_enabled) {
            // Lock enabled: Remove permission
            if ($role->hasPermission($permission)) {
              $role->revokePermission($permission);
              $role->save();
              \Drupal::logger('bioland')->info('Removed "@permission" permission from @role role.', [
                '@permission' => $permission,
                '@role' => $role_id,
              ]);
            }
          }
          else {
            // Lock disabled: Grant permission
            if (!$role->hasPermission($permission)) {
              $role->grantPermission($permission);
              $role->save();
              \Drupal::logger('bioland')->info('Granted "@permission" permission to @role role.', [
                '@permission' => $permission,
                '@role' => $role_id,
              ]);
            }
          }
        }
        else {
          \Drupal::logger('bioland')->warning('Role @role not found when managing main menu lock permissions.', [
            '@role' => $role_id,
          ]);
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bioland')->error('Failed to manage main menu lock permissions: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while updating menu permissions. Please check the logs.'));
    }
  }

}
