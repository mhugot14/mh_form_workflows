<?php
/**
 * View: Dashboard / Einstiegsseite [mh_dashboard]
 *
 * @var array $data Vom Dashboard_Controller zusammengestellt.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$step_labels = include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-labels.php';

$links       = $data['links'];
$user        = $data['user'];
$absentismus = $data['absentismus'];
$submissions = $data['submissions'];
$noten_owned = $data['noten_owned'];
$noten_todo  = $data['noten_todo'];

$vorname = trim( (string) $user->first_name ) ?: $user->display_name;

$fall_url = static function ( int $case_id ) use ( $links ): string {
	return '' !== $links['absentismus_fall']
		? add_query_arg( 'mh_case_id', $case_id, $links['absentismus_fall'] )
		: '';
};

$datum = static fn( ?string $sql ): string => empty( $sql ) ? '' : date_i18n( 'd.m.Y', strtotime( $sql ) );

$form_titel = [
	'abmeldung_student_v1' => 'Schüler*innen-Abmeldung',
	'service_leave_v1'     => 'Dienstbefreiung',
];

// Zählt, was insgesamt Aufmerksamkeit braucht - steuert den Leer-Zustand.
$offen_gesamt = count( $absentismus ) + count( $noten_owned ) + count( $noten_todo );
?>

<style>
	.mh-db { max-width: 1100px; margin: 20px auto; font-family: inherit; }
	.mh-db h2 { margin: 0 0 4px; }
	.mh-db .mh-db-lead { color: #666; margin: 0 0 25px; }

	.mh-db-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 6px; margin-bottom: 22px; overflow: hidden; }
	.mh-db-card > h3 { margin: 0; padding: 13px 18px; background: #f8f9fa; border-bottom: 1px solid #e2e4e7;
		font-size: 0.95em; display: flex; align-items: center; gap: 10px; }
	.mh-db-count { display: inline-block; min-width: 22px; padding: 1px 7px; border-radius: 11px;
		background: #d9d9d9; color: #333; font-size: 0.8em; text-align: center; }
	.mh-db-count.is-open { background: #d63638; color: #fff; }
	.mh-db-card > .mh-db-body { padding: 0; }
	.mh-db-empty { padding: 16px 18px; color: #6f6f6f; font-style: italic; }

	.mh-db-list { list-style: none; margin: 0; padding: 0; }
	.mh-db-list li { padding: 12px 18px; border-bottom: 1px solid #f0f0f1; display: flex;
		align-items: center; gap: 14px; flex-wrap: wrap; }
	.mh-db-list li:last-child { border-bottom: 0; }
	.mh-db-main { flex: 1 1 260px; min-width: 0; }
	.mh-db-title { font-weight: 600; }
	.mh-db-meta { font-size: 0.85em; color: #6f6f6f; margin-top: 2px; }
	.mh-db-action { flex: 0 0 auto; }
	.mh-db-action a { text-decoration: none; padding: 6px 13px; border-radius: 4px;
		background: #0073aa; color: #fff !important; font-size: 0.85em; display: inline-block; }
	.mh-db-action a.is-quiet { background: #f0f0f1; color: #2c3338 !important; }

	.mh-db-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 0.72em;
		font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; }
	.mh-db-badge.warn { background: #fcf0d8; color: #8a6116; }
	.mh-db-badge.beta { background: #1b5e20; color: #fff; }

	.mh-db-progress { flex: 0 0 150px; }
	.mh-db-bar { height: 7px; border-radius: 4px; background: #eceef0; overflow: hidden; }
	.mh-db-bar span { display: block; height: 100%; background: #1b5e20; }

	.mh-db-foot { padding: 11px 18px; background: #fbfbfc; border-top: 1px solid #f0f0f1; font-size: 0.87em; }
	.mh-db-foot a { text-decoration: none; }

	.mh-db-quick { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 25px; }
	.mh-db-quick a { flex: 1 1 200px; padding: 15px 18px; background: #fff; border: 1px solid #e2e4e7;
		border-radius: 6px; text-decoration: none; color: #1d2327; }
	.mh-db-quick a:hover { border-color: #0073aa; }
	.mh-db-quick strong { display: block; margin-bottom: 3px; }
	.mh-db-quick span { font-size: 0.85em; color: #6f6f6f; }

	@media (max-width: 600px) {
		.mh-db-list li { flex-direction: column; align-items: flex-start; gap: 8px; }
		.mh-db-progress { flex-basis: 100%; }
	}
</style>

<div class="mh-db">

	<h2>Hallo <?= esc_html( $vorname ) ?></h2>
	<p class="mh-db-lead">
		<?php if ( 0 === $offen_gesamt ) : ?>
			Im Moment wartet nichts auf dich.
		<?php else : ?>
			<?= (int) $offen_gesamt ?> Vorgänge brauchen deine Aufmerksamkeit.
		<?php endif; ?>
		<?php if ( $data['is_admin'] ) : ?>
			<?php if ( $data['show_all'] ) : ?>
				&nbsp;·&nbsp;<a href="<?= esc_url( remove_query_arg( 'mh_all' ) ) ?>">Nur meine Vorgänge zeigen</a>
			<?php else : ?>
				&nbsp;·&nbsp;<a href="<?= esc_url( add_query_arg( 'mh_all', '1' ) ) ?>">Als Administrator alle Vorgänge zeigen</a>
			<?php endif; ?>
		<?php endif; ?>
	</p>

	<!-- SCHNELLZUGRIFF: neue Vorgänge starten -->
	<div class="mh-db-quick">
		<?php if ( '' !== $links['abmeldung'] ) : ?>
			<a href="<?= esc_url( $links['abmeldung'] ) ?>">
				<strong>Abmeldung ausfüllen</strong>
				<span>Ausschulung einer Schüler*in mit Zeugnis und Konferenzprotokoll</span>
			</a>
		<?php endif; ?>
		<?php if ( '' !== $links['absentismus_fall'] ) : ?>
			<a href="<?= esc_url( $links['absentismus_fall'] ) ?>">
				<strong>Absentismus-Fall anlegen</strong>
				<span>Fehlzeiten-Verfahren starten oder fortsetzen</span>
			</a>
		<?php endif; ?>
		<?php if ( '' !== $links['dienstbefreiung'] ) : ?>
			<a href="<?= esc_url( $links['dienstbefreiung'] ) ?>">
				<strong>Dienstbefreiung beantragen</strong>
				<span>Antrag ausfüllen und als PDF herunterladen</span>
			</a>
		<?php endif; ?>
	</div>

	<!-- 1. NOTEN, DIE ICH SCHULDE -->
	<div class="mh-db-card">
		<h3>
			Noten, die von dir erwartet werden
			<span class="mh-db-count <?= count( $noten_todo ) ? 'is-open' : '' ?>"><?= count( $noten_todo ) ?></span>
			<span class="mh-db-badge beta">Beta</span>
		</h3>
		<div class="mh-db-body">
			<?php if ( empty( $noten_todo ) ) : ?>
				<p class="mh-db-empty">Nichts offen – es wartet keine Noteneingabe auf dich.</p>
			<?php else : ?>
				<ul class="mh-db-list">
					<?php foreach ( $noten_todo as $todo ) :
						$fd = $todo['case']['form_data'] ?? []; ?>
						<li>
							<div class="mh-db-main">
								<div class="mh-db-title">
									<?= esc_html( (string) ( $todo['item']['subject'] ?? '' ) ) ?>
									<?php if ( $todo['reminders'] > 0 ) : ?>
										<span class="mh-db-badge warn"><?= (int) $todo['reminders'] ?>× erinnert</span>
									<?php endif; ?>
								</div>
								<div class="mh-db-meta">
									<?= esc_html( (string) ( $fd['lastname'] ?? '' ) ) ?>,
									<?= esc_html( (string) ( $fd['firstname'] ?? '' ) ) ?>
									· Klasse <?= esc_html( (string) ( $fd['class_name'] ?? '' ) ) ?>
									<?php if ( ! empty( $fd['started_at'] ) ) : ?>
										· angefragt am <?= esc_html( $datum( $fd['started_at'] ) ) ?>
									<?php endif; ?>
								</div>
							</div>
							<div class="mh-db-action"><a href="<?= esc_url( $todo['link'] ) ?>">Note eintragen</a></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $links['noten_liste'] ) : ?>
			<div class="mh-db-foot"><a href="<?= esc_url( $links['noten_liste'] ) ?>">Alle meine Noteneingaben →</a></div>
		<?php endif; ?>
	</div>

	<!-- 2. OFFENE ABSENTISMUS-FÄLLE -->
	<div class="mh-db-card">
		<h3>
			Laufende Absentismus-Fälle
			<span class="mh-db-count <?= count( $absentismus ) ? 'is-open' : '' ?>"><?= count( $absentismus ) ?></span>
		</h3>
		<div class="mh-db-body">
			<?php if ( empty( $absentismus ) ) : ?>
				<p class="mh-db-empty">Kein laufendes Verfahren.</p>
			<?php else : ?>
				<ul class="mh-db-list">
					<?php foreach ( $absentismus as $case ) :
						$fd    = $case['form_data'] ?? [];
						$steps = $fd['steps'] ?? [];
						$last  = ! empty( $steps ) ? end( $steps ) : null;
						$url   = $fall_url( (int) $case['id'] ); ?>
						<li>
							<div class="mh-db-main">
								<div class="mh-db-title">
									<?= esc_html( (string) ( $fd['lastname'] ?? '' ) ) ?>,
									<?= esc_html( (string) ( $fd['firstname'] ?? '' ) ) ?>
									<?php if ( ! empty( $fd['class_name'] ) ) : ?>
										<span style="font-weight:400;color:#6f6f6f;">· <?= esc_html( (string) $fd['class_name'] ) ?></span>
									<?php endif; ?>
								</div>
								<div class="mh-db-meta">
									<?php if ( $last ) : ?>
										Zuletzt: <?= esc_html( $step_labels[ $last['type'] ?? '' ] ?? (string) ( $last['type'] ?? '' ) ) ?>
										<?php if ( ! empty( $last['created_at'] ) ) : ?>
											am <?= esc_html( $datum( $last['created_at'] ) ) ?>
										<?php endif; ?>
									<?php else : ?>
										Noch kein Schritt dokumentiert
									<?php endif; ?>
									· <?= count( $steps ) ?> <?= 1 === count( $steps ) ? 'Schritt' : 'Schritte' ?>
								</div>
							</div>
							<?php if ( '' !== $url ) : ?>
								<div class="mh-db-action"><a href="<?= esc_url( $url ) ?>">Fall öffnen</a></div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $links['absentismus_liste'] ) : ?>
			<div class="mh-db-foot">
				<a href="<?= esc_url( $links['absentismus_liste'] ) ?>">Alle Fälle inklusive abgeschlossener und archivierter →</a>
			</div>
		<?php endif; ?>
	</div>

	<!-- 3. EIGENE NOTENEINSAMMLUNGEN -->
	<div class="mh-db-card">
		<h3>
			Noteneinsammlungen, die du gestartet hast
			<span class="mh-db-count <?= count( $noten_owned ) ? 'is-open' : '' ?>"><?= count( $noten_owned ) ?></span>
			<span class="mh-db-badge beta">Beta</span>
		</h3>
		<div class="mh-db-body">
			<?php if ( empty( $noten_owned ) ) : ?>
				<p class="mh-db-empty">Keine laufende Noteneinsammlung.</p>
			<?php else : ?>
				<ul class="mh-db-list">
					<?php foreach ( $noten_owned as $n ) :
						$fd      = $n['case']['form_data'] ?? [];
						$percent = $n['total'] > 0 ? (int) round( $n['done'] / $n['total'] * 100 ) : 0; ?>
						<li>
							<div class="mh-db-main">
								<div class="mh-db-title">
									<?= esc_html( (string) ( $fd['lastname'] ?? '' ) ) ?>,
									<?= esc_html( (string) ( $fd['firstname'] ?? '' ) ) ?>
									<span style="font-weight:400;color:#6f6f6f;">· <?= esc_html( (string) ( $fd['class_name'] ?? '' ) ) ?></span>
								</div>
								<div class="mh-db-meta">
									<?= (int) $n['done'] ?> von <?= (int) $n['total'] ?> Noten liegen vor
									<?php if ( ! empty( $n['missing'] ) ) : ?>
										· es fehlen noch: <?= esc_html( implode( ', ', $n['missing'] ) ) ?>
									<?php endif; ?>
								</div>
							</div>
							<div class="mh-db-progress">
								<div class="mh-db-bar"><span style="width: <?= (int) $percent ?>%;"></span></div>
							</div>
							<div class="mh-db-action"><a href="<?= esc_url( $n['link'] ) ?>">Fall ansehen</a></div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<!-- 4. EIGENE EINSENDUNGEN -->
	<div class="mh-db-card">
		<h3>
			Meine letzten Anträge
			<span class="mh-db-count"><?= (int) $submissions['total'] ?></span>
		</h3>
		<div class="mh-db-body">
			<?php if ( empty( $submissions['items'] ) ) : ?>
				<p class="mh-db-empty">Noch nichts eingereicht.</p>
			<?php else : ?>
				<ul class="mh-db-list">
					<?php foreach ( $submissions['items'] as $sub ) :
						$sd   = $sub['data'] ?? [];
						$typ  = (string) ( $sub['form_type'] ?? '' );
						$name = trim( (string) ( $sd['lastname'] ?? '' ) . ', ' . (string) ( $sd['firstname'] ?? '' ), ', ' ); ?>
						<li>
							<div class="mh-db-main">
								<div class="mh-db-title">
									<?= esc_html( $form_titel[ $typ ] ?? $typ ) ?>
									<?php if ( '' !== $name ) : ?>
										<span style="font-weight:400;color:#6f6f6f;">· <?= esc_html( $name ) ?></span>
									<?php endif; ?>
								</div>
								<div class="mh-db-meta">
									Eingereicht am <?= esc_html( $datum( $sub['created_at'] ?? null ) ) ?>
									<?php if ( ! empty( $sd['class_name'] ) ) : ?>
										· Klasse <?= esc_html( (string) $sd['class_name'] ) ?>
									<?php endif; ?>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $links['meine_antraege'] ) : ?>
			<div class="mh-db-foot">
				<a href="<?= esc_url( $links['meine_antraege'] ) ?>">Alle Anträge mit PDF-Download und Bearbeiten →</a>
			</div>
		<?php else : ?>
			<div class="mh-db-foot" style="color:#6f6f6f;">
				Für die vollständige Liste mit PDF-Download bitte in den Plugin-Einstellungen eine Seite
				mit <code>[mh_my_submissions]</code> hinterlegen.
			</div>
		<?php endif; ?>
	</div>

</div>
