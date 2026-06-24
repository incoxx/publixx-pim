---
title: AI & Automation
---

# AI & Automation

anyPIM bundles several AI-assisted features that speed up day-to-day work with product data — from a conversational assistant to semantic quick search to the automatic generation of social-media videos.

## Overview

| Feature | Description | Requirement |
|---|---|---|
| [**Copilot**](/en/ai/copilot) | In-app chat assistant that reads from the PIM and executes changes only after confirmation | Anthropic API key, MCP endpoint |
| [**Semantic Search**](/en/ai/semantic-search) | Natural-language quick search with constraint extraction and hybrid ranking | Meilisearch (optionally with an embedder) |
| [**Reel Generator**](/en/ai/reel-generator) | Generates social-media product videos (reels/shorts) incl. AI voiceover from PIM data | Video engine, optionally Claude & ElevenLabs |

## Shared concepts

All AI features follow the same principles:

- **Existing PIM data as the source:** No separate data silos are maintained. Attributes, media, prices and hierarchies are the foundation.
- **Human-in-the-loop:** Write actions (e.g. via the Copilot) are presented to the user for confirmation before execution.
- **Deterministic where possible:** Semantic search extracts numbers, units and prices rule-based (without an LLM) and uses AI only where it adds real value.
- **Optional activation:** Each feature can be enabled or disabled independently via environment variables and permissions. If an API key is missing, the feature stays disabled or degrades to a local fallback.

::: tip Note
The AI features are protected by the permission system. Which role may see and execute which feature is configurable under [Roles & Permissions](/en/administration/roles).
:::
