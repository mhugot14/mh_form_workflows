<?php
if ( ! defined( 'ABSPATH' ) ) exit;
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

    /* Schritte / Grid */
    .mh-help-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
</style>

<div class="wrap">
    <div class="mh-help-wrapper">
        
        <!-- HEADER -->
        <div class="mh-help-header">
            <h1>Willkommen bei MH Form Workflows</h1>
            <p>Digitale Formularprozesse für das LEBK Münster.</p>
        </div>

        <!-- SCHRITTE -->
        <div class="mh-help-steps">
            <div class="mh-step-card">
                <span class="dashicons dashicons-admin-page"></span>
                <h3>1. Seiten anlegen</h3>
                <p>Erstellen Sie in WordPress neue Seiten für die Formulare (z.B. "Abmeldung") und das Dashboard ("Meine Anträge").</p>
            </div>
            <div class="mh-step-card">
                <span class="dashicons dashicons-shortcode"></span>
                <h3>2. Shortcodes einbinden</h3>
                <p>Kopieren Sie die unten stehenden Shortcodes in den Inhalt der jeweiligen Seiten.</p>
            </div>
            <div class="mh-step-card">
                <span class="dashicons dashicons-admin-settings"></span>
                <h3>3. Verknüpfung prüfen</h3>
                <p>Gehen Sie zu den <a href="admin.php?page=mh-form-workflows-settings">Einstellungen</a> und wählen Sie dort die erstellten Seiten aus.</p>
            </div>
        </div>

        <!-- SHORTCODES -->
        <div class="mh-info-section">
            <h2>Verfügbare Shortcodes</h2>
            <table class="mh-shortcode-table">
                <thead>
                    <tr>
                        <th width="25%">Funktion</th>
                        <th width="45%">Shortcode</th>
                        <th width="30%">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Schüler-Abmeldung</strong></td>
                        <td><code>[mh_form_workflow type="abmeldung_student_v1"]</code></td>
                        <td>Formular zur Ausschulung/Abmeldung.</td>
                    </tr>
                    <tr>
                        <td><strong>Dienstbefreiung</strong></td>
                        <td><code>[mh_form_workflow type="service_leave_v1"]</code></td>
                        <td>Antrag auf Dienstbefreiung / Sonderurlaub.</td>
                    </tr>
                    <tr>
                        <td><strong>Benutzer-Dashboard</strong></td>
                        <td><code>[mh_my_submissions]</code></td>
                        <td>Liste der eigenen Anträge für Lehrer.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ABSENTISMUS -->
        <div class="mh-info-section">
            <h2>Absentismus-Verfahren (Fehlzeiten-Eskalation)</h2>
            <p style="color:#646970; margin-top:-10px; margin-bottom:20px; line-height:1.6;">
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
                        <th width="25%">Funktion</th>
                        <th width="35%">Shortcode</th>
                        <th width="40%">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Fall-Workflow</strong></td>
                        <td><code>[mh_absentismus_fall]</code></td>
                        <td>Zentrale Seite zum Eröffnen eines Falls, zum Anlegen/Bearbeiten der einzelnen Schritte sowie zur Timeline-Ansicht eines Falls.</td>
                    </tr>
                    <tr>
                        <td><strong>Fallliste</strong></td>
                        <td><code>[mh_absentismus_liste]</code></td>
                        <td>Übersicht aller Fälle. Admins sehen alle Fälle, alle anderen Nutzer nur die selbst eröffneten.</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top:25px; margin-bottom:10px; font-size:15px; color:#23282d;">Eigenständige Einzelformulare (ohne Fall-Bindung)</h3>
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

        <!-- HINWEISE -->
        <div class="mh-info-section">
            <h2>Wichtige Hinweise</h2>
            <ul class="mh-notice-list">
                <li>
                    <span class="dashicons dashicons-warning" style="color:#e5a912;"></span>
                    <div>
                        <strong>Stammdaten-Abhängigkeit:</strong><br>
                        Die Auswahl von Klassen und Schülern basiert auf den Daten des Plugins <em>WebUntis Analyser</em>. Stellen Sie sicher, dass dort regelmäßig ein Import durchgeführt wird.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-pdf" style="color:#d63638;"></span>
                    <div>
                        <strong>PDF-Generierung:</strong><br>
                        Die Dokumente werden als PDF/A-kompatible Dateien erzeugt. Das Datum im Dateinamen entspricht immer dem Datum der letzten Speicherung.
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-lock" style="color:#0073aa;"></span>
                    <div>
                        <strong>Absentismus nur für eingeloggte Nutzer:</strong><br>
                        Alle Absentismus-Shortcodes sind bewusst nicht für Gäste freigegeben, da sie sensible Schülerdaten anzeigen. Nicht angemeldete Besucher sehen nur den Hinweis „Bitte anmelden“.
                    </div>
                </li>
            </ul>
        </div>

    </div>
</div>