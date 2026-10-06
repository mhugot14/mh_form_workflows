<?php
/**
 * View: PDF - 1. Pädagogisches Gespräch mit Schüler/-in.
 * Wird auch von pdf-absentismus-gespraech_weiteres.php eingebunden, das dazu
 * $pdf_title/$pdf_subtitle vorbelegt.
 *
 * @var array $data Case-Metadaten + Schritt-Daten zusammengeführt.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';

$pdf_title    = $pdf_title ?? '1. Pädagogisches Gespräch mit Schüler/-in';
$pdf_subtitle = $pdf_subtitle ?? 'Bedingung: Faustregel nach 10 versäumten unentschuldigten Unterrichtsstunden';
?>

<div class="header"><?= esc_html( $pdf_title ) ?></div>
<div class="subheader"><?= esc_html( $pdf_subtitle ) ?></div>

<?php
if ( in_array( $data['dokumentation'] ?? '', [ 'webuntis', 'handschriftlich' ], true ) ) {
	include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-extern-dokumentiert.php';
	return;
}
?>

<table style="margin-bottom: 10px;">
	<tr>
		<td width="50%">Datum: <b><?= $date_fmt( 'datum' ) ?></b></td>
		<td width="50%">Uhrzeit: <b><?= $esc( 'uhrzeit_von' ) ?></b> bis <b><?= $esc( 'uhrzeit_bis' ) ?></b></td>
	</tr>
	<tr>
		<td>Klassenlehrer: <b><?= $esc( 'teacher' ) ?></b></td>
		<td>Schüler: <b><?= $esc( 'lastname' ) ?>, <?= $esc( 'firstname' ) ?></b></td>
	</tr>
	<tr>
		<td colspan="2">
			Ort: <?= 'schule' === ( $data['ort'] ?? '' ) ? $x : $o ?> Schule &nbsp;&nbsp; <?= 'telefonat' === ( $data['ort'] ?? '' ) ? $x : $o ?> Telefonat
		</td>
	</tr>
	<tr>
		<td>Schulsozialarbeit: <b><?= $esc( 'schulsozialarbeit' ) ?></b></td>
		<td>Weitere: <b><?= $esc( 'weitere' ) ?></b></td>
	</tr>
	<tr>
		<td colspan="2">Erziehungsberechtigte: <b><?= nl2br( $esc( 'erziehungsberechtigte' ) ) ?></b></td>
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

<p>Überprüfen der Vereinbarungen am: <b><?= $date_fmt( 'ueberpruefung_am' ) ?></b></p>

<table class="no-border" style="margin-top: 30px;">
	<tr>
		<td style="border-top: 1px solid black; padding-top: 4px;">Klassenlehrer/in</td>
		<td style="border-top: 1px solid black; padding-top: 4px;">Schüler/in</td>
		<td style="border-top: 1px solid black; padding-top: 4px;">ggf. Erziehungsberechtigte</td>
	</tr>
</table>
