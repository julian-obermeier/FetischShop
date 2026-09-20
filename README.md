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

Stand 0.10.0: Zusätzlich zu 0.9.0 wurde ein Produktions-Hotfix für den Verkäuferinnen-Fristenservice eingespielt: Versandfristen verwenden nicht mehr die nicht vorhandene Spalte shipping_steps.created_at, sondern den Startzeitpunkt des Versandworkflows beziehungsweise die Frist. Das Verkäuferinnen-Layout wurde gegen horizontales Überlaufen der Angebotsfilter gehärtet, sodass die linke Navigation auf /angebote nicht mehr aus dem sichtbaren Bereich gedrückt wird. CSS und JavaScript werden nach Deployments per Cache-Busting und Network-first-Service-Worker sofort aktualisiert. Bildnachweise können nun direkt über eine In-App-Kamera mit Vorschau aufgenommen werden; die Aufnahmemethode wird als verständliche Metainformation dokumentiert. Die Verkäuferinnen-Navigation zeigt Zähler für dringende Fristen und ungelesene Benachrichtigungen, mobil zusätzlich im Topbar-Indikator. Die Admin-Auftragsansicht besitzt eine Schnellnavigation und übersetzt weitere technische Statuscodes in verständliches Deutsch. Die GitHub-CI startet nun eine echte MariaDB, führt alle Migrationen aus und testet Scheduler, Fristenservice und Navigationsabfragen gegen das tatsächliche Schema.

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


### PWA und Offline-Verhalten

Die installierbare Web-App startet unter `/konto` und bietet Schnellzugriffe für Aufträge, Fristen, Wallet und Angebote. Dynamische Konto- und Auftragsdaten werden bewusst nicht offline gespeichert. Bei fehlender Verbindung erscheint eine neutrale Offline-Seite; private Medien, Cron-Endpunkte und Adminseiten werden vom Service Worker ausgeschlossen.

### Betriebsdiagnose

Unter `/admin/system/status` können Administratoren zusätzlich:
- den Scheduler einmal manuell ausführen,
- eine echte Test-E-Mail an eine frei eingegebene Adresse senden,
- Cron-Heartbeat, HTTPS-Basis-URL, Cron-Schlüssel, Installationssperre, Migrationen und private Ablage prüfen.

Die öffentlichen Plattformregeln befinden sich unter `/regeln`. Die rechtlichen Texte unter Impressum, Datenschutz und Bedingungen bleiben bewusst als prüfpflichtige Entwürfe gekennzeichnet und müssen vor Produktivbetrieb anhand des tatsächlichen Betreibers und der eingesetzten Dienstleister rechtlich geprüft werden.
