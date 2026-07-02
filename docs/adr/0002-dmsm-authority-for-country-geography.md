---
status: accepted
date: 2026-06-24
deciders: [randy]
context: system-wide
code-path: src/Service/BiolandDmsmConfigService.php
origin: standalone
---

# 0002. DMSM is the authority for a site's country geography

A Bioland site's countries, region, and continent come from the DMSM API, addressed by parsing the
site's own hostname into environment, multi-site code, and site code. We treat DMSM as the single
upstream authority: on fetch we **replace** the local `countries` list entirely rather than merging,
and we set `is_biosafety_land` from the parsed multi-site code at the same time.

The install-time fetch is non-blocking (it falls back to the seeded default if DMSM is unreachable),
but the update-time fetch (`bioland_update_9023`) is **blocking**: if DMSM cannot supply countries,
the update fails. We chose the blocking update so a deployment can never leave stale or partial
geography on a site; the cost is that a routine `drush updb` depends on DMSM being available.

## Considered Options

- Merge DMSM countries into the existing list. Rejected: a site that drops a country would keep it
  forever, and there would be no single source of truth.
- Make the update non-blocking like install. Rejected: silently keeping old countries on a failed
  fetch is exactly the stale-data risk we are trying to remove.

## Consequences

- A DMSM outage blocks `drush updb` until resolved. This is deliberate.
- Country geography is never edited by hand as the source of truth; manual edits are overwritten on
  the next DMSM fetch.
