<?php
/**
 * View: PDF Template Master (Seite 1 & Styles für alles)
 */

$x = '<span style="font-family: DejaVu Sans, sans-serif; font-size:10pt;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif; font-size:12pt;">&#9744;</span>'; 

$chk = fn($field, $val) => ($data[$field] ?? '') === $val ? $x : $o;
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$date_fmt = function($field) use ($data) {
    $val = $data[$field] ?? ''; return empty($val) ? '' : date('d.m.Y', strtotime($val));
};
?>
<html>
<head>
<style>
    /* Ränder entspannt für besseres Aussehen */
    @page { margin: 1.0cm 1.5cm 1.2cm 1.5cm; } 
    
    body { font-family: Helvetica, sans-serif; font-size: 9pt; line-height: 1.2; }
    
    /* Globale Tabellen-Regeln gegen Absturz */
    table { width: 100%; border-collapse: collapse; margin-bottom: 3px; page-break-inside: avoid; }
    tr { page-break-inside: avoid; }
    td, th { border: 1px solid black; padding: 3px 5px; vertical-align: top; }
    
    /* Layout */
    .header { font-weight: bold; font-size: 14pt; text-align: center; margin-bottom: 15px; margin-top:0; }
    .bg-gray { background-color: #eee; }
    .section-num { font-weight: bold; font-size: 14pt; width: 25px; text-align: center; vertical-align: middle; }
    
    .check-row { margin-bottom: 2px; } 
    .small-text { font-size: 8pt; }
    
    /* Layout-Tabelle ohne Rahmen (für Spalten innerhalb von Zellen) */
    table.layout-table, table.layout-table td { border: none !important; padding: 0; margin: 0; }

    /* Footer Fixiert */
    #footer {
        position: fixed; 
        bottom: -30px; 
        left: 0px; right: 0px; 
        height: 20px; 
        color: #555; font-size: 8pt; text-align: center;
        border-top: 1px solid #ccc; padding-top: 5px;
    }
