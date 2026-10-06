<?php
/**
 * View: Admin-Übersicht der digitalen Noteneinsammlung.
 *
 * @var array  $rows          Je Fall: ['case' => array, 'diag' => array]
 * @var array  $summary       open_cases, open_items, problem_cases, closed_cases
 * @var array  $system_checks Je Eintrag: level (ok|info|warn|error), text
 * @var string $f_status      offen | abgeschlossen | alle
 * @var bool   $f_problems    nur Fälle mit Problemen
 * @var string $notice        Rückmeldung einer Aktion
 * @var string $admin_url     URL dieser Seite
 * @var \Mh\FormWorkflows\Service\Reminder_Service $reminder
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$icon = [ 'ok' => '✅', 'info' => 'ℹ️', 'warn' => '⚠️', 'error' => '❌' ];

$fmt_dt = static function ( $value, string $format = 'd.m.Y H:i' ): string {
	if ( empty( $value ) ) {
		return '–';
	}
	$ts = strtotime( (string) $value );
	return false === $ts ? '–' : date_i18n( $format, $ts );
};

$status_label = [
	'erledigt' => '<span class="mh-na-badge mh-na-ok">erledigt</span>',
	'offen'    => '<span class="mh-na-badge mh-na-open">offen</span>',
];
$role_label = [ 'vorab' => 'vorab (Klassenleitung)', 'fachlehrer' => 'Fachlehrkraft', 'klassenlehrer' => 'Nachtrag Klassenleitung' ];
?>
<style>
	.mh-na-cards { display:flex; gap:12px; flex-wrap:wrap; margin:15px 0; }
	.mh-na-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 18px; min-width:150px; }
	.mh-na-card strong { display:block; font-size:24px; line-height:1.2; }
	.mh-na-card.mh-na-alert strong { color:#b32d2e; }
	.mh-na-checks { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:8px 14px; margin-bottom:15px; }
	.mh-na-checks li { margin:4px 0; }
	.mh-na-badge { display:inline-block; padding:1px 7px; border-radius:3px; font-size:11px; font-weight:600; }
	.mh-na-ok { background:#e7f5ea; color:#1e6b30; }
	.mh-na-open { background:#fff4e0; color:#8a5a00; }
	.mh-na-closed { background:#f0f0f1; color:#50575e; }
	.mh-na-problems { margin:0; padding-left:0; list-style:none; }
	.mh-na-problems li { margin:2px 0; }
	.mh-na-level-error { color:#b32d2e; }
	.mh-na-level-warn { color:#8a5a00; }
	.mh-na-bar { background:#f0f0f1; border-radius:3px; height:8px; width:100%; margin-top:4px; overflow:hidden; }
	.mh-na-bar span { display:block; height:100%; background:#2271b1; }
	details.mh-na-details summary { cursor:pointer; color:#2271b1; }
	table.mh-na-items { margin-top:8px; background:#fff; }
	table.mh-na-items td, table.mh-na-items th { font-size:12px; padding:4px 8px; }
	tr.mh-na-row-error > td { background:#fcf0f1 !important; }
</style>

<div class="wrap">
	<h1 class="wp-heading-inline">Noteneinsammlung</h1>
	<hr class="wp-header-end">

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?= esc_html( $notice ) ?></p></div>
	<?php endif; ?>

	<div class="mh-na-cards">
		<div class="mh-na-card"><strong><?= (int) $summary['open_cases'] ?></strong>laufende Einsammlungen</div>
		<div class="mh-na-card"><strong><?= (int) $summary['open_items'] ?></strong>ausstehende Noten</div>
		<div class="mh-na-card <?= $summary['problem_cases'] > 0 ? 'mh-na-alert' : '' ?>"><strong><?= (int) $summary['problem_cases'] ?></strong>Fälle mit Fehlern</div>
		<div class="mh-na-card"><strong><?= (int) $summary['closed_cases'] ?></strong>abgeschlossen</div>
	</div>

	<div class="mh-na-checks">
		<strong>Systemprüfung</strong>
		<ul>
			<?php foreach ( $system_checks as $c ) : ?>
				<li class="mh-na-level-<?= esc_attr( $c['level'] ) ?>"><?= $icon[ $c['level'] ] ?? '' ?> <?= esc_html( $c['text'] ) ?></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<form method="get" style="margin:10px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
		<input type="hidden" name="page" value="<?= esc_attr( \Mh\FormWorkflows\Controller\Noten_Controller::ADMIN_SLUG ) ?>">
		<select name="status">
			<option value="offen" <?= selected( $f_status, 'offen', false ) ?>>Laufende</option>
			<option value="abgeschlossen" <?= selected( $f_status, 'abgeschlossen', false ) ?>>Abgeschlossene</option>
			<option value="alle" <?= selected( $f_status, 'alle', false ) ?>>Alle</option>
		</select>
		<label><input type="checkbox" name="probleme" value="1" <?= checked( $f_problems, true, false ) ?>> nur Fälle mit Hinweisen</label>
		<?php submit_button( 'Filtern', 'secondary', '', false ); ?>
		<span class="description">Fälle mit Fehlern stehen oben, danach die ältesten.</span>
	</form>

	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th width="110">Gestartet</th>
				<th width="190">Schüler*in / Klasse</th>
				<th width="140">Klassenleitung</th>
				<th width="130">Fortschritt</th>
				<th>Hinweise</th>
				<th width="170">Aktionen</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rows ) ) : ?>
			<tr><td colspan="6"><em>Keine Noteneinsammlung passt zum Filter.</em></td></tr>
		<?php endif; ?>

		<?php foreach ( $rows as $row ) :
			$case      = $row['case'];
			$diag      = $row['diag'];
			$fd        = $case['form_data'];
			$case_id   = (int) $case['id'];
			$is_open   = 'offen' === ( $case['status'] ?? '' );
			$total     = count( $fd['items'] ?? [] );
			$done      = $total - $diag['open_count'];
			$owner_id  = (int) ( $fd['owner_user_id'] ?? $case['user_id'] ?? 0 );
			$owner     = $owner_id > 0 ? get_userdata( $owner_id ) : false;
			$sub_id    = (int) ( $fd['submission_id'] ?? 0 );
		?>
			<tr class="<?= $diag['has_error'] ? 'mh-na-row-error' : '' ?>">
				<td>
					<?= esc_html( $fmt_dt( $fd['started_at'] ?? $case['created_at'], 'd.m.Y' ) ) ?><br>
					<small>#<?= $case_id ?></small>
				</td>
				<td>
					<strong><?= esc_html( strtoupper( (string) ( $fd['lastname'] ?? '' ) ) ) ?>, <?= esc_html( (string) ( $fd['firstname'] ?? '' ) ) ?></strong><br>
					<code><?= esc_html( (string) ( $fd['class_name'] ?? '' ) ) ?></code>
				</td>
				<td><?= esc_html( $owner ? $owner->display_name : '–' ) ?></td>
				<td>
					<?php if ( $is_open ) : ?>
						<span class="mh-na-badge mh-na-open">läuft</span>
					<?php else : ?>
						<span class="mh-na-badge mh-na-closed"><?= ! empty( $fd['ended_early'] ) ? 'vorzeitig beendet' : 'abgeschlossen' ?></span><br>
						<small><?= esc_html( $fmt_dt( $fd['completed_at'] ?? '', 'd.m.Y' ) ) ?></small>
						<?php if ( ! empty( $fd['owner_done_at'] ) ) : ?>
							<br><small>PDF erledigt <?= esc_html( $fmt_dt( $fd['owner_done_at'], 'd.m.Y' ) ) ?></small>
						<?php endif; ?>
					<?php endif; ?>
					<div><?= (int) $done ?> von <?= (int) $total ?> Noten</div>
					<div class="mh-na-bar"><span style="width:<?= $total > 0 ? (int) round( 100 * $done / $total ) : 0 ?>%;"></span></div>
				</td>
				<td>
					<?php if ( empty( $diag['problems'] ) ) : ?>
						<span class="description"><?= $is_open ? 'keine Auffälligkeiten' : '–' ?></span>
					<?php else : ?>
						<ul class="mh-na-problems">
							<?php foreach ( $diag['problems'] as $p ) : ?>
								<li class="mh-na-level-<?= esc_attr( $p['level'] ) ?>"><?= $icon[ $p['level'] ] ?? '' ?> <?= esc_html( $p['text'] ) ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<details class="mh-na-details">
						<summary>Fächer im Detail</summary>
						<table class="widefat mh-na-items">
							<thead>
								<tr>
									<th>Fach</th><th>Lehrkraft</th><th>Status</th><th>Eingeladen</th>
									<th>Erinnert</th><th>Eingetragen</th><th></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $fd['items'] ?? [] as $item ) :
								$idx      = (int) $item['idx'];
								$is_done  = 'erledigt' === ( $item['status'] ?? 'offen' );
								$collect  = '1' === ( $item['collect'] ?? '0' );
								$item_pr  = $diag['items'][ $idx ]['problems'] ?? [];
							?>
								<tr>
									<td><?= esc_html( (string) $item['subject'] ) ?></td>
									<td>
										<?= esc_html( (string) $item['teacher_kuerzel'] ) ?>
										<?php if ( '' !== (string) ( $item['recipient_email'] ?? '' ) ) : ?>
											<br><small><?= esc_html( (string) $item['recipient_email'] ) ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?= $status_label[ $is_done ? 'erledigt' : 'offen' ] ?>
										<?php if ( ! $collect ) : ?><br><small>nicht angefragt</small><?php endif; ?>
									</td>
									<td><?= $collect ? esc_html( $fmt_dt( $item['notified_at'] ?? '' ) ) : '–' ?></td>
									<td>
										<?= (int) ( $item['reminder_count'] ?? 0 ) ?>×
										<?php if ( ! empty( $item['last_reminder_at'] ) ) : ?><br><small>zuletzt <?= esc_html( $fmt_dt( $item['last_reminder_at'], 'd.m.Y' ) ) ?></small><?php endif; ?>
										<?php if ( ! empty( $item['escalated_at'] ) ) : ?><br><small>eskaliert <?= esc_html( $fmt_dt( $item['escalated_at'], 'd.m.Y' ) ) ?></small><?php endif; ?>
									</td>
									<td>
										<?php if ( $is_done ) : ?>
											<?= esc_html( $fmt_dt( $item['entered_at'] ?? '' ) ) ?>
											<br><small><?= esc_html( $role_label[ $item['entered_by_role'] ?? '' ] ?? '' ) ?></small>
										<?php else : ?>
											–
										<?php endif; ?>
									</td>
									<td>
										<?php foreach ( $item_pr as $p ) : ?>
											<div class="mh-na-level-<?= esc_attr( $p['level'] ) ?>"><?= $icon[ $p['level'] ] ?? '' ?> <?= esc_html( $p['text'] ) ?></div>
										<?php endforeach; ?>
										<?php if ( $is_open && ! $is_done && $collect ) : ?>
											<a href="<?= esc_url( wp_nonce_url(
												add_query_arg( [
													'action'   => 'mh_noten_remind_now',
													'case_id'  => $case_id,
													'item_idx' => $idx,
													'from'     => 'admin',
												], admin_url( 'admin-post.php' ) ),
												'mh_noten_remind'
											) ) ?>"><?= empty( $item['notified_at'] ) ? 'Einladung senden' : 'Jetzt erinnern' ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				</td>
				<td>
					<a class="button button-small" href="<?= esc_url( $reminder->case_link( $case_id ) ) ?>" target="_blank">Fall öffnen</a>
					<?php if ( $diag['submission_exists'] ) : ?>
						<a class="button button-small" target="_blank" href="<?= esc_url( wp_nonce_url(
							admin_url( 'admin.php?page=mh-form-admin-list&mh_admin_action=download&id=' . $sub_id ),
							'mh_admin_action_' . $sub_id
						) ) ?>">PDF</a>
					<?php endif; ?>
					<?php if ( $diag['stuck'] ) : ?>
						<br><a class="button button-small button-primary" style="margin-top:4px;" href="<?= esc_url( wp_nonce_url(
							add_query_arg( [ 'action' => 'mh_noten_admin_complete', 'case_id' => $case_id ], admin_url( 'admin-post.php' ) ),
							'mh_noten_admin_complete_' . $case_id
						) ) ?>">Abschluss nachholen</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
