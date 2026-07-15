<?php
/**
 * Feld-Partial: 2. Pädagogisches Gespräch (gespraech_2).
 *
 * @var array $case_meta Fall-Stammdaten, u. a. is_schulpflichtig.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$is_schulpflichtig = ! empty( $case_meta['is_schulpflichtig'] );
?>
<?php if ( $is_schulpflichtig ) : ?>
	<p style="font-size:0.85em; color:#666;">Bei schulpflichtigen SuS lädt das Sekretariat die Erziehungsberechtigten formell ein (Formular + Einladung Pflicht).</p>
<?php else : ?>
	<p style="font-size:0.85em; color:#666;">Nicht mehr schulpflichtig: kein Einladungsschreiben nötig, nur Protokoll/Zielvereinbarungen.</p>
<?php endif; ?>

<div class="mh-grid-row mh-grid-3">
	<div class="mh-input-group">
		<label>Datum der Einladung <?= $is_schulpflichtig ? '<span class="req">*</span>' : '' ?></label>
		<input type="date" name="einladung_datum" class="<?= $err_cls('einladung_datum') ?>" value="<?= $val('einladung_datum') ?>">
	</div>
	<div class="mh-input-group">
		<label>Uhrzeit der Einladung <?= $is_schulpflichtig ? '<span class="req">*</span>' : '' ?></label>
		<input type="time" name="einladung_uhrzeit" class="<?= $err_cls('einladung_uhrzeit') ?>" value="<?= $val('einladung_uhrzeit') ?>">
	</div>
	<div class="mh-input-group">
		<label>Versand der Einladung <span style="font-weight:normal;">(Sekretariat)</span></label>
		<input type="date" name="versand_einladung" value="<?= $val('versand_einladung') ?>">
	</div>
</div>

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
		<label>Abteilungsleitung</label>
		<input type="text" name="abteilungsleitung" value="<?= $val('abteilungsleitung') ?>">
	</div>
	<div class="mh-input-group">
		<label>Schulsozialarbeit</label>
		<input type="text" name="schulsozialarbeit" value="<?= $val('schulsozialarbeit') ?>">
	</div>
	<div class="mh-input-group">
		<label>Erziehungsberechtigte</label>
		<input type="text" name="erziehungsberechtigte_1" value="<?= $val('erziehungsberechtigte_1') ?>">
	</div>
	<div class="mh-input-group">
		<label>Erziehungsberechtigte</label>
		<input type="text" name="erziehungsberechtigte_2" value="<?= $val('erziehungsberechtigte_2') ?>">
	</div>
	<div class="mh-input-group">
		<label>Weitere Teilnehmer</label>
		<input type="text" name="weitere" value="<?= $val('weitere') ?>">
	</div>
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

<div class="checkbox-group"><input type="checkbox" name="ankuendigung_attestpflicht" value="1" id="chk_attest" <?= $checked('ankuendigung_attestpflicht') ?>> <label for="chk_attest">Ankündigung einer möglichen Attestpflicht (bei krankheitsbedingtem Fehlen)</label></div>
<div class="checkbox-group"><input type="checkbox" name="kontakt_sozialarbeit" value="1" id="chk_sozial" <?= $checked('kontakt_sozialarbeit') ?>> <label for="chk_sozial">Kontaktaufnahme mit der Schulsozialarbeit</label></div>

<div class="mh-input-group" style="margin-top:15px;">
	<label>Überprüfen der Vereinbarungen am <span class="req">*</span></label>
	<input type="date" name="ueberpruefung_am" class="<?= $err_cls('ueberpruefung_am') ?>" value="<?= $val('ueberpruefung_am') ?>">
</div>
