<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Controller;

use Mh\FormWorkflows\Repository\Absentismus_Fall_Repository;
use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Submission_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;
use Mh\FormWorkflows\Service\Reminder_Service;

/**
 * Class Dashboard_Controller
 *
 * Einstiegsseite nach dem Login: bündelt das, was gerade offen ist, und verweist
 * auf die vollständigen Listen. Die Fach-Controller bleiben unangetastet — dieser
 * Controller liest nur und schreibt nichts.
 *
 * Bewusst vier getrennte Blöcke statt einer gemischten Liste: Ein Absentismus-Fall,
 * eine laufende Noteneinsammlung und eine Note, die man selbst schuldet, verlangen
 * völlig verschiedene Handlungen. In einer gemeinsamen Liste ginge genau das unter.
 */
class Dashboard_Controller {

	public function __construct(
		private Submission_Repository $submission_repo,
		private Absentismus_Fall_Repository $absentismus_repo,
		private Noten_Fall_Repository $noten_repo,
		private Teacher_Account_Repository $account_repo,
		private Reminder_Service $reminder
	) {}

	/**
	 * Shortcode [mh_dashboard].
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>Bitte melden Sie sich an, um Ihre Vorgänge zu sehen.</p>';
		}

		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'manage_options' );

		// Administratoren können die Gesamtsicht einschalten. Standard bleibt die
		// eigene Sicht, sonst ertrinkt auch ein Admin in fremden Vorgängen.
		$show_all = $is_admin && ! empty( $_GET['mh_all'] );

		$data = [
			'user'        => wp_get_current_user(),
			'is_admin'    => $is_admin,
			'show_all'    => $show_all,
			'absentismus' => $this->collect_absentismus( $user_id, $show_all ),
			'submissions' => $this->collect_submissions( $user_id ),
			'noten_owned' => $this->collect_noten_owned( $user_id, $show_all ),
			'noten_todo'  => $this->collect_noten_todo( $user_id ),
			'links'       => $this->collect_links(),
		];

		ob_start();
		include MH_FW_PLUGIN_DIR . 'templates/dashboard.php';

		return ob_get_clean() ?: '';
	}

	/**
	 * Offene Absentismus-Fälle. Archivierte bleiben draußen — die gehören in die Liste,
	 * nicht auf die Startseite.
	 */
	private function collect_absentismus( int $user_id, bool $show_all ): array {
		$filters = [ 'status' => 'offen', 'archived' => 'exclude' ];
		if ( ! $show_all ) {
			$filters['user_id'] = $user_id;
		}

		return $this->absentismus_repo->get_all_cases( $filters );
	}

	/**
	 * Eigene Einsendungen, neueste zuerst. Das Dashboard zeigt nur den Anfang;
	 * die vollständige Liste steckt hinter [mh_my_submissions].
	 */
	private function collect_submissions( int $user_id ): array {
		$all = $this->submission_repo->get_submissions_by_user(
			$user_id,
			Submission_Repository::ANTRAG_FORM_TYPES
		);

		foreach ( $all as &$sub ) {
			$sub['data'] = is_string( $sub['form_data'] )
				? ( json_decode( $sub['form_data'], true ) ?: [] )
				: (array) $sub['form_data'];
		}
		unset( $sub );

		return [ 'items' => array_slice( $all, 0, 5 ), 'total' => count( $all ) ];
	}

	/**
	 * Selbst gestartete Noteneinsammlungen mit Fortschritt.
	 */
	private function collect_noten_owned( int $user_id, bool $show_all ): array {
		$filters = [ 'status' => 'offen' ];
		if ( ! $show_all ) {
			$filters['owner_user_id'] = $user_id;
		}

		$result = [];
		foreach ( $this->noten_repo->get_all_cases( $filters ) as $case ) {
			$items = $case['form_data']['items'] ?? [];
			$open  = $this->noten_repo->count_open_items( $case );

			$result[] = [
				'case'    => $case,
				'total'   => count( $items ),
				'open'    => $open,
				'done'    => count( $items ) - $open,
				'missing' => $this->missing_teachers( $items ),
				'link'    => $this->reminder->case_link( (int) $case['id'] ),
			];
		}

		return $result;
	}

	/**
	 * Noten, die dieser Benutzer als Fachlehrkraft noch schuldet. Das ist der Block,
	 * der das Dashboard auch für Kolleg*innen ohne Klassenleitung nützlich macht:
	 * bisher stand diese Information nur in der Einladungsmail.
	 */
	private function collect_noten_todo( int $user_id ): array {
		// Liefert eine Liste: in WebUntis kann eine Person mehrere Kürzel führen.
		$kuerzel_list = $this->account_repo->get_kuerzel_for_user( $user_id );

		$result = [];
		foreach ( $this->noten_repo->get_cases_for_teacher( $user_id, $kuerzel_list, 'offen' ) as $entry ) {
			foreach ( $entry['items'] as $item ) {
				if ( 'erledigt' === ( $item['status'] ?? 'offen' ) ) {
					continue;
				}
				$result[] = [
					'case'      => $entry['case'],
					'item'      => $item,
					'link'      => $this->reminder->entry_link( (int) $entry['case']['id'], (int) $item['idx'] ),
					'reminders' => (int) ( $item['reminder_count'] ?? 0 ),
				];
			}
		}

		return $result;
	}

	/**
	 * Kürzel der Lehrkräfte, deren Note noch fehlt — als Klartext für die Fortschrittszeile.
	 */
	private function missing_teachers( array $items ): array {
		$missing = [];
		foreach ( $items as $item ) {
			if ( 'erledigt' === ( $item['status'] ?? 'offen' ) ) {
				continue;
			}
			$kuerzel = (string) ( $item['teacher_kuerzel'] ?? '' );
			if ( '' !== $kuerzel && ! in_array( $kuerzel, $missing, true ) ) {
				$missing[] = $kuerzel;
			}
		}

		return $missing;
	}

	/**
	 * Zielseiten aus den Einstellungen. Fehlt eine, wird der Link später schlicht
	 * weggelassen, statt ins Leere zu zeigen.
	 */
	private function collect_links(): array {
		$options = get_option( 'mh_fw_settings', [] );

		$url = static function ( string $key ) use ( $options ): string {
			$page_id = (int) ( $options[ $key ] ?? 0 );
			if ( $page_id <= 0 ) {
				return '';
			}

			return get_permalink( $page_id ) ?: '';
		};

		return [
			'abmeldung'         => $url( 'page_id_abmeldung_student_v1' ),
			'dienstbefreiung'   => $url( 'page_id_service_leave_v1' ),
			'absentismus_fall'  => $url( 'page_id_mh_absentismus_fall' ),
			'absentismus_liste' => $url( 'page_id_mh_absentismus_liste' ),
			'noten_liste'       => $url( 'page_id_mh_noten_liste' ),
			'meine_antraege'    => $url( 'page_id_mh_my_submissions' ),
		];
	}
}
