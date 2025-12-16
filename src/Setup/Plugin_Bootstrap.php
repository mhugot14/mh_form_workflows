<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Setup;

use Mh\FormWorkflows\Controller\Form_Controller;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Class_Repository;   // <-- NEU
use Mh\FormWorkflows\Repository\Teacher_Repository; // <-- NEU
use Mh\FormWorkflows\Service\Pdf_Generator;

/**
 * Class Plugin_Bootstrap
 *
 * Initialisiert die Komponenten des Plugins.
 */
class Plugin_Bootstrap {

	/**
	 * Startet das Plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_dependencies();
	}

	/**
	 * Instanziiert Klassen und registriert Hooks.
	 * (Manuelle Dependency Injection).
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		global $wpdb;

		// 1. Services & Repositories instanziieren
		$submission_repo = new Submission_Repository( $wpdb );
		$class_repo      = new Class_Repository( $wpdb );     // <-- NEU: Stammdaten
		$teacher_repo    = new Teacher_Repository( $wpdb );   // <-- NEU: Stammdaten
		$pdf_generator   = new Pdf_Generator();

		// 2. Controller instanziieren und ALLE 4 Abhängigkeiten injizieren
		// WICHTIG: Die Reihenfolge muss exakt zum __construct im Form_Controller passen!
		$form_controller = new Form_Controller( 
			$submission_repo, 
			$class_repo, 
			$teacher_repo, 
			$pdf_generator 
		);

		// 3. Hooks registrieren
		// Gutenberg Block Registrierung
		add_action( 'init', [ $this, 'register_blocks' ] );
		
		// AJAX / Formular Handling
		add_action( 'admin_post_mh_submit_form', [ $form_controller, 'handle_submission' ] );
		add_action( 'admin_post_nopriv_mh_submit_form', [ $form_controller, 'handle_submission' ] );
		
		// Shortcode für den Render-Controller (als Fallback/Block Callback)
		add_shortcode( 'mh_form_workflow', [ $form_controller, 'render_form' ] );
	}

	/**
	 * Registriert den Gutenberg Block (PHP-Side).
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		
		// Wir geben dem Block Attribute mit (Titel, Icon, Kategorie), 
		// damit man ihn im Editor findet.
		register_block_type( 'mh/form-workflow', [
			'api_version'     => 3,
			'title'           => 'MH Formular Workflow',
			'icon'            => 'pdf',
			'category'        => 'widgets',
			'editor_script'   => null, 
			'render_callback' => function( $attributes ) {
				// Dependency Injection "Manuell" für den Render-Context (Frontend/Editor)
				global $wpdb;
				
				// Achtung: Namespaces beachten bei new ...
				// Hier müssen wir EBENFALLS alle 4 Dependencies erzeugen
				$sub_repo     = new \Mh\FormWorkflows\Repository\Submission_Repository( $wpdb );
				$class_repo   = new \Mh\FormWorkflows\Repository\Class_Repository( $wpdb );     // <-- NEU
				$teacher_repo = new \Mh\FormWorkflows\Repository\Teacher_Repository( $wpdb );   // <-- NEU
				$pdf          = new \Mh\FormWorkflows\Service\Pdf_Generator();
				
				// Controller erstellen
				$controller = new \Mh\FormWorkflows\Controller\Form_Controller( 
					$sub_repo, 
					$class_repo, 
					$teacher_repo, 
					$pdf 
				);
				
				return $controller->render_form( $attributes );
			}
		]);
	}
}