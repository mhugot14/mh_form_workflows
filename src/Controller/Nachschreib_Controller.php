<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use DateTimeImmutable;
use Mh\FormWorkflows\Model\Form\Nachschreib_Anmeldung_Form;
use Mh\FormWorkflows\Model\Form\Nachschreib_Termine_Form;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Nachschreib_Termin_Repository;
use Mh\FormWorkflows\Repository\Subject_Repository;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Service\Nachschreib_Slot_Provider_Interface;
use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Service\School_Date_Calculator;

/**
 * Class Nachschreib_Controller
 *
 * Anmeldung zu Nachschreibterminen: Shortcode [mh_nachschreib_anmeldung], Speichern,
 * PDF-Abruf und Löschen. Berechtigte Lehrkräfte (Einstellung „Terminverwaltung“) sehen
 * im selben Shortcode einen zweiten Reiter, in dem sie Termine freischalten, deaktivieren,
 * Uhrzeit/Raum/Kontingent ändern und lange Termine anlegen.
 *
 * Eigener Controller statt einer weiteren Weiche im Form_Controller: Der Ablauf ist ein
 * anderer (Speichern -> zurück zum Formular mit Erfolgsmeldung -> PDF per Link, statt
 * PDF direkt als Antwort auf das Absenden), und eine spätere Platzbuchung bekommt hier
 * ihren Platz, ohne den Abmelde-Workflow anzufassen.
 *
 * Alle Aktionen nur für angemeldete Nutzer - es geht um Schülernamen.
 */
class Nachschreib_Controller {

	public const SHORTCODE = 'mh_nachschreib_anmeldung';

	/** Wie viele eigene Anmeldungen unter dem Formular gelistet werden. */
	private const MY_LIST_LIMIT = 15;

	public function __construct(
		private Submission_Repository $repository,
		private Class_Repository $class_repo,
		private Teacher_Repository $teacher_repo,
		private Subject_Repository $subject_repo,
		private Teacher_Account_Repository $account_repo,
		private Nachschreib_Termin_Katalog $katalog,
		private Nachschreib_Slot_Provider_Interface $slots,
		private Nachschreib_Termin_Repository $termin_repo,
		private School_Date_Calculator $calendar,
		private Pdf_Generator $pdf_generator
	) {}

	/**
	 * Darf der aktuelle Nutzer Termine verwalten? Admins immer, sonst die in den
	 * Einstellungen ausgewählten Benutzer.
	 */
	public static function can_manage(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$options = get_option( 'mh_fw_settings', [] );
		$ids     = array_map( 'intval', (array) ( is_array( $options ) ? ( $options['ns_manager_ids'] ?? [] ) : [] ) );
		return in_array( get_current_user_id(), $ids, true );
	}

	/**
	 * Shortcode: Formular plus Liste der eigenen Anmeldungen.
	 */
	public function render_form( $attributes = [] ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte anmelden, um Schüler*innen zu einem Nachschreibtermin anzumelden.</p>';
		}

		$user_id    = get_current_user_id();
		$now        = current_datetime();
		$can_manage = self::can_manage();
		$page_url   = $this->current_page_url();
		$tab        = 'anmeldung';

		if ( $can_manage && 'termine' === ( $_GET['mh_ns_tab'] ?? '' ) ) {
			return $this->render_termine( $now, $page_url );
		}

		// Zustand nach dem Absenden (Fehler oder Erfolg) - einmalig, dann verworfen.
		$state = get_transient( $this->state_key() );
		if ( false !== $state ) {
			delete_transient( $this->state_key() );
		}
		$state = is_array( $state ) ? $state : [];

		$form_data   = $state['data'] ?? [];
		$form_errors = $state['errors'] ?? [];
		$success     = null;

		if ( ! empty( $state['success_id'] ) ) {
			$entry = $this->get_own_entry( (int) $state['success_id'], $user_id );
			if ( null !== $entry ) {
				$success = $entry;
			}
		} elseif ( empty( $form_data ) && isset( $_GET['mh_ns_edit'] ) ) {
			$entry = $this->get_own_entry( (int) $_GET['mh_ns_edit'], $user_id );
			if ( null !== $entry ) {
				$form_data       = $entry['form_data'];
				$form_data['id'] = (int) $entry['id'];
			}
		}

