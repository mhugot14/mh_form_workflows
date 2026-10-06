<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Setup;

use Mh\FormWorkflows\Controller\Form_Controller;
use Mh\FormWorkflows\Controller\Fall_Controller;
use Mh\FormWorkflows\Controller\Noten_Controller;
use Mh\FormWorkflows\Controller\Dashboard_Controller;
use Mh\FormWorkflows\Controller\Nachschreib_Controller;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
use Mh\FormWorkflows\Repository\Subject_Repository;
use Mh\FormWorkflows\Repository\Track_Subject_Repository;
use Mh\FormWorkflows\Repository\Student_Course_Repository;
use Mh\FormWorkflows\Repository\Absentismus_Fall_Repository;
use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Repository\Nachschreib_Termin_Repository;
use Mh\FormWorkflows\Service\Mail_Service;
use Mh\FormWorkflows\Service\Reminder_Service;
use Mh\FormWorkflows\Service\Pdf_Generator;
use Mh\FormWorkflows\Service\School_Date_Calculator;
use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;
use Mh\FormWorkflows\Service\Nachschreib_Slot_Provider;

/**
 * Class Plugin_Bootstrap
 *
 * Initialisiert die Komponenten des Plugins.
 */
class Plugin_Bootstrap {

	/**
	 * @var Form_Controller Speichert den Controller für Admin-Callbacks.
	 */
	private Form_Controller $form_controller;

	/**
	 * @var Fall_Controller Speichert den Controller für den Absentismus-Fall-Workflow.
	 */
	private Fall_Controller $fall_controller;

	/**
	 * @var Noten_Controller Controller der digitalen Noteneinsammlung.
	 */
	private Noten_Controller $noten_controller;

	/**
	 * @var Dashboard_Controller Einstiegsseite mit allem, was gerade offen ist.
	 */
	private Dashboard_Controller $dashboard_controller;

	/**
	 * @var Nachschreib_Controller Anmeldung zu Nachschreibterminen.
	 */
	private Nachschreib_Controller $nachschreib_controller;

	/**
	 * @var Submission_Repository Wird für den Wartungsbereich der Einstellungsseite gebraucht.
	 */
	private Submission_Repository $submission_repo;

	/**
	 * @var Reminder_Service Wird auch vom Cron-Hook gebraucht.
	 */
	private Reminder_Service $reminder_service;

	/**
	 * Startet das Plugin.
	 */
	public function init(): void {
		$this->load_dependencies();
	}

