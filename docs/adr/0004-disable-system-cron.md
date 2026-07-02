---
status: accepted
date: 2026-06-24
deciders: [randy]
context: system-wide
code-path: includes/bioland.install.helpers.inc
origin: standalone
---

# 0004. Disable Drupal system cron in favour of external scheduling

`bioland_update_9061` sets the state key `system.cron_disabled` to true, turning off Drupal's
built-in cron (BL-739 is the originating tracker ticket and commit reference, not an identifier
present in the code). Scheduled work that would normally ride core cron, including Search API
indexing, is expected to be driven externally rather than by Drupal's own scheduler.

We disabled system cron so scheduling is controlled by infrastructure outside the application,
giving predictable, observable runs instead of cron firing on page requests. The trade-off is that
the module only sets the flag: it contains no `hook_cron` guard that reads it, so honouring the flag
and providing the replacement schedule are the deployment's responsibility, not the module's.

## Consequences

- Any environment running this module must provide an external trigger for periodic work, or
  indexing and other cron tasks will not run.
- Because enforcement lives outside the repo, this decision is invisible from the module's runtime
  code alone, which is why it is recorded here.
