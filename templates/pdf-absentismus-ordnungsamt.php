<?php
/**
 * View: PDF - Zuführung durch das Ordnungsamt für schulpflichtige SuS.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Zuführung durch das Ordnungsamt für schulpflichtige SuS</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<div class="section-title">Bedingung:</div>
<p>Versuchte Kontaktaufnahme und 3 Tage am Stück unentschuldigt.<br>
<span class="small-text">Anlage: Auszug aus WebUntis mindestens der letzten drei Schultage.</span></p>

<div class="section-title">Auszufüllen vom Sekretariat:</div>
<p>
	Information des Jugendamtes nach § 41 Abs. 4<br>
	Bezirkssozialarbeiterin: <b><?= $esc( 'bezirkssozialarbeiterin' ) ?></b><br>
	Telefon: <b><?= $esc( 'telefon' ) ?></b>
</p>
<p>Zuführung geplant am: <b><?= $date_fmt( 'zufuehrung_geplant_am' ) ?></b></p>
<p>
	Zuführung erfolgreich:<br>
	<?= 'ja' === ( $data['zufuehrung_erfolgreich'] ?? '' ) ? $x : $o ?> Ja<br>
	<?= 'nein' === ( $data['zufuehrung_erfolgreich'] ?? '' ) ? $x : $o ?> Nein. Grund: <?= $esc( 'zufuehrung_grund' ) ?>
</p>
