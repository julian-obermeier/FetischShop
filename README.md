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

Stand 0.7.0: Zusätzlich zu 0.6.0 wurde der komplette Verkäuferinnen-Bereich als eigene responsive Anwendung neu gestaltet: Desktop-Seitenleiste, mobile Bottom-Navigation, handlungsorientiertes Dashboard, verbesserte Auftragsliste, klare Fortschrittsanzeige im Auftrag, Schnellnavigation, verständliche Statusbezeichnungen, überarbeitete Wallet-/Auszahlungsansicht, Benachrichtigungs-Postfach, Profil sowie verbesserte Login- und Registrierungsseiten. Technische Rohdaten und Statuscodes werden im Verkäuferinnenbereich nicht mehr angezeigt. Neue Zusatzaufgaben, Fotoanforderungen, Schadensentscheidungen, Versand-/Wareneingangsereignisse, digitale Prüfungen, Abschlussentscheidungen und Auszahlungsstatus können Verkäuferinnen nun aktiv per In-App-Benachrichtigung und E-Mail erreichen. Für digitale Inhalte gibt es einen nachvollziehbaren Rechte-Lifecycle inklusive Verkäuferinnen-Zustimmung, Freigabe/Nichtfreigabe und protokollierten Admin-Downloads.

## Deployment nach Update

Auf dem ALL-INKL-Webspace im Projektverzeichnis:

```bash
git pull origin main
```

Danach als Administrator unter `/admin/system/update` alle ausstehenden SQL-Migrationen ausführen.

### Cronjob bei ALL-INKL

Für ALL-INKL ist kein PHP-CLI-Aufruf mehr erforderlich. Nach dem Datenbankupdate unter `/admin/system/update` zeigt der Bereich `/admin/einstellungen` eine geheime Cron-URL nach dem Muster:

```
https://deine-domain.de/cron/GEHEIMER-SCHLUESSEL
```

Diese URL beim Hosting als URL-Cronjob hinterlegen und empfohlen alle 5 Minuten per GET aufrufen. Die URL verarbeitet Nachweisfenster, Erinnerungen, Nachfristen, Verstöße, Privatangebotsfristen, Revisionen, Beschädigungsnachforderungen und Versandfristen idempotent. Über „Neue geheime Cron-URL erzeugen“ kann der Schlüssel jederzeit ersetzt werden; die alte URL wird dann sofort ungültig. `bin/cron.php` bleibt lediglich als optionale technische Fallback-Möglichkeit im Repository.

Private Nachweise und digitale Medien liegen außerhalb des öffentlichen Webroots und werden nur über autorisierte PHP-Endpunkte ausgeliefert.


### Migrationen ab 0.5.0

- `014_component_extensions_payout_allocations.sql` – komponentenbezogene Verstöße/Verlängerungen und Auszahlung-zu-Auftrag-Zuordnung
- `015_spontaneous_component_scope.sql` – komponentenbezogene spontane Fotoanforderungen
- `016_cron_url_token.sql` – geheimer Schlüssel für den ALL-INKL-URL-Cronjob
- `017_digital_rights_lifecycle.sql` – Rechte-Lifecycle und Download-/Rechtehistorie für digitale Inhalte

Für Auftragsbestätigungen muss `base_url` in `config/runtime.php` auf die produktive HTTPS-URL zeigen. Der Adminbereich zeigt unter `/admin/system/status` den Cron-Heartbeat, PHP-/DB-Status, Schreibrechte und ausstehende Migrationen.
