/**
 * @file
 * Jest setup file for Bioland module tests.
 * Sets up the global Drupal object and other mocks needed for testing.
 */

// Mock the global Drupal object
global.Drupal = {
  behaviors: {},
  t: (str) => str, // Simple translation mock that returns the string
  // Note: CKEditor5Instances is intentionally NOT set here - let tests set it as needed
};

// Mock console methods to reduce noise in tests (optional)
// Comment out if you want to see console output during tests
// global.console = {
//   ...console,
//   log: jest.fn(),
//   warn: jest.fn(),
//   error: jest.fn(),
// };

// Reset DOM between tests
beforeEach(() => {
  document.body.innerHTML = '';
  document.head.innerHTML = '';
  
  // Reset Drupal behaviors and CKEditor5Instances
  global.Drupal.behaviors = {};
  delete global.Drupal.CKEditor5Instances;
  
  // Reset cookies
  document.cookie.split(';').forEach((c) => {
    document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
  });
});

// Clean up after each test
afterEach(() => {
  jest.clearAllMocks();
  jest.clearAllTimers();
});
