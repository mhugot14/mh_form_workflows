<?php
/**
 * View: Übersicht der eigenen Noteneinsammlungen (Klassenlehrer-Sicht ohne konkreten Fall).
 *
 * @var array $cases
 * @var \Mh\FormWorkflows\Service\Reminder_Service $reminder
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<style>
	.mh-fall-liste { max-width: 1000px; margin: 25px auto; font-family: inherit; }
	.mh-fall-liste table { width: 100%; border-collapse: collapse; background: #fff; }
	.mh-fall-liste th, .mh-fall-liste td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
	.mh-fall-liste th { background: #f8f9fa; color: #666; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
	.mh-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; }
	.mh-badge-open { background: #fff8e5; color: #7a5b00; }
	.mh-badge-done { background: #e8f5e9; color: #1b5e20; }
</style>

<div class="mh-fall-liste">
	<h2>Meine Noteneinsammlungen</h2>

	<?php if ( empty( $cases ) ) : ?>
		<p>Es wurde noch keine Noteneinsammlung gestartet. Sie wird unten im Abgangsformular über
		   „Noteneinsammlung digital starten" ausgelöst.</p>
	<?php else : ?>
		<table>
			<thead>
				<tr>
					<th>Schüler/in</th>
					<th>Klasse</th>
					<th>Fortschritt</th>
					<th>Status</th>
					<th>Gestartet</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $cases as $case ) :
				$fd    = $case['form_data'];
				$items = $fd['items'] ?? [];
				$total = count( $items );
				$done  = 0;
				foreach ( $items as $i ) {
					if ( 'erledigt' === ( $i['status'] ?? 'offen' ) ) $done++;
				}
				$closed = 'abgeschlossen' === ( $case['status'] ?? '' );
			?>
				<tr>
					<td><strong><?= esc_html( ( $fd['lastname'] ?? '' ) . ', ' . ( $fd['firstname'] ?? '' ) ) ?></strong></td>
					<td><?= esc_html( $fd['class_name'] ?? '' ) ?></td>
					<td><?= (int) $done ?> / <?= (int) $total ?></td>
					<td>
						<?php if ( $closed ) : ?>
							<span class="mh-badge mh-badge-done">Abgeschlossen</span>
						<?php else : ?>
							<span class="mh-badge mh-badge-open">Läuft</span>
						<?php endif; ?>
					</td>
					<td style="font-size:0.9em;color:#555;"><?= esc_html( $fd['started_at'] ?? '' ) ?></td>
					<td><a href="<?= esc_url( $reminder->case_link( (int) $case['id'] ) ) ?>">Öffnen</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
