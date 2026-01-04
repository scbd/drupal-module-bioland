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
      // Check if user is contributor
      const biolandSettings = settings.bioland || {};
      const isContributor = biolandSettings.isContributor || false;

      if (!isContributor) {
        return;
      }

      // Target the specific bulk action elements
      const bulkActionSelectors = [
        // The dropdown select with actions
        '.views-bulk-actions__item--preceding-actions',
        'div.views-bulk-actions__item.js-form-item.form-item.js-form-type-select',
        'select[data-drupal-selector="edit-action"]',
        '#edit-action',
        // The apply button
        '.views-bulk-actions__item.form-actions',
        'input[data-drupal-selector="edit-submit"][value="Apply to selected items"]',
        '#edit-submit',
        // Entire bulk actions form wrapper
        '.views-form',
        'form.views-form'
      ];

      // Hide each element
      bulkActionSelectors.forEach(function(selector) {
        const elements = context.querySelectorAll(selector);
        elements.forEach(function(element) {
          // Check if already hidden to prevent re-processing
          if (element.dataset.biolandBulkHidden) {
            return;
          }
          
          element.dataset.biolandBulkHidden = 'true';
          element.style.display = 'none';
          
          // Also hide parent containers if they become empty
          const parent = element.closest('.views-bulk-actions__item');
          if (parent) {
            parent.style.display = 'none';
          }
        });
      });

      // More aggressive: Hide any element containing "Apply to selected items"
      const submitButtons = context.querySelectorAll('input[type="submit"]');
      submitButtons.forEach(function(button) {
        if (button.value && button.value.includes('Apply to selected items')) {
          if (!button.dataset.biolandBulkHidden) {
            button.dataset.biolandBulkHidden = 'true';
            button.style.display = 'none';
            
            // Hide parent form-actions wrapper
            const wrapper = button.closest('.form-actions');
            if (wrapper) {
              wrapper.style.display = 'none';
            }
          }
        }
      });

      // Hide the action select dropdown by ID or name
      const actionSelect = context.querySelector('select[name="action"]');
      if (actionSelect && !actionSelect.dataset.biolandBulkHidden) {
        actionSelect.dataset.biolandBulkHidden = 'true';
        actionSelect.style.display = 'none';
        
        // Hide parent container
        const selectParent = actionSelect.closest('.views-bulk-actions__item');
        if (selectParent) {
          selectParent.style.display = 'none';
        }
      }

      console.log('Bioland: Bulk actions hidden for contributor role');
    }
  };

})(Drupal, drupalSettings);
