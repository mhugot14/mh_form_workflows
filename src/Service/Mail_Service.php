<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Mail_Service
 *
 * Dünne Hülle um wp_mail() für die Noteneinsammlung.
 *
 * Grundsatz: In keiner Mail an eine Fachlehrkraft steht eine Note. Schülername und
 * Klasse müssen enthalten sein, sonst kann die Lehrkraft die Anfrage nicht zuordnen —
 * mehr aber auch nicht. Die Klassenleitung erfährt Noten ohnehin im Fall selbst; auch
 * ihre Mails nennen deshalb nur, DASS eine Note eingetragen wurde.
 *
 * Zum Layout: Mailprogramme (vor allem Outlook) ignorieren Standard-Abstände von <p>
 * und das Padding von Inline-Links. Absätze bekommen deshalb feste Abstände, Knöpfe
 * werden als Tabellenzelle gebaut ("bulletproof button"), sonst liegt der Knopf über
 * dem Text darunter.
 */
class Mail_Service {

	private const FONT = 'font-family:Arial,Helvetica,sans-serif;';

	/** Vorgabe für den Absendernamen, solange in den Einstellungen nichts gespeichert ist. */
	public const DEFAULT_FROM_NAME = 'LEBK Schild';

	/**
	 * Absendername aller Mails der Website (Einstellungen → „Absendername für E-Mails“).
	 * Leer gespeichert heisst: WordPress-Standard. Angewendet wird er zentral über den
	 * Filter wp_mail_from_name in Plugin_Bootstrap - damit heissen auch Passwort-Mails
	 * und Mails anderer Plugins so.
	 */
	public static function from_name(): string {
		$options = get_option( 'mh_fw_settings', [] );
		if ( ! isset( $options['mail_from_name'] ) ) {
			return self::DEFAULT_FROM_NAME;
		}

		return trim( sanitize_text_field( (string) $options['mail_from_name'] ) );
	}

	/** @var string[] Zustellfehler dieses Requests, für die Rückmeldung im UI. */
	private array $failures = [];

