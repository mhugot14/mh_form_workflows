<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
use Mh\FormWorkflows\Repository\Subject_Repository;
use Mh\FormWorkflows\Repository\Track_Subject_Repository;
use Mh\FormWorkflows\Repository\Student_Course_Repository;
use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Repository\Diagnostics_Repository;
use Mh\FormWorkflows\Service\Config_Check;
use Mh\FormWorkflows\Service\Noten_Feature;
use Mh\FormWorkflows\Service\Mail_Service;
use Mh\FormWorkflows\Service\Reminder_Service;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Service\School_Date_Calculator;
use Mh\FormWorkflows\Model\Form\Form_Interface;
use Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form;
use Mh\FormWorkflows\Model\Form\Service_Leave_Form;

class Form_Controller {

	/**
	 * Konstruktor mit allen Abhängigkeiten (muss zur Bootstrap passen!)
	 */
	public function __construct(
		private Submission_Repository $repository,
		private Class_Repository $class_repo,
		private Teacher_Repository $teacher_repo,
		private Student_Repository $student_repo,
                private Subject_Repository $subject_repo,
		private Track_Subject_Repository $track_subject_repo,
		private Student_Course_Repository $student_course_repo,
		private Noten_Fall_Repository $noten_repo,
		private Teacher_Account_Repository $account_repo,
		private Mail_Service $mail,
		private Reminder_Service $reminder,
		private Pdf_Generator $pdf_generator
	) {}

	/**
	 * AJAX-Endpunkt: Liefert die Fächer-Vorbelegung für einen Schüler.
	 *
	 * Vereinigt zwei Schild-Quellen, die ab Schuljahresbeginn gepflegt sind:
	 * - die Stundentafel des Bildungsgangs (Regelfächer, für alle Schüler gleich)
	 * - die klassenübergreifenden Kursbelegungen des Schülers (nur dort steht eine Lehrkraft)
	 *
	 * Bewusst NICHT genutzt wird die schülerbezogene Fachbelegung aus Schild: die entsteht
	 * erst kurz vor der Notensammlung und ist bei einer Abmeldung meist noch leer.
	 */
	public function ajax_get_subject_rows(): void {
		if ( ! check_ajax_referer( 'mh_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Sicherheits-Check fehlgeschlagen.' );
		}

		$track_key = isset( $_POST['track_key'] ) ? sanitize_text_field( wp_unslash( $_POST['track_key'] ) ) : '';
		$schild_id = isset( $_POST['schild_id'] ) ? sanitize_text_field( wp_unslash( $_POST['schild_id'] ) ) : '';

		$rows = [];
		$seen = [];

		// Kurse stehen in der Vorbelegung VOR den Regelfächern, und sie ersetzen das Fach,
		// zu dem sie gehören.
		//
		// Das Trägerfach aus Schild ("KURS1_11u12", "Reli/PRPH") ist ein echtes Fach der
		// Stundentafel, kein Sammelbegriff: es steht dort als Platzhalter, auf den die
		// konkreten Kurse gebucht werden. "Reli/PRPH" meint den Platz für Religion oder
		// Praktische Philosophie, "KURS1_11u12" ein Fach, das über beide Jahrgangsstufen
		// läuft, "KURS1_11" nur die Unterstufe. Steht für ein solches Fach ein Kurs, gehört
		// die Kurszeile ins Protokoll und nicht der Platzhalter — sie nennt den tatsächlichen
		// Kurs und bringt die Kurslehrkraft mit.
		$course_rows   = [];
		$track_rows    = [];
		$covered_by_course = [];

		// 1. Regelfächer aus der Stundentafel des Bildungsgangs.
		$track_subjects = $this->track_subject_repo->get_subjects_for_track( $track_key );
		$track_options  = [];
		$track_labels   = [];

		foreach ( $track_subjects as $s ) {
			$short   = (string) $s['short_name'];
			$display = (string) ( $s['display_name'] ?? '' );
			$label   = '' !== $display ? $short . ' - ' . $display : $short;

			$track_options[] = [ 'value' => $short, 'label' => $label ];

			if ( isset( $track_labels[ $short ] ) ) {
				continue;
			}
			$track_labels[ $short ] = $label;
		}

		// 2. Klassenübergreifende Kurse. Angezeigt wird die Kursbezeichnung, weil das
		//    Trägerfach im Protokoll nur den Platzhalter nennen würde ("Reli/PRPH") statt
		//    des belegten Kurses. Im Dropdown bekommen die Kurse eine eigene Gruppe; eine
		//    Einrückung unter das Fach entfällt, weil das Fach als eigene Zeile gerade
		//    wegfällt, sobald ein Kurs dafür da ist.
		$course_options = [];
		foreach ( $this->student_course_repo->get_courses_for_student( $schild_id ) as $c ) {
			$course_name = (string) $c['course_name'];
			if ( '' === $course_name || isset( $seen[ $course_name ] ) ) {
				continue;
			}
			$seen[ $course_name ] = true;

			$teacher = (string) ( $c['teacher_short'] ?? '' );
			$label   = '' !== $teacher ? $course_name . ' (' . $teacher . ')' : $course_name;

			// Das Fach, auf das dieser Kurs gebucht ist, entfällt als eigene Zeile.
			$traegerfach = trim( (string) ( $c['subject_short'] ?? '' ) );
			if ( '' !== $traegerfach ) {
				$covered_by_course[ $traegerfach ] = true;
			}

			// Das Dropdown führt die Kurse eigenständig: Wer eine vorbelegte Kurszeile
			// löscht oder umstellt, konnte den Kurs bisher nicht wieder auswählen.
			$course_options[] = [
				'value'   => $course_name,
				'label'   => $label,
				'teacher' => $teacher,
			];

			$course_rows[] = [
				'value'     => $course_name,
				'label'     => $course_name,
				'teacher'   => $teacher,
				'is_course' => true,
			];
		}

		// Regelfächer hinter den Kursen einreihen - ausser denen, für die bereits ein Kurs
		// vorliegt. Die Kurszeile nennt den tatsächlich belegten Kurs und bringt die
		// Kurslehrkraft mit; der Platzhalter daneben wäre eine Dublette ohne Mehrwert.
		foreach ( $track_labels as $short => $label ) {
			if ( isset( $seen[ $short ] ) || isset( $covered_by_course[ $short ] ) ) {
				continue;
			}
			$seen[ $short ] = true;

			$track_rows[] = [
				'value'     => (string) $short,
				'label'     => $label,
				'teacher'   => '',
				'is_course' => false,
			];
		}

		$rows = array_merge( $course_rows, $track_rows );

		// Der Rest der Schild-Fächerliste bleibt als Notausgang erreichbar (Fachwechsler,
		// Wiederholer), steht im Dropdown aber unterhalb der Bildungsgang-Fächer.
		$other_options = [];
		foreach ( $this->subject_repo->get_all_subjects() as $sub ) {
			$short = (string) $sub['short_name'];
			if ( isset( $seen[ $short ] ) ) {
				continue;
			}
			$other_options[] = [
				'value' => $short,
				'label' => $short . ' - ' . $sub['display_name'],
			];
		}

		wp_send_json_success( [
			'rows'           => $rows,
			'course_options' => $course_options,
			'track_options'  => $track_options,
			'other_options'  => $other_options,
		] );
	}
	
