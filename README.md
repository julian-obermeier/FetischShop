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

Stand 0.4.0: Zusätzlich zu den 0.3.0-Grundworkflows sind echte Kombi-Aufträge mit komponentenbezogener Kategorieblockierung, Artikelerfassung, Vorabkontrolle und Tageslogik umgesetzt. Angebotsvorlagen sind wiederverwendbar, Optionen werden revisionssicher historisiert und können vor Start von der Verkäuferin bzw. danach nur vom Admin geändert werden. Bonus-, Preis- und Versandzuschussänderungen aktualisieren Wallet und Auftrag historisch. Vorabfotos besitzen einzelne Perspektiven, Retake-Fristen und unveränderte Originalhistorie. Beschädigungsneustarts erzeugen neue Durchläufe, neue Prechecks, setzen Optionen neu auf und historisieren Wallet-Storno/Neureservierung. Vorgeplante Aufgaben sowie eine verbindliche Auftragszusammenfassung vor Annahme sind integriert.

## Deployment nach Update

Auf dem ALL-INKL-Webspace im Projektverzeichnis:

```bash
git pull origin main
```

Danach als Administrator unter `/admin/system/update` alle ausstehenden SQL-Migrationen ausführen.

Der Cronjob soll regelmäßig, empfohlen alle 5 Minuten, `bin/cron.php` mit PHP CLI ausführen. Er verarbeitet Nachweisfenster, Erinnerungen, Nachfristen, Verstöße, Privatangebotsfristen, Revisionen, Beschädigungsnachforderungen und Versandfristen idempotent.

Private Nachweise und digitale Medien liegen außerhalb des öffentlichen Webroots und werden nur über autorisierte PHP-Endpunkte ausgeliefert.
