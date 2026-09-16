<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Mail_Service
 *
 * Dünne Hülle um wp_mail() für die Noteneinsammlung.
 *
 * Grundsatz: In keiner Mail steht eine Note. Schülername und Klasse müssen enthalten
 * sein, sonst kann die Lehrkraft die Anfrage nicht zuordnen — mehr aber auch nicht.
 */
class Mail_Service {

	/** @var string[] Zustellfehler dieses Requests, für die Rückmeldung im UI. */
	private array $failures = [];

	public function __construct() {
		// wp_mail() liefert bei manchen Mailern true zurück und scheitert erst danach.
		// Ohne diesen Hook bliebe ein Zustellfehler vollständig unsichtbar.
		add_action( 'wp_mail_failed', function ( $error ): void {
			$this->failures[] = is_wp_error( $error ) ? $error->get_error_message() : 'Unbekannter Mailfehler';
			error_log( '[mh_form_workflows] wp_mail fehlgeschlagen: ' . end( $this->failures ) );
		} );
	}

	/** @return string[] */
	public function get_failures(): array {
		return $this->failures;
	}

	/**
	 * Einladung an eine Fachlehrkraft, ihre Note einzutragen.
	 */
	public function send_invitation( string $to, string $teacher_name, array $case, array $item, string $link ): bool {
		$subject = sprintf(
			'Noteneingabe %s – %s, %s',
			$item['subject'],
			$case['form_data']['lastname'] ?? '',
			$case['form_data']['firstname'] ?? ''
		);

		$body = $this->wrap(
			'Noteneingabe für eine Ausschulung',
			sprintf(
				'<p>Hallo %s,</p>
				 <p>für die Ausschulung von <strong>%s, %s</strong> (Klasse %s) wird deine Note im Fach
				 <strong>%s</strong> benötigt.</p>
				 <p>Bitte trage sie über den folgenden Link ein:</p>
				 <p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;">Note jetzt eintragen</a></p>
				 <p style="font-size:12px;color:#666;">Falls der Button nicht funktioniert: %s</p>',
				esc_html( $teacher_name ),
				esc_html( (string) ( $case['form_data']['lastname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['firstname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['class_name'] ?? '' ) ),
				esc_html( (string) $item['subject'] ),
				esc_url( $link ),
				esc_html( $link )
			)
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Erinnerung an eine überfällige Note.
	 */
	public function send_reminder( string $to, string $teacher_name, array $case, array $item, string $link, int $reminder_no ): bool {
		$subject = sprintf(
			'Erinnerung: Noteneingabe %s – %s, %s',
			$item['subject'],
			$case['form_data']['lastname'] ?? '',
			$case['form_data']['firstname'] ?? ''
		);

		$body = $this->wrap(
			'Erinnerung: Noteneingabe steht noch aus',
			sprintf(
				'<p>Hallo %s,</p>
				 <p>für die Ausschulung von <strong>%s, %s</strong> (Klasse %s) fehlt weiterhin deine Note
				 im Fach <strong>%s</strong>. Dies ist Erinnerung Nr. %d.</p>
				 <p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;">Note jetzt eintragen</a></p>
				 <p style="font-size:12px;color:#666;">Falls der Button nicht funktioniert: %s</p>',
				esc_html( $teacher_name ),
				esc_html( (string) ( $case['form_data']['lastname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['firstname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['class_name'] ?? '' ) ),
				esc_html( (string) $item['subject'] ),
				$reminder_no,
				esc_url( $link ),
				esc_html( $link )
			)
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Eskalation an den Klassenlehrer: eine Lehrkraft reagiert nicht.
	 */
	public function send_escalation( string $to, array $case, array $item, string $teacher_name, string $link ): bool {
		$subject = sprintf(
			'Noteneingabe überfällig: %s – %s, %s',
			$item['subject'],
			$case['form_data']['lastname'] ?? '',
			$case['form_data']['firstname'] ?? ''
		);

		$body = $this->wrap(
			'Eine Noteneingabe bleibt aus',
			sprintf(
				'<p>Für die Ausschulung von <strong>%s, %s</strong> (Klasse %s) fehlt die Note im Fach
				 <strong>%s</strong> von <strong>%s</strong> trotz %d Erinnerungen.</p>
				 <p>Du kannst die Note im Fall selbst nachtragen:</p>
				 <p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;">Zur Ausschulung</a></p>',
				esc_html( (string) ( $case['form_data']['lastname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['firstname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['class_name'] ?? '' ) ),
				esc_html( (string) $item['subject'] ),
				esc_html( $teacher_name ),
				(int) ( $item['reminder_count'] ?? 0 ),
				esc_url( $link )
			)
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Abschluss an den Klassenlehrer: alle Noten liegen vor.
	 */
	public function send_completion( string $to, array $case, string $link ): bool {
		$subject = sprintf(
			'Noten vollständig – Ausschulung %s, %s',
			$case['form_data']['lastname'] ?? '',
			$case['form_data']['firstname'] ?? ''
		);

		$body = $this->wrap(
			'Alle Noten liegen vor',
			sprintf(
				'<p>Für die Ausschulung von <strong>%s, %s</strong> (Klasse %s) sind alle Noten eingetragen.</p>
				 <p>Das Formular kann jetzt gedruckt und der Abteilungsleitung vorgelegt werden:</p>
				 <p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;">Zur Ausschulung</a></p>',
				esc_html( (string) ( $case['form_data']['lastname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['firstname'] ?? '' ) ),
				esc_html( (string) ( $case['form_data']['class_name'] ?? '' ) ),
				esc_url( $link )
			)
		);

		return $this->send( $to, $subject, $body );
	}

	private function send( string $to, string $subject, string $body ): bool {
		if ( '' === $to || ! is_email( $to ) ) {
			$this->failures[] = 'Ungültige oder leere Empfängeradresse: ' . $to;
			return false;
		}

		return wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	private function wrap( string $headline, string $content ): string {
		return sprintf(
			'<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;max-width:600px;">
				<h2 style="color:#0073aa;font-size:18px;">%s</h2>
				%s
				<hr style="border:none;border-top:1px solid #ddd;margin:25px 0 10px;">
				<p style="font-size:11px;color:#888;">Diese Nachricht wurde automatisch vom Formular-Workflow der Schule erzeugt.</p>
			</div>',
			esc_html( $headline ),
			$content
		);
	}
}
