<?php
/**
 * View: Terminverwaltung Nachschreiben (Reiter „Termine verwalten“)
 *
 * Vom Nachschreib_Controller::render_termine() bereitgestellt:
 * @var string $typ          kurz|samstag|lang
 * @var array  $def          Katalogeintrag
 * @var int    $weeks
 * @var array  $liste        Slots aus get_verwaltung() (mit vorgabe/abweichung/frist_abgelaufen)
 * @var array  $form_errors
 * @var array  $form_input   Eingaben nach Fehler
 * @var string $flash
 * @var string $page_url
 * @var bool   $can_manage
 * @var string $tab
 * @var Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog $katalog
 */

use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;

if ( ! defined( 'ABSPATH' ) ) exit;

$weekly    = 'weekly' === $def['slots'];
$typ_url   = static fn( string $t ): string => add_query_arg( [ 'mh_ns_tab' => 'termine', 'mh_ns_typ' => $t ], remove_query_arg( 'mh_ns_wochen', $page_url ) );
$back_url  = add_query_arg( [ 'mh_ns_tab' => 'termine', 'mh_ns_typ' => $typ, 'mh_ns_wochen' => $weekly ? $weeks : null ], $page_url );
$err       = static fn( string $key ): string => isset( $form_errors[ $key ] ) ? ' is-invalid' : '';
$posted    = is_array( $form_input['termine'] ?? null ) ? $form_input['termine'] : null;

// Feldwert: nach einem Fehler die Eingabe, sonst die gespeicherte Abweichung ('' = Vorgabe).
$wert = static function ( array $slot, string $feld ) use ( $posted ): string {
	if ( null !== $posted ) {
		return (string) ( $posted[ $slot['datum'] ][ $feld ] ?? '' );
	}
	$v = $slot['abweichung'][ $feld ] ?? '';
	return null === $v ? '' : (string) $v;
};
$ist_aktiv = static function ( array $slot ) use ( $posted ): bool {
	return null !== $posted ? isset( $posted[ $slot['datum'] ]['aktiv'] ) : (bool) $slot['aktiv'];
};
$neu = is_array( $form_input['neu'] ?? null ) ? $form_input['neu'] : [];

