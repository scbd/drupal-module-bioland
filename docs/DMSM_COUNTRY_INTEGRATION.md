# DMSM Country Configuration Integration

## Overview
This implementation adds automatic fetching and configuration of country settings from the DMSM (Dynamic Multi-Site Manager) API based on the site's hostname.

## Components Added

### 1. Service: `BiolandDmsmConfigService`
**Location:** `src/Service/BiolandDmsmConfigService.php`

A new Drupal service that:
- Parses hostnames to determine environment, multi-site code, and site code
- Makes HTTP requests to the DMSM API
- Extracts country data from the API response
- Updates the `bioland.settings` configuration with countries
- Also extracts and saves `region` and `continent` from the API response when present
- Sets `is_biosafety_land` flag based on multi-site code (true for bsl, false for bl2)
- Also provides `getEffectiveTheme()`, which fetches and merges DMSM's per-network theme document
  as part of the theme precedence chain (see `docs/adr/0006-theme-authority.md`)

**Service Registration:** Added to `bioland.services.yml` as `bioland.dmsm_config`

### 2. Helper Function: `_bioland_update_countries_from_dmsm()`
**Location:** `bioland.install` (line ~334)

A helper function that:
- Calls the DMSM service
- Returns status messages
- Handles errors gracefully without failing installation/updates

### 3. Install Hook Integration
**Location:** `bioland_install()` in `bioland.install`

Added after default country configuration:
- Attempts to fetch countries from DMSM API during module installation
- Falls back silently if API is unavailable
- Logs warnings if the fetch fails

### 4. Update Hook: `bioland_update_9023()`
**Location:** `bioland.install` (line ~1815)

An update hook that:
- Fetches countries from DMSM API for existing installations
- **CRITICAL**: Completely replaces existing countries configuration (no merge)
- **FAILS the update** if countries cannot be fetched from DMSM API
- Must succeed to allow the update to proceed

### 5. Unit Tests
**Location:** `tests/Unit/Service/BiolandDmsmConfigServiceTest.php`

Tests for hostname parsing covering all patterns:
- Development environments (cbddev.xyz)
- Staging environments (staging.cbd.int)
- Production environments (chm-cbd.net, biodiv.be, biodiv.mnhn.fr)
- Both bl2 and bsl multi-site codes
- Invalid hostnames

## Hostname Parsing Logic

### Environment Detection
- `*.cbddev.xyz` → `dev`
- `*.staging.cbd.int` → `stg`
- `*.chm-cbd.net` → `prod`
- `www.biodiv.be`, `biodiv.be`, `biodiv.mnhn.fr` → `prod`

### Multi-Site Code Detection
- Contains `.bsl.` → `bsl`
- Contains `.bl2.` → `bl2`
- `www.biodiv.be`, `biodiv.be`, `biodiv.mnhn.fr` → `bl2`

### Site Code Detection
- `www.biodiv.be`, `biodiv.be` → `be`
- `biodiv.mnhn.fr` → `fr`
- `{siteCode}.{multiSiteCode}.{baseHost}` → `{siteCode}`

## API Integration

### Endpoint
```
https://dmsm.cbddev.xyz/api/config/{env}/{multiSiteCode}/{siteCode}
```

### Expected JSON Response Structure
```json
{
  "data": {
    "runTime": {
      "countries": ["us", "ca", "mx"]
    }
  }
}
```

Or with fallback format (single country):
```json
{
  "data": {
    "country": "be"
  }
}
```

### Response Parsing
1. **Primary:** `data.runTime.countries` (array of country codes)
2. **Fallback:** `data.country` (single string country code - converted to array internally)

The response must be a valid JSON object with a `data` property. The service validates:
- Response is valid JSON
- Response is an object (not array or primitive)
- `data` property exists and is an object
- Either `data.runTime.countries` (array) or `data.country` (string) contains country data
- At least one valid country code is present

**Important:** The countries configuration is **completely replaced** with new values from the API. Existing countries in `bioland.settings` are not preserved or merged.

### Error Handling
- HTTP errors are caught and logged
- JSON parsing errors are handled
- Missing/empty country data is detected
- Invalid hostnames return null
- **Update hook will FAIL** if countries cannot be fetched (prevents proceeding with stale data)

## Usage

### On Fresh Installation
Countries are automatically fetched and configured when the module is installed.

### On Existing Installations
Run database updates to fetch current country configuration:
```bash
drush updb
```

**Important:** If the DMSM API is unavailable or returns no countries, the update will fail and must be resolved before proceeding. This ensures the site always has valid country configuration.

### Manual Testing
You can test the service directly via Drush:
```php
drush php-eval "\$service = \Drupal::service('bioland.dmsm_config'); print_r(\$service->updateCountriesFromDmsm());"
```

## Configuration Storage
**CRITICAL:** When updating from DMSM API, the countries array is **completely replaced** - existing values are not preserved or merged. The configuration will only contain the new values returned by the API.

Countries are stored in `bioland.settings` configuration under the `countries` key as a sequence of ISO country codes.

The `is_biosafety_land` boolean flag is automatically set:
- `true` when hostname contains `.bsl.` (Biosafety Land sites)
- `false` when hostname contains `.bl2.` (Bioland sites)

## Logging
All operations are logged to the `bioland` log channel:
- Info: Successful fetches with countries list
- Error: API failures, parsing errors, invalid hostnames
- Warning: Install-time failures (non-blocking)

## Benefits
1. **Automatic Configuration:** No manual country setup required
2. **Environment-Aware:** Correctly identifies dev/staging/prod environments
3. **Multi-Site Support:** Handles both bl2 and bsl deployments
4. **Graceful Degradation:** Falls back to defaults if API unavailable
5. **Centralized Management:** Single source of truth via DMSM API
