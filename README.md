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

Stand 0.9.0: Zusätzlich zu 0.8.0 wurden die öffentlichen Plattformtexte erweitert. Unter /regeln gibt es nun eine eigene verständliche Regeln-Seite; „So funktioniert es“ und die FAQ decken Registrierung, Annahme, Vorabkontrolle, Fristen, Beschädigungen, Versand, digitale Revisionen, Rechte-Status, Wallet und Archivierung vollständig ab. Die PWA startet für Verkäuferinnen direkt im Konto-Bereich, bietet Schnellzugriffe auf Aufträge, Fristen, Wallet und Angebote und verwendet einen neutralen Offline-Fallback; private Aufträge, Medien, Adminseiten und Cron-Endpunkte werden bewusst nicht offline gecacht. Der Admin-Systemstatus besitzt nun zusätzlich einen manuellen Cron-Test und einen echten Test-E-Mail-Versand, um Deployments auf Shared Hosting unmittelbar prüfen zu können.

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
