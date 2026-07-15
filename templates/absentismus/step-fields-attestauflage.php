<?php
/**
 * Feld-Partial: Attestauflage (attestauflage).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mh-grid-row mh-grid-2">
	<div class="mh-input-group">
		<label>Anzahl Fehlstunden gesamt <span class="req">*</span></label>
		<input type="number" min="0" name="fehlstunden_gesamt" class="<?= $err_cls('fehlstunden_gesamt') ?>" value="<?= $val('fehlstunden_gesamt') ?>">
	</div>
	<div class="mh-input-group">
		<label>davon unentschuldigt <span class="req">*</span></label>
		<input type="number" min="0" name="fehlstunden_unentschuldigt" class="<?= $err_cls('fehlstunden_unentschuldigt') ?>" value="<?= $val('fehlstunden_unentschuldigt') ?>">
	</div>
</div>

<div class="mh-grid-row mh-grid-2">
	<div class="mh-input-group">
		<label>Datum der Ankündigung <span class="req">*</span></label>
		<input type="date" name="ankuendigung_datum" class="<?= $err_cls('ankuendigung_datum') ?>" value="<?= $val('ankuendigung_datum') ?>">
	</div>
	<div class="mh-input-group">
		<label>Abteilungsleitung informiert (Paraphe) <span class="req">*</span></label>
		<input type="text" name="paraphe_al" class="<?= $err_cls('paraphe_al') ?>" value="<?= $val('paraphe_al') ?>">
	</div>
</div>

<div class="mh-input-group" style="margin-bottom:5px;">
	<label>Begründete Zweifel <span class="req">*</span> <span style="font-weight:normal;">(mind. einen Grund wählen)</span></label>
</div>
<div class="checkbox-group"><input type="checkbox" name="grund_haeufige_verspaetungen" value="1" id="g1" <?= $checked('grund_haeufige_verspaetungen') ?>> <label for="g1">häufige Verspätungen</label></div>
<div class="checkbox-group"><input type="checkbox" name="grund_vorzeitiges_beenden" value="1" id="g2" <?= $checked('grund_vorzeitiges_beenden') ?>> <label for="g2">häufiges vorzeitiges Beenden des Schultages</label></div>
<div class="checkbox-group"><input type="checkbox" name="grund_nicht_zusammenhaengende_fehltage" value="1" id="g3" <?= $checked('grund_nicht_zusammenhaengende_fehltage') ?>> <label for="g3">häufige, nicht zusammenhängende Fehltage</label></div>
<div class="checkbox-group"><input type="checkbox" name="grund_bestimmte_wochentage" value="1" id="g4" <?= $checked('grund_bestimmte_wochentage') ?>> <label for="g4">wiederholtes Fehlen an bestimmten Wochentagen</label></div>
<div class="mh-weekday-row">
	<?php foreach ( [ 'weekday_mo' => 'Mo', 'weekday_di' => 'Di', 'weekday_mi' => 'Mi', 'weekday_do' => 'Do', 'weekday_fr' => 'Fr' ] as $field => $label ) : ?>
		<label><input type="checkbox" name="<?= $field ?>" value="1" <?= $checked( $field ) ?>> <?= $label ?></label>
	<?php endforeach; ?>
</div>
<div class="checkbox-group"><input type="checkbox" name="grund_sonstige" value="1" id="g5" <?= $checked('grund_sonstige') ?>> <label for="g5">sonstige Gründe</label></div>
<div class="mh-input-group" style="margin: 0 0 15px 26px;">
	<textarea name="grund_sonstige_text" class="<?= $err_cls('grund_sonstige_text') ?>" placeholder="Nähere Ausführung..."><?= $val('grund_sonstige_text') ?></textarea>
</div>

<div class="mh-input-group" style="margin-bottom:5px;"><label>Anlage</label></div>
<div class="checkbox-group"><input type="checkbox" name="anlage_webuntis" value="1" id="a1" <?= $checked('anlage_webuntis') ?>> <label for="a1">aktueller Auszug WebUntis</label></div>
<div class="checkbox-group"><input type="checkbox" name="anlage_protokoll" value="1" id="a2" <?= $checked('anlage_protokoll') ?>> <label for="a2">Protokoll der pädagogischen Gespräche</label></div>

<div class="mh-input-group" style="margin-top:15px;">
	<label>Versand der Attestpflicht <span style="font-weight:normal;">(Sekretariat)</span></label>
	<input type="date" name="versand_datum" value="<?= $val('versand_datum') ?>">
</div>
