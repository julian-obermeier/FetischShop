# FetischShop – Ankaufsplattform

Produktionsorientierte, mobile-first Ankaufsplattform für volljährige Verkäuferinnen auf klassischem PHP/MySQL-Shared-Hosting.

## Zielumgebung
- PHP 8.1+
- MySQL/MariaDB
- Apache/mod_rewrite
- Europe/Berlin
- keine dauerhaften Worker, kein Redis-/Docker-Zwang
- private Medien außerhalb des öffentlichen Webroots

## Architektur
Eigener schlanker MVC-/Service-Kern mit PDO, Prepared Statements, CSRF, sicherer Sessionverwaltung, Migrationen, Installer, Updater, Cron-Scheduler und PWA.

## Status
Aktiver Neuaufbau nach MASTERPROMPT vom 20.09.2026.

Stand 0.3.0: Authentifizierung, Angebots-/Auftragsbasis, Nachweise, Aufgaben, Verstöße, Beschädigungen, Wallet/Auszahlungen, Versand/Wareneingang/Abschlussprüfung, digitale Versionen und Revisionen, Admin-Entscheidungen, Fristen, Kalender, globale Suche, Archiv, Systemeinstellungen, Ausfalldokumentation, Web-Updater, Cron-Scheduler und PWA sind in produktiver Grundlogik implementiert. Weitere Masterprompt-Bereiche werden schrittweise vervollständigt; der vollständige Endtest erfolgt erst nach Gesamtumsetzung.

## Deployment nach Update

Auf dem ALL-INKL-Webspace im Projektverzeichnis:

```bash
git pull origin main
```

Danach als Administrator unter `/admin/system/update` alle ausstehenden SQL-Migrationen ausführen.

Der Cronjob soll regelmäßig, empfohlen alle 5 Minuten, `bin/cron.php` mit PHP CLI ausführen. Er verarbeitet Nachweisfenster, Erinnerungen, Nachfristen, Verstöße, Privatangebotsfristen, Revisionen, Beschädigungsnachforderungen und Versandfristen idempotent.

Private Nachweise und digitale Medien liegen außerhalb des öffentlichen Webroots und werden nur über autorisierte PHP-Endpunkte ausgeliefert.
