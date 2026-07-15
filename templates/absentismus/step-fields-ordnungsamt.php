<?php
/**
 * Feld-Partial: Zuführung durch das Ordnungsamt (ordnungsamt).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p style="margin-top:0;"><strong>Bedingung:</strong> Versuchte Kontaktaufnahme und 3 Tage am Stück unentschuldigt. Anlage: Auszug aus WebUntis der letzten 3 Schultage.</p>

<div class="mh-grid-row mh-grid-2">
	<div class="mh-input-group">
		<label>Bezirkssozialarbeiterin <span class="req">*</span></label>
		<input type="text" name="bezirkssozialarbeiterin" class="<?= $err_cls('bezirkssozialarbeiterin') ?>" value="<?= $val('bezirkssozialarbeiterin') ?>">
	</div>
	<div class="mh-input-group">
		<label>Telefon <span class="req">*</span></label>
		<input type="text" name="telefon" class="<?= $err_cls('telefon') ?>" value="<?= $val('telefon') ?>">
	</div>
</div>

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Zuführung geplant am <span class="req">*</span></label>
	<input type="date" name="zufuehrung_geplant_am" class="<?= $err_cls('zufuehrung_geplant_am') ?>" value="<?= $val('zufuehrung_geplant_am') ?>">
</div>

<div class="mh-input-group" style="margin-bottom:10px;">
	<label>Zuführung erfolgreich?</label>
	<div class="radio-group"><input type="radio" name="zufuehrung_erfolgreich" value="ja" id="zf_ja" <?= $chk('zufuehrung_erfolgreich','ja') ?>> <label for="zf_ja">Ja</label></div>
	<div class="radio-group"><input type="radio" name="zufuehrung_erfolgreich" value="nein" id="zf_nein" <?= $chk('zufuehrung_erfolgreich','nein') ?>> <label for="zf_nein">Nein</label></div>
</div>

<div class="mh-input-group">
	<label>Grund (falls nicht erfolgreich)</label>
	<textarea name="zufuehrung_grund" class="<?= $err_cls('zufuehrung_grund') ?>"><?= $val('zufuehrung_grund') ?></textarea>
</div>
