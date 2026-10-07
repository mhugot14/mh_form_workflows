<?php
/**
 * View: Anmeldung zum Nachschreibtermin [mh_nachschreib_anmeldung]
 *
 * Vom Nachschreib_Controller bereitgestellt:
 * @var array                      $form_data     Eingaben (nach Fehler) oder gespeicherte Anmeldung (Bearbeiten)
 * @var array<string,string>       $form_errors   Fehlermeldungen, Zeilenfelder als "rows.<i>.<feld>"
 * @var array|null                 $success       Gerade gespeicherte Anmeldung
 * @var array<string,array>        $termin_data   Termine und Regeln je Art (fürs JS)
 * @var Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog $katalog
 * @var array                      $classes_list
 * @var array                      $teachers_list
 * @var array                      $subjects_list
 * @var array<string,array>        $subject_lists Fächer je Bildungsgang, Stundentafel zuerst
 * @var string                     $own_kuerzel
 * @var array                      $my_entries
 * @var string                     $page_url
 */

use Mh\FormWorkflows\Controller\Nachschreib_Controller;
use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;

if ( ! defined( 'ABSPATH' ) ) exit;

$typen      = $katalog->all();
$cur_typ    = (string) ( $form_data['termin_typ'] ?? '' );
$cur_datum  = (string) ( $form_data['termin_datum'] ?? '' );
$is_edit    = ! empty( $form_data['id'] );
$err        = static fn( string $key ): string => isset( $form_errors[ $key ] ) ? ' is-invalid' : '';
$rows       = is_array( $form_data['rows'] ?? null ) ? array_values( $form_data['rows'] ) : [];
if ( empty( $rows ) ) {
	$rows = [ [] ];
}
$fmt_date = static fn( string $ymd ): string => '' === $ymd ? '' : date_i18n( 'D, d.m.Y', strtotime( $ymd ) );

/**
 * Eine Schülerzeile. Wird für vorhandene Zeilen und - mit Platzhalter "__I__" - für die
 * <template>-Vorlage benutzt, aus der das JS neue Zeilen klont. So gibt es das Markup nur einmal.
 */
// Lehrkräfte als Auswahl „KÜRZEL – Vorname Nachname“, sortiert nach Kürzel.
$teacher_names = [];
foreach ( $teachers_list as $t ) {
	$teacher_names[ mb_strtoupper( (string) $t['name'] ) ] = trim( ( $t['fore_name'] ?? '' ) . ' ' . ( $t['long_name'] ?? '' ) );
}
ksort( $teacher_names );

// Fächer-Vorschläge: je Bildungsgang eine eigene <datalist> (Stundentafel zuerst), die
// beim Wählen der Klasse ans Fach-Feld gehängt wird. Ohne Bildungsgang die allgemeine Liste.
$subject_list_ids = [];
foreach ( array_keys( $subject_lists ) as $n => $track_key ) {
	$subject_list_ids[ $track_key ] = 'mh-ns-subjects-' . $n;
}
$subject_list_for = static fn( string $track_key ): string => $subject_list_ids[ $track_key ] ?? 'mh-ns-subjects';
$class_lists      = [];
foreach ( $classes_list as $c ) {
	$class_lists[ (string) $c['wu_id'] ] = $subject_list_for( (string) ( $c['track_key'] ?? '' ) );
}

