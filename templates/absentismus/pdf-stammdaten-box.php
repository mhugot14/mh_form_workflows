<?php
/**
 * Gemeinsame Stammdaten-Box (Name/geb.am/Klasse/Kürzel), wie sie auf den
 * Seiten 2-8 der Vorlage erscheint. Nutzt $esc/$x/$o aus pdf-header.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<table style="margin-bottom: 10px;">
	<tr>
		<td width="60%">Name Schüler/in:<br><b><?= $esc( 'lastname' ) ?>, <?= $esc( 'firstname' ) ?></b></td>
		<td width="40%">Klasse:<br><b><?= $esc( 'class_name' ) ?></b></td>
	</tr>
	<tr>
		<td>
			geb. am: <b><?= $date_fmt( 'dob' ) ?></b>
			&nbsp;&nbsp; <?= ! empty( $data['is_minor'] ) ? $x : $o ?> minderjährig
			&nbsp;&nbsp; <?= ! empty( $data['is_schulpflichtig'] ) ? $x : $o ?> schulpflichtig
		</td>
		<td>Klassenleitung:<br><b><?= $esc( 'teacher' ) ?></b></td>
	</tr>
</table>
