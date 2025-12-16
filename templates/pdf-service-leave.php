<?php
/**
 * View: PDF Antrag auf Dienstbefreiung / Sonderurlaub
 */

// Symbole
$x = '<span style="font-family: DejaVu Sans, sans-serif; font-size: 10pt;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif; font-size: 12pt;">&#9744;</span>'; 
$scissor = '<span style="font-family: DejaVu Sans, sans-serif; font-size: 16pt;">&#9988;</span>';

// Helper
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$chk = fn($val) => ( ($data['reason_key'] ?? '') === $val ) ? $x : $o;
$footer_date = date('y-m-d');
$footer_id   = $data['entry_id'] ?? 0;
$footer_name = ($data['lastname'] ?? '') . '-' . ($data['firstname'] ?? '');
// Bereinigen (Umlaute etc. sicherheitshalber entfernen für Dateinamen-Optik, falls gewünscht, oder roh lassen)
// Hier nehmen wir es direkt, wie es auch im Controller gemacht wird:
$footer_text = sprintf('LEBK Schulverwaltung: %s_%d_Befreiung_%s', $footer_date, $footer_id, $footer_name);

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
    'allg_br' => 'Allg. Einladungen der BR', // Neu
    'pruefung' => 'Vorprüfung / Fachberater', // Neu benannt
    'ihk' => 'IHK-Prüfungen', // Neu
    'jubilaeum' => 'Dienstjubiläum',
    'geburt_tod' => 'Tod Angehörige/r',
    'niederkunft' => 'Niederkunft Ehefrau/Partnerin',
    'umzug' => 'Umzug',
    'erkrankung_ange' => 'Schwere Erkrankung Angehörige',
    'erkrankung_kind' => 'Erkrankung Kind u. 12',
    'betreuung' => 'Erkr. Betreuungsperson (Kind u. 8)',
    'pol_bildung' => 'Politische Bildung',
    'sport' => 'Sport-Meisterschaften',
    'sonstige_dringend' => 'Sonstige dringende Fälle',
    'dienstunfall' => 'Dienstveranstaltung ohne Unterrichtsbefreiung'
];
$reason_label = $reasons_map[$data['reason_key'] ?? ''] ?? 'Sonstiges';
?>
<html>
<head>
<style>
    @page { margin: 1cm 1cm 0.5cm 1cm; }
    body { font-family: Helvetica, sans-serif; font-size: 9pt; line-height: 1.1; }
    
    /* Layout */
    table { width: 100%; border-collapse: collapse; margin-bottom: 0px; }
    td, th { border: 1px solid black; padding: 3px; vertical-align: top; }
    
    .no-border td { border: none; }
    
    /* Header Table Styles */
    .th-blue { background-color: #B4C6E7; text-align: center; font-weight: bold; font-size: 10pt; height: 30px; vertical-align: middle; }
    .th-blue-dark { background-color: #B4C6E7; text-align: center; font-weight: bold; border-top: 1px solid black; border-bottom: 1px solid black; margin: 5px -4px; padding: 2px 0; }
    
    .check-row { margin-bottom: 3px; padding-left: 2px; }
    .small-note { font-size: 7pt; line-height: 1; }
    
    .label-col { width: 30%; background: #f0f0f0; font-weight: bold; }
    
    /* Boxen und Linien für Handschrift */
    .write-line { border-bottom: 1px solid black; display: inline-block; width: 100%; height: 14px; }
    
    /* Schnittlinie */
    .cut-line { border-top: 2px dashed #666; margin-top: 20px; margin-bottom: 10px; position: relative; height: 10px; }
    .cut-icon { position: absolute; top: -12px; left: 10px; background: white; padding: 0 5px; font-size: 14pt; }
	
	/* Footer Style */
    #footer {
        position: fixed; 
        bottom: -20px; 
        left: 0px; 
        right: 0px; 
        height: 20px; 
        color: #888;
        font-size: 8pt;
        text-align: right; /* Rechtsbündig sieht meist gut aus für Dateinamen */
        padding-right: 0px;
    }
</style>
</head>
<body>
	<!-- Fußzeile -->
    <div id="footer">
        <?= $footer_text ?>
    </div>
	<h2>Antrag: Unterrichts- und Dienstbefreiung</h2>
    <!-- Titel -->
    <div style="margin-bottom: 5px;">
        <span style="font-weight:bold; font-size:10pt;">
            Bitte zu jedem <u style="font-weight:bold;">Antrag</u> vor verbindlicher Anmeldung Rücksprache mit der Schulleitung nehmen!
        </span>
        <!-- Logo Platzhalter links oben ist im Bild, hier Text -->
    </div>

    <!-- HAUPTTABELLE OBEN -->
    <table style="font-size: 8pt;">
        <!-- Kopfzeile -->
        <tr>
            <td width="23%" class="th-blue">Dienstbefreiung</td>
            <td width="23%" class="th-blue">Unterrichtsbefreiung</td>
            <td width="27%" class="th-blue">Sonderurlaub</td>
            <td width="27%" class="th-blue">Genehmigungsvermerk<br>der Schulleitung</td>
        </tr>
        
        <!-- Inhalt -->
        <tr>
            <!-- SPALTE 1: Dienstbefreiung -->
            <td style="vertical-align: top;">
                <div style="font-weight:bold; margin-bottom:5px;">
                    zwingend Rücksprache <span style="font-weight:normal">mit der Schulleitung – auch für Konferenzen</span>
                </div>
                
                <div class="check-row" style="margin-bottom:10px;">
                    <?= $chk('krank_planbar') ?> Planbare Krankheitsangelegenheiten
                </div>
                
                <div style="margin-bottom:2px;">besondere persönliche Ereignisse wie:</div>
                <div class="check-row"><?= $chk('beerdigung') ?> Beerdigungen</div>
                <div class="check-row"><?= $chk('einschulung') ?> Einschulung Kind</div>
                <div class="check-row"><?= $chk('sonstige') ?> anderer persönlicher Anlass</div>
                <div class="check-row"><?= $chk('versorgung') ?> Versorgungsleistungen für Angehörige</div>
            </td>

            <!-- SPALTE 2: Unterrichtsbefreiung -->
            <td style="vertical-align: top; padding: 0;">
                <div style="padding: 3px;">
                    <div class="check-row"><?= $chk('fb_br') ?> Fortbildungen BR **</div>
                    <div class="check-row"><?= $chk('fb_schelf') ?> Fortbildungen SchELF **</div>
                    <div class="check-row"><?= $chk('fb_schilf') ?> Fortbildungen SchiLF</div>
                    <div class="check-row"><?= $chk('allg_br') ?> allg. Einladungen der BR</div>
                    <div class="check-row"><?= $chk('pruefung') ?> Vorprüfungstermine</div>
                    <div class="check-row"><?= $chk('fachberater') ?> Fachberatertermine</div>
                    <div class="check-row"><?= $chk('ihk') ?> IHK-Prüfungen</div>
                    <div class="check-row"><?= $chk('sonstige_unt') ?> sonstige</div>
                </div>
                
                <!-- Der blaue Block Dienstunfallschutz -->
                <div class="th-blue-dark" style="margin-top:5px; border-left:0; border-right:0;">Dienstunfallschutz</div>
                <div style="padding: 3px;">
                    <div class="check-row"><?= $chk('dienstunfall') ?> Dienstveranstaltung ohne Unterrichtsbefreiung</div>
                </div>
            </td>

            <!-- SPALTE 3: Sonderurlaub -->
            <td style="vertical-align: top;">
                <div class="check-row"><?= $chk('jubilaeum') ?> Dienstjubiläen: 25/40/50 Jahre</div>
                <div class="check-row"><?= $chk('geburt_tod') ?> Tod Angehörige/r*<br><span style="padding-left:18px;">(Eltern, Partner*in, Kinder)</span></div>
                <div class="check-row"><?= $chk('niederkunft') ?> Niederkunft der Ehefrau/Lebenspartnerin*</div>
                <div class="check-row"><?= $chk('umzug') ?> Umzug aus dienstlichem Grund</div>
                <div class="check-row"><?= $chk('erkrankung_ange') ?> Schwere Erkrankung Angehörige/r*</div>
                <div class="check-row"><?= $chk('erkrankung_kind') ?> Schwere Erkrankung eines Kindes unter 12 Jahren*</div>
                <div class="check-row"><?= $chk('betreuung') ?> Schwere Erkrankung der Betreuungsperson eines Kindes unter 8 Jahren*</div>
                <div class="check-row"><?= $chk('pol_bildung') ?> Politische Bildung</div>
                <div class="check-row"><?= $chk('sport') ?> internationale Sport-Meisterschaften</div>
                <div class="check-row"><?= $chk('sonstige_dringend') ?> Sonstige dringende Fälle*</div>
            </td>

            <!-- SPALTE 4: Genehmigung (Statisch) -->
            <td style="vertical-align: top; padding-left: 10px;">
                <div style="height:5px;"></div>
                <div class="check-row"><?= $o ?> genehmigt</div>
                <div class="check-row"><?= $o ?> nicht genehmigt</div>
                <div class="check-row"><?= $o ?> bitte Rücksprache</div>
                
                <br>
                Datum: __________________
                <br><br><br>
                <div style="border-top:1px solid black; width: 90%;"> (Unterschrift)</div>
                <br>
                <div style="border-bottom:1px dashed black; width:100%; margin: 5px 0;"></div>
                
                <div>Vertretung geplant:</div>
                <div style="margin-top:5px;">
                    <?= $o ?> ja &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <?= $o ?> nein
                </div>
                
                <br>
                Datum: __________________
                <br><br><br>
                <div style="border-top:1px solid black; width: 90%;"> (Unterschrift)</div>
            </td>
        </tr>
    </table>
    
    <div style="font-weight:bold; font-size:8pt; text-align:center; margin-top:2px;">
        *der schriftliche Antrag kann ggf. nachgereicht werden ** genehmigter Dienstreiseantrag ist beizulegen
    </div>

    <!-- ANTRAGSTELLER DETAILS -->
    <table style="margin-top: 5px;">
        <tr>
            <td width="35%" style="background:#fff;">Antragsteller*in <small>(Name, Vorname)</small></td>
            <td><b><?= $esc('lastname') ?>, <?= $esc('firstname') ?></b></td>
        </tr>
        <tr>
            <td>Befreiungsdatum/ -zeitraum <small>(von – bis)</small></td>
            <td><?= $date_range ?></td>
        </tr>
        <tr>
            <td>von Stunde – bis Stunde <small>(einschließlich)</small></td>
            <td><?= $time_str ?></td>
        </tr>
        <tr>
            <td>weitere beteiligte Kolleginnen und Kollegen in einer Veranstaltung</td>
            <td><?= $esc('colleagues') ?></td>
        </tr>
        <tr>
            <td>Termin kollidiert mit schulinternem Termin -> welchem?</td>
            <td><?= $esc('collision') ?></td>
        </tr>
        <tr>
            <td>Grund für den Antrag, ggf. Anlagen</td>
            <td><?= $esc('reason_text') ?></td>
        </tr>
    </table>

    <div style="font-size:8pt; font-weight:bold; margin:3px 0;">
        Hinweise für die Vertretungsplanung bitte auf der Rückseite ergänzen! Mindestens 14 Tage vorher bei der Schulleitung einreichen!
    </div>

    <!-- UNTERSCHRIFTEN -->
    <table class="no-border" style="margin-top: 15px; width:100%;">
        <tr>
            <td width="30%" style="border-bottom: 1px solid black;">Münster, den <?= date('d.m.Y') ?></td>
            <td width="5%"></td>
            <td width="65%" style="border-bottom: 1px solid black;"></td>
        </tr>
        <tr>
            <td style="font-size:8pt;">Datum</td>
            <td></td>
            <td style="font-size:8pt;">Unterschrift Antragsteller*in</td>
        </tr>
    </table>

    <!-- SCHNITT-LINIE -->
    <div class="cut-line">
        <div class="cut-icon"><?= $scissor ?></div>
    </div>

    <!-- ABSCHNITT UNTEN (QUITTUNG) -->
    <div style="font-weight: bold; margin-bottom: 5px; font-size: 10pt;">
        Abschnitt für Antragsteller*in &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        Name, Vorname __________________________________________
    </div>

    <table style="margin-bottom: 0;">
        <tr>
            <td width="40%">Befreiungsdatum/ -zeitraum <small>(von – bis)</small></td>
            <td width="60%"><b><?= $date_range ?></b></td>
        </tr>
        <tr>
            <td>von Stunde – bis Stunde <small>(einschließlich)</small></td>
            <td><?= $time_str ?></td>
        </tr>
        <tr>
            <td>Grund für den Antrag</td>
            <td>
                <?= $reason_label ?>
                <?php if(!empty($data['reason_text'])) echo ' (' . substr($esc('reason_text'), 0, 30) . '...)'; ?>
            </td>
        </tr>
    </table>

    <table style="margin-top: -1px; width: 100%;">
        <tr class="th-blue">
            <th width="30%">Genehmigungsvermerk</th>
            <th width="20%">Genehmigung</th>
            <th width="35%">Unterschrift</th>
            <th width="15%">Datum</th>
        </tr>
        <tr>
            <td style="height:30px;">Antrag an die Schulleitung:</td>
            <td>ja &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp; nein</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <!-- SEITE 2: VERTRETUNG -->
    <div style="page-break-before: always;"></div>
    
    <!-- Logo Wiederholung oder Header -->
    <div style="font-weight:bold; font-size:12pt; margin-bottom:10px;">Rückseite: Hinweise zur Vertretungsplanung</div>

    <table style="margin-top: 10px;">
        <tr class="th-blue">
            <th width="20%">Datum</th>
            <th width="20%">Lerngruppe</th>
            <th width="10%">Stunde</th>
            <th width="50%">Vertretungshinweise</th>
        </tr>
        <?php 
        $rows = $data['sub_rows'] ?? [];
        $rows_to_print = max(count($rows), 10); 
        for($i = 0; $i < $rows_to_print; $i++): 
            $r = $rows[$i] ?? [];
            $d_date = !empty($r['date']) ? date('d.m. y', strtotime($r['date'])) : '';
        ?>
        <tr>
            <td style="height: 28px;"><?= $d_date ?></td>
            <td><?= htmlspecialchars($r['group'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['hour'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['info'] ?? '') ?></td>
        </tr>
        <?php endfor; ?>
    </table>

</body>
</html>