$render_row = static function ( $i, array $row ) use ( $classes_list, $own_kuerzel, $err, $cur_typ, $katalog, $teacher_names, $subject_list_for, $class_lists ): void {
	$name       = static fn( string $f ): string => 'rows[' . $i . '][' . $f . ']';
	$v          = static fn( string $f, string $def = '' ): string => esc_attr( (string) ( $row[ $f ] ?? $def ) );
	$class_id   = (string) ( $row['class_wu_id'] ?? '' );
	$student_id = (string) ( $row['student_wu_id'] ?? '' );
	$durations  = '' !== $cur_typ ? $katalog->durations( $cur_typ ) : [];
	// Nach einem Fehler kommt die Rohform zurück („custom“ + Zusatzfeld), sonst die Minuten.
	$raw_dur    = (string) ( $row['duration'] ?? '' );
	$cur_dur    = 'custom' === $raw_dur ? (int) ( $row['duration_custom'] ?? 0 ) : (int) $raw_dur;
	$is_custom  = $cur_dur > 0 && ! in_array( $cur_dur, $durations, true );
	$teacher    = mb_strtoupper( (string) ( $row['teacher'] ?? $own_kuerzel ) );
	$e          = static fn( string $f ): string => is_int( $i ) ? $err( 'rows.' . $i . '.' . $f ) : '';
	?>
	<div class="mh-ns-row" data-index="<?= esc_attr( (string) $i ) ?>"
	     data-class="<?= esc_attr( $class_id ) ?>" data-student="<?= esc_attr( $student_id ) ?>">
		<div class="mh-ns-row-head">
			<span class="mh-ns-row-nr"><?= is_int( $i ) ? (int) $i + 1 : '' ?></span>
			<span class="mh-ns-row-title">Schüler*in</span>
			<button type="button" class="mh-ns-row-remove" title="Zeile entfernen" aria-label="Zeile entfernen">&times;</button>
		</div>
		<div class="mh-ns-row-grid">
			<div class="mh-ns-field mh-ns-f-class<?= $e( 'class' ) ?>">
				<label>Klasse</label>
				<?php if ( ! empty( $classes_list ) ) : ?>
					<select class="js-class" name="<?= $name( 'class_wu_id' ) ?>">
						<option value="">– wählen –</option>
						<?php foreach ( $classes_list as $c ) : ?>
							<option value="<?= (int) $c['wu_id'] ?>" data-name="<?= esc_attr( $c['name'] ) ?>" data-subjects="<?= esc_attr( $subject_list_for( (string) ( $c['track_key'] ?? '' ) ) ) ?>"><?= esc_html( $c['name'] ) ?></option>
						<?php endforeach; ?>
						<option value="0" data-manual="1">andere Klasse …</option>
					</select>
				<?php else : ?>
					<input type="hidden" class="js-class" name="<?= $name( 'class_wu_id' ) ?>" value="0">
				<?php endif; ?>
				<input type="text" class="js-class-name mh-ns-manual" name="<?= $name( 'class_name' ) ?>" value="<?= $v( 'class_name' ) ?>" placeholder="Klasse, z. B. H11E" autocomplete="off">
			</div>

			<div class="mh-ns-field mh-ns-f-student<?= $e( 'student' ) ?>">
				<label>Name, Vorname</label>
				<select class="js-student" name="<?= $name( 'student_wu_id' ) ?>" disabled>
					<option value="">– erst Klasse wählen –</option>
				</select>
				<div class="mh-ns-manual mh-ns-name-pair">
					<input type="text" class="js-lastname" name="<?= $name( 'lastname' ) ?>" value="<?= $v( 'lastname' ) ?>" placeholder="Nachname" autocomplete="off">
					<input type="text" class="js-firstname" name="<?= $name( 'firstname' ) ?>" value="<?= $v( 'firstname' ) ?>" placeholder="Vorname" autocomplete="off">
				</div>
			</div>

			<div class="mh-ns-field mh-ns-f-subject<?= $e( 'subject' ) ?>">
				<label>Fach</label>
				<input type="text" class="js-subject" name="<?= $name( 'subject' ) ?>" value="<?= $v( 'subject' ) ?>" list="<?= esc_attr( $class_lists[ $class_id ] ?? 'mh-ns-subjects' ) ?>" placeholder="z. B. E" autocomplete="off">
			</div>

			<div class="mh-ns-field mh-ns-f-teacher<?= $e( 'teacher' ) ?>">
				<label>Fachlehrkraft</label>
				<?php if ( ! empty( $teacher_names ) ) : ?>
					<select class="js-teacher" name="<?= $name( 'teacher' ) ?>">
						<option value="">– wählen –</option>
						<?php if ( '' !== $teacher && ! isset( $teacher_names[ $teacher ] ) ) : ?>
							<option value="<?= esc_attr( $teacher ) ?>" selected><?= esc_html( $teacher ) ?></option>
						<?php endif; ?>
						<?php foreach ( $teacher_names as $kz => $full ) : ?>
							<option value="<?= esc_attr( $kz ) ?>" <?php selected( $teacher, $kz ); ?>><?= esc_html( $kz . ( '' !== $full ? ' – ' . $full : '' ) ) ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" class="js-teacher" name="<?= $name( 'teacher' ) ?>" value="<?= esc_attr( $teacher ) ?>" placeholder="Kürzel" autocomplete="off">
				<?php endif; ?>
			</div>

			<div class="mh-ns-field mh-ns-f-duration<?= $e( 'duration' ) ?><?= $is_custom ? ' is-custom' : '' ?>">
				<label>Dauer</label>
				<select class="js-duration" name="<?= $name( 'duration' ) ?>" data-value="<?= $cur_dur > 0 ? (int) $cur_dur : '' ?>">
					<?php if ( empty( $durations ) ) : ?>
						<option value="">– erst Terminart –</option>
					<?php else : ?>
						<option value="">– Min. –</option>
						<?php foreach ( $durations as $d ) : ?>
							<option value="<?= (int) $d ?>" <?php selected( $cur_dur, $d ); ?>><?= (int) $d ?> Min.</option>
						<?php endforeach; ?>
						<option value="custom" <?php selected( $is_custom ); ?>>andere …</option>
					<?php endif; ?>
				</select>
				<input type="number" class="js-duration-custom mh-ns-custom" name="<?= $name( 'duration_custom' ) ?>" value="<?= $is_custom ? (int) $cur_dur : '' ?>" min="1" max="600" step="5" placeholder="Minuten" inputmode="numeric">
			</div>

			<div class="mh-ns-field mh-ns-f-aids<?= $e( 'aids' ) ?>">
				<label>Hilfsmittel</label>
				<input type="text" class="js-aids" name="<?= $name( 'aids' ) ?>" value="<?= $v( 'aids' ) ?>" list="mh-ns-aids" placeholder="z. B. keine" autocomplete="off">
			</div>
		</div>
	</div>
	<?php
};
?>

<?php include __DIR__ . '/partial-style.php'; ?>

