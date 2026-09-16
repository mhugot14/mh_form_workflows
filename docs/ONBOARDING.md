# Onboarding – Entwicklungsumgebung für MH Form Workflows

Diese Anleitung führt vom leeren Windows-Rechner bis zur lauffähigen Entwicklungsumgebung
mit VS Code. Sie setzt den gleichen Pfad wie beim bestehenden Setup voraus
(`C:\xampp\htdocs\WordpressPluginDev\`), damit Doku und Debug-Konfiguration überall passen.

## 0. Vorab (Repo-Owner)

1. Auf GitHub beide Repos für das neue Teammitglied freigeben
   (Settings → Collaborators):
   - `mhugot14/mh_form_workflows`
   - `mhugot14/webuntisAnalyser` (Pflicht-Abhängigkeit, liefert die `wa_`-Tabellen)
2. **Keine Datenbank-Kopie weitergeben.** Die Entwicklungs-DB darf ausschließlich synthetische
   Daten enthalten (siehe Datenschutz-Abschnitt in `CLAUDE.md`). Jede*r baut sich die DB aus den
   Testdaten des webuntisAnalyser selbst auf.

## 1. Software installieren

| Tool | Hinweis |
|---|---|
| **XAMPP** (PHP 8.2.x) | https://www.apachefriends.org – Apache + MySQL genügen. In `xampp\php\php.ini` prüfen, dass `extension=gd` und `extension=mbstring` aktiv sind (Dompdf braucht beide). |
| **Composer** | https://getcomposer.org/Composer-Setup.exe – beim Setup die `php.exe` aus XAMPP auswählen. |
| **Git** | https://git-scm.com – inkl. Git Bash. |
| **VS Code** | https://code.visualstudio.com |

Kontrolle in einem neuen Terminal:

```
php -v        → PHP 8.2.x
composer -V
git --version
```

## 2. WordPress aufsetzen

1. WordPress herunterladen und nach `C:\xampp\htdocs\WordpressPluginDev\` entpacken.
2. In phpMyAdmin (`http://localhost/phpmyadmin`) eine leere Datenbank `wordpress_dev`
   (Kollation `utf8mb4_unicode_ci`) anlegen.
3. `http://localhost/WordpressPluginDev/` aufrufen und den WordPress-Installer durchlaufen.
4. In `wp-config.php` vor `/* That's all, stop editing! */` ergänzen:

   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

   PHP-Fehler landen damit in `wp-content/debug.log`.

## 3. Repos klonen

Im Ordner `wp-content\plugins`:

```
git clone https://github.com/mhugot14/webuntisAnalyser.git webuntisAnalyser
git clone https://github.com/mhugot14/mh_form_workflows.git mh_form_workflows
```

Die Ordnernamen müssen exakt so heißen – WordPress leitet den Plugin-Slug daraus ab.

Danach in **beiden** Plugin-Ordnern die Abhängigkeiten installieren (`vendor/` ist nicht im
Repo, siehe `.gitignore`):

```
composer install
```

Ohne diesen Schritt bricht das Aktivieren mit `Class ... not found` ab – der PSR-4-Autoloader
und Dompdf kommen aus `vendor/`.

Arbeitsbranch setzen:

```
cd mh_form_workflows
git checkout develop
```

## 4. Plugins aktivieren und Testdaten laden

1. WP-Backend → Plugins: **zuerst** WebUntis Analyser, **dann** MH Form Workflows aktivieren.
   Der Activator legt beim Aktivieren die Tabelle `mh_form_submissions` per `dbDelta()` an.
2. Im WebUntis Analyser die Dateien aus dessen Ordner `testdaten/` importieren. Das füllt
   `wa_classes`, `wa_students`, `wa_teachers` und `wa_subjects` mit erfundenen Daten.
3. Im WebUntis Analyser unter „Lehrer-Zuordnung“ mindestens ein Lehrerkürzel mit dem eigenen
   WP-Konto und einer E-Mail-Adresse verknüpfen – sonst kann die digitale Noteneinsammlung
   nicht starten.
4. WordPress-Seiten anlegen, Shortcodes einfügen und unter **MH Formulare → Einstellungen**
   verknüpfen. Die vollständige Shortcode-Liste mit Erklärung steht im Backend unter
   **MH Formulare → Übersicht & Hilfe**.
5. Funktionstest ohne Browser und ohne Mailversand:

   ```
   php tools/verify_noteneinsammlung.php
   ```

   Das Skript legt nur erfundene Datensätze (Präfix `__T…__`) an und entfernt sie am Ende.

## 5. VS Code einrichten

Als Workspace den Ordner `mh_form_workflows` öffnen (nicht ganz `htdocs` – sonst indexiert
Intelephense den kompletten WordPress-Core, was langsam ist und die WordPress-Stubs
überflüssig macht).

Beim ersten Öffnen schlägt VS Code die im Repo hinterlegten Erweiterungen vor
(`.vscode/extensions.json`) – alle installieren:

- **PHP Intelephense** – Autocomplete, Navigation, kennt WordPress-Funktionen über Stubs.
- **PHP Debug** – Breakpoints mit Xdebug.
- **GitLens** – Blame/History (optional).

Die Editor-Einstellungen (Tabs, UTF-8, LF, PHP 8.2, WordPress-Stubs) sind in
`.vscode/settings.json` vorkonfiguriert.

### Xdebug

XAMPP bringt Xdebug mit; in `xampp\php\php.ini` am Ende ergänzen bzw. prüfen:

```ini
[xdebug]
zend_extension = xdebug
xdebug.mode = debug
xdebug.start_with_request = yes
xdebug.client_port = 9003
```

Apache danach neu starten. In VS Code stehen zwei Debug-Konfigurationen bereit
(`.vscode/launch.json`):

- **Xdebug: Auf Browser warten** – Breakpoint setzen, F5, Seite im Browser aufrufen.
- **Xdebug: verify_noteneinsammlung.php** – startet das CLI-Testskript im Debugger.

## 6. Git-Workflow

- `master` = Release-Stand, `develop` = Integrationsbranch. Nie direkt auf `master` committen.
- Pro Aufgabe ein Feature-Branch von `develop`:

  ```
  git checkout develop
  git pull origin develop
  git checkout -b feature/kurzer-name
  ```

  Danach Pull Request nach `develop` auf GitHub, damit die Änderung vor dem Zusammenführen
  gesehen wird.
- Commit-Messages auf Deutsch, wie im bisherigen Verlauf.
- `vendor/` und `nbproject/` bleiben ignoriert; `.vscode/` ist bewusst eingecheckt, damit alle
  dieselben Einstellungen nutzen.

## 7. Pflichtlektüre

`CLAUDE.md` im Repo-Root beschreibt Architektur (MVC, Repository-Pattern, manuelles
DI-Wiring in `Setup\Plugin_Bootstrap`), die `Snake_Case`-Klassenkonvention, Sicherheitsregeln
und die Datenschutzvorgabe. Sie ist die beste Einführung in den Code – und gilt auch, wenn mit
Claude Code entwickelt wird.
