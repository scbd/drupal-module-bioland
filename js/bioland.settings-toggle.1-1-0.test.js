/**
 * @file
 * Unit tests for bioland-settings-toggle-1-1-0.js
 */

describe('Bioland Settings Toggle', () => {
  let originalConsoleLog;

  beforeEach(() => {
    // Suppress console output during tests
    originalConsoleLog = console.log;
    console.log = jest.fn();

    // Reset Drupal behaviors
    global.Drupal.behaviors = {};

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
  });

  describe('Drupal behavior registration', () => {
    test('should register biolandSettingsToggle behavior', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      expect(Drupal.behaviors.biolandSettingsToggle).toBeDefined();
      expect(typeof Drupal.behaviors.biolandSettingsToggle.attach).toBe('function');
    });
  });

  describe('Field Visibility toggle', () => {
    test('should skip field visibility toggle (now using native details)', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      // Should not log initialization for field visibility toggle anymore
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      expect(console.log).not.toHaveBeenCalledWith(
        'Bioland [settingsToggle]: Settings toggle initialized for', 
        '.bioland-toggle-visibility-settings'
      );
    });
  });

  describe('Additional Fields toggle', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form>
          <a href="#" class="bioland-toggle-additional-fields-settings">Show more</a>
          <div class="bioland-additional-fields-settings bioland-collapsible-hidden">
            <p>Additional fields settings content</p>
          </div>
        </form>
      `;
    });

    test('should initialize toggle for additional fields settings', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-additional-fields-settings');
    });

    test('should show additional fields settings when toggle is clicked', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-additional-fields-settings');
      const container = document.querySelector('.bioland-additional-fields-settings');
      
      toggleLink.click();
      
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(true);
      expect(container.classList.contains('bioland-collapsible-hidden')).toBe(false);
    });
  });

  describe('Multiple toggles', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form>
          <a href="#" class="bioland-toggle-visibility-settings">Show more</a>
          <div class="bioland-field-visibility-settings bioland-collapsible-hidden">
            <p>Field visibility settings content</p>
          </div>
          <a href="#" class="bioland-toggle-additional-fields-settings">Show more</a>
          <div class="bioland-additional-fields-settings bioland-collapsible-hidden">
            <p>Additional fields settings content</p>
          </div>
        </form>
      `;
    });

    test('should initialize additional fields toggle only', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      // Field visibility no longer uses custom toggle
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-additional-fields-settings');
    });

    test('should toggle additional fields section', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const additionalToggle = document.querySelector('.bioland-toggle-additional-fields-settings');
      const additionalContainer = document.querySelector('.bioland-additional-fields-settings');
      
      // Expand additional fields settings
      additionalToggle.click();
      
      expect(additionalContainer.classList.contains('bioland-collapsible-visible')).toBe(true);
      expect(additionalContainer.classList.contains('bioland-collapsible-hidden')).toBe(false);
    });
  });

  describe('Context-specific initialization', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form>
          <div id="section1">
            <a href="#" class="bioland-toggle-additional-fields-settings">Show more</a>
            <div class="bioland-additional-fields-settings bioland-collapsible-hidden">
              <p>Content 1</p>
            </div>
          </div>
          <div id="section2">
            <a href="#" class="bioland-toggle-additional-fields-settings">Show more</a>
            <div class="bioland-additional-fields-settings bioland-collapsible-hidden">
              <p>Content 2</p>
            </div>
          </div>
        </form>
      `;
    });

    test('should initialize toggles within specific context', () => {
      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document.querySelector('#section1');
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const section1Toggle = document.querySelector('#section1 .bioland-toggle-additional-fields-settings');
      const section2Toggle = document.querySelector('#section2 .bioland-toggle-additional-fields-settings');
      
      // Only section1 toggle should be initialized
      expect(section1Toggle.dataset.biolandToggleInit).toBe('true');
      expect(section2Toggle.dataset.biolandToggleInit).toBeUndefined();
    });
  });

  describe('Edge cases', () => {
    test('should handle missing target container gracefully', () => {
      document.body.innerHTML = `
        <form>
          <a href="#" class="bioland-toggle-visibility-settings">Show more</a>
          <!-- No target container -->
        </form>
      `;

      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      // Should not throw
      expect(() => {
        Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      }).not.toThrow();
    });

    test('should handle no toggle links in context', () => {
      document.body.innerHTML = '<form><p>No toggles here</p></form>';

      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      // Should not throw
      expect(() => {
        Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      }).not.toThrow();
    });

    test('should handle initially expanded container', () => {
      document.body.innerHTML = `
        <form>
          <a href="#" class="bioland-toggle-additional-fields-settings expanded">Show less</a>
          <div class="bioland-additional-fields-settings bioland-collapsible-visible">
            <p>Already visible content</p>
          </div>
        </form>
      `;

      require('./bioland-settings-toggle-1-1-0.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-additional-fields-settings');
      const container = document.querySelector('.bioland-additional-fields-settings');
      
      // Click to hide
      toggleLink.click();
      
      expect(container.classList.contains('bioland-collapsible-hidden')).toBe(true);
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(false);
      expect(toggleLink.textContent).toBe('Show more');
    });
  });
});
