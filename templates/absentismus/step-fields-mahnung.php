<?php
/**
 * Feld-Partial: Schriftliche Mahnung / Aufforderung Schulbesuch (mahnung).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p style="margin-top:0;"><strong>Bedingung:</strong> Nach den pädagogischen Gesprächen zeigt sich keine Verhaltensänderung.</p>

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

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Abteilungsleitung informiert (Paraphe) <span class="req">*</span></label>
	<input type="text" name="paraphe_al" class="<?= $err_cls('paraphe_al') ?>" value="<?= $val('paraphe_al') ?>">
</div>

<div class="mh-input-group" style="margin-bottom:5px;"><label>Anlage</label></div>
<div class="checkbox-group"><input type="checkbox" name="anlage_webuntis" value="1" id="a1" <?= $checked('anlage_webuntis') ?>> <label for="a1">aktueller Auszug WebUntis</label></div>
<div class="checkbox-group"><input type="checkbox" name="anlage_protokoll" value="1" id="a2" <?= $checked('anlage_protokoll') ?>> <label for="a2">Protokoll der pädagogischen Gespräche</label></div>

<div class="mh-input-group" style="margin-top:15px;">
	<label>Versanddatum <span style="font-weight:normal;">(Sekretariat)</span></label>
	<input type="date" name="versand_datum" value="<?= $val('versand_datum') ?>">
</div>
