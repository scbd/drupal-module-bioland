/**
 * @file
 * Unit tests for bioland-settings-toggle-1-0-24.js
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
      require('./bioland-settings-toggle-1-0-24.js');
      expect(Drupal.behaviors.biolandSettingsToggle).toBeDefined();
      expect(typeof Drupal.behaviors.biolandSettingsToggle.attach).toBe('function');
    });
  });

  describe('Field Visibility toggle', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form>
          <a href="#" class="bioland-toggle-visibility-settings">Show more</a>
          <div class="bioland-field-visibility-settings bioland-collapsible-hidden">
            <p>Field visibility settings content</p>
          </div>
        </form>
      `;
    });

    test('should initialize toggle for field visibility settings', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-visibility-settings');
    });

    test('should show settings when toggle is clicked', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      const container = document.querySelector('.bioland-field-visibility-settings');
      
      toggleLink.click();
      
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(true);
      expect(container.classList.contains('bioland-collapsible-hidden')).toBe(false);
    });

    test('should change toggle text to "Show less" when expanded', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      toggleLink.click();
      
      expect(toggleLink.textContent).toBe('Show less');
    });

    test('should add expanded class when expanded', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      toggleLink.click();
      
      expect(toggleLink.classList.contains('expanded')).toBe(true);
    });

    test('should hide settings when toggle is clicked twice', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      const container = document.querySelector('.bioland-field-visibility-settings');
      
      // Click to show
      toggleLink.click();
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(true);
      
      // Click to hide
      toggleLink.click();
      expect(container.classList.contains('bioland-collapsible-hidden')).toBe(true);
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(false);
    });

    test('should change toggle text to "Show more" when collapsed', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      // Click to show
      toggleLink.click();
      expect(toggleLink.textContent).toBe('Show less');
      
      // Click to hide
      toggleLink.click();
      expect(toggleLink.textContent).toBe('Show more');
    });

    test('should remove expanded class when collapsed', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      // Click to show
      toggleLink.click();
      expect(toggleLink.classList.contains('expanded')).toBe(true);
      
      // Click to hide
      toggleLink.click();
      expect(toggleLink.classList.contains('expanded')).toBe(false);
    });

    test('should prevent default link behavior', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      const event = new Event('click', { cancelable: true });
      toggleLink.dispatchEvent(event);
      
      expect(event.defaultPrevented).toBe(true);
    });

    test('should not attach duplicate event listeners', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      // Attach twice
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      
      expect(toggleLink.dataset.biolandToggleInit).toBe('true');
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
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-additional-fields-settings');
    });

    test('should show additional fields settings when toggle is clicked', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
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

    test('should initialize both toggles', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-visibility-settings');
      expect(console.log).toHaveBeenCalledWith('Bioland [settingsToggle]: Settings toggle initialized for', '.bioland-toggle-additional-fields-settings');
    });

    test('should toggle each section independently', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const visibilityToggle = document.querySelector('.bioland-toggle-visibility-settings');
      const visibilityContainer = document.querySelector('.bioland-field-visibility-settings');
      const additionalToggle = document.querySelector('.bioland-toggle-additional-fields-settings');
      const additionalContainer = document.querySelector('.bioland-additional-fields-settings');
      
      // Expand visibility settings only
      visibilityToggle.click();
      
      expect(visibilityContainer.classList.contains('bioland-collapsible-visible')).toBe(true);
      expect(additionalContainer.classList.contains('bioland-collapsible-hidden')).toBe(true);
      
      // Expand additional fields settings
      additionalToggle.click();
      
      expect(visibilityContainer.classList.contains('bioland-collapsible-visible')).toBe(true);
      expect(additionalContainer.classList.contains('bioland-collapsible-visible')).toBe(true);
    });
  });

  describe('Context-specific initialization', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form>
          <div id="section1">
            <a href="#" class="bioland-toggle-visibility-settings">Show more</a>
            <div class="bioland-field-visibility-settings bioland-collapsible-hidden">
              <p>Content 1</p>
            </div>
          </div>
          <div id="section2">
            <a href="#" class="bioland-toggle-visibility-settings">Show more</a>
            <div class="bioland-field-visibility-settings bioland-collapsible-hidden">
              <p>Content 2</p>
            </div>
          </div>
        </form>
      `;
    });

    test('should initialize toggles within specific context', () => {
      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document.querySelector('#section1');
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const section1Toggle = document.querySelector('#section1 .bioland-toggle-visibility-settings');
      const section2Toggle = document.querySelector('#section2 .bioland-toggle-visibility-settings');
      
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

      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      // Should not throw
      expect(() => {
        Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      }).not.toThrow();
    });

    test('should handle no toggle links in context', () => {
      document.body.innerHTML = '<form><p>No toggles here</p></form>';

      require('./bioland-settings-toggle-1-0-24.js');
      
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
          <a href="#" class="bioland-toggle-visibility-settings expanded">Show less</a>
          <div class="bioland-field-visibility-settings bioland-collapsible-visible">
            <p>Already visible content</p>
          </div>
        </form>
      `;

      require('./bioland-settings-toggle-1-0-24.js');
      
      const context = document;
      const settings = {};
      
      Drupal.behaviors.biolandSettingsToggle.attach(context, settings);
      
      const toggleLink = document.querySelector('.bioland-toggle-visibility-settings');
      const container = document.querySelector('.bioland-field-visibility-settings');
      
      // Click to hide
      toggleLink.click();
      
      expect(container.classList.contains('bioland-collapsible-hidden')).toBe(true);
      expect(container.classList.contains('bioland-collapsible-visible')).toBe(false);
      expect(toggleLink.textContent).toBe('Show more');
    });
  });
});
