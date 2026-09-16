<?php
/**
 * View: Klassenlehrer-Sicht auf eine Noteneinsammlung.
 *
 * @var array  $case
 * @var array  $form_errors
 * @var string $notice
 * @var int    $open_count
 * @var \Mh\FormWorkflows\Repository\Teacher_Account_Repository $account
 * @var \Mh\FormWorkflows\Service\Reminder_Service $reminder
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$fd        = $case['form_data'];
$items     = $fd['items'] ?? [];
$total     = count( $items );
$done      = $total - $open_count;
$percent   = $total > 0 ? (int) round( $done / $total * 100 ) : 0;
$is_closed = 'abgeschlossen' === ( $case['status'] ?? '' );

$dash_options = get_option( 'mh_fw_settings', [] );
$abm_page_id  = (int) ( $dash_options['page_id_abmeldung_student_v1'] ?? 0 );
?>

<style>
	.mh-fall { max-width: 1000px; margin: 25px auto; font-family: inherit; }
	.mh-fall table { width: 100%; border-collapse: collapse; background: #fff; }
	.mh-fall th, .mh-fall td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
	.mh-fall th { background: #f8f9fa; color: #666; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
	.mh-stamm { background: #f6f8fa; border-left: 4px solid #0073aa; padding: 14px 18px; margin-bottom: 18px; }
	.mh-progress { background: #eee; border-radius: 10px; height: 18px; overflow: hidden; margin: 12px 0 6px; }
	.mh-progress > div { background: #0073aa; height: 100%; }
	.mh-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; }
	.mh-badge-open { background: #fff8e5; color: #7a5b00; }
	.mh-badge-done { background: #e8f5e9; color: #1b5e20; }
	.mh-badge-warn { background: #fdecea; color: #a13a2f; }
	.mh-notice { background: #e8f5e9; color: #1b5e20; padding: 12px 16px; border-radius: 4px; margin-bottom: 18px; }
	.mh-done-box { background: #e8f5e9; border-left: 4px solid #1b5e20; padding: 14px 18px; margin-bottom: 18px; }
	.mh-fall a.mh-act { font-size: 0.85em; }
</style>

<div class="mh-fall">
	<h2>Noteneinsammlung – <?= esc_html( ( $fd['lastname'] ?? '' ) . ', ' . ( $fd['firstname'] ?? '' ) ) ?></h2>

	<?php if ( '' !== $notice ) : ?>
		<div class="mh-notice"><?= esc_html( $notice ) ?></div>
	<?php endif; ?>

	<div class="mh-stamm">
		<div><strong>Klasse:</strong> <?= esc_html( $fd['class_name'] ?? '' ) ?></div>
		<div><strong>Gestartet:</strong> <?= esc_html( $fd['started_at'] ?? '' ) ?></div>
		<div class="mh-progress"><div style="width: <?= (int) $percent ?>%;"></div></div>
		<div><strong><?= (int) $done ?></strong> von <strong><?= (int) $total ?></strong> Noten eingetragen</div>
	</div>

	<?php if ( $is_closed ) : ?>
		<div class="mh-done-box">
			<strong>✅ Alle Noten liegen vor.</strong> Die Noten wurden in das Abgangsformular übernommen.
			<?php if ( $abm_page_id > 0 ) : ?>
				Das fertige PDF lässt sich über
				<a href="<?= esc_url( get_permalink( $abm_page_id ) ?: '' ) ?>">das Abgangsformular</a>
				bzw. die Übersicht der eigenen Einsendungen herunterladen.
			<?php else : ?>
				Das fertige PDF lässt sich über die Übersicht der eigenen Einsendungen herunterladen.
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<table>
		<thead>
			<tr>
				<th width="18%">Fach</th>
				<th width="22%">Lehrkraft</th>
				<th width="8%">Note</th>
				<th width="14%">Status</th>
				<th width="20%">Eingetragen</th>
				<th width="18%"></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $items as $item ) :
			$is_done   = 'erledigt' === ( $item['status'] ?? 'offen' );
			$reminders = (int) ( $item['reminder_count'] ?? 0 );
			$entered   = '';
			if ( $is_done && ! empty( $item['entered_by'] ) ) {
				$u       = get_userdata( (int) $item['entered_by'] );
				$who     = $u ? $u->display_name : 'unbekannt';
				$role    = 'klassenlehrer' === ( $item['entered_by_role'] ?? '' ) ? ' (nachgetragen)' : '';
				$entered = $who . $role;
			}
		?>
			<tr>
				<td><strong><?= esc_html( $item['subject'] ?? '' ) ?></strong></td>
				<td>
					<?= esc_html( $account->get_display_name( (string) ( $item['teacher_kuerzel'] ?? '' ) ) ) ?>
					<?php if ( ! empty( $item['is_fallback'] ) ) : ?>
						<br><span class="mh-badge mh-badge-warn" title="Zustellung an die WordPress-Kontoadresse">Fallback-Adresse</span>
					<?php endif; ?>
				</td>
				<td><strong><?= esc_html( $item['grade'] ?? '–' ) ?></strong></td>
				<td>
					<?php if ( $is_done ) : ?>
						<span class="mh-badge mh-badge-done">Eingetragen</span>
					<?php elseif ( $reminders > 0 ) : ?>
						<span class="mh-badge mh-badge-warn"><?= (int) $reminders ?>× erinnert</span>
					<?php else : ?>
						<span class="mh-badge mh-badge-open">Offen</span>
					<?php endif; ?>
				</td>
				<td style="font-size:0.85em;color:#555;">
					<?= esc_html( $entered ) ?>
					<?php if ( $is_done && ! empty( $item['entered_at'] ) ) : ?>
						<br><?= esc_html( $item['entered_at'] ) ?>
					<?php endif; ?>
				</td>
				<td>
					<a class="mh-act" href="<?= esc_url( $reminder->entry_link( (int) $case['id'], (int) $item['idx'] ) ) ?>">
						<?= $is_done ? 'Ändern' : 'Nachtragen' ?>
					</a>
					<?php if ( ! $is_done && ! $is_closed ) : ?>
						&nbsp;|&nbsp;
						<a class="mh-act" href="<?= esc_url( wp_nonce_url(
							add_query_arg( [
								'action'   => 'mh_noten_remind_now',
								'case_id'  => (int) $case['id'],
								'item_idx' => (int) $item['idx'],
							], admin_url( 'admin-post.php' ) ),
							'mh_noten_remind'
						) ) ?>">Erinnern</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
