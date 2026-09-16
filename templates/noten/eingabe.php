<?php
/**
 * View: Noteneingabe einer Fachlehrkraft.
 *
 * Zeigt bewusst NUR die eigene Zeile — keine fremden Noten, keine Abmeldegründe.
 *
 * @var array $case
 * @var array $item
 * @var array $form_errors
 * @var bool  $is_success
 * @var bool  $is_owner
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$fd        = $case['form_data'];
$done      = 'erledigt' === ( $item['status'] ?? 'offen' );
$is_stand  = $is_owner && (int) ( $item['recipient_user_id'] ?? 0 ) !== get_current_user_id();
?>

<style>
	.mh-noten-box { max-width: 640px; margin: 25px auto; font-family: inherit; }
	.mh-noten-box .mh-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 25px; }
	.mh-noten-box h2 { margin-top: 0; font-size: 1.3em; }
	.mh-stamm { background: #f6f8fa; border-left: 4px solid #0073aa; padding: 12px 16px; margin-bottom: 20px; }
	.mh-stamm div { margin: 3px 0; }
	.mh-noten-box label { display: block; font-weight: bold; margin: 16px 0 5px; }
	.mh-noten-box select, .mh-noten-box textarea { width: 100%; padding: 8px; box-sizing: border-box; }
	.mh-noten-box textarea { height: 90px; }
	.mh-check { font-weight: normal !important; margin-top: 12px !important; }
	.mh-check input { margin-right: 8px; }
	.mh-err { color: #a13a2f; font-size: 0.9em; margin-top: 4px; }
	.mh-field-err select, .mh-field-err textarea { border: 2px solid #a13a2f; }
	.mh-ok { background: #e8f5e9; color: #1b5e20; padding: 12px 16px; border-radius: 4px; margin-bottom: 18px; }
	.mh-hint { background: #fff8e5; border-left: 4px solid #e5a912; padding: 10px 15px; font-size: 0.9em; margin-bottom: 18px; }
	.mh-noten-box button { margin-top: 20px; padding: 12px 24px; background: #0073aa; color: #fff; border: 0; border-radius: 4px; cursor: pointer; font-size: 1em; }
</style>

<div class="mh-noten-box">
	<div class="mh-card">
		<h2>Noteneingabe</h2>

		<?php if ( $is_success ) : ?>
			<div class="mh-ok">✅ Die Note wurde gespeichert. Vielen Dank.</div>
		<?php endif; ?>

		<?php if ( $is_stand ) : ?>
			<div class="mh-hint">
				Du trägst diese Note als Klassenlehrkraft nach. Es wird vermerkt, dass die Eingabe
				nicht von der zuständigen Fachlehrkraft stammt.
			</div>
		<?php endif; ?>

		<div class="mh-stamm">
			<div><strong>Schüler/in:</strong> <?= esc_html( ( $fd['lastname'] ?? '' ) . ', ' . ( $fd['firstname'] ?? '' ) ) ?></div>
			<div><strong>Klasse:</strong> <?= esc_html( $fd['class_name'] ?? '' ) ?></div>
			<div><strong>Fach:</strong> <?= esc_html( $item['subject'] ?? '' ) ?></div>
		</div>

		<?php if ( $done ) : ?>
			<p>Für dieses Fach ist bereits eine Note eingetragen. Eine Korrektur ist weiterhin möglich.</p>
		<?php endif; ?>

		<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
			<input type="hidden" name="action" value="mh_noten_save_item">
			<input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
			<input type="hidden" name="item_idx" value="<?= (int) $item['idx'] ?>">
			<?php wp_nonce_field( 'mh_noten_save_item' ); ?>

			<div class="<?= isset( $form_errors['grade'] ) ? 'mh-field-err' : '' ?>">
				<label for="mh_grade">Note *</label>
				<select name="grade" id="mh_grade">
					<option value="">-- Note wählen --</option>
					<?php foreach ( [ '1', '2', '3', '4', '5', '6', 'NB', 'NE' ] as $g ) : ?>
						<option value="<?= esc_attr( $g ) ?>" <?= selected( $item['grade'] ?? '', $g, false ) ?>><?= esc_html( $g ) ?></option>
					<?php endforeach; ?>
				</select>
				<p style="font-size:0.85em;color:#666;margin:6px 0 0;">NB = nicht bewertbar &nbsp;|&nbsp; NE = nicht erteilt</p>
				<?php if ( isset( $form_errors['grade'] ) ) : ?>
					<div class="mh-err"><?= esc_html( $form_errors['grade'] ) ?></div>
				<?php endif; ?>
			</div>

			<div class="<?= isset( $form_errors['remark'] ) ? 'mh-field-err' : '' ?>">
				<label for="mh_remark">Bemerkung <span id="mh_remark_req" style="display:none;">*</span></label>
				<textarea name="remark" id="mh_remark" placeholder="Bei der Note NB zwingend erforderlich."><?= esc_textarea( $item['remark'] ?? '' ) ?></textarea>
				<?php if ( isset( $form_errors['remark'] ) ) : ?>
					<div class="mh-err"><?= esc_html( $form_errors['remark'] ) ?></div>
				<?php endif; ?>
			</div>

			<label class="mh-check">
				<input type="checkbox" name="webuntis" value="1" <?= checked( $item['webuntis'] ?? '0', '1', false ) ?>>
				Teilnoten sind in WebUntis eingetragen
			</label>
			<label class="mh-check">
				<input type="checkbox" name="completed" value="1" <?= checked( $item['completed'] ?? '0', '1', false ) ?>>
				Das Fach wurde vorher abgeschlossen
			</label>

			<button type="submit">Note speichern</button>
		</form>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	// Gleiche Regel wie im Hauptformular: NB verlangt eine Begründung.
	var grade  = document.getElementById('mh_grade');
	var remark = document.getElementById('mh_remark');
	var req    = document.getElementById('mh_remark_req');
	if (!grade || !remark) return;

	function sync() {
		var needed = grade.value === 'NB';
		remark.required = needed;
		if (req) req.style.display = needed ? 'inline' : 'none';
	}
	grade.addEventListener('change', sync);
	sync();
});
</script>
