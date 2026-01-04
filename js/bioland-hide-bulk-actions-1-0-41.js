/**
 * @file
 * Hides bulk actions for contributor role users.
 *
 * Removes the VBO action dropdown and apply button from views
 * when the current user has the contributor role.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  /**
   * Drupal behavior to hide bulk actions for contributors.
   */
  Drupal.behaviors.biolandHideBulkActions = {
    attach: function (context, settings) {
      // Check if user has the contributor role
      const userRoles = settings.user && settings.user.roles ? settings.user.roles : [];
      const isContributor = userRoles.includes('contributor');

      if (!isContributor) {
        return;
      }

      // Hide the bulk action dropdown (does NOT respect permissions)
      const actionDropdown = context.querySelector('.views-bulk-actions__item--preceding-actions');
      if (actionDropdown && !actionDropdown.dataset.biolandBulkHidden) {
        actionDropdown.dataset.biolandBulkHidden = 'true';
        actionDropdown.style.display = 'none';
      }

      // Hide the "Apply to selected items" button (does NOT respect permissions)
      const applyButton = context.querySelector('.views-bulk-actions__item.form-actions');
      if (applyButton && !applyButton.dataset.biolandBulkHidden) {
        applyButton.dataset.biolandBulkHidden = 'true';
        applyButton.style.display = 'none';
      }

      console.log('Bioland: Bulk actions hidden for contributor role');
    }
  };

})(Drupal, drupalSettings);
