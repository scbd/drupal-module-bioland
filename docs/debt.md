# Tech Debt Ledger

10-column markdown table of intentionally deferred issues, maintained per the `docs-debt` skill.

| ID | Date | Source | Location | Issue | Ceiling / Impact | Fix trigger | Priority | Complexity | Status |
|----|------|--------|----------|-------|-------------------|-------------|----------|------------|--------|
| `ac2277e` | 2026-08-06 | pr-code-reviewer | `src/Service/BiolandSettingsManager.php:51-71` | Home widget vocabulary lives in 4 places: `BiolandHomeWidgetRegistry::WIDGETS`, `BiolandSettingsManager::getHomeWidgetSettings()` (ships `home_widgets.*.enable` to head), `config/schema/bioland.schema.yml:381`, `config/install/bioland.settings.yml:114` | adding one home widget requires 4 coordinated edits; the enumerations can silently drift out of sync | phase 02 consolidation, or the next widget added | P3 | M | open |
