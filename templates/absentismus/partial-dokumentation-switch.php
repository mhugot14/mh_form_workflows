<?php
/**
 * Feld-Partial: Umschalter "Gespräch hier dokumentieren" vs. "bereits extern
 * dokumentiert" (WebUntis / handschriftlich) für gespraech_1, gespraech_2 und gespraech_weiteres.
 *
 * Bei externer Dokumentation werden alle Elemente mit der Klasse
 * .mh-doku-details innerhalb des umgebenden .mh-doku-scope ausgeblendet — es
 * bleibt nur das Gesprächsdatum (Pflicht). Die Validierung im Model
 * (Abstract_Absentismus_Step_Form::validate_extern_dokumentation()) ignoriert
 * die ausgeblendeten Felder dann ebenfalls.
 *
 * Im Einzelformular ohne Fall ($allow_extern_doku = false) nicht sinnvoll — dort
 * wäre das Ergebnis ein leeres PDF —, daher wird der Umschalter dort nicht
 * angezeigt.
 *
 * Nutzt $chk aus step-form.php / fall-open.php; $doku_id_prefix macht die
 * Radio-IDs eindeutig, da fall-open.php mehrere Partials gleichzeitig rendert.
 *
 * @var string $doku_id_prefix
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! ( $allow_extern_doku ?? true ) ) {
	return;
}

$doku_current = $form_data['dokumentation'] ?? 'formular';
// Statisches HTML (fett hervorgehobene Schlüsselwörter) — daher unten nicht
// escaped, sondern über wp_kses() auf <strong> beschränkt ausgegeben.
$doku_options = [
	'formular'        => 'Gespräch <strong>hier im Formular</strong> dokumentieren',
	'webuntis'        => 'Gespräch wurde geführt und ist in <strong>WebUntis</strong> dokumentiert',
	'handschriftlich' => 'Gespräch wurde geführt und ist <strong>handschriftlich</strong> dokumentiert',
];
?>
<div class="mh-input-group mh-doku-switch" style="margin-bottom:15px;">
	<label>Dokumentation <span class="req">*</span></label>
	<?php foreach ( $doku_options as $doku_value => $doku_label ) : ?>
		<div class="radio-group">
			<input type="radio" name="dokumentation" value="<?= esc_attr( $doku_value ) ?>" id="<?= esc_attr( $doku_id_prefix . '_doku_' . $doku_value ) ?>" <?= $doku_current === $doku_value ? 'checked' : '' ?>>
			<label for="<?= esc_attr( $doku_id_prefix . '_doku_' . $doku_value ) ?>" style="font-weight:normal;"><?= wp_kses( $doku_label, [ 'strong' => [] ] ) ?></label>
		</div>
	<?php endforeach; ?>
	<p class="mh-doku-extern-hint" style="font-size:0.85em; color:#666; margin:4px 0 0 0;<?= 'formular' === $doku_current ? ' display:none;' : '' ?>">Bei externer Dokumentation ist nur das Datum des Gesprächs anzugeben.</p>
</div>

<script>
( function () {
	// Einmal-Registrierung per Event-Delegation — das Partial kann mehrfach auf
	// einer Seite eingebunden sein (fall-open.php rendert alle Einstiegs-Typen).
	if ( window.mhDokuSwitchInit ) {
		window.mhDokuSwitchInit();
		return;
	}
	function apply( scope ) {
		const checked = scope.querySelector( 'input[name="dokumentation"]:checked' );
		const isExtern = checked && 'formular' !== checked.value;
		scope.querySelectorAll( '.mh-doku-details' ).forEach( function ( el ) {
			el.style.display = isExtern ? 'none' : '';
		} );
		scope.querySelectorAll( '.mh-doku-extern-hint' ).forEach( function ( el ) {
			el.style.display = isExtern ? '' : 'none';
		} );
	}
	window.mhDokuSwitchInit = function () {
		document.querySelectorAll( '.mh-doku-scope' ).forEach( apply );
	};
	document.addEventListener( 'change', function ( e ) {
		if ( 'dokumentation' === e.target.name ) {
			const scope = e.target.closest( '.mh-doku-scope' );
			if ( scope ) apply( scope );
		}
	} );
	document.addEventListener( 'DOMContentLoaded', window.mhDokuSwitchInit );
	window.mhDokuSwitchInit();
} )();
</script>
