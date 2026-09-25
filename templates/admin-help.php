<?php
/**
 * Übersichts- und Hilfeseite im Backend (Menüpunkt "MH Formulare").
 *
 * Reine View: bekommt keine Daten vom Controller, nur die globalen Konstanten.
 * Alle hier dokumentierten Shortcodes werden in Setup\Plugin_Bootstrap registriert —
 * bei neuen Shortcodes bitte diese Seite mitpflegen.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mh_settings_url = admin_url( 'admin.php?page=mh-form-workflows-settings' );
$mh_list_url     = admin_url( 'admin.php?page=mh-form-admin-list' );
$mh_version      = defined( 'MH_FW_VERSION' ) ? MH_FW_VERSION : '';
?>

<style>
    .mh-help-wrapper {
        max-width: 1100px;
        margin: 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    /* Header Bereich */
    .mh-help-header {
        background: #003E7E; /* LEBK Blau */
        color: #fff;
        padding: 40px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 0;
        position: relative;
    }

    .mh-help-header h1 {
        color: #fff !important;
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 700;
    }

    .mh-help-header p {
        font-size: 16px;
        opacity: 0.9;
        margin: 0;
    }

    .mh-help-version {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255,255,255,0.15);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    /* Inhaltsverzeichnis */
    .mh-help-toc {
        background: #fff;
        border: 1px solid #ddd;
        border-top: none;
        padding: 15px 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px 20px;
        font-size: 13px;
    }

    .mh-help-toc a {
        text-decoration: none;
    }

    /* Schritte / Grid */
    .mh-help-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1px;
        background: #ddd; /* Trennlinien-Farbe */
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 8px 8px;
        overflow: hidden;
    }

    .mh-step-card {
        background: #fff;
        padding: 30px;
        text-align: center;
    }

    .mh-step-card .dashicons {
        font-size: 40px;
        width: 40px;
        height: 40px;
        color: #003E7E;
        margin-bottom: 15px;
    }

    .mh-step-card h3 {
        font-size: 18px;
        margin: 0 0 15px 0;
        color: #23282d;
    }

    .mh-step-card p {
        color: #646970;
        line-height: 1.5;
    }

    /* Info Boxen / Cards */
    .mh-info-section {
        margin-top: 30px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }

    .mh-info-section h2 {
        margin-top: 0;
        border-bottom: 2px solid #f0f0f1;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }

    .mh-info-section h3 {
        margin: 25px 0 10px 0;
        font-size: 15px;
        color: #23282d;
    }

    .mh-info-section p.mh-lead {
        color: #646970;
        margin-top: -10px;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .mh-info-section p,
    .mh-info-section li {
        line-height: 1.6;
    }

    /* Modul-Kacheln */
    .mh-module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 15px;
    }

    .mh-module-card {
        border: 1px solid #e0e0e0;
        border-left: 4px solid #003E7E;
        border-radius: 4px;
        padding: 15px 18px;
        background: #fafafa;
    }

    .mh-module-card h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
    }

    .mh-module-card p {
        margin: 0;
        color: #646970;
        font-size: 13px;
    }

    /* Tabelle */
    .mh-shortcode-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .mh-shortcode-table th {
        text-align: left;
        background: #f8f9fa;
        padding: 12px;
        border-bottom: 2px solid #f0f0f1;
    }

    .mh-shortcode-table td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f1;
        vertical-align: top;
    }

    .mh-shortcode-table code {
        background: #f0f0f1;
        padding: 3px 8px;
        border-radius: 4px;
        color: #d63638;
        font-weight: 600;
        white-space: nowrap;
    }

    .mh-shortcode-table code.mh-code-soft,
    .mh-info-section code.mh-code-soft {
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 4px;
        color: #1d2327;
        font-weight: normal;
        white-space: nowrap;
    }

    /* Zugriffs-Badges */
    .mh-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .mh-badge-public { background: #e7f5e9; color: #1b5e20; }
    .mh-badge-login  { background: #e5f0fa; color: #0a4b78; }
    .mh-badge-admin  { background: #fbe9e7; color: #8a1c0f; }

    /* Prozess-Schritte (nummerierte Liste) */
    .mh-process {
        counter-reset: mh-step;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .mh-process li {
        position: relative;
        padding-left: 42px;
        margin-bottom: 14px;
    }

    .mh-process li::before {
        counter-increment: mh-step;
        content: counter(mh-step);
        position: absolute;
        left: 0;
        top: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #003E7E;
        color: #fff;
        font-weight: 700;
        font-size: 13px;
        text-align: center;
        line-height: 28px;
    }

    /* Hinweis-Liste */
    .mh-notice-list {
        list-style: none;
        padding: 0;
    }

    .mh-notice-list li {
        margin-bottom: 15px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .mh-notice-list .dashicons {
        margin-top: 2px;
    }

    .mh-inline-note {
        background: #fff8e5;
        border-left: 4px solid #e5a912;
        padding: 10px 15px;
        margin: 15px 0;
        font-size: 13px;
    }
</style>

<div class="wrap">
    <div class="mh-help-wrapper">

        <!-- HEADER -->
        <div class="mh-help-header">
            <?php if ( '' !== $mh_version ) : ?>
                <span class="mh-help-version">Version <?= esc_html( $mh_version ) ?></span>
            <?php endif; ?>
            <h1>Willkommen bei MH Form Workflows</h1>
            <p>Digitale Formularprozesse für das LEBK Münster: Schüler-Abmeldung, Dienstbefreiung, digitale Noteneinsammlung und Absentismus-Verfahren – mit PDF-Erzeugung.</p>
        </div>

        <!-- INHALTSVERZEICHNIS -->
        <div class="mh-help-toc">
            <strong>Inhalt:</strong>
            <a href="#mh-schnellstart">Schnellstart</a>
            <a href="#mh-module">Module</a>
            <a href="#mh-sc-formulare">Formulare &amp; Dashboard</a>
            <a href="#mh-abmeldung">Schüler-Abmeldung</a>
            <a href="#mh-noten">Noteneinsammlung</a>
            <a href="#mh-absentismus">Absentismus</a>
            <a href="#mh-menu">Backend-Menü</a>
            <a href="#mh-einstellungen">Einstellungen</a>
            <a href="#mh-hinweise">Hinweise</a>
        </div>

        <!-- SCHRITTE -->
        <div class="mh-help-steps" id="mh-schnellstart">
            <div class="mh-step-card">
                <span class="dashicons dashicons-database-import"></span>
                <h3>1. Stammdaten prüfen</h3>
                <p>Klassen, Schüler*innen, Lehrkräfte und Fächer kommen aus dem Plugin <em>WebUntis Analyser</em>. Dort muss ein aktueller Import vorliegen. Zusätzlich muss dort unter <em>Klassen</em> jeder Klasse ein Bildungsgang zugeordnet sein &ndash; nur dann werden im Abgangsformular die Fächer der Stundentafel vorbelegt.</p>
            </div>
            <div class="mh-step-card">
                <span class="dashicons dashicons-admin-page"></span>
                <h3>2. Seiten anlegen</h3>
                <p>Erstellen Sie in WordPress je eine Seite pro Funktion (z.&nbsp;B. „Abmeldung“, „Meine Anträge“, „Noteneingabe“, „Absentismus-Fälle“).</p>
            </div>
            <div class="mh-step-card">
                <span class="dashicons dashicons-shortcode"></span>
                <h3>3. Shortcodes einbinden</h3>
                <p>Kopieren Sie den passenden Shortcode aus den Tabellen unten in den Inhalt der jeweiligen Seite (ein Shortcode pro Seite).</p>
            </div>
            <div class="mh-step-card">
                <span class="dashicons dashicons-admin-settings"></span>
                <h3>4. Seiten verknüpfen</h3>
                <p>Wählen Sie in den <a href="<?= esc_url( $mh_settings_url ) ?>">Einstellungen</a> die erstellten Seiten aus – sonst funktionieren Links in Mails, Dashboards und Menüs nicht.</p>
            </div>
        </div>

        <!-- MODULE -->
        <div class="mh-info-section" id="mh-module">
            <h2>Die vier Module im Überblick</h2>
            <div class="mh-module-grid">
                <div class="mh-module-card">
                    <h4>📄 Formulare mit PDF</h4>
                    <p>Schüler-Abmeldung (wird gespeichert, später bearbeitbar) und Dienstbefreiung (nur PDF, keine Speicherung). Serverseitige Validierung, PDF via Dompdf.</p>
                </div>
                <div class="mh-module-card">
                    <h4>🗂 Persönliches Dashboard</h4>
                    <p>Jede Lehrkraft sieht ihre eigenen Abmeldungen nach Schuljahr gruppiert, kann PDFs erneut herunterladen, Einträge bearbeiten oder löschen.</p>
                </div>
                <div class="mh-module-card">
                    <h4>📝 Digitale Noteneinsammlung</h4>
                    <p>Aus einer Abmeldung heraus werden die Fachlehrkräfte per Mail gebeten, ihre Note einzutragen. Erinnerungen und Eskalation laufen automatisch.</p>
                </div>
                <div class="mh-module-card">
                    <h4>⚖️ Absentismus-Verfahren</h4>
                    <p>Mehrstufiger Eskalationsprozess bei unentschuldigten Fehlzeiten als „Fall“ mit Timeline, Notizen, Kontakten und PDF je Schritt.</p>
                </div>
            </div>
        </div>

        <!-- SHORTCODES FORMULARE -->
        <div class="mh-info-section" id="mh-sc-formulare">
            <h2>Shortcodes: Formulare &amp; Dashboard</h2>
            <p class="mh-lead">
                Legende Zugriff:
                <span class="mh-badge mh-badge-public">öffentlich</span> auch ohne Anmeldung nutzbar &nbsp;·&nbsp;
                <span class="mh-badge mh-badge-login">angemeldet</span> nur für eingeloggte Nutzer &nbsp;·&nbsp;
                <span class="mh-badge mh-badge-admin">Administrator</span> erweiterte Sicht für <code class="mh-code-soft">manage_options</code>.
            </p>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="22%">Funktion</th>
                        <th width="33%">Shortcode</th>
                        <th width="12%">Zugriff</th>
                        <th width="33%">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Schüler-Abmeldung</strong></td>
                        <td><code>[mh_form_workflow type="abmeldung_student_v1"]</code></td>
                        <td><span class="mh-badge mh-badge-public">öffentlich</span></td>
                        <td>Formular zur Ausschulung/Abmeldung inkl. optionalem Protokoll (Fehlzeiten, Fächer &amp; Noten). Wird in der Datenbank gespeichert. Details siehe <a href="#mh-abmeldung">unten</a>.</td>
                    </tr>
                    <tr>
                        <td><strong>Dienstbefreiung</strong></td>
                        <td><code>[mh_form_workflow type="service_leave_v1"]</code></td>
                        <td><span class="mh-badge mh-badge-public">öffentlich</span></td>
                        <td>Antrag auf Dienstbefreiung / Sonderurlaub. Wird <strong>nicht</strong> gespeichert – das PDF wird nach dem Absenden direkt zum Download ausgeliefert.</td>
                    </tr>
                    <tr>
                        <td><strong>Benutzer-Dashboard</strong><br><small>„Meine Anträge“</small></td>
                        <td><code>[mh_my_submissions]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span></td>
                        <td>Liste der eigenen gespeicherten Abmeldungen, gruppiert nach Schuljahr. Pro Eintrag: PDF erneut herunterladen, im Formular bearbeiten, löschen. Der Bearbeiten-Link setzt voraus, dass die Abmeldungs-Seite in den Einstellungen verknüpft ist.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Attribut <code class="mh-code-soft">type</code></h3>
            <p>
                Der Shortcode <code class="mh-code-soft">[mh_form_workflow]</code> steuert über das Attribut <code class="mh-code-soft">type</code>, welches Formular angezeigt wird.
                Fehlt das Attribut, wird die <strong>Schüler-Abmeldung</strong> (<code class="mh-code-soft">abmeldung_student_v1</code>) angezeigt.
                Ein unbekannter Wert fällt ebenfalls auf die Abmeldung zurück. Weitere Attribute gibt es nicht.
            </p>
        </div>

        <!-- SCHÜLER-ABMELDUNG -->
        <div class="mh-info-section" id="mh-abmeldung">
            <h2>Ablauf: Schüler-Abmeldung</h2>
            <p class="mh-lead">
                Das Abmeldeformular ist der Einstieg für die Ausschulung. Es bietet am Ende drei Schaltflächen, die unterschiedliche Wege auslösen:
            </p>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="28%">Schaltfläche</th>
                        <th width="72%">Was passiert</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Formular nur prüfen</strong></td>
                        <td>Serverseitige Validierung ohne Speicherung. Fehler werden am Formular markiert, alle Eingaben bleiben erhalten. Wird ein Datum automatisch korrigiert (Wochenende/Ferien), erscheint ein Hinweis.</td>
                    </tr>
                    <tr>
                        <td><strong>Prüfen &amp; PDF erstellen</strong></td>
                        <td>Validiert, speichert die Abmeldung in der Datenbank (bzw. aktualisiert sie beim Bearbeiten) und liefert das PDF zum Download. Wurde „Zeugniskonferenzprotokoll jetzt erstellen“ gewählt, wird es als weitere Seite angehängt. Dateiname: <code class="mh-code-soft">JJ-MM-TT_ID_Abmeldung_Nachname.pdf</code>.</td>
                    </tr>
                    <tr>
                        <td><strong>Noteneinsammlung digital starten</strong></td>
                        <td>Validiert, speichert die Abmeldung und legt statt des PDF-Downloads einen Noten-Fall an: Alle in der Fächertabelle eingetragenen Lehrkräfte erhalten eine E-Mail mit Link zur Noteneingabe. Der Knopf entfällt, wenn ein bestehendes Zeugniskonferenzprotokoll beigefügt wird &ndash; die Noten stehen dann bereits darin. Voraussetzungen und Ablauf siehe <a href="#mh-noten">Noteneinsammlung</a>.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Zeugniskonferenzprotokoll</h3>
            <p>
                Dem Formular liegt <strong>immer</strong> ein Zeugniskonferenzprotokoll bei. In Abschnitt 3 wird
                entschieden, woher es kommt:
            </p>
            <ul style="list-style: disc; padding-left: 20px; line-height:1.6;">
                <li>
                    <strong>Zeugniskonferenzprotokoll jetzt erstellen</strong> &ndash; der bisherige Weg. Abschnitt 4
                    wird eingeblendet, Konferenzdaten, Fehlstunden sowie Fächer und Noten werden erfasst, und das
                    Protokoll wird dem PDF als weitere Seite angehängt. Nur in diesem Fall ist die digitale
                    Noteneinsammlung möglich.
                </li>
                <li>
                    <strong>Ein bestehendes Protokoll liegt bei</strong> &ndash; gedacht für Zeugnisse, die auf dem
                    Konferenzprotokoll der gesamten Klasse beruhen, was vor allem zum Schuljahresbeginn vorkommt.
                    Noten und Zeugnisdatum stammen dann aus jenem Protokoll. Die Klassenleitung holt es aus den Akten,
                    bearbeitet es, lässt die Änderungen von der Abteilungsleitung abzeichnen und reicht es zusammen mit
                    dem Formular ein. Abschnitt 4 entfällt, dem PDF wird kein Protokoll angehängt, und die erforderliche
                    Erklärung wird auf dem PDF mit ausgedruckt.
                </li>
            </ul>

            <h3>Bearbeiten &amp; erneuter Download</h3>
            <p>
                Gespeicherte Abmeldungen lassen sich über das Benutzer-Dashboard (<code class="mh-code-soft">[mh_my_submissions]</code>) erneut öffnen. Der Link
                <code class="mh-code-soft">?mh_edit_id=…</code> lädt den Datensatz ins Formular; beim nächsten „Prüfen &amp; PDF erstellen“ wird derselbe Eintrag aktualisiert, es entsteht kein Duplikat.
                Nur der/die Ersteller*in kann eigene Einträge bearbeiten; Administratoren sehen alle Einträge unter <a href="<?= esc_url( $mh_list_url ) ?>">Alle Einsendungen</a>.
            </p>
        </div>

        <!-- NOTENEINSAMMLUNG -->
        <div class="mh-info-section" id="mh-noten">
            <h2>Digitale Noteneinsammlung</h2>
            <p class="mh-lead">
                Ersetzt den Umlauf des Abgangszeugnis-Protokolls: Die Klassenleitung startet den Prozess aus der Abmeldung, jede Fachlehrkraft trägt
                ihre Note selbst ein, das Plugin erinnert automatisch und schreibt die Noten zurück in die Abmeldung – das fertige PDF enthält dann alle Noten.
            </p>

            <h3>Shortcodes</h3>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="22%">Funktion</th>
                        <th width="22%">Shortcode</th>
                        <th width="12%">Zugriff</th>
                        <th width="44%">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Noteneingabe</strong><br><small>Fachlehrkraft</small></td>
                        <td><code>[mh_noten_eingabe]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span></td>
                        <td>Ziel der Einladungs- und Erinnerungsmails. Zeigt <strong>nur die eigene Fachzeile</strong> (keine fremden Noten, keine Abmeldegründe). Ohne Parameter in der URL wird stattdessen die Liste „Meine Noteneingaben“ angezeigt. Nicht angemeldete Nutzer werden zum WordPress-Login und danach zurück auf die Seite geleitet.</td>
                    </tr>
                    <tr>
                        <td><strong>Meine Noteneingaben</strong></td>
                        <td><code>[mh_noten_liste]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span></td>
                        <td>Alle Ausschulungen, an denen die angemeldete Lehrkraft mit einer Note beteiligt ist – filterbar nach „offen“ / „abgeschlossen“. Die Zuordnung erfolgt über das Lehrerkürzel aus dem WebUntis Analyser.</td>
                    </tr>
                    <tr>
                        <td><strong>Noteneinsammlung</strong><br><small>Klassenleitung</small></td>
                        <td><code>[mh_noten_fall]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span> <span class="mh-badge mh-badge-admin">Administrator</span></td>
                        <td>Ohne Parameter: Übersicht aller selbst gestarteten Noten-Fälle (Administratoren sehen alle). Mit Fall-ID: Fortschritt, fehlende Noten, Nachtragen einer Note durch die Klassenleitung, „Jetzt erinnern“ auslösen.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Ablauf</h3>
            <ol class="mh-process">
                <li><strong>Start:</strong> Klassenleitung füllt die Abmeldung aus, trägt im Protokollbereich Fächer und Lehrkräfte ein und klickt „Noteneinsammlung digital starten“.</li>
                <li><strong>Vorprüfung:</strong> Jedes Fach braucht eine Lehrkraft, und zu jedem Kürzel muss sich eine E-Mail-Adresse auflösen lassen (WebUntis Analyser → „Lehrer-Zuordnung“, Hauptadresse; Rückfall: E-Mail des verknüpften WordPress-Kontos). Fehlt etwas, startet der Prozess nicht und das Formular zeigt an, welche Fächer betroffen sind.</li>
                <li><strong>Einladung:</strong> Jede Fachlehrkraft erhält eine Mail mit Schülername, Klasse und Link auf die Seite mit <code class="mh-code-soft">[mh_noten_eingabe]</code>. In keiner Mail steht eine Note.</li>
                <li><strong>Eingabe:</strong> Die Fachlehrkraft meldet sich an und trägt ihre Note ein. Ein Doppelstart für dieselbe Abmeldung wird verhindert – es wird auf den bestehenden Fall weitergeleitet.</li>
                <li><strong>Erinnerung:</strong> Ein täglicher Cron-Lauf erinnert säumige Lehrkräfte im eingestellten Abstand („Erinnerung nach Tagen“). Nach der eingestellten Anzahl erfolgloser Erinnerungen wird zusätzlich einmalig die Klassenleitung informiert („Klassenlehrer informieren nach“).</li>
                <li><strong>Nachtragen:</strong> Die Klassenleitung kann fehlende Noten in der Fall-Ansicht selbst nachtragen (z.&nbsp;B. nach Rücksprache).</li>
                <li><strong>Abschluss:</strong> Sind alle Noten erfasst, wird der Fall automatisch abgeschlossen und die Noten in die Abmeldung zurückgeschrieben. Das PDF aus dem Dashboard enthält dann das vollständige Protokoll.</li>
            </ol>

            <div class="mh-inline-note">
                <strong>Betriebshinweis Cron:</strong> WordPress-Cron feuert nur bei Seitenaufrufen. Für verlässliche Erinnerungsfristen sollte auf dem Server ein echter Cron-Job
                <code class="mh-code-soft">wp-cron.php</code> regelmäßig aufrufen (z.&nbsp;B. stündlich). Der Hook heißt <code class="mh-code-soft">mh_fw_noten_reminders</code> und läuft täglich.
            </div>
        </div>

        <!-- ABSENTISMUS -->
        <div class="mh-info-section" id="mh-absentismus">
            <h2>Absentismus-Verfahren (Fehlzeiten-Eskalation)</h2>
            <p class="mh-lead">
                Bildet den mehrstufigen Eskalationsprozess bei unentschuldigten Fehlzeiten ab: von den
                pädagogischen Gesprächen über Mahnung, Bußgeldverfahren und Teilkonferenz bis zur Beendigung
                des Schulverhältnisses. Ein <strong>Fall</strong> bündelt alle Schritte eines Schülers/einer
                Schülerin und wird über <code>[mh_absentismus_fall]</code> eröffnet und bearbeitet;
                <code>[mh_absentismus_liste]</code> zeigt die Übersicht aller Fälle. Jeder Schritt steht
                zusätzlich als eigenständiges Einzelformular zur Verfügung, falls kein vollständiger Fall
                geführt werden soll.
            </p>

            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="22%">Funktion</th>
                        <th width="24%">Shortcode</th>
                        <th width="12%">Zugriff</th>
                        <th width="42%">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Fall-Workflow</strong></td>
                        <td><code>[mh_absentismus_fall]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span></td>
                        <td>Zentrale Seite zum Eröffnen eines Falls, zum Anlegen/Bearbeiten/Finalisieren der einzelnen Schritte, für Notizen und Kontaktpersonen sowie zur Timeline-Ansicht. Jeder finalisierte Schritt kann als PDF heruntergeladen werden. Fälle können abgeschlossen, wiedereröffnet und archiviert werden.</td>
                    </tr>
                    <tr>
                        <td><strong>Fallliste</strong></td>
                        <td><code>[mh_absentismus_liste]</code></td>
                        <td><span class="mh-badge mh-badge-login">angemeldet</span> <span class="mh-badge mh-badge-admin">Administrator</span></td>
                        <td>Übersicht aller Fälle mit Status-Filter und optionaler Anzeige archivierter Fälle (inkl. Mehrfach-Archivierung). Administratoren sehen alle Fälle, alle anderen Nutzer nur die selbst eröffneten. Der Backend-Menüpunkt „Absentismus-Fälle“ leitet auf diese Seite weiter.</td>
                    </tr>
                </tbody>
            </table>

            <h3>Eigenständige Einzelformulare (ohne Fall-Bindung)</h3>
            <p>
                Alle Einzelformulare sind nur für <span class="mh-badge mh-badge-login">angemeldete</span> Nutzer sichtbar, erzeugen direkt ein PDF und werden <strong>nicht</strong> in einem Fall gespeichert.
                Der Formulartyp ergibt sich aus dem Shortcode-Namen – Attribute gibt es nicht.
            </p>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="28%">Schritt</th>
                        <th width="30%">Shortcode</th>
                        <th width="42%">Regulärer Auslöser</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>1. Pädagogisches Gespräch</td><td><code>[mh_absentismus_gespraech_1]</code></td><td>ca. 10 unentschuldigte Fehlstunden (kumulativ)</td></tr>
                    <tr><td>2. Pädagogisches Gespräch</td><td><code>[mh_absentismus_gespraech_2]</code></td><td>weitere ca. 10 unentschuldigte Fehlstunden nach dem 1. Gespräch</td></tr>
                    <tr><td>Schriftliche Mahnung / Aufforderung Schulbesuch</td><td><code>[mh_absentismus_mahnung]</code></td><td>weiterhin unentschuldigte Fehlstunden nach dem 2. Gespräch</td></tr>
                    <tr><td>Einleitung Bußgeldverfahren / Anhörung</td><td><code>[mh_absentismus_bussgeld]</code></td><td>weitere Eskalation nach der Mahnung</td></tr>
                    <tr><td>Teilkonferenz</td><td><code>[mh_absentismus_teilkonferenz]</code></td><td>20 unentschuldigte Fehlstunden innerhalb von 30 Tagen</td></tr>
                    <tr><td>Zuführung durch das Ordnungsamt</td><td><code>[mh_absentismus_ordnungsamt]</code></td><td>3 Tage in Folge unentschuldigt gefehlt (nur schulpflichtige Schüler*innen)</td></tr>
                    <tr><td>Beendigung Schulverhältnis § 47 Abs. 1 Nr. 8 SchulG</td><td><code>[mh_absentismus_beendigung_47]</code></td><td>15 Tage in Folge unentschuldigt <em>oder</em> Teilkonferenz-Beschluss „Entlassung“ (nur <u>nicht mehr</u> schulpflichtige Schüler*innen)</td></tr>
                    <tr><td>Attestauflage</td><td><code>[mh_absentismus_attestauflage]</code></td><td>begründete Zweifel an einer krankheitsbedingten (entschuldigten) Abwesenheit</td></tr>
                </tbody>
            </table>
        </div>

        <!-- BACKEND-MENÜ -->
        <div class="mh-info-section" id="mh-menu">
            <h2>Das Backend-Menü „MH Formulare“</h2>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="25%">Menüpunkt</th>
                        <th width="75%">Inhalt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Übersicht &amp; Hilfe</strong></td>
                        <td>Diese Seite.</td>
                    </tr>
                    <tr>
                        <td><strong><a href="<?= esc_url( $mh_list_url ) ?>">Alle Einsendungen</a></strong></td>
                        <td>Administrator-Sicht auf alle gespeicherten Abmeldungen. Filter nach Zeitraum und Ersteller*in, PDF-Download je Eintrag, Mehrfachaktion „Löschen“ mit Checkboxen. Dienstbefreiungen erscheinen hier nicht, da sie nicht gespeichert werden.</td>
                    </tr>
                    <tr>
                        <td><strong><a href="<?= esc_url( $mh_settings_url ) ?>">Einstellungen</a></strong></td>
                        <td>Verknüpfung der Frontend-Seiten mit den Shortcodes sowie die Zeitparameter der Noteneinsammlung. Siehe <a href="#mh-einstellungen">unten</a>.</td>
                    </tr>
                    <tr>
                        <td><strong>Absentismus-Fälle</strong></td>
                        <td>Kein eigener Backend-Bildschirm: Der Menüpunkt leitet auf die in den Einstellungen hinterlegte Frontend-Seite mit <code class="mh-code-soft">[mh_absentismus_liste]</code> weiter. Ist keine Seite konfiguriert, erscheint ein entsprechender Hinweis.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- EINSTELLUNGEN -->
        <div class="mh-info-section" id="mh-einstellungen">
            <h2>Einstellungen im Detail</h2>
            <p class="mh-lead">
                Jede Seiten-Zuordnung sagt dem Plugin, <em>wo</em> ein Shortcode eingebunden ist. Das wird überall dort gebraucht, wo das Plugin selbst Links erzeugt (Dashboard, E-Mails, Weiterleitungen nach dem Absenden, Backend-Menü).
            </p>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="30%">Einstellung</th>
                        <th width="25%">Erwarteter Shortcode</th>
                        <th width="45%">Wird benötigt für</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Seite für Schüler-Abmeldung</td>
                        <td><code>[mh_form_workflow type="abmeldung_student_v1"]</code></td>
                        <td>„Bearbeiten“-Link im Benutzer-Dashboard sowie Link „das Abgangsformular“ in der Klassenleitungs-Ansicht der Noteneinsammlung.</td>
                    </tr>
                    <tr>
                        <td>Seite für Dienstbefreiung</td>
                        <td><code>[mh_form_workflow type="service_leave_v1"]</code></td>
                        <td>Basis-URL für das Benutzer-Dashboard. Da Dienstbefreiungen nicht gespeichert werden, hat die Einstellung derzeit keine sichtbare Auswirkung – sie ist für künftige Verlinkungen reserviert.</td>
                    </tr>
                    <tr>
                        <td>Seite für Absentismus-Fall (Formular)</td>
                        <td><code>[mh_absentismus_fall]</code></td>
                        <td>Links aus der Fallliste in die Fall-Ansicht, Weiterleitungen nach dem Speichern eines Schritts.</td>
                    </tr>
                    <tr>
                        <td>Seite für Absentismus-Fälle (Übersicht)</td>
                        <td><code>[mh_absentismus_liste]</code></td>
                        <td>Ausschließlich für den Backend-Menüpunkt „Absentismus-Fälle“, der auf diese Seite weiterleitet.</td>
                    </tr>
                    <tr>
                        <td>Seite für Noteneingabe (Fachlehrkraft)</td>
                        <td><code>[mh_noten_eingabe]</code></td>
                        <td>Ziel der Einladungs- und Erinnerungsmails. <strong>Ohne diese Einstellung enthalten die Mails keinen funktionierenden Link.</strong></td>
                    </tr>
                    <tr>
                        <td>Seite für „Meine Noteneingaben“</td>
                        <td><code>[mh_noten_liste]</code></td>
                        <td>Derzeit nur zur Dokumentation der Seitenzuordnung – das Plugin erzeugt keinen Link auf diese Seite. Die Liste ist alternativ auch über die Noteneingabe-Seite ohne URL-Parameter erreichbar.</td>
                    </tr>
                    <tr>
                        <td>Seite für Noteneinsammlung (Klassenlehrer)</td>
                        <td><code>[mh_noten_fall]</code></td>
                        <td>Weiterleitung nach „Noteneinsammlung digital starten“, Link in der Eskalationsmail an die Klassenleitung.</td>
                    </tr>
                    <tr>
                        <td>Erinnerung nach (Tagen)</td>
                        <td><code class="mh-code-soft">1–60, Standard 3</code></td>
                        <td>Abstand zwischen zwei Erinnerungsmails an dieselbe Fachlehrkraft.</td>
                    </tr>
                    <tr>
                        <td>Klassenlehrer informieren nach</td>
                        <td><code class="mh-code-soft">1–20, Standard 2</code></td>
                        <td>Anzahl erfolgloser Erinnerungen, nach der die Klassenleitung einmalig benachrichtigt wird.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- HINWEISE -->
        <div class="mh-info-section" id="mh-hinweise">
            <h2>Wichtige Hinweise</h2>
            <ul class="mh-notice-list">
                <li>
                    <span class="dashicons dashicons-warning" style="color:#e5a912;"></span>
                    <div>
                        <strong>Stammdaten-Abhängigkeit:</strong><br>
                        Die Auswahl von Klassen, Schüler*innen, Lehrkräften und Fächern basiert auf den Tabellen des Plugins <em>WebUntis Analyser</em>. Stellen Sie sicher, dass dort regelmäßig ein Import durchgeführt wird. Fehlen die Tabellen, bleiben die Auswahlfelder leer – das Plugin bricht nicht ab.
                        <br><br>
                        Die Fächer-Vorbelegung im Abgangsformular speist sich aus zwei Quellen: der <strong>Stundentafel des Bildungsgangs</strong> (gilt für alle Schüler*innen einer Klasse, gepflegt unter <em>WebUntis Analyser → Bildungsgänge</em>, der Klasse zugeordnet unter <em>Klassen</em>) und den <strong>Kursbelegungen</strong> der einzelnen Person (nur dort steht eine Lehrkraft). Bei einem manuellen Schülereintrag gibt es keine Kursbelegungen; die Stundentafel der Klasse wird trotzdem vorbelegt. Hat eine Klasse keinen Bildungsgang, bleibt die Fächertabelle leer.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-email-alt" style="color:#1b5e20;"></span>
                    <div>
                        <strong>E-Mail-Adressen der Lehrkräfte:</strong><br>
                        Die Noteneinsammlung findet Empfänger über das Lehrerkürzel. Pflegen Sie im WebUntis Analyser unter „Lehrer-Zuordnung“ die Benachrichtigungsadresse; andernfalls wird die E-Mail des verknüpften WordPress-Kontos verwendet. Ohne Adresse lässt sich kein Prozess starten.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-pdf" style="color:#d63638;"></span>
                    <div>
                        <strong>PDF-Generierung:</strong><br>
                        Die Dokumente werden serverseitig mit Dompdf erzeugt und direkt zum Download gestreamt. Das Datum im Dateinamen entspricht immer dem Datum der letzten Speicherung.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-lock" style="color:#0073aa;"></span>
                    <div>
                        <strong>Sensible Daten nur für angemeldete Nutzer:</strong><br>
                        Alle Absentismus- und Noten-Shortcodes sind bewusst nicht für Gäste freigegeben. Nicht angemeldete Besucher sehen nur den Hinweis „Bitte anmelden“ bzw. werden zum Login geleitet. Ein weitergeleiteter Mail-Link öffnet dadurch keine fremden Schülerdaten. Nutzer sehen grundsätzlich nur eigene Fälle und Eingaben; Administratoren (<code class="mh-code-soft">manage_options</code>) sehen alles.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-clock" style="color:#646970;"></span>
                    <div>
                        <strong>Automatische Datumskorrektur:</strong><br>
                        Termine in der Abmeldung werden auf Wochenenden und NRW-Schulferien geprüft und ggf. auf den nächsten Schultag verschoben. Das Formular weist auf jede Korrektur hin und verlangt eine erneute Bestätigung.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-database" style="color:#646970;"></span>
                    <div>
                        <strong>Datenspeicherung:</strong><br>
                        Abmeldungen, Noten-Fälle und Absentismus-Fälle liegen in der Plugin-Tabelle <code class="mh-code-soft">mh_form_submissions</code>. Dienstbefreiungen und die Absentismus-Einzelformulare werden nicht gespeichert. Beim Löschen eines Eintrags im Dashboard oder unter „Alle Einsendungen“ ist der Datensatz endgültig weg.
                    </div>
                </li>
            </ul>
        </div>

    </div>
</div>
