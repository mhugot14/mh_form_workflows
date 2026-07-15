<?php
/**
 * View: PDF - Beendigung Schulverhältnis nach § 47 Abs. 1 Nr. 8 SchulG.
 * @var array $data
 */
if ( ! defined( 'ABSPATH' ) ) exit;
include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-header.php';
?>

<div class="header">Beendigung Schulverhältnis nach § 47 Abs. 1 Nr. 8 SchulG</div>

<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/pdf-stammdaten-box.php'; ?>

<p>(20 unentschuldigte Tage am Stück)</p>

<p>
	<?= '1' === ( $data['erinnerungsschreiben'] ?? '' ) ? $x : $o ?> Erinnerungsschreiben ab dem 15. Tag<br>
	Fehlt seitdem <b><?= $esc( 'erinnerung_tage_seitdem' ) ?></b> Tage am Stück unentschuldigt.<br>
	<span class="small-text">(Anlage: aktueller Auszug WebUntis)</span>
</p>

<div class="section-title">Vom Sekretariat auszufüllen:</div>
<p>Versand des Erinnerungsschreibens: <b><?= $date_fmt( 'erinnerung_versand' ) ?></b></p>

<p>
	<?= '1' === ( $data['ausschulungsschreiben'] ?? '' ) ? $x : $o ?> Ausschulungsschreiben<br>
	<span class="small-text">Nach 5 weiteren Tagen nachfragen, ob der Schüler zwischendurch anwesend/gemeldet hat und ob die Ausschulung erfolgen soll.</span>
</p>
<p>
	Versand der Ausschulung: <b><?= $date_fmt( 'ausschulung_versand' ) ?></b><br>
	Grund, warum nicht: <b><?= nl2br( $esc( 'ausschulung_grund' ) ) ?></b>
</p>
