---
status: accepted
date: 2026-08-06
deciders: [randy]
context: system-wide
code-path: config/schema/bioland.schema.yml
origin: implementation
plan: themeing
phase: p01-01
---

# 0006. bioland.settings.theme is the per-site theme authority

Per-site theme (colors, home-widget columns, mega-menu bounds, language-bar wrap) moves out of
dmsm's JSON5 config and into Drupal, authored under `bioland.settings.theme` (snake_case keys). It
reaches head today through dmsm's existing `biolandSettings` attach, a live per-request Drupal read
camelCased at depth 7, so this decision needs zero dmsm changes, and the data migrates automatically
once a future config-endpoint document exists. Precedence: `biolandSettings.theme` overrides site
`config.theme`, then `runTime.theme`, then code defaults, so an unseeded site renders exactly as
today.

See [details](details/0006.md).
