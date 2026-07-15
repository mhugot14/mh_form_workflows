<?php
/**
 * View: PDF - Schriftliche Mahnung (Verweis) / Aufforderung des Schulbesuchs.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Schriftliche Mahnung (Verweis) / Aufforderung des Schulbesuchs</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<div class="section-title">Bedingungen:</div>
<p>Nach den pädagogischen Gesprächen zeigt sich keine Verhaltensänderung.</p>

<p>
	Anzahl der Fehlstunden gesamt: <b><?= $esc( 'fehlstunden_gesamt' ) ?></b><br>
	Anzahl der unentschuldigten Fehlstunden: <b><?= $esc( 'fehlstunden_unentschuldigt' ) ?></b><br>
	Abteilungsleitung informiert: Paraphe AL: <b><?= $esc( 'paraphe_al' ) ?></b>
</p>

<div class="section-title">Anlage:</div>
<p>
	<?= '1' === ( $data['anlage_webuntis'] ?? '' ) ? $x : $o ?> aktueller Auszug WebUntis<br>
	<?= '1' === ( $data['anlage_protokoll'] ?? '' ) ? $x : $o ?> Protokoll der pädagogischen Gespräche
</p>

<div class="section-title">Vom Sekretariat auszufüllen:</div>
<p>Versand der schriftlichen Mahnung (Verweis) / Aufforderung des Schulbesuchs: <b><?= $date_fmt( 'versand_datum' ) ?></b></p>
