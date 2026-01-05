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

      console.log('Bioland: Hide bulk actions - user roles:', userRoles, 'isContributor:', isContributor);

      if (!isContributor) {
        console.log('Bioland: User is not contributor, bulk actions remain visible');
        return;
      }

      // Hide ALL bulk action dropdowns (does NOT respect permissions)
      // Use querySelectorAll to find all instances in context
      const actionDropdowns = context.querySelectorAll('.views-bulk-actions__item--preceding-actions');
      console.log('Bioland: Found', actionDropdowns.length, 'bulk action dropdowns to hide');
      
      actionDropdowns.forEach(function(actionDropdown) {
        if (!actionDropdown.dataset.biolandBulkHidden) {
          actionDropdown.dataset.biolandBulkHidden = 'true';
          actionDropdown.style.display = 'none';
          console.log('Bioland: Hid bulk action dropdown');
        }
      });

      // Hide ALL "Apply to selected items" buttons (does NOT respect permissions)
      const applyButtons = context.querySelectorAll('.views-bulk-actions__item.form-actions');
      console.log('Bioland: Found', applyButtons.length, 'apply buttons to hide');
      
      applyButtons.forEach(function(applyButton) {
        if (!applyButton.dataset.biolandBulkHidden) {
          applyButton.dataset.biolandBulkHidden = 'true';
          applyButton.style.display = 'none';
          console.log('Bioland: Hid apply button');
        }
      });

      if (actionDropdowns.length > 0 || applyButtons.length > 0) {
        console.log('Bioland: Bulk actions hidden for contributor role');
      } else {
        console.warn('Bioland: No bulk action elements found to hide');
      }
    }
  };

})(Drupal, drupalSettings);
