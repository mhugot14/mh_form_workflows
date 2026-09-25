<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Setup;

use Mh\FormWorkflows\Controller\Form_Controller;
use Mh\FormWorkflows\Controller\Fall_Controller;
use Mh\FormWorkflows\Controller\Noten_Controller;
use Mh\FormWorkflows\Controller\Dashboard_Controller;
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
use Mh\FormWorkflows\Service\Mail_Service;
use Mh\FormWorkflows\Service\Reminder_Service;
use Mh\FormWorkflows\Service\Pdf_Generator;

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

		// 3. Hooks registrieren
		add_action( 'init', [ $this, 'register_blocks' ] );
		
		// Formular Handling (POST)
		add_action( 'admin_post_mh_submit_form', [ $this->form_controller, 'handle_submission' ] );
		add_action( 'admin_post_nopriv_mh_submit_form', [ $this->form_controller, 'handle_submission' ] );
		
		// Admin Menü & Settings
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
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
						<th>Seite für „Meine Anträge"</th>
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
		</div>
		<?php
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