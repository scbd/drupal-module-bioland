---
type: project
references: [docs/CONTEXT.md, docs/architecture.md, docs/adr/]
date: 2026-06-24
---

# Bioland Module PRD

## Problem Statement

SCBD runs many national clearing-house websites (Bioland sites and Biosafety Land sites) on Drupal.
Each site shares the same shape: one primary content type, the same editorial conveniences, the same
need for content in many languages, the same homepage widgets, and geography that depends on which
country the site serves. Without a shared module, every site would re-implement that behaviour by
hand. Content editors would face a cluttered node form, would have to remember to create translation
placeholders for each language, and could accidentally delete users or break navigation. Site
managers would have to configure countries, maps, menus, and search by hand on every deployment, and
keep that config in sync with the central DMSM source of truth.

## Solution

A single Drupal module, `bioland`, that installs and maintains the whole site-behaviour layer for
both Bioland and Biosafety Land sites. It provisions the content model, roles, menus, search, and
text formats through versioned update hooks; sources a site's countries, region, and continent from
the DMSM API; tailors the content form with field visibility, additional fields, auto summary, and
help comments; creates translation defaults automatically on save; locks down dangerous editor
actions; and publishes settings and per-country map data for the separate Nuxt front end to consume.
The `is_biosafety_land` flag, derived from the hostname, switches branding and the active content
terms and menus so the one module serves both site types.

## User Stories

### Site manager / SCBD staff

1. As a site manager, I want one settings screen with tabs for general, field behaviour, tags, help
   comments, front end, system functions, and admin, so that I can configure the whole site in one
   place.
2. As a site manager, I want the site's countries to come from DMSM automatically, so that I do not
   have to enter ISO codes by hand or keep them in sync.
3. As a site manager, I want the site's region and continent to be set from DMSM too, so that
   geography is consistent with the central record.
4. As a site manager, I want a site to know whether it is a Bioland or a Biosafety Land site from its
   hostname, so that branding and available content adjust without manual flags.
5. As a site manager, I want each configured country to get sensible map zoom and centre defaults, so
   that the homepage map looks right before I touch anything.
6. As a site manager, I want to override a country's map zoom or coordinates and have my value win, so
   that I can correct a default that does not suit my site.
7. As a site manager, I want to choose which homepage widgets are enabled (GBIF, latest news,
   national targets, e-learning, cooperation, discussions, statistics, and others), so that the
   homepage shows only what is relevant.
8. As a site manager, I want to configure the mega menu (which menus appear and where), so that the
   front-end navigation matches the site's structure.
9. As a site manager, I want to rebuild the Drupal cache from the settings UI, so that I can clear
   stale content without shell access.
10. As a site manager, I want the main menu locked against non-administrators, so that navigation is
    not accidentally restructured.
11. As a site manager, I want field-visibility rules expressed as JSON, so that I can define
    conditional form fields per content term without code.
12. As a site manager, I want to pick which content terms show URL, published, and date-range fields,
    so that the form only asks for fields that apply.
13. As a site manager, I want help-comment text per field, translatable per language, so that editors
    get guidance in their own language.
14. As an administrator, I want certain tabs (system functions, admin) restricted to the
    administrator role, so that destructive operations are not exposed to ordinary managers.

### Content editor

15. As a content editor, I want the node form to hide fields that do not apply to the content term I
    chose, so that the form stays focused.
16. As a content editor, I want a summary generated from the body as I type, so that I do not have to
    write one separately.
17. As a content editor, I want term-specific additional fields (event status, project status,
    organization type, ecosystem type, document type) to appear automatically, so that I capture the
    right metadata.
18. As a content editor, I want inline help next to the body, attachments, promotion, and order
    fields, so that I understand what each field is for.
19. As a content editor, I want language, order override, and meta tucked into the advanced sidebar,
    so that the main form is uncluttered.
20. As a content editor, I want the save button to read "Save" rather than "Save (this translation)",
    so that the label is not confusing.
