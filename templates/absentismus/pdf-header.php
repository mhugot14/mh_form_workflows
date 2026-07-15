<?php
/**
 * Gemeinsamer PDF-Kopf für die 8 Absentismus-PDF-Templates. Öffnet
 * <html><head>/<style>/</head><body> + Footer. Der Controller hängt
 * </body></html> nach dem ob_get_clean() an (wie bei pdf-abmeldung.php).
 *
 * Stellt für die einzelnen Templates zur Verfügung:
 * @var callable $esc      esc_html-Helfer für $data-Felder
 * @var callable $date_fmt Datumsformatierung für $data-Felder
 * @var string   $x        Checkbox "angekreuzt"
 * @var string   $o        Checkbox "leer"
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$esc = fn( $field ) => htmlspecialchars( (string) ( $data[ $field ] ?? '' ) );
$date_fmt = function ( $field ) use ( $data ) {
	$val = $data[ $field ] ?? '';
	return empty( $val ) ? '' : date( 'd.m.Y', strtotime( $val ) );
};
$x = '<span style="font-family: DejaVu Sans, sans-serif;">&#9746;</span>';
$o = '<span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span>';
?>
<html>
<head>
<style>
<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-style.php'; ?>
</style>
</head>
<body>

<div id="footer">Ludwig-Erhard-Berufskolleg Münster<?= ! empty( $case_id ) ? ' | Absentismus-Fall #' . (int) $case_id : '' ?> | <?= date( 'd.m.Y' ) ?></div>
