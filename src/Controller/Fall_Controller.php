<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Absentismus_Fall_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Model\Form\Form_Interface;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Gespraech_1_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Gespraech_2_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Ordnungsamt_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Attestauflage_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Mahnung_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Bussgeld_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Teilkonferenz_Form;
use Mh\FormWorkflows\Model\Form\Absentismus_Step_Beendigung_47_Form;
use InvalidArgumentException;

/**
 * Class Fall_Controller
 *
 * Orchestriert den Absentismus-Fall-Workflow (Fehlzeiten-Eskalationsverfahren):
 * Fall eröffnen, Schritte anlegen/bearbeiten/festschreiben, Timeline anzeigen,
 * PDF-Export. Bewusst getrennt von Form_Controller, da das Fall/Schritt-Lifecycle-
 * Paradigma sich stark von "ein Formular = eine Submission" unterscheidet.
 */
class Fall_Controller {

	/**
	 * Shortcode-Tag => Schritt-Typ für die 8 eigenständigen Einzelformular-
	 * Shortcodes (unabhängig von einem Fall). Einzige Quelle der Wahrheit für
	 * die Registrierung in Plugin_Bootstrap UND für render_standalone_step_form()
	 * (der Typ wird aus dem tatsächlich aufgerufenen Shortcode-Tag abgeleitet —
	 * eine einzige Methode bedient alle 8, keine 8 Kopien).
	 */
	public const STANDALONE_SHORTCODES = [
		'mh_absentismus_gespraech_1'   => 'gespraech_1',
		'mh_absentismus_gespraech_2'   => 'gespraech_2',
		'mh_absentismus_ordnungsamt'   => 'ordnungsamt',
		'mh_absentismus_attestauflage' => 'attestauflage',
		'mh_absentismus_mahnung'       => 'mahnung',
		'mh_absentismus_bussgeld'      => 'bussgeld',
		'mh_absentismus_teilkonferenz' => 'teilkonferenz',
		'mh_absentismus_beendigung_47' => 'beendigung_47',
	];

	public function __construct(
		private Absentismus_Fall_Repository $fall_repo,
		private Class_Repository $class_repo,
		private Student_Repository $student_repo,
		private Pdf_Generator $pdf_generator
	) {}

	/**
	 * Factory: Erzeugt das passende Schritt-Formular. Kein stiller Fallback —
	 * bei unbekanntem Typ ist ein harter Fehler sicherer als ein Default-Formular
	 * bei sensiblen Schülerdaten.
	 */
	private function get_step_form_instance( string $step_type ): Form_Interface {
		return match ( $step_type ) {
			'gespraech_1'   => new Absentismus_Step_Gespraech_1_Form(),
			'gespraech_2'   => new Absentismus_Step_Gespraech_2_Form(),
			'ordnungsamt'   => new Absentismus_Step_Ordnungsamt_Form(),
			'attestauflage' => new Absentismus_Step_Attestauflage_Form(),
			'mahnung'       => new Absentismus_Step_Mahnung_Form(),
			'bussgeld'      => new Absentismus_Step_Bussgeld_Form(),
			'teilkonferenz' => new Absentismus_Step_Teilkonferenz_Form(),
			'beendigung_47' => new Absentismus_Step_Beendigung_47_Form(),
			default         => throw new InvalidArgumentException( "Unbekannter Absentismus-Schritt-Typ: {$step_type}" ),
		};
	}

	// ------------------------------------------------------------------
	// Rendering (Shortcodes)
	// ------------------------------------------------------------------

	/**
	 * Shortcode-Callback [mh_absentismus_fall]. Routet über $_GET zwischen
	 * Fall-Eröffnen, Schritt-Formular (neu/bearbeiten) und Timeline.
	 */
	public function render_fall_view( array $attributes = [] ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte anmelden.</p>';
		}

		$case_id = isset( $_GET['mh_case_id'] ) ? (int) $_GET['mh_case_id'] : 0;