	/**
	 * AJAX-Endpunkt: Holt Schüler einer Klasse
	 */
	public function ajax_get_students(): void {
		// 1. Sicherheit: Nonce prüfen
		if ( ! check_ajax_referer( 'mh_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Sicherheits-Check fehlgeschlagen.' );
		}

		// 2. Daten holen
		$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

		if ( $class_id <= 0 ) {
			wp_send_json_error( 'Ungültige Klassen-ID.' );
		}

		// 3. Repository abfragen
		$students = $this->student_repo->get_students_by_class( $class_id );

		if ( ! empty( $students ) ) {
			wp_send_json_success( $students );
		} else {
			wp_send_json_error( 'Keine Schüler für diese Klasse gefunden.' );
		}
	}

	/**
	 * Factory: Erzeugt das passende Model
	 */
	private function get_form_instance( string $type ): Form_Interface {
		return match( $type ) {
			'service_leave_v1'     => new Service_Leave_Form(),
			'abmeldung_student_v1' => new Abmeldung_Student_Form(),
			default                => new Abmeldung_Student_Form(),
		};
	}

	/**
	 * Holt die URL für einen Formular-Typ aus den Admin-Einstellungen.
	 */
	private function get_url_for_form_type(string $type): string {
		$options = get_option( 'mh_fw_settings', [] );
		$key = 'page_id_' . $type;
		$page_id = (int)($options[$key] ?? 0);

		if ($page_id > 0) {
			return get_permalink($page_id) ?: '';
		}
		return ''; 
	}