<div class="mh-ns" id="mh-ns">

	<?php include __DIR__ . '/partial-tabs.php'; ?>

	<?php if ( null !== $success ) :
		$s_data   = $success['form_data'];
		$s_termin = is_array( $s_data['termin'] ?? null ) ? $s_data['termin'] : [];
		$s_def    = $katalog->get( (string) ( $s_data['termin_typ'] ?? '' ) ) ?? [];
		$s_count  = count( $s_data['rows'] ?? [] );
		?>
		<div class="mh-ns-success" role="status">
			<h3>✓ Anmeldung gespeichert</h3>
			<div>
				<strong><?= esc_html( $s_def['label'] ?? '' ) ?>: <?= esc_html( $s_termin['label'] ?? '' ) ?></strong>
				· <?= esc_html( $s_termin['zeit'] ?? '' ) ?> · <?= (int) $s_count ?> Schüler*in<?= 1 === $s_count ? '' : 'nen' ?>
			</div>
			<ol>
				<li>PDF öffnen, ausdrucken und unterschreiben. Ab Seite 2 stehen die ausgefüllten Deckblätter für die Aufgabenstellungen.</li>
				<li>Meldung zusammen mit den Arbeitsunterlagen <strong>bis <?= esc_html( $s_termin['frist_label'] ?? '' ) ?></strong>
					in das Postfach <strong>„<?= esc_html( $s_termin['postfach'] ?? '' ) ?>“</strong> legen (<?= esc_html( Nachschreib_Termin_Katalog::POSTFACH_ORT ) ?>).</li>
				<li>Die Schüler*innen über Termin und Raum (<?= esc_html( $s_termin['raum'] ?? '' ) ?>) informieren und auf die Ausweispflicht hinweisen.</li>
			</ol>
			<div class="mh-ns-actions" style="margin-top:0;">
				<a class="mh-ns-btn mh-ns-btn-primary" href="<?= esc_url( Nachschreib_Controller::pdf_url( (int) $success['id'] ) ) ?>" target="_blank" rel="noopener">PDF öffnen</a>
				<a class="mh-ns-btn" href="<?= esc_url( add_query_arg( 'mh_ns_edit', (int) $success['id'], $page_url ) ) ?>#mh-ns">Bearbeiten</a>
				<a class="mh-ns-btn" href="<?= esc_url( $page_url ) ?>#mh-ns">Neue Anmeldung</a>
			</div>
		</div>
	<?php else : ?>

	<p class="mh-ns-intro">
		Schüler*innen für einen Nachschreibtermin anmelden. Aus den Angaben entsteht die unterschriftsfertige Meldung
		samt Deckblättern für die Aufgabenstellungen. Die Abgabe im Postfach bleibt wie bisher.
	</p>

	<?php if ( ! empty( $form_errors ) ) : ?>
		<div class="mh-ns-errors" role="alert">
			<h3>Bitte noch ergänzen bzw. korrigieren:</h3>
			<ul>
				<?php foreach ( array_unique( $form_errors ) as $msg ) : ?>
					<li><?= esc_html( (string) $msg ) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form id="mh-ns-form" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" method="post" novalidate>
		<input type="hidden" name="action" value="mh_nachschreib_submit">
		<input type="hidden" name="submission_id" value="<?= (int) ( $form_data['id'] ?? 0 ) ?>">
		<input type="hidden" name="client_token" value="<?= esc_attr( (string) ( $form_data['client_token'] ?? wp_generate_uuid4() ) ) ?>">
		<input type="hidden" name="mh_back" value="<?= esc_url( $page_url ) ?>">
		<?php wp_nonce_field( 'mh_nachschreib_submit' ); ?>

		<?php if ( $is_edit ) : ?>
			<div class="mh-ns-notice">Du bearbeitest eine gespeicherte Anmeldung. Nach dem Speichern bitte das PDF neu ausdrucken.</div>
		<?php endif; ?>

		<!-- 1. Terminart -->
		<section class="mh-ns-step" id="mh-ns-step-typ">
			<h3><span class="mh-ns-step-nr">1</span> Welcher Nachschreibtermin?</h3>
			<div class="mh-ns-step-body">
				<div class="mh-ns-types<?= $err( 'termin_typ' ) ?>" role="radiogroup">
					<?php foreach ( $typen as $key => $def ) : ?>
						<label class="mh-ns-type<?= $cur_typ === $key ? ' is-selected' : '' ?>">
							<input type="radio" name="termin_typ" value="<?= esc_attr( $key ) ?>" <?php checked( $cur_typ, $key ); ?>>
							<span class="mh-ns-type-title"><?= esc_html( $def['label'] ) ?></span>
							<span class="mh-ns-type-sub"><?= esc_html( $def['untertitel'] ) ?></span>
							<ul class="mh-ns-type-facts">
								<li><b>Zeit</b> <?= esc_html( Nachschreib_Termin_Katalog::zeit_label( $def['zeit_von'], $def['zeit_bis'] ) ) ?></li>
								<li><b>Raum</b> <?= esc_html( $def['raum'] ) ?></li>
								<li><b>Dauer</b> <?= esc_html( 'lang' === $key ? 'über 90 Min.' : 'bis ' . (int) $def['dauer_max'] . ' Min.' ) ?></li>
								<li><b>Abgabe</b> <?= (int) $def['frist_tage'] ?> Tage vorher, 11 Uhr</li>
							</ul>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- 2. Termin -->
		<section class="mh-ns-step<?= '' === $cur_typ ? ' is-locked' : '' ?>" id="mh-ns-step-datum">
			<h3><span class="mh-ns-step-nr">2</span> Termin auswählen</h3>
			<div class="mh-ns-step-body">
				<input type="hidden" name="termin_datum" id="mh-ns-datum" value="<?= esc_attr( $cur_datum ) ?>">
				<div id="mh-ns-date-area" class="<?= trim( $err( 'termin_datum' ) ) ?>">
					<p class="mh-ns-hint">Bitte zuerst oben die Art des Termins wählen.</p>
				</div>
				<div id="mh-ns-summary" class="mh-ns-summary" hidden></div>
			</div>
		</section>

		<!-- 3. Schüler*innen -->
		<section class="mh-ns-step" id="mh-ns-step-rows">
			<h3><span class="mh-ns-step-nr">3</span> Schüler*innen</h3>
			<div class="mh-ns-step-body">
				<p class="mh-ns-hint">
					Eine neue Zeile übernimmt Klasse, Fach, Lehrkraft, Dauer und Hilfsmittel aus der Zeile darüber –
					bei mehreren Schüler*innen derselben Klausur muss nur noch der Name gewählt werden.
				</p>
				<?php if ( isset( $form_errors['rows'] ) ) : ?>
					<div class="mh-ns-notice"><?= esc_html( $form_errors['rows'] ) ?></div>
				<?php endif; ?>
				<div class="mh-ns-rows" id="mh-ns-rows">
					<?php foreach ( $rows as $i => $row ) {
						$render_row( (int) $i, is_array( $row ) ? $row : [] );
					} ?>
				</div>
				<button type="button" class="mh-ns-add" id="mh-ns-add">+ weitere Schüler*in</button>
				<span class="mh-ns-add-hint" id="mh-ns-count"></span>
			</div>
		</section>

		<!-- 4. Bestätigen -->
		<section class="mh-ns-step" id="mh-ns-step-confirm">
			<h3><span class="mh-ns-step-nr">4</span> Bestätigen und PDF erstellen</h3>
			<div class="mh-ns-step-body">
				<label class="mh-ns-check<?= $err( 'confirm_berechtigung' ) ?>">
					<input type="checkbox" name="confirm_berechtigung" value="1" <?php checked( (string) ( $form_data['confirm_berechtigung'] ?? '' ), '1' ); ?>>
					<span>Ich habe überprüft, dass die Berechtigung der genannten Schüler*innen zur Teilnahme am Nachschreibtermin gegeben ist.</span>
				</label>
				<label class="mh-ns-check<?= $err( 'confirm_info' ) ?>">
					<input type="checkbox" name="confirm_info" value="1" <?php checked( (string) ( $form_data['confirm_info'] ?? '' ), '1' ); ?>>
					<span>Ich habe geprüft, ob weitere Nachschreibverpflichtungen bestehen, informiere die Schüler*innen rechtzeitig über Termin und Raum
						und weise sie auf die <strong>Ausweispflicht</strong> hin.</span>
				</label>
				<div class="mh-ns-field" style="margin-top:14px;">
					<label for="mh-ns-remark">Bemerkung für die Aufsicht <small>(optional, erscheint auf der Meldung)</small></label>
					<textarea id="mh-ns-remark" name="remark" maxlength="500" placeholder="z. B. Nachteilsausgleich: 15 Min. Zeitverlängerung für Zeile 2"><?= esc_textarea( (string) ( $form_data['remark'] ?? '' ) ) ?></textarea>
				</div>

				<div class="mh-ns-actions">
					<button type="submit" class="mh-ns-btn mh-ns-btn-primary"><?= $is_edit ? 'Änderungen speichern' : 'Anmeldung speichern' ?></button>
					<?php if ( $is_edit ) : ?>
						<a class="mh-ns-btn" href="<?= esc_url( $page_url ) ?>#mh-ns">Abbrechen</a>
					<?php endif; ?>
					<span class="mh-ns-hint" style="margin:0;">Danach kann das PDF geöffnet werden.</span>
				</div>
			</div>
		</section>

		<template id="mh-ns-row-tpl"><?php $render_row( '__I__', [] ); ?></template>

		<datalist id="mh-ns-subjects">
			<?php foreach ( $subjects_list as $s ) : ?>
				<option value="<?= esc_attr( $s['short_name'] ) ?>"><?= esc_html( $s['display_name'] ) ?></option>
			<?php endforeach; ?>
		</datalist>
		<?php foreach ( $subject_lists as $track_key => $list ) : ?>
			<datalist id="<?= esc_attr( $subject_list_for( (string) $track_key ) ) ?>">
				<?php foreach ( $list as $s ) : ?>
					<option value="<?= esc_attr( $s['short_name'] ) ?>"><?= esc_html( $s['display_name'] ) ?></option>
				<?php endforeach; ?>
			</datalist>
		<?php endforeach; ?>
		<datalist id="mh-ns-aids">
			<?php foreach ( Nachschreib_Termin_Katalog::HILFSMITTEL_VORSCHLAEGE as $a ) : ?>
				<option value="<?= esc_attr( $a ) ?>"></option>
			<?php endforeach; ?>
		</datalist>
	</form>
	<?php endif; ?>

	<!-- Eigene Anmeldungen -->
	<section class="mh-ns-step" id="mh-ns-list" style="margin-top:30px;">
		<h3>Meine Anmeldungen</h3>
		<div class="mh-ns-step-body">
			<?php if ( isset( $_GET['mh_ns_msg'] ) && 'deleted' === $_GET['mh_ns_msg'] ) : ?>
				<div class="mh-ns-flash">Anmeldung gelöscht.</div>
			<?php endif; ?>
			<?php if ( empty( $my_entries ) ) : ?>
				<p class="mh-ns-hint" style="margin:0;">Noch keine Anmeldungen.</p>
			<?php else : ?>
				<table class="mh-ns-list">
					<thead>
						<tr><th>Termin</th><th>Schüler*innen</th><th class="mh-ns-col-created">Erstellt</th><th></th></tr>
					</thead>
					<tbody>
					<?php
					$today = current_datetime()->format( 'Y-m-d' );
					foreach ( $my_entries as $entry ) :
						$d       = $entry['form_data'];
						$e_def   = $katalog->get( (string) ( $d['termin_typ'] ?? '' ) ) ?? [];
						$e_rows  = is_array( $d['rows'] ?? null ) ? $d['rows'] : [];
						$names   = array_map( static fn( $r ) => trim( ( $r['lastname'] ?? '' ) . ', ' . ( $r['firstname'] ?? '' ), ', ' ), $e_rows );
						$is_past = ( (string) ( $d['termin_datum'] ?? '' ) ) < $today;
						$eid     = (int) $entry['id'];
						?>
						<tr class="<?= $is_past ? 'is-past' : '' ?>">
							<td>
								<strong><?= esc_html( $fmt_date( (string) ( $d['termin_datum'] ?? '' ) ) ) ?></strong><br>
								<small><?= esc_html( $e_def['label'] ?? '' ) ?></small>
							</td>
							<td>
								<?= esc_html( implode( '; ', array_slice( $names, 0, 3 ) ) ) ?>
								<?php if ( count( $names ) > 3 ) : ?><span class="mh-ns-tag">+<?= count( $names ) - 3 ?></span><?php endif; ?>
							</td>
							<td class="mh-ns-col-created"><small><?= esc_html( date_i18n( 'd.m.Y', strtotime( (string) $entry['created_at'] ) ) ) ?></small></td>
							<td class="mh-ns-list-actions">
								<a class="mh-ns-btn mh-ns-btn-small" href="<?= esc_url( Nachschreib_Controller::pdf_url( $eid ) ) ?>" target="_blank" rel="noopener">PDF</a>
								<a class="mh-ns-btn mh-ns-btn-small" href="<?= esc_url( add_query_arg( 'mh_ns_edit', $eid, $page_url ) ) ?>#mh-ns">Bearbeiten</a>
								<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" onsubmit="return confirm('Diese Anmeldung löschen?');">
									<input type="hidden" name="action" value="mh_nachschreib_delete">
									<input type="hidden" name="id" value="<?= $eid ?>">
									<input type="hidden" name="mh_back" value="<?= esc_url( $page_url ) ?>">
									<?php wp_nonce_field( 'mh_ns_delete_' . $eid ); ?>
									<button type="submit" class="mh-ns-btn mh-ns-btn-small mh-ns-btn-danger">Löschen</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php if ( null === $success ) : ?>
