<?php
/**
 * View: PDF - Attestauflage.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Attestauflage</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<div class="section-title">Bedingungen:</div>
<p>
	- Attestauflage wurde in einem pädagogischen Gespräch nachweislich angekündigt<br>
	- Fehlen wird krankheitsbedingt entschuldigt bzw. Verspätungen.
</p>

<p>
	Anzahl der Fehlstunden gesamt: <b><?= $esc( 'fehlstunden_gesamt' ) ?></b><br>
	Anzahl der unentschuldigten Fehlstunden: <b><?= $esc( 'fehlstunden_unentschuldigt' ) ?></b><br>
	Datum der Ankündigung der Attestauflage: <b><?= $date_fmt( 'ankuendigung_datum' ) ?></b><br>
	Abteilungsleitung informiert: Paraphe AL: <b><?= $esc( 'paraphe_al' ) ?></b>
</p>

<div class="section-title">Aufführung der begründeten Zweifel:</div>
<p>
	<?= '1' === ( $data['grund_haeufige_verspaetungen'] ?? '' ) ? $x : $o ?> häufige Verspätungen<br>
	<?= '1' === ( $data['grund_vorzeitiges_beenden'] ?? '' ) ? $x : $o ?> häufiges vorzeitiges Beenden des Schultages<br>
	<?= '1' === ( $data['grund_nicht_zusammenhaengende_fehltage'] ?? '' ) ? $x : $o ?> häufige, nicht zusammenhängende Fehltage<br>
	<?= '1' === ( $data['grund_bestimmte_wochentage'] ?? '' ) ? $x : $o ?> wiederholtes Fehlen an bestimmten Wochentagen:
	<?= '1' === ( $data['weekday_mo'] ?? '' ) ? $x : $o ?> Mo /
	<?= '1' === ( $data['weekday_di'] ?? '' ) ? $x : $o ?> Di /
	<?= '1' === ( $data['weekday_mi'] ?? '' ) ? $x : $o ?> Mi /
	<?= '1' === ( $data['weekday_do'] ?? '' ) ? $x : $o ?> Do /
	<?= '1' === ( $data['weekday_fr'] ?? '' ) ? $x : $o ?> Fr<br>
	<?= '1' === ( $data['grund_sonstige'] ?? '' ) ? $x : $o ?> sonstige Gründe: <?= $esc( 'grund_sonstige_text' ) ?>
</p>

<div class="section-title">Anlage:</div>
<p>
	<?= '1' === ( $data['anlage_webuntis'] ?? '' ) ? $x : $o ?> aktueller Auszug WebUntis<br>
	<?= '1' === ( $data['anlage_protokoll'] ?? '' ) ? $x : $o ?> Protokoll der pädagogischen Gespräche
</p>

<div class="section-title">Vom Sekretariat auszufüllen:</div>
<p>Versand der Attestpflicht: <b><?= $date_fmt( 'versand_datum' ) ?></b></p>