	/**
	 * Rendert das Formular (Frontend)
	 */
	public function render_form( array $attributes = [] ): string {
		$form_type = $attributes['type'] ?? 'abmeldung_student_v1'; 

		// State laden (Fehler/Inputs nach Reload)
		$transient_key = 'mh_fw_state_' . get_current_user_id();
		$state = get_transient( $transient_key );
		
		$form_data   = [];
		$form_errors = [];
		$is_success  = false;

		if ( false !== $state ) {
			$form_data   = $state['data'] ?? [];
			$form_errors = $state['errors'] ?? [];
			$is_success  = $state['success'] ?? false;
			if(isset($form_data['form_type']) && $form_data['form_type'] !== $form_type) {
				$form_data = []; $form_errors = []; $is_success = false;
			} else {
				delete_transient( $transient_key );
			}
		} else if ( isset($_GET['mh_edit_id']) ) {
			$edit_id = (int)$_GET['mh_edit_id'];
			$entry = $this->repository->get_by_id($edit_id);
			if ($entry && (int)$entry['user_id'] === get_current_user_id()) {
				$form_data = $entry['form_data'];
				$form_data['id'] = $entry['id'];
				$form_data['is_reloaded'] = true; 
			}
		}

		// Steuert, ob das Formular die Option "automatisch einsammeln" überhaupt anbietet.
		$noten_enabled = Noten_Feature::is_enabled();

		// Stammdaten für Dropdowns laden
		$classes_list = $this->class_repo->get_real_classes();
		$teachers_list = $this->teacher_repo->get_all_teachers();
                $subjects_list = $this->subject_repo->get_all_subjects();

		ob_start();
		if ( 'service_leave_v1' === $form_type ) {
			include MH_FW_PLUGIN_DIR . 'templates/form-service-leave.php';
		} else {
			include MH_FW_PLUGIN_DIR . 'templates/form-abmeldung.php';
		}
		return ob_get_clean() ?: '';
	}

	/**
	 * Dashboard für User
	 */
	public function render_dashboard(): string {
		if ( ! is_user_logged_in() ) return '<p>Bitte anmelden.</p>';

		$user_id = get_current_user_id();
		// Nur echte Anträge: Absentismus- und Noten-Fälle liegen in derselben Tabelle,
		// wurden hier aber als "Abmeldung" beschriftet und mit einem PDF-Link versehen,
		// der für sie nicht funktioniert.
		$submissions = $this->repository->get_submissions_by_user(
			$user_id,
			Submission_Repository::ANTRAG_FORM_TYPES
		);
		
		$urls = [
			'service_leave_v1'     => $this->get_url_for_form_type('service_leave_v1'),
			'abmeldung_student_v1' => $this->get_url_for_form_type('abmeldung_student_v1'),
		];

		$grouped = [];
		foreach ( $submissions as $sub ) {
			$ts = strtotime( $sub['created_at'] );
			$year = (int)date( 'Y', $ts );
			$month = (int)date( 'n', $ts );
			$school_year = ($month < 8) ? ($year - 1) . '/' . substr((string)$year, -2) : $year . '/' . substr((string)($year + 1), -2);
			$sub['data'] = is_string($sub['form_data']) ? json_decode($sub['form_data'], true) : $sub['form_data'];
			$grouped[ $school_year ][] = $sub;
		}

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/dashboard-user.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Dashboard für Admin
	 */
	public function render_admin_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$filters = [
			'start_date' => sanitize_text_field( $_GET['start_date'] ?? '' ),
			'end_date'   => sanitize_text_field( $_GET['end_date'] ?? '' ),
			'user_id'    => sanitize_text_field( $_GET['user_id'] ?? '' ),
			'form_type'  => sanitize_text_field( $_GET['form_type'] ?? '' ),
		];

		$submissions = $this->repository->get_filtered_submissions( $filters );
		$submitters  = $this->repository->get_distinct_submitters();
		
		foreach ( $submissions as &$sub ) {
			$sub['data'] = is_string($sub['form_data']) ? json_decode($sub['form_data'], true) : $sub['form_data'];
		}

		include MH_FW_PLUGIN_DIR . 'templates/dashboard-admin.php';
	}

