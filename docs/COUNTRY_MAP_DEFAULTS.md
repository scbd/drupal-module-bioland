# Country Map Defaults

This document explains how the Bioland module handles country-specific map settings for the GBIF widget and other home page widgets.

## Overview

The module includes preset zoom levels and center coordinates for over 240 countries. These defaults are automatically applied when:
1. A new country is added to the site configuration
2. The user hasn't manually customized the settings for that country

## Architecture

### Backend (PHP)

**BiolandCountryMapDefaults Service** (`src/Service/BiolandCountryMapDefaults.php`)
- Contains static preset data for all countries
- Provides methods to retrieve defaults by country code
- Data structure:
  ```php
  [
    'COUNTRY_CODE' => [
      'zoomLevel' => int,        // 1-255
      'coordinates' => [
        'longitude' => float,
        'latitude' => float
      ]
    ]
  ]
  ```

**BiolandSettingsManager Service** (`src/Service/BiolandSettingsManager.php`)
- Method `getHomeWidgetSettings()` merges config with defaults
- Returns final settings for frontend consumption
- Respects user customizations (saved values take precedence over defaults)

**BiolandSettingsForm** (`src/Form/BiolandSettingsForm.php`)
- Form builder shows preset defaults in field descriptions
- Uses fallback chain: saved value → preset default → generic default
- Located at: `/admin/config/bioland/settings/front-end/home-widgets`

### Frontend (JavaScript)

**Home Widgets Behavior** (`js/bioland-home-widgets-1-1-0.js`)
- Exposes settings via `window.Bioland.homeWidgets`
- Available on all pages via `hook_page_attachments()`
- Debug logging when enabled in settings

**Access Pattern:**
```javascript
// Get GBIF widget settings for Sri Lanka
var lkSettings = window.Bioland.homeWidgets.gbif_widget.countries.lk;
console.log(lkSettings.zoomLevel);  // e.g., 5
console.log(lkSettings.longitude);  // e.g., 80.669312
console.log(lkSettings.latitude);   // e.g., 7.696631
```

## Configuration

### Admin Interface

1. Navigate to: `/admin/config/bioland/settings/front-end/home-widgets`
2. Expand "GBIF Widget" section
3. For each country, view preset defaults in field descriptions
4. Modify values to override defaults
5. Save configuration

### Programmatic Access

```php
// Get settings manager service
$settingsManager = \Drupal::service('bioland.settings_manager');

// Get all home widget settings with defaults applied
$widgetSettings = $settingsManager->getHomeWidgetSettings();

// Get specific country settings
$lkSettings = $widgetSettings['gbif_widget']['countries']['lk'];
echo $lkSettings['zoomLevel'];  // 5
echo $lkSettings['longitude'];  // 80.669312
echo $lkSettings['latitude'];   // 7.696631

// Get raw country defaults (without config merge)
use Drupal\bioland\Service\BiolandCountryMapDefaults;
$defaults = BiolandCountryMapDefaults::getCountryDefaults('LK');
// Returns: ['zoomLevel' => 5, 'coordinates' => ['longitude' => ..., 'latitude' => ...]]
```

## Workflow: User Changes

### Scenario 1: First Time Setup
1. Admin adds countries to general settings (e.g., 'lk', 'us', 'ca')
2. Admin visits home widgets page
3. **Form displays preset defaults** for each country
4. Admin saves without changes → presets are used

### Scenario 2: Customization
1. Admin modifies zoom level for 'lk' from preset 5 to 8
2. Admin saves form
3. Custom value (8) is stored in config
4. **Custom value takes precedence** over preset (5)
5. Frontend receives custom value via drupalSettings

### Scenario 3: Reset to Defaults
To reset a country to preset defaults:
1. Delete the country-specific config values
2. Or manually enter the preset values shown in field descriptions

