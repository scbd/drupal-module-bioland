<?php

/**
 * @file
 * Role and permission management for Bioland module installation.
 *
 * Contains functions for creating roles, granting permissions, and managing
 * maintenance mode access. Also includes related update hooks.
 */

use Drupal\user\Entity\Role;

/**
 * Ensure custom roles exist, creating them if missing.
 *
 * @param array $roles
 *   Associative array of role_id => role_label.
 *
 * @return array
 *   Array of status messages.
 */
function _bioland_ensure_roles_exist(array $roles) {
  $messages = [];
  foreach ($roles as $rid => $label) {
    if (!Role::load($rid)) {
      $role = Role::create([
        'id' => $rid,
        'label' => $label,
      ]);
      $role->save();
      $messages[] = t('Created role: @role', ['@role' => $label]);
    }
  }
  return $messages;
}

/**
 * Grant permissions to roles based on a permission matrix.
 *
 * @param array $permission_matrix
 *   Associative array of role_id => [permission1, permission2, ...].
 *
 * @return array
 *   Array of status messages.
 */
function _bioland_grant_role_permissions(array $permission_matrix) {
  $messages = [];
  foreach ($permission_matrix as $rid => $permissions) {
    $role = Role::load($rid);
    if ($role) {
      $changed = FALSE;
      foreach ($permissions as $permission) {
        if (!$role->hasPermission($permission)) {
          $role->grantPermission($permission);
          $changed = TRUE;
        }
      }
      if ($changed) {
        $role->save();
        $messages[] = t('Granted bioland permissions to @role.', ['@role' => $rid]);
      }
    }
  }
  return $messages;
}

/**
 * Get the standard Bioland roles definition.
 *
 * @return array
 *   Associative array of role_id => role_label.
 */
function _bioland_get_standard_roles() {
  return [
    'scbd_staff' => 'SCBD Staff',
    'site_manager' => 'Site Manager',
    'content_manager' => 'Content Manager',
    'contributor' => 'Contributor',
    'system' => 'System',
  ];
}

/**
 * Get the standard Bioland permission matrix.
 *
 * @return array
 *   Associative array of role_id => [permissions].
 */
function _bioland_get_standard_permission_matrix() {
  $permissions = [
    'administer bioland settings',
    'administer bioland field settings',
    'administer bioland translation settings',
    'access administration pages',
    'view the administration theme',
    'access site reports',
  ];
  return [
    'administrator' => $permissions,
    'site_manager' => $permissions,
    'content_manager' => $permissions,
    'scbd_staff' => $permissions,
  ];
}

/**
 * Configure maintenance mode access permissions.
 *
 * Grants 'access site in maintenance mode' to administrator and scbd_staff only.
 * Revokes the permission from other roles.
 *
 * @return array
 *   Array of status messages.
 */
function _bioland_configure_maintenance_mode_access() {
  $messages = [];
  
  // Grant permission to administrator and scbd_staff.
  $permission_matrix = [
    'administrator' => ['access site in maintenance mode'],
    'scbd_staff' => ['access site in maintenance mode'],
  ];
  
  $perm_messages = _bioland_grant_role_permissions($permission_matrix);
  $messages = array_merge($messages, $perm_messages);
  
  // Revoke permission from other roles if they have it.
  $roles_to_revoke = ['site_manager', 'content_manager', 'contributor', 'system', 'authenticated'];
  foreach ($roles_to_revoke as $rid) {
    $role = Role::load($rid);
    if ($role && $role->hasPermission('access site in maintenance mode')) {
      $role->revokePermission('access site in maintenance mode');
      $role->save();
      $messages[] = t('Revoked maintenance mode access from @role.', ['@role' => $rid]);
    }
  }
  
  return $messages;
}

/**
 * Helper function to sync scbd_staff permissions with site_manager.
 *
 * Copies all permissions from site_manager role to scbd_staff role,
 * ensuring scbd_staff has equivalent access.
 *
 * @return string
 *   Status message about permissions sync.
 */
function _bioland_sync_scbd_staff_permissions() {
  $site_manager = Role::load('site_manager');
  $scbd_staff = Role::load('scbd_staff');

  if (!$site_manager) {
    return t('site_manager role not found; cannot sync permissions.');
  }

  if (!$scbd_staff) {
    return t('scbd_staff role not found; cannot sync permissions.');
  }

  $site_manager_permissions = $site_manager->getPermissions();
  $scbd_staff_permissions = $scbd_staff->getPermissions();

  $added_count = 0;
  foreach ($site_manager_permissions as $permission) {
    if (!in_array($permission, $scbd_staff_permissions)) {
      $scbd_staff->grantPermission($permission);
      $added_count++;
    }
  }

  if ($added_count > 0) {
    $scbd_staff->save();
    \Drupal::logger('bioland')->notice('Synced @count permissions from site_manager to scbd_staff.', [
      '@count' => $added_count,
    ]);
    return t('Added @count permissions to scbd_staff (synced with site_manager).', ['@count' => $added_count]);
  }

  return t('scbd_staff already has all site_manager permissions.');
}

/*
 * =============================================================================
 * UPDATE HOOKS - Role & Permission Management
 * =============================================================================
 */

/**
 * Ensure SCBD Staff role exists on existing installs.
 */
function bioland_update_9006() {
  $messages = _bioland_ensure_roles_exist(['scbd_staff' => 'SCBD Staff']);
  return empty($messages) ? t('SCBD Staff role (scbd_staff) already exists; no changes made.') : implode(' ', $messages);
}

/**
 * Ensure all Bioland roles and permissions are configured.
 *
 * Creates roles: scbd_staff, site_manager, content_manager, contributor, system.
 * Grants bioland permissions to administrator, site_manager, content_manager, scbd_staff.
 *
 * @version 1.0.30
 */
function bioland_update_9020() {
  $messages = [];

  // Ensure custom roles exist.
  $role_messages = _bioland_ensure_roles_exist(_bioland_get_standard_roles());
  $messages = array_merge($messages, $role_messages);

  // Grant permissions.
  $perm_messages = _bioland_grant_role_permissions(_bioland_get_standard_permission_matrix());
  $messages = array_merge($messages, $perm_messages);

  return empty($messages) ? t('All roles and permissions already configured.') : implode(' ', $messages);
}

/**
 * Grant maintenance mode access to administrator and scbd_staff roles only.
 */
function bioland_update_9032() {
  $messages = _bioland_configure_maintenance_mode_access();
  
  if (empty($messages)) {
    return t('Maintenance mode permissions already configured correctly.');
  }
  
  return implode("\n", $messages);
}

/**
 * Grant admin/config access permissions to scbd_staff, site_manager, and content_manager.
 */
function bioland_update_9033() {
  $permission_matrix = [
    'scbd_staff' => [
      'access administration pages',
      'view the administration theme',
      'access site reports',
    ],
    'site_manager' => [
      'access administration pages',
      'view the administration theme',
      'access site reports',
    ],
    'content_manager' => [
      'access administration pages',
      'view the administration theme',
      'access site reports',
    ],
  ];
  
  $messages = _bioland_grant_role_permissions($permission_matrix);
  
  if (empty($messages)) {
    return t('Admin access permissions already granted.');
  }
  
  return implode("\n", $messages);
}

/**
 * Sync scbd_staff permissions with site_manager role.
 *
 * @version 1.0.30
 */
function bioland_update_9045() {
  return _bioland_sync_scbd_staff_permissions();
}