		// Termine je Art für die Auswahl im Formular. Beim Bearbeiten kann der gespeicherte
		// Termin bereits hinter der Frist liegen - er muss trotzdem wählbar bleiben.
		// Beim Bearbeiten zählen die eigenen Schüler*innen nicht zur Belegung - sonst wäre
		// ein voller Termin für die Anmeldung, die ihn füllt, plötzlich „ausgebucht“.
		$exclude_id  = (int) ( $form_data['id'] ?? 0 );
		$termin_data = [];
		foreach ( $this->katalog->all() as $typ => $def ) {
			$list = $this->slots->get_slots( $typ, $now, $exclude_id );
			if ( ( $form_data['termin_typ'] ?? '' ) === $typ && ! empty( $form_data['termin_datum'] ) ) {
				$known = array_column( $list, 'datum' );
				if ( ! in_array( $form_data['termin_datum'], $known, true ) ) {
					$saved = $this->slots->find_slot( $typ, (string) $form_data['termin_datum'], $exclude_id );
					if ( null !== $saved ) {
						$saved['bisher'] = true;
						array_unshift( $list, $saved );
					}
				}
			}
			$termin_data[ $typ ] = [
				'slots'     => $list,
				'free'      => $this->slots->allows_free_date( $typ ),
				'durations'  => $def['durations'],
				'dauer_min'  => $def['dauer_min'],
				'dauer_max'  => $def['dauer_max'],
				'weekdays'   => $def['weekdays'],
				'label'      => $def['label'],
				'postfach'   => is_array( $def['postfach'] ) ? '' : $def['postfach'],
				'zeit'       => Nachschreib_Termin_Katalog::zeit_label( $def['zeit_von'], $def['zeit_bis'] ),
				'raum'       => $def['raum'],
				'kontingent' => $def['kontingent'],
			];
		}

		$katalog       = $this->katalog;
		$classes_list  = $this->class_repo->get_real_classes();
		$teachers_list = $this->teacher_repo->get_all_teachers();
		$subjects_list = $this->subject_repo->get_all_subjects();
		$own_kuerzel   = $this->account_repo->get_kuerzel_for_user( $user_id )[0] ?? '';
		$my_entries    = $this->get_my_entries( $user_id );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/nachschreib/form.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * POST: Anmeldung prüfen und speichern. Bei Erfolg zurück zum Formular mit
	 * Erfolgsmeldung und PDF-Link (Post/Redirect/Get - ein Neuladen sendet nichts doppelt).
	 */
	public function handle_submit(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mh_nachschreib_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden und erneut absenden.' );
		}

		$user_id = get_current_user_id();
		$input   = wp_unslash( $_POST );
		$back    = $this->back_url( (string) ( $input['mh_back'] ?? '' ) );

		// Bearbeiten nur der eigenen Anmeldung - eine fremde ID wird ignoriert und
		// führt zu einer neuen Anmeldung statt zu einem Fehler.
		$submission_id = (int) ( $input['submission_id'] ?? 0 );
		$previous      = $submission_id > 0 ? $this->get_own_entry( $submission_id, $user_id ) : null;
		if ( null === $previous ) {
			$submission_id = 0;
		}

		$known_teachers = array_map(
			static fn( array $t ): string => mb_strtoupper( (string) $t['name'] ),
			$this->teacher_repo->get_all_teachers()
		);

		$form = new Nachschreib_Anmeldung_Form(
			$this->katalog,
			$this->slots,
			current_datetime(),
			$known_teachers,
			$previous['form_data'] ?? null,
			$submission_id
		);

		if ( ! $form->validate( $input ) ) {
			$refill       = array_merge( $input, $form->get_data() );
			$refill['id'] = $submission_id;
			// Die Rohzeilen behalten, damit auch fehlerhafte Eingaben wieder im Formular stehen.
			$refill['rows'] = is_array( $input['rows'] ?? null ) ? array_values( $input['rows'] ) : [];
			set_transient( $this->state_key(), [ 'data' => $refill, 'errors' => $form->get_errors() ], 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $back . '#mh-ns' );
			exit;
		}

		$data = $form->get_data();

		$data['submitted_kuerzel'] = $this->account_repo->get_kuerzel_for_user( $user_id )[0] ?? '';
		$data['submitted_by']      = $this->signer_name( $data['submitted_kuerzel'] );