<script>
(function () {
	'use strict';

	const TERMINE  = <?= wp_json_encode( $termin_data ) ?>;
	const AJAX_URL = <?= wp_json_encode( admin_url( 'admin-ajax.php' ) ) ?>;
	const NONCE    = <?= wp_json_encode( wp_create_nonce( 'mh_form_nonce' ) ) ?>;
	const MAX_ROWS = <?= (int) Mh\FormWorkflows\Model\Form\Nachschreib_Anmeldung_Form::MAX_ROWS ?>;
	const VISIBLE_DATES = 8;
	const WENIGE   = <?= (int) Nachschreib_Termin_Katalog::WENIGE_PLAETZE ?>;

	const form     = document.getElementById('mh-ns-form');
	if (!form) return;
	const rowsBox  = document.getElementById('mh-ns-rows');
	const tpl      = document.getElementById('mh-ns-row-tpl');
	const dateArea = document.getElementById('mh-ns-date-area');
	const datumInp = document.getElementById('mh-ns-datum');
	const summary  = document.getElementById('mh-ns-summary');
	const stepDate = document.getElementById('mh-ns-step-datum');
	const counter  = document.getElementById('mh-ns-count');

	const studentCache = {};
	let showAllDates = false;

	const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const currentTyp = () => (form.querySelector('input[name="termin_typ"]:checked') || {}).value || '';

	/* ---------- 1. Terminart ---------- */
	form.querySelectorAll('input[name="termin_typ"]').forEach(radio => {
		radio.addEventListener('change', () => {
			form.querySelectorAll('.mh-ns-type').forEach(l => l.classList.toggle('is-selected', l.contains(radio) && radio.checked));
			// Ein Datum der vorherigen Art passt nicht mehr.
			if (!findSlot(currentTyp(), datumInp.value)) datumInp.value = '';
			showAllDates = false;
			renderDates();
			rowsBox.querySelectorAll('.mh-ns-row').forEach(updateDurations);
			clearInvalid(form.querySelector('.mh-ns-types'));
		});
	});

	/* ---------- 2. Termin ---------- */
	function findSlot(typ, datum) {
		if (!typ || !datum || !TERMINE[typ]) return null;
		return TERMINE[typ].slots.find(s => s.datum === datum) || null;
	}

	function daysUntil(frist) {
		const d = new Date(frist.replace(' ', 'T'));
		return (d - new Date()) / 86400000;
	}

	function renderDates() {
		const typ = currentTyp();
		stepDate.classList.toggle('is-locked', !typ);
		if (!typ) {
			dateArea.innerHTML = '<p class="mh-ns-hint">Bitte zuerst oben die Art des Termins wählen.</p>';
			renderSummary();
			return;
		}
		const info = TERMINE[typ];
		let html = '';

		if (info.slots.length) {
			const list = showAllDates ? info.slots : info.slots.slice(0, VISIBLE_DATES);
			html += '<div class="mh-ns-dates">';
			list.forEach(s => {
				const soon = !s.bisher && daysUntil(s.frist) < 1;
				const full = s.ausgebucht && s.datum !== datumInp.value;
				const low  = !s.ausgebucht && s.rest <= WENIGE;
				const seats = s.ausgebucht ? 'ausgebucht'
					: (low ? 'nur noch ' + s.rest + (s.rest === 1 ? ' Platz' : ' Plätze') : s.rest + ' Plätze frei');
				html += '<button type="button" class="mh-ns-date' + (s.datum === datumInp.value ? ' is-selected' : '')
					+ (s.ausgebucht ? ' is-full' : (low ? ' is-low' : '')) + '" data-datum="' + esc(s.datum) + '"'
					+ (full ? ' disabled aria-disabled="true"' : '') + ' aria-pressed="' + (s.datum === datumInp.value) + '"'
					+ ' title="' + esc(s.zeit + ' · Raum ' + s.raum + ' · ' + s.belegt + ' von ' + s.kontingent + ' Plätzen belegt') + '">'
					+ '<span class="mh-ns-date-main">' + esc(s.label_kurz) + '</span>'
					+ '<span class="mh-ns-date-sub' + (soon ? ' is-soon' : '') + '">' + (s.bisher ? 'bisher gewählt' : 'Abgabe bis ' + esc(s.frist_label)) + '</span>'
					+ (s.hinweis ? '<span class="mh-ns-date-note">' + esc(s.hinweis) + '</span>' : '')
					+ '<span class="mh-ns-seats">' + esc(seats) + '</span>'
					+ '</button>';
			});
			html += '</div>';
			if (!showAllDates && info.slots.length > VISIBLE_DATES) {
				html += '<button type="button" class="mh-ns-more" data-more="1">Weitere ' + (info.slots.length - VISIBLE_DATES) + ' Termine anzeigen</button>';
			}
			html += '<div class="mh-ns-legend">'
				+ '<span><i style="background:#e7f3ea;border-color:#9ccaa8"></i>Plätze frei</span>'
				+ '<span><i style="background:#fff8dc;border-color:#e0b100"></i>höchstens ' + WENIGE + ' Plätze frei</span>'
				+ '<span><i style="background:#fdecec;border-color:#e3a3a3"></i>ausgebucht</span></div>';
		} else if (!info.free) {
			html += '<div class="mh-ns-notice">Für diese Terminart ist derzeit kein Termin mit offener Abgabefrist freigegeben.</div>';
		}

		if (info.free) {
			const wd = info.weekdays;
			html += '<div class="mh-ns-notice">'
				+ 'Lange Termine werden nach Bedarf mit der Abteilungs- bzw. Stufenleitung abgesprochen. Es ist noch kein langer Termin angelegt – bitte das abgesprochene Datum eintragen.'
				+ '</div>'
				+ '<div class="mh-ns-free"><label for="mh-ns-free-date"><strong>Datum:</strong></label>'
				+ '<input type="date" id="mh-ns-free-date" min="' + new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10) + '" value="' + esc(findSlot(typ, datumInp.value) ? '' : datumInp.value) + '">'
				+ '<span class="mh-ns-hint" id="mh-ns-free-msg" style="margin:0;"></span></div>';
			dateArea.dataset.weekdays = wd.join(',');
		}

		dateArea.innerHTML = html;
		renderSummary();
	}

	dateArea.addEventListener('click', e => {
		const more = e.target.closest('[data-more]');
		if (more) { showAllDates = true; renderDates(); return; }
		const btn = e.target.closest('.mh-ns-date');
		if (!btn) return;
		datumInp.value = btn.dataset.datum;
		const free = document.getElementById('mh-ns-free-date');
		if (free) free.value = '';
		renderDates();
		clearInvalid(dateArea);
	});

	dateArea.addEventListener('change', e => {
		if (e.target.id !== 'mh-ns-free-date') return;
		const val = e.target.value;
		const msg = document.getElementById('mh-ns-free-msg');
		const wd  = (dateArea.dataset.weekdays || '').split(',').map(Number);
		msg.textContent = '';
		if (!val) { datumInp.value = ''; renderSummary(); return; }
		const day = new Date(val + 'T12:00:00').getDay() || 7; // ISO: Mo=1 … So=7
		if (wd.indexOf(day) === -1) {
			msg.textContent = currentTyp() === 'samstag' ? 'Bitte einen Samstag wählen.' : 'Bitte einen Wochentag (Mo–Fr) wählen.';
			msg.style.color = '#c62828';
			datumInp.value = '';
		} else {
			datumInp.value = val;
			dateArea.querySelectorAll('.mh-ns-date').forEach(b => b.classList.remove('is-selected'));
			clearInvalid(dateArea);
		}
		renderSummary();
	});

	const rowCount = () => rowsBox.querySelectorAll('.mh-ns-row').length;

	/** Reicht das Kontingent des gewählten Termins für alle Zeilen? */
	function capacityProblem() {
		const slot = findSlot(currentTyp(), datumInp.value);
		if (!slot || rowCount() <= slot.rest) return '';
		return slot.rest === 0
			? 'Dieser Termin ist ausgebucht.'
			: 'Nur noch ' + slot.rest + (slot.rest === 1 ? ' Platz' : ' Plätze') + ' frei – angemeldet werden sollen ' + rowCount() + ' Schüler*innen.';
	}

	function renderSummary() {
		const typ  = currentTyp();
		const slot = findSlot(typ, datumInp.value);
		summary.classList.remove('is-warn');
		if (!typ || !datumInp.value) { summary.hidden = true; return; }
		const info = TERMINE[typ];
		if (slot) {
			const problem = capacityProblem();
			summary.innerHTML = '<strong>' + esc(info.label) + ': ' + esc(slot.label) + '</strong>'
				+ esc(slot.zeit) + ' · Raum ' + esc(slot.raum)
				+ (slot.hinweis ? ' · ' + esc(slot.hinweis) : '')
				+ ' · <b>' + slot.rest + ' von ' + slot.kontingent + ' Plätzen frei</b>'
				+ '<div class="mh-ns-deadline">Meldung + Arbeitsunterlagen bis ' + esc(slot.frist_label) + ' ins Postfach „' + esc(slot.postfach) + '“.</div>'
				+ (problem ? '<div class="mh-ns-deadline" style="color:#c62828;">⚠ ' + esc(problem) + ' Bitte einen anderen Termin wählen oder auf zwei Termine aufteilen.</div>' : '');
			summary.classList.toggle('is-warn', !!problem);
		} else {
			const d = new Date(datumInp.value + 'T12:00:00');
			summary.innerHTML = '<strong>' + esc(info.label) + ': ' + d.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) + '</strong>'
				+ esc(info.zeit) + ' · Raum: ' + esc(info.raum)
				+ '<div class="mh-ns-deadline">Meldung + Arbeitsunterlagen spätestens 2 Tage vorher, 11:00 Uhr, ins Postfach „' + esc(info.postfach) + '“.</div>';
		}
		summary.hidden = false;
	}

	/* ---------- 3. Schülerzeilen ---------- */
	function setManual(field, on) {
		field.classList.toggle('is-manual', !!on);
	}

	function loadStudents(row, classId, selectId) {
		const sel = row.querySelector('.js-student');
		const studentField = row.querySelector('.mh-ns-f-student');
		if (!classId || classId === '0') {
			sel.innerHTML = '<option value="0">von Hand eintragen</option>';
			sel.disabled = true;
			sel.style.display = classId === '0' ? 'none' : '';
			if (classId === '0') setManual(studentField, true);
			if (!classId) { sel.style.display = ''; sel.innerHTML = '<option value="">– erst Klasse wählen –</option>'; setManual(studentField, false); }
			return Promise.resolve();
		}
		sel.style.display = '';
		sel.disabled = false;

		const fill = list => {
			let html = '<option value="">– Schüler*in wählen –</option>';
			list.forEach(s => {
				html += '<option value="' + esc(s.wu_id) + '" data-last="' + esc(s.name) + '" data-first="' + esc(s.fore_name) + '">' + esc(s.name) + ', ' + esc(s.fore_name) + '</option>';
			});
			html += '<option value="0" data-manual="1">von Hand eintragen …</option>';
			sel.innerHTML = html;
			if (selectId !== undefined && selectId !== '') {
				sel.value = String(selectId);
				if (sel.value !== String(selectId)) sel.value = '0';
			}
			setManual(studentField, sel.value === '0');
		};

		if (studentCache[classId]) { fill(studentCache[classId]); return Promise.resolve(); }

		sel.innerHTML = '<option value="">lade Klassenliste …</option>';
		const fd = new FormData();
		fd.append('action', 'mh_get_students');
		fd.append('class_id', classId);
		fd.append('nonce', NONCE);
		return fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(r => r.json())
			.then(res => {
				studentCache[classId] = (res && res.success && Array.isArray(res.data)) ? res.data : [];
				fill(studentCache[classId]);
			})
			.catch(() => { studentCache[classId] = []; fill([]); });
	}

	/** Wirksame Minuten einer Zeile (Vorschlag oder individuell). */
	function durationOf(row) {
		const sel = row.querySelector('.js-duration');
		return sel.value === 'custom' ? row.querySelector('.js-duration-custom').value.trim() : sel.value;
	}

	function setDuration(row, minutes) {
		const sel    = row.querySelector('.js-duration');
		const custom = row.querySelector('.js-duration-custom');
		const field  = row.querySelector('.mh-ns-f-duration');
		const m      = String(minutes || '');
		sel.value = m;
		if (m && sel.value !== m) { sel.value = 'custom'; custom.value = m; }
		else if (sel.value !== 'custom') { custom.value = ''; }
		field.classList.toggle('is-custom', sel.value === 'custom');
	}

	function updateDurations(row) {
		const sel = row.querySelector('.js-duration');
		const typ = currentTyp();
		const keep = durationOf(row) || sel.dataset.value || '';
		if (!typ) {
			sel.innerHTML = '<option value="">– erst Terminart –</option>';
			row.querySelector('.mh-ns-f-duration').classList.remove('is-custom');
			return;
		}
		const info = TERMINE[typ];
		let html = '<option value="">– Min. –</option>';
		info.durations.forEach(d => { html += '<option value="' + d + '">' + d + ' Min.</option>'; });
		html += '<option value="custom">andere …</option>';
		sel.innerHTML = html;
		const custom = row.querySelector('.js-duration-custom');
		custom.min = info.dauer_min; custom.max = info.dauer_max;
		custom.placeholder = info.dauer_min + '–' + info.dauer_max + ' Min.';
		// Passt die bisherige Dauer nicht mehr zur Terminart, lieber leeren als falsch übernehmen.
		const n = parseInt(keep, 10);
		setDuration(row, n >= info.dauer_min && n <= info.dauer_max ? n : '');
		sel.dataset.value = '';
	}

	/** Fach-Vorschläge passend zum Bildungsgang der gewählten Klasse. */
	function syncSubjects(row) {
		const classSel = row.querySelector('.js-class');
		const opt = classSel.tagName === 'SELECT' ? classSel.options[classSel.selectedIndex] : null;
		row.querySelector('.js-subject').setAttribute('list', (opt && opt.dataset.subjects) || 'mh-ns-subjects');
	}

	function initRow(row) {
		const classSel  = row.querySelector('.js-class');
		const className = row.querySelector('.js-class-name');
		const classFld  = row.querySelector('.mh-ns-f-class');
		const studSel   = row.querySelector('.js-student');
		const last      = row.querySelector('.js-lastname');
		const first     = row.querySelector('.js-firstname');

		if (classSel.tagName === 'SELECT') {
			classSel.addEventListener('change', () => {
				const opt = classSel.options[classSel.selectedIndex];
				const manual = classSel.value === '0';
				setManual(classFld, manual);
				if (!manual) className.value = opt && classSel.value ? (opt.dataset.name || '') : '';
				else { className.value = ''; className.focus(); }
				last.value = ''; first.value = '';
				syncSubjects(row);
				loadStudents(row, classSel.value);
			});
		} else {
			// Keine WebUntis-Klassen vorhanden: alles von Hand.
			setManual(classFld, true);
			setManual(row.querySelector('.mh-ns-f-student'), true);
			studSel.style.display = 'none';
		}

		studSel.addEventListener('change', () => {
			const opt = studSel.options[studSel.selectedIndex];
			const manual = studSel.value === '0';
			setManual(row.querySelector('.mh-ns-f-student'), manual);
			if (manual) { last.value = ''; first.value = ''; last.focus(); }
			else { last.value = opt ? (opt.dataset.last || '') : ''; first.value = opt ? (opt.dataset.first || '') : ''; }
			clearInvalid(row.querySelector('.mh-ns-f-student'));
		});

		row.querySelector('.js-duration').addEventListener('change', e => {
			const isCustom = e.target.value === 'custom';
			row.querySelector('.mh-ns-f-duration').classList.toggle('is-custom', isCustom);
			if (isCustom) row.querySelector('.js-duration-custom').focus();
		});

		row.querySelector('.mh-ns-row-remove').addEventListener('click', () => {
			if (rowsBox.querySelectorAll('.mh-ns-row').length <= 1) return;
			row.remove();
			renumber();
		});

		row.querySelectorAll('input, select').forEach(el => {
			el.addEventListener('input', () => clearInvalid(el.closest('.mh-ns-field')));
			el.addEventListener('change', () => clearInvalid(el.closest('.mh-ns-field')));
		});

		updateDurations(row);
	}

	/** Gespeicherten Zustand einer Zeile wiederherstellen (nach Fehler / beim Bearbeiten). */
	function restoreRow(row) {
		const classSel = row.querySelector('.js-class');
		const classId  = row.dataset.class || '';
		const studId   = row.dataset.student || '';
		const classFld = row.querySelector('.mh-ns-f-class');
		const hasName  = row.querySelector('.js-lastname').value !== '';

		if (classSel.tagName !== 'SELECT') return;
		if (classId && classId !== '0') {
			classSel.value = classId;
			syncSubjects(row);
			if (classSel.value === classId) {
				loadStudents(row, classId, studId === '' && hasName ? '0' : studId);
				return;
			}
		}
		if (row.querySelector('.js-class-name').value !== '') {
			classSel.value = '0';
			setManual(classFld, true);
			loadStudents(row, '0');
		}
	}

	function renumber() {
		const rows = rowsBox.querySelectorAll('.mh-ns-row');
		rows.forEach((row, i) => {
			row.querySelector('.mh-ns-row-nr').textContent = i + 1;
			row.querySelectorAll('[name^="rows["]').forEach(el => {
				el.name = el.name.replace(/^rows\[[^\]]*\]/, 'rows[' + i + ']');
			});
		});
		rowsBox.classList.toggle('is-single', rows.length <= 1);
		document.getElementById('mh-ns-add').disabled = rows.length >= MAX_ROWS;
		counter.textContent = rows.length > 1 ? rows.length + ' Schüler*innen' : '';
		renderSummary(); // Kontingent-Hinweis hängt an der Zeilenzahl
	}

	document.getElementById('mh-ns-add').addEventListener('click', () => {
		const rows = rowsBox.querySelectorAll('.mh-ns-row');
		if (rows.length >= MAX_ROWS) return;
		const prev = rows[rows.length - 1];
		const frag = tpl.content.cloneNode(true);
		const row  = frag.querySelector('.mh-ns-row');
		rowsBox.appendChild(frag);
		initRow(row);
		renumber();

		// Angaben der Zeile darüber übernehmen - außer der Person.
		if (prev) {
			['.js-subject', '.js-teacher', '.js-aids'].forEach(s => { row.querySelector(s).value = prev.querySelector(s).value; });
			setDuration(row, durationOf(prev));
			const pClass = prev.querySelector('.js-class');
			const nClass = row.querySelector('.js-class');
			row.querySelector('.js-class-name').value = prev.querySelector('.js-class-name').value;
			if (pClass.tagName === 'SELECT') {
				nClass.value = pClass.value;
				syncSubjects(row);
				setManual(row.querySelector('.mh-ns-f-class'), pClass.value === '0');
				loadStudents(row, pClass.value).then(() => {
					const s = row.querySelector('.js-student');
					if (s.style.display !== 'none' && !s.disabled) s.focus();
				});
			}
		}
	});

	/* ---------- Prüfen vor dem Absenden ---------- */
	function markInvalid(el) { if (el) el.classList.add('is-invalid'); }
	function clearInvalid(el) { if (el) el.classList.remove('is-invalid'); }

	form.addEventListener('submit', e => {
		const problems = [];
		form.querySelectorAll('.is-invalid').forEach(clearInvalid);

		if (!currentTyp()) { problems.push('Art des Termins wählen'); markInvalid(form.querySelector('.mh-ns-types')); }
		else if (!datumInp.value) { problems.push('Termin auswählen'); markInvalid(dateArea); }
		else if (capacityProblem()) { problems.push(capacityProblem()); markInvalid(dateArea); renderSummary(); }

		const info = TERMINE[currentTyp()];
		rowsBox.querySelectorAll('.mh-ns-row').forEach((row, i) => {
			const v = s => row.querySelector(s).value.trim();
			const n = 'Zeile ' + (i + 1);
			if (!v('.js-class-name')) { problems.push(n + ': Klasse'); markInvalid(row.querySelector('.mh-ns-f-class')); }
			if (!v('.js-lastname') || !v('.js-firstname')) { problems.push(n + ': Schüler*in'); markInvalid(row.querySelector('.mh-ns-f-student')); }
			if (!v('.js-subject')) { problems.push(n + ': Fach'); markInvalid(row.querySelector('.mh-ns-f-subject')); }
			if (!v('.js-teacher')) { problems.push(n + ': Fachlehrkraft'); markInvalid(row.querySelector('.mh-ns-f-teacher')); }
			const dur = parseInt(durationOf(row), 10);
			if (!dur || (info && (dur < info.dauer_min || dur > info.dauer_max))) { problems.push(n + ': Dauer'); markInvalid(row.querySelector('.mh-ns-f-duration')); }
			if (!v('.js-aids')) { problems.push(n + ': Hilfsmittel'); markInvalid(row.querySelector('.mh-ns-f-aids')); }
		});

		['confirm_berechtigung', 'confirm_info'].forEach(name => {
			const cb = form.querySelector('input[name="' + name + '"]');
			if (!cb.checked) { problems.push('Bestätigung ankreuzen'); markInvalid(cb.closest('.mh-ns-check')); }
		});

		if (problems.length) {
			e.preventDefault();
			const first = form.querySelector('.is-invalid');
			if (first) {
				first.scrollIntoView({ behavior: 'smooth', block: 'center' });
				const input = first.querySelector('input:not([type=hidden]):not([type=radio]), select:not(:disabled)');
				if (input) setTimeout(() => input.focus({ preventScroll: true }), 300);
			}
			return;
		}
		form.querySelector('button[type="submit"]').disabled = true;
	});

	form.querySelectorAll('.mh-ns-check input').forEach(cb => cb.addEventListener('change', () => clearInvalid(cb.closest('.mh-ns-check'))));

	/* ---------- Start ---------- */
	rowsBox.querySelectorAll('.mh-ns-row').forEach(row => { initRow(row); restoreRow(row); });
	renumber();
	renderDates();
})();
</script>
<?php endif; ?>
