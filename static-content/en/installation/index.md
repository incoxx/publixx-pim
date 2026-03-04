---
title: Installation - Overview
---

# Installation

This chapter describes the complete setup of Publixx PIM — from system requirements through the local development environment to production deployment on a Linux server.

## Chapter Structure

### [Requirements](./requirements)

Detailed listing of all software and hardware requirements for Publixx PIM. Learn which PHP extensions must be installed, which MySQL version is supported, and the recommended server sizing.

### [Quick Start](./quickstart)

Step-by-step guide to get Publixx PIM running locally in just a few minutes. Ideal for developers who want to be productive immediately. Covers cloning the repository, installing dependencies, configuring environment variables, and starting the development servers.

### [Deployment](./deployment)

Guide for production deployment on an Ubuntu server with Nginx, PHP-FPM, SSL certificates, Supervisor for the queue worker, and the automated deploy script. Also includes recommendations for monitoring, logging, and backup.

## Technology Stack

| Component | Technology | Version |
|---|---|---|
| **Backend Framework** | Laravel | 11.x |
| **Programming Language** | PHP | 8.3+ |
| **Frontend Framework** | Vue.js | 3.x |
| **Build Tool** | Vite | 6.x |
| **CSS Framework** | Tailwind CSS + DaisyUI | 4.x |
| **Database** | MySQL | 8.0+ |
| **Cache & Queue** | Redis | 6+ |
| **Web Server** | Nginx | 1.24+ |
| **Queue Worker** | Laravel Horizon + Supervisor | — |
| **Authentication** | Laravel Sanctum | — |

## License

Publixx PIM is released under the **AGPL-3.0-only** license. This means:

- You may freely use, modify, and distribute the software.
- Modifications to the software provided over a network must also be published under AGPL-3.0.
- The full license can be found in the `LICENSE` file in the project directory.

## Recommended Installation Path

1. **Check requirements** — Ensure all required services are installed and properly configured.
2. **Follow Quick Start** — Set up the system locally first and familiarize yourself with the configuration.
3. **Plan deployment** — Transfer the configuration to your production server and set up automated deployment.

::: tip Note
If you only want to use the system for evaluation or development, the [Quick Start](./quickstart) is sufficient. The full [Deployment](./deployment) is only required for production environments.
:::