21. As a content editor, I want to set an order override on a piece of content, so that I can control
    where it sorts in lists and search.
22. As a content editor, I want to switch the content language from the sidebar and land on the right
    URL, so that editing in another language is smooth.
23. As a content editor working in a non-source language, I want the menu-parent control hidden, so
    that I cannot accidentally restructure navigation from a translation.
24. As a content editor, I do not want to see the Lolspeak development language in language pickers or
    translation tabs, so that my options are the real languages.

### Translator / multilingual platform

25. As the platform, I want a translation default created in every target language when a node is
    saved, so that content is reachable in all enabled languages from the start.
26. As the platform, I want those defaults marked outdated, so that editors can see they still need
    real translation.
27. As the platform, I want an existing proper translation never overwritten, so that real
    translation work is never lost.
28. As the platform, I want translation creation to be configurable (all languages or a chosen set,
    copy source values or not, which entity types), so that each site can tune the behaviour.
29. As a site manager, I want to backfill translation defaults for existing content in batches, so
    that a site that already has content can adopt the feature.
30. As the platform, I want source timestamps preserved when adding translations, so that backfill
    does not bump every node's changed date.
31. As the platform, I want the module to ship `.po` files for the languages it supports, so that the
    module's own strings are translated.

### Front-end consumer (bioland-head)

32. As the front end, I want the site's home-widget settings, including per-country GBIF map data,
    available on every page, so that I can render widgets without extra calls.
33. As the front end, I want mega-menu configuration exposed as plain config, so that I can build
    navigation from it.
34. As the front end, I want country map data already merged with defaults server-side, so that I do
    not duplicate the fallback logic.

### Platform safety and governance

35. As the platform, I want the destructive user-cancel options removed, so that accounts and their
    content cannot be deleted by mistake.
36. As the platform, I want a fixed allowlist of named SCBD staff accounts granted the scbd_staff
    role, and a fixed list of legacy accounts (including some @cbd.int and external addresses)
    blocked, so that access reflects current policy.
37. As the platform, I want roles (SCBD staff, site manager, content manager, contributor, system)
    and their permissions provisioned consistently, so that every site has the same access model.
38. As the platform, I want maintenance-mode access restricted to administrators and SCBD staff, so
    that ordinary managers cannot take a site down.
39. As the platform, I want admin-page links cache-busted on admin routes, so that editors do not see
    stale cached content after saving.
40. As the platform, I want `field_order` indexed for search, so that ordered results respect editor
    intent.
41. As the platform, I want auto_node_translate's admin menu items hidden, so that editors are not
    confused by machine-translation provider settings.
42. As the platform, I want JSON:API surface trimmed to what the front end needs, so that internal
    resources are not exposed.

## Implementation Decisions

- **One config object.** Nearly all site behaviour is stored in `bioland.settings`, validated by a
  config schema. Country map defaults are the exception: they live in code (a static service) because
  they are reference data, not per-site config.
- **Site type from hostname.** `is_biosafety_land` and the DMSM addressing are derived by parsing the
  hostname into environment, multi-site code, and site code. There is no manual site-type switch.
- **DMSM is the geography authority.** The country list is replaced (not merged) from DMSM. The
  install-time fetch is non-blocking; the update-time fetch is blocking and fails the update if DMSM
  cannot supply countries. Recorded in an ADR.
- **Translation defaults, not machine translation.** The module creates placeholder translations on
  save and marks them outdated; it never calls DeepL or Amazon. Re-entrancy is guarded, proper
  translations are never overwritten, and source timestamps are preserved. Recorded in an ADR.
- **Feature toggles drive attachment.** Field visibility, additional fields, auto summary, and help
  comments each have an enable flag; the form alter attaches only the libraries for enabled features
  and passes a single `drupalSettings.bioland` payload.
- **Provisioning is versioned and idempotent.** Setup is expressed as numbered update hooks
  (currently through 9061) split into per-concern include files, so installs and upgrades are
  re-runnable and reviewable. Helpers skip cleanly when an optional dependency module is absent.
