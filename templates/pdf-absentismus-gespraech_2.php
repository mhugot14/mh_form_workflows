<?php
/**
 * View: PDF - 2. Pädagogisches Gespräch mit Schüler/-in.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">2. Pädagogisches Gespräch mit Schüler/-in</div>
<div class="subheader">Bedingungen: Vereinbarungen aus dem 1. Pädagogischen Gespräch wurden nicht eingehalten bzw. weitere Fehlstunden sind hinzugekommen.</div>

<p>
	Datum des 2. Pädagogischen Gesprächs: <b><?= $date_fmt( 'einladung_datum' ) ?></b><br>
	Uhrzeit: <b><?= $esc( 'einladung_uhrzeit' ) ?></b>
</p>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<p>Vom Sekretariat auszufüllen: Versand der Einladung: <b><?= $date_fmt( 'versand_einladung' ) ?></b></p>

<div class="section-title">Gesprächsprotokoll</div>
<table style="margin-bottom: 10px;">
	<tr>
		<td width="50%">Datum: <b><?= $date_fmt( 'datum' ) ?></b></td>
		<td width="50%">Uhrzeit: <b><?= $esc( 'uhrzeit_von' ) ?></b> bis <b><?= $esc( 'uhrzeit_bis' ) ?></b></td>
	</tr>
	<tr>
		<td colspan="2">
			Ort: <?= 'schule' === ( $data['ort'] ?? '' ) ? $x : $o ?> Schule &nbsp;&nbsp; <?= 'telefonat' === ( $data['ort'] ?? '' ) ? $x : $o ?> Telefonat
		</td>
	</tr>
</table>

<div class="section-title">Neben Klassenlehrer und Schüler nahmen teil:</div>
<table style="margin-bottom: 10px;">
	<tr>
		<td width="50%">Abteilungsleitung: <b><?= $esc( 'abteilungsleitung' ) ?></b></td>
		<td width="50%">Schulsozialarbeit: <b><?= $esc( 'schulsozialarbeit' ) ?></b></td>
	</tr>
	<tr>
		<td>Erziehungsberechtigte: <b><?= $esc( 'erziehungsberechtigte_1' ) ?></b></td>
		<td>Erziehungsberechtigte: <b><?= $esc( 'erziehungsberechtigte_2' ) ?></b></td>
	</tr>
	<tr>
		<td colspan="2">Weitere: <b><?= $esc( 'weitere' ) ?></b></td>
	</tr>
</table>

<div class="section-title">Anlass des Gesprächs:</div>
<p>
	Anzahl der Fehlstunden gesamt: <b><?= $esc( 'fehlstunden_gesamt' ) ?></b><br>
	Anzahl der unentschuldigten Fehlstunden: <b><?= $esc( 'fehlstunden_unentschuldigt' ) ?></b>
</p>

<div class="section-title">Inhalte des Gesprächs:</div>
<p><?= nl2br( $esc( 'inhalte' ) ) ?></p>

<div class="section-title">Ergebnisse/Vereinbarungen:</div>
<p><?= nl2br( $esc( 'ergebnisse' ) ) ?></p>
<p>
	<?= '1' === ( $data['ankuendigung_attestpflicht'] ?? '' ) ? $x : $o ?> Ankündigung einer möglichen Attestpflicht (bei krankheitsbedingtem Fehlen)<br>
	<?= '1' === ( $data['kontakt_sozialarbeit'] ?? '' ) ? $x : $o ?> Kontaktaufnahme mit der Schulsozialarbeit
</p>

<p>Überprüfen der Vereinbarungen am: <b><?= $date_fmt( 'ueberpruefung_am' ) ?></b></p>
