<?php

/**
 * @file
 * User management for Bioland module installation.
 *
 * Contains functions for provisioning, blocking, and managing user accounts.
 * Also includes related update hooks.
 */

use Drupal\user\Entity\Role;

/**
 * Manage user account: create, update, or block.
 *
 * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
 *   The user entity storage.
 * @param string $email
 *   The user email (also used as username).
 * @param array $roles
 *   Array of role IDs to assign.
 * @param string $password
 *   The password to set.
 * @param string $action
 *   One of: 'create_only', 'create_or_update', 'block'.
 *
 * @return string|null
 *   Status message or NULL if no action taken.
 */
function _bioland_manage_user($user_storage, $email, array $roles, $password, $action = 'create_or_update') {
  // Find existing user by email or name.
  $existing = $user_storage->loadByProperties(['mail' => $email]);
  if (empty($existing)) {
    $existing = $user_storage->loadByProperties(['name' => $email]);
  }
  $user = !empty($existing) ? reset($existing) : NULL;

  // Handle block action.
  if ($action === 'block') {
    if (!$user) {
      return NULL;
    }
    // Remove all roles except authenticated.
    $current_roles = $user->getRoles(TRUE);
    foreach ($current_roles as $role_id) {
      $user->removeRole($role_id);
    }
    // Add system role.
    if (Role::load('system')) {
      $user->addRole('system');
    }
    $user->block();
    $user->save();
    return t('Blocked user @email and set to system role only.', ['@email' => $email]);
  }

  // Handle create_only action.
  if ($action === 'create_only' && $user) {
    return NULL;
  }

  // Create or update user.
  if ($user) {
    // Remove current roles.
    $current_roles = $user->getRoles(TRUE);
    foreach ($current_roles as $role_id) {
      $user->removeRole($role_id);
    }
    // Add new roles.
    foreach ($roles as $role_id) {
      if (Role::load($role_id)) {
        $user->addRole($role_id);
      }
    }
    $user->setPassword($password);
    $user->set('mail', $email);
    $user->activate();
    $user->save();
    return t('Updated user @email with roles: @roles.', [
      '@email' => $email,
      '@roles' => implode(', ', $roles),
    ]);
  }

  // Create new user.
  $user = $user_storage->create([
    'name' => $email,
    'mail' => $email,
    'pass' => $password,
    'status' => 1,
  ]);
  foreach ($roles as $role_id) {
    if (Role::load($role_id)) {
      $user->addRole($role_id);
    }
  }
  $user->save();
  return t('Created user @email with roles: @roles.', [
    '@email' => $email,
    '@roles' => implode(', ', $roles),
  ]);
}

/**
 * Helper function to provision users from environment variables.
 *
 * Handles creation and management of:
 * - E2E test users (env var: E2E_USERS)
 * - Bioland training users (env var: BIOLAND_USERS)
 * - Training users (env var: TRAINING_USERS)
 * - Legacy users to block
 * - SCBD staff users (individual env vars: AR, FV, DM, AP)
 * - Administrator users (individual env vars: RG, RH, RE, BF, VG, SB, API_USER)
 *
 * @return string
 *   Status messages about user provisioning.
 */