	/**
	 * Instanziiert Klassen und registriert Hooks.
	 */
	private function load_dependencies(): void {
		global $wpdb;

		// 1. Repositories & Services
		$submission_repo = new Submission_Repository( $wpdb );
		$this->submission_repo = $submission_repo;
		$class_repo      = new Class_Repository( $wpdb );
		$teacher_repo    = new Teacher_Repository( $wpdb );
		 $student_repo =   new Student_Repository( $wpdb );
		$pdf_generator   = new Pdf_Generator();
                $subject_repo = new Subject_Repository( $wpdb );
		$track_subject_repo  = new Track_Subject_Repository( $wpdb );
		$student_course_repo = new Student_Course_Repository( $wpdb );
		$fall_repo       = new Absentismus_Fall_Repository( $wpdb );
		$noten_repo      = new Noten_Fall_Repository( $wpdb );
		$account_repo    = new Teacher_Account_Repository( $wpdb );
		$mail_service    = new Mail_Service();

		$this->reminder_service = new Reminder_Service( $noten_repo, $account_repo, $mail_service );

		// 2. Controller instanziieren und in Property speichern
		$this->form_controller = new Form_Controller(
			$submission_repo,
			$class_repo,
			$teacher_repo,
			$student_repo,
			$subject_repo,
			$track_subject_repo,
			$student_course_repo,
			$noten_repo,
			$account_repo,
			$mail_service,
			$this->reminder_service,
			$pdf_generator
		);

		$this->noten_controller = new Noten_Controller(
			$noten_repo,
			$account_repo,
			$submission_repo,
			$mail_service,
			$this->reminder_service
		);

		$this->fall_controller = new Fall_Controller(
			$fall_repo,
			$class_repo,
			$student_repo,
			$pdf_generator
		);

		// Liest nur - das Dashboard fasst zusammen, was die anderen Controller anlegen.
		$this->dashboard_controller = new Dashboard_Controller(
			$submission_repo,
			$fall_repo,
			$noten_repo,
			$account_repo,
			$this->reminder_service
		);

		// Nachschreibtermine. Der Slot-Provider ist die Naht für eine spätere Platzbuchung:
		// Eine Implementierung mit Kapazitäten wird nur hier ausgetauscht.
		$ns_katalog  = new Nachschreib_Termin_Katalog();
		$ns_calendar = new School_Date_Calculator();
		$ns_termine  = new Nachschreib_Termin_Repository( $wpdb );
		$this->nachschreib_controller = new Nachschreib_Controller(
			$submission_repo,
			$class_repo,
			$teacher_repo,
			$subject_repo,
			$account_repo,
			$ns_katalog,
			new Nachschreib_Slot_Provider( $ns_katalog, $ns_calendar, $ns_termine, $submission_repo ),
			$ns_termine,
			$ns_calendar,
			$pdf_generator
		);

		// 3. Hooks registrieren
		add_action( 'init', [ $this, 'register_blocks' ] );
		
		// Formular Handling (POST)
		add_action( 'admin_post_mh_submit_form', [ $this->form_controller, 'handle_submission' ] );
		add_action( 'admin_post_nopriv_mh_submit_form', [ $this->form_controller, 'handle_submission' ] );
		
		// Admin Menü & Settings
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_mh_fw_cleanup_legacy', [ $this, 'handle_cleanup_legacy' ] );
		// Muss früh (admin_init) laufen, NICHT im Seiten-Callback selbst — dort sind
		// die Header bereits gesendet (WP hat Admin-Header/Skripte schon ausgegeben).
		add_action( 'admin_init', [ $this, 'maybe_redirect_absentismus_liste' ] );

		// Admin Aktionen (Löschen/Download)
		add_action( 'admin_init', function() {
			if ( isset( $_GET['mh_admin_action'] ) ) {
				$this->form_controller->handle_admin_action();
			}
		});

		// Dashboard Aktionen für User (Download/Delete)
		add_action( 'init', function() {
			if ( isset( $_GET['mh_action'] ) ) {
				$this->form_controller->handle_dashboard_action();
			}
		});
		add_action( 'admin_init', function() {
            if ( isset( $_POST['bulk_ids'] ) && (isset($_POST['action']) || isset($_POST['action2'])) ) {
                $this->form_controller->handle_admin_bulk_action();
            }
        });
		add_action('wp_ajax_mh_get_students', [$this->form_controller, 'ajax_get_students']);
		add_action('wp_ajax_mh_get_subject_rows', [$this->form_controller, 'ajax_get_subject_rows']);

		add_shortcode( 'mh_form_workflow', [ $this->form_controller, 'render_form' ] );
		add_shortcode( 'mh_my_submissions', [ $this->form_controller, 'render_dashboard' ] );
		add_shortcode( 'mh_dashboard', [ $this->dashboard_controller, 'render' ] );

		// Digitale Noteneinsammlung: nur für eingeloggte Nutzer, deshalb keine nopriv-Hooks.
		add_action( 'admin_post_mh_noten_save_item', [ $this->noten_controller, 'handle_save_item' ] );
		add_action( 'admin_post_mh_noten_remind_now', [ $this->noten_controller, 'handle_remind_now' ] );
		add_action( 'admin_post_mh_noten_admin_complete', [ $this->noten_controller, 'handle_admin_complete' ] );

		// Absendername für ALLE Mails der Website (Einstellung „Absendername für E-Mails“).
		// Leer = WordPress-Standard ("WordPress").
		add_filter( 'wp_mail_from_name', static function ( $name ) {
			$custom = Mail_Service::from_name();
			return '' !== $custom ? $custom : $name;
		}, 20 );
		add_action( 'admin_post_mh_noten_end_case', [ $this->noten_controller, 'handle_end_case' ] );
		add_action( 'admin_post_mh_noten_owner_done', [ $this->noten_controller, 'handle_owner_done' ] );

		add_shortcode( 'mh_noten_eingabe', [ $this->noten_controller, 'render_eingabe' ] );
		add_shortcode( 'mh_noten_liste', [ $this->noten_controller, 'render_liste' ] );
		add_shortcode( 'mh_noten_fall', [ $this->noten_controller, 'render_fall' ] );

		// Erinnerungen. WP-Cron feuert nur bei Seitenaufrufen — für verlässliche Fristen
		// sollte auf dem Server ein echter Cron-Job wp-cron.php aufrufen.
		add_action( Reminder_Service::CRON_HOOK, [ $this->reminder_service, 'run' ] );
		add_action( 'init', function (): void {
			if ( ! wp_next_scheduled( Reminder_Service::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Reminder_Service::CRON_HOOK );
			}
		} );

		// Absentismus-Fall-Workflow: eingeloggte Nutzer only, keine nopriv-Hooks
		// (sensible Schülerdaten, siehe Fall_Controller-Berechtigungsprüfungen).
		add_action( 'admin_post_mh_absentismus_open_case', [ $this->fall_controller, 'handle_open_case_submission' ] );
		add_action( 'admin_post_mh_absentismus_step_submit', [ $this->fall_controller, 'handle_step_submission' ] );
		add_action( 'admin_post_mh_absentismus_finalize_step', [ $this->fall_controller, 'handle_finalize_step' ] );
		add_action( 'admin_post_mh_absentismus_close_case', [ $this->fall_controller, 'handle_close_case' ] );
		add_action( 'admin_post_mh_absentismus_reopen_case', [ $this->fall_controller, 'handle_reopen_case' ] );
		add_action( 'admin_post_mh_absentismus_archive_case', [ $this->fall_controller, 'handle_archive_case' ] );
		add_action( 'admin_post_mh_absentismus_unarchive_case', [ $this->fall_controller, 'handle_unarchive_case' ] );
		add_action( 'admin_post_mh_absentismus_bulk_archive', [ $this->fall_controller, 'handle_bulk_archive' ] );
		add_action( 'admin_post_mh_absentismus_download_pdf', [ $this->fall_controller, 'handle_download_step_pdf' ] );
		add_action( 'admin_post_mh_absentismus_add_note', [ $this->fall_controller, 'handle_add_note' ] );
		add_action( 'admin_post_mh_absentismus_delete_note', [ $this->fall_controller, 'handle_delete_note' ] );
		add_action( 'admin_post_mh_absentismus_update_contacts', [ $this->fall_controller, 'handle_update_contacts' ] );
		add_action( 'admin_post_mh_absentismus_standalone_submit', [ $this->fall_controller, 'handle_standalone_step_submission' ] );

		// Nachschreibtermine: nur eingeloggte Nutzer (Schülernamen), keine nopriv-Hooks.
		add_action( 'admin_post_mh_nachschreib_submit', [ $this->nachschreib_controller, 'handle_submit' ] );
		add_action( 'admin_post_mh_nachschreib_pdf', [ $this->nachschreib_controller, 'handle_download' ] );
		add_action( 'admin_post_mh_nachschreib_delete', [ $this->nachschreib_controller, 'handle_delete' ] );
		add_action( 'admin_post_mh_nachschreib_termine_save', [ $this->nachschreib_controller, 'handle_termine_save' ] );
		add_action( 'admin_post_mh_nachschreib_liste', [ $this->nachschreib_controller, 'handle_liste_druck' ] );
		add_shortcode( Nachschreib_Controller::SHORTCODE, [ $this->nachschreib_controller, 'render_form' ] );

		add_shortcode( 'mh_absentismus_fall', [ $this->fall_controller, 'render_fall_view' ] );
		add_shortcode( 'mh_absentismus_liste', [ $this->fall_controller, 'render_fall_liste' ] );

		// Die 8 Einzelformulare (unabhängig von einem Fall) — Typ ergibt sich im
		// Controller aus dem jeweils aufgerufenen Shortcode-Tag, daher genügt hier
		// eine Schleife über dieselbe Zuordnungstabelle statt 8 einzelner Zeilen.
		foreach ( array_keys( Fall_Controller::STANDALONE_SHORTCODES ) as $shortcode_tag ) {
			add_shortcode( $shortcode_tag, [ $this->fall_controller, 'render_standalone_step_form' ] );
		}
	}

