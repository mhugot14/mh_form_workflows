<?php
/**
 * View: PDF Protokoll Anhang (Seite 2 & 3)
 */

$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>'; 
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>'; 
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
$date_fmt = function($field) use ($data) {
    $val = $data[$field] ?? ''; return empty($val) ? '................' : date('d.m.Y', strtotime($val));
};
$val_underline = fn($field) => !empty($data[$field]) ? '<b>'.htmlspecialchars($data[$field]).'</b>' : '........................................';

// Header Logic
$title = ($data['prot_type'] === 'vollzeit') 
    ? 'Ergebnisprotokoll der Zeugniskonferenz Vollzeit<br><span style="font-size:12pt; font-weight:normal;">Abgang / Überweisung</span>' 
    : 'Ergebnisprotokoll der Zeugniskonferenz Berufsschule<br><span style="font-size:12pt; font-weight:normal;">Halbjahr / Abschluss / Abgang</span>';

// CSS Page Break
?>
<div style="page-break-before: always;"></div>

<!-- Header -->
<div style="font-weight: bold; font-size: 14pt; text-align: left; margin-bottom: 20px;">
    Ludwig-Erhard-Berufskolleg<br>Münster
</div>
<div class="header" style="text-decoration: underline; margin-bottom: 30px;"><?= $title ?></div>

<!-- Stammdaten Zeilen -->
<table class="no-border" style="width:100%; margin-bottom: 20px;">
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
        <td class="no-border">Ausgabedatum: ........................................</td>
    </tr>

    <?php if($data['prot_type'] === 'vollzeit'): ?>
    <tr>
        <td class="no-border">Ende des Schulverhältnisses: <?= $date_fmt('prot_end_school') ?></td>
        <td class="no-border">
            Schulpflicht überprüft? 
            <?= ($data['prot_check_comp'] === '1' ? $x : $o) ?> Ja  
            <?= ($data['prot_check_comp'] !== '1' ? $x : $o) ?> Nein
        </td>
    </tr>
    <tr>
        <td class="no-border" colspan="2">Er/Sie wird an folgende Schule überwiesen: <?= $val_underline('prot_transfer') ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- Notentabelle (Leergerüst für manuelle oder spätere Eintragung) -->
<table style="margin-top: 10px; border: 1px solid black;">
    <tr style="background:#eee; font-weight:bold;">
        <th width="30%">Fach</th>
        <th width="20%">Lehrkraft</th>
        <th width="10%">Note</th>
        <th width="10%">Fach<br>vorher<br>abg.?</th>
        <th width="30%">Unterschrift Fachlehrer<br><small>bei vorher abgeschlossenem Fach: Unterschrift Klassenlehrer</small></th>
    </tr>
    <?php for($i=0; $i<14; $i++): ?>
    <tr>
        <td style="height: 25px;">&nbsp;</td>
        <td></td><td></td><td></td><td></td>
    </tr>
    <?php endfor; ?>
</table>

<div style="text-align: center; font-size: 10pt; margin-top: 10px;">Seite 2</div>

<!-- ================= SEITE 3 ================= -->
<div style="page-break-before: always;"></div>

<div style="text-align: center; margin-bottom: 20px;">Seite 3</div>
<div style="font-weight: bold; text-decoration: underline; margin-bottom: 15px; font-size:12pt;"><?= $title ?></div>

<div style="font-weight: bold; margin-bottom: 5px;">Beschlussfassung:</div>
<p>Die Noten der Lernfeldfächer/Fächer wurden verglichen und festgestellt (s. Notenliste).</p>
<p>Folgende Bemerkungen wurden beschlossen:</p>
<div style="border-bottom: 1px solid black; margin-bottom: 25px; height: 20px;"></div>
<div style="border-bottom: 1px solid black; margin-bottom: 25px; height: 20px;"></div>
<div style="border-bottom: 1px solid black; margin-bottom: 40px; height: 20px;"></div>

<?php if($data['prot_type'] === 'berufsschule'): ?>
    <div style="font-weight: bold;">Für Berufsschulabschluss:</div>
    <p>Folgende Schüler haben den Berufsschulabschluss <u>nicht</u> erreicht wg. einer ungenügenden oder wegen nicht ausreichenden Leistungen in zwei und mehr Fächern:</p>
    
    <table style="margin-bottom: 40px;">
        <tr>
            <th width="40%">Name, Vorname</th>
            <th width="30%">Fächer</th>
            <th width="30%">Nachprüfung möglich<br>Ja &nbsp;&nbsp;&nbsp; Nein</th>
        </tr>
        <!-- Da es hier nur um EINEN Schüler geht, tragen wir ihn vor ein, oder leer lassen? 
             Prompt impliziert Einzelfall. Wir lassen Platz für manuelle Ergänzung. -->
        <tr><td height="30">&nbsp;</td><td></td><td></td></tr>
        <tr><td height="30">&nbsp;</td><td></td><td></td></tr>
        <tr><td height="30">&nbsp;</td><td></td><td></td></tr>
    </table>
<?php endif; ?>

<!-- Unterschriften -->
<table class="no-border" style="margin-top: 50px;">
    <tr>
        <td class="no-border" width="50%">Münster, den ........................................</td>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 80%; float:right;">Klassenleitung</div>
        </td>
    </tr>
    <tr>
        <td class="no-border" colspan="2" style="height: 30px;"></td>
    </tr>
    <tr>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 80%;">Protokoll</div>
        </td>
        <td class="no-border" width="50%" style="vertical-align: bottom;">
            <div style="border-top: 1px solid black; width: 80%; float:right;">Vorsitz</div>
        </td>
    </tr>
</table>