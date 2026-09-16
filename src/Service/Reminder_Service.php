<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

use Mh\FormWorkflows\Repository\Noten_Fall_Repository;
use Mh\FormWorkflows\Repository\Teacher_Account_Repository;

/**
 * Class Reminder_Service
 *
 * Die Cron-Logik der Noteneinsammlung, bewusst vom Controller getrennt: so lässt sie
 * sich ohne HTTP-Request und ohne angemeldeten Benutzer direkt aufrufen und prüfen.
 *
 * Hinweis zum Betrieb: WP-Cron feuert nur bei Seitenaufrufen. Für verlässliche Fristen
 * sollte auf dem Server ein echter Cron-Job wp-cron.php aufrufen.
 */
class Reminder_Service {

	public const CRON_HOOK = 'mh_fw_noten_reminders';

	public function __construct(
		private Noten_Fall_Repository $case_repo,
		private Teacher_Account_Repository $account_repo,
		private Mail_Service $mail
	) {}

	private function interval_days(): int {
		$options = get_option( 'mh_fw_settings', [] );
		$days    = (int) ( $options['noten_reminder_days'] ?? 3 );

		return $days > 0 ? $days : 3;
	}

	private function escalate_after(): int {
		$options = get_option( 'mh_fw_settings', [] );
		$count   = (int) ( $options['noten_escalate_after'] ?? 2 );

		return $count > 0 ? $count : 2;
	}

	/**
	 * Ein Durchlauf über alle offenen Fälle.
	 *
	 * @return array{reminded:int, escalated:int, skipped:int} Zählwerte für Protokoll und Test.
	 */
	public function run(): array {
		$interval  = $this->interval_days();
		$escalate  = $this->escalate_after();
		$now       = current_time( 'timestamp' );
		$threshold = $interval * DAY_IN_SECONDS;

		$stats = [ 'reminded' => 0, 'escalated' => 0, 'skipped' => 0 ];

		foreach ( $this->case_repo->get_all_cases( [ 'status' => 'offen' ] ) as $case ) {
			$case_id = (int) $case['id'];

			foreach ( $case['form_data']['items'] ?? [] as $item ) {
				if ( 'erledigt' === ( $item['status'] ?? 'offen' ) ) {
					continue;
				}

				// Maßgeblich ist die letzte Kontaktaufnahme, nicht der Fallbeginn:
				// so wird nach einer Erinnerung wieder das volle Intervall gewartet.
				$last = $item['last_reminder_at'] ?? $item['notified_at'] ?? null;
				if ( null === $last ) {
					$stats['skipped']++;
					continue; // noch nie benachrichtigt — das erledigt der Start des Falls
				}

				$last_ts = strtotime( (string) $last );
				if ( false === $last_ts || ( $now - $last_ts ) < $threshold ) {
					continue;
				}

				$idx       = (int) $item['idx'];
				$recipient = $this->account_repo->resolve_recipient( (string) $item['teacher_kuerzel'] );

				if ( null === $recipient ) {
					$stats['skipped']++;
				} else {
					$link = $this->entry_link( $case_id, $idx );
					$this->mail->send_reminder(
						$recipient['email'],
						$recipient['name'],
						$case,
						$item,
						$link,
						(int) ( $item['reminder_count'] ?? 0 ) + 1
					);
					$this->case_repo->mark_notified( $case_id, $idx, true );
					$stats['reminded']++;
				}

				// Eskalation genau einmal: escalated_at verhindert die Wiederholung.
				$reminders_after = (int) ( $item['reminder_count'] ?? 0 ) + 1;
				if ( $reminders_after >= $escalate && empty( $item['escalated_at'] ) ) {
					if ( $this->escalate( $case, $item ) ) {
						$this->case_repo->mark_escalated( $case_id, $idx );
						$stats['escalated']++;
					}
				}
			}
		}

		return $stats;
	}

	private function escalate( array $case, array $item ): bool {
		$owner_id = (int) ( $case['form_data']['owner_user_id'] ?? $case['user_id'] ?? 0 );
		$owner    = $owner_id > 0 ? get_userdata( $owner_id ) : false;
		if ( ! $owner || '' === (string) $owner->user_email ) {
			return false;
		}

		return $this->mail->send_escalation(
			(string) $owner->user_email,
			$case,
			$item,
			$this->account_repo->get_display_name( (string) $item['teacher_kuerzel'] ),
			$this->case_link( (int) $case['id'] )
		);
	}

	/**
	 * Link auf die Eingabemaske einer einzelnen Note.
	 */
	public function entry_link( int $case_id, int $idx ): string {
		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_noten_eingabe'] ?? 0 );
		$base    = $page_id > 0 ? ( get_permalink( $page_id ) ?: home_url( '/' ) ) : home_url( '/' );

		return add_query_arg( [ 'mh_noten_id' => $case_id, 'mh_item' => $idx ], $base );
	}

	/**
	 * Link auf die Klassenlehrer-Sicht eines Falls.
	 */
	public function case_link( int $case_id ): string {
		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_noten_fall'] ?? 0 );
		$base    = $page_id > 0 ? ( get_permalink( $page_id ) ?: home_url( '/' ) ) : home_url( '/' );

		return add_query_arg( 'mh_noten_id', $case_id, $base );
	}
}