	/**
	 * Prüft, ob eine digitale Noteneinsammlung überhaupt starten kann.
	 *
	 * Jede belegte Fächerzeile braucht eine Lehrkraft, und zu jedem Kürzel muss sich
	 * eine Zustelladresse auflösen lassen. Fehlt eines von beidem, gibt es niemanden
	 * zu benachrichtigen — der Prozess bliebe unbemerkt liegen.
	 *
	 * @return array<string,string> Fehler für die Rückgabe ins Formular.
	 */
	private function check_collect_preconditions( array $valid_data, array &$bad_teachers = [] ): array {
		// Abgeschaltet heisst: keine NEUEN Einsammlungen. Laufende Fälle bleiben
		// unberührt, die laufen über den Noten_Controller weiter.
		if ( ! Noten_Feature::is_enabled() ) {
			return [ 'collect_disabled' => 'Die digitale Noteneinsammlung ist derzeit abgeschaltet. Bitte die Noten im Formular eintragen oder auf Papier einsammeln.' ];
		}

		// Liegt ein bestehendes Konferenzprotokoll bei, stehen die Noten bereits darin.
		// Ein Umlauf würde Lehrkräfte um etwas bitten, das längst entschieden ist.
		if ( 'existing' === ( $valid_data['protocol_mode'] ?? '' ) ) {
			return [ 'protocol_mode' => 'Die digitale Noteneinsammlung ist nicht möglich, wenn ein bestehendes Zeugniskonferenzprotokoll beigefügt wird — die Noten ergeben sich aus diesem Protokoll.' ];
		}

		// Ohne Zeugnis keine Zeugniskonferenz - es gibt keine Noten, die man einsammeln könnte.
		if ( 'none' === ( $valid_data['certificate'] ?? '' ) ) {
			return [ 'certificate' => 'Die digitale Noteneinsammlung ist nicht möglich, wenn kein Zeugnis erteilt wird.' ];
		}

		$subjects = $valid_data['subjects'] ?? [];

		if ( empty( $subjects ) ) {
			return [ 'subjects' => 'Für die Noteneinsammlung muss mindestens ein Fach eingetragen sein.' ];
		}

		$missing_teacher = [];
		$missing_address = [];
		$collect_count   = 0;

		// Geprüft wird nur, was auch angefragt werden soll. Fächer, deren Note die
		// Klassenleitung selbst einträgt, brauchen keine erreichbare Lehrkraft - sonst
		// müsste sie Adressen pflegen für Mails, die nie rausgehen.
		foreach ( $subjects as $s ) {
			$name    = trim( (string) ( $s['name'] ?? '' ) );
			$kuerzel = trim( (string) ( $s['teacher'] ?? '' ) );
			if ( '' === $name || '1' !== ( $s['collect'] ?? '0' ) ) {
				continue;
			}
			$collect_count++;
			if ( '' === $kuerzel ) {
				$missing_teacher[] = $name;
				continue;
			}
			if ( null === $this->account_repo->resolve_recipient( $kuerzel ) ) {
				$missing_address[] = $name . ' (' . $kuerzel . ')';
				// Für die Inline-Prüfung: die betroffenen Lehrkraft-Felder rot markieren.
				$bad_teachers[]    = $kuerzel;
			}
		}

		$errors = [];
		if ( 0 === $collect_count ) {
			return [ 'subjects' => 'Es ist kein Fach zum Anfragen markiert. Setze die gewünschten Fächer in der Notenspalte auf „✉ per Mail anfragen“ — oder erstelle das PDF mit den selbst eingetragenen Noten.' ];
		}
		if ( ! empty( $missing_teacher ) ) {
			$errors['subjects_teacher'] = 'Für die Noteneinsammlung fehlt die Lehrkraft in folgenden Fächern: '
				. implode( ', ', $missing_teacher ) . '.';
		}
		if ( ! empty( $missing_address ) ) {
			$errors['subjects_address'] = 'Für folgende Lehrkräfte ist keine E-Mail-Adresse hinterlegt: '
				. implode( ', ', $missing_address ) . '. Bitte im WebUntis Analyser unter "Lehrer-Zuordnung" ergänzen.';
		}

		return $errors;
	}

