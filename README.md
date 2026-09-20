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

Stand 0.8.0: Zusätzlich zu 0.7.0 besitzen Verkäuferinnen nun einen eigenen Bereich „Fristen & Kalender“ mit Priorisierung in überfällig, jetzt fällig, heute, morgen und später. Auftragslisten können durchsucht und nach aktiv, Auszahlung und Archiv gefiltert werden. Benachrichtigungen werden nicht mehr beim bloßen Öffnen pauschal gelesen, sondern können nach ungelesen gefiltert sowie einzeln oder gesammelt als gelesen markiert werden. Eingeloggte Verkäuferinnen bleiben beim Öffnen der Angebotsseiten im eigenen Konto-Layout; Angebotsdetails verwenden verständliche Erfüllungsbegriffe und zeigen vor der Annahme, ob ein Angebot wegen einer bereits belegten Kategorie, einer Frist oder fehlender E-Mail-Bestätigung aktuell nicht annehmbar ist. Die Abschlussansicht zeigt freigegebenen, bereits ausgezahlten und noch offenen Betrag sowie Archivstatus; abgeschlossene Chats werden als Nur-Lesen dargestellt. Weitere In-App-/E-Mail-Ereignisse wurden für Nachweisbeanstandungen, Auftragsstart, Verstöße, Zusatztage, Wert-/Optionsänderungen und Chatnachrichten ergänzt. Admin-Kalender, Archiv, globale Suche und Entscheidungsansicht wurden erweitert. Neu ist außerdem ein zentrales, filterbares Admin-Protokoll für System-, Auftrags- und digitale Rechteereignisse ohne rohe JSON-Anzeige. Der Systemstatus prüft zusätzlich HTTPS-Basis-URL, Cron-Schlüssel, Installationssperre und die Trennung der privaten Ablage vom Webroot.

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
