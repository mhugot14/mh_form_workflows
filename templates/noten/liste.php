<?php
/**
 * View: "Meine Noteneingaben" — alle Ausschulungen, an denen die angemeldete
 * Lehrkraft mit mindestens einer Note beteiligt ist.
 *
 * Gezeigt werden ausschliesslich die eigenen Fächer.
 *
 * @var array $entries  Liste aus ['case' => array, 'items' => array]
 * @var \Mh\FormWorkflows\Service\Reminder_Service $reminder
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$offen_total = 0;
foreach ( $entries as $e ) {
	foreach ( $e['items'] as $i ) {
		if ( 'erledigt' !== ( $i['status'] ?? 'offen' ) ) $offen_total++;
	}
}
?>

<style>
	.mh-noten-liste { max-width: 1000px; margin: 25px auto; font-family: inherit; }
	.mh-noten-liste table { width: 100%; border-collapse: collapse; background: #fff; }
	.mh-noten-liste th, .mh-noten-liste td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
	.mh-noten-liste th { background: #f8f9fa; color: #666; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
	.mh-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; }
	.mh-badge-open { background: #fff8e5; color: #7a5b00; }
	.mh-badge-done { background: #e8f5e9; color: #1b5e20; }
	.mh-filters { margin-bottom: 15px; }
	.mh-summary { background: #f6f8fa; border-left: 4px solid #0073aa; padding: 12px 16px; margin-bottom: 18px; }
</style>

<div class="mh-noten-liste">
	<h2>Meine Noteneingaben</h2>

	<div class="mh-summary">
		<?php if ( $offen_total > 0 ) : ?>
			Es warten <strong><?= (int) $offen_total ?></strong> Noten auf deine Eingabe.
		<?php else : ?>
			Aktuell wartet keine Noteneingabe auf dich.
		<?php endif; ?>
	</div>

	<form method="get" class="mh-filters">
		<select name="status" onchange="this.form.submit()">
			<option value="">-- Alle Ausschulungen --</option>
			<option value="offen" <?= selected( $_GET['status'] ?? '', 'offen', false ) ?>>Nur offene</option>
			<option value="abgeschlossen" <?= selected( $_GET['status'] ?? '', 'abgeschlossen', false ) ?>>Nur abgeschlossene</option>
		</select>
	</form>

	<?php if ( empty( $entries ) ) : ?>
		<p>Du bist derzeit an keiner Ausschulung mit einer Note beteiligt.</p>
	<?php else : ?>
		<table>
			<thead>
				<tr>
					<th>Schüler/in</th>
					<th>Klasse</th>
					<th>Fach</th>
					<th>Status</th>
					<th>Ausschulung</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $entries as $entry ) :
				$case = $entry['case'];
				$fd   = $case['form_data'];
				foreach ( $entry['items'] as $item ) :
					$done = 'erledigt' === ( $item['status'] ?? 'offen' );
				?>
				<tr>
					<td><strong><?= esc_html( ( $fd['lastname'] ?? '' ) . ', ' . ( $fd['firstname'] ?? '' ) ) ?></strong></td>
					<td><?= esc_html( $fd['class_name'] ?? '' ) ?></td>
					<td><?= esc_html( $item['subject'] ?? '' ) ?></td>
					<td>
						<?php if ( $done ) : ?>
							<span class="mh-badge mh-badge-done">Eingetragen</span>
						<?php else : ?>
							<span class="mh-badge mh-badge-open">Offen</span>
						<?php endif; ?>
					</td>
					<td><?= 'abgeschlossen' === ( $case['status'] ?? '' ) ? 'abgeschlossen' : 'läuft' ?></td>
					<td>
						<a href="<?= esc_url( $reminder->entry_link( (int) $case['id'], (int) $item['idx'] ) ) ?>">
							<?= $done ? 'Note ändern' : 'Note eintragen' ?>
						</a>
					</td>
				</tr>
			<?php endforeach; endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
