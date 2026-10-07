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
 * @var string $pdf_url   Download-Link des PDFs, leer wenn nicht verfügbar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$fd        = $case['form_data'];
$items     = $fd['items'] ?? [];
$total     = count( $items );
$done      = $total - $open_count;
$percent   = $total > 0 ? (int) round( $done / $total * 100 ) : 0;
$is_closed = 'abgeschlossen' === ( $case['status'] ?? '' );
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
	.mh-run-box { background: #eaf3fb; border-left: 4px solid #0073aa; padding: 14px 18px; margin-bottom: 18px; line-height: 1.5; }
	.mh-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 12px; }
	.mh-actions form { margin: 0; }
	.mh-btn { display: inline-block; padding: 8px 16px; border-radius: 4px; background: #0073aa; color: #fff !important;
		border: 1px solid #0073aa; text-decoration: none; font-size: 0.9em; cursor: pointer; line-height: 1.3; }
	.mh-btn-quiet { background: #fff; color: #1d2327 !important; border-color: #c3c4c7; }
	.mh-btn-quiet:hover { border-color: #0073aa; }
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
			<?php if ( $open_count > 0 ) : ?>
				<strong>Noteneinsammlung beendet.</strong> <?= (int) $open_count ?> Note(n) fehlen noch. Trage sie
				unten über „Nachtragen“ ein, wenn du sie inzwischen hast, oder ergänze sie im PDF von Hand.
			<?php else : ?>
				<strong>✅ Alle Noten liegen vor.</strong> Sie wurden in die Abmeldung übernommen.
			<?php endif; ?>

			<div class="mh-actions">
				<?php if ( '' !== $pdf_url ) : ?>
					<a class="mh-btn" href="<?= esc_url( $pdf_url ) ?>" target="_blank">PDF herunterladen ↗</a>
				<?php else : ?>
					<span style="color:#555;">Das PDF lädt die Person herunter, die die Abmeldung angelegt hat.</span>
				<?php endif; ?>

				<?php if ( empty( $fd['owner_done_at'] ) ) : ?>
					<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
						<input type="hidden" name="action" value="mh_noten_owner_done">
						<input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
						<?php wp_nonce_field( 'mh_noten_owner_done_' . (int) $case['id'] ); ?>
						<button type="submit" class="mh-btn mh-btn-quiet" title="Entfernt den Fall aus deinem Dashboard. Er bleibt unter „Meine Noteneinsammlungen“ abrufbar.">Erledigt – PDF ist weitergegeben</button>
					</form>
				<?php else : ?>
					<span style="color:#1b5e20;">Als erledigt markiert am <?= esc_html( date_i18n( 'd.m.Y', strtotime( (string) $fd['owner_done_at'] ) ) ) ?></span>
				<?php endif; ?>
			</div>
		</div>
	<?php else : ?>
		<div class="mh-run-box">
			<strong>Die Einsammlung läuft.</strong> Die Fachlehrkräfte werden automatisch erinnert. Du bekommst eine
			Mail, sobald jemand eine Note einträgt, und eine weitere, wenn alle vorliegen.
			<br>Hast du eine Note schon auf anderem Weg – etwa im Lehrerzimmer –, trage sie unten über „Nachtragen“ selbst ein.
			Brauchst du das PDF sofort, kannst du die Einsammlung beenden: Es wird dann niemand mehr angefragt, und
			fehlende Noten ergänzt du im PDF von Hand.
			<div class="mh-actions">
				<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>"
					onsubmit="return confirm('Einsammlung jetzt beenden? Offene Fächer werden nicht mehr angefragt.');">
					<input type="hidden" name="action" value="mh_noten_end_case">
					<input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
					<?php wp_nonce_field( 'mh_noten_end_case_' . (int) $case['id'] ); ?>
					<button type="submit" class="mh-btn mh-btn-quiet">Einsammlung beenden</button>
				</form>
			</div>
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
				$role    = match ( $item['entered_by_role'] ?? '' ) {
					'klassenlehrer' => ' (nachgetragen)',
					'vorab'         => ' (beim Anlegen eingetragen)',
					default         => '',
				};
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
					<?php
					// Versandstand: macht nachvollziehbar, ob und wohin die Mail ging -
					// "nicht angekommen" ist sonst nicht von "nie verschickt" zu unterscheiden.
					if ( ! $is_done && '1' === (string) ( $item['collect'] ?? '0' ) ) :
						$mail_error = (string) ( $item['mail_error'] ?? '' );
						$last_at    = (string) ( $item['last_mail_at'] ?? $item['last_reminder_at'] ?? $item['notified_at'] ?? '' );
						$last_to    = (string) ( $item['last_mail_to'] ?? '' );
					?>
						<div style="font-size:0.8em; color:#555; margin-top:4px; line-height:1.35;">
							<?php if ( '' !== $mail_error ) : ?>
								<span style="color:#b32d2e;">Mail fehlgeschlagen: <?= esc_html( $mail_error ) ?></span>
							<?php elseif ( '' !== $last_at ) : ?>
								Mail gesendet <?= esc_html( date_i18n( 'd.m. H:i', strtotime( $last_at ) ) ) ?>
								<?php if ( '' !== $last_to ) : ?><br>an <?= esc_html( $last_to ) ?><?php endif; ?>
							<?php else : ?>
								Einladung wird gesendet …
							<?php endif; ?>
						</div>
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
