<?php
/**
 * View: Übersicht aller Absentismus-Fälle.
 *
 * @var array $cases
 * @var bool  $is_admin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';
$self_base   = get_permalink() ?: '';

$options      = get_option( 'mh_fw_settings', [] );
$fall_page_id = (int) ( $options['page_id_mh_absentismus_fall'] ?? 0 );
$fall_base    = $fall_page_id > 0 ? ( get_permalink( $fall_page_id ) ?: '' ) : '';

$nonce_url = fn( array $params, string $nonce_action ) => wp_nonce_url(
	add_query_arg( $params, admin_url( 'admin-post.php' ) ),
	$nonce_action
);
?>

<style>
	.mh-dashboard { max-width: 1100px; margin: 20px auto; font-family: inherit; }
	.mh-dash-table { width: 100%; border-collapse: collapse; background: #fff; }
	.mh-dash-table th, .mh-dash-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
	.mh-dash-table th { background: #f8f9fa; color: #666; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
	.mh-status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; text-transform: uppercase; margin-left: 4px; }
	.badge-open { background: #e8f5e9; color: #1b5e20; }
	.badge-closed { background: #eee; color: #555; }
	.badge-archived { background: #fdecea; color: #a13a2f; }
	.mh-filters { margin-bottom: 15px; display: flex; align-items: center; gap: 15px; }
	.mh-filters select { padding: 6px 10px; }
	.mh-row-actions a { font-size: 0.85em; text-decoration: none; }
	.mh-bulk-bar { margin-bottom: 10px; }
</style>

<div class="mh-dashboard">
	<h2>Absentismus-Fälle</h2>

	<form method="get" class="mh-filters">
		<select name="status" onchange="this.form.submit()">
			<option value="">-- Alle Status --</option>
			<option value="offen" <?= selected( $_GET['status'] ?? '', 'offen' ) ?>>Offen</option>
			<option value="geschlossen" <?= selected( $_GET['status'] ?? '', 'geschlossen' ) ?>>Geschlossen</option>
		</select>
		<?php if ( $is_admin ) : ?>
			<label>
				<input type="checkbox" name="show_archived" value="1" <?= checked( isset( $_GET['show_archived'] ) ) ?> onchange="this.form.submit()">
				Archivierte einblenden
			</label>
		<?php endif; ?>
	</form>

	<?php if ( empty( $cases ) ) : ?>
		<p>Keine Fälle gefunden.</p>
	<?php else : ?>
		<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
			<input type="hidden" name="action" value="mh_absentismus_bulk_archive">
			<?php wp_nonce_field( 'bulk-absentismus-faelle' ); ?>

			<?php if ( $is_admin ) : ?>
				<div class="mh-bulk-bar">
					<button type="submit" class="button" onclick="return confirm('Ausgewählte Fälle archivieren? Sie bleiben erhalten, verschwinden aber aus der Standard-Übersicht.');">Ausgewählte archivieren</button>
				</div>
			<?php endif; ?>

			<table class="mh-dash-table">
				<thead>
					<tr>
						<?php if ( $is_admin ) : ?><th width="30"></th><?php endif; ?>
						<th>Schüler:in</th>
						<th>Klasse</th>
						<th>Klassenleitung</th>
						<th>Status</th>
						<th>Aktueller Schritt</th>
						<th>Angelegt am</th>
						<?php if ( $is_admin ) : ?><th>Aktionen</th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cases as $case ) :
						$meta        = $case['form_data'];
						$steps       = $meta['steps'] ?? [];
						$last_step   = empty( $steps ) ? null : end( $steps );
						$is_closed   = 'geschlossen' === $case['status'];
						$is_archived = ! empty( $case['archived_at'] );
						$url         = $fall_base ? add_query_arg( 'mh_case_id', $case['id'], $fall_base ) : '#';
					?>
						<tr>
							<?php if ( $is_admin ) : ?>
								<td><input type="checkbox" name="bulk_ids[]" value="<?= (int) $case['id'] ?>"></td>
							<?php endif; ?>
							<td><a href="<?= esc_url( $url ) ?>"><strong><?= esc_html( ( $meta['lastname'] ?? '' ) . ', ' . ( $meta['firstname'] ?? '' ) ) ?></strong></a></td>
							<td><?= esc_html( $meta['class_name'] ?? '-' ) ?></td>
							<td><?= esc_html( $meta['teacher'] ?? '-' ) ?></td>
							<td>
								<span class="mh-status-badge <?= $is_closed ? 'badge-closed' : 'badge-open' ?>"><?= $is_closed ? 'Geschlossen' : 'Offen' ?></span>
								<?php if ( $is_archived ) : ?><span class="mh-status-badge badge-archived">Archiviert</span><?php endif; ?>
							</td>
							<td><?= $last_step ? esc_html( $step_labels[ $last_step['type'] ] ?? $last_step['type'] ) : '-' ?></td>
							<td><?= esc_html( date( 'd.m.Y', strtotime( $case['created_at'] ) ) ) ?></td>
							<?php if ( $is_admin ) : ?>
								<td class="mh-row-actions">
									<?php if ( $is_archived ) : ?>
										<a href="<?= esc_url( $nonce_url( [ 'action' => 'mh_absentismus_unarchive_case', 'case_id' => $case['id'] ], 'mh_absentismus_unarchive_' . $case['id'] ) ) ?>">Wiederherstellen</a>
									<?php else : ?>
										<a href="<?= esc_url( $nonce_url( [ 'action' => 'mh_absentismus_archive_case', 'case_id' => $case['id'] ], 'mh_absentismus_archive_' . $case['id'] ) ) ?>" onclick="return confirm('Diesen Fall archivieren?');">Archivieren</a>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	<?php endif; ?>

	<?php if ( $fall_base ) : ?>
		<p style="margin-top:20px;"><a class="button button-primary" href="<?= esc_url( $fall_base ) ?>">Neuen Fall eröffnen</a></p>
	<?php endif; ?>
</div>
