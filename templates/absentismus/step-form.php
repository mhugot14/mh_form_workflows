<?php
/**
 * View: Rahmen für ein Absentismus-Schritt-Formular. Bindet je nach $step_type
 * eine der 8 step-fields-<type>.php Partials ein.
 *
 * @var int        $case_id
 * @var array      $case_meta    Fall-Stammdaten (form_data der Fall-Zeile)
 * @var int        $step_no
 * @var string     $step_type
 * @var int|null   $edit_step_no Gesetzt, wenn ein bestehender Entwurf bearbeitet wird
 * @var array      $form_data
 * @var array      $form_errors
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';
$step_label  = $step_labels[ $step_type ] ?? $step_type;
$is_editing  = isset( $edit_step_no );

$val      = fn( $key ) => isset( $form_data[ $key ] ) ? esc_attr( $form_data[ $key ] ) : '';
$err_cls  = fn( $key ) => isset( $form_errors[ $key ] ) ? 'mh-error-field' : '';
$chk      = fn( $key, $value ) => ( isset( $form_data[ $key ] ) && $form_data[ $key ] == $value ) ? 'checked' : '';
$checked  = fn( $key ) => ( isset( $form_data[ $key ] ) && '1' === $form_data[ $key ] ) ? 'checked' : '';

include MH_FW_PLUGIN_DIR . 'templates/absentismus/partial-form-base-css.php';
?>

<style>
	.mh-fall-header { background: #003E7E; color: #fff; padding: 15px 20px; border-radius: 4px 4px 0 0; }
	.mh-fall-header h3 { margin: 0 0 5px 0; color: #fff; }
	.mh-fall-header .mh-fall-sub { font-size: 0.85em; opacity: 0.85; }
	.mh-form-wrapper .mh-form-section { border-top: none; border-radius: 0 0 4px 4px; }
</style>

<div class="mh-form-wrapper">
	<div class="mh-fall-header">
		<h3><?= esc_html( $step_label ) ?></h3>
		<div class="mh-fall-sub">
			<?= esc_html( $case_meta['lastname'] ?? '' ) ?>, <?= esc_html( $case_meta['firstname'] ?? '' ) ?>
			&nbsp;·&nbsp; Klasse <?= esc_html( $case_meta['class_name'] ?? '' ) ?>
			&nbsp;·&nbsp; Klassenleitung <?= esc_html( $case_meta['teacher'] ?? '' ) ?>
			&nbsp;·&nbsp;
			<?= ! empty( $case_meta['is_minor'] ) ? 'minderjährig' : 'volljährig' ?>,
			<?= ! empty( $case_meta['is_schulpflichtig'] ) ? 'schulpflichtig' : 'nicht schulpflichtig' ?>
		</div>
	</div>

	<div class="mh-form-section">
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
			<input type="hidden" name="action" value="mh_absentismus_step_submit">
			<input type="hidden" name="case_id" value="<?= (int) $case_id ?>">
			<input type="hidden" name="edit_step_no" value="<?= $is_editing ? (int) $edit_step_no : 0 ?>">
			<input type="hidden" name="step_type" value="<?= esc_attr( $step_type ) ?>">
			<input type="hidden" name="is_schulpflichtig" value="<?= ! empty( $case_meta['is_schulpflichtig'] ) ? '1' : '0' ?>">
			<?php wp_nonce_field( 'mh_absentismus_step_submit' ); ?>

			<?php include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-fields-' . $step_type . '.php'; ?>

			<div class="btn-group">
				<button type="submit" class="mh-btn mh-btn-primary mh-btn-large">Als Entwurf speichern</button>
			</div>
		</form>
	</div>
</div>
