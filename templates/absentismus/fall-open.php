<?php
/**
 * View: Absentismus-Fall eröffnen. Klasse/Schüler-Auswahl + Wahl des
 * Einstiegs-Schritts. Ein Fall muss NICHT mehr zwingend mit dem 1.
 * Pädagogischen Gespräch beginnen — laut Prozess kann er auch direkt über
 * Ordnungsamt (3 Tage in Folge), Beendigung §47 (15 Tage in Folge) oder
 * Attestauflage (Zweifel an Erkrankung) starten.
 *
 * @var array $form_data
 * @var array $form_errors
 * @var array $classes_list
 * @var string[] $entry_types_schulpflichtig      Zulässige Einstiegs-Typen, falls schulpflichtig
 * @var string[] $entry_types_nicht_schulpflichtig Zulässige Einstiegs-Typen, falls nicht schulpflichtig
 * @var array    $step_conditions                 Klartext-Beschreibung je Typ (Absentismus_Fall_Repository::get_condition_labels())
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$val     = fn( $key ) => isset( $form_data[ $key ] ) ? esc_attr( $form_data[ $key ] ) : '';
$err_cls = fn( $key ) => isset( $form_errors[ $key ] ) ? 'mh-error-field' : '';
$chk     = fn( $key, $value ) => ( isset( $form_data[ $key ] ) && $form_data[ $key ] == $value ) ? 'checked' : '';
$checked = fn( $key ) => ( isset( $form_data[ $key ] ) && '1' === $form_data[ $key ] ) ? 'checked' : '';

$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';

// Alle als Einstieg überhaupt denkbaren Typen (Vereinigung beider Varianten), in
// sinnvoller Reihenfolge — welche davon wirklich wählbar sind, entscheidet JS
// anhand der "schulpflichtig"-Checkbox (serverseitig erneut geprüft beim Absenden).
// Manche Typen sind NUR bei schulpflichtig sichtbar (ordnungsamt), manche NUR bei
// NICHT (mehr) schulpflichtig (beendigung_47), manche immer (gespraech_1, attestauflage).
$all_entry_types = array_values( array_unique( array_merge( $entry_types_schulpflichtig, $entry_types_nicht_schulpflichtig ) ) );
$entry_visibility = [];
foreach ( $all_entry_types as $t ) {
	$in_sp  = in_array( $t, $entry_types_schulpflichtig, true );
	$in_nsp = in_array( $t, $entry_types_nicht_schulpflichtig, true );
	$entry_visibility[ $t ] = $in_sp && $in_nsp ? 'always' : ( $in_sp ? 'schulpflichtig' : 'nicht_schulpflichtig' );
}
$selected_entry_type = $val( 'entry_step_type' ) ?: 'gespraech_1';

include MH_FW_PLUGIN_DIR . 'templates/absentismus/partial-form-base-css.php';
?>

<style>
	.mh-entry-type-option { opacity: 1; transition: opacity 0.15s; }
	.mh-entry-type-option.mh-hidden { display: none; }
</style>

<div class="mh-form-wrapper">
	<h2>Neuen Absentismus-Fall eröffnen</h2>
	<p>Wähle zuerst Klasse/Schüler:in, dann den zutreffenden Anlass — ein Fall kann je nach Situation mit unterschiedlichen Schritten beginnen.</p>

	<?php if ( ! empty( $form_errors ) ) : ?>
		<div class="mh-error-box">
			<strong>Bitte korrigieren:</strong>
			<ul>
				<?php foreach ( $form_errors as $error ) : ?>
					<li><?= esc_html( $error ) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" id="mh-absentismus-open-form">
		<input type="hidden" name="action" value="mh_absentismus_open_case">
		<?php wp_nonce_field( 'mh_absentismus_open_case' ); ?>

		<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/partial-student-selector.php'; ?>

		<div class="mh-form-section">
			<h4>Um welches Vergehen handelt es sich?</h4>
			<?php foreach ( $all_entry_types as $type ) : ?>
				<div class="radio-group mh-entry-type-option" data-visibility="<?= esc_attr( $entry_visibility[ $type ] ) ?>">
					<input type="radio" name="entry_step_type" value="<?= esc_attr( $type ) ?>" id="entry_<?= esc_attr( $type ) ?>" class="mh-entry-type-radio" <?= $type === $selected_entry_type ? 'checked' : '' ?>>
					<label for="entry_<?= esc_attr( $type ) ?>">
						<?= esc_html( $step_conditions[ $type ] ?? '' ) ?>
						<span class="mh-step-name-hint">→ <?= esc_html( $step_labels[ $type ] ?? $type ) ?></span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="mh-form-section">
			<h4 id="entry-fields-heading"><?= esc_html( $step_labels[ $selected_entry_type ] ?? '' ) ?></h4>
			<?php foreach ( $all_entry_types as $type ) : ?>
				<div class="mh-entry-fields" data-step-type="<?= esc_attr( $type ) ?>" style="<?= $type === $selected_entry_type ? '' : 'display:none;' ?>">
					<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-fields-' . $type . '.php'; ?>
				</div>
			<?php endforeach; ?>
			<div class="btn-group">
				<button type="submit" class="mh-btn mh-btn-primary mh-btn-large">Fall eröffnen &amp; als Entwurf speichern</button>
			</div>
		</div>
	</form>
</div>

<script>
const MH_STEP_LABELS = <?php echo wp_json_encode( array_intersect_key( $step_labels, array_flip( $all_entry_types ) ) ); ?>;

document.addEventListener('DOMContentLoaded', function () {
	const chkSchulpflichtig = document.getElementById('chk_schulpflichtig');
	const entryOptions = document.querySelectorAll('.mh-entry-type-option');
	const entryRadios  = document.querySelectorAll('.mh-entry-type-radio');
	const entryFields  = document.querySelectorAll('.mh-entry-fields');
	const entryHeading = document.getElementById('entry-fields-heading');

	// Bug-Fix: nicht ausgewählte Formulare müssen zusätzlich zu display:none auch
	// disabled werden — sonst überschreiben gleichnamige, versteckte Felder (z. B.
	// fehlstunden_gesamt kommt sowohl bei gespraech_1 als auch bei attestauflage vor)
	// beim Absenden den tatsächlich eingegebenen Wert des sichtbaren Formulars.
	function updateFieldsVisibility() {
		const selected = document.querySelector('.mh-entry-type-radio:checked');
		const selectedType = selected ? selected.value : 'gespraech_1';
		entryFields.forEach(function (block) {
			const isSelected = block.dataset.stepType === selectedType;
			block.style.display = isSelected ? '' : 'none';
			block.querySelectorAll('input, select, textarea').forEach(function (el) {
				el.disabled = !isSelected;
			});
		});
		if (entryHeading && MH_STEP_LABELS[selectedType]) {
			entryHeading.textContent = MH_STEP_LABELS[selectedType];
		}
	}

	// Manche Anlässe gelten NUR bei schulpflichtig (ordnungsamt), manche NUR bei
	// NICHT (mehr) schulpflichtig (beendigung_47), manche immer.
	function updateEntryTypeVisibility() {
		const isSchulpflichtig = chkSchulpflichtig.checked;
		let checkedIsHidden = false;

		entryOptions.forEach(function (opt) {
			const vis = opt.dataset.visibility; // 'always' | 'schulpflichtig' | 'nicht_schulpflichtig'
			const radio = opt.querySelector('.mh-entry-type-radio');
			const hide = (vis === 'schulpflichtig' && !isSchulpflichtig) || (vis === 'nicht_schulpflichtig' && isSchulpflichtig);
			opt.classList.toggle('mh-hidden', hide);
			if (hide && radio.checked) {
				checkedIsHidden = true;
			}
		});

		if (checkedIsHidden) {
			const fallback = document.getElementById('entry_gespraech_1');
			if (fallback) fallback.checked = true;
		}
		updateFieldsVisibility();
	}

	chkSchulpflichtig.addEventListener('change', updateEntryTypeVisibility);
	entryRadios.forEach(function (r) { r.addEventListener('change', updateFieldsVisibility); });
	updateEntryTypeVisibility();
});
</script>
