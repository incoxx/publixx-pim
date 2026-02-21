---
title: Voraussetzungen
---

# Voraussetzungen

Bevor Sie mit der Installation des Publixx PIM beginnen, stellen Sie sicher, dass Ihre Umgebung die folgenden Anforderungen erfüllt. Die Angaben beziehen sich auf den produktiven Betrieb -- für die lokale Entwicklung gelten dieselben Software-Voraussetzungen, die Hardware-Anforderungen sind jedoch geringer.

## Software-Anforderungen

### PHP 8.3+

Das Publixx PIM setzt PHP in Version 8.3 oder höher voraus. Folgende PHP-Erweiterungen müssen aktiviert sein:

| Erweiterung | Zweck |
|---|---|
| `pdo_mysql` | Datenbankverbindung zu MySQL |
| `redis` | Verbindung zum Redis-Cache und Queue-Broker |
| `mbstring` | Korrekte Verarbeitung von UTF-8-Zeichenketten |
| `xml` | Verarbeitung von XML-Daten und Konfigurationen |
| `zip` | Verarbeitung von ZIP-Archiven (Medien-Upload, Export) |
| `gd` | Bildverarbeitung (Thumbnails, Medienvorschau) |
| `bcmath` | Präzise mathematische Berechnungen (Preise, Einheiten) |
| `curl` | HTTP-Kommunikation mit externen Diensten |
| `intl` | Internationalisierung, Sortierung und Formatierung |

Prüfen Sie die installierten Erweiterungen mit:

```bash
php -m | grep -iE "(mysql|redis|mbstring|xml|zip|gd|bcmath|curl|intl)"
```

Alle aufgelisteten Erweiterungen sollten in der Ausgabe erscheinen. Fehlende Erweiterungen können unter Ubuntu wie folgt installiert werden:

```bash
sudo apt install php8.3-mysql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-gd php8.3-bcmath \
    php8.3-curl php8.3-intl
```

### MySQL 8.0+

MySQL 8.0 oder höher wird als Datenbank vorausgesetzt. Das System nutzt folgende MySQL-spezifische Features:

- **InnoDB** -- als Storage Engine für Transaktionssicherheit und Foreign Keys
- **JSON-Spalten** -- für flexible Metadaten und Konfigurationen
- **Common Table Expressions (CTEs)** -- für rekursive Hierarchie-Abfragen
- **FULLTEXT-Indizes** -- für die Volltextsuche in Produktdaten

::: warning Hinweis
MariaDB wird derzeit nicht offiziell unterstützt, da sich die JSON-Funktionen und CTE-Implementierungen teilweise unterscheiden.
:::

Empfohlene MySQL-Konfiguration (`my.cnf`):

```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size    = 512M
max_connections         = 200
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
sql_mode                = STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

### Redis 6+

Redis dient als Cache-Backend und Queue-Broker für Laravel Horizon. Version 6 oder höher ist erforderlich.

```bash
redis-server --version
```

Empfohlene Redis-Konfiguration:

```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
```

### Nginx oder Apache

Als Webserver wird **Nginx** empfohlen. Apache mit `mod_rewrite` wird ebenfalls unterstützt, ist jedoch nicht die primäre Zielplattform.

- **Nginx**: Version 1.24 oder höher
- **Apache**: Version 2.4 oder höher (mit `mod_rewrite` und `mod_headers`)

### Node.js 20+ und npm

Für das Bauen des Vue.js-Frontends wird Node.js in Version 20 (LTS) oder höher benötigt:

```bash
node --version   # >= v20.0.0
npm --version    # >= 10.0.0
```

### Composer 2.x

Der PHP-Paketmanager Composer wird in Version 2.x vorausgesetzt:

```bash
composer --version   # >= 2.0.0
```

Installation:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Supervisor

Supervisor wird zum Betrieb von Laravel Horizon (Queue-Worker) im Produktivbetrieb benötigt:

```bash
sudo apt install supervisor
```

## Hardware-Anforderungen

### Minimum (Produktivbetrieb)

Die folgenden Werte gelten als Mindestanforderung für einen produktiven Betrieb mit bis zu 50.000 Produkten und moderatem gleichzeitigem Zugriff. Als Referenz dient ein **IONOS Cloud VPS M**:

| Ressource | Anforderung |
|---|---|
| **vCPU** | 4 Kerne |
| **Arbeitsspeicher** | 8 GB RAM |
| **Speicherplatz** | 160 GB SSD |
| **Netzwerk** | 400 Mbit/s |

### Empfohlen (Produktivbetrieb)

Für grössere Produktbestände (100.000+ Produkte), umfangreiche Medien-Bibliotheken oder hohe gleichzeitige Zugriffszahlen empfehlen wir:

| Ressource | Anforderung |
|---|---|
| **vCPU** | 8 Kerne |
| **Arbeitsspeicher** | 16 GB RAM |
| **Speicherplatz** | 500 GB SSD (NVMe) |
| **Netzwerk** | 1 Gbit/s |

### Lokale Entwicklung

Für die lokale Entwicklung genügen in der Regel:

| Ressource | Anforderung |
|---|---|
| **CPU** | 2 Kerne |
| **Arbeitsspeicher** | 4 GB RAM |
| **Speicherplatz** | 20 GB freier Speicher |

## Betriebssystem

Das Publixx PIM ist für den Betrieb unter **Ubuntu 24.04 LTS** optimiert und getestet. Andere Linux-Distributionen (Debian 12, Rocky Linux 9) können ebenfalls verwendet werden, sind aber nicht offiziell getestet.

::: info Zusammenfassung der Versionsanforderungen
| Komponente | Mindestversion |
|---|---|
| PHP | 8.3 |
| MySQL | 8.0 |
| Redis | 6.0 |
| Node.js | 20.0 (LTS) |
| Composer | 2.0 |
| Nginx | 1.24 |
| Ubuntu | 24.04 LTS |
:::

## Nächster Schritt

Wenn alle Voraussetzungen erfüllt sind, fahren Sie mit dem [Schnellstart](./schnellstart) fort.
