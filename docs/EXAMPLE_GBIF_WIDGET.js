/**
 * @file
 * Example: GBIF Widget Integration using Country Map Defaults
 *
 * This file demonstrates how to integrate the Bioland country map defaults
 * with a GBIF (Global Biodiversity Information Facility) widget.
 *
 * USAGE:
 * 1. Include this behavior in your custom module's JavaScript
 * 2. Ensure bioland/home_widgets library is loaded
 * 3. The widget will automatically initialize with correct zoom/coordinates
 */

(function (Drupal, $, window) {
  'use strict';

  /**
   * GBIF Widget Behavior - Example Implementation
   *
   * This behavior demonstrates how to use the country map defaults
   * provided by the Bioland module to initialize GBIF maps.
   */
  Drupal.behaviors.exampleGbifWidget = {
    attach: function (context, settings) {
      // Get home widget settings (includes country defaults)
      var homeWidgets = window.Bioland && window.Bioland.homeWidgets;
      
      if (!homeWidgets || !homeWidgets.gbif_widget) {
        console.log('GBIF Widget: No configuration found');
        return;
      }

      var gbifConfig = homeWidgets.gbif_widget;
      
      // Check if widget is enabled
      if (!gbifConfig.enable) {
        console.log('GBIF Widget: Disabled in settings');
        return;
      }

      // Find all GBIF widget containers on the page
      $('.gbif-widget-container', context).once('gbif-widget-init').each(function () {
        var $container = $(this);
        var countryCode = $container.data('country'); // e.g., data-country="lk"
        
        if (!countryCode) {
          console.warn('GBIF Widget: No country code specified for container');
          return;
        }

        // Get country-specific settings with preset defaults
        var countrySettings = gbifConfig.countries[countryCode.toLowerCase()];
        
        if (!countrySettings) {
          console.warn('GBIF Widget: No settings found for country: ' + countryCode);
          return;
        }

        // Initialize GBIF map with the settings
        initializeGbifMap($container, countryCode, countrySettings);
      });
    }
  };

  /**
   * Initialize a GBIF map widget
   *
   * @param {jQuery} $container - The container element
   * @param {string} countryCode - ISO country code (e.g., 'lk', 'us')
   * @param {object} settings - Country settings with zoomLevel, longitude, latitude
   */
  function initializeGbifMap($container, countryCode, settings) {
    console.log('Initializing GBIF map for: ' + countryCode.toUpperCase(), settings);

    // Create map container if it doesn't exist
    var mapId = 'gbif-map-' + countryCode.toLowerCase();
    if ($container.find('#' + mapId).length === 0) {
      $container.append('<div id="' + mapId + '" class="gbif-map"></div>');
    }

    // Example: Initialize Leaflet map (pseudo-code)
    // Replace with actual GBIF API integration
    /*
    var map = L.map(mapId).setView(
      [settings.latitude, settings.longitude],
      settings.zoomLevel
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Add GBIF occurrence data layer
    fetch('https://api.gbif.org/v1/occurrence/search?country=' + countryCode.toUpperCase())
      .then(response => response.json())
      .then(data => {
        // Process and display GBIF data on map
        displayGbifOccurrences(map, data);
      });
    */

    // For this example, just show the configuration
    $container.find('.gbif-map').html(
      '<div class="gbif-config-display">' +
      '<h3>GBIF Map: ' + countryCode.toUpperCase() + '</h3>' +
      '<p><strong>Zoom Level:</strong> ' + settings.zoomLevel + '</p>' +
      '<p><strong>Center:</strong> ' + 
      settings.latitude.toFixed(6) + ', ' + 
      settings.longitude.toFixed(6) + '</p>' +
      '<p><em>Map initialization would happen here</em></p>' +
      '</div>'
    );

    console.log('GBIF map initialized for ' + countryCode.toUpperCase());
  }

  /**
   * Helper: Display GBIF occurrences on map
   * (This is pseudo-code - implement according to your needs)
   */
  function displayGbifOccurrences(map, data) {
    if (!data || !data.results) {
      return;
    }

    data.results.forEach(function(occurrence) {
      if (occurrence.decimalLatitude && occurrence.decimalLongitude) {
        // Add marker for each occurrence
        // L.marker([occurrence.decimalLatitude, occurrence.decimalLongitude])
        //   .addTo(map)
        //   .bindPopup('<strong>' + occurrence.scientificName + '</strong>');
      }
    });
  }

})(Drupal, jQuery, window);

/**
 * HTML EXAMPLE:
 *
 * To use this widget, add HTML like this to your page/block:
 *
 * <div class="gbif-widget-container" data-country="lk">
 *   <!-- GBIF map will be inserted here -->
 * </div>
 *
 * <div class="gbif-widget-container" data-country="us">
 *   <!-- Another GBIF map for US -->
 * </div>
 *
 * The JavaScript will automatically:
 * 1. Find the containers
 * 2. Read the country code from data-country attribute
 * 3. Look up the country settings (with preset defaults)
 * 4. Initialize the map with correct zoom level and coordinates
 */

/**
 * CSS EXAMPLE:
 *
 * Add styles for the GBIF widget:
 *
 * .gbif-widget-container {
 *   width: 100%;
 *   margin-bottom: 2rem;
 * }
 *
 * .gbif-map {
 *   width: 100%;
 *   height: 500px;
 *   border: 1px solid #ccc;
 *   border-radius: 4px;
 * }
 *
 * .gbif-config-display {
 *   padding: 2rem;
 *   background: #f5f5f5;
 *   height: 100%;
 * }
 *
 * .gbif-config-display h3 {
 *   margin-top: 0;
 *   color: #333;
 * }
 */

/**
 * DRUPAL BLOCK EXAMPLE:
 *
 * Create a custom block plugin:
 *
 * <?php
 * namespace Drupal\my_module\Plugin\Block;
 *
 * use Drupal\Core\Block\BlockBase;
 *
 * /**
 *  * @Block(
 *  *   id = "gbif_widget_block",
 *  *   admin_label = @Translation("GBIF Widget")
 *  * )
 *  *\/
 * class GbifWidgetBlock extends BlockBase {
 *   public function build() {
 *     return [
 *       '#markup' => '<div class="gbif-widget-container" data-country="lk"></div>',
 *       '#attached' => [
 *         'library' => [
 *           'my_module/gbif_widget',  // Your custom library
 *           'bioland/home_widgets',   // Bioland home widgets library
 *         ],
 *       ],
 *     ];
 *   }
 * }
 */
