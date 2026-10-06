<?php
/**
 * View: Buchungsübersicht eines Nachschreibtermins (Terminverwaltung)
 *
 * Vom Nachschreib_Controller::render_termine() bereitgestellt:
 * @var array  $buchungen  slot, def, eintraege, bemerkungen, anmeldungen
 * @var string $back_url   zurück zur Terminliste
 * @var string $druck_url  druckfertige Liste
 * @var string $page_url
 * @var bool   $can_manage
 * @var string $tab
 */

use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;

if ( ! defined( 'ABSPATH' ) ) exit;

$slot      = $buchungen['slot'];
$def       = $buchungen['def'];
$eintraege = $buchungen['eintraege'];
$belegt    = count( $eintraege );
$occ       = 0 === $belegt ? 'is-none' : ( $slot['ausgebucht'] ? 'is-full' : ( $slot['rest'] <= Nachschreib_Termin_Katalog::WENIGE_PLAETZE ? 'is-low' : '' ) );
$hat_ende  = '' !== $slot['zeit_von'];
?>
<?php include __DIR__ . '/partial-style.php'; ?>
<style>
	.mh-ns-bu-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
	.mh-ns-bu-head h2 { margin: 0 0 4px; font-size: 1.35em; }
	.mh-ns-bu-meta { color: var(--ns-muted); font-size: 0.92em; }
	.mh-ns-bu-kpis { display: flex; gap: 10px; flex-wrap: wrap; margin: 0 0 18px; }
	.mh-ns-bu-kpi { border: 1px solid var(--ns-border); border-radius: 8px; padding: 10px 16px; min-width: 120px; background: #fff; }
	.mh-ns-bu-kpi b { display: block; font-size: 1.5em; line-height: 1.1; }
	.mh-ns-bu-kpi span { font-size: 0.8em; color: var(--ns-muted); }
	.mh-ns-bu-kpi.is-low { background: #fff8dc; border-color: #e0b100; }
	.mh-ns-bu-kpi.is-full { background: #fdecec; border-color: #e3a3a3; }
	.mh-ns-bu { width: 100%; border-collapse: collapse; font-size: 0.9em; }
	.mh-ns-bu th { text-align: left; font-size: 0.76em; text-transform: uppercase; letter-spacing: .3px; color: var(--ns-muted);
		padding: 8px 8px; border-bottom: 2px solid var(--ns-border); white-space: nowrap; }
	.mh-ns-bu td { padding: 7px 8px; border-bottom: 1px solid #eceef1; vertical-align: top; }
	.mh-ns-bu td.num { text-align: right; white-space: nowrap; }
	.mh-ns-bu small { color: var(--ns-muted); }
	.mh-ns-bu-wrap { overflow-x: auto; }
	.mh-ns-bu-notes { margin-top: 16px; }
	.mh-ns-bu-notes li { margin: 4px 0; }
</style>

<div class="mh-ns" id="mh-ns">
	<?php include __DIR__ . '/partial-tabs.php'; ?>

	<p style="margin:0 0 14px;"><a href="<?= esc_url( $back_url ) ?>#mh-ns">← zurück zur Terminliste</a></p>

	<div class="mh-ns-bu-head">
		<div>
			<h2><?= esc_html( $def['label'] ) ?>: <?= esc_html( $slot['label'] ) ?></h2>
			<div class="mh-ns-bu-meta">
				<?= esc_html( $slot['zeit'] ) ?> · Raum <?= esc_html( $slot['raum'] ) ?>
				<?= '' !== $slot['hinweis'] ? ' · ' . esc_html( $slot['hinweis'] ) : '' ?>
				· Abgabe bis <?= esc_html( $slot['frist_label'] ) ?>
				<?php if ( ! $slot['aktiv'] ) : ?> · <strong style="color:var(--ns-red);">Termin deaktiviert</strong><?php endif; ?>
			</div>
		</div>
		<a class="mh-ns-btn mh-ns-btn-primary" href="<?= esc_url( $druck_url ) ?>" target="_blank" rel="noopener">🖨 Teilnehmerliste drucken</a>
	</div>

	<div class="mh-ns-bu-kpis">
		<div class="mh-ns-bu-kpi <?= esc_attr( $occ ) ?>"><b><?= (int) $belegt ?> / <?= (int) $slot['kontingent'] ?></b><span>Plätze belegt</span></div>
		<div class="mh-ns-bu-kpi"><b><?= (int) $slot['rest'] ?></b><span>Plätze frei</span></div>
		<div class="mh-ns-bu-kpi"><b><?= (int) $buchungen['anmeldungen'] ?></b><span>Meldungen</span></div>
	</div>

	<?php if ( empty( $eintraege ) ) : ?>
		<p class="mh-ns-hint">Für diesen Termin liegen noch keine Anmeldungen vor.</p>
	<?php else : ?>
		<div class="mh-ns-bu-wrap">
			<table class="mh-ns-bu">
				<thead>
					<tr>
						<th>#</th><th>Name, Vorname</th><th>Klasse</th><th>Fach</th><th>Lehrkraft</th>
						<th>Dauer</th><?php if ( $hat_ende ) : ?><th>Ende</th><?php endif; ?>
						<th>Hilfsmittel</th><th>Angemeldet von</th><th>Eingang</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $eintraege as $i => $e ) : ?>
					<tr>
						<td class="num"><?= (int) $i + 1 ?></td>
						<td><strong><?= esc_html( $e['lastname'] ) ?></strong>, <?= esc_html( $e['firstname'] ) ?></td>
						<td><?= esc_html( $e['class_name'] ) ?></td>
						<td><?= esc_html( $e['subject'] ) ?></td>
						<td><?= esc_html( $e['teacher'] ) ?></td>
						<td class="num"><?= (int) $e['duration'] ?> Min.</td>
						<?php if ( $hat_ende ) : ?><td><?= esc_html( $e['ende'] ) ?></td><?php endif; ?>
						<td><?= esc_html( $e['aids'] ) ?></td>
						<td><?= esc_html( $e['von'] ) ?> <small>(Meldung <?= (int) $e['anmeldung'] ?>)</small></td>
						<td><small><?= esc_html( date_i18n( 'd.m. H:i', strtotime( $e['eingang'] ) ) ) ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( ! empty( $buchungen['bemerkungen'] ) ) : ?>
			<div class="mh-ns-bu-notes">
				<strong>Bemerkungen der Lehrkräfte</strong>
				<ul>
					<?php foreach ( $buchungen['bemerkungen'] as $b ) : ?>
						<li><small>Meldung <?= (int) $b['anmeldung'] ?>, <?= esc_html( $b['von'] ) ?>:</small> <?= nl2br( esc_html( $b['text'] ) ) ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
