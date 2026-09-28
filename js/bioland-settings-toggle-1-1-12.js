/**
 * @file
 * Bioland Settings Form Toggle Behavior
 *
 * Handles show/hide toggle for field visibility settings in the admin form.
 * 
 * Note: No module-level state. All state passed via parameters or stored in DOM data attributes.
 * Uses window.biolandGetLogger for logging (loaded via debug_logger dependency).
 */
(function(Drupal, window, document) {
  'use strict';

  /**
   * Initialize a toggle for a specific toggle link and target container.
   *
   * @param {Element} context - The context element
   * @param {string} toggleSelector - CSS selector for the toggle link
   * @param {string} containerSelector - CSS selector for the target container
   * @param {Object} logger - Logger instance
   */
  const initializeToggle = function(context, toggleSelector, containerSelector, logger) {
    // Find all toggle links within context
    const toggleLinks = context.querySelectorAll(toggleSelector);

    toggleLinks.forEach(function(toggleLink) {
      // Prevent duplicate event binding
      if (toggleLink.dataset.biolandToggleInit) {
        return;
      }
      toggleLink.dataset.biolandToggleInit = 'true';

      // Find the target container
      const targetContainer = document.querySelector(containerSelector);

      // Handle click event
      toggleLink.addEventListener('click', function(e) {
        e.preventDefault();

        // Toggle the visibility
        if (targetContainer.classList.contains('bioland-collapsible-hidden')) {
          // Show the settings
          targetContainer.classList.remove('bioland-collapsible-hidden');
          targetContainer.classList.add('bioland-collapsible-visible');
          toggleLink.textContent = 'Show less';
          toggleLink.classList.add('expanded');
        } else {
          // Hide the settings
          targetContainer.classList.remove('bioland-collapsible-visible');
          targetContainer.classList.add('bioland-collapsible-hidden');
          toggleLink.textContent = 'Show more';
          toggleLink.classList.remove('expanded');
        }
      });

      logger.log('Settings toggle initialized for', toggleSelector);
    });
  };

  /**
   * Drupal behavior for settings toggle functionality.
   */
  Drupal.behaviors.biolandSettingsToggle = {
    attach: function(context, settings) {
      const biolandSettings = settings.bioland || {};
      const logger = window.biolandGetLogger('settingsToggle', biolandSettings);

      // Handle Additional Fields toggle only
      // Field Visibility now uses native Drupal details elements
      initializeToggle(context, '.bioland-toggle-additional-fields-settings', '.bioland-additional-fields-settings', logger);
    }
  };

})(Drupal, window, document);
