<?php
/**
 * View: PDF - Einleitung Bußgeldverfahren / Anhörung.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Einleitung Bußgeldverfahren / Anhörung</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<div class="section-title">Bedingungen:</div>
<p>
	- nach den pädagogischen Gesprächen zeigt sich keine Verhaltensänderung<br>
	- schriftliche Aufforderung des Schulbesuchs ist erfolgt<br>
	- weiteres erzieherisches Einwirken war erfolglos
</p>

<div class="section-title">Anlage:</div>
<p><?= '1' === ( $data['anlage_webuntis'] ?? '' ) ? $x : $o ?> aktueller Auszug WebUntis</p>

<div class="section-title">Vom Sekretariat auszufüllen:</div>
<p>
	Versand des Anhörungsbogens: <b><?= $date_fmt( 'versand_anhoerungsbogen' ) ?></b><br>
	<?= '1' === ( $data['mail_bezirksregierung'] ?? '' ) ? $x : $o ?> Mail an die Bezirksregierung: <b><?= $date_fmt( 'mail_bezirksregierung_datum' ) ?></b>
</p>
