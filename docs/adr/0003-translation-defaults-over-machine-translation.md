---
status: accepted
date: 2026-06-24
deciders: [randy]
context: system-wide
code-path: src/Service/BiolandTranslationManager.php
origin: standalone
---

# 0003. Create translation defaults on save, not machine translations

When a translatable node is saved, Bioland creates a placeholder translation (a "translation
default") in each target language rather than invoking machine translation. Each default is marked
`content_translation_outdated` so editors can see it still needs real translation, source field
values are optionally copied, and source timestamps are preserved so backfill does not bump every
node's changed date. A translation whose source language is already proper (not `und`) is **never**
overwritten.

We chose placeholders over machine translation so that every node is immediately reachable in every
enabled language without risking machine output being mistaken for finished, reviewed content. Actual
translation stays a human (or separate auto_node_translate) step.

## Considered Options

- Call DeepL / Amazon (the auto_node_translate contrib modules) on save. Rejected: it would produce
  unreviewed machine text presented as content, and couples every save to an external service.
- Do nothing automatic and let editors create each translation by hand. Rejected: content would be
  missing in most languages until someone remembered to add it.

## Consequences

- Editors must still do the real translation work; the default only guarantees the language exists
  and is flagged outdated.
- The manager guards against re-entrancy and saves the source entity once after adding all
  translations, so the hook is safe to fire on both insert and update.
