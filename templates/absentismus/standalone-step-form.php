<?php
/**
 * View: Einzelnes Absentismus-Formular OHNE Einbindung in einen Fall. Nutzt
 * bewusst dieselben Bausteine wie der Fall-Workflow (step-fields-<type>.php,
 * dasselbe Model, dasselbe PDF-Template) — es gibt keine zweite Formular-
 * Definition, nur eine schlankere Hülle drumherum ohne Fall-Lifecycle.
 *
 * @var array  $form_data
 * @var array  $form_errors
 * @var array  $classes_list
 * @var string $step_type
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$val     = fn( $key ) => isset( $form_data[ $key ] ) ? esc_attr( $form_data[ $key ] ) : '';
$err_cls = fn( $key ) => isset( $form_errors[ $key ] ) ? 'mh-error-field' : '';
$chk     = fn( $key, $value ) => ( isset( $form_data[ $key ] ) && $form_data[ $key ] == $value ) ? 'checked' : '';
$checked = fn( $key ) => ( isset( $form_data[ $key ] ) && '1' === $form_data[ $key ] ) ? 'checked' : '';

$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';
$step_label  = $step_labels[ $step_type ] ?? $step_type;

// step-fields-gespraech_2.php erwartet $case_meta['is_schulpflichtig'] — hier aus
// der (ggf. wiederbefüllten) Formular-Eingabe gespeist, damit dieselbe Partial-
// Datei unverändert wiederverwendet werden kann.
$case_meta = [ 'is_schulpflichtig' => $val( 'is_schulpflichtig' ) ];

include MH_FW_PLUGIN_DIR . 'templates/absentismus/partial-form-base-css.php';
?>

<div class="mh-form-wrapper">
	<h2><?= esc_html( $step_label ) ?></h2>
	<p>Dieses Formular wird direkt als PDF erstellt und <strong>nicht</strong> in einem Absentismus-Fall gespeichert.</p>

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

	<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
		<input type="hidden" name="action" value="mh_absentismus_standalone_submit">
		<input type="hidden" name="step_type" value="<?= esc_attr( $step_type ) ?>">
		<?php wp_nonce_field( 'mh_absentismus_standalone_submit_' . $step_type ); ?>

		<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/partial-student-selector.php'; ?>

		<div class="mh-form-section">
			<h4><?= esc_html( $step_label ) ?></h4>
			<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-fields-' . $step_type . '.php'; ?>
			<div class="btn-group">
				<button type="submit" class="mh-btn mh-btn-primary mh-btn-large">Prüfen &amp; PDF erstellen</button>
			</div>
		</div>
	</form>
</div>
