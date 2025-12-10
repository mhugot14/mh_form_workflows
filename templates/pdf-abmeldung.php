<?php
// Helper für Checkboxen im PDF
$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>'; // Checked box symbol
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>'; // Empty box symbol

$chk = fn($field, $val) => ($data[$field] ?? '') === $val ? $x : $o;
$esc = fn($field) => htmlspecialchars($data[$field] ?? '');
?>
<html>
<head>
<style>
    body { font-family: Helvetica, sans-serif; font-size: 11pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    td, th { border: 1px solid black; padding: 5px; vertical-align: top; }
    .header { font-weight: bold; font-size: 14pt; text-align: center; margin-bottom: 20px; }
    .bg-gray { background-color: #eee; }
    .no-border { border: none; }
    .section-num { font-weight: bold; font-size: 16pt; width: 30px; text-align: center; }
</style>
</head>
<body>

    <div class="header">Abmeldung von Schülerinnen und Schülern</div>

    <!-- Stammdaten -->
    <table>
        <tr>
            <td width="50%">Name:<br><b><?= $esc('lastname') ?></b></td>
            <td width="25%">Geburtsdatum:<br><b><?= $esc('dob') ?></b></td>
            <td width="25%">Klasse:<br><b><?= $esc('class_name') ?></b></td>
        </tr>
        <tr>
            <td>Vorname:<br><b><?= $esc('firstname') ?></b></td>
            <td colspan="2">
                <?= ($data['is_minor'] ? $x : $o) ?> Minderjährig &nbsp;
                <?= (!$data['is_minor'] ? $x : $o) ?> Volljährig
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td width="50%" class="bg-gray"><b>Datum der Abmeldung:</b></td>
            <td><?= $esc('date_off') ?></td>
        </tr>
    </table>

    <div style="font-weight: bold; margin-top: 10px;">Grund der Abmeldung:</div>
    <table>
        <tr>
            <td rowspan="5" class="section-num">1</td>
            <td><?= $chk('reason', 'schulwechsel') ?> Schulwechsel (Name/Ort): <b><?= $esc('new_school') ?></b></td>
        </tr>
        <tr><td><?= $chk('reason', 'aufloesung') ?> Auflösung Ausbildungsvertrag</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_beschluss') ?> Ausschulung Beschluss Teilkonferenz</td></tr>
        <tr><td><?= $chk('reason', 'ausschulung_47') ?> Ausschulung §47 Abs 1</td></tr>
        <tr><td><?= $chk('reason', 'abmeldung') ?> Abmeldung</td></tr>
    </table>

    <div style="font-weight: bold; margin-top: 10px;">Schulpflicht:</div>
    <table>
        <tr>
            <td rowspan="2" class="section-num">2</td>
            <td><?= $chk('compulsory', 'fulfilled') ?> Die Schulpflicht ist erfüllt.</td>
        </tr>
        <tr>
            <td><?= $chk('compulsory', 'not_fulfilled') ?> Die Schulpflicht ist NICHT erfüllt.</td>
        </tr>
    </table>

    <div style="font-weight: bold; margin-top: 10px;">Zeugnis:</div>
    <table>
        <tr>
            <td rowspan="2" class="section-num">3</td>
            <td>
                <?= $chk('certificate', 'ueberweisung') ?> Überweisungszeugnis gem. § 49 SchulG<br>
                <div style="text-align: right;">
                    Fehlstunden: <b><?= $esc('missed_hours') ?></b> davon unentschuldigt: <b><?= $esc('missed_ue') ?></b>
                </div>
            </td>
        </tr>
        <tr>
            <td><?= $chk('certificate', 'abgang') ?> Abgangszeugnis gem. § 49 SchulG</td>
        </tr>
    </table>

    <br>
    <table>
        <tr class="bg-gray">
            <td width="30%">Ablauf</td>
            <td width="20%">Datum</td>
            <td width="50%">Unterschrift</td>
        </tr>
        <tr>
            <td>Klassenleitung</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Abteilungsleitung</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>WebUntis</td>
            <td></td>
            <td></td>
        </tr>
         <tr>
            <td>Schild</td>
            <td></td>
            <td></td>
        </tr>
    </table>

</body>
</html>