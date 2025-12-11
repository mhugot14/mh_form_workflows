<?php
/**
 * View: PDF Protokoll Anhang (Seite 2 & 3 des Gesamt-PDFs)
 */

$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$val_underline = fn($field) => !empty($data[$field]) ? '<b>'.htmlspecialchars($data[$field]).'</b>' : '........................................';

// Helper für Datum
$date_fmt = function($field) use ($data) {
    $val = $data[$field] ?? ''; 
    return empty($val) ? '................' : date('d.m.Y', strtotime($val));
};

// Logik für den Untertitel (Punkt 3)
$subtitle = '';
if ( isset($data['certificate']) && $data['certificate'] === 'ueberweisung' ) {
    $subtitle = 'Überweisungszeugnis';
} elseif ( isset($data['certificate']) && $data['certificate'] === 'abgang' ) {
    $subtitle = 'Abgangszeugnis';
} else {
    $subtitle = 'Zeugnis'; 
}
?>

<!-- Neuer Start auf neuer Seite -->
<div style="page-break-before: always;"></div>

<!-- Seite 1 Label (Punkt 4) -->
<div style="text-align: right; font-size: 10pt; margin-bottom: 10px;">Seite 1</div>

<!-- Titel (Punkt 1 & 2: Kein Logo, keine Unterstreichung) -->
<div style="font-weight: bold; font-size: 14pt; margin-bottom: 5px;">
    Ergebnisprotokoll der Zeugniskonferenz Berufsschule
</div>

<!-- Untertitel (Punkt 3: Dynamisch) -->
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
</table>

<!-- Notentabelle (Punkt 5: Gekürzt auf 11 Zeilen) -->
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

<!-- Beschlussfassung (Punkt 6) -->
<div style="font-weight: bold; margin-bottom: 5px;">Beschlussfassung:</div>
<p style="margin: 0 0 5px 0;">Die Noten der Lernfeldfächer/Fächer wurden verglichen und festgestellt (s. Notenliste).</p>
<p style="margin: 0 0 5px 0;">Folgende Bemerkungen wurden beschlossen:</p>

<!-- HIER IST DIE ÄNDERUNG: Ausgabe der Bemerkungen aus dem Textfeld -->
<div style="border: 1px solid #999; padding: 8px; min-height: 60px; margin-bottom: 20px; font-size: 10pt; background-color: #fafafa;">
    <?php 
        // nl2br sorgt dafür, dass Zeilenumbrüche im Textfeld auch im PDF neue Zeilen sind
        $remarks = $esc('prot_remarks');
        echo empty($remarks) ? '&nbsp;' : nl2br($remarks);
    ?>
</div>

<!-- (Punkt 7: Bereich Berufsschulabschluss wurde entfernt) -->

<!-- Unterschriften (Punkt 8, 9, 10) -->
<table class="no-border" style="width:100%; margin-top: 30px;">
    <tr>
        <!-- Punkt 8: Datum heute -->
        <td class="no-border" width="50%" style="vertical-align: bottom; padding-bottom: 20px;">
            Münster, den <?= $date_fmt('prot_date') ?>
        </td>
        <!-- Punkt 8: Klassenleitung mit Slash + Kürzel -->
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; float:right; padding-top:2px;">
                Klassenleitung / <?= $esc('teacher') ?>
            </div>
        </td>
    </tr>
    <tr><td colspan="2" class="no-border" height="30"></td></tr>
    <tr>
        <!-- Punkt 9: Protokoll mit Slash + Kürzel (Wiederverwendung teacher) -->
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; padding-top:2px;">
                Protokoll / <?= $esc('teacher') ?>
            </div>
        </td>
        <!-- Punkt 10: Vorsitz mit Slash + Name -->
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 90%; float:right; padding-top:2px;">
                Vorsitz / <?= $esc('prot_chair') ?>
            </div>
        </td>
    </tr>
</table>