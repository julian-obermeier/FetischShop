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

Stand 0.5.0: Zusätzlich zu 0.4.0 sind komponentenbezogene Verstöße und Verlängerungen für Kombi-Aufträge umgesetzt. Spontane Fotoanforderungen, manuelle Zusatzaufgaben und Zusatztage werden einem konkreten Bestandteil zugeordnet. Die Wallet-Zustandsmaschine läuft nun über reserviert → in Prüfung → verfügbar/abgelehnt. Auszahlungen werden auf konkrete freigegebene Aufträge verteilt; vollständig ausgezahlte Aufträge werden automatisch archiviert und ihre Chats schreibgeschützt. Auftragsbestätigungen werden nach Annahme per E-Mail versandt, ohne die Auftragstransaktion bei Mailfehlern zurückzurollen. Physische und digitale Bestandteile durchlaufen ihren Abschlussstatus getrennt und werden erst gemeinsam zur Abschlussprüfung freigegeben. Hinzu kommen Session-/Cookie-Härtung, Security-Header, persistenter Installer-Lock, restriktive Konfigurationsrechte, Admin-Passwort-Rehashing sowie ein Admin-Systemstatus mit Cron-Heartbeat, Migrationen und Laufzeitchecks.

## Deployment nach Update

Auf dem ALL-INKL-Webspace im Projektverzeichnis:

```bash
git pull origin main
```

Danach als Administrator unter `/admin/system/update` alle ausstehenden SQL-Migrationen ausführen.

Der Cronjob soll regelmäßig, empfohlen alle 5 Minuten, `bin/cron.php` mit PHP CLI ausführen. Er verarbeitet Nachweisfenster, Erinnerungen, Nachfristen, Verstöße, Privatangebotsfristen, Revisionen, Beschädigungsnachforderungen und Versandfristen idempotent.

Private Nachweise und digitale Medien liegen außerhalb des öffentlichen Webroots und werden nur über autorisierte PHP-Endpunkte ausgeliefert.


### Migrationen ab 0.5.0

- `014_component_extensions_payout_allocations.sql` – komponentenbezogene Verstöße/Verlängerungen und Auszahlung-zu-Auftrag-Zuordnung
- `015_spontaneous_component_scope.sql` – komponentenbezogene spontane Fotoanforderungen

Für Auftragsbestätigungen muss `base_url` in `config/runtime.php` auf die produktive HTTPS-URL zeigen. Der Adminbereich zeigt unter `/admin/system/status` den Cron-Heartbeat, PHP-/DB-Status, Schreibrechte und ausstehende Migrationen.
