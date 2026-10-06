<?php
/**
 * PDF-Ersatzinhalt für gespraech_1/gespraech_2/gespraech_weiteres, wenn das Gespräch nicht im
 * Formular, sondern extern (WebUntis/handschriftlich) dokumentiert wurde.
 * Wird nach der Überschrift des jeweiligen PDF-Templates eingebunden; das
 * aufrufende Template beendet sich danach per return.
 * Nutzt $esc/$date_fmt aus pdf-header.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$doku_ort = 'webuntis' === ( $data['dokumentation'] ?? '' ) ? 'in WebUntis' : 'handschriftlich';
?>
<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<p>
	Datum des Gesprächs: <b><?= $date_fmt( 'datum' ) ?></b>
</p>

<p>
	Das Gespräch wurde geführt und ist <b><?= esc_html( $doku_ort ) ?></b> dokumentiert.
	Inhalte, Ergebnisse und Vereinbarungen sind der dortigen Dokumentation zu entnehmen.
</p>