	/**
	 * Legt den Fall an, verschickt die Einladungen und leitet auf die Fall-Ansicht.
	 */
	private function start_grade_collection( int $submission_id, array $valid_data, int $user_id ): void {
		// Doppelstart verhindern (z. B. durch erneutes Absenden desselben Formulars).
		$existing = $this->noten_repo->find_open_case_by_submission( $submission_id );
		if ( null !== $existing ) {
			wp_redirect( $this->reminder->case_link( (int) $existing['id'] ) );
			exit;
		}

		// Alle Fächer wandern in den Fall, nicht nur die angefragten: beim Abschluss
		// ersetzt write_back_to_submission() die subjects der Einsendung vollständig
		// durch die Positionen des Falls. Fehlten die selbst eingetragenen Noten hier,
		// wären sie hinterher weg.
		$items = [];
		foreach ( $valid_data['subjects'] ?? [] as $s ) {
			$name = trim( (string) ( $s['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}

			$kuerzel    = trim( (string) ( $s['teacher'] ?? '' ) );
			$is_collect = ( '1' === ( $s['collect'] ?? '0' ) );

			$recipient = null;
			if ( $is_collect ) {
				$recipient = $this->account_repo->resolve_recipient( $kuerzel );
				if ( null === $recipient ) {
					continue; // von check_collect_preconditions() bereits ausgeschlossen
				}
			}

			$items[] = [
				'subject'           => $name,
				'teacher_kuerzel'   => $kuerzel,
				'recipient_user_id' => $recipient['user_id'] ?? 0,
				'recipient_email'   => $recipient['email'] ?? '',
				'is_fallback'       => $recipient['is_fallback'] ?? false,
				'collect'           => $is_collect,
				'grade'             => (string) ( $s['grade'] ?? '' ),
				'webuntis'          => (string) ( $s['webuntis'] ?? '0' ),
				'completed'         => (string) ( $s['completed'] ?? '0' ),
			];
		}

		$case_id = $this->noten_repo->create_case( [
			'submission_id' => $submission_id,
			'student_wu_id' => (int) ( $valid_data['student_wu_id'] ?? 0 ),
			'lastname'      => (string) ( $valid_data['lastname'] ?? '' ),
			'firstname'     => (string) ( $valid_data['firstname'] ?? '' ),
			'class_wu_id'   => (int) ( $valid_data['class_wu_id'] ?? 0 ),
			'class_name'    => (string) ( $valid_data['class_name'] ?? '' ),
			'owner_user_id' => $user_id,
		], $items, $user_id );

		if ( 0 === $case_id ) {
			wp_die( 'Die Noteneinsammlung konnte nicht angelegt werden (DB-Fehler).' );
		}

		// Eingeladen wird nur, wo auch wirklich eine Note fehlt.
		$case = $this->noten_repo->get_by_id( $case_id );
		foreach ( $case['form_data']['items'] as $item ) {
			if ( '1' !== ( $item['collect'] ?? '0' ) ) {
				continue;
			}
			$idx  = (int) $item['idx'];
			$sent = $this->mail->send_invitation(
				(string) $item['recipient_email'],
				$this->account_repo->get_display_name( (string) $item['teacher_kuerzel'] ),
				$case,
				$item,
				$this->reminder->entry_link( $case_id, $idx )
			);
			// Nur bei Erfolg als benachrichtigt markieren. Ein Fehlschlag wird am Fach
			// vermerkt; der Erinnerungs-Cron versucht die Einladung dann erneut.
			if ( $sent ) {
				$this->noten_repo->mark_notified( $case_id, $idx, false );
			} else {
				$this->noten_repo->mark_mail_failed( $case_id, $idx, $this->mail->get_last_error() );
			}
		}

		wp_redirect( $this->reminder->case_link( $case_id ) );
		exit;
	}

	/**
	 * Gemeinsame Prüfung für den echten Submit und die Inline-Prüfung per AJAX.
	 *
	 * Beide Wege MÜSSEN dieselben Regeln anwenden - sonst meldet das Formular "alles in
	 * Ordnung" und der Submit scheitert trotzdem. Deshalb liegt die komplette Prüfkette
	 * (Model-Validierung, Datumskorrektur, Vorbedingungen der Noteneinsammlung) hier.
	 *
	 * @return array{form: Form_Interface, is_valid: bool, valid_data: array, errors: array<string,string>, mode: string, bad_teachers: string[]}
	 */
	private function evaluate_submission( array $post, string $mode ): array {
		$form = $this->get_form_instance( sanitize_text_field( $post['form_type'] ?? '' ) );

		$is_valid   = $form->validate( $post );
		$valid_data = $form->get_data();
		$errors     = $form->get_errors();

		// Ist das Verfahren abgeschaltet, darf keine Zeile als "einzusammeln" gespeichert
		// werden. Sonst stünde die Markierung später im Formular, ohne dass je jemand
		// gefragt würde. Das Model kennt die Einstellung nicht — diese Entscheidung
		// gehört in den Controller.
		if ( ! Noten_Feature::is_enabled() && ! empty( $valid_data['subjects'] ) ) {
			foreach ( $valid_data['subjects'] as &$mh_subject ) {
				$mh_subject['collect'] = '0';
			}
			unset( $mh_subject );
		}

		if ( 'pdf' === $mode && ! empty( $valid_data['prot_was_corrected'] ) ) {
			$mode = 'check';
			$errors['date_autocorrect'] = 'Achtung: Datum korrigiert (WE/Ferien). Bitte prüfen.';
			$is_valid = false;
		}

		// Start der digitalen Noteneinsammlung: Ohne zugeordnete Lehrkraft mit
		// erreichbarer Adresse gäbe es keinen Empfänger — dann lieber gar nicht
		// starten, statt einen Prozess anzulegen, der still auf niemanden wartet.
		// Geprüft wird auch bei sonstigen Fehlern, damit alle Mängel auf einmal
		// angezeigt werden statt nacheinander.
		$bad_teachers = [];
		if ( 'collect' === $mode ) {
			$collect_errors = $this->check_collect_preconditions( $valid_data, $bad_teachers );
			if ( ! empty( $collect_errors ) ) {
				$errors   = array_merge( $errors, $collect_errors );
				$mode     = 'check';
				$is_valid = false;
			}
		}

		return [
			'form'         => $form,
			'is_valid'     => $is_valid,
			'valid_data'   => $valid_data,
			'errors'       => $errors,
			'mode'         => $mode,
			'bad_teachers' => array_values( array_unique( $bad_teachers ) ),
		];
	}

	/**
	 * AJAX-Endpunkt: Prüft das Formular, ohne etwas zu speichern oder zu erzeugen.
	 *
	 * Das Formular ruft das vor jedem Absenden auf und zeigt die Fehler direkt an den
	 * Feldern an. Erst wenn hier nichts mehr bemängelt wird, schickt es wirklich ab -
	 * so öffnet sich kein PDF-Fenster, das nur eine Fehlerliste enthält.
	 */
	public function ajax_validate_form(): void {
		if ( ! check_ajax_referer( 'mh_form_submit', '_wpnonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Die Sitzung ist abgelaufen. Bitte die Seite neu laden (Eingaben vorher sichern).' ] );
		}

		$mode = sanitize_key( $_POST['submit_mode'] ?? 'check' );
		if ( ! in_array( $mode, [ 'check', 'pdf', 'collect' ], true ) ) {
			$mode = 'check';
		}

		$result     = $this->evaluate_submission( $_POST, $mode );
		$valid_data = $result['valid_data'];
		$errors     = $result['errors'];

		// Die Datumskorrektur ist kein Eingabefehler im eigentlichen Sinn: das Formular
		// übernimmt das korrigierte Datum sofort, und der nächste Klick geht durch.
		$corrected_date = ! empty( $valid_data['prot_was_corrected'] ) ? (string) ( $valid_data['prot_date'] ?? '' ) : '';
		if ( isset( $errors['date_autocorrect'] ) ) {
			$errors['date_autocorrect'] = 'Das Abmeldedatum fällt nicht auf einen Schultag (Wochenende/Ferien).'
				. ( '' !== $corrected_date ? ' Konferenz- und Zeugnisdatum wurden deshalb auf den ' . date_i18n( 'd.m.Y', strtotime( $corrected_date ) ) . ' gelegt.' : '' )
				. ' Bitte prüfen und dann erneut auf „Prüfen & PDF erstellen“ klicken.';
		}

		wp_send_json_success( [
			'valid'          => $result['is_valid'],
			'errors'         => $errors,
			'corrected_date' => $corrected_date,
			'bad_teachers'   => $result['bad_teachers'],
		] );
	}

	/**
	 * AJAX-Endpunkt: Konferenz- und Zeugnisdatum zum eingegebenen Abmeldedatum.
	 *
	 * Beide müssen auf einen Schultag fallen. Das Formular setzt sie schon bei der
	 * Eingabe und erklärt eine Verschiebung, statt erst beim Absenden zu meckern.
	 * Dieselbe Rechnung wendet das Model beim Absenden an.
	 */
	public function ajax_school_day(): void {
		if ( ! check_ajax_referer( 'mh_form_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Sicherheits-Check fehlgeschlagen.' );
		}

		$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( 'Ungültiges Datum.' );
		}

		$calc      = new School_Date_Calculator();
		$corrected = $calc->get_previous_school_day( $date );
		$changed   = $corrected !== $date;

		$explanation = '';
		if ( $changed ) {
			$explanation = sprintf(
				'Der %s ist %s und damit kein Schultag. Konferenz- und Zeugnisdatum wurden deshalb auf den letzten Schultag davor gelegt: %s.',
				date_i18n( 'd.m.Y', strtotime( $date ) ),
				$calc->get_non_school_reason_ymd( $date ),
				date_i18n( 'l, d.m.Y', strtotime( $corrected ) )
			);
		}

		wp_send_json_success( [
			'date'        => $corrected,
			'changed'     => $changed,
			'explanation' => $explanation,
		] );
	}

	/**
	 * POST-Verarbeitung
	 */
	public function handle_submission(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mh_form_submit' ) ) {
			wp_die( 'Sicherheitsprüfung fehlgeschlagen.' );
		}

		$form_type_slug = sanitize_text_field( $_POST['form_type'] ?? '' );

		$result     = $this->evaluate_submission( $_POST, (string) ( $_POST['submit_mode'] ?? 'check' ) );
		$form       = $result['form'];
		$mode       = $result['mode'];
		$is_valid   = $result['is_valid'];
		$raw_data   = $_POST;
		$valid_data = $result['valid_data'];
		$errors     = $result['errors'];

		// Das Abmeldeformular erzeugt das PDF in einem eigenen Fenster. Scheitert dort die
		// Prüfung, wäre ein zweites Formular im neuen Fenster nur verwirrend - stattdessen
		// die Fehler auflisten; korrigiert wird im stehengebliebenen Formular-Fenster.
		$in_window = 'pdf' === ( $_POST['submit_mode'] ?? '' ) && '1' === ( $_POST['pdf_in_window'] ?? '' );
		if ( $in_window && ( 'check' === $mode || ! $is_valid ) ) {
			$this->render_pdf_window_errors( $errors, $valid_data );
			exit;
		}

		if ( 'check' === $mode || ! $is_valid ) {
			$is_success = ( $is_valid && 'check' === $mode );
			$refill_data = array_merge( $raw_data, $valid_data );
			$state = [ 'data' => $refill_data, 'errors' => $errors, 'success' => $is_success ];
			set_transient( 'mh_fw_state_' . get_current_user_id(), $state, 60 );
			wp_redirect( wp_get_referer() );
			exit;
		}

		$submission_id = (int)( $_POST['submission_id'] ?? 0 );
		$current_user_id = get_current_user_id();
		$entry_id = 0; // Initialisieren

		// LOGIK: Nur speichern, wenn es KEINE Dienstbefreiung ist
		if ( 'service_leave_v1' !== $form_type_slug ) {

			// Formularsitzung wiedererkennen: wiederholtes Erzeugen aus demselben, noch
			// offenen Formular aktualisiert dieselbe Einsendung (siehe find_id_by_client_token()).
			$client_token = sanitize_text_field( wp_unslash( $_POST['client_token'] ?? '' ) );
			if ( ! preg_match( '/^[a-f0-9-]{36}$/', $client_token ) ) {
				$client_token = '';
			}
			if ( '' !== $client_token ) {
				$valid_data['client_token'] = $client_token;
				if ( $submission_id <= 0 ) {
					$submission_id = $this->repository->find_id_by_client_token( $current_user_id, $form->get_slug(), $client_token );
				}
			}

			$db_data = [ 
				'form_type' => $form->get_slug(), 
				'status' => 'submitted', 
				'user_id' => $current_user_id, 
				'form_data' => $valid_data 
			];

			if ( $submission_id > 0 ) {
				$this->repository->update( $submission_id, $db_data, $current_user_id );
				$entry_id = $submission_id;
			} else {
				$entry_id = $this->repository->create( $db_data );
			}

			if ( 0 === $entry_id ) wp_die( 'DB Error' );

		} else {
			// FALL: Dienstbefreiung (Wird nicht gespeichert)
			// Wir nutzen eine temporäre "ID" für den Dateinamen (z.B. Uhrzeit)
			$entry_id = (int)date('His');
		}

		// Digitale Noteneinsammlung starten statt PDF ausliefern.
		if ( 'collect' === $mode ) {
			$this->start_grade_collection( $entry_id, $valid_data, $current_user_id );
			exit;
		}

		// PDF Generierung
		$valid_data['entry_id'] = $entry_id;
		$data = $valid_data; 
		ob_start();
		if ( 'service_leave_v1' === $form_type_slug ) {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
		} else {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
			if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) {
				include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
			}
		}
		$final_html = ob_get_clean() . '</body></html>';