$anzahl_aktiv = count( array_filter( $liste, static fn( $s ) => $s['aktiv'] ) );
?>
<?php include __DIR__ . '/partial-style.php'; ?>
<style>
	.mh-ns-typnav { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 16px; }
	.mh-ns-typnav a { padding: 7px 14px; border-radius: 18px; border: 1px solid var(--ns-border); text-decoration: none !important;
		color: #1d2327 !important; font-size: 0.92em; background: #fff; }
	.mh-ns-typnav a.is-active { background: var(--ns-blue); border-color: var(--ns-blue); color: #fff !important; font-weight: 600; }

	.mh-ns-tt { width: 100%; border-collapse: collapse; font-size: 0.9em; }
	.mh-ns-tt th { text-align: left; font-size: 0.78em; text-transform: uppercase; letter-spacing: .3px; color: var(--ns-muted);
		padding: 8px 6px; border-bottom: 2px solid var(--ns-border); white-space: nowrap; }
	.mh-ns-tt td { padding: 6px; border-bottom: 1px solid #eceef1; vertical-align: middle; }
	.mh-ns-tt tr.is-off td { background: #f6f7f8; }
	.mh-ns-tt tr.is-off .mh-ns-tt-date { color: #8a929b; }
	.mh-ns-tt input[type="text"], .mh-ns-tt input[type="time"], .mh-ns-tt input[type="number"], .mh-ns-tt input[type="date"] {
		width: 100%; height: 34px; padding: 0 7px; border: 1px solid #b6bdc5; border-radius: 5px; font-size: 14px; background: #fff; margin: 0; }
	.mh-ns-tt input::placeholder { color: #a0a7af; }
	.mh-ns-tt .is-invalid input, .mh-ns-tt input.is-invalid { border-color: var(--ns-red); background: #fff6f6; }
	.mh-ns-tt-date { font-weight: 700; white-space: nowrap; }
	.mh-ns-tt-sub { display: block; font-size: 0.8em; font-weight: 400; color: var(--ns-muted); }
	/* Beginn und Ende teilen sich eine Spalte, damit Raum und Hinweis genug Breite haben. */
	.mh-ns-tt .c-time { width: 196px; white-space: nowrap; }
	.mh-ns-tt .c-time input { width: 86px !important; display: inline-block; }
	.mh-ns-tt .c-time .sep { display: inline-block; width: 12px; text-align: center; color: var(--ns-muted); }
	.mh-ns-tt .c-raum { min-width: 150px; }
	.mh-ns-tt .c-hint { min-width: 120px; }
	.mh-ns-tt .c-num { width: 84px; }
	.mh-ns-tt .c-num input { padding-right: 2px !important; }
	.mh-ns-tt .c-switch { width: 58px; text-align: center; }
	.mh-ns-tt .c-belegt { width: 96px; white-space: nowrap; }
	.mh-ns-tt .c-date { min-width: 150px; }
	a.mh-ns-occ { text-decoration: none !important; cursor: pointer; }
	a.mh-ns-occ:hover { outline: 2px solid var(--ns-blue); outline-offset: 1px; }
	.mh-ns-occ-link { display: block; font-size: 0.75em; margin-top: 3px; color: var(--ns-blue) !important; }
	.mh-ns-tt-scroll { overflow-x: auto; }
	.mh-ns-occ { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.85em; font-weight: 600; background: #e7f3ea; color: var(--ns-green); }
	.mh-ns-occ.is-low { background: #f5d547; color: #5c4400; }
	.mh-ns-occ.is-full { background: var(--ns-red); color: #fff; }
	.mh-ns-occ.is-none { background: #eef0f2; color: #8a929b; font-weight: 400; }
	.mh-ns-tag-warn { display: inline-block; margin-top: 2px; font-size: 0.75em; color: var(--ns-red); font-weight: 600; }

	/* Schalter */
	.mh-ns-switch { position: relative; display: inline-block; width: 42px; height: 24px; margin: 0; cursor: pointer; }
	.mh-ns-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
	.mh-ns-switch span { position: absolute; inset: 0; background: #c3c8ce; border-radius: 12px; transition: background .15s; }
	.mh-ns-switch span::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff;
		border-radius: 50%; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
	.mh-ns-switch input:checked + span { background: var(--ns-green); }
	.mh-ns-switch input:checked + span::before { transform: translateX(18px); }
	.mh-ns-switch input:focus-visible + span { outline: 2px solid var(--ns-blue); outline-offset: 2px; }

	.mh-ns-tt-tools { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin: 0 0 10px; }
	.mh-ns-save-bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--ns-border); padding: 12px 0; margin-top: 14px;
		display: flex; gap: 12px; align-items: center; flex-wrap: wrap; z-index: 5; }
	.mh-ns-dirty { color: #9a5b00; font-size: 0.88em; font-weight: 600; display: none; }
	.mh-ns-dirty.is-on { display: inline; }
	.mh-ns-newbox { display: grid; grid-template-columns: 150px 100px 100px 1fr 1fr 80px; gap: 10px; align-items: end; }
	.mh-ns-newbox label { display: block; font-size: 0.8em; font-weight: 600; margin: 0 0 4px; }
	@media (max-width: 900px) {
		.mh-ns-newbox { grid-template-columns: 1fr 1fr; }
		.mh-ns-tt thead { display: none; }
		.mh-ns-tt tr { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 10px 0; border-bottom: 1px solid var(--ns-border); }
		.mh-ns-tt td { border: none; padding: 2px 4px; }
		.mh-ns-tt td[data-l]::before { content: attr(data-l); display: block; font-size: 0.72em; color: var(--ns-muted); text-transform: uppercase; }
		.mh-ns-tt .c-time, .mh-ns-tt .c-num, .mh-ns-tt .c-switch, .mh-ns-tt .c-belegt, .mh-ns-tt .c-raum, .mh-ns-tt .c-hint, .mh-ns-tt .c-date { width: auto; min-width: 0; text-align: left; }
	}
</style>

<div class="mh-ns" id="mh-ns">
	<?php include __DIR__ . '/partial-tabs.php'; ?>

	<nav class="mh-ns-typnav" aria-label="Terminart">
		<?php foreach ( $katalog->all() as $k => $d ) : ?>
			<a href="<?= esc_url( $typ_url( $k ) ) ?>#mh-ns" class="<?= $k === $typ ? 'is-active' : '' ?>"><?= esc_html( $d['label'] ) ?></a>
		<?php endforeach; ?>
	</nav>

	<p class="mh-ns-intro">
		<?php if ( 'kurz' === $typ ) : ?>
			Jeder Schul-Mittwoch und -Donnerstag ist automatisch freigegeben. Hier einzelne Tage <strong>deaktivieren</strong>
			(z. B. Prüfungstage, Konferenzen) oder Uhrzeit, Raum und Plätze abweichend setzen.
		<?php elseif ( 'samstag' === $typ ) : ?>
			Alle Samstage außerhalb der Ferien stehen zur Auswahl, sind aber erst buchbar, wenn sie hier
			<strong>freigeschaltet</strong> werden.
		<?php else : ?>
			Lange Termine werden nach Bedarf angelegt. Solange kein Termin angelegt ist, dürfen Lehrkräfte das Datum im
			Formular frei eintragen.
		<?php endif; ?>
		Leere Felder übernehmen die Vorgabe (grau angezeigt). Belegt zählt die angemeldeten Schüler*innen.
	</p>

	<?php if ( '' !== $flash ) : ?>
		<div class="mh-ns-flash"><?= esc_html( $flash ) ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $form_errors ) ) : ?>
		<div class="mh-ns-errors" role="alert">
			<h3>Nicht gespeichert – bitte korrigieren:</h3>
			<ul><?php foreach ( array_unique( $form_errors ) as $msg ) : ?><li><?= esc_html( (string) $msg ) ?></li><?php endforeach; ?></ul>
		</div>
	<?php endif; ?>

	<form id="mh-ns-termine" method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
		<input type="hidden" name="action" value="mh_nachschreib_termine_save">
		<input type="hidden" name="typ" value="<?= esc_attr( $typ ) ?>">
		<input type="hidden" name="mh_back" value="<?= esc_url( $back_url ) ?>">
		<?php wp_nonce_field( 'mh_ns_termine_save' ); ?>

		<?php if ( ! $weekly ) : ?>
			<section class="mh-ns-step">
				<h3>Neuen langen Termin anlegen</h3>
				<div class="mh-ns-step-body">
					<div class="mh-ns-newbox">
						<div class="<?= trim( $err( 'neu.datum' ) ) ?>"><label for="ns-neu-datum">Datum</label>
							<input class="mh-ns-tt-in" type="date" id="ns-neu-datum" name="neu[datum]" value="<?= esc_attr( (string) ( $neu['datum'] ?? '' ) ) ?>" min="<?= esc_attr( current_time( 'Y-m-d' ) ) ?>" style="width:100%;height:36px;"></div>
						<div class="<?= trim( $err( 'neu.zeit_von' ) ) ?>"><label for="ns-neu-von">Beginn</label>
							<input type="time" id="ns-neu-von" name="neu[zeit_von]" value="<?= esc_attr( (string) ( $neu['zeit_von'] ?? '' ) ) ?>" style="width:100%;height:36px;"></div>
						<div class="<?= trim( $err( 'neu.zeit_bis' ) ) ?>"><label for="ns-neu-bis">Ende</label>
							<input type="time" id="ns-neu-bis" name="neu[zeit_bis]" value="<?= esc_attr( (string) ( $neu['zeit_bis'] ?? '' ) ) ?>" style="width:100%;height:36px;"></div>
						<div><label for="ns-neu-raum">Raum</label>
							<input type="text" id="ns-neu-raum" name="neu[raum]" value="<?= esc_attr( (string) ( $neu['raum'] ?? '' ) ) ?>" placeholder="z. B. B12" style="width:100%;height:36px;"></div>
						<div><label for="ns-neu-hinweis">Hinweis</label>
							<input type="text" id="ns-neu-hinweis" name="neu[hinweis]" value="<?= esc_attr( (string) ( $neu['hinweis'] ?? '' ) ) ?>" placeholder="optional" style="width:100%;height:36px;"></div>
						<div class="<?= trim( $err( 'neu.kontingent' ) ) ?>"><label for="ns-neu-kont">Plätze</label>
							<input type="number" id="ns-neu-kont" name="neu[kontingent]" min="0" max="999" value="<?= esc_attr( (string) ( $neu['kontingent'] ?? '' ) ) ?>" placeholder="<?= (int) $def['kontingent'] ?>" style="width:100%;height:36px;"></div>
					</div>
					<p class="mh-ns-hint" style="margin:8px 0 0;">Wird mit „Speichern“ angelegt. Nur Schultage (Mo–Fr, außerhalb der Ferien).</p>
				</div>
			</section>
		<?php endif; ?>

		<section class="mh-ns-step">
			<h3><?= esc_html( $def['label'] ) ?> <span class="mh-ns-tag"><?= (int) $anzahl_aktiv ?> von <?= count( $liste ) ?> freigegeben</span></h3>
			<div class="mh-ns-step-body">
				<?php if ( empty( $liste ) ) : ?>
					<p class="mh-ns-hint" style="margin:0;"><?= $weekly ? 'Im gewählten Zeitraum gibt es keine möglichen Tage.' : 'Noch kein langer Termin angelegt.' ?></p>
				<?php else : ?>
					<div class="mh-ns-tt-tools">
						<button type="button" class="mh-ns-btn mh-ns-btn-small" data-all="1">alle freigeben</button>
						<button type="button" class="mh-ns-btn mh-ns-btn-small" data-all="0">alle deaktivieren</button>
						<span class="mh-ns-hint" style="margin:0;">Vorgabe: <?= esc_html( Nachschreib_Termin_Katalog::zeit_label( $def['zeit_von'], $def['zeit_bis'] ) ) ?>
							· Raum <?= esc_html( $def['raum'] ) ?> · <?= (int) $def['kontingent'] ?> Plätze</span>
					</div>
					<div class="mh-ns-tt-scroll"><table class="mh-ns-tt">
						<thead>
							<tr>
								<th class="c-switch">Buchbar</th>
								<th class="c-date">Termin</th>
								<th class="c-time">Zeit (Beginn – Ende)</th>
								<th class="c-raum">Raum</th>
								<th class="c-hint">Hinweis</th>
								<th class="c-num">Plätze</th>
								<th class="c-belegt">Belegt</th>
								<?php if ( ! $weekly ) : ?><th class="c-num">Löschen</th><?php endif; ?>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $liste as $s ) :
							$d      = $s['datum'];
							$n      = static fn( string $f ): string => 'termine[' . $d . '][' . $f . ']';
							$aktiv  = $ist_aktiv( $s );
							$occ    = 0 === $s['belegt'] ? 'is-none' : ( $s['ausgebucht'] ? 'is-full' : ( $s['rest'] <= Nachschreib_Termin_Katalog::WENIGE_PLAETZE ? 'is-low' : '' ) );
							$prefix = 'termine.' . $d . '.';
							?>
							<tr class="<?= $aktiv ? '' : 'is-off' ?>">
								<td class="c-switch" data-l="Buchbar">
									<label class="mh-ns-switch" title="<?= $aktiv ? 'buchbar – zum Deaktivieren klicken' : 'deaktiviert – zum Freigeben klicken' ?>">
										<input type="checkbox" class="js-aktiv" name="<?= $n( 'aktiv' ) ?>" value="1" <?php checked( $aktiv ); ?>>
										<span></span>
									</label>
								</td>
								<td class="c-date">
									<span class="mh-ns-tt-date"><?= esc_html( $s['label'] ) ?></span>
									<span class="mh-ns-tt-sub">
										<?= $s['frist_abgelaufen'] ? 'Abgabefrist vorbei' : 'Abgabe bis ' . esc_html( $s['frist_label'] ) ?>
									</span>
									<?php if ( ! $aktiv && $s['belegt'] > 0 ) : ?>
										<span class="mh-ns-tag-warn">⚠ <?= (int) $s['belegt'] ?> bereits angemeldet</span>
									<?php endif; ?>
								</td>
								<td class="c-time<?= $err( $prefix . 'zeit_von' ) . $err( $prefix . 'zeit_bis' ) ?>" data-l="Zeit">
									<input type="time" name="<?= $n( 'zeit_von' ) ?>" value="<?= esc_attr( $wert( $s, 'zeit_von' ) ) ?>" aria-label="Beginn" title="Beginn – leer = <?= esc_attr( '' !== $s['vorgabe']['zeit_von'] ? $s['vorgabe']['zeit_von'] : 'offen' ) ?>"><span class="sep">–</span><input type="time" name="<?= $n( 'zeit_bis' ) ?>" value="<?= esc_attr( $wert( $s, 'zeit_bis' ) ) ?>" aria-label="Ende" title="Ende – leer = <?= esc_attr( '' !== $s['vorgabe']['zeit_bis'] ? $s['vorgabe']['zeit_bis'] : 'offen' ) ?>">
								</td>
								<td class="c-raum" data-l="Raum"><input type="text" name="<?= $n( 'raum' ) ?>" value="<?= esc_attr( $wert( $s, 'raum' ) ) ?>" placeholder="<?= esc_attr( $s['vorgabe']['raum'] ) ?>" maxlength="100"></td>
								<td class="c-hint" data-l="Hinweis"><input type="text" name="<?= $n( 'hinweis' ) ?>" value="<?= esc_attr( $wert( $s, 'hinweis' ) ) ?>" placeholder="–" maxlength="255"></td>
								<td class="c-num<?= $err( $prefix . 'kontingent' ) ?>" data-l="Plätze"><input type="number" name="<?= $n( 'kontingent' ) ?>" value="<?= esc_attr( $wert( $s, 'kontingent' ) ) ?>" placeholder="<?= (int) $s['vorgabe']['kontingent'] ?>" min="0" max="999"></td>
								<td class="c-belegt" data-l="Belegt">
									<a class="mh-ns-occ <?= esc_attr( $occ ) ?>" href="<?= esc_url( add_query_arg( 'mh_ns_liste', $d, $back_url ) ) ?>#mh-ns" title="Buchungsübersicht öffnen"><?= (int) $s['belegt'] ?> / <?= (int) $s['kontingent'] ?></a>
									<a class="mh-ns-occ-link" href="<?= esc_url( add_query_arg( 'mh_ns_liste', $d, $back_url ) ) ?>#mh-ns">Liste</a>
								</td>
								<?php if ( ! $weekly ) : ?>
									<td class="c-num" data-l="Löschen" style="text-align:center;">
										<input type="checkbox" name="<?= $n( 'loeschen' ) ?>" value="1" title="Termin löschen (nur ohne Anmeldungen)" <?= $s['belegt'] > 0 ? 'disabled' : '' ?>>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>

				<?php if ( $weekly ) : ?>
					<p style="margin:12px 0 0;">
						<a href="<?= esc_url( add_query_arg( 'mh_ns_wochen', min( 52, $weeks + 12 ), $back_url ) ) ?>#mh-ns">Weitere 12 Wochen anzeigen</a>
						<span class="mh-ns-hint">(aktuell <?= (int) $weeks ?> Wochen)</span>
					</p>
				<?php endif; ?>

				<div class="mh-ns-save-bar">
					<button type="submit" class="mh-ns-btn mh-ns-btn-primary">Speichern</button>
					<span class="mh-ns-dirty" id="mh-ns-dirty">Ungespeicherte Änderungen</span>
				</div>
			</div>
		</section>
	</form>
</div>

<script>
(function () {
	'use strict';
	const form  = document.getElementById('mh-ns-termine');
	if (!form) return;
	const dirty = document.getElementById('mh-ns-dirty');
	const markDirty = () => dirty.classList.add('is-on');

	form.addEventListener('input', markDirty);
	form.addEventListener('change', e => {
		markDirty();
		if (e.target.classList.contains('js-aktiv')) {
			e.target.closest('tr').classList.toggle('is-off', !e.target.checked);
		}
	});
	form.querySelectorAll('[data-all]').forEach(btn => btn.addEventListener('click', () => {
		const on = btn.dataset.all === '1';
		form.querySelectorAll('.js-aktiv').forEach(cb => { cb.checked = on; cb.closest('tr').classList.toggle('is-off', !on); });
		markDirty();
	}));
	// Ungespeicherte Änderungen nicht versehentlich verlieren.
	let submitting = false;
	form.addEventListener('submit', () => { submitting = true; });
	window.addEventListener('beforeunload', e => {
		if (!submitting && dirty.classList.contains('is-on')) { e.preventDefault(); e.returnValue = ''; }
	});
})();
</script>
