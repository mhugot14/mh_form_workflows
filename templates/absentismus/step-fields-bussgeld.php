<?php
/**
 * Feld-Partial: Einleitung Bußgeldverfahren / Anhörung (bussgeld).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p style="margin-top:0;"><strong>Bedingungen:</strong> Keine Verhaltensänderung nach den Gesprächen, schriftliche Aufforderung erfolgt, weiteres erzieherisches Einwirken erfolglos.</p>

<div class="checkbox-group"><input type="checkbox" name="anlage_webuntis" value="1" id="a1" <?= $checked('anlage_webuntis') ?>> <label for="a1">aktueller Auszug WebUntis</label></div>

<div class="mh-grid-row mh-grid-2" style="margin-top:15px;">
	<div class="mh-input-group">
		<label>Versand Anhörungsbogen <span style="font-weight:normal;">(Sekretariat)</span></label>
		<input type="date" name="versand_anhoerungsbogen" value="<?= $val('versand_anhoerungsbogen') ?>">
	</div>
	<div class="mh-input-group">
		<label>Datum Mail an Bezirksregierung <span class="req">*</span></label>
		<input type="date" name="mail_bezirksregierung_datum" class="<?= $err_cls('mail_bezirksregierung_datum') ?>" value="<?= $val('mail_bezirksregierung_datum') ?>">
	</div>
</div>
<div class="checkbox-group"><input type="checkbox" name="mail_bezirksregierung" value="1" id="a2" <?= $checked('mail_bezirksregierung') ?>> <label for="a2">Mail an die Bezirksregierung wurde versendet</label></div>
