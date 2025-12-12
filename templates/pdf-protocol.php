<?php
/**
 * View: PDF Protokoll Anhang (Seite 2 & 3 des Gesamt-PDFs)
 */

$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$val_underline = fn($field) => !empty($data[$field]) ? '<b>'.htmlspecialchars($data[$field]).'</b>' : '........................................';

// Checkbox-Symbole
$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>'; 

// Helper für Datum
$date_fmt = function($field) use ($data) {
    $val = $data[$field] ?? ''; 
    return empty($val) ? '................' : date('d.m.Y', strtotime($val));
};

// 1. Logik für den Haupttitel (Vollzeit vs. Berufsschule)
$is_vollzeit = (isset($data['prot_type']) && $data['prot_type'] === 'vollzeit');
$main_title = $is_vollzeit 
    ? 'Ergebnisprotokoll der Zeugniskonferenz Vollzeit' 
    : 'Ergebnisprotokoll der Zeugniskonferenz Berufsschule';

// 2. Logik für den Untertitel (Zeugnisart)
$subtitle = '';
if ( isset($data['certificate']) && $data['certificate'] === 'ueberweisung' ) {
    $subtitle = 'Überweisungszeugnis';
} elseif ( isset($data['certificate']) && $data['certificate'] === 'abgang' ) {
    $subtitle = 'Abgangszeugnis';
} else {
    $subtitle = 'Zeugnis'; 
}

// 3. Datenaufbereitung für Vollzeit-Zusatzzeilen
if ($is_vollzeit) {
    // Ende des Verhältnisses = Abmeldedatum
    $end_date_str = $date_fmt('date_off'); 
    
    // Schulpflicht Checkboxen aus Daten ableiten
    $comp_yes = ($data['compulsory'] === 'fulfilled') ? $x : $o;
    $comp_no  = ($data['compulsory'] !== 'fulfilled') ? $x : $o; // Checkt "nicht erfüllt", "AV", "Bildungsgang" als 'nein' oder separate Logik?
    // Alternativ: Wenn explizit 'not_fulfilled' gewählt, dann Nein. Bei AV/Bildungsgang ist sie meist auch erfüllt oder wird erfüllt?
    // Im Zweifel: Nur 'fulfilled' ist "Ja".
    
    // Überwiesen an: Nur wenn Grund "Schulwechsel" ist und Schule angegeben wurde
    $transfer_school = '-';
    if (($data['reason'] ?? '') === 'schulwechsel' && !empty($data['new_school'])) {
        $transfer_school = $data['new_school'];
    }
}
?>

<!-- Neuer Start auf neuer Seite -->
<div style="page-break-before: always;"></div>

<!-- Seite 1 Label -->
<div style="text-align: right; font-size: 10pt; margin-bottom: 10px;">Seite 1</div>

<!-- Titel (Dynamisch nach Typ) -->
<div style="font-weight: bold; font-size: 14pt; margin-bottom: 5px;">
    <?= $main_title ?>
</div>

<!-- Untertitel (Dynamisch nach Zeugnisart) -->
<div style="font-size: 12pt; margin-bottom: 25px;">
    <?= $subtitle ?>
</div>

<!-- Stammdaten Tabelle -->
<table class="no-border" style="width:100%; margin-bottom: 15px;">
    <tr>
        <td class="no-border" colspan="2">Schülername, Vorname: <b><?= $esc('lastname') ?>, <?= $esc('firstname') ?></b></td>
    </tr>
    <tr>
        <td class="no-border" width="50%">Klasse: <b><?= $esc('class_name') ?></b></td>
        <td class="no-border" width="50%">Klassenlehrer/in: <b><?= $esc('teacher') ?></b></td>
    </tr>
    <tr>
        <td class="no-border">Konferenzdatum: <?= $date_fmt('prot_date') ?></td>
        <td class="no-border">Vorsitzende/r: <?= $val_underline('prot_chair') ?></td>
    </tr>
    <tr>
        <td class="no-border">Raum: <?= $val_underline('prot_room') ?></td>
        <td class="no-border">Ausgabedatum: <?= $date_fmt('prot_issue_date') ?></td>
    </tr>

    <!-- ZUSATZZEILEN NUR FÜR VOLLZEIT -->
    <?php if ($is_vollzeit): ?>
    <tr>
        <td class="no-border">Ende des Schulverhältnisses: <b><?= $date_fmt('prot_issue_date') ?></b></td>
        <td class="no-border">
            Schulpflicht erfüllt? <?= $comp_yes ?> Ja &nbsp; <?= $comp_no ?> Nein
        </td>
    </tr>
    <tr>
        <td class="no-border" colspan="2">
            Er/Sie wird an folgende Schule überwiesen: <b><?= htmlspecialchars($transfer_school) ?></b>
        </td>
    </tr>
    <?php endif; ?>
    <!-- ENDE ZUSATZZEILEN -->

</table>

<!-- Notentabelle (11 Zeilen) -->
<table style="width: 100%; border-collapse: collapse; border: 1px solid black; margin-bottom: 15px;">
    <tr style="background:#eee; font-weight:bold;">
        <th style="border:1px solid black; padding:4px;" width="30%">Fach</th>
        <th style="border:1px solid black; padding:4px;" width="20%">Lehrkraft</th>
        <th style="border:1px solid black; padding:4px;" width="10%">Note</th>
        <th style="border:1px solid black; padding:4px;" width="10%">Fach<br>vorher<br>abg.?</th>
        <th style="border:1px solid black; padding:4px;" width="30%">Unterschrift Fachlehrer<br><small>bei vorher abgeschlossenem Fach: Unterschrift Klassenlehrer</small></th>
    </tr>
    <?php for($i=0; $i<11; $i++): ?>
    <tr>
        <td style="border:1px solid black; height: 22px;">&nbsp;</td>
        <td style="border:1px solid black;"></td>
        <td style="border:1px solid black;"></td>
        <td style="border:1px solid black;"></td>
        <td style="border:1px solid black;"></td>
    </tr>
    <?php endfor; ?>
</table>

<!-- Beschlussfassung -->
<div style="font-weight: bold; margin-bottom: 5px;">Beschlussfassung:</div>
<p style="margin: 0 0 5px 0;">Die Noten der Lernfeldfächer/Fächer wurden verglichen und festgestellt (s. Notenliste).</p>
<p style="margin: 0 0 5px 0;">Folgende Bemerkungen wurden beschlossen:</p>

<!-- Bemerkungen -->
<div style="border: 1px solid #999; padding: 8px; min-height: 60px; margin-bottom: 20px; font-size: 10pt; background-color: #fafafa;">
    <?php 
        $remarks = $esc('prot_remarks');
        echo empty($remarks) ? '&nbsp;' : nl2br($remarks);
    ?>
</div>

<!-- Unterschriften -->
<table class="no-border" style="width:100%; margin-top: 30px;">
    <tr>
        <td class="no-border" width="50%" style="vertical-align: bottom; padding-bottom: 20px;">
            Münster, den <?= $date_fmt('prot_date') ?>
        </td>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; float:right; padding-top:2px;">
                Klassenleitung / <?= $esc('teacher') ?>
            </div>
        </td>
    </tr>
    <tr><td colspan="2" class="no-border" height="30"></td></tr>
    <tr>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; padding-top:2px;">
                Protokoll / <?= $esc('teacher') ?>
            </div>
        </td>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; float:right; padding-top:2px;">
                Vorsitz / <?= $esc('prot_chair') ?>
            </div>
        </td>
    </tr>
</table>