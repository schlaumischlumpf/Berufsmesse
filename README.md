# Berufsmesse

Willkommen im Repository für das Berufsmesse-Projekt. Dieses Projekt stellt eine Plattform zur Verwaltung und Präsentation einer Berufsmesse bereit.

## Inhaltsverzeichnis

- [Über das Projekt](#über-das-projekt)
- [Features](#features)
- [Voraussetzungen](#voraussetzungen)
- [Installation mit Docker](#installation-mit-docker)
    - [Image von GitHub Packages pullen](#image-von-github-packages-pullen)
    - [Container starten](#container-starten)
    - [Docker Compose (Empfohlen)](#docker-compose-empfohlen)
- [Entwicklung](#entwicklung)
- [Lizenz](#lizenz)

## Über das Projekt

Dieses System wurde entwickelt, um die Organisation von Berufsmessen zu vereinfachen. Es ermöglicht Ausstellern, sich zu präsentieren, und Besuchern, Informationen über verfügbare Stände und Unternehmen zu erhalten.

## Features

*   Verwaltung von Ausstellern und Ständen
*   Besucher-Frontend zur Orientierung
*   Admin-Dashboard
*   Responsive Design für mobile Nutzung

## Voraussetzungen

Für den Betrieb der Anwendung wird eine Docker-Laufzeitumgebung benötigt.

*   Docker Engine
*   Docker Compose (optional, aber empfohlen)

## Installation mit Docker

Dieses Projekt stellt ein Docker Image über die GitHub Container Registry (GHCR) bereit. Sie können dieses Image direkt auf Ihren Server ziehen, ohne den Quellcode bauen zu müssen.

### Image von GitHub Packages pullen

Zuerst müssen Sie sich an der GitHub Container Registry anmelden (falls das Image privat ist) oder es direkt pullen (falls öffentlich).

```bash
# Beispiel für öffentliches Image
docker pull ghcr.io/schlaumischlumpf/berufsmesse:latest
```

*Ersetzen Sie `username` durch den tatsächlichen GitHub-Benutzernamen oder die Organisation.*

### Container starten

Starten Sie den Container mit folgendem Befehl. Achten Sie darauf, die Ports entsprechend Ihrer Serverkonfiguration anzupassen.

```bash
docker run -d \
    --name berufsmesse \
    -p 8080:80 \
    -e ENV_VAR_EXAMPLE=wert \
    ghcr.io/username/berufsmesse:latest
```

### Docker Compose (Empfohlen)

Für den produktiven Einsatz empfiehlt sich die Verwendung einer `docker-compose.yml`.

Erstellen Sie eine Datei `docker-compose.yml` auf Ihrem Server:

```yaml
version: '3.8'

services:
    app:
        image: ghcr.io/username/berufsmesse:latest
        container_name: berufsmesse
        restart: unless-stopped
        ports:
            - "8080:80"
        environment:
            # Passen Sie diese Umgebungsvariablen an Ihre App an
            - NODE_ENV=production
            - DB_HOST=db
            - DB_USER=example
            - DB_PASS=secret
        depends_on:
            - db

    db:
        image: postgres:15-alpine
        container_name: berufsmesse_db
        restart: unless-stopped
        environment:
            - POSTGRES_USER=example
            - POSTGRES_PASSWORD=secret
            - POSTGRES_DB=berufsmesse
        volumes:
            - db_data:/var/lib/postgresql/data

volumes:
    db_data:
```

Starten Sie den Stack mit:

```bash
docker-compose up -d
```

## Entwicklung

Um lokal an diesem Projekt zu arbeiten:

1.  Repository klonen:
        ```bash
        git clone https://github.com/username/berufsmesse.git
        ```
2.  Abhängigkeiten installieren:
        ```bash
        npm install
        ```
3.  Entwicklungsserver starten:
        ```bash
        npm run dev
        ```

## Lizenz

Dieses Projekt ist unter der MIT Lizenz lizenziert - siehe die [LICENSE.md](LICENSE.md) Datei für Details.