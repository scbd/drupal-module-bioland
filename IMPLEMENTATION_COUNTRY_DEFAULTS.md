# Implementation Summary: Country Map Defaults

## What Was Implemented

A comprehensive system for managing country-specific map settings (zoom level, longitude, latitude) with preset defaults for 240+ countries, allowing users to customize values while preserving sensible defaults.

## Files Created/Modified

### New Files

1. **`src/Service/BiolandCountryMapDefaults.php`**
   - Service class with static preset data for all countries
   - Methods: `getDefaults()`, `getCountryDefaults($code)`
   - Contains zoom levels and coordinates for 240+ countries

2. **`js/bioland-home-widgets-1-0-47.js`**
   - Drupal behavior that exposes settings to frontend
   - Makes data available via `window.Bioland.homeWidgets`
   - Includes debug logging support

3. **`js/bioland.home-widgets.1-0-47.test.js`**
   - Jest unit tests for the home widgets behavior
   - Tests: registration, global availability, country settings, edge cases

4. **`docs/COUNTRY_MAP_DEFAULTS.md`**
   - Complete documentation for developers
   - Usage examples, API reference, workflows
   - Frontend integration patterns

### Modified Files

1. **`src/Service/BiolandSettingsManager.php`**
   - Added `getHomeWidgetSettings()` method
   - Merges config with preset defaults
   - Respects user customizations

2. **`src/Form/BiolandSettingsForm.php`**
   - Updated form builder to show preset defaults in descriptions
   - Changed zoom level max from 50 to 255
   - Uses fallback chain: saved → preset → generic default

3. **`bioland.module`**
   - Updated `hook_page_attachments()` to attach home widget settings
   - Exposes settings via `drupalSettings.bioland.homeWidgets`
   - Attaches new `bioland/home_widgets` library

4. **`bioland.libraries.yml`**
   - Added `home_widgets` library definition
   - Depends on: core/drupal, core/drupalSettings, bioland/debug_logger

## How It Works

### Backend Flow

```
User visits settings form
    ↓
Form builder reads countries from config
    ↓
For each country: Check BiolandCountryMapDefaults for presets
    ↓
Display form with default_value = saved ?? preset ?? generic
    ↓
User saves form (custom values stored in config)
    ↓
BiolandSettingsManager::getHomeWidgetSettings() merges config + presets
    ↓
Data passed to frontend via drupalSettings
```

### Frontend Flow

```
Page loads
    ↓
hook_page_attachments() runs
    ↓
Settings passed via drupalSettings.bioland.homeWidgets
    ↓
bioland-home-widgets behavior attaches
    ↓
Settings exposed globally at window.Bioland.homeWidgets
    ↓
Other widgets can access country settings
```

## Key Features

### 1. **Preset Defaults**
- 240+ countries with optimized zoom/coordinates
- Automatically applied when country added to config
- Shown in form field descriptions

### 2. **User Customization**
- Users can override any preset value
- Custom values take precedence over presets
- Changes persist in `bioland.settings` config

### 3. **Frontend Access**
```javascript
// Access country settings
var lkSettings = window.Bioland.homeWidgets.gbif_widget.countries.lk;
console.log(lkSettings.zoomLevel);   // 5
console.log(lkSettings.longitude);   // 80.669312
console.log(lkSettings.latitude);    // 7.696631
```

### 4. **Drupal Integration**
- Service-based architecture
- Config schema updated
- Follows PSR-12 coding standards
- Comprehensive documentation

## Usage Examples

### PHP: Get Country Settings Programmatically

```php
// Get settings manager
$settingsManager = \Drupal::service('bioland.settings_manager');

// Get all home widget settings with defaults applied
$widgetSettings = $settingsManager->getHomeWidgetSettings();

// Access specific country
$lkSettings = $widgetSettings['gbif_widget']['countries']['lk'];
echo $lkSettings['zoomLevel'];  // 5
```

### JavaScript: Use in Custom Widget

```javascript
Drupal.behaviors.myCustomWidget = {
  attach: function (context, settings) {
    var homeWidgets = window.Bioland.homeWidgets;
    
    if (homeWidgets.gbif_widget.enable) {
      var countries = homeWidgets.gbif_widget.countries;
      
      // Initialize widget for each country
      Object.keys(countries).forEach(function(countryCode) {
        var cfg = countries[countryCode];
        initializeMap(countryCode, cfg.zoomLevel, cfg.longitude, cfg.latitude);
      });
    }
  }
};
```

### Admin: Override Preset

1. Navigate to: `/admin/config/bioland/settings/front-end/home-widgets`
2. Expand "GBIF Widget" section
3. Expand country (e.g., "Country: LK")
4. See preset defaults in field descriptions:
   - Zoom Factor: "(Preset default: 5)"
   - Longitude: "(Preset default: 80.669312)"
   - Latitude: "(Preset default: 7.696631)"
5. Change values to customize
6. Save form

## Testing

### Run JavaScript Tests
```bash
npm test
```

Tests verify:
- Behavior registration
- Global settings availability
- Country-specific data loading
- Multiple country handling
- Graceful error handling

### Run PHP Tests
```bash
composer test
```

### Manual Testing

1. **Add new country to site:**
   - Go to `/admin/config/bioland/settings`
   - Add country code (e.g., 'us')
   - Save

2. **Verify presets applied:**
   - Go to `/admin/config/bioland/settings/front-end/home-widgets`
   - Expand "GBIF Widget" → "Country: US"
   - Check defaults shown in descriptions

3. **Check frontend:**
   - Open browser console on any page
   - Type: `window.Bioland.homeWidgets`
   - Verify country settings present

4. **Test customization:**
   - Change zoom level for a country
   - Save form
   - Reload page
   - Verify custom value in console

## Benefits

1. **No Manual Configuration Needed**
   - Adding a country automatically applies optimal map settings
   - Reduces setup time for site administrators

2. **Flexibility**
   - Users can customize any value
   - Custom values always take precedence
   - Can reset by clearing config

3. **Developer-Friendly**
   - Clear API (both PHP and JavaScript)
   - Comprehensive documentation
   - Service-based architecture

4. **Maintainable**
   - Centralized defaults in one service
   - Easy to add new countries
   - Follows Drupal best practices

## Country Data Coverage

- **Total Countries:** 240+
- **ISO Codes:** AD to ZW
- **Zoom Levels:** 2-255 (optimized per country size)
- **Coordinates:** Geographic center points with 6-decimal precision

## Future Enhancements

Potential improvements:
1. Add UI for resetting individual countries to presets
2. Bulk import/export of custom settings
3. Visual map preview in settings form
4. Historical tracking of coordinate changes
5. Integration with external geocoding APIs

## Related Documentation

- [Country Map Defaults](docs/COUNTRY_MAP_DEFAULTS.md) - Complete developer guide
- [Main README](README.md) - Module overview
- [AI Agent Instructions](.github/copilot-instructions.md) - Development guidelines