**Programmatic reset:**
```php
$config = \Drupal::configFactory()->getEditable('bioland.settings');
$config->clear('home_widgets.gbif_widget.countries.lk.zoom_level');
$config->clear('home_widgets.gbif_widget.countries.lk.longitude');
$config->clear('home_widgets.gbif_widget.countries.lk.latitude');
$config->save();
// Next load will use presets
```

## Data Sources

Country defaults were generated based on:
- Geographic center points of each country
- Optimal zoom levels for country size
- Coverage of primary geographic features

**Zoom Level Guidelines:**
- 1-2: Large countries (e.g., Russia, Canada, China)
- 3-5: Medium countries (e.g., Thailand, Kenya)
- 7-12: Small countries (e.g., Singapore, Luxembourg)
- 255: City-states (e.g., Vatican)

## Adding New Countries

### Method 1: Use Existing Presets
Simply add the country code to the main countries configuration:
1. Go to `/admin/config/bioland/settings`
2. Add country code (e.g., 'no' for Norway)
3. Navigate to home widgets settings
4. **Presets automatically available**

### Method 2: Define Custom Defaults

If a country is not in the preset list, add it to `BiolandCountryMapDefaults.php`:

```php
public static function getDefaults() {
  return [
    // ... existing countries ...
    'XX' => [
      'zoomLevel' => 5,
      'coordinates' => [
        'longitude' => 0.0,
        'latitude' => 0.0
      ]
    ],
  ];
}
```

**Steps to determine values:**
1. Find geographic center of country
2. Test zoom levels on actual map (1-255)
3. Add to defaults array with uppercase country code

## Frontend Integration Example

### GBIF Widget Implementation

```javascript
(function (Drupal) {
  'use strict';

  Drupal.behaviors.customGbifWidget = {
    attach: function (context, settings) {
      // Get country settings
      var homeWidgets = window.Bioland.homeWidgets;
      
      if (!homeWidgets || !homeWidgets.gbif_widget.enable) {
        return; // Widget disabled
      }
      
      // Get countries config
      var countries = homeWidgets.gbif_widget.countries;
      
      // Initialize map for each country
      Object.keys(countries).forEach(function(countryCode) {
        var countrySettings = countries[countryCode];
        
        // Initialize GBIF map with preset/custom settings
        initializeGbifMap({
          country: countryCode,
          zoom: countrySettings.zoomLevel,
          center: [
            countrySettings.longitude,
            countrySettings.latitude
          ]
        });
      });
    }
  };
  
  function initializeGbifMap(options) {
    // Your GBIF widget initialization code
    console.log('Initializing GBIF map:', options);
  }

})(Drupal);
```

## Debugging

Enable debug logging to see settings in browser console:

1. Go to `/admin/config/bioland/settings/admin`
2. Check "Enable Debug Logging"
3. Check "Home Widgets" under debug areas
4. Save configuration

Console output will show:
```
Bioland: Home Widget Settings loaded {gbif_widget: {...}}
Bioland: GBIF Widget is enabled
Bioland: GBIF Widget countries: {lk: {...}, us: {...}}
```

## Testing

Run JavaScript tests:
```bash
npm test -- bioland.home-widgets
```

Run PHP unit tests:
```bash
vendor/bin/phpunit
```

## API Summary

### PHP API
- `BiolandCountryMapDefaults::getDefaults()` - Get all presets
- `BiolandCountryMapDefaults::getCountryDefaults($code)` - Get specific country
- `$settingsManager->getHomeWidgetSettings()` - Get merged config + defaults

### JavaScript API
- `window.Bioland.homeWidgets` - Global settings object
- `drupalSettings.bioland.homeWidgets` - Drupal settings object

### Configuration
- Config key: `bioland.settings`
- Home widgets: `home_widgets.gbif_widget.countries.{COUNTRY_CODE}`
- Fields: `zoom_level`, `longitude`, `latitude`

## See Also

- [Main README](../README.md)
- [Settings Form Documentation](BiolandSettingsForm.php)
- [GBIF Widget Documentation](https://www.gbif.org/)
