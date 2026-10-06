<?php
/**
 * View: Druckfertige Teilnehmerliste eines Nachschreibtermins (eigenständige HTML-Seite).
 *
 * Vom Nachschreib_Controller::handle_liste_druck() bereitgestellt:
 * @var array             $buchungen  slot, def, eintraege, bemerkungen, anmeldungen
 * @var string            $logo_src
 * @var DateTimeImmutable $stand
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$slot      = $buchungen['slot'];
$def       = $buchungen['def'];
$eintraege = $buchungen['eintraege'];
$hat_ende  = '' !== $slot['zeit_von'];
$h         = static fn( $v ): string => esc_html( (string) $v );
$stand_txt = $stand->format( 'd.m.Y' ) . ', ' . $stand->format( 'H:i' ) . ' Uhr';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teilnehmerliste <?= $h( $def['label'] . ' ' . $slot['label'] ) ?></title>
<style>
	@page { size: A4 landscape; margin: 12mm 12mm 14mm; }
	* { box-sizing: border-box; }
	body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 20px; background: #fff; }
	.toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 18px; padding: 10px 12px; background: #f3f6fa; border: 1px solid #c9d3df; border-radius: 6px; }
	.toolbar button { font-size: 11pt; padding: 7px 16px; border-radius: 5px; border: 1px solid #004f9f; background: #004f9f; color: #fff; cursor: pointer; }
	.toolbar span { color: #555; font-size: 9.5pt; }
	header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #004f9f; padding-bottom: 8px; margin-bottom: 10px; }
	h1 { font-size: 16pt; margin: 0 0 2px; color: #004f9f; }
	.meta { font-size: 10.5pt; }
	.meta strong { font-size: 12pt; }
	.stand { font-size: 9.5pt; margin-top: 4px; color: #333; }
	table { width: 100%; border-collapse: collapse; }
	th, td { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; }
	th { background: #dfe7f1; font-size: 8.5pt; text-align: left; }
	td { height: 24px; }
	.c { text-align: center; }
	.nr { width: 26px; text-align: right; }
	.box { width: 50px; }
	tr { page-break-inside: avoid; }
	thead { display: table-header-group; }
	.notes { margin-top: 10px; font-size: 9pt; }
	.notes li { margin: 2px 0; }
	.sign { display: flex; gap: 40px; margin-top: 22px; font-size: 9pt; }
	.sign div { flex: 1; border-top: 1px solid #000; padding-top: 3px; }
	.empty { padding: 20px; border: 1px dashed #999; text-align: center; }
	@media print {
		body { padding: 0; }
		.toolbar { display: none; }
	}
</style>
</head>
<body>
	<div class="toolbar">
		<button type="button" onclick="window.print()">Drucken</button>
		<span>Querformat empfohlen. Die Liste zeigt den Stand beim Öffnen – für einen aktuellen Stand die Seite neu laden.</span>
	</div>

	<header>
		<div>
			<h1>Teilnehmerliste Nachschreibtermin</h1>
			<div class="meta">
				<strong><?= $h( $def['label'] ) ?>: <?= $h( $slot['label'] ) ?></strong><br>
				<?= $h( $slot['zeit'] ) ?> · Raum <?= $h( $slot['raum'] ) ?><?= '' !== $slot['hinweis'] ? ' · ' . $h( $slot['hinweis'] ) : '' ?>
				· <?= count( $eintraege ) ?> von <?= (int) $slot['kontingent'] ?> Plätzen belegt (<?= (int) $buchungen['anmeldungen'] ?> Meldungen)
			</div>
			<div class="stand"><strong>Stand: <?= $h( $stand_txt ) ?></strong></div>
		</div>
		<?php if ( '' !== $logo_src ) : ?>
			<img src="<?= esc_attr( $logo_src ) ?>" alt="LEBK" style="width:110px;">
		<?php endif; ?>
	</header>

	<?php if ( empty( $eintraege ) ) : ?>
		<div class="empty">Für diesen Termin liegen keine Anmeldungen vor.</div>
	<?php else : ?>
		<table>
			<thead>
				<tr>
					<th class="nr">#</th>
					<th>Name, Vorname</th>
					<th style="width:80px;">Klasse</th>
					<th style="width:60px;">Fach</th>
					<th class="c" style="width:70px;">Lehrkraft</th>
					<th class="c" style="width:70px;">Dauer</th>
					<?php if ( $hat_ende ) : ?><th class="c" style="width:55px;">Ende</th><?php endif; ?>
					<th>Hilfsmittel</th>
					<th class="c box">anwesend</th>
					<th class="c box">Ausweis geprüft</th>
					<th class="c box">Unterlagen vollständig</th>
					<th class="c" style="width:70px;">Abgabe (Uhrzeit)</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $eintraege as $i => $e ) : ?>
				<tr>
					<td class="nr"><?= (int) $i + 1 ?></td>
					<td><strong><?= $h( mb_strtoupper( $e['lastname'] ) ) ?></strong>, <?= $h( $e['firstname'] ) ?></td>
					<td><?= $h( $e['class_name'] ) ?></td>
					<td><?= $h( $e['subject'] ) ?></td>
					<td class="c"><?= $h( $e['teacher'] ) ?></td>
					<td class="c"><?= (int) $e['duration'] ?> Min.</td>
					<?php if ( $hat_ende ) : ?><td class="c"><?= $h( $e['ende'] ) ?></td><?php endif; ?>
					<td><?= $h( $e['aids'] ) ?></td>
					<td></td><td></td><td></td><td></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $buchungen['bemerkungen'] ) ) : ?>
			<div class="notes">
				<strong>Bemerkungen der Lehrkräfte</strong>
				<ul>
					<?php foreach ( $buchungen['bemerkungen'] as $b ) : ?>
						<li><?= $h( $b['von'] ) ?>: <?= nl2br( $h( $b['text'] ) ) ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="sign">
		<div>Aufsicht (Name)</div>
		<div>Unterschrift</div>
	</div>
</body>
</html>