</style>
</head>
<body>

    <div id="footer">
        Ludwig-Erhard-Berufskolleg Münster | Schulverwaltung | <?= date('d.m.Y') ?>
    </div>

    <!-- Header -->
    <div class="header">Abmeldung von Schülerinnen und Schülern</div>

    <!-- Stammdaten -->
    <table style="margin-bottom: 10px;">
        <tr>
            <td width="45%">Name:<br><b><?= $esc('lastname') ?></b></td>
            <td width="25%">Geburtsdatum:<br><b><?= $date_fmt('dob') ?></b></td>
            <td width="30%">Klasse:<br><b><?= $esc('class_name') ?></b></td>
        </tr>
        <tr>
            <td>Vorname:<br><b><?= $esc('firstname') ?></b></td>
            <td>
                <?= ($data['is_minor'] ? $x : $o) ?> Minderjährig &nbsp;
                <?= (!$data['is_minor'] ? $x : $o) ?> Volljährig
            </td>
            <td>Klassenlehrer/in:<br><b><?= $esc('teacher') ?></b></td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td width="70%" class="bg-gray"><b>Datum der Abmeldung / Ende Schulverhältnis</b> (Abmeldung bitte anfügen):</td>
            <td><b><?= $date_fmt('date_off') ?></b></td>
        </tr>
    </table>

    <!-- 1. GRUND -->
    <div style="font-weight: bold; margin-bottom: 2px;">Grund der Abmeldung:</div>
    <table style="margin-bottom: 5px;">
        <tr>
            <td rowspan="5" class="section-num">1</td>
            <td><?= $chk('reason', 'schulwechsel') ?> Schulwechsel (Name und Ort der aufnehmenden Schule):<br><b><?= $esc('new_school') ?></b></td>
        </tr>
        <tr><td><?= $chk('reason', 'aufloesung') ?> Auflösung Ausbildungsvertrag/Beendigung des Ausbildungsverhältnisses</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_beschluss') ?> Ausschulung durch Beschluss Teilkonferenz</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_47') ?> Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</td></tr>
        <tr><td><?= $chk('reason', 'abmeldung') ?> Abmeldung</td></tr>
    </table>

    <!-- 2. SCHULPFLICHT -->
    <div style="font-weight: bold; margin-top: 5px; margin-bottom: 2px;">Schulpflicht:</div>
    <table style="border-bottom:none; margin-bottom:0;">
        <tr>
            <td rowspan="4" class="section-num">2</td>
            <td><?= $chk('compulsory', 'fulfilled') ?> Die Schulpflicht ist erfüllt.</td>
        </tr>
        <tr>
            <td>
                <?= $chk('compulsory', 'not_fulfilled') ?> Die Schulpflicht ist NICHT erfüllt.<br>
                <span class="small-text">Schulpflichtverfolgung: Aufnahmebestätigung der aufnehmenden Schule oder Ausbildungsvertrag beifügen</span>
            </td>
        </tr>
        <tr>
            <td>
                <?= $chk('compulsory', 'av_klasse') ?> Der/die SchülerIn wechselt in die AV-Klasse zum: <b><?= $date_fmt('av_date_start') ?></b><br>
                &nbsp;&nbsp;&nbsp;&nbsp; Aufnahmegespräch mit: <b><?= $esc('av_talk_with') ?></b> am: <b><?= $date_fmt('av_talk_date') ?></b>
            </td>
        </tr>
        <tr>
            <td style="border-bottom: 1px solid black;">
                <?= $chk('compulsory', 'bildungsgang') ?> Der/die SchülerIn wechselt in den Bildungsgang: <b><?= $esc('new_education_track') ?></b>
            </td>
        </tr>
    </table>

    <!-- 3. ANSCHLUSSPERSPEKTIVE -->
    <div style="font-weight: bold; margin-top: 8px; margin-bottom: 2px;">
        Anschlussperspektive:
        <span style="font-weight:normal; font-size:8pt; margin-left:10px;">Auszufüllen für folgende Bildungsgänge: AV, BFI, BFII, HH, KA, WG</span>
    </div>
    
    <table style="margin-bottom: 5px;">
        <tr>
            <td rowspan="2" class="section-num">3</td>
            <td style="padding: 5px;">
                <div style="margin-bottom:3px; font-weight:bold;">
                    <?= $chk('perspective', 'exists') ?> Es liegt eine konkrete Anschlussperspektive vor:
                </div>
                <div style="margin-left: 20px;">
                    <div class="check-row"><?= $chk('perspective_detail', 'ausbildung') ?> unterschriebener Ausbildungsvertrag</div>
                    <div class="check-row"><?= $chk('perspective_detail', 'schule') ?> Aufnahmebestätigung einer anderen Schule</div>
                    <div class="check-row"><?= $chk('perspective_detail', 'studium') ?> schriftliche Zusage eines Studienplatzes</div>
                    <div class="check-row"><?= $chk('perspective_detail', 'fsj') ?> schriftliche Zusage eines FSJ, FÖJ oder BFD</div>
                    <div class="check-row">
                        <?= $chk('perspective_detail', 'sonstiges') ?> sonstiges: 
                        <?php if($data['perspective_detail'] === 'sonstiges') echo '<u>' . substr($esc('perspective_other'),0,40) . '</u>'; ?>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding: 5px;">
                <?= $chk('perspective', 'none') ?> <b>Es liegt keine konkrete Anschlussperspektive vor.</b>
            </td>
        </tr>
    </table>
	
    <!-- 4. ZEUGNIS -->
    <div style="font-weight: bold; margin-top: 8px; margin-bottom: 2px;">
        Zeugnis:    &nbsp;&nbsp;&nbsp;      <?= $chk('protocol_attached', '1') ?> Zeugniskonferenzprotokoll liegt bei
    </div>

    <table class="mb-0">
        <tr>
            <td rowspan="2" class="section-num">4</td>
            <td style="padding: 5px;">
                <table class="layout-table" width="100%">
                    <tr>
                        <td width="65%">
                            <?= $chk('certificate', 'ueberweisung') ?> Überweisungszeugnis gem. § 49 SchulG<br>
                            <span class="small-text" style="padding-left:15px;">(Der/die SchülerIn wechselt innerhalb derselben Schulstufe die Schule.)</span>
                        </td>
                        <td width="35%" style="text-align:right;">
                           
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 5px;">
                <?= $chk('certificate', 'abgang') ?> Abgangszeugnis gem. § 49 SchulG<br>
                <span class="small-text" style="padding-left:15px;">(Der/die SchülerIn verlässt die Schule/den Bildungsgang <u>nach</u> Erfüllung der Schulpflicht <u>ohne</u> Abschluss.)</span>
            </td>
        </tr>
    </table>

    <!-- UNTERSCHRIFTEN -->
    <table class="no-border" style="margin-top: 15px;">
        <tr class="bg-gray">
            <td width="40%">Ablauf</td>
            <td width="20%">Datum</td>
            <td width="40%">Unterschrift</td>
        </tr>
        <tr><td>Klassenleitung</td><td></td><td></td></tr>
        <tr><td>Abteilungsleitung</td><td></td><td></td></tr>
        <tr><td>WebUntis (Herr Dagott)</td><td></td><td></td></tr>
        <tr><td>Schild-Team</td><td></td><td></td></tr>
        <tr>
            <td>Schulverwaltungsassistenz<br><span class="small-text">(Bücher/Schülerausweis/M365/Surface)</span></td>
            <td></td><td></td>
        </tr>
    </table>

<!-- Das Ende des BODY ist hier NICHT, da eventuell noch das Protokoll angehängt wird. 
     Der Controller schließt das </body></html> -->