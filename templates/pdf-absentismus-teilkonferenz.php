<?php
/**
 * View: PDF - Teilkonferenz.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Teilkonferenz</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<p>
	<?= 'beendigung_53' === ( $data['trigger'] ?? '' ) ? $x : $o ?> Mögliche Beendigung Schulverhältnis nach § 53 Abs. Nr. 3, 4, 7 SchulG<br>
	<span class="small-text">(20 unentschuldigte Stunden innerhalb von 30 Tagen, seit der letzten Maßnahme)</span>
</p>
<p>
	<?= 'hohe_fehlstunden' === ( $data['trigger'] ?? '' ) ? $x : $o ?> Hohe unentschuldigte Fehlstunden
</p>

<p>
	Zeitraum: von <b><?= $date_fmt( 'zeitraum_von' ) ?></b> bis <b><?= $date_fmt( 'zeitraum_bis' ) ?></b><br>
	Anzahl der unentschuldigten Fehlstunden: <b><?= $esc( 'fehlstunden_unentschuldigt' ) ?></b><br>
	Anlage: WebUntis-Auszug der Fehlzeiten
</p>

<div class="section-title">Beschluss der Teilkonferenz:</div>
<p>
	<?php
	$beschluss_labels = [
		'androhung_entlassung' => 'Androhung der Entlassung (§ 53 Abs. 3 Nr. 4)',
		'entlassung'           => 'Entlassung (§ 53 Abs. 3 Nr. 5 i. V. m. Abs. 4)',
	];
	$beschluss = $data['beschluss'] ?? '';
	echo $beschluss !== '' ? esc_html( $beschluss_labels[ $beschluss ] ?? $beschluss ) : 'noch offen';
	?>
</p>
