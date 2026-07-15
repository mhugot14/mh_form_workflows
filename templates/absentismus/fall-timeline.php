<?php
/**
 * View: Timeline eines Absentismus-Falls.
 *
 * @var array $case          Fall-Zeile inkl. dekodiertem form_data
 * @var int   $case_id
 * @var array $step_overview Eine Karte je Vergehen-Variante (0-n, ein Schritt-Typ
 *                            kann mehrere Varianten haben, z. B. beendigung_47),
 *                            je mit 'condition' (Klartext), 'available' (bool),
 *                            'missing' (string[] noch nicht festgeschriebene
 *                            Vorbedingungs-Typen) und 'locked_hint' (vorformulierter
 *                            Text, überschreibt den generischen missing-Hinweis) —
 *                            siehe Absentismus_Fall_Repository::get_step_type_overview()
 * @var bool  $is_admin
 * @var bool  $is_owner
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$case_meta   = $case['form_data'];
$steps       = $case_meta['steps'] ?? [];
$contacts    = $case_meta['contacts'] ?? [];
$notes       = $case_meta['notes'] ?? [];
$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';
$field_meta  = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-field-meta.php';
$is_closed   = 'geschlossen' === ( $case_meta['case_status'] ?? $case['status'] );
$can_manage  = $is_admin || $is_owner;

$contact_roles = [
	'eltern'    => 'Erziehungsberechtigte/Eltern',
	'betreuer'  => 'Betreuer:in',
	'betrieb'   => 'Betrieb/Ausbildung',
	'schueler'  => 'Schüler:in',
	'sonstige'  => 'Sonstige',
];

$self_base = get_permalink() ?: '';
$this_case_url = add_query_arg( 'mh_case_id', $case_id, $self_base );

/**
 * Formatiert ein Feld-Wert-Paar eines Schritts für die Detailansicht.
 * Gibt null zurück, wenn das Feld leer oder ausgeblendet ist (kein Zeileneintrag).
 */
$format_step_field = function ( string $field, $value ) use ( $field_meta ): ?array {
	if ( in_array( $field, $field_meta['hidden_fields'], true ) ) {
		return null;
	}
	if ( is_bool( $value ) ) {
		$value = $value ? '1' : '0';
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}

	$label = $field_meta['labels'][ $field ] ?? ucwords( str_replace( '_', ' ', $field ) );

	if ( in_array( $field, $field_meta['checkbox_fields'], true ) ) {
		$display = ( '1' === $value ) ? 'Ja' : 'Nein';
	} elseif ( isset( $field_meta['value_labels'][ $field ][ $value ] ) ) {
		$display = $field_meta['value_labels'][ $field ][ $value ];
	} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		$display = date( 'd.m.Y', strtotime( $value ) );
	} else {
		$display = $value;
	}

	return [ 'label' => $label, 'value' => $display ];
};

$nonce_url = fn( array $params, string $nonce_action ) => wp_nonce_url(
	add_query_arg( $params, admin_url( 'admin-post.php' ) ),
	$nonce_action
);
?>