		// Formularsitzung wiedererkennen: doppeltes Absenden aus demselben Formular
		// (Zurück-Taste, Doppelklick) aktualisiert dieselbe Anmeldung.
		$client_token = sanitize_text_field( (string) ( $input['client_token'] ?? '' ) );
		if ( preg_match( '/^[a-f0-9-]{36}$/', $client_token ) ) {
			$data['client_token'] = $client_token;
			if ( 0 === $submission_id ) {
				$submission_id = $this->repository->find_id_by_client_token( $user_id, Nachschreib_Termin_Katalog::FORM_TYPE, $client_token );
			}
		}

		$db_data = [
			'form_type' => Nachschreib_Termin_Katalog::FORM_TYPE,
			'status'    => 'submitted',
			'user_id'   => $user_id,
			'form_data' => $data,
		];

		if ( $submission_id > 0 ) {
			$this->repository->update( $submission_id, $db_data, $user_id );
			$entry_id = $submission_id;
		} else {
			$entry_id = $this->repository->create( $db_data );
		}

		if ( 0 === $entry_id ) {
			wp_die( 'Die Anmeldung konnte nicht gespeichert werden (Datenbankfehler).' );
		}

		set_transient( $this->state_key(), [ 'success_id' => $entry_id ], 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( remove_query_arg( 'mh_ns_edit', $back ) . '#mh-ns' );
		exit;
	}

