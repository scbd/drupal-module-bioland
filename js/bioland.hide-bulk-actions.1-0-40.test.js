/**
 * @file
 * Tests for bioland-hide-bulk-actions JavaScript behavior.
 */

describe('Bioland Hide Bulk Actions', () => {
  let originalConsoleLog;

  beforeEach(() => {
    // Suppress console output during tests
    originalConsoleLog = console.log;
    console.log = jest.fn();

    // Reset Drupal behaviors
    global.Drupal = {
      behaviors: {}
    };

    // Reset drupalSettings
    global.drupalSettings = {
      bioland: {
        isContributor: false
      }
    };

    // Clear the module cache to get fresh module state
    jest.resetModules();
  });

  afterEach(() => {
    console.log = originalConsoleLog;
  });

  test('behavior is registered', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    expect(behavior).toBeDefined();
    expect(typeof behavior.attach).toBe('function');
  });

  test('does nothing when user is not contributor', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    context.innerHTML = `
      <select name="action" id="edit-action">
        <option>Delete</option>
      </select>
      <input type="submit" value="Apply to selected items">
    `;

    global.drupalSettings.bioland.isContributor = false;
    
    behavior.attach(context, global.drupalSettings);

    const select = context.querySelector('#edit-action');
    const submit = context.querySelector('input[type="submit"]');
    
    expect(select.style.display).not.toBe('none');
    expect(submit.style.display).not.toBe('none');
  });

  test('hides action dropdown when user is contributor', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    const select = document.createElement('select');
    select.name = 'action';
    select.id = 'edit-action';
    context.appendChild(select);

    global.drupalSettings.bioland.isContributor = true;
    
    behavior.attach(context, global.drupalSettings);

    expect(select.style.display).toBe('none');
    expect(select.dataset.biolandBulkHidden).toBe('true');
  });

  test('hides submit button when user is contributor', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    const submit = document.createElement('input');
    submit.type = 'submit';
    submit.value = 'Apply to selected items';
    context.appendChild(submit);

    global.drupalSettings.bioland.isContributor = true;
    
    behavior.attach(context, global.drupalSettings);

    expect(submit.style.display).toBe('none');
    expect(submit.dataset.biolandBulkHidden).toBe('true');
  });

  test('does not re-process already hidden elements', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    const select = document.createElement('select');
    select.name = 'action';
    select.dataset.biolandBulkHidden = 'true';
    select.style.display = 'none';
    context.appendChild(select);

    global.drupalSettings.bioland.isContributor = true;
    
    // Mock to verify it doesn't get called again
    const originalDisplay = select.style.display;
    behavior.attach(context, global.drupalSettings);

    expect(select.style.display).toBe(originalDisplay);
  });

  test('handles missing drupalSettings gracefully', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    
    // Call with empty settings
    expect(() => {
      behavior.attach(context, {});
    }).not.toThrow();
  });

  test('logs success message for contributors', () => {
    require('./bioland-hide-bulk-actions-1-0-40.js');
    const behavior = global.Drupal.behaviors.biolandHideBulkActions;
    
    const context = document.createElement('div');
    global.drupalSettings.bioland.isContributor = true;
    
    const consoleSpy = jest.spyOn(console, 'log');
    
    behavior.attach(context, global.drupalSettings);

    expect(consoleSpy).toHaveBeenCalledWith(
      'Bioland: Bulk actions hidden for contributor role'
    );
  });
});