<style>
	.mh-fall-wrapper { max-width: 900px; margin: 0 auto; font-family: inherit; }
	.mh-fall-header { background: #003E7E; color: #fff; padding: 20px; border-radius: 4px; margin-bottom: 20px; }
	.mh-fall-header h2 { margin: 0 0 8px 0; color: #fff; }
	.mh-fall-meta { font-size: 0.9em; opacity: 0.9; }
	.mh-status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; text-transform: uppercase; margin-left: 10px; }
	.badge-open { background: #e8f5e9; color: #1b5e20; }
	.badge-closed { background: #eee; color: #555; }
	.badge-draft { background: #fff3e0; color: #e65100; }
	.badge-final { background: #e3f2fd; color: #0d47a1; }
	.mh-step-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px; padding: 18px 20px; }
	.mh-step-card h4 { margin: 0 0 8px 0; }
	.mh-step-meta { font-size: 0.85em; color: #666; margin-bottom: 12px; }
	.mh-blocked-note { text-align: center; padding: 15px; color: #666; font-style: italic; }

	/* Einheitliches, selbst-enthaltenes Button-Design für die gesamte Seite —
	   bewusst NICHT auf WordPress' .button/.button-primary verlassen, da diese
	   Backend-Klassen im Frontend (auf dieser Shortcode-Seite) oft gar nicht
	   geladen sind und Buttons dann uneinheitlich/unstyled aussehen. */
	.mh-btn { display: inline-block; text-decoration: none; font-family: inherit; font-size: 0.85em; font-weight: 500; padding: 7px 14px; border-radius: 4px; border: 1px solid #ccc; color: #333; background: #fff; cursor: pointer; line-height: 1.4; text-align: center; }
	.mh-btn:hover { background: #f2f2f2; border-color: #999; color: #333; }
	.mh-btn-primary { background: #0073aa; border-color: #0073aa; color: #fff; }
	.mh-btn-primary:hover { background: #005d8a; border-color: #005d8a; color: #fff; }
	.mh-btn-small { font-size: 0.78em; padding: 5px 10px; }
	.mh-step-actions .mh-btn, .mh-admin-actions .mh-btn { margin-right: 8px; }
	.mh-step-details { margin-top: 12px; }
	.mh-step-details summary { cursor: pointer; font-size: 0.85em; color: #0073aa; font-weight: 600; padding: 4px 0; list-style: none; }
	.mh-step-details summary::-webkit-details-marker { display: none; }
	.mh-step-details summary::before { content: '▸ '; }
	.mh-step-details[open] summary::before { content: '▾ '; }
	.mh-step-details summary:hover { text-decoration: underline; }
	.mh-detail-table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fafafa; border: 1px solid #eee; border-radius: 4px; }
	.mh-detail-table th, .mh-detail-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.85em; vertical-align: top; }
	.mh-detail-table th { width: 35%; font-weight: 600; color: #555; background: #f0f0f0; }
	.mh-detail-table tr:last-child th, .mh-detail-table tr:last-child td { border-bottom: none; }
	.mh-admin-actions { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; }
	.mh-step-name-hint { display: block; font-weight: normal; font-size: 0.85em; color: #666; margin-top: 2px; }

	.mh-next-step-section { margin-top: 30px; }
	.mh-next-step-section h3 { margin-bottom: 15px; }
	.mh-next-step-group { margin-bottom: 22px; }
	.mh-next-step-group-title { font-size: 0.75em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin: 0 0 10px 0; }
	.mh-option-card { display: block; background: #fff; border: 1px solid #ddd; border-left: 4px solid #0073aa; border-radius: 4px; padding: 14px 18px; margin-bottom: 10px; text-decoration: none !important; color: inherit; transition: box-shadow 0.15s, transform 0.1s; }
	.mh-option-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: translateY(-1px); }
	.mh-option-card-title { font-weight: 600; color: #0073aa; margin: 0 0 4px 0; }
	.mh-option-card-sub { font-size: 0.85em; color: #666; }
	.mh-option-card-locked { border-left-color: #ccc; background: #f7f7f7; cursor: not-allowed; }
	.mh-option-card-locked:hover { box-shadow: none; transform: none; }
	.mh-option-card-locked .mh-option-card-title { color: #888; }
	.mh-option-card-hint { font-size: 0.8em; color: #b26a00; font-style: italic; margin-top: 6px; }

	.mh-fall-header-grid { display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap; }
	.mh-fall-header-main { flex: 1 1 280px; }
	.mh-fall-header-contacts { flex: 0 1 280px; background: rgba(255,255,255,0.1); border-radius: 4px; padding: 12px 15px; font-size: 0.85em; }
	.mh-contacts-title { font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85em; opacity: 0.85; margin-bottom: 8px; }
	.mh-contact-row { margin-bottom: 8px; line-height: 1.4; }
	.mh-contact-row:last-of-type { margin-bottom: 0; }
	.mh-contact-role { opacity: 0.75; font-size: 0.9em; }
	.mh-contacts-empty { opacity: 0.7; font-style: italic; margin-bottom: 8px; }
	.mh-contacts-edit-link { color: #fff; opacity: 0.9; font-size: 0.85em; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.4); border-radius: 4px; padding: 5px 10px; display: inline-block; margin-top: 8px; cursor: pointer; text-decoration: none; }
	.mh-contacts-edit-link:hover { opacity: 1; background: rgba(255,255,255,0.25); }

	/* Modale Dialoge (natives <dialog>) für Kontakte-bearbeiten und Notiz-hinzufügen */
	dialog.mh-modal { border: none; border-radius: 8px; padding: 0; max-width: 640px; width: 92%; box-shadow: 0 10px 40px rgba(0,0,0,0.25); }
	dialog.mh-modal::backdrop { background: rgba(0,0,0,0.5); }
	.mh-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 22px; border-bottom: 1px solid #eee; }
	.mh-modal-header h3 { margin: 0; font-size: 1.1em; }
	.mh-modal-close-x { background: none; border: none; font-size: 1.4em; cursor: pointer; color: #888; line-height: 1; padding: 0 4px; }
	.mh-modal-close-x:hover { color: #333; }
	.mh-modal-body { padding: 20px 22px; max-height: 60vh; overflow-y: auto; }
	.mh-modal-footer { padding: 14px 22px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }

	/* Kontakt-Karten im Modal (statt der gequetschten Grid-Zeile) */
	.mh-contact-card { border: 1px solid #ddd; border-radius: 6px; padding: 14px 16px; margin-bottom: 12px; position: relative; background: #fafafa; }
	.mh-contact-card-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
	.mh-contact-card label { display: flex; flex-direction: column; font-size: 0.78em; font-weight: 600; color: #555; gap: 4px; margin-top: 10px; }
	.mh-contact-card-grid label { margin-top: 0; }
	.mh-contact-card input, .mh-contact-card select { font-weight: normal; padding: 7px 9px; border: 1px solid #aaa; border-radius: 4px; font-size: 0.95em; }
	.mh-contact-remove-row { position: absolute; top: 10px; right: 10px; background: #fff; border: 1px solid #d63638; color: #d63638; border-radius: 50%; width: 24px; height: 24px; line-height: 1; cursor: pointer; font-size: 0.8em; }
	@media (max-width: 480px) { .mh-contact-card-grid { grid-template-columns: 1fr; } }

	/* Notizen: kompakte Anzeige, Erfassung über Modal */
	.mh-notes-section { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px; }
	.mh-notes-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
	.mh-notes-header h3 { margin: 0; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 700; }
	.mh-notes-list { max-height: 180px; overflow-y: auto; }
	.mh-note-item { position: relative; border-left: 3px solid #0073aa; background: #f7faff; padding: 6px 30px 6px 10px; margin-bottom: 6px; border-radius: 0 4px 4px 0; font-size: 0.82em; }
	.mh-note-delete { position: absolute; top: 4px; right: 6px; font-size: 0.95em; text-decoration: none; opacity: 0.55; }
	.mh-note-delete:hover { opacity: 1; }
	.mh-note-text { white-space: pre-wrap; margin-bottom: 3px; }
	.mh-note-meta { font-size: 0.78em; color: #888; }
	.mh-note-empty { color: #888; font-style: italic; font-size: 0.85em; margin: 0; }
	.mh-note-modal-textarea { width: 100%; min-height: 150px; padding: 10px 12px; border: 1px solid #aaa; border-radius: 4px; font-size: 0.95em; }
</style>

<div class="mh-fall-wrapper">
	<div class="mh-fall-header">
		<div class="mh-fall-header-grid">
			<div class="mh-fall-header-main">
				<h2>
					<?= esc_html( $case_meta['lastname'] ?? '' ) ?>, <?= esc_html( $case_meta['firstname'] ?? '' ) ?>
					<span class="mh-status-badge <?= $is_closed ? 'badge-closed' : 'badge-open' ?>"><?= $is_closed ? 'Geschlossen' : 'Offen' ?></span>
				</h2>
				<div class="mh-fall-meta">
					Klasse <?= esc_html( $case_meta['class_name'] ?? '' ) ?>
					&nbsp;·&nbsp; Klassenleitung <?= esc_html( $case_meta['teacher'] ?? '' ) ?>
					&nbsp;·&nbsp; <?= ! empty( $case_meta['is_minor'] ) ? 'minderjährig' : 'volljährig' ?>
					&nbsp;·&nbsp; <?= ! empty( $case_meta['is_schulpflichtig'] ) ? 'schulpflichtig' : 'nicht schulpflichtig' ?>
				</div>
			</div>
			<div class="mh-fall-header-contacts">
				<div class="mh-contacts-title">Kontakte</div>
				<?php if ( empty( $contacts ) ) : ?>
					<div class="mh-contacts-empty">Keine Kontakte hinterlegt</div>
				<?php else : foreach ( $contacts as $contact ) : ?>
					<div class="mh-contact-row">
						<strong><?= esc_html( $contact['name'] ?? '' ) ?></strong>
						<span class="mh-contact-role">(<?= esc_html( $contact_roles[ $contact['role'] ?? '' ] ?? ( $contact['role'] ?? '' ) ) ?>)</span><br>
						<?php if ( ! empty( $contact['phone'] ) ) : ?>📞 <?= esc_html( $contact['phone'] ) ?><?php endif; ?>
						<?php if ( ! empty( $contact['phone'] ) && ! empty( $contact['email'] ) ) : ?> &nbsp;·&nbsp; <?php endif; ?>
						<?php if ( ! empty( $contact['email'] ) ) : ?>✉️ <?= esc_html( $contact['email'] ) ?><?php endif; ?>
						<?php if ( ! empty( $contact['note'] ) ) : ?><br><span style="opacity:0.8;"><?= esc_html( $contact['note'] ) ?></span><?php endif; ?>
					</div>
				<?php endforeach; endif; ?>
				<?php if ( $can_manage ) : ?>
					<button type="button" class="mh-contacts-edit-link" onclick="document.getElementById('mh-contacts-modal').showModal();">✏️ Kontakte bearbeiten</button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( $can_manage ) : ?>
		<dialog id="mh-contacts-modal" class="mh-modal">
			<div class="mh-modal-header">
				<h3>Kontakte bearbeiten</h3>
				<button type="button" class="mh-modal-close-x" onclick="document.getElementById('mh-contacts-modal').close();">&times;</button>
			</div>
			<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" id="mh-contacts-form">
				<input type="hidden" name="action" value="mh_absentismus_update_contacts">
				<input type="hidden" name="case_id" value="<?= (int) $case_id ?>">
				<?php wp_nonce_field( 'mh_absentismus_update_contacts' ); ?>
				<div class="mh-modal-body">
					<div id="mh-contact-rows">
						<?php
						$edit_rows = ! empty( $contacts ) ? $contacts : [ [] ];
						foreach ( $edit_rows as $contact ) :
						?>
							<div class="mh-contact-card">
								<button type="button" class="mh-contact-remove-row" title="Entfernen" onclick="this.closest('.mh-contact-card').remove();">✕</button>
								<div class="mh-contact-card-grid">
									<label>Name
										<input type="text" name="contact_name[]" value="<?= esc_attr( $contact['name'] ?? '' ) ?>">
									</label>
									<label>Rolle
										<select name="contact_role[]">
											<option value="">-- Rolle --</option>
											<?php foreach ( $contact_roles as $key => $label ) : ?>
												<option value="<?= esc_attr( $key ) ?>" <?= selected( $contact['role'] ?? '', $key ) ?>><?= esc_html( $label ) ?></option>
											<?php endforeach; ?>
										</select>
									</label>
									<label>Telefon
										<input type="text" name="contact_phone[]" value="<?= esc_attr( $contact['phone'] ?? '' ) ?>">
									</label>
									<label>E-Mail
										<input type="email" name="contact_email[]" value="<?= esc_attr( $contact['email'] ?? '' ) ?>">
									</label>
								</div>
								<label>Bemerkung
									<input type="text" name="contact_note[]" value="<?= esc_attr( $contact['note'] ?? '' ) ?>">
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="mh-btn mh-btn-small" id="mh-contact-add-row">+ Kontakt hinzufügen</button>
				</div>
				<div class="mh-modal-footer">
					<button type="button" class="mh-btn" onclick="document.getElementById('mh-contacts-modal').close();">Abbrechen</button>
					<button type="submit" class="mh-btn mh-btn-primary">Speichern</button>
				</div>
			</form>
		</dialog>

		<script>
		document.getElementById( 'mh-contact-add-row' ).addEventListener( 'click', function () {
			const rows = document.getElementById( 'mh-contact-rows' );
			const firstRow = rows.querySelector( '.mh-contact-card' );
			const newRow = firstRow.cloneNode( true );
			newRow.querySelectorAll( 'input' ).forEach( function ( el ) { el.value = ''; } );
			newRow.querySelectorAll( 'select' ).forEach( function ( el ) { el.selectedIndex = 0; } );
			rows.appendChild( newRow );
		} );
		</script>
	<?php endif; ?>

	<div class="mh-notes-section">
		<div class="mh-notes-header">
			<h3>📝 Notizen<?= ! empty( $notes ) ? ' (' . count( $notes ) . ')' : '' ?></h3>
			<?php if ( $can_manage ) : ?>
				<button type="button" class="mh-btn mh-btn-small" onclick="document.getElementById('mh-note-modal').showModal();">+ Notiz</button>
			<?php endif; ?>
		</div>
		<?php if ( empty( $notes ) ) : ?>
			<p class="mh-note-empty">Noch keine Notizen vorhanden.</p>
		<?php else : ?>
			<div class="mh-notes-list">
				<?php
				// array_reverse( ..., true ) erhält die Original-Keys — die braucht
				// delete_note() im Repository, um die Notiz eindeutig zu identifizieren
				// (Anzeige ist neueste zuerst, Speicherung bleibt chronologisch).
				foreach ( array_reverse( $notes, true ) as $note_index => $note ) :
					$author = get_userdata( (int) ( $note['created_by'] ?? 0 ) );
					$delete_url = $nonce_url(
						[ 'action' => 'mh_absentismus_delete_note', 'case_id' => $case_id, 'note_index' => $note_index ],
						'mh_absentismus_delete_note_' . $case_id . '_' . $note_index
					);
				?>
					<div class="mh-note-item">
						<?php if ( $can_manage ) : ?>
							<a href="<?= esc_url( $delete_url ) ?>" class="mh-note-delete" title="Notiz löschen" onclick="return confirm('Diese Notiz wirklich löschen?');">🗑️</a>
						<?php endif; ?>
						<div class="mh-note-text"><?= nl2br( esc_html( $note['text'] ?? '' ) ) ?></div>
						<div class="mh-note-meta">
							<?= esc_html( $author ? $author->display_name : 'Unbekannt' ) ?>
							&nbsp;·&nbsp; <?= esc_html( date( 'd.m.Y H:i', strtotime( $note['created_at'] ?? 'now' ) ) ) ?> Uhr
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $can_manage ) : ?>
		<dialog id="mh-note-modal" class="mh-modal">
			<div class="mh-modal-header">
				<h3>Neue Notiz</h3>
				<button type="button" class="mh-modal-close-x" onclick="document.getElementById('mh-note-modal').close();">&times;</button>
			</div>
			<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
				<input type="hidden" name="action" value="mh_absentismus_add_note">
				<input type="hidden" name="case_id" value="<?= (int) $case_id ?>">
				<?php wp_nonce_field( 'mh_absentismus_add_note' ); ?>
				<div class="mh-modal-body">
					<textarea name="note_text" class="mh-note-modal-textarea" placeholder="Notiz eingeben…" required></textarea>
				</div>
				<div class="mh-modal-footer">
					<button type="button" class="mh-btn" onclick="document.getElementById('mh-note-modal').close();">Abbrechen</button>
					<button type="submit" class="mh-btn mh-btn-primary">Speichern</button>
				</div>
			</form>
		</dialog>
	<?php endif; ?>

	<?php if ( empty( $steps ) ) : ?>
		<p>Dieser Fall hat noch keine dokumentierten Schritte.</p>
	<?php else : foreach ( $steps as $step ) :
		$step_no    = (int) $step['step_no'];
		$is_draft   = 'draft' === $step['status'];
		$pdf_url    = $nonce_url( [ 'action' => 'mh_absentismus_download_pdf', 'case_id' => $case_id, 'step_no' => $step_no ], 'mh_absentismus_pdf_' . $case_id . '_' . $step_no );
		$edit_url   = add_query_arg( [ 'mh_case_id' => $case_id, 'mh_edit_step' => $step_no ], $self_base );
		$final_url  = $nonce_url( [ 'action' => 'mh_absentismus_finalize_step', 'case_id' => $case_id, 'step_no' => $step_no ], 'mh_absentismus_finalize_' . $case_id . '_' . $step_no );
		$can_edit_this = $can_manage && ( $is_draft || $is_admin );
	?>
		<div class="mh-step-card">
			<h4>
				Schritt <?= $step_no ?>: <?= esc_html( $step_labels[ $step['type'] ] ?? $step['type'] ) ?>
				<span class="mh-status-badge <?= $is_draft ? 'badge-draft' : 'badge-final' ?>"><?= $is_draft ? 'Entwurf' : 'Festgeschrieben' ?></span>
			</h4>
			<div class="mh-step-meta">
				Angelegt am <?= esc_html( date( 'd.m.Y H:i', strtotime( $step['created_at'] ) ) ) ?>
				<?php if ( ! $is_draft && ! empty( $step['finalized_at'] ) ) : ?>
					&nbsp;·&nbsp; Festgeschrieben am <?= esc_html( date( 'd.m.Y H:i', strtotime( $step['finalized_at'] ) ) ) ?>
				<?php endif; ?>
			</div>
			<div class="mh-step-actions">
				<a class="mh-btn" href="<?= esc_url( $pdf_url ) ?>" target="_blank">📄 PDF</a>
				<?php if ( $can_edit_this ) : ?>
					<a class="mh-btn" href="<?= esc_url( $edit_url ) ?>">✏️ Bearbeiten</a>
				<?php endif; ?>
				<?php if ( $is_draft && $can_manage ) : ?>
					<a class="mh-btn" href="<?= esc_url( $final_url ) ?>" onclick="return confirm('Diesen Schritt festschreiben? Danach ist er für die Klassenleitung nicht mehr änderbar.');">🔒 Festschreiben</a>
				<?php endif; ?>
			</div>
			<?php
			$detail_rows = [];
			foreach ( $step['data'] ?? [] as $field => $value ) {
				$row = $format_step_field( (string) $field, $value );
				if ( null !== $row ) {
					$detail_rows[] = $row;
				}
			}
			?>
			<?php if ( ! empty( $detail_rows ) ) : ?>
				<details class="mh-step-details">
					<summary>Details anzeigen</summary>
					<table class="mh-detail-table">
						<?php foreach ( $detail_rows as $row ) : ?>
							<tr>
								<th><?= esc_html( $row['label'] ) ?></th>
								<td><?= nl2br( esc_html( $row['value'] ) ) ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</details>
			<?php endif; ?>
		</div>
	<?php endforeach; endif; ?>

	<?php if ( $is_closed ) : ?>
		<p class="mh-blocked-note">Dieser Fall ist abgeschlossen. Es können keine weiteren Schritte angelegt werden.</p>
	<?php elseif ( ! empty( $step_overview ) && $can_manage ) :
		// Unabhängig auslösbare Vergehen (kein requires/step_match — z. B. Ordnungsamt,
		// Attestauflage, die 15-Tage-Variante von Beendigung §47) werden bewusst von
		// den Schritten der eigentlichen Eskalationskette (nur der Reihe nach möglich)
		// getrennt dargestellt, damit nicht der Eindruck einer festen Abfolge entsteht.
		$independent_entries = array_values( array_filter( $step_overview, static fn( array $e ): bool => ! $e['sequential'] ) );
		$sequential_entries  = array_values( array_filter( $step_overview, static fn( array $e ): bool => $e['sequential'] ) );
	?>
		<div class="mh-next-step-section">
			<h3>Weitere Eskalationsstufe</h3>

			<?php if ( ! empty( $independent_entries ) ) : ?>
				<div class="mh-next-step-group">
					<p class="mh-next-step-group-title">Unabhängig möglich</p>
					<?php foreach ( $independent_entries as $entry ) : ?>
						<a class="mh-option-card" href="<?= esc_url( add_query_arg( [ 'mh_case_id' => $case_id, 'mh_new_step' => $entry['type'] ], $self_base ) ) ?>">
							<div class="mh-option-card-title"><?= esc_html( $entry['condition'] ?: ( $step_labels[ $entry['type'] ] ?? $entry['type'] ) ) ?></div>
							<div class="mh-option-card-sub">→ <?= esc_html( $step_labels[ $entry['type'] ] ?? $entry['type'] ) ?></div>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $sequential_entries ) ) : ?>
				<div class="mh-next-step-group">
					<p class="mh-next-step-group-title">Eskalationskette (der Reihe nach)</p>
					<?php foreach ( $sequential_entries as $entry ) :
						$type           = $entry['type'];
						$condition_text = $entry['condition'] ?: ( $step_labels[ $type ] ?? $type );
						$step_name      = $step_labels[ $type ] ?? $type;
					?>
						<?php if ( $entry['available'] ) : ?>
							<a class="mh-option-card" href="<?= esc_url( add_query_arg( [ 'mh_case_id' => $case_id, 'mh_new_step' => $type ], $self_base ) ) ?>">
								<div class="mh-option-card-title"><?= esc_html( $condition_text ) ?></div>
								<div class="mh-option-card-sub">→ <?= esc_html( $step_name ) ?></div>
							</a>
						<?php else :
							if ( ! empty( $entry['locked_hint'] ) ) {
								$hint = $entry['locked_hint'];
							} else {
								$missing_labels = array_map( static fn( $t ) => $step_labels[ $t ] ?? $t, $entry['missing'] );
								$hint = 'Wird verfügbar, sobald ' . implode( ' und ', $missing_labels ) . ( count( $missing_labels ) > 1 ? ' festgeschrieben sind.' : ' festgeschrieben ist.' );
							}
						?>
							<div class="mh-option-card mh-option-card-locked">
								<div class="mh-option-card-title">🔒 <?= esc_html( $condition_text ) ?></div>
								<div class="mh-option-card-sub">→ <?= esc_html( $step_name ) ?></div>
								<div class="mh-option-card-hint"><?= esc_html( $hint ) ?></div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php elseif ( empty( $steps ) ) : ?>
		<p class="mh-blocked-note">Für diesen Fall ist aktuell kein Einstiegs-Schritt zulässig.</p>
	<?php endif; ?>

	<?php if ( $can_manage ) : ?>
		<div class="mh-admin-actions">
			<?php if ( $is_closed ) : ?>
				<a class="mh-btn" href="<?= esc_url( $nonce_url( [ 'action' => 'mh_absentismus_reopen_case', 'case_id' => $case_id ], 'mh_absentismus_reopen_' . $case_id ) ) ?>">Fall wieder öffnen</a>
			<?php else : ?>
				<a class="mh-btn" href="<?= esc_url( $nonce_url( [ 'action' => 'mh_absentismus_close_case', 'case_id' => $case_id ], 'mh_absentismus_close_' . $case_id ) ) ?>" onclick="return confirm('Fall manuell schließen?');">Fall schließen</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
