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

	public const LAST_RUN_OPTION = 'mh_fw_noten_last_run';

	/**
	 * Einmal-Ereignis: Einladungen eines frisch gestarteten Falls versenden. Sicherheitsnetz,
	 * falls der Versand direkt nach dem Start abbricht (siehe send_pending_invitations()).
	 */
	public const INVITE_HOOK = 'mh_fw_noten_send_invitations';

	/** Nach so vielen Sekunden gilt eine Versandsperre als verwaist (Prozess abgebrochen). */
	private const INVITE_LOCK_TTL = 300;

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

		$stats = [ 'reminded' => 0, 'escalated' => 0, 'skipped' => 0, 'invited' => 0, 'failed' => 0 ];

		foreach ( $this->case_repo->get_all_cases( [ 'status' => 'offen' ] ) as $case ) {
			$case_id = (int) $case['id'];

			foreach ( $case['form_data']['items'] ?? [] as $item ) {
				if ( 'erledigt' === ( $item['status'] ?? 'offen' ) ) {
					continue;
				}

				$idx = (int) $item['idx'];

				// Maßgeblich ist die letzte Kontaktaufnahme, nicht der Fallbeginn:
				// so wird nach einer Erinnerung wieder das volle Intervall gewartet.
				$last = $item['last_reminder_at'] ?? $item['notified_at'] ?? null;
				if ( null === $last ) {
					// Noch nie erfolgreich benachrichtigt. Ist beim Start die Einladung
					// gescheitert, wird sie hier nachgeholt - sonst bliebe das Fach für
					// immer liegen, weil es ohne Erstkontakt nie eine Erinnerung gäbe.
					if ( '' !== (string) ( $item['mail_error'] ?? '' ) ) {
						$this->retry_invitation( $case, $item ) ? $stats['invited']++ : $stats['failed']++;
					} else {
						$stats['skipped']++;
					}
					continue;
				}

				$last_ts = strtotime( (string) $last );
				if ( false === $last_ts || ( $now - $last_ts ) < $threshold ) {
					continue;
				}

				$recipient = $this->account_repo->resolve_recipient( (string) $item['teacher_kuerzel'] );

				if ( null === $recipient ) {
					$stats['skipped']++;
				} else {
					$link = $this->entry_link( $case_id, $idx );
					$sent = $this->mail->send_reminder(
						$recipient['email'],
						$recipient['name'],
						$case,
						$item,
						$link,
						(int) ( $item['reminder_count'] ?? 0 ) + 1
					);
					if ( $sent ) {
						$this->case_repo->mark_notified( $case_id, $idx, true, $recipient['email'] );
						$stats['reminded']++;
					} else {
						// Kein mark_notified: die letzte Kontaktaufnahme bleibt alt, der
						// nächste Lauf versucht es also erneut.
						$this->case_repo->mark_mail_failed( $case_id, $idx, $this->mail->get_last_error() );
						$stats['failed']++;
						continue;
					}
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

		// Für die Admin-Übersicht: läuft der Cron überhaupt, und mit welchem Ergebnis?
		update_option( self::LAST_RUN_OPTION, [ 'at' => current_time( 'mysql' ), 'stats' => $stats ], false );

		return $stats;
	}

	/**
	 * Verschickt alle noch ausstehenden Einladungen eines Falls.
	 *
	 * Läuft direkt nach dem Start im Hintergrund (nach der Weiterleitung) und zusätzlich
	 * als WP-Cron-Einmalereignis, falls jener Prozess abbricht. Beide können gleichzeitig
	 * laufen - eine Sperre pro Fall verhindert, dass eine Lehrkraft zwei Einladungen bekommt.
	 * Eingeladen wird nur, wer noch nie benachrichtigt wurde; gescheiterte Versuche
	 * landen als mail_error am Fach und werden vom täglichen Lauf erneut versucht.
	 *
	 * @return int Anzahl erfolgreich versendeter Einladungen.
	 */
	public function send_pending_invitations( int $case_id ): int {
		$lock  = 'mh_fw_invite_lock_' . $case_id;
		$since = (int) get_option( $lock, 0 );

		if ( $since > 0 && ( time() - $since ) < self::INVITE_LOCK_TTL ) {
			// Läuft gerade woanders. Später noch einmal nachsehen, statt doppelt zu senden.
			wp_schedule_single_event( time() + self::INVITE_LOCK_TTL, self::INVITE_HOOK, [ $case_id ] );
			return 0;
		}
		if ( $since > 0 ) {
			delete_option( $lock ); // verwaist: der sperrende Prozess ist abgebrochen
		}
		// add_option() schlägt fehl, wenn die Option schon existiert - das ist die Sperre.
		if ( ! add_option( $lock, time(), '', false ) ) {
			return 0;
		}

		$sent = 0;
		try {
			$case = $this->case_repo->get_by_id( $case_id );
			if ( null === $case || 'offen' !== ( $case['status'] ?? '' ) ) {
				return 0;
			}

			foreach ( $case['form_data']['items'] ?? [] as $item ) {
				if ( '1' !== (string) ( $item['collect'] ?? '0' )
					|| 'erledigt' === ( $item['status'] ?? 'offen' )
					|| ! empty( $item['notified_at'] ) ) {
					continue;
				}
				if ( $this->retry_invitation( $case, $item ) ) {
					$sent++;
				}
			}
		} finally {
			delete_option( $lock );
		}

		return $sent;
	}

	/**
	 * Holt eine beim Start gescheiterte Einladung nach.
	 */
	private function retry_invitation( array $case, array $item ): bool {
		$case_id   = (int) $case['id'];
		$idx       = (int) $item['idx'];
		$recipient = $this->account_repo->resolve_recipient( (string) $item['teacher_kuerzel'] );
		if ( null === $recipient ) {
			$this->case_repo->mark_mail_failed( $case_id, $idx, 'Keine Mailadresse für ' . $item['teacher_kuerzel'] . ' hinterlegt.' );
			return false;
		}

		$sent = $this->mail->send_invitation(
			$recipient['email'],
			$recipient['name'],
			$case,
			$item,
			$this->entry_link( $case_id, $idx )
		);
		if ( $sent ) {
			$this->case_repo->mark_notified( $case_id, $idx, false, $recipient['email'] );
		} else {
			$this->case_repo->mark_mail_failed( $case_id, $idx, $this->mail->get_last_error() );
		}

		return $sent;
	}

	/**
	 * Letzter Cron-Lauf, oder null, wenn er noch nie lief.
	 *
	 * @return array{at:string, stats:array}|null
	 */
	public function get_last_run(): ?array {
		$run = get_option( self::LAST_RUN_OPTION, null );

		return is_array( $run ) ? $run : null;
	}

	public function get_interval_days(): int {
		return $this->interval_days();
	}

	public function get_escalate_after(): int {
		return $this->escalate_after();
	}

	private function escalate( array $case, array $item ): bool {
		$owner_id = (int) ( $case['form_data']['owner_user_id'] ?? $case['user_id'] ?? 0 );
		// Hauptadresse aus der Lehrer-Zuordnung vor der Konto-Adresse, wie bei den Fachlehrkräften.
		$to       = $this->account_repo->resolve_email_for_user( $owner_id );
		if ( '' === $to ) {
			return false;
		}

		return $this->mail->send_escalation(
			$to,
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
