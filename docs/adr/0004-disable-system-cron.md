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
- **Open question (BL-992): what the external trigger actually is has not been established from
  this repository.** Nothing in the module, its docs, or `.github/workflows/ci.yml` names the
  mechanism. This matters for the `bioland_dmsm_geography` queue worker, which refuses to run under
  a web SAPI: if the trigger is `drush cron` / `drush queue:run` the design works, but if it is an
  HTTP GET to `/cron/{key}` then `PHP_SAPI` is `fpm-fcgi`, the worker refuses on every pass, and the
  queue grows without bound. Until an operator confirms the trigger, `hook_requirements('runtime')`
  reports a non-draining queue as a warning on the status report; that is a detector, not a fix.
