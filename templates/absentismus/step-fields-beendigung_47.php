<?php
/**
 * Feld-Partial: Beendigung Schulverhältnis § 47 Abs. 1 Nr. 8 SchulG (beendigung_47).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p style="margin-top:0;"><strong>Bedingung:</strong> 20 unentschuldigte Tage am Stück.</p>

<div class="mh-input-group" style="margin-bottom:15px;">
	<label>Unentschuldigte Tage am Stück <span class="req">*</span></label>
	<input type="number" min="0" name="tage_am_stueck" class="<?= $err_cls('tage_am_stueck') ?>" value="<?= $val('tage_am_stueck') ?>">
</div>

<div class="checkbox-group"><input type="checkbox" name="erinnerungsschreiben" value="1" id="e1" <?= $checked('erinnerungsschreiben') ?>> <label for="e1">Erinnerungsschreiben ab dem 15. Tag</label></div>
<div class="mh-grid-row mh-grid-2" style="margin: 0 0 15px 26px;">
	<div class="mh-input-group">
		<label>Fehlt seitdem (Tage am Stück)</label>
		<input type="number" min="0" name="erinnerung_tage_seitdem" class="<?= $err_cls('erinnerung_tage_seitdem') ?>" value="<?= $val('erinnerung_tage_seitdem') ?>">
	</div>
	<div class="mh-input-group">
		<label>Versanddatum Erinnerungsschreiben</label>
		<input type="date" name="erinnerung_versand" class="<?= $err_cls('erinnerung_versand') ?>" value="<?= $val('erinnerung_versand') ?>">
	</div>
</div>

<div class="checkbox-group"><input type="checkbox" name="ausschulungsschreiben" value="1" id="e2" <?= $checked('ausschulungsschreiben') ?>> <label for="e2">Ausschulungsschreiben</label></div>
<div class="mh-grid-row mh-grid-2" style="margin: 0 0 15px 26px;">
	<div class="mh-input-group">
		<label>Versanddatum der Ausschulung</label>
		<input type="date" name="ausschulung_versand" class="<?= $err_cls('ausschulung_versand') ?>" value="<?= $val('ausschulung_versand') ?>">
	</div>
	<div class="mh-input-group">
		<label>Grund, warum nicht</label>
		<textarea name="ausschulung_grund"><?= $val('ausschulung_grund') ?></textarea>
	</div>
</div>
