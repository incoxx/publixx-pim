---
title: Installation - Übersicht
---

# Installation

Dieses Kapitel beschreibt die vollständige Einrichtung des Publixx PIM -- von den Systemvoraussetzungen über die lokale Entwicklungsumgebung bis hin zum produktiven Deployment auf einem Linux-Server.

## Kapitelstruktur

### [Voraussetzungen](./voraussetzungen)

Detaillierte Aufstellung aller Software- und Hardwareanforderungen, die das Publixx PIM benötigt. Hier erfahren Sie, welche PHP-Erweiterungen installiert sein müssen, welche MySQL-Version unterstützt wird und wie die empfohlene Serverdimensionierung aussieht.

### [Schnellstart](./schnellstart)

Schritt-für-Schritt-Anleitung, um das Publixx PIM in wenigen Minuten lokal zum Laufen zu bringen. Ideal für Entwickler, die sofort produktiv arbeiten möchten. Umfasst das Klonen des Repositorys, die Installation der Abhängigkeiten, die Konfiguration der Umgebungsvariablen sowie den Start der Entwicklungsserver.

### [Deployment](./deployment)

Anleitung für das produktive Deployment auf einem Ubuntu-Server mit Nginx, PHP-FPM, SSL-Zertifikaten, Supervisor für den Queue-Worker und dem automatisierten Deploy-Skript. Enthält ausserdem Empfehlungen zu Monitoring, Logging und Backup.

## Technologie-Stack

Das Publixx PIM basiert auf folgenden Kerntechnologien:

| Komponente | Technologie | Version |
|---|---|---|
| **Backend-Framework** | Laravel | 11.x |
| **Programmiersprache** | PHP | 8.3+ |
| **Frontend-Framework** | Vue.js | 3.x |
| **Build-Tool** | Vite | 6.x |
| **CSS-Framework** | Tailwind CSS + DaisyUI | 4.x |
| **Datenbank** | MySQL | 8.0+ |
| **Cache & Queue** | Redis | 6+ |
| **Webserver** | Nginx | 1.24+ |
| **Queue-Worker** | Laravel Horizon + Supervisor | -- |
| **Authentifizierung** | Laravel Sanctum | -- |

## Empfohlener Installationspfad

Für die meisten Anwendungsfälle empfehlen wir folgenden Ablauf:

1. **Voraussetzungen prüfen** -- Stellen Sie sicher, dass alle benötigten Dienste installiert und korrekt konfiguriert sind.
2. **Schnellstart durchführen** -- Richten Sie das System zunächst lokal ein und machen Sie sich mit der Konfiguration vertraut.
3. **Deployment planen** -- Übertragen Sie die Konfiguration auf Ihren Produktivserver und setzen Sie das automatisierte Deployment auf.

::: tip Hinweis
Wenn Sie das System ausschliesslich zur Evaluierung oder Entwicklung nutzen möchten, reicht der [Schnellstart](./schnellstart) aus. Das vollständige [Deployment](./deployment) ist nur für produktive Umgebungen erforderlich.
:::