		if ( $case_id <= 0 ) {
			return $this->render_open_case_form();
		}

		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_view_case( $case ) ) {
			return '<p>Dieser Fall existiert nicht oder Sie haben keinen Zugriff darauf.</p>';
		}

		if ( isset( $_GET['mh_edit_step'] ) ) {
			$edit_step_no = (int) $_GET['mh_edit_step'];
			$step         = $this->find_step( $case, $edit_step_no );
			$is_admin     = current_user_can( 'manage_options' );

			if ( null === $step || ( 'draft' !== $step['status'] && ! $is_admin ) ) {
				return '<p>Dieser Schritt kann nicht (mehr) bearbeitet werden.</p>';
			}
			return $this->render_step_form( $case, $step['type'], $edit_step_no );
		}

		if ( ! empty( $_GET['mh_new_step'] ) ) {
			$requested_type = sanitize_text_field( $_GET['mh_new_step'] );
			if ( ! in_array( $requested_type, $this->fall_repo->get_available_step_types( $case ), true ) ) {
				return '<p>Dieser Schritt kann für diesen Fall aktuell nicht angelegt werden.</p>';
			}
			return $this->render_step_form( $case, $requested_type );
		}

		return $this->render_fall_timeline( $case );
	}

	/**
	 * Shortcode-Callback [mh_absentismus_liste]. Admin sieht alle Fälle,
	 * alle anderen nur die eigenen (Ersteller = user_id der Fall-Zeile).
	 */
	public function render_fall_liste(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte anmelden.</p>';
		}

		$is_admin      = current_user_can( 'manage_options' );
		$show_archived = $is_admin && isset( $_GET['show_archived'] );

		$filters = [
			'status'   => sanitize_text_field( $_GET['status'] ?? '' ),
			'archived' => $show_archived ? 'all' : 'exclude',
		];
		if ( ! $is_admin ) {
			$filters['user_id'] = get_current_user_id();
		}

		$cases = $this->fall_repo->get_all_cases( $filters );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/absentismus/fall-liste.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Shortcode-Callback für die 8 eigenständigen Einzelformular-Shortcodes
	 * (siehe STANDALONE_SHORTCODES) — der Schritt-Typ ergibt sich aus dem
	 * konkret aufgerufenen Shortcode-Tag ($tag), nicht aus einem Attribut.
	 * Rendert dasselbe step-fields-<type>.php Partial wie der Fall-Workflow,
	 * ohne jede Fall-Bindung.
	 */
	public function render_standalone_step_form( $attributes = [], string $content = '', string $tag = '' ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte anmelden.</p>';
		}

		$step_type = self::STANDALONE_SHORTCODES[ $tag ] ?? '';
		if ( '' === $step_type ) {
			return '<p>Unbekannter Formular-Typ.</p>';
		}

		$state       = $this->get_state( '_standalone' );
		$form_data   = $state['data'] ?? [];
		$form_errors = $state['errors'] ?? [];

		$classes_list = $this->class_repo->get_real_classes();

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/absentismus/standalone-step-form.php';
		return ob_get_clean() ?: '';
	}

	private function render_open_case_form(): string {
		$state       = $this->get_state();
		$form_data   = $state['data'] ?? [];
		$form_errors = $state['errors'] ?? [];

		$classes_list = $this->class_repo->get_real_classes();

		// Zulässige Einstiegs-Typen hängen von is_schulpflichtig ab, das aber erst
		// im selben Formular gesetzt wird — daher beide Varianten berechnen, das
		// Template blendet per JS anhand der Checkbox clientseitig um (serverseitig
		// wird die tatsächliche Wahl in handle_open_case_submission() erneut geprüft).
		$virtual_case_schulpflichtig     = [ 'status' => 'offen', 'form_data' => [ 'steps' => [], 'is_schulpflichtig' => true ] ];
		$virtual_case_nicht_schulpflichtig = [ 'status' => 'offen', 'form_data' => [ 'steps' => [], 'is_schulpflichtig' => false ] ];
		$entry_types_schulpflichtig     = $this->fall_repo->get_available_step_types( $virtual_case_schulpflichtig );
		$entry_types_nicht_schulpflichtig = $this->fall_repo->get_available_step_types( $virtual_case_nicht_schulpflichtig );
		$step_conditions = $this->fall_repo->get_condition_labels();

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/absentismus/fall-open.php';
		return ob_get_clean() ?: '';
	}

	private function render_step_form( array $case, string $step_type, ?int $edit_step_no = null ): string {
		$state       = $this->get_state();
		$form_data   = $state['data'] ?? [];
		$form_errors = $state['errors'] ?? [];

		if ( empty( $form_data ) && null !== $edit_step_no ) {
			$step      = $this->find_step( $case, $edit_step_no );
			$form_data = $step['data'] ?? [];
		}

		$case_id   = (int) $case['id'];
		$case_meta = $case['form_data'];
		$step_no   = $edit_step_no ?? ( count( $case['form_data']['steps'] ?? [] ) + 1 );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/absentismus/step-form.php';
		return ob_get_clean() ?: '';
	}

	private function render_fall_timeline( array $case ): string {
		$case_id       = (int) $case['id'];
		$step_overview = $this->fall_repo->get_step_type_overview( $case );
		$is_admin      = current_user_can( 'manage_options' );
		$is_owner      = (int) $case['user_id'] === get_current_user_id();

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/absentismus/fall-timeline.php';
		return ob_get_clean() ?: '';
	}

	// ------------------------------------------------------------------
	// POST-Handler (admin-post)
	// ------------------------------------------------------------------

	public function handle_open_case_submission(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_absentismus_open_case' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$student_wu_id = (int) ( $_POST['student_wu_id'] ?? 0 );
		if ( $student_wu_id <= 0 ) {
			wp_die( 'Bitte einen Schüler auswählen.' );
		}

		// Es darf pro Schüler immer nur einen offenen Fall geben.
		$existing = $this->fall_repo->find_open_case_by_student( $student_wu_id );
		if ( null !== $existing ) {
			wp_redirect( $this->case_url( (int) $existing['id'] ) );
			exit;
		}

		$is_schulpflichtig = isset( $_POST['is_schulpflichtig'] ) && '1' === $_POST['is_schulpflichtig'];
		$entry_step_type    = sanitize_text_field( $_POST['entry_step_type'] ?? '' );

		// Ein Fall kann laut Prozess über verschiedene Einstiegs-Typen eröffnet werden
		// (nicht mehr zwingend 'gespraech_1') — serverseitig gegen die Regel-Tabelle
		// geprüft, unabhängig davon, was das Formular clientseitig anzeigt.
		$allowed_entry_types = $this->fall_repo->get_available_step_types( [
			'status'    => 'offen',
			'form_data' => [ 'steps' => [], 'is_schulpflichtig' => $is_schulpflichtig ],
		] );
		if ( ! in_array( $entry_step_type, $allowed_entry_types, true ) ) {
			wp_die( 'Ungültiger Einstiegs-Schritt für diesen Fall.' );
		}

		$form     = $this->get_step_form_instance( $entry_step_type );
		$is_valid = $form->validate( $_POST );

		if ( ! $is_valid ) {
			$this->set_state( [ 'data' => $_POST, 'errors' => $form->get_errors() ] );
			wp_redirect( wp_get_referer() );
			exit;
		}

		$student = $this->student_repo->get_student_by_wu_id( $student_wu_id );

		$case_meta = [
			'student_wu_id'     => $student_wu_id,
			'lastname'          => sanitize_text_field( $_POST['lastname'] ?? ( $student['name'] ?? '' ) ),
			'firstname'         => sanitize_text_field( $_POST['firstname'] ?? ( $student['fore_name'] ?? '' ) ),
			'dob'               => sanitize_text_field( $_POST['dob'] ?? ( $student['dob'] ?? '' ) ),
			'class_wu_id'       => (int) ( $_POST['class_wu_id'] ?? 0 ),
			'class_name'        => sanitize_text_field( $_POST['class_name'] ?? '' ),
			'teacher'           => sanitize_text_field( $_POST['teacher'] ?? '' ),
			'is_minor'          => isset( $_POST['is_minor'] ) && '1' === $_POST['is_minor'],
			'is_schulpflichtig' => $is_schulpflichtig,
		];

		$case_id = $this->fall_repo->create_case( $case_meta, $entry_step_type, $form->get_data(), get_current_user_id() );
		if ( 0 === $case_id ) {
			wp_die( 'Fehler beim Anlegen des Falls.' );
		}

		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	public function handle_step_submission(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_absentismus_step_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		$case    = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		$is_admin     = current_user_can( 'manage_options' );
		$edit_step_no = isset( $_POST['edit_step_no'] ) ? (int) $_POST['edit_step_no'] : 0;

		if ( $edit_step_no > 0 ) {
			$step = $this->find_step( $case, $edit_step_no );
			if ( null === $step ) {
				wp_die( 'Schritt nicht gefunden.' );
			}
			if ( 'final' === $step['status'] && ! $is_admin ) {
				wp_die( 'Dieser Schritt ist bereits festgeschrieben und kann nur noch von einem Admin geändert werden.' );
			}
			$step_type = $step['type'];
		} else {
			// Ziel-Typ kommt aus dem POST-Feld, wird aber zwingend gegen die aktuell
			// zulässigen Typen geprüft (Schutz gegen manipulierte Requests — der Nutzer
			// kann keinen Schritt einschleusen, dessen Vorbedingungen nicht erfüllt sind).
			$step_type = sanitize_text_field( $_POST['step_type'] ?? '' );
			if ( ! in_array( $step_type, $this->fall_repo->get_available_step_types( $case ), true ) ) {
				wp_die( 'Dieser Schritt kann für diesen Fall aktuell nicht angelegt werden.' );
			}
		}

		$form     = $this->get_step_form_instance( $step_type );
		$is_valid = $form->validate( $_POST );

		if ( ! $is_valid ) {
			$this->set_state( [ 'data' => $_POST, 'errors' => $form->get_errors() ] );
			wp_redirect( wp_get_referer() );
			exit;
		}

		if ( $edit_step_no > 0 ) {
			$is_admin
				? $this->fall_repo->update_step_admin( $case_id, $edit_step_no, $form->get_data() )
				: $this->fall_repo->update_step( $case_id, $edit_step_no, $form->get_data() );
		} else {
			$this->fall_repo->append_step( $case_id, $step_type, $form->get_data(), get_current_user_id() );
		}

		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	public function handle_finalize_step(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		$step_no = (int) ( $_GET['step_no'] ?? 0 );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_finalize_' . $case_id . '_' . $step_no ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		// Kein Auto-Close (mehr) — Schließen ist immer eine bewusste, manuelle
		// Aktion durch Fall-Ersteller oder Admin (siehe handle_close_case()).
		$this->fall_repo->finalize_step( $case_id, $step_no, get_current_user_id() );

		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	/**
	 * Schließen/Wiedereröffnen ist für Fall-Ersteller UND Admin zugänglich (anders
	 * als Archivieren, das Admin-only bleibt) — der Klassenlehrer entscheidet selbst,
	 * wann ein Fall fachlich abgeschlossen ist (z. B. nach einer Entlassung).
	 */
	public function handle_close_case(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_close_' . $case_id ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}
		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}
		$this->fall_repo->close_case( $case_id, get_current_user_id(), 'manual' );
		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	public function handle_reopen_case(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_reopen_' . $case_id ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}
		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}
		$this->fall_repo->reopen_case( $case_id, get_current_user_id() );
		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	/**
	 * Fügt eine Aktennotiz hinzu. Siehe Absentismus_Fall_Repository::add_note().
	 */
	public function handle_add_note(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_absentismus_add_note' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		$case    = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		$text = sanitize_textarea_field( $_POST['note_text'] ?? '' );
		if ( '' !== $text ) {
			$this->fall_repo->add_note( $case_id, $text, get_current_user_id() );
		}

		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	public function handle_delete_note(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$case_id    = (int) ( $_GET['case_id'] ?? 0 );
		$note_index = (int) ( $_GET['note_index'] ?? -1 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_delete_note_' . $case_id . '_' . $note_index ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		$this->fall_repo->delete_note( $case_id, $note_index );
		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	/**
	 * Ersetzt die komplette Kontaktliste des Falls (Eltern/Betreuer/Betrieb/Schüler).
	 */
	public function handle_update_contacts(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_absentismus_update_contacts' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case_id = (int) ( $_POST['case_id'] ?? 0 );
		$case    = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_edit_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		$names  = $_POST['contact_name'] ?? [];
		$roles  = $_POST['contact_role'] ?? [];
		$phones = $_POST['contact_phone'] ?? [];
		$emails = $_POST['contact_email'] ?? [];
		$notes  = $_POST['contact_note'] ?? [];

		$contacts = [];
		foreach ( $names as $i => $raw_name ) {
			$name = sanitize_text_field( $raw_name );
			if ( '' === $name ) {
				continue; // Zeile ohne Namen wird verworfen
			}
			$contacts[] = [
				'role'  => sanitize_text_field( $roles[ $i ] ?? '' ),
				'name'  => $name,
				'phone' => sanitize_text_field( $phones[ $i ] ?? '' ),
				'email' => sanitize_email( $emails[ $i ] ?? '' ),
				'note'  => sanitize_text_field( $notes[ $i ] ?? '' ),
			];
		}

		$this->fall_repo->update_contacts( $case_id, $contacts );
		wp_redirect( $this->case_url( $case_id ) );
		exit;
	}

	/**
	 * Archivieren ist bewusst kein Löschen: der Fall bleibt vollständig erhalten,
	 * verschwindet nur aus der Standard-Übersicht (siehe Absentismus_Fall_Repository).
	 */
	public function handle_archive_case(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_archive_' . $case_id ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}
		$this->fall_repo->archive_case( $case_id, get_current_user_id() );
		wp_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	public function handle_unarchive_case(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_unarchive_' . $case_id ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}
		$this->fall_repo->unarchive_case( $case_id );
		wp_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	public function handle_bulk_archive(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bulk-absentismus-faelle' );

		$ids = array_map( 'intval', $_POST['bulk_ids'] ?? [] );
		if ( ! empty( $ids ) ) {
			$this->fall_repo->archive_multiple( $ids, get_current_user_id() );
		}
		wp_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	public function handle_download_step_pdf(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}
		$case_id = (int) ( $_GET['case_id'] ?? 0 );
		$step_no = (int) ( $_GET['step_no'] ?? 0 );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_absentismus_pdf_' . $case_id . '_' . $step_no ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$case = $this->fall_repo->get_by_id( $case_id );
		if ( null === $case || ! $this->can_view_case( $case ) ) {
			wp_die( 'Kein Zugriff auf diesen Fall.' );
		}

		$step = $this->find_step( $case, $step_no );
		if ( null === $step ) {
			wp_die( 'Schritt nicht gefunden.' );
		}

		$case_meta = $case['form_data'];
		$data       = array_merge( $case_meta, $step['data'] );

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/pdf-absentismus-' . $step['type'] . '.php';
		$html = ob_get_clean() . '</body></html>';

		$filename = sprintf(
			'%s_Fall%d_Schritt%d_%s_%s',
			date( 'y-m-d' ),
			$case_id,
			$step_no,
			sanitize_file_name( $step['type'] ),
			sanitize_file_name( $case_meta['lastname'] ?? '' )
		);
		$this->pdf_generator->generate_and_stream( $case_id * 100 + $step_no, $html, $filename );
		exit;
	}

	/**
	 * Verarbeitet die Einsendung eines der 8 eigenständigen Einzelformulare
	 * (siehe STANDALONE_SHORTCODES). Nutzt exakt dasselbe Model wie der
	 * Fall-Workflow (get_step_form_instance()) und exakt dasselbe PDF-Template
	 * (pdf-absentismus-<type>.php) — keine zweite Formular-/PDF-Definition.
	 * Wird bewusst NICHT in einem Fall gespeichert (wie service_leave_v1 bei
	 * Form_Controller — reine PDF-Ausgabe ohne Persistierung).
	 */
	public function handle_standalone_step_submission(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Bitte anmelden.' );
		}

		$step_type = sanitize_text_field( $_POST['step_type'] ?? '' );
		if ( ! in_array( $step_type, self::STANDALONE_SHORTCODES, true ) ) {
			wp_die( 'Unbekannter Formular-Typ.' );
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_absentismus_standalone_submit_' . $step_type ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$form     = $this->get_step_form_instance( $step_type );
		$is_valid = $form->validate( $_POST );

		if ( ! $is_valid ) {
			$this->set_state( [ 'data' => $_POST, 'errors' => $form->get_errors() ], '_standalone' );
			wp_redirect( wp_get_referer() );
			exit;
		}

		// Dieselben Stammdaten-Feldnamen wie im Fall-Workflow (case_meta) — die
		// PDF-Templates (pdf-header.php/pdf-stammdaten-box.php) erwarten genau diese.
		$student_data = [
			'lastname'          => sanitize_text_field( $_POST['lastname'] ?? '' ),
			'firstname'         => sanitize_text_field( $_POST['firstname'] ?? '' ),
			'dob'               => sanitize_text_field( $_POST['dob'] ?? '' ),
			'class_name'        => sanitize_text_field( $_POST['class_name'] ?? '' ),
			'teacher'           => sanitize_text_field( $_POST['teacher'] ?? '' ),
			'is_minor'          => isset( $_POST['is_minor'] ) && '1' === $_POST['is_minor'],
			'is_schulpflichtig' => isset( $_POST['is_schulpflichtig'] ) && '1' === $_POST['is_schulpflichtig'],
		];

		$data    = array_merge( $student_data, $form->get_data() );
		$case_id = 0; // kein Fall — pdf-header.php lässt die "Fall #"-Referenz dann weg

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/pdf-absentismus-' . $step_type . '.php';
		$html = ob_get_clean() . '</body></html>';

		$entry_id = (int) date( 'His' );
		$filename = sprintf(
			'%s_%s_%s',
			date( 'y-m-d' ),
			sanitize_file_name( $step_type ),
			sanitize_file_name( $student_data['lastname'] )
		);
		$this->pdf_generator->generate_and_stream( $entry_id, $html, $filename );
		exit;
	}

	// ------------------------------------------------------------------
	// Helfer
	// ------------------------------------------------------------------

	private function can_view_case( array $case ): bool {
		return current_user_can( 'manage_options' ) || (int) $case['user_id'] === get_current_user_id();
	}

	private function can_edit_case( array $case ): bool {
		return $this->can_view_case( $case );
	}

	private function find_step( ?array $case, int $step_no ): ?array {
		if ( null === $case ) {
			return null;
		}
		foreach ( $case['form_data']['steps'] ?? [] as $step ) {
			if ( (int) $step['step_no'] === $step_no ) {
				return $step;
			}
		}
		return null;
	}

	private function get_base_url(): string {
		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_absentismus_fall'] ?? 0 );
		if ( $page_id > 0 ) {
			return get_permalink( $page_id ) ?: '';
		}
		return '';
	}

	private function case_url( int $case_id ): string {
		$base = $this->get_base_url() ?: ( wp_get_referer() ?: home_url( '/' ) );
		return remove_query_arg( [ 'mh_edit_step', 'mh_new_step' ], add_query_arg( 'mh_case_id', $case_id, $base ) );
	}

	/**
	 * @param string $key_suffix Trennt den State verschiedener Formular-Flows
	 *                           (Fall-Schritt vs. eigenständiges Einzelformular),
	 *                           damit sie sich bei parallelem Gebrauch nicht überschreiben.
	 */
	private function set_state( array $state, string $key_suffix = '' ): void {
		set_transient( 'mh_fw_absentismus_state_' . get_current_user_id() . $key_suffix, $state, 60 );
	}

	private function get_state( string $key_suffix = '' ): array {
		$key   = 'mh_fw_absentismus_state_' . get_current_user_id() . $key_suffix;
		$state = get_transient( $key );
		if ( false === $state ) {
			return [];
		}
		delete_transient( $key );
		return $state;
	}
}
