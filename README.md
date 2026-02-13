# Berufsmesse

Kurze Projektbeschreibung
- Webanwendung zur Verwaltung und Darstellung einer Berufsmesse (Aussteller, Stände, Termine, Besucherregistrierung).
- Ziel: einfache Bereitstellung (Docker / GitHub Container Registry), lokale Entwicklung und CI/CD-Integration.

Inhalt
- Features
- Architektur & Technologien
- Voraussetzungen
- Schnellstart (lokal)
- Installation mit Docker (GitHub Package / ghcr.io)
- Konfiguration (Beispiel .env)
- Entwicklung & Tests
- CI / Veröffentlichung (GitHub Actions)
- Beitragende & Lizenz

Features
- Verwaltung von Ausstellern und Events
- Responsive Web-Front‑/Backend (API)
- Dockerisiert für einfache Verteilung
- CI/CD-ready (GitHub Actions & GitHub Container Registry)

Architektur & Technologien (Beispiel)
- Frontend: React / Vue / Angular (ersetzbar)
- Backend: Node / Python / Go (API)
- Datenbank: PostgreSQL (Docker-Compose)
- Container: Docker, Images in GitHub Container Registry (ghcr.io)

Voraussetzungen
- Docker (>= 20.x)
- optional: docker-compose
- falls private Images oder Push: GitHub Personal Access Token (PAT) mit scopes:
    - read:packages (ziehen privater Images)
    - write:packages, delete:packages (pushen/löschen)
    - repo (wenn Repository privat)

Schnellstart (lokal, Source)
- Code klonen:
    git clone https://github.com/<OWNER>/berufsmesse.git
    cd berufsmesse
- Abhängigkeiten installieren & Dev-Server starten (Beispiel Node):
    npm ci
    npm run dev
- Oder mit Docker Compose:
    docker-compose up --build

Installation mit Docker über GitHub Package (GitHub Container Registry — ghcr.io)

1) Öffentliche Image-Nutzung (kein Login nötig)
- Pull:
    docker pull ghcr.io/<OWNER>/berufsmesse:latest
- Run:
    docker run --rm -p 3000:3000 --env-file .env ghcr.io/<OWNER>/berufsmesse:latest

2) Private Image-Nutzung (Login erforderlich)
- Login (lokal):
    echo "<GHCR_PAT>" | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin
    docker pull ghcr.io/<OWNER>/berufsmesse:latest
- Run:
    docker run --rm -p 3000:3000 --env-file .env ghcr.io/<OWNER>/berufsmesse:latest

3) Image bauen & in ghcr.io pushen (lokal)
- Build:
    docker build -t ghcr.io/<OWNER>/berufsmesse:1.0.0 .
- Login:
    echo "<GHCR_PAT>" | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin
- Push:
    docker push ghcr.io/<OWNER>/berufsmesse:1.0.0

4) Beispiel docker-compose.yml (pull von ghcr.io)
    version: "3.8"
    services:
        app:
            image: ghcr.io/<OWNER>/berufsmesse:latest
            ports:
                - "3000:3000"
            env_file: .env
        db:
            image: postgres:14
            environment:
                POSTGRES_USER: berufs
                POSTGRES_PASSWORD: secret

Hinweise zu GitHub Packages
- Für private Images: PAT mit read:packages erforderlich zum Pull.
- Für GitHub Actions: GITHUB_TOKEN kann zum Push genutzt werden, sofern repository permissions packages: write erlaubt sind.

Konfiguration (.env — Beispiel)
    PORT=3000
    DATABASE_URL=postgres://user:pass@db:5432/berufsmesse
    NODE_ENV=production
    JWT_SECRET=replace_me

Entwicklung & Tests
- Lokaler Dev-Start: npm run dev | make dev | docker-compose -f docker-compose.dev.yml up --build
- Tests: npm test | pytest (je nach Stack)
- Linter / Formatter: npm run lint | make format

CI / Veröffentlichung (GitHub Actions — Beispiel)
- Workflow-Punkte:
    - Build & Test
    - Build Docker-Image
    - Push zu ghcr.io (permissions: packages: write)
- Minimaler Workflow-Snippet:
    permissions:
        contents: read
        packages: write
    jobs:
        build-and-push:
            runs-on: ubuntu-latest
            steps:
                - uses: actions/checkout@v4
                - uses: docker/login-action@v2
                    with:
                        registry: ghcr.io
                        username: ${{ github.actor }}
                        password: ${{ secrets.GITHUB_TOKEN }}
                - uses: docker/build-push-action@v4
                    with:
                        context: .
                        push: true
                        tags: ghcr.io/${{ github.repository_owner }}/berufsmesse:latest

Troubleshooting (kurz)
- 401 beim Pull: PAT prüfen / token scopes / Image-Visibility
- Port belegt: anderen Port mappen (-p HOST:CONTAINER)
- DB-Verbindungsfehler: .env prüfen, DB erreichbar?