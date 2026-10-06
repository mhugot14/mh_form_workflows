<?php
/**
 * View: PDF - Weiteres (3., 4., …) Pädagogisches Gespräch mit Schüler/-in.
 * Gleiches Layout wie das 1. Gespräch, nur mit fortlaufender Nummer im Titel.
 *
 * @var array $data Case-Metadaten + Schritt-Daten zusammengeführt.
 * @var array $step Der aktuelle Schritt (aus Fall_Controller::handle_download_step_pdf()).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Laufende Nummer = Anzahl aller Gesprächs-Schritte bis einschließlich diesem.
$gespraech_no = 0;
foreach ( $data['steps'] ?? [] as $s ) {
	if ( str_starts_with( (string) $s['type'], 'gespraech_' ) && (int) $s['step_no'] <= (int) ( $step['step_no'] ?? PHP_INT_MAX ) ) {
		$gespraech_no++;
	}
}

$pdf_title    = $gespraech_no >= 3 ? $gespraech_no . '. Pädagogisches Gespräch mit Schüler/-in' : 'Weiteres Pädagogisches Gespräch mit Schüler/-in';
$pdf_subtitle = 'Optionales weiteres Gespräch nach dem 2. Pädagogischen Gespräch';

include MH_FW_PLUGIN_DIR . 'templates/pdf-absentismus-gespraech_1.php';