function _bioland_provision_users() {
  $messages = [];
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');

  // 1. E2E test users - create or update with E2E_USERS password.
  $e2e_password = getenv('E2E_USERS');
  if ($e2e_password) {
    $e2e_users = [
      'e2e-authenticated@chm-cbd.net' => ['system'],
      'e2e-contributor@chm-cbd.net' => ['system', 'contributor'],
      'e2e-content-manager@chm-cbd.net' => ['system', 'content_manager'],
      'e2e-site-manager@chm-cbd.net' => ['system', 'site_manager'],
      'e2e-scbd-staff@chm-cbd.net' => ['system', 'scbd_staff'],
    ];

    foreach ($e2e_users as $email => $roles) {
      $result = _bioland_create_or_update_user($user_storage, $email, $roles, $e2e_password);
      if ($result) {
        $messages[] = $result;
      }
    }
  }
  else {
    $messages[] = t('E2E_USERS env var not set; skipping E2E user creation.');
  }

  // 2. Bioland users - create or update with BIOLAND_USERS password.
  $bioland_password = getenv('BIOLAND_USERS');
  if ($bioland_password) {
    $bioland_users = [
      'bioland-contributor@chm-cbd.net' => ['system', 'contributor'],
      'bioland-cm@chm-cbd.net' => ['system', 'content_manager'],
      'bioland-sm@chm-cbd.net' => ['system', 'site_manager'],
      'bioland-scbd@chm-cbd.net' => ['system', 'scbd_staff'],
      'bioland@chm-cbd.net' => ['system'],
    ];

    foreach ($bioland_users as $email => $roles) {
      $result = _bioland_create_or_update_user($user_storage, $email, $roles, $bioland_password);
      if ($result) {
        $messages[] = $result;
      }
    }
  }
  else {
    $messages[] = t('BIOLAND_USERS env var not set; skipping Bioland user creation.');
  }

  // 3. Training users - create or update with TRAINING_USERS password.
  $training_password = getenv('TRAINING_USERS');
  if ($training_password) {
    $training_users = [
      'training-contributor@chm-cbd.net' => ['system', 'contributor'],
      'training-cm@chm-cbd.net' => ['system', 'content_manager'],
      'training-sm@chm-cbd.net' => ['system', 'site_manager'],
      'training@chm-cbd.net' => ['system'],
    ];

    foreach ($training_users as $email => $roles) {
      $result = _bioland_create_or_update_user($user_storage, $email, $roles, $training_password);
      if ($result) {
        $messages[] = $result;
      }
    }
  }
  else {
    $messages[] = t('TRAINING_USERS env var not set; skipping training user creation.');
  }

  // 4. Legacy users to block - if exists, set to system role only and block.
  $legacy_users_to_block = [
    'anastasia_yb@hotmail.com',
    'randy@houlahan.ca',
    'support@chm-cbd.net',
    'arafalov+cm@gmail.com',
    'alexandre.rafalovitch@cbd.int',
    'it@cbd.int',
    'ray.goh@cbd.int',
  ];

  foreach ($legacy_users_to_block as $email) {
    $result = _bioland_block_legacy_user($user_storage, $email);
    if ($result) {
      $messages[] = $result;
    }
  }

  // 5. SCBD staff users - create or update with scbd_staff role.
  $scbd_staff_users = [
    'alexandre.rafalovitch@un.org' => 'AR',
    'frederic.vogel@un.org' => 'FV',
    'djessy.monnier@un.org' => 'DM',
    'abhinav.prakash@un.org' => 'AP',
  ];

  foreach ($scbd_staff_users as $email => $env_var) {
    $password = getenv($env_var);
    if ($password) {
      $result = _bioland_create_or_update_user($user_storage, $email, ['scbd_staff'], $password);
      if ($result) {
        $messages[] = $result;
      }
    }
    else {
      $messages[] = t('@env_var env var not set; skipping @email.', [
        '@env_var' => $env_var,
        '@email' => $email,
      ]);
    }
  }

  // 6. Administrator users - create or update with administrator role.
  $admin_users = [
    'ray.goh@un.org' => 'RG',
    'randy.houlahan@un.org' => 'RH',
    'rodrigo.elias@un.org' => 'RE',
    'blaise.fonseca@un.org' => 'BF',
    'vince.gopez@un.org' => 'VG',
    'stephane.bilodeau@un.org' => 'SB',
    'api-user@chm-cbd.net' => 'API_USER',
  ];

  foreach ($admin_users as $email => $env_var) {
    $password = getenv($env_var);
    if ($password) {
      $result = _bioland_create_or_update_user($user_storage, $email, ['administrator'], $password);
      if ($result) {
        $messages[] = $result;
      }
    }
    else {
      $messages[] = t('@env_var env var not set; skipping @email.', [
        '@env_var' => $env_var,
        '@email' => $email,
      ]);
    }
  }

  // 7. Grant scbd_staff role to all @un.org users.
  $un_org_result = _bioland_grant_scbd_staff_to_un_org_users($user_storage);
  if ($un_org_result) {
    $messages[] = $un_org_result;
  }

  if (empty($messages)) {
    return t('No user provisioning actions taken.');
  }

  \Drupal::logger('bioland')->notice('User provisioning: @messages', [
    '@messages' => implode(' ', $messages),
  ]);

  return implode(' ', $messages);
}

/**
 * Create a user if they don't exist.
 *
 * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
 *   The user entity storage.
 * @param string $email
 *   The user email (also used as username and name).
 * @param array $roles
 *   Array of role IDs to assign.
 * @param string $password
 *   The password to set.
 *
 * @return string|null
 *   Status message or NULL if user already exists.
 */
function _bioland_create_user_if_not_exists($user_storage, $email, array $roles, $password) {
  return _bioland_manage_user($user_storage, $email, $roles, $password, 'create_only');
}

/**
 * Grant scbd_staff role to all users with @un.org email addresses.
 *
 * @param \Drupal\user\UserStorageInterface $user_storage
 *   The user storage service.
 *
 * @return string|null
 *   Status message or NULL if no actions taken.
 */
function _bioland_grant_scbd_staff_to_un_org_users($user_storage) {
  // Check if scbd_staff role exists.
  if (!Role::load('scbd_staff')) {
    return NULL;
  }

  $count = 0;
  $updated_users = [];

  // Load all active users.
  $query = $user_storage->getQuery()
    ->condition('status', 1)
    ->accessCheck(FALSE);
  $uids = $query->execute();

  foreach ($user_storage->loadMultiple($uids) as $user) {
    $email = $user->getEmail();
    
    // Check if email ends with @un.org.
    if ($email && preg_match('/@un\.org$/i', $email)) {
      if (!$user->hasRole('scbd_staff')) {
        $user->addRole('scbd_staff');
        $user->save();
        $updated_users[] = $email;
        $count++;
      }
    }
  }

  if ($count > 0) {
    return t('Granted scbd_staff role to @count @un.org user(s): @users', [
      '@count' => $count,
      '@users' => implode(', ', $updated_users),
    ]);
  }

  return NULL;
}

