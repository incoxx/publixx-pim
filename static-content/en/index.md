---
layout: home
title: anyPIM Documentation
hero:
  name: anyPIM
  text: Product Information Management
  tagline: Flexible, powerful product data management with EAV architecture, inheritance, PQL query language, and seamless integration. Open Source (GPL-3.0).
  actions:
    - theme: brand
      text: Quick Start
      link: /en/installation/quickstart
    - theme: alt
      text: Unique Features
      link: /en/intro/unique-features
    - theme: alt
      text: API Reference
      link: /en/api/

# These cards mirror the sidebar's main sections 1:1 (same titles, same order) so the
# landing page and the navigation speak the same language.
features:
  - icon: 🚀
    title: Getting Started
    details: From a fresh server to a running installation in ten minutes.
    link: /en/installation/quickstart
  - icon: ⚙️
    title: Installation & Operations
    details: Requirements, environment variables, deployment, cron jobs, and search services.
    link: /en/installation/
  - icon: 📋
    title: Daily Business
    details: Products, variants, hierarchies, media, watchlist, workflow, and planning calendar.
    link: /en/usage/
  - icon: 🔧
    title: Configuration
    details: Set up attributes, dictionary, units, pricing, manufacturers, and relation types.
    link: /en/usage/attributes
  - icon: 🔄
    title: Import & Export
    details: Excel import with validation, JSON and Publixx export, BMEcat, export jobs.
    link: /en/import/
  - icon: 📤
    title: Publishing & Output
    details: Reports, PDF templates, catalog embed, portals, and social videos.
    link: /en/advanced/reports
  - icon: 🤖
    title: AI & Automation
    details: Copilot as an AI assistant and semantic search across all product data.
    link: /en/ai/
  - icon: 🌍
    title: Translation
    details: Translation memory for metadata and XLIFF jobs for product content.
    link: /en/usage/translations
  - icon: 📁
    title: Project Management
    details: Organise projects and teams, assign tasks, and track their progress.
    link: /en/usage/projects-teams
  - icon: 🔌
    title: Integrations & API
    details: 600+ REST endpoints, PQL, API designer, and connectors to shop systems.
    link: /en/api/
  - icon: 🛡️
    title: Administration
    details: Roles and permissions, users, audit, access links, and system tools.
    link: /en/administration/roles
  - icon: 🏗️
    title: Architecture
    details: EAV data model, inheritance system, services, and events in detail.
    link: /en/architecture/
  - icon: ❓
    title: FAQ
    details: Answers to the most frequently asked questions about anyPIM.
    link: /en/faq/
---

## Welcome

anyPIM is a **Product Information Management System** specifically designed for the demands of modern product data management. It combines the flexibility of an EAV architecture with the power of Laravel and Vue.js.

### Who is this documentation for?

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin: 1.5rem 0;">

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Users**

Want to maintain products, assign media, or run imports and exports?
→ Start with [Daily Business](/en/usage/)

</div>

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Administrators**

Setting up the system, granting permissions, and keeping it running?
→ Start with [Installation](/en/installation/) and [Administration](/en/administration/roles)

</div>

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Developers**

Want to use the API, integrate the system, or understand the architecture?
→ Start with the [API Reference](/en/api/) or the [Architecture](/en/architecture/)

</div>

</div>
