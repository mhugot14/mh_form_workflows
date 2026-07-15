<?php
/**
 * Feld-Partial: 1. Pädagogisches Gespräch (gespraech_1).
 * Nutzt $val/$err_cls/$chk/$checked aus step-form.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mh-grid-row mh-grid-3">
	<div class="mh-input-group">
		<label>Datum <span class="req">*</span></label>
		<input type="date" name="datum" class="<?= $err_cls('datum') ?>" value="<?= $val('datum') ?>">
	</div>
	<div class="mh-input-group">
		<label>Uhrzeit von <span class="req">*</span></label>
		<input type="time" name="uhrzeit_von" class="<?= $err_cls('uhrzeit_von') ?>" value="<?= $val('uhrzeit_von') ?>">
	</div>
	<div class="mh-input-group">
		<label>Uhrzeit bis</label>
		<input type="time" name="uhrzeit_bis" value="<?= $val('uhrzeit_bis') ?>">
	</div>
</div>

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Ort <span class="req">*</span></label>
	<div class="radio-group"><input type="radio" name="ort" value="schule" id="ort_schule" <?= $chk('ort','schule') ?>> <label for="ort_schule">Schule</label></div>
	<div class="radio-group"><input type="radio" name="ort" value="telefonat" id="ort_tel" <?= $chk('ort','telefonat') ?>> <label for="ort_tel">Telefonat</label></div>
</div>

<div class="mh-grid-row mh-grid-2">
	<div class="mh-input-group">
		<label>Schulsozialarbeit</label>
		<input type="text" name="schulsozialarbeit" value="<?= $val('schulsozialarbeit') ?>">
	</div>
	<div class="mh-input-group">
		<label>Weitere Teilnehmer</label>
		<input type="text" name="weitere" value="<?= $val('weitere') ?>">
	</div>
</div>

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Erziehungsberechtigte</label>
	<textarea name="erziehungsberechtigte"><?= $val('erziehungsberechtigte') ?></textarea>
</div>

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
	<label>Inhalte des Gesprächs <span class="req">*</span></label>
	<textarea name="inhalte" class="<?= $err_cls('inhalte') ?>"><?= $val('inhalte') ?></textarea>
</div>

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Ergebnisse / Vereinbarungen <span class="req">*</span></label>
	<textarea name="ergebnisse" class="<?= $err_cls('ergebnisse') ?>"><?= $val('ergebnisse') ?></textarea>
</div>

<div class="mh-input-group">
	<label>Überprüfen der Vereinbarungen am <span class="req">*</span></label>
	<input type="date" name="ueberpruefung_am" class="<?= $err_cls('ueberpruefung_am') ?>" value="<?= $val('ueberpruefung_am') ?>">
</div>