	/**
	 * GET: PDF einer gespeicherten Anmeldung. Für die anmeldende Lehrkraft und für Admins.
	 */
	public function handle_download(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$id = (int) ( $_GET['id'] ?? 0 );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'mh_ns_pdf_' . $id ) ) {
			wp_die( 'Der Link ist abgelaufen. Bitte die Seite neu laden.' );
		}

		$entry = $this->repository->get_by_id( $id );
		if ( ! $entry || Nachschreib_Termin_Katalog::FORM_TYPE !== $entry['form_type'] ) {
			wp_die( 'Anmeldung nicht gefunden.' );
		}
		if ( (int) $entry['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$data     = is_array( $entry['form_data'] ) ? $entry['form_data'] : [];
		$html     = $this->build_pdf_html( $data, $id );
		$filename = sprintf(
			'%s_Nachschreiben_%s',
			(string) ( $data['termin_datum'] ?? date( 'Y-m-d' ) ),
			sanitize_file_name( (string) ( $data['termin_typ'] ?? '' ) )
		);

		$this->pdf_generator->generate_and_stream( $id, $html, $filename );
		exit;
	}

	/**
	 * POST: eigene Anmeldung löschen.
	 */
	public function handle_delete(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'mh_ns_delete_' . $id );

		$user_id = get_current_user_id();
		if ( null !== $this->get_own_entry( $id, $user_id ) ) {
			$this->repository->delete_submission( $id, $user_id );
		}

		$back = $this->back_url( sanitize_text_field( wp_unslash( (string) ( $_POST['mh_back'] ?? '' ) ) ) );
		wp_safe_redirect( add_query_arg( 'mh_ns_msg', 'deleted', remove_query_arg( 'mh_ns_edit', $back ) ) . '#mh-ns-list' );
		exit;
	}

	/**
	 * Reiter „Termine verwalten“: Tabelle einer Terminart zum direkten Bearbeiten.
	 */
	private function render_termine( DateTimeImmutable $now, string $page_url ): string {
		$typ = sanitize_key( (string) ( $_GET['mh_ns_typ'] ?? 'kurz' ) );
		if ( ! $this->katalog->exists( $typ ) ) {
			$typ = 'kurz';
		}
		$def = $this->katalog->get( $typ );

		$liste_datum = sanitize_text_field( (string) ( $_GET['mh_ns_liste'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $liste_datum ) ) {
			$buchungen = $this->build_buchungen( $typ, $liste_datum );
			if ( null !== $buchungen ) {
				$katalog    = $this->katalog;
				$can_manage = true;
				$tab        = 'termine';
				$back_url   = remove_query_arg( 'mh_ns_liste', add_query_arg( [ 'mh_ns_tab' => 'termine', 'mh_ns_typ' => $typ ], $page_url ) );
				$druck_url  = self::liste_druck_url( $typ, $liste_datum );
				ob_start();
				include MH_FW_PLUGIN_DIR . 'templates/nachschreib/buchungen.php';
				return ob_get_clean() ?: '';
			}
		}

		$weeks = 'weekly' === $def['slots'] ? min( 52, max( 4, (int) ( $_GET['mh_ns_wochen'] ?? 12 ) ) ) : 52;

		$state = get_transient( $this->termine_state_key() );
		if ( false !== $state ) {
			delete_transient( $this->termine_state_key() );
		}
		$state = is_array( $state ) ? $state : [];

		$katalog     = $this->katalog;
		$liste       = $this->slots->get_verwaltung( $typ, $now, $weeks );
		$form_errors = $state['errors'] ?? [];
		$form_input  = $state['data'] ?? [];
		$flash       = (string) ( $state['flash'] ?? '' );
		$can_manage  = true;
		$tab         = 'termine';

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/nachschreib/termine.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * POST: Terminverwaltung einer Art speichern.
	 */
	public function handle_termine_save(): void {
		if ( ! self::can_manage() ) {
			wp_die( 'Keine Berechtigung für die Terminverwaltung.' );
		}
		check_admin_referer( 'mh_ns_termine_save' );

		$input = wp_unslash( $_POST );
		$back  = $this->back_url( (string) ( $input['mh_back'] ?? '' ) );
		$form  = new Nachschreib_Termine_Form( $this->katalog, $this->calendar );

		if ( ! $form->validate( $input ) ) {
			set_transient( $this->termine_state_key(), [ 'data' => $input, 'errors' => $form->get_errors() ], 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $back . '#mh-ns' );
			exit;
		}

		$data       = $form->get_data();
		$configured = 'configured' === $this->katalog->get( $data['typ'] )['slots'];
		$belegung   = $this->repository->get_nachschreib_belegung( $data['typ'] );
		$user_id    = get_current_user_id();
		$saved      = 0;
		$hinweise   = [];

		foreach ( $data['ops'] as $op ) {
			$before = $this->termin_repo->find( $data['typ'], $op['datum'] );

			if ( 'delete' === $op['op'] ) {
				// Einen angelegten Termin mit Anmeldungen zu löschen, ließe diese ins Leere laufen.
				$angemeldet = $belegung[ $op['datum'] ]['schueler'] ?? 0;
				if ( $configured && $angemeldet > 0 ) {
					$hinweise[] = date( 'd.m.Y', (int) strtotime( $op['datum'] ) ) . ' nicht gelöscht – ' . $angemeldet . ' Schüler*innen angemeldet. Bitte stattdessen deaktivieren.';
					continue;
				}
				if ( null !== $before ) {
					$this->termin_repo->delete( $data['typ'], $op['datum'] );
					$saved++;
				}
				continue;
			}

			if ( null !== $before && $before == $op['werte'] + [ 'typ' => $data['typ'], 'datum' => $op['datum'] ] ) {
				continue; // unverändert
			}
			$this->termin_repo->upsert( $data['typ'], $op['datum'], $op['werte'], $user_id );
			$saved++;
		}

		$flash = 0 === $saved ? 'Keine Änderungen.' : $saved . ' Termin(e) gespeichert.';
		if ( ! empty( $hinweise ) ) {
			$flash .= ' ' . implode( ' ', $hinweise );
		}
		set_transient( $this->termine_state_key(), [ 'flash' => $flash ], 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( $back . '#mh-ns' );
		exit;
	}

	/**
	 * GET: Druckfertige Teilnehmerliste eines Termins als eigenständige HTML-Seite.
	 */
	public function handle_liste_druck(): void {
		if ( ! self::can_manage() ) {
			wp_die( 'Keine Berechtigung für die Terminverwaltung.' );
		}
		$typ   = sanitize_key( (string) ( $_GET['typ'] ?? '' ) );
		$datum = sanitize_text_field( (string) ( $_GET['datum'] ?? '' ) );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'mh_ns_liste_' . $typ . '_' . $datum ) ) {
			wp_die( 'Der Link ist abgelaufen. Bitte die Seite neu laden.' );
		}
		$buchungen = $this->build_buchungen( $typ, $datum );
		if ( null === $buchungen ) {
			wp_die( 'Termin nicht gefunden.' );
		}

		$katalog  = $this->katalog;
		$logo_src = $this->logo_data_uri();
		$stand    = current_datetime();

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		include MH_FW_PLUGIN_DIR . 'templates/nachschreib/liste-druck.php';
		exit;
	}

	/**
	 * Link auf die Druckliste (mit Nonce, nur für Berechtigte gültig).
	 */
	public static function liste_druck_url( string $typ, string $datum ): string {
		return wp_nonce_url(
			add_query_arg( [ 'action' => 'mh_nachschreib_liste', 'typ' => $typ, 'datum' => $datum ], admin_url( 'admin-post.php' ) ),
			'mh_ns_liste_' . $typ . '_' . $datum
		);
	}

	/**
	 * Buchungsübersicht eines Termins: alle angemeldeten Schüler*innen als flache Liste,
	 * alphabetisch - so sucht die Aufsicht am Nachschreibtag. Wer angemeldet hat und
	 * wann, steht in jeder Zeile; Bemerkungen der Anmeldungen gesammelt darunter.
	 *
	 * @return array{slot:array,def:array,eintraege:array,bemerkungen:array,anmeldungen:int}|null
	 */
	private function build_buchungen( string $typ, string $datum ): ?array {
		$def  = $this->katalog->get( $typ );
		$slot = null !== $def ? $this->slots->find_slot( $typ, $datum, 0, true ) : null;
		if ( null === $slot ) {
			return null;
		}

		$eintraege   = [];
		$bemerkungen = [];
		$anmeldungen = $this->repository->get_nachschreib_anmeldungen( $typ, $datum );

		foreach ( $anmeldungen as $nr => $a ) {
			$d   = $a['form_data'];
			$von = $this->signer_line( (string) ( $d['submitted_kuerzel'] ?? '' ), (string) ( $d['submitted_by'] ?? '' ) );
			if ( '' === $von ) {
				$user = get_userdata( $a['user_id'] );
				$von  = $user ? (string) $user->display_name : '';
			}
			foreach ( (array) ( $d['rows'] ?? [] ) as $r ) {
				$dauer       = (int) ( $r['duration'] ?? 0 );
				$eintraege[] = [
					'lastname'   => (string) ( $r['lastname'] ?? '' ),
					'firstname'  => (string) ( $r['firstname'] ?? '' ),
					'class_name' => (string) ( $r['class_name'] ?? '' ),
					'subject'    => (string) ( $r['subject'] ?? '' ),
					'teacher'    => (string) ( $r['teacher'] ?? '' ),
					'duration'   => $dauer,
					'ende'       => $this->ende( (string) $slot['zeit_von'], $dauer ),
					'aids'       => (string) ( $r['aids'] ?? '' ),
					'von'        => $von,
					'eingang'    => (string) $a['created_at'],
					'anmeldung'  => $nr + 1,
				];
			}
			if ( '' !== trim( (string) ( $d['remark'] ?? '' ) ) ) {
				$bemerkungen[] = [ 'anmeldung' => $nr + 1, 'von' => $von, 'text' => (string) $d['remark'] ];
			}
		}

		// Alphabetisch ohne Umlaut-Sonderfall („Äbel“ bei A, nicht hinter Z).
		$key = static fn( array $e ): string => mb_strtolower( remove_accents( $e['lastname'] . ' ' . $e['firstname'] ) );
		usort( $eintraege, static fn( array $a, array $b ): int => strcmp( $key( $a ), $key( $b ) ) );

		return [
			'slot'        => $slot,
			'def'         => $def,
			'eintraege'   => $eintraege,
			'bemerkungen' => $bemerkungen,
			'anmeldungen' => count( $anmeldungen ),
		];
	}

	/**
	 * Spätestes Ende bei bekannter Beginnzeit: „14:50“ + 90 Min. = „16:20“.
	 */
	private function ende( string $von, int $minuten ): string {
		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $von, $m ) || $minuten <= 0 ) {
			return '';
		}
		$total = (int) $m[1] * 60 + (int) $m[2] + $minuten;
		return sprintf( '%02d:%02d', intdiv( $total, 60 ) % 24, $total % 60 );
	}

	private function termine_state_key(): string {
		return 'mh_ns_termine_state_' . get_current_user_id();
	}

	/**
	 * Link auf das PDF einer Anmeldung (auch aus der Admin-Liste genutzt).
	 */
	public static function pdf_url( int $id ): string {
		return wp_nonce_url(
			add_query_arg( [ 'action' => 'mh_nachschreib_pdf', 'id' => $id ], admin_url( 'admin-post.php' ) ),
			'mh_ns_pdf_' . $id
		);
	}

	/**
	 * Setzt das PDF-HTML zusammen. Seite 1: die Meldung (wie die Papiervorlage),
	 * Seite 2 ff.: je Schüler*in ein ausgefülltes Deckblatt-Etikett für die Aufgabenstellung.
	 */
	private function build_pdf_html( array $data, int $entry_id ): string {
		$katalog  = $this->katalog;
		$def      = $katalog->get( (string) ( $data['termin_typ'] ?? '' ) ) ?? [];
		// Bevorzugt die bei der Anmeldung gespeicherte Momentaufnahme, sonst neu berechnen.
		$termin   = is_array( $data['termin'] ?? null )
			? $data['termin']
			: ( $this->slots->find_slot( (string) ( $data['termin_typ'] ?? '' ), (string) ( $data['termin_datum'] ?? '' ) ) ?? [] );
		$logo_src = $this->logo_data_uri();
		// Bei jedem PDF neu ermittelt, damit auch ältere Anmeldungen einheitlich erscheinen.
		$signer   = $this->signer_line( (string) ( $data['submitted_kuerzel'] ?? '' ), (string) ( $data['submitted_by'] ?? '' ) );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/nachschreib/pdf.php';
		return (string) ob_get_clean();
	}

	/**
	 * Name der unterschreibenden Lehrkraft - zum Kürzel aus WebUntis, nicht aus dem
	 * WordPress-Profil. Sonst könnten Name und Kürzel auf dem PDF auseinanderlaufen
	 * (anderer Profilname, falsche Kürzel-Zuordnung).
	 */
	private function signer_name( string $kuerzel ): string {
		if ( '' !== $kuerzel ) {
			$name = $this->account_repo->get_display_name( $kuerzel );
			return $name !== $kuerzel ? $name : '';
		}
		$user = wp_get_current_user();
		return trim( $user->first_name . ' ' . $user->last_name ) ?: (string) $user->display_name;
	}

	/**
	 * Unterschriftszeile „Vorname Nachname (KÜR)“. Mit Kürzel stammt der Name immer aus
	 * WebUntis; ist dort keiner hinterlegt, steht nur das Kürzel. Ohne Kürzel der
	 * gespeicherte Name ohne Kürzel.
	 */
	private function signer_line( string $kuerzel, string $fallback_name ): string {
		if ( '' === $kuerzel ) {
			return $fallback_name;
		}
		$name = $this->account_repo->get_display_name( $kuerzel );
		return $name !== $kuerzel ? $name . ' (' . $kuerzel . ')' : $kuerzel;
	}

	private function logo_data_uri(): string {
		$file = MH_FW_PLUGIN_DIR . 'assets/img/lebk-logo.jpg';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		return 'data:image/jpeg;base64,' . base64_encode( (string) file_get_contents( $file ) );
	}

	/**
	 * @return array|null Eintrag mit dekodiertem form_data, nur wenn er dem Nutzer gehört
	 *                    und eine Nachschreib-Anmeldung ist.
	 */
	private function get_own_entry( int $id, int $user_id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		$entry = $this->repository->get_by_id( $id );
		if ( ! $entry
			|| Nachschreib_Termin_Katalog::FORM_TYPE !== $entry['form_type']
			|| (int) $entry['user_id'] !== $user_id
			|| ! is_array( $entry['form_data'] ) ) {
			return null;
		}
		return $entry;
	}

	/**
	 * @return array<int,array> Die letzten eigenen Anmeldungen, form_data dekodiert.
	 */
	private function get_my_entries( int $user_id ): array {
		$rows = $this->repository->get_submissions_by_user( $user_id, [ Nachschreib_Termin_Katalog::FORM_TYPE ] );
		$out  = [];
		foreach ( array_slice( $rows, 0, self::MY_LIST_LIMIT ) as $row ) {
			$row['form_data'] = is_string( $row['form_data'] ) ? ( json_decode( $row['form_data'], true ) ?: [] ) : (array) $row['form_data'];
			$out[]            = $row;
		}
		return $out;
	}

	private function state_key(): string {
		return 'mh_ns_state_' . get_current_user_id();
	}

	/**
	 * Adresse der aktuellen Seite ohne Einmal-Parameter - Ziel der Formulare.
	 */
	private function current_page_url(): string {
		// Nicht home_url( $uri ): Liegt WordPress in einem Unterverzeichnis, enthält
		// REQUEST_URI dieses bereits - der Pfad stünde sonst doppelt in der Adresse.
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$url  = esc_url_raw( set_url_scheme( 'http://' . $host . $uri ) );
		return remove_query_arg( [ 'mh_ns_msg', 'mh_ns_edit' ], $url );
	}

	/**
	 * Rücksprung nur auf diese Website (wp_validate_redirect), sonst auf den Referer.
	 */
	private function back_url( string $candidate ): string {
		$fallback = wp_get_referer() ?: home_url( '/' );
		return wp_validate_redirect( $candidate, $fallback );
	}
}
