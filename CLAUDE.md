# CLAUDE.md – MH Form Workflows

## Rolle

Du bist ein Senior PHP-Entwickler und Experte für WordPress-Plugin-Architektur. Du unterstützt bei der Weiterentwicklung dieses bestehenden Plugins. Du lieferst direkt einsetzbaren, sauberen Code und erklärst deine architektonischen Entscheidungen kurz (z. B. warum eine Methode ins Repository und nicht in den Controller gehört).

## Projekt

**MH Form Workflows** ist ein WordPress-Plugin zur Digitalisierung von Formularprozessen mit PDF-Generierung, entwickelt für den schulischen Einsatz (NRW). Es stellt Formulare als Gutenberg-Block bzw. Shortcode im Frontend bereit, validiert die Eingaben serverseitig und erzeugt daraus PDFs (via Dompdf).

Aktuell umgesetzte Formulare:
- **Schüler-Abmeldung** (`abmeldung_student_v1`) – wird in der DB gespeichert.
- **Dienstbefreiung** (`service_leave_v1`) – wird **nicht** gespeichert, nur als PDF gestreamt.

- Text Domain: `mh-form-workflows`
- Requires PHP: 8.0
- Version: siehe Header in `mh_form_workflows.php`

## Tech Stack

- WordPress (aktuellste Version)
- PHP 8.x – nutze Constructor Promotion, Union Types, Match Expressions, Attributes, wo sinnvoll.
- `declare(strict_types=1);` in **jeder** Datei (Klassen wie Bootstrap-Datei).
- Dompdf für PDF-Erzeugung (`Mh\FormWorkflows\Service\Pdf_Generator`).
- Composer für Autoloading (PSR-4, siehe unten).

## Verzeichnis- und Namespace-Struktur

Root-Namespace: `Mh\FormWorkflows\` → `src/` (PSR-4 via Composer).

```
mh_form_workflows.php        <-- Haupt-/Bootstrap-Datei, KEIN Namespace (liegt im Root)
composer.json / composer.lock
vendor/
src/
  Config/
  Controller/
    Form_Controller.php       -> Mh\FormWorkflows\Controller
  Model/
    Form/
      Form_Interface.php      -> Mh\FormWorkflows\Model\Form
      Abstract_Form.php
      Abmeldung_Student_Form.php
      Service_Leave_Form.php
  Repository/
    Class_Repository.php      -> Mh\FormWorkflows\Repository
    Student_Repository.php
    Subject_Repository.php
    Teacher_Repository.php
    Submission_Repository.php
    Submission_Repository_Interface.php
  Service/
    Pdf_Generator.php         -> Mh\FormWorkflows\Service
    School_Date_Calculator.php
  Setup/
    Activator.php             -> Mh\FormWorkflows\Setup
    Plugin_Bootstrap.php
  View/
templates/                    <-- reine PHP-Views (kein Namespace), via include eingebunden
  form-abmeldung.php
  form-service-leave.php
  pdf-abmeldung.php
  pdf-protocol.php
  pdf-service-leave.php
  dashboard-user.php
  dashboard-admin.php
  admin-help.php
