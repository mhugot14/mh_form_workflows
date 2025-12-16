<?php
/**
 * View: PDF Antrag auf Dienstbefreiung / Sonderurlaub
 */

// Symbole
$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>'; 
$scissor = '<span style="font-family: DejaVu Sans, sans-serif; font-size: 16pt;">&#9988;</span>';

// Helper
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$chk = fn($val) => ( ($data['reason_key'] ?? '') === $val ) ? $x : $o;

// Datum formatieren
$fmt_date = function($iso) {
    if(empty($iso)) return '..................';
    return date('d.m.Y', strtotime($iso));
};

// Zeit & Datum Strings bauen
$time_str = '-';
if(!empty($data['time_start'])) {
    $time_str = $esc('time_start');
    if(!empty($data['time_end'])) $time_str .= ' bis ' . $esc('time_end');
}

$date_range = $fmt_date($data['date_start']);
if(!empty($data['date_end']) && $data['date_end'] !== $data['date_start']) {
    $date_range .= ' bis ' . $fmt_date($data['date_end']);
}

// Mapping für Klartext-Grund im Abschnitt unten
$reasons_map = [
    'krank_planbar' => 'Planbare Krankheit',
    'beerdigung' => 'Beerdigung',
    'einschulung' => 'Einschulung Kind',
    'versorgung' => 'Versorgungsleistungen',
    'sonstige' => 'Sonstiges',
    'fb_br' => 'Fortbildung BR',
    'fb_schelf' => 'Fortbildung SchELF',
    'fb_schilf' => 'Fortbildung SchiLF',
    'pruefung' => 'Vorprüfung / IHK',
    'jubilaeum' => 'Dienstjubiläum',
    'geburt_tod' => 'Tod / Niederkunft',
    'erkrankung_kind' => 'Erkrankung Kind/Ang.',
    'sport' => 'Sport / Politisch',
    'dienstunfall' => 'Dienstveranstaltung'
];
$reason_label = $reasons_map[$data['reason_key'] ?? ''] ?? 'Sonstiges';
?>
<html>
<head>
<style>
    @page { margin: 1cm; }
    body { font-family: Helvetica, sans-serif; font-size: 10pt; line-height: 1.3; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    td, th { border: 1px solid black; padding: 4px; vertical-align: middle; }
    
    .no-border td { border: none; }
    .header { font-weight: bold; font-size: 14pt; margin-bottom: 5px; }
    .bg-gray { background-color: #eee; font-weight: bold; }
    
    /* Box oben rechts */
    .stamp-box {
        border: 1px solid black;
        width: 150px; height: 50px;
        float: right;
        font-size: 8pt; color: #666;
        padding: 5px; margin-bottom: 10px;
    }
    
    .check-row { margin-bottom: 1px; font-size: 9pt; }
    .label-col { width: 25%; background: #f0f0f0; font-weight: bold; }

    /* Styles für den Abreiß-Abschnitt */
    .cut-line {
        border-top: 3px dashed #666;
        margin-top: 25px;
        margin-bottom: 15px;
        position: relative;
        height: 10px;
    }
    .cut-icon {
        position: absolute;
        top: -15px; left: 30px;
        background: white;
        padding: 0 5px;
    }
    
    /* Blau hinterlegte Header im Abschnitt */
    .bg-blue { background-color: #daeef3; font-weight: bold; text-align: center; } /* Helles Blau ähnlich Screenshot */
</style>
</head>
<body>

    <!-- Header Bereich -->
    <div class="stamp-box">Eingangsstempel</div>
    
    <div style="font-weight:bold; font-size:12pt; margin-bottom:5px;">
        Ludwig-Erhard-Berufskolleg
    </div>
    <div class="header">Antrag auf Dienstbefreiung / Sonderurlaub</div>
    
    <p style="font-size:8pt; margin-bottom:5px;">
        <i>Bitte zu jedem Antrag vor verbindlicher Anmeldung Rücksprache mit der Schulleitung nehmen!</i>
    </p>

    <!-- SEKTION 1: GRÜNDE -->
    <table style="font-size: 9pt;">
        <tr class="bg-gray">
            <td width="33%">Dienstbefreiung</td>
            <td width="33%">Unterrichtsbefreiung</td>
            <td width="33%">Sonderurlaub / Unfall</td>
        </tr>
        <tr style="vertical-align: top;">
            <td>
                <div class="check-row"><?= $chk('krank_planbar') ?> Planbare Krankheit</div>
                <div class="check-row"><?= $chk('beerdigung') ?> Beerdigungen</div>
                <div class="check-row"><?= $chk('einschulung') ?> Einschulung Kind</div>
                <div class="check-row"><?= $chk('versorgung') ?> Versorgungsleist.</div>
                <div class="check-row"><?= $chk('sonstige') ?> Sonstiges</div>
            </td>
            <td>
                <div class="check-row"><?= $chk('fb_br') ?> Fortbildung BR</div>
                <div class="check-row"><?= $chk('fb_schelf') ?> Fortbildung SchELF</div>
                <div class="check-row"><?= $chk('fb_schilf') ?> Fortbildung SchiLF</div>
                <div class="check-row"><?= $chk('pruefung') ?> IHK / Vorprüfung</div>
            </td>
            <td>
                <div class="check-row"><?= $chk('jubilaeum') ?> Dienstjubiläum</div>
                <div class="check-row"><?= $chk('geburt_tod') ?> Tod / Niederkunft</div>
                <div class="check-row"><?= $chk('erkrankung_kind') ?> Erkrankung Kind/Ang.</div>
                <div class="check-row"><?= $chk('sport') ?> Sport / Politisch</div>
                <hr style="margin:2px 0;">
                <div class="check-row"><?= $chk('dienstunfall') ?> <b>Dienstveranstaltung</b><br>(Unfallschutz)</div>
            </td>
        </tr>
    </table>

    <!-- SEKTION 2: DETAILS -->
    <table>
        <tr>
            <td class="label-col">Antragsteller*in:</td>
            <td><b><?= $esc('lastname') ?>, <?= $esc('firstname') ?></b></td>
        </tr>
        <tr>
            <td class="label-col">Datum / Zeitraum:</td>
            <td><?= $date_range ?></td>
        </tr>
        <tr>
            <td class="label-col">Zeit (Stunde/Uhrzeit):</td>
            <td><?= $time_str ?></td>
        </tr>
        <tr>
            <td class="label-col" colspan="2" style="border-bottom:none;">Grund für den Antrag / Erläuterung / Ggf. Anlagen:</td>
        </tr>
        <tr>
            <td colspan="2" height="40" style="vertical-align: top; border-top:none;">
                <?= nl2br($esc('reason_text')) ?>
            </td>
        </tr>
        <tr>
            <td class="label-col">Weitere Beteiligte:</td>
            <td><?= $esc('colleagues') ?></td>
        </tr>
        <tr>
            <td class="label-col">Terminkollision mit:</td>
            <td><?= $esc('collision') ?></td>
        </tr>
    </table>

    <!-- Unterschriften oben -->
    <p style="font-size:8pt; margin: 2px 0 5px 0;">Hinweise zur Vertretung bitte auf der Rückseite ergänzen. (Mind. 14 Tage vorher einreichen)</p>
    <table class="no-border" style="margin-top: 5px;">
        <tr>
            <td width="30%" style="border-bottom: 1px solid black; padding-bottom: 0;">Münster, <?= date('d.m.Y') ?></td>
            <td width="10%"></td>
            <td width="60%" style="border-bottom: 1px solid black; padding-bottom: 0;"></td>
        </tr>
        <tr>
            <td style="font-size: 7pt; padding:0;">Datum</td>
            <td></td>
            <td style="font-size: 7pt; padding:0;">Unterschrift Antragsteller*in</td>
        </tr>
    </table>

    <!-- SCHNITT-LINIE -->
    <div class="cut-line">
        <div class="cut-icon"><?= $scissor ?></div>
    </div>

    <!-- ABSCHNITT (QUITTUNG) -->
    <div style="font-weight: bold; margin-bottom: 5px;">
        Abschnitt für Antragsteller*in 
        <span style="font-weight: normal; float:right;">
            Name, Vorname: <u><?= $esc('lastname') ?>, <?= $esc('firstname') ?></u> &nbsp;&nbsp;&nbsp;
        </span>
    </div>

    <table style="margin-bottom: 0;">
        <tr>
            <td width="40%">Befreiungsdatum/ -zeitraum (von – bis)</td>
            <td width="60%"><b><?= $date_range ?></b></td>
        </tr>
        <tr>
            <td>von Stunde – bis Stunde</td>
            <td><?= $time_str ?></td>
        </tr>
        <tr>
            <td>Grund für den Antrag</td>
            <td>
                <b><?= $reason_label ?></b>
                <?php if(!empty($data['reason_text'])) echo ' (' . substr($esc('reason_text'), 0, 30) . '...)'; ?>
            </td>
        </tr>
    </table>

    <!-- Genehmigungstabelle -->
    <table style="margin-top: -1px; width: 100%;">
        <tr class="bg-blue">
            <th width="30%">Genehmigungsvermerk</th>
            <th width="20%">Genehmigung</th>
            <th width="35%">Unterschrift</th>
            <th width="15%">Datum</th>
        </tr>
        <tr>
            <td>Antrag an die Schulleitung:</td>
            <td>ja &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp; nein</td>
            <td></td>
            <td></td>
        </tr>
    </table>


    <!-- SEITE 2: VERTRETUNG -->
    <div style="page-break-before: always;"></div>

    <div class="header" style="text-align: left;">Rückseite: Hinweise zur Vertretungsplanung</div>
    
    <table style="margin-top: 20px;">
        <tr class="bg-gray">
            <th width="20%">Datum</th>
            <th width="20%">Lerngruppe / Klasse</th>
            <th width="10%">Stunde</th>
            <th width="50%">Vertretungshinweise / Aufgaben</th>
        </tr>
        <?php 
        $rows = $data['sub_rows'] ?? [];
        $rows_to_print = max(count($rows), 8); 
        for($i = 0; $i < $rows_to_print; $i++): 
            $r = $rows[$i] ?? [];
            $d_date = !empty($r['date']) ? date('d.m.', strtotime($r['date'])) : '';
        ?>
        <tr>
            <td style="height: 25px;"><?= $d_date ?></td>
            <td><?= htmlspecialchars($r['group'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['hour'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['info'] ?? '') ?></td>
        </tr>
        <?php endfor; ?>
    </table>

</body>
</html>