/**
 * @file
 * Bioland Settings Form Toggle Behavior
 *
 * Handles show/hide toggle for field visibility settings in the admin form.
 */

/**
 * Initialize a toggle for a specific toggle link and target container.
 *
 * @param {Element} context - The context element
 * @param {string} toggleSelector - CSS selector for the toggle link
 * @param {string} containerSelector - CSS selector for the target container
 */
function initializeToggle(context, toggleSelector, containerSelector) {
  // Find all toggle links within context
  const toggleLinks = context.querySelectorAll(toggleSelector);

  toggleLinks.forEach((toggleLink) => {
    // Prevent duplicate event binding
    if (toggleLink.dataset.biolandToggleInit) {
      return;
    }
    toggleLink.dataset.biolandToggleInit = 'true';

    // Find the target container
    const targetContainer = document.querySelector(containerSelector);

    // Handle click event
    toggleLink.addEventListener('click', (e) => {
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

    console.log('Bioland: Settings toggle initialized for', toggleSelector);
  });
}

/**
 * Drupal behavior for settings toggle functionality.
 */
Drupal.behaviors.biolandSettingsToggle = {
  attach(context, settings) {
    // Handle Field Visibility toggle
    initializeToggle(context, '.bioland-toggle-visibility-settings', '.bioland-field-visibility-settings');

    // Handle Additional Fields toggle
    initializeToggle(context, '.bioland-toggle-additional-fields-settings', '.bioland-additional-fields-settings');
  }
};