/**
 * Block a legacy user and set to system role only.
 *
 * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
 *   The user entity storage.
 * @param string $email
 *   The user email to block.
 *
 * @return string|null
 *   Status message or NULL if user doesn't exist.
 */
function _bioland_block_legacy_user($user_storage, $email) {
  return _bioland_manage_user($user_storage, $email, [], '', 'block');
}

/**
 * Create or update a user with specific roles and password.
 *
 * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
 *   The user entity storage.
 * @param string $email
 *   The user email (also used as username and name).
 * @param array $roles
 *   Array of role IDs to assign (replaces existing roles).
 * @param string $password
 *   The password to set.
 *
 * @return string
 *   Status message.
 */
function _bioland_create_or_update_user($user_storage, $email, array $roles, $password) {
  return _bioland_manage_user($user_storage, $email, $roles, $password, 'create_or_update');
}

/**
 * Helper function to block @cbd.int users except excluded ones.
 *
 * Finds all users with @cbd.int email domain (except randy.houlahan@cbd.int),
 * assigns them the system role, removes other roles, and blocks them.
 *
 * @return string
 *   Status message.
 */
function _bioland_block_cbd_int_users() {
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $excluded_emails = ['randy.houlahan@cbd.int'];

  // Find all users with @cbd.int email.
  $query = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('mail', '%@cbd.int', 'LIKE');
  $uids = $query->execute();

  if (empty($uids)) {
    return t('No @cbd.int users found.');
  }

  $blocked_count = 0;
  $users = $user_storage->loadMultiple($uids);

  foreach ($users as $user) {
    $email = $user->getEmail();

    // Skip excluded emails.
    if (in_array($email, $excluded_emails)) {
      continue;
    }

    // Skip user 1 (super admin).
    if ((int) $user->id() === 1) {
      continue;
    }

    // Remove all roles except authenticated.
    $current_roles = $user->getRoles(TRUE);
    foreach ($current_roles as $role_id) {
      $user->removeRole($role_id);
    }

    // Add system role.
    if (Role::load('system')) {
      $user->addRole('system');
    }

    // Block the user.
    $user->block();
    $user->save();
    $blocked_count++;
  }

  if ($blocked_count > 0) {
    \Drupal::logger('bioland')->notice('Blocked @count @cbd.int users.', ['@count' => $blocked_count]);
    return t('Blocked @count @cbd.int users (set to system role).', ['@count' => $blocked_count]);
  }

  return t('No @cbd.int users needed blocking.');
}

/*
 * =============================================================================
 * UPDATE HOOKS - User Management
 * =============================================================================
 */

/**
 * Provision test, training, and staff user accounts.
 *
 * Creates or updates users based on environment variables for E2E testing,
 * Bioland training, staff access, and administrator accounts.
 *
 * @version 1.0.30
 */
function bioland_update_9021() {
  return _bioland_provision_users();
}

/**
 * Block legacy @cbd.int users and configure Search API.
 */
function bioland_update_9025() {
  $messages = [];

  // Block @cbd.int users.
  $result = _bioland_block_cbd_int_users();
  if ($result) {
    $messages[] = $result;
  }

  // Configure Search API index.
  $result = _bioland_configure_search_api_index();
  if ($result) {
    $messages[] = $result;
  }

  // Configure facets.
  $result = _bioland_configure_facets();
  if ($result) {
    $messages[] = $result;
  }

  return implode(' ', $messages);
}

/**
 * Ensure all bioland users have the system role.
 */
function bioland_update_9030() {
  $messages = [];
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  
  // Define bioland users that should have the system role.
  $bioland_users = [
    'bioland-contributor@chm-cbd.net' => ['system', 'contributor'],
    'bioland-cm@chm-cbd.net' => ['system', 'content_manager'],
    'bioland-sm@chm-cbd.net' => ['system', 'site_manager'],
    'bioland-scbd@chm-cbd.net' => ['system', 'scbd_staff'],
    'bioland@chm-cbd.net' => ['system'],
  ];
  
  foreach ($bioland_users as $email => $expected_roles) {
    $users = $user_storage->loadByProperties(['mail' => $email]);
    if ($user = reset($users)) {
      $changed = FALSE;
      foreach ($expected_roles as $role) {
        if (!$user->hasRole($role)) {
          $user->addRole($role);
          $changed = TRUE;
        }
      }
      
      if ($changed) {
        $user->save();
        $messages[] = t('Added missing roles to user @email', ['@email' => $email]);
      }
      else {
        $messages[] = t('User @email already has all required roles', ['@email' => $email]);
      }
    }
    else {
      $messages[] = t('User @email does not exist (skipped)', ['@email' => $email]);
    }
  }
  
  return implode("\n", $messages);
}

/**
 * Grant scbd_staff role to all users with @un.org email addresses.
 */
function bioland_update_9031() {
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $result = _bioland_grant_scbd_staff_to_un_org_users($user_storage);
  return $result ?: t('No @un.org users found or all already have scbd_staff role.');
}