	/** Fehlertext des letzten Versands, leer bei Erfolg. */
	private string $last_error = '';

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
	 * Warum der letzte Versand scheiterte. Wird am Fach bzw. Fall gespeichert, damit
	 * die Admin-Übersicht zeigen kann, wer eine Mail nicht bekommen hat.
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * Einladung an eine Fachlehrkraft, ihre Note einzutragen.
	 */
	public function send_invitation( string $to, string $teacher_name, array $case, array $item, string $link ): bool {
		$subject = sprintf( 'Noteneingabe %s – %s', $item['subject'], $this->student( $case ) );

		$body = $this->wrap(
			'Noteneingabe für eine Ausschulung',
			$this->p( 'Hallo ' . esc_html( $teacher_name ) . ',' )
			. $this->p( sprintf(
				'für die Ausschulung von <strong>%s</strong> (Klasse %s) wird deine Note im Fach <strong>%s</strong> benötigt.',
				esc_html( $this->student( $case ) ),
				esc_html( $this->klasse( $case ) ),
				esc_html( (string) $item['subject'] )
			) )
			. $this->p( 'Bitte trage sie über den folgenden Knopf ein:' )
			. $this->button( 'Note jetzt eintragen', $link )
			. $this->fallback_link( $link )
			. $this->footer()
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Erinnerung an eine überfällige Note.
	 */
	public function send_reminder( string $to, string $teacher_name, array $case, array $item, string $link, int $reminder_no ): bool {
		$subject = sprintf( 'Erinnerung: Noteneingabe %s – %s', $item['subject'], $this->student( $case ) );

		$body = $this->wrap(
			'Erinnerung: Noteneingabe steht noch aus',
			$this->p( 'Hallo ' . esc_html( $teacher_name ) . ',' )
			. $this->p( sprintf(
				'für die Ausschulung von <strong>%s</strong> (Klasse %s) fehlt weiterhin deine Note im Fach <strong>%s</strong>. Dies ist Erinnerung Nr. %d.',
				esc_html( $this->student( $case ) ),
				esc_html( $this->klasse( $case ) ),
				esc_html( (string) $item['subject'] ),
				$reminder_no
			) )
			. $this->button( 'Note jetzt eintragen', $link )
			. $this->fallback_link( $link )
			. $this->footer()
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Eskalation an die Klassenleitung: eine Lehrkraft reagiert nicht.
	 */
	public function send_escalation( string $to, array $case, array $item, string $teacher_name, string $link ): bool {
		$subject = sprintf( 'Noteneingabe überfällig: %s – %s', $item['subject'], $this->student( $case ) );

		$body = $this->wrap(
			'Eine Noteneingabe bleibt aus',
			$this->p( sprintf(
				'Für die Ausschulung von <strong>%s</strong> (Klasse %s) fehlt die Note im Fach <strong>%s</strong> von <strong>%s</strong> trotz %d Erinnerungen.',
				esc_html( $this->student( $case ) ),
				esc_html( $this->klasse( $case ) ),
				esc_html( (string) $item['subject'] ),
				esc_html( $teacher_name ),
				(int) ( $item['reminder_count'] ?? 0 )
			) )
			. $this->p( 'Du kannst die Note im Fall selbst nachtragen – etwa wenn sie dir im Lehrerzimmer genannt wurde – oder die Einsammlung beenden und die Note im PDF von Hand ergänzen.' )
			. $this->button( 'Zur Noteneinsammlung', $link )
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Zwischenstand an die Klassenleitung: eine Fachlehrkraft hat ihre Note eingetragen.
	 * Die Note selbst steht bewusst nicht in der Mail (siehe Klassenkommentar).
	 */
	public function send_grade_entered( string $to, array $case, array $item, string $teacher_name, int $open_count, string $link ): bool {
		$subject = sprintf( 'Note eingetragen: %s – %s', $item['subject'], $this->student( $case ) );

		$rest = 1 === $open_count
			? 'Es fehlt noch <strong>1</strong> Note.'
			: 'Es fehlen noch <strong>' . $open_count . '</strong> Noten.';

		$body = $this->wrap(
			'Eine Note wurde eingetragen',
			$this->p( sprintf(
				'<strong>%s</strong> hat für die Ausschulung von <strong>%s</strong> (Klasse %s) die Note im Fach <strong>%s</strong> eingetragen.',
				esc_html( $teacher_name ),
				esc_html( $this->student( $case ) ),
				esc_html( $this->klasse( $case ) ),
				esc_html( (string) $item['subject'] )
			) )
			. $this->p( $rest . ' Sobald alle vorliegen, bekommst du eine eigene Nachricht.' )
			. $this->button( 'Stand ansehen', $link )
		);

		return $this->send( $to, $subject, $body );
	}

	/**
	 * Abschluss an die Klassenleitung: alle Noten liegen vor, das PDF kann gedruckt werden.
	 */
	public function send_completion( string $to, array $case, string $link ): bool {
		$subject = sprintf( 'Noten vollständig – PDF bereit: %s', $this->student( $case ) );

		$body = $this->wrap(
			'Alle Noten liegen vor',
			$this->p( sprintf(
				'Für die Ausschulung von <strong>%s</strong> (Klasse %s) sind alle Noten eingetragen und in die Abmeldung übernommen.',
				esc_html( $this->student( $case ) ),
				esc_html( $this->klasse( $case ) )
			) )
			. $this->p( 'Das fertige PDF kannst du jetzt herunterladen, drucken und der Abteilungsleitung vorlegen:' )
			. $this->button( 'Zum PDF', $link )
			. $this->fallback_link( $link )
		);

		return $this->send( $to, $subject, $body );
	}

	// ---------------------------------------------------------------
	// Bausteine
	// ---------------------------------------------------------------

	private function student( array $case ): string {
		return trim( ( $case['form_data']['lastname'] ?? '' ) . ', ' . ( $case['form_data']['firstname'] ?? '' ), ', ' );
	}

	private function klasse( array $case ): string {
		return (string) ( $case['form_data']['class_name'] ?? '' );
	}

	/** Absatz mit festen Abständen - Mailprogramme setzen sonst keine. $html muss escaped sein. */
	private function p( string $html, string $extra_style = '' ): string {
		return '<p style="' . self::FONT . 'margin:0 0 14px 0;font-size:14px;line-height:1.5;color:#222;' . $extra_style . '">' . $html . '</p>';
	}

	/** Knopf als Tabellenzelle: bleibt in Outlook ein Block und überdeckt keinen Text. */
	private function button( string $label, string $link ): string {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px 0;border-collapse:collapse;">'
			. '<tr><td bgcolor="#0073aa" style="background:#0073aa;border-radius:4px;">'
			. '<a href="' . esc_url( $link ) . '" style="' . self::FONT . 'display:inline-block;padding:11px 20px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:4px;">'
			. esc_html( $label )
			. '</a></td></tr></table>';
	}

	private function fallback_link( string $link ): string {
		return $this->p(
			'Falls der Knopf nicht funktioniert: <a href="' . esc_url( $link ) . '" style="color:#0073aa;word-break:break-all;">' . esc_html( $link ) . '</a>',
			'font-size:12px;color:#666;'
		);
	}

	/**
	 * Fussnote mit BETA-Hinweis und, falls eine Dashboard-Seite hinterlegt ist, einem
	 * Verweis darauf.
	 *
	 * Wer eine Note einträgt, will meistens wissen, ob noch mehr auf ihn wartet. Ohne
	 * den Verweis steht diese Information nur in weiteren Einzelmails.
	 */
	private function footer(): string {
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:8px;">'
			. '<tr><td style="border-top:1px solid #e0e0e0;padding-top:12px;">'
			. $this->p(
				'<strong>Hinweis:</strong> Die digitale Noteneingabe wird gerade erprobt (BETA). Wenn etwas nicht '
				. 'funktioniert, gib der Klassenleitung Bescheid &ndash; die Note kann auch auf dem üblichen Weg '
				. 'weitergegeben werden.',
				'font-size:12px;color:#666;'
			);

		$dashboard = $this->dashboard_link();
		if ( '' !== $dashboard ) {
			$html .= $this->p(
				'Eine Übersicht aller Noten, die auf dich warten: <a href="' . esc_url( $dashboard ) . '" style="color:#0073aa;">' . esc_html( $dashboard ) . '</a>',
				'font-size:12px;color:#666;'
			);
		}

		return $html . '</td></tr></table>';
	}

	/** Leerer String, wenn keine Dashboard-Seite hinterlegt ist — dann entfällt der Verweis. */
	private function dashboard_link(): string {
		$options = get_option( 'mh_fw_settings', [] );
		$page_id = (int) ( $options['page_id_mh_dashboard'] ?? 0 );

		if ( $page_id <= 0 ) {
			return '';
		}

		return (string) ( get_permalink( $page_id ) ?: '' );
	}

	private function send( string $to, string $subject, string $body ): bool {
		$this->last_error = '';

		if ( '' === $to || ! is_email( $to ) ) {
			$this->failures[] = 'Ungültige oder leere Empfängeradresse: ' . $to;
			$this->last_error = end( $this->failures );
			return false;
		}

		// wp_mail() kann true liefern, obwohl wp_mail_failed gefeuert hat (siehe
		// Konstruktor). Beides zählt deshalb als Fehlschlag.
		$failures_before = count( $this->failures );
		// Der Absendername kommt zentral aus den Einstellungen (siehe from_name()).
		$ok = wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

		if ( count( $this->failures ) > $failures_before ) {
			$this->last_error = (string) end( $this->failures );
			return false;
		}
		if ( ! $ok ) {
			$this->last_error = 'wp_mail() hat den Versand abgelehnt.';
			return false;
		}

		return true;
	}

	/** Rahmen als Tabelle mit fester Breite - das rendert in allen Mailprogrammen gleich. */
	private function wrap( string $headline, string $content ): string {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">'
			. '<tr><td style="padding:10px 0;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;max-width:600px;width:100%;">'
			. '<tr><td style="' . self::FONT . 'font-size:14px;color:#222;">'
			. '<h2 style="' . self::FONT . 'color:#0073aa;font-size:19px;margin:0 0 16px 0;line-height:1.3;">' . esc_html( $headline ) . '</h2>'
			. $content
			. '<p style="' . self::FONT . 'font-size:11px;color:#888;border-top:1px solid #ddd;padding-top:10px;margin:22px 0 0 0;">'
			. 'Diese Nachricht wurde automatisch vom Formular-Workflow der Schule erzeugt.</p>'
			. '</td></tr></table>'
			. '</td></tr></table>';
	}
}
