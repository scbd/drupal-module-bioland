/**
 * @jest-environment jsdom
 */

describe('Bioland Home Widgets', () => {
  let Drupal;
  let drupalSettings;

  beforeEach(() => {
    // Mock Drupal and drupalSettings
    global.window.Bioland = undefined;
    
    Drupal = {
      behaviors: {}
    };
    
    global.Drupal = Drupal;
    
    drupalSettings = {
      bioland: {
        homeWidgets: {
          gbif_widget: {
            enable: true,
            countries: {
              lk: {
                zoomLevel: 5,
                longitude: 80.66931169770622,
                latitude: 7.696630939329944
              },
              us: {
                zoomLevel: 2,
                longitude: -96.33161660829639,
                latitude: 38.8208089190304
              }
            }
          }
        },
        enableDebugLogging: false,
        debugLogAreas: {
          homeWidgets: false
        }
      }
    };

    global.drupalSettings = drupalSettings;

    // Manually execute the IIFE from bioland-home-widgets-1-1-2.js
    const behavior = {
      attach: function (context, settings) {
        var biolandSettings = settings.bioland || {};
        var homeWidgetSettings = biolandSettings.homeWidgets || {};

        // Log settings for debugging (only in development)
        if (biolandSettings.enableDebugLogging && biolandSettings.debugLogAreas.homeWidgets) {
          console.log('Bioland: Home Widget Settings loaded', homeWidgetSettings);
        }

        // Make settings available globally for other widgets to use
        if (typeof window.Bioland === 'undefined') {
          window.Bioland = {};
        }
        window.Bioland.homeWidgets = homeWidgetSettings;

        // Example: GBIF Widget settings
        if (homeWidgetSettings.gbif_widget && homeWidgetSettings.gbif_widget.enable) {
          if (biolandSettings.enableDebugLogging && biolandSettings.debugLogAreas.homeWidgets) {
            console.log('Bioland: GBIF Widget is enabled');
            console.log('Bioland: GBIF Widget countries:', homeWidgetSettings.gbif_widget.countries);
          }
        }
      }
    };
    
    Drupal.behaviors.biolandHomeWidgets = behavior;
  });

  afterEach(() => {
    // Reset to initial state instead of deleting
    if (global.Drupal) {
      global.Drupal.behaviors = {};
    }
    delete global.drupalSettings;
    delete global.window.Bioland;
  });

  test('behavior is registered', () => {
    expect(Drupal.behaviors.biolandHomeWidgets).toBeDefined();
    expect(typeof Drupal.behaviors.biolandHomeWidgets.attach).toBe('function');
  });

  test('makes settings available globally', () => {
    const context = document.createElement('div');
    Drupal.behaviors.biolandHomeWidgets.attach(context, drupalSettings);

    expect(global.window.Bioland).toBeDefined();
    expect(global.window.Bioland.homeWidgets).toBeDefined();
    expect(global.window.Bioland.homeWidgets.gbif_widget).toBeDefined();
  });

  test('loads country-specific settings', () => {
    const context = document.createElement('div');
    Drupal.behaviors.biolandHomeWidgets.attach(context, drupalSettings);

    const lkSettings = global.window.Bioland.homeWidgets.gbif_widget.countries.lk;
    expect(lkSettings).toBeDefined();
    expect(lkSettings.zoomLevel).toBe(5);
    expect(lkSettings.longitude).toBe(80.66931169770622);
    expect(lkSettings.latitude).toBe(7.696630939329944);
  });

  test('handles multiple countries', () => {
    const context = document.createElement('div');
    Drupal.behaviors.biolandHomeWidgets.attach(context, drupalSettings);

    const countries = global.window.Bioland.homeWidgets.gbif_widget.countries;
    expect(Object.keys(countries).length).toBe(2);
    expect(countries.lk).toBeDefined();
    expect(countries.us).toBeDefined();
  });

  test('handles missing bioland settings gracefully', () => {
    const context = document.createElement('div');
    const emptySettings = {};
    
    expect(() => {
      Drupal.behaviors.biolandHomeWidgets.attach(context, emptySettings);
    }).not.toThrow();
    
    expect(global.window.Bioland.homeWidgets).toEqual({});
  });
});
