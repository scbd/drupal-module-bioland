# ADR Index

| # | Decision (≤3 sentences) | Details | Status |
|---|-------------------------|---------|--------|
| [0001](0001-record-architecture-decisions.md) | We record architecturally-significant decisions as ADRs in `docs/adr/`, numbered and immutable. | — | accepted |
| [0002](0002-dmsm-authority-for-country-geography.md) | DMSM is the authority for a site's country geography; the install-time fetch is non-blocking, the update-time fetch is blocking. | — | accepted |
| [0003](0003-translation-defaults-over-machine-translation.md) | Bioland creates placeholder translation defaults on save instead of invoking machine translation. | — | accepted |
| [0004](0004-disable-system-cron.md) | Drupal's built-in system cron is disabled in favour of external scheduling. | — | accepted |
| [0005](0005-component-menu-authoring-surface.md) | A dedicated `/admin/structure/menu/manage/{menu}/add-component` route and form replace hand-typed `bl2-component-*` classes with a token picker for mega-menu components. | — | accepted |
| [0006](0006-theme-authority.md) | `bioland.settings.theme` is the per-site theme authority, reaching head via dmsm's existing `biolandSettings` attach, with a defined precedence order. | [details](details/0006.md) | accepted |