	/**
	 * Registriert das Admin-Menü.
	 */
	public function add_admin_menu(): void {
		// Hauptpunkt (zeigt jetzt die Hilfe/Übersicht)
		add_menu_page(
			'MH Formulare',
			'MH Formulare',
			'manage_options',
			'mh-form-admin-help', // Neuer Haupt-Slug
			[ $this->form_controller, 'render_admin_help' ],
			'dashicons-clipboard',
			30
		);

		// Unterpunkt 1: Übersicht (muss den gleichen Slug wie der Hauptpunkt haben)
		add_submenu_page(
			'mh-form-admin-help',
			'Übersicht & Hilfe',
			'Übersicht & Hilfe',
			'manage_options',
			'mh-form-admin-help',
			[ $this->form_controller, 'render_admin_help' ]
		);

		// Unterpunkt 2: Alle Einsendungen
		add_submenu_page(
			'mh-form-admin-help',
			'Alle Einsendungen',
			'Alle Einsendungen',
			'manage_options',
			'mh-form-admin-list',
			[ $this->form_controller, 'render_admin_dashboard' ]
		);

		// Unterpunkt 2b: Noteneinsammlung - Fortschritt und Fehlerdiagnose aller Fälle
		add_submenu_page(
			'mh-form-admin-help',
			'Noteneinsammlung',
			'Noteneinsammlung',
			'manage_options',
			Noten_Controller::ADMIN_SLUG,
			[ $this->noten_controller, 'render_admin_overview' ]
		);

		// Unterpunkt 3: Einstellungen
		add_submenu_page(
			'mh-form-admin-help',
			'Einstellungen',
			'Einstellungen',
			'manage_options',
			'mh-form-workflows-settings',
			[ $this, 'render_settings_page' ]
		);

		// Unterpunkt 4: Absentismus-Fälle (verlinkt auf die konfigurierte Frontend-Seite,
		// keine eigene wp-admin-Ansicht, um die Fall-Übersicht nicht doppelt zu bauen).
		add_submenu_page(
			'mh-form-admin-help',
			'Absentismus-Fälle',
			'Absentismus-Fälle',
			'manage_options',
			'mh-form-absentismus-list',
			[ $this, 'render_absentismus_liste_placeholder' ]
		);
	}

