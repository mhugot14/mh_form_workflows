<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Setup;

use Mh\FormWorkflows\Controller\Form_Controller;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;
use Mh\FormWorkflows\Repository\Teacher_Repository;
use Mh\FormWorkflows\Repository\Student_Repository;
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

		// 2. Controller instanziieren und in Property speichern
		$this->form_controller = new Form_Controller( 
			$submission_repo, 
			$class_repo, 
			$teacher_repo,
			$student_repo,
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
		
		add_shortcode( 'mh_form_workflow', [ $this->form_controller, 'render_form' ] );
		add_shortcode( 'mh_my_submissions', [ $this->form_controller, 'render_dashboard' ] );
	}

	/**
	 * Registriert das Admin-Menü.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			'MH Formulare',
			'MH Formulare',
			'manage_options',
			'mh-form-admin-list',
			[ $this->form_controller, 'render_admin_dashboard' ],
			'dashicons-clipboard',
			30
		);

		add_submenu_page(
			'mh-form-admin-list',
			'Alle Einsendungen',
			'Alle Einsendungen',
			'manage_options',
			'mh-form-admin-list',
			[ $this->form_controller, 'render_admin_dashboard' ]
		);

		add_submenu_page(
			'mh-form-admin-list',
			'Einstellungen',
			'Einstellungen',
			'manage_options',
			'mh-form-workflows-settings',
			[ $this, 'render_settings_page' ]
		);
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
						<th>Seite für Dienstbefreiung</th>
						<td>
							<?php wp_dropdown_pages([
								'name' => 'mh_fw_settings[page_id_service_leave_v1]',
								'selected' => $options['page_id_service_leave_v1'] ?? 0,
								'show_option_none' => '-- Seite wählen --'
							]); ?>
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