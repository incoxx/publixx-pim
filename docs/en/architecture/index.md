---
title: Architecture Overview
---

# Architecture Overview

Publixx PIM is designed as a modern web application with a clearly separated backend and frontend. The backend provides a complete REST API, and the frontend consumes it as a single-page application. This architecture enables both usage through the web interface and direct API integration by third-party systems.

## Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel (PHP 8.2+) |
| **Frontend** | Vue 3 + Tailwind CSS + DaisyUI |
| **Database** | MySQL / MariaDB |
| **Cache** | Redis |
| **Search** | PQL (Product Query Language) |
| **Web Server** | Nginx |

## Key Architectural Decisions

### EAV (Entity-Attribute-Value)

The core data model uses an EAV architecture, allowing unlimited flexible product attributes without schema changes. This provides maximum flexibility for diverse product catalogs.

### Inheritance System

Product variants automatically inherit attributes from parent products. Changes propagate in real-time, ensuring data consistency across the entire product hierarchy.

### Service Layer

Business logic is encapsulated in dedicated service classes, keeping controllers thin and logic reusable across API and UI contexts.

### PQL Query Engine

A custom query language (PQL — Product Query Language) enables complex product filtering across any attribute combination, including fuzzy text search and range queries.

::: tip
The German documentation is more comprehensive. For detailed architecture documentation, see the [German architecture overview](/de/architektur/).
:::