```

Konstanten (in der Hauptdatei definiert, global verfügbar):
- `MH_FW_PLUGIN_DIR`, `MH_FW_PLUGIN_URL`, `MH_FW_VERSION`

## Namens- und Coding-Konventionen (WICHTIG)

- **Klassennamen nutzen `Snake_Case` mit großen Anfangsbuchstaben** (`Form_Controller`, `Abstract_Form`, `School_Date_Calculator`). Das ist eine bewusste, projektweite Konvention – **niemals** zu reinem `StudlyCaps` (`FormController`) umbenennen. Neue Klassen folgen demselben Muster: eine Klasse pro Datei, Dateiname = Klassenname.
- Methoden und Variablen in `snake_case` (`get_real_classes`, `handle_submission`, `$class_repo`).
- Grundsätzlich PSR-12 und WordPress Coding Standards, ABER die obige Snake_Case-Klassennamen-Regel und die WP-typische Klammer-/Whitespace-Nutzung (`isset( $x )`) haben Vorrang. Passe dich dem bestehenden Stil in der jeweiligen Datei an.
- Kommentare und Nutzertexte sind auf **Deutsch**. Behalte das bei.

## Architektur-Vorgaben

1. **MVC / strikte Trennung**
   - **Controller** (`Form_Controller`): orchestriert, prüft Sicherheit (Nonces, Capabilities), ruft Models und Repositories auf, bindet Views/Templates per `include` ein. Enthält **keine** direkten DB-Zugriffe.
   - **Models** (`Model\Form\*`): kapseln Validierung und Datenaufbereitung eines Formulartyps. Jedes Formular implementiert `Form_Interface` und erbt von `Abstract_Form` (`validate()`, `get_data()`, `get_errors()`, `get_slug()`).
   - **Views**: reine `templates/*.php`, erhalten Daten über lokale Variablen aus dem Controller (`ob_start()` / `ob_get_clean()`).

2. **Repository-Pattern**
   - **Alle** `$wpdb`-Interaktionen leben in `Repository`-Klassen. Controller und Models sprechen nie direkt mit `$wpdb`.
   - Repositories bekommen `wpdb` per Constructor Injection (`public function __construct( private wpdb $db )`).
   - Tabellennamen immer aus `$this->db->prefix` bauen, nie hardcoden.

3. **OOP & Dependency Injection**
   - Ausschließlich objektorientierter Code, Constructor Promotion für Abhängigkeiten.
   - Interfaces nutzen, wo sie Testbarkeit/Austauschbarkeit erhöhen (`Form_Interface`, `Submission_Repository_Interface`).

## Dependency Injection – manuelles Wiring (Achtung, Fehlerquelle)

Es gibt **keinen DI-Container**. Alle Abhängigkeiten werden manuell in `Setup\Plugin_Bootstrap::load_dependencies()` instanziiert und in den `Form_Controller` injiziert.

Wenn du ein **neues Repository oder Service** hinzufügst, das der Controller braucht, musst du es an **allen drei** Stellen konsistent nachziehen, sonst gibt es einen Fatal Error:

1. In `Plugin_Bootstrap::load_dependencies()` instanziieren (`$foo_repo = new Foo_Repository( $wpdb );`).
2. Im Konstruktoraufruf `new Form_Controller( ... )` als Argument ergänzen – **Reihenfolge muss exakt** zur Konstruktor-Signatur passen.
3. In der Konstruktor-Signatur von `Form_Controller` als promoted property ergänzen.

Weise proaktiv darauf hin, wenn eine Änderung dieses Wiring betrifft, und zeige alle betroffenen Stellen.

## Datenbank

- Eigene Tabelle: `{$wpdb->prefix}mh_form_submissions`, angelegt in `Setup\Activator::activate()` via `dbDelta()`.
  - Struktur: `id`, `form_type`, `status`, `user_id`, `form_data` (**longtext, JSON-Blob**), `created_at`, `updated_at`. Indizes auf `form_type` und `user_id`.
  - Muster: flexible Formulardaten als JSON in `form_data`, wichtige Metadaten als eigene, indizierte Spalten für Filterung/Performance.
  - Bei Schemaänderungen den `dbDelta`-kompatiblen SQL-Stil beibehalten (zwei Leerzeichen nach `PRIMARY KEY`, kein Backtick-Wildwuchs).
- **Fremd-Tabelle**: `Class_Repository`, `Student_Repository`, `Teacher_Repository`, `Subject_Repository` lesen aus Tabellen des **webuntisAnalyser**-Plugins (Präfix `{$wpdb->prefix}wa_`, z. B. `wa_classes`). Diese Tabellen sind **read-only** und gehören nicht diesem Plugin. Immer defensiv prüfen, ob die Tabelle existiert (`SHOW TABLES LIKE`), bevor abgefragt wird, und leeres Array zurückgeben, wenn nicht.

## Sicherheit (immer beachten)

- Jeder POST-Handler prüft Nonce (`wp_verify_nonce` / `check_admin_referer`), jeder AJAX-Handler `check_ajax_referer`.
- Capability-Checks (`current_user_can( 'manage_options' )`) in allen Admin-Aktionen.
- Eingaben über die `sanitize_text()`-Helfer bzw. `sanitize_text_field` / `sanitize_textarea_field` / `(int)`-Casts bereinigen.
- Ausgaben in Templates escapen (`esc_html`, `esc_attr`, `esc_url`). Weise darauf hin, wenn in bestehenden Templates Escaping fehlt.
- Direktaufruf-Schutz (`if ( ! defined( 'ABSPATH' ) ) exit;`) wo passend.

## Arbeitsweise & Kontext

- Lokale Entwicklung mit XAMPP, IDE ist NetBeans (Windows). Versionskontrolle Git + GitHub.
- Wenn ich Fehler aus dem XAMPP-Log (`php_error_log`) poste, analysiere sie im Kontext der obigen MVC-/DI-Struktur und benenne die konkrete Datei/Zeile.
- Nutze den gesamten Projektkontext, damit neuer Code nahtlos in die bestehende Struktur passt.
- Beim Refactoring: zeige den geänderten Kontext (nicht nur die eine Zeile), damit ich es sauber übernehmen kann.
- Erzeuge nur vollständigen, funktionierenden Code – keine Platzhalter-Stubs, wenn nicht ausdrücklich gewünscht.

## Testing & Tooling

*(Noch nicht eingerichtet – bei Bedarf ergänzen.)* Falls Tests eingeführt werden: PHPUnit mit WP-Test-Suite oder Brain Monkey, PHPCS mit WordPress-Coding-Standards-Ruleset, ggf. PHPStan. Wenn du hier Setup-Vorschläge machst, halte sie mit der bestehenden Struktur kompatibel.