	/**
	 * Leitet vom Admin-Menüpunkt zur konfigurierten Frontend-Seite mit dem
	 * Shortcode [mh_absentismus_liste] weiter. Muss auf admin_init laufen,
	 * bevor irgendein Output (Admin-Header, Skripte) gesendet wurde.
	 */
	public function maybe_redirect_absentismus_liste(): void {
		if ( ! isset( $_GET['page'] ) || 'mh-form-absentismus-list' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_absentismus_liste'] ?? 0 );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : false;

		if ( $url ) {
			wp_redirect( $url );
			exit;
		}
	}

	/**
	 * Fallback-Anzeige, falls noch keine Seite mit [mh_absentismus_liste]
	 * konfiguriert ist (dann greift der Redirect in maybe_redirect_absentismus_liste() nicht).
	 */
	public function render_absentismus_liste_placeholder(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>Absentismus-Fälle</h1><p>Bitte zunächst unter Einstellungen eine Seite mit dem Shortcode <code>[mh_absentismus_liste]</code> festlegen.</p></div>';
	}

	/**
	 * Registriert die Plugin-Einstellungen.
	 */
	public function register_settings(): void {
		register_setting( 'mh_fw_settings_group', 'mh_fw_settings' );
	}

	/**
	 * Rendert die Einstellungsseite.
	 */
	public function render_settings_page(): void {
		$options = get_option( 'mh_fw_settings', [] );
		?>
		<div class="wrap">
			<h1>MH Form Workflows - Einstellungen</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'mh_fw_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th>Seite für Schüler-Abmeldung</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_abmeldung_student_v1]',
								'selected' => $options['page_id_abmeldung_student_v1'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
						</td>
					</tr>
					<tr>
						<th>Seite für das Dashboard</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_dashboard]',
								'selected' => $options['page_id_mh_dashboard'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">
								Seite mit dem Shortcode <code>[mh_dashboard]</code>. Die Seite funktioniert auch ohne
								diese Zuordnung &ndash; sie wird nur gebraucht, damit die Mails der Noteneinsammlung
								darauf verweisen können.
							</p>
						</td>
					</tr>
					<tr>
						<th>Seite für „Meine Formulare"</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_my_submissions]',
								'selected' => $options['page_id_mh_my_submissions'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_my_submissions]</code> &ndash; die vollständige Liste der eigenen Abmeldungen. Wird vom Dashboard verlinkt.</p>
						</td>
					</tr>
					<tr>
						<th>Seite für Dienstbefreiung</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_service_leave_v1]',
								'selected' => $options['page_id_service_leave_v1'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
						</td>
					</tr>
					<tr>
						<th>Seite für Nachschreibtermine</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_nachschreib]',
								'selected' => $options['page_id_mh_nachschreib'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_nachschreib_anmeldung]</code>.</p>
						</td>
					</tr>
					<?php $this->render_nachschreib_settings( $options ); ?>
					<tr>
						<th>Seite für Absentismus-Fall (Formular)</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_absentismus_fall]',
								'selected' => $options['page_id_mh_absentismus_fall'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_absentismus_fall]</code>.</p>
						</td>
					</tr>
					<tr>
						<th>Seite für Absentismus-Fälle (Übersicht)</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_absentismus_liste]',
								'selected' => $options['page_id_mh_absentismus_liste'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_absentismus_liste]</code>.</p>
						</td>
					</tr>
					<tr>
						<th>Seite für Noteneingabe (Fachlehrkraft)</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_noten_eingabe]',
								'selected' => $options['page_id_mh_noten_eingabe'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_noten_eingabe]</code>. Ziel der Einladungs- und Erinnerungsmails.</p>
						</td>
					</tr>
					<tr>
						<th>Seite für „Meine Noteneingaben"</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_noten_liste]',
								'selected' => $options['page_id_mh_noten_liste'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_noten_liste]</code>.</p>
						</td>
					</tr>
					<tr>
						<th>Seite für Noteneinsammlung (Klassenlehrer)</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_mh_noten_fall]',
								'selected' => $options['page_id_mh_noten_fall'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
							<p class="description">Seite mit dem Shortcode <code>[mh_noten_fall]</code>.</p>
						</td>
					</tr>
					<tr>
						<th>Absendername für E-Mails</th>
						<td>
							<input type="text" name="mh_fw_settings[mail_from_name]" class="regular-text"
							       value="<?= esc_attr( Mail_Service::from_name() ) ?>" placeholder="WordPress">
							<p class="description">
								Gilt für <strong>alle</strong> Mails dieser Website &ndash; die der Noteneinsammlung ebenso wie
								Passwort-Mails und Mails anderer Plugins. WordPress selbst bietet dafür keine Einstellung und
								schreibt sonst „WordPress“. Leer lassen, um den WordPress-Standard zu behalten.<br>
								Hinweis: Verschickt ein Mail-Plugin (z.&nbsp;B. WPO365) über Microsoft 365, kann dort der
								Anzeigename des Postfachs Vorrang haben.
							</p>
						</td>
					</tr>
					<tr>
						<th>Digitale Noteneinsammlung</th>
						<td>
							<?php
							// Hidden-Feld VOR der Checkbox: eine abgewählte Checkbox sendet nichts,
							// dann bliebe der alte Wert stehen und liesse sich nie abschalten.
							$noten_an = \Mh\FormWorkflows\Service\Noten_Feature::is_enabled();
							?>
							<input type="hidden" name="mh_fw_settings[noten_enabled]" value="0">
							<label>
								<input type="checkbox" name="mh_fw_settings[noten_enabled]" value="1" <?php checked( $noten_an ); ?>>
								Verfahren aktiv &ndash; neue Einsammlungen dürfen gestartet werden
							</label>
							<p class="description">
								Das Verfahren ist noch in der Erprobung (BETA). Wird der Haken entfernt, verschwindet die
								Option „automatisch einsammeln“ aus dem Abmeldeformular und es lässt sich keine neue
								Einsammlung mehr starten.<br>
								<strong>Bereits laufende Fälle bleiben vollständig bedienbar</strong> und werden weiter
								erinnert, bis sie abgeschlossen sind &ndash; sonst gingen die schon eingetragenen Noten
								verloren, weil sie erst beim Abschluss in die Abmeldung zurückgeschrieben werden.
							</p>
						</td>
					</tr>
					<tr>
						<th>Erinnerung nach (Tagen)</th>
						<td>
							<input type="number" min="1" max="60" name="mh_fw_settings[noten_reminder_days]"
							       value="<?= esc_attr( $options['noten_reminder_days'] ?? 3 ) ?>" class="small-text">
							<p class="description">Abstand zwischen zwei Erinnerungen an eine Fachlehrkraft.</p>
						</td>
					</tr>
					<tr>
						<th>Klassenlehrer informieren nach</th>
						<td>
							<input type="number" min="1" max="20" name="mh_fw_settings[noten_escalate_after]"
							       value="<?= esc_attr( $options['noten_escalate_after'] ?? 2 ) ?>" class="small-text">
							<p class="description">Anzahl erfolgloser Erinnerungen, nach der zusätzlich der Klassenlehrer benachrichtigt wird (einmalig).</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php $this->render_maintenance_section(); ?>
		</div>
		<?php
	}

	/**
	 * Einstellung: Wer darf die Nachschreibtermine im Frontend verwalten? Administratoren
	 * dürfen es immer und stehen deshalb nicht in der Liste.
	 */
	private function render_nachschreib_settings( array $options ): void {
		$selected = array_map( 'intval', (array) ( $options['ns_manager_ids'] ?? [] ) );
		$users    = get_users( [
			'role__not_in' => [ 'administrator' ],
			'orderby'      => 'display_name',
			'fields'       => [ 'ID', 'display_name' ],
		] );
		?>
		<tr>
			<th>Terminverwaltung Nachschreiben</th>
			<td>
				<input type="search" id="mh-ns-mgr-filter" placeholder="Namen filtern …" class="regular-text" style="margin-bottom:6px;">
				<div id="mh-ns-mgr-list" style="max-height:220px; overflow:auto; border:1px solid #c3c4c7; background:#fff; padding:6px 10px; max-width:420px;">
					<?php if ( empty( $users ) ) : ?>
						<em>Keine Benutzer außer Administrator*innen vorhanden.</em>
					<?php endif; ?>
					<?php foreach ( $users as $u ) : ?>
						<label style="display:block; margin:3px 0;">
							<input type="checkbox" name="mh_fw_settings[ns_manager_ids][]" value="<?= (int) $u->ID ?>" <?php checked( in_array( (int) $u->ID, $selected, true ) ); ?>>
							<?= esc_html( $u->display_name ) ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description">
					Ausgewählte Lehrkräfte sehen auf der Nachschreib-Seite den Reiter <strong>„Termine verwalten“</strong>:
					regelmäßige Termine deaktivieren, Samstage freischalten, lange Termine anlegen sowie Uhrzeit, Raum und
					Kontingent je Termin ändern. Administrator*innen dürfen das immer.
					Ausgewählt: <strong><?= count( $selected ) ?></strong>.
				</p>
				<script>
					document.getElementById('mh-ns-mgr-filter').addEventListener('input', function () {
						var q = this.value.toLowerCase();
						document.querySelectorAll('#mh-ns-mgr-list label').forEach(function (l) {
							l.style.display = l.textContent.toLowerCase().indexOf(q) === -1 ? 'none' : 'block';
						});
					});
				</script>
			</td>
		</tr>
		<tr>
			<th>Vorgabe-Kontingent Nachschreiben</th>
			<td>
				<?php foreach ( ( new Nachschreib_Termin_Katalog() )->all() as $typ => $def ) :
					$key = Nachschreib_Termin_Katalog::kontingent_option_key( $typ ); ?>
					<label style="display:inline-block; margin:0 18px 6px 0;">
						<?= esc_html( $def['label'] ) ?><br>
						<input type="number" min="0" max="999" class="small-text" name="mh_fw_settings[<?= esc_attr( $key ) ?>]"
						       value="<?= (int) $def['kontingent'] ?>"> Plätze
					</label>
				<?php endforeach; ?>
				<p class="description">
					Plätze je Termin, wenn in der Terminverwaltung nichts Abweichendes eingetragen ist.
					Ab <?= (int) Nachschreib_Termin_Katalog::WENIGE_PLAETZE ?> Restplätzen wird ein Termin im Formular gelb,
					bei 0 als ausgebucht angezeigt. Leer = Werkseinstellung
					(<?= esc_html( implode( ', ', array_map(
						static fn( string $t, array $d ): string => $d['label'] . ' ' . Nachschreib_Termin_Katalog::kontingent_werk( $t ),
						array_keys( ( new Nachschreib_Termin_Katalog() )->all() ),
						( new Nachschreib_Termin_Katalog() )->all()
					) ) ) ?>).
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wartung: Altlasten aus mh_form_submissions.
	 *
	 * Dienstbefreiungen werden nicht gespeichert (der Riegel steht in
	 * Form_Controller::handle_submission()). Aus älteren Plugin-Versionen können aber
	 * noch Zeilen dieses Typs liegen. Sie erscheinen in keiner Liste und wären ohne
	 * diesen Abschnitt nur per Datenbankzugriff erreichbar.
	 */
	private function render_maintenance_section(): void {
		$info = $this->submission_repo->inspect_form_type( 'service_leave_v1' );
		?>
		<hr style="margin:35px 0 25px;">
		<h2>Wartung</h2>

		<?php if ( isset( $_GET['mh_cleaned'] ) ) : ?>
			<div class="notice notice-success is-dismissible" style="margin:0 0 15px;">
				<p><?= (int) $_GET['mh_cleaned'] ?> Zeile(n) entfernt.</p>
			</div>
		<?php endif; ?>

		<?php if ( 0 === $info['count'] ) : ?>
			<p>Keine Altlasten gefunden &ndash; es liegen keine gespeicherten Dienstbefreiungen in der Datenbank.</p>
		<?php else : ?>
			<div class="notice notice-warning inline" style="margin:0 0 15px; padding:10px 12px;">
				<p style="margin:0 0 6px;">
					<strong><?= (int) $info['count'] ?></strong> gespeicherte Dienstbefreiung(en) gefunden.
					Dienstbefreiungen werden nicht mehr gespeichert; diese Zeilen stammen aus einer
					früheren Plugin-Version und erscheinen in keiner Liste mehr.
				</p>
				<p style="margin:0; color:#50575e;">
					IDs: <?= esc_html( implode( ', ', $info['ids'] ) ) ?>
					&nbsp;·&nbsp; Zeitraum: <?= esc_html( (string) $info['first'] ) ?>
					bis <?= esc_html( (string) $info['last'] ) ?>
				</p>
			</div>

			<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>"
			      onsubmit="return confirm('<?= esc_attr( $info['count'] ) ?> Zeile(n) endgültig löschen? Das lässt sich nicht rückgängig machen.');">
				<?php wp_nonce_field( 'mh_fw_cleanup_legacy' ); ?>
				<input type="hidden" name="action" value="mh_fw_cleanup_legacy">
				<?php submit_button( 'Altlasten jetzt entfernen', 'delete', 'submit', false ); ?>
				<span class="description" style="margin-left:10px;">Vorher bitte ein Backup der Tabelle ziehen.</span>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Führt das Aufräumen aus. Löscht ausschliesslich Zeilen vom Typ service_leave_v1.
	 */
	public function handle_cleanup_legacy(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'mh_fw_cleanup_legacy' );

		$deleted = $this->submission_repo->delete_by_form_type( 'service_leave_v1' );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'mh-form-workflows-settings', 'mh_cleaned' => $deleted ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Registriert den Gutenberg Block.
	 */
	public function register_blocks(): void {
		register_block_type( 'mh/form-workflow', [
			'api_version'     => 3,
			'render_callback' => function( $attributes ) {
				return $this->form_controller->render_form( $attributes );
			}
		]);
	}
}