- **Search uses the database backend.** The `content` index runs on Search API's database backend,
  not Solr. A newer v2 install path applies serialized production config and rebuilds the index.
- **System cron disabled.** An update hook sets `system.cron_disabled`; scheduling is expected to be
  driven externally (BL-739). Recorded in an ADR.
- **Front-end decoupling.** Settings and per-country widget data are published as plain config the
  Nuxt front end reads over JSON:API; the map-default fallback chain is resolved server-side.
- **Client behaviours avoid `once()`.** Browser behaviours use data-attribute init guards and value
  tracking rather than jQuery `.once()` / Drupal `once()`, which misbehave in this module.

## Testing Decisions

- **Test external behaviour at the service and behaviour seam.** PHPUnit unit tests cover the
  services and the menu-link deriver (`tests/Unit/*`), using lightweight Drupal stubs
  (`tests/stubs/*`) so the suite runs without a full Drupal bootstrap. New service logic should be
  tested the same way: assert the observable result (config written, translations created, payload
  shape), not internal calls.
- **Hostname parsing is the highest-value unit seam for DMSM.** `BiolandDmsmConfigServiceTest`
  exercises every hostname pattern (dev/staging/prod, bl2/bsl, the biodiv.* special cases, invalid
  hosts). Any new pattern gets a case there.
- **Browser behaviours are tested with Jest.** Each `js/*` behaviour has a matching
  `js/*.test.js` (field visibility, additional fields, auto summary, help comments, home widgets,
  hide bulk actions, settings toggle, debug logger). Test the public behaviour and globals, mirroring
  the existing tests.
- **Guard against duplicate function declarations.** The suite includes tests
  (`DuplicateFunctionDeclarationTest`, `InstallDuplicateFunctionsTest`) that protect against the same
  function being declared twice across the install include files. Keep them green when adding hooks.
- **Translation manager is the deepest logic.** `BiolandTranslationManagerTest` should cover the
  no-overwrite rule, the source-language skip, the enabled-type and auto-create gates, and the
  outdated flag, since those are the rules editors rely on.

## Success Metrics

- A fresh install on a recognised hostname ends with countries, region, and `is_biosafety_land`
  populated from DMSM with zero manual entry.
- `drush updb` on an existing site applies geography from DMSM, and fails loudly rather than keeping
  stale countries when DMSM is unavailable.
- Saving a translatable node in an enabled type creates one translation default per target language,
  each marked outdated, with no existing proper translation overwritten and the source `changed`
  timestamp unchanged.
- Every configured country resolves to map settings (override, else preset, else generic) on the
  front end, for all ~240 preset countries.
- The PHPUnit and Jest suites pass, including the duplicate-function guards.
- An editor on a Biosafety Land site sees biosafety-appropriate terms and branding; the same module
  on a Bioland site shows Bioland branding, with no code fork.

## Out of Scope

- Rendering the public website. The Nuxt front end owns presentation; this module only publishes
  config and data.
- Machine translation. Translation defaults are placeholders; actual translation is manual or done by
  the separate auto_node_translate contrib modules.
- Owning the DMSM model or writing back to it. Bioland is a downstream consumer of DMSM geography.
- The scbd_field widget itself. Additional fields are mounted here but the picker UI is the sibling
  scbd_field module / scbd-field-js widget.
- Solr search. The index uses the database backend; Solr is not configured by this module.
- Scheduling/cron execution. The module disables system cron and assumes external scheduling.

## Further Notes

The top-level `README.md`, `IMPLEMENTATION_COUNTRY_DEFAULTS.md`, and `docs/COUNTRY_MAP_DEFAULTS.md`
predate the current code in places (versioned JS filenames, a debug route, some API names). When they
disagree with the source, the source is correct. These documents (`docs/CONTEXT.md`,
`docs/architecture.md`, this PRD, and the ADRs) are the reverse-engineered, code-checked record.