		$filename = sprintf('%s_%d_%s%s', date('y-m-d'), $entry_id, ('service_leave_v1' === $form_type_slug ? 'Befreiung_' : 'Abmeldung_'), sanitize_file_name($valid_data['lastname']));
		$this->pdf_generator->generate_and_stream( $entry_id, $final_html, $filename );
		exit;
	}

	/**
	 * Fehlerseite für das PDF-Fenster: listet, was die Prüfung bemängelt hat.
	 * Das Formular im anderen Fenster behält alle Eingaben.
	 */
	private function render_pdf_window_errors( array $errors, array $valid_data ): void {
		$items = [];
		foreach ( $errors as $key => $message ) {
			if ( 'date_autocorrect' === $key ) {
				// Konferenz- und Zeugnisdatum werden auf einen Schultag gelegt. Im Formular
				// sind sie schreibgeschützt - ändern lässt sich nur das Abmeldedatum.
				$corrected = ! empty( $valid_data['prot_date'] ) ? date_i18n( 'd.m.Y', strtotime( (string) $valid_data['prot_date'] ) ) : '';
				$message   = 'Das Abmeldedatum fällt nicht auf einen Schultag (Wochenende/Ferien).'
					. ( '' !== $corrected ? ' Konferenz- und Zeugnisdatum würden auf den ' . $corrected . ' gelegt.' : '' )
					. ' Bitte das Datum im Formular prüfen – oder dort „Formular nur prüfen“ nutzen, dann wird die Korrektur übernommen.';
			}
			$items[] = '<li>' . esc_html( (string) $message ) . '</li>';
		}

		$html = '<h1>PDF wurde nicht erzeugt</h1>'
			. '<p>Das Formular hat die Prüfung nicht bestanden:</p>'
			. '<ul>' . implode( '', $items ) . '</ul>'
			. '<p>Bitte dieses Fenster schließen, die Angaben im Formular-Fenster korrigieren und das PDF erneut erzeugen. '
			. 'Deine Eingaben dort sind unverändert.</p>'
			. '<p><button type="button" class="button" onclick="window.close();">Fenster schließen</button></p>';

		wp_die( $html, 'PDF wurde nicht erzeugt', [ 'response' => 200 ] );
	}

	/**
	 * Admin Aktionen
	 */
	public function handle_admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Forbidden');
		$action = $_GET['mh_admin_action'] ?? '';
		$id = (int)($_GET['id'] ?? 0);
		check_admin_referer( 'mh_admin_action_' . $id );
		if ( 'delete' === $action ) {
			$this->repository->delete_as_admin( $id );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=deleted' ) );
			exit;
		}
		if ( 'download' === $action ) {
			// Nonce und Berechtigung sind oben geprüft. Früher lief das über
			// handle_dashboard_action() - das prüfte aber eine andere Nonce und nur den
			// Ersteller, der Download aus dem Backend schlug deshalb immer fehl.
			$entry = $this->repository->get_by_id( $id );
			if ( ! $entry ) wp_die( 'Eintrag nicht gefunden.' );
			$this->stream_submission_pdf( $entry );
			exit;
		}
	}

	/**
	 * Erzeugt das PDF einer gespeicherten Einsendung und liefert es aus.
	 * Die Berechtigungsprüfung liegt beim Aufrufer.
	 */
	private function stream_submission_pdf( array $entry ): void {
		$id         = (int) $entry['id'];
		$valid_data = $entry['form_data'];
		$valid_data['entry_id'] = $id;
		$data = $valid_data;
		ob_start();
		if ( 'service_leave_v1' === $entry['form_type'] ) include MH_FW_PLUGIN_DIR . 'templates/pdf-service-leave.php';
		else {
			include MH_FW_PLUGIN_DIR . 'templates/pdf-abmeldung.php';
			if ( isset( $valid_data['protocol_attached'] ) && '1' === $valid_data['protocol_attached'] ) include MH_FW_PLUGIN_DIR . 'templates/pdf-protocol.php';
		}
		$html = ob_get_clean() . '</body></html>';
		$filename = sprintf('%s_%d_%s', date('y-m-d', strtotime($entry['created_at'])), $id, sanitize_file_name($valid_data['lastname'] ?? ''));
		$this->pdf_generator->generate_and_stream( $id, $html, $filename );
	}

	/**
	 * Dashboard Aktionen (User)
	 */
	public function handle_dashboard_action(): void {
		if ( ! is_user_logged_in() ) wp_die( 'Forbidden' );
		$action = $_GET['mh_action'] ?? '';
		$id = (int)( $_GET['id'] ?? 0 );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mh_dashboard_action_' . $id ) ) wp_die( 'Nonce fail' );
		$current_user = get_current_user_id();

		if ( 'delete' === $action ) {
			$this->repository->delete_submission( $id, $current_user );
			wp_redirect( add_query_arg( 'mh_msg', 'deleted', wp_get_referer() ) );
			exit;
		}
		if ( 'download' === $action ) {
			$entry = $this->repository->get_by_id( $id );
			if ( ! $entry || (int)$entry['user_id'] !== $current_user ) wp_die( 'Denied' );
			$this->stream_submission_pdf( $entry );
			exit;
		}
	}

	/**
	 * Bulk Aktionen (Admin)
	 */
	public function handle_admin_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		check_admin_referer( 'bulk-submissions' );
		$action = $_POST['action'] ?? $_POST['action2'] ?? '';
		$ids = array_map( 'intval', $_POST['bulk_ids'] ?? [] );
		if ( 'delete' === $action && ! empty( $ids ) ) {
			$count = $this->repository->delete_multiple( $ids );
			wp_redirect( admin_url( 'admin.php?page=mh-form-admin-list&mh_msg=bulk_deleted&count=' . $count ) );
			exit;
		}
	}
	/**
	 * Rendert die Hilfe-Seite im Backend
	 */
	public function render_admin_help(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Der Konfigurationscheck läuft bei jedem Aufruf frisch: eine zwischengespeicherte
		// Einrichtungsprüfung wäre genau dann falsch, wenn man sie am dringendsten braucht.
		global $wpdb;
		$check  = new Config_Check( new Diagnostics_Repository( $wpdb ) );
		$report = $check->run();

		include MH_FW_PLUGIN_DIR . 'templates/admin-help.php';
	}
}