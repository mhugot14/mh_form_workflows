<?php
/**
 * View: PDF Template
 */

// Helper für Checkboxen
$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>'; 

$chk = fn($field, $val) => ($data[$field] ?? '') === $val ? $x : $o;
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');

// Helper für Datumsformatierung (YYYY-MM-DD -> DD.MM.YYYY)
$date_fmt = function($field) use ($data) {
    $val = $data[$field] ?? '';
    if ( empty( $val ) ) return '';
    $ts = strtotime( $val );
    return $ts ? date( 'd.m.Y', $ts ) : $val;
};
?>
<html>
<head>
<style>
    @page { margin: 2cm 1.5cm 2cm 1.5cm; } /* Ränder für A4 */
    body { font-family: Helvetica, sans-serif; font-size: 10pt; line-height: 1.3; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    td, th { border: 1px solid black; padding: 4px; vertical-align: top; }
    
    .header { font-weight: bold; font-size: 14pt; text-align: center; margin-bottom: 20px; }
    .bg-gray { background-color: #eee; }
    .section-num { font-weight: bold; font-size: 14pt; width: 25px; text-align: center; }
    
    /* Footer Style */
    #footer {
        position: fixed; 
        bottom: -50px; 
        left: 0px; 
        right: 0px; 
        height: 30px; 
        color: #555;
        font-size: 8pt;
        text-align: center;
        border-top: 1px solid #ccc;
        padding-top: 5px;
    }
</style>
</head>
<body>

    <!-- Fußzeile -->
    <div id="footer">
        Ludwig-Erhard-Berufskolleg Münster | Schulverwaltung
    </div>

    <div class="header">Abmeldung von Schülerinnen und Schülern</div>

    <!-- Stammdaten Tabelle -->
    <table>
        <tr>
            <td width="45%">Name:<br><b><?= $esc('lastname') ?></b></td>
            <td width="25%">Geburtsdatum:<br><b><?= $date_fmt('dob') ?></b></td>
            <td width="30%">Klasse:<br><b><?= $esc('class_name') ?></b></td>
        </tr>
        <tr>
            <td>Vorname:<br><b><?= $esc('firstname') ?></b></td>
            <td>
                <?= ($data['is_minor'] ? $x : $o) ?> Minderjährig<br>
                <?= (!$data['is_minor'] ? $x : $o) ?> Volljährig
            </td>
            <td>Klassenlehrer/in:<br><b><?= $esc('teacher') ?></b></td>
        </tr>
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td width="50%" class="bg-gray"><b>Datum der Abmeldung</b> (Abmeldung bitte beifügen):</td>
            <td><b><?= $date_fmt('date_off') ?></b></td>
        </tr>
    </table>

    <!-- 1. GRUND -->
    <div style="font-weight: bold; margin-top: 10px;">Grund der Abmeldung:</div>
    <table>
        <tr>
            <td rowspan="5" class="section-num">1</td>
            <td><?= $chk('reason', 'schulwechsel') ?> Schulwechsel (Name/Ort): <b><?= $esc('new_school') ?></b></td>
        </tr>
        <tr><td><?= $chk('reason', 'aufloesung') ?> Auflösung Ausbildungsvertrag / Beendigung des Ausbildungsverhältnisses</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_beschluss') ?> Ausschulung Beschluss Teilkonferenz</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_47') ?> Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</td></tr>
        <tr><td><?= $chk('reason', 'abmeldung') ?> Abmeldung</td></tr>
    </table>

    <!-- 2. SCHULPFLICHT -->
    <div style="font-weight: bold; margin-top: 10px;">Schulpflicht:</div>
    <table>
        <tr>
            <td rowspan="4" class="section-num">2</td>
            <td><?= $chk('compulsory', 'fulfilled') ?> Die Schulpflicht ist erfüllt.</td>
        </tr>
        <tr>
            <td>
                <?= $chk('compulsory', 'not_fulfilled') ?> Die Schulpflicht ist NICHT erfüllt.<br>
                <small>Schulpflichtverfolgung: Aufnahmebestätigung der aufnehmenden Schule oder Ausbildungsvertrag beifügen</small>
            </td>
        </tr>
        <tr>
            <td>
                <?= $chk('compulsory', 'av_klasse') ?> Der/die SchülerIn wechselt in die AV-Klasse zum: <b><?= $date_fmt('av_date_start') ?></b><br>
                &nbsp;&nbsp;&nbsp;&nbsp; Aufnahmegespräch mit: <b><?= $esc('av_talk_with') ?></b> am: <b><?= $date_fmt('av_talk_date') ?></b>
            </td>
        </tr>
        <tr>
            <td>
                <?= $chk('compulsory', 'bildungsgang') ?> Der/die SchülerIn wechselt in den Bildungsgang: <b><?= $esc('new_education_track') ?></b>
            </td>
        </tr>
    </table>

    <!-- 3. ZEUGNIS -->
    <div style="font-weight: bold; margin-top: 10px;">Zeugnis: <small>(Zeugniskonferenzprotokoll bitte beifügen!)</small></div>
    <!-- Checkbox für Protokoll -->
    <div style="margin-bottom: 5px;">
        <?= $chk('protocol_attached', '1') ?> Zeugniskonferenzprotokoll liegt bei.
    </div>

    <table>
        <tr>
            <td rowspan="2" class="section-num">3</td>
            <td>
                <?= $chk('certificate', 'ueberweisung') ?> Überweisungszeugnis gem. § 49 SchulG<br>
                <small>(Der/die SchülerIn wechselt innerhalb derselben Schulstufe die Schule.)</small><br>
                <div style="text-align: right; margin-top: 5px;">
                    Fehlstunden: <b><?= $esc('missed_hours') ?></b> davon unentschuldigt: <b><?= $esc('missed_ue') ?></b>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <?= $chk('certificate', 'abgang') ?> Abgangszeugnis gem. § 49 SchulG<br>
                <small>(Der/die SchülerIn verlässt die Schule/den Bildungsgang nach Erfüllung der Schulpflicht ohne Abschluss.)</small>
            </td>
        </tr>
    </table>

    <br>
    <!-- Unterschriften -->
    <table>
        <tr class="bg-gray">
            <td width="30%">Ablauf</td>
            <td width="20%">Datum</td>
            <td width="50%">Unterschrift</td>
        </tr>
        <tr><td>Klassenleitung</td><td></td><td></td></tr>
        <tr><td>Abteilungsleitung</td><td></td><td></td></tr>
        <tr><td>WebUntis (Herr Dagott)</td><td></td><td></td></tr>
        <tr><td>Schild</td><td></td><td></td></tr>
        <tr>
            <td>Schulverwaltungsassistenz<br><small>(Bücher / Schülerausweis / Microsoft 365 / Surface)</small></td>
            <td></td><td></td>
        </tr>
    </table>

</body>
</html>