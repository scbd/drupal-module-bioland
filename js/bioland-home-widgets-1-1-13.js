/**
 * @file
 * Bioland Home Widgets - provides access to widget settings for frontend.
 *
 * This behavior makes home widget settings (including country map defaults)
 * available to other JavaScript code through drupalSettings.
 *
 * Country map settings include zoom level and coordinates (longitude/latitude)
 * that are automatically populated from preset defaults based on country codes.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.biolandHomeWidgets = {
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
        
        // Settings are now available at:
        // - window.Bioland.homeWidgets.gbif_widget.countries[countryCode].zoomLevel
        // - window.Bioland.homeWidgets.gbif_widget.countries[countryCode].longitude
        // - window.Bioland.homeWidgets.gbif_widget.countries[countryCode].latitude
      }
    }
  };

})(Drupal, drupalSettings);
