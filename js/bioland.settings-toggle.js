/**
 * @file
 * Bioland Settings Form Toggle Behavior
 *
 * Handles show/hide toggle for field visibility settings in the admin form.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Drupal behavior for settings toggle functionality.
   */
  Drupal.behaviors.biolandSettingsToggle = {
    attach: function (context, settings) {
      // Handle Field Visibility toggle
      this.initializeToggle(context, '.bioland-toggle-visibility-settings', '.bioland-field-visibility-settings');
      
      // Handle Additional Fields toggle
      this.initializeToggle(context, '.bioland-toggle-additional-fields-settings', '.bioland-additional-fields-settings');
    },

    /**
     * Initialize a toggle for a specific toggle link and target container.
     *
     * @param {Element} context - The context element
     * @param {string} toggleSelector - CSS selector for the toggle link
     * @param {string} containerSelector - CSS selector for the target container
     */
    initializeToggle: function (context, toggleSelector, containerSelector) {
      // Find all toggle links (using context to avoid processing multiple times)
      $(toggleSelector, context).each(function () {
        var $toggleLink = $(this);
        
        // Prevent duplicate event binding
        if ($toggleLink[0].dataset.biolandToggleInit) {
          return;
        }
        $toggleLink[0].dataset.biolandToggleInit = 'true';

        // Find the target container
        var $targetContainer = $(containerSelector);

        // Handle click event
        $toggleLink.on('click.biolandToggle', function (e) {
          e.preventDefault();

          // Toggle the visibility
          if ($targetContainer.hasClass('bioland-collapsible-hidden')) {
            // Show the settings
            $targetContainer
              .removeClass('bioland-collapsible-hidden')
              .addClass('bioland-collapsible-visible');
            $toggleLink.text('Show less').addClass('expanded');
          } else {
            // Hide the settings
            $targetContainer
              .removeClass('bioland-collapsible-visible')
              .addClass('bioland-collapsible-hidden');
            $toggleLink.text('Show more').removeClass('expanded');
          }
        });

        console.log('Bioland: Settings toggle initialized for', toggleSelector);
      });
    }
  };

})(jQuery, Drupal);
