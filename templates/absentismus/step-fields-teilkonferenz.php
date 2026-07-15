<?php
/**
 * Feld-Partial: Teilkonferenz (teilkonferenz).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Grund <span class="req">*</span></label>
	<div class="radio-group">
		<input type="radio" name="trigger" value="beendigung_53" id="tr1" <?= $chk('trigger','beendigung_53') ?>>
		<label for="tr1">Mögliche Beendigung Schulverhältnis nach § 53 Abs. Nr. 3, 4, 7 SchulG (20 unentschuldigte Stunden innerhalb von 30 Tagen)</label>
	</div>
	<div class="radio-group">
		<input type="radio" name="trigger" value="hohe_fehlstunden" id="tr2" <?= $chk('trigger','hohe_fehlstunden') ?>>
		<label for="tr2">Hohe unentschuldigte Fehlstunden</label>
	</div>
</div>

<div class="mh-grid-row mh-grid-3">
	<div class="mh-input-group">
		<label>Zeitraum von <span class="req">*</span></label>
		<input type="date" name="zeitraum_von" class="<?= $err_cls('zeitraum_von') ?>" value="<?= $val('zeitraum_von') ?>">
	</div>
	<div class="mh-input-group">
		<label>Zeitraum bis <span class="req">*</span></label>
		<input type="date" name="zeitraum_bis" class="<?= $err_cls('zeitraum_bis') ?>" value="<?= $val('zeitraum_bis') ?>">
	</div>
	<div class="mh-input-group">
		<label>Anzahl unentschuldigte Fehlstunden <span class="req">*</span></label>
		<input type="number" min="0" name="fehlstunden_unentschuldigt" class="<?= $err_cls('fehlstunden_unentschuldigt') ?>" value="<?= $val('fehlstunden_unentschuldigt') ?>">
	</div>
</div>
<p style="font-size:0.85em; color:#666;">Anlage: WebUntis-Auszug der Fehlzeiten. Bei "Beendigung § 53": max. 30 Tage Zeitraum, mind. 20 unentschuldigte Fehlstunden.</p>

<div class="mh-input-group" style="margin-top:15px;">
	<label>Beschluss <span style="font-weight:normal;">(sobald bekannt — kann auch nachträglich vor dem Festschreiben ergänzt werden)</span></label>
	<div class="radio-group"><input type="radio" name="beschluss" value="" id="b0" <?= empty($form_data['beschluss'] ?? '') ? 'checked' : '' ?>> <label for="b0">noch offen</label></div>
	<div class="radio-group"><input type="radio" name="beschluss" value="androhung_entlassung" id="b1" <?= $chk('beschluss','androhung_entlassung') ?>> <label for="b1">Androhung der Entlassung (§ 53 Abs. 3 Nr. 4)</label></div>
	<div class="radio-group"><input type="radio" name="beschluss" value="entlassung" id="b2" <?= $chk('beschluss','entlassung') ?>> <label for="b2">Entlassung (§ 53 Abs. 3 Nr. 5 i. V. m. Abs. 4)</label></div>
	<p style="font-size:0.85em; color:#666; margin: 5px 0 0 0;">Hinweis: Der Fall wird dadurch nicht automatisch geschlossen — das entscheidet die Klassenleitung eigenständig über "Fall schließen" in der Fall-Übersicht.</p>
</div>
