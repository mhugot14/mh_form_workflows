<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

/**
 * Class Noten_Feature
 *
 * Ein-/Ausschalter für die digitale Noteneinsammlung.
 *
 * Die Abschaltung ist bewusst WEICH: "aus" heisst, dass keine neue Einsammlung mehr
 * gestartet werden kann. Bereits laufende Fälle bleiben vollständig bedienbar, und der
 * Erinnerungs-Cron läuft für sie weiter.
 *
 * Der Grund ist handfest: Die eingesammelten Noten wandern erst beim Abschluss eines
 * Falls zurück in die Abmeldung (Noten_Controller::write_back_to_submission()). Würde
 * man das Verfahren hart abschalten, klickten Fachlehrkräfte mit einer Einladungsmail
 * im Postfach ins Leere, und die bereits eingetragenen Noten gingen verloren.
 */
final class Noten_Feature {

	public const OPTION_KEY = 'noten_enabled';

	/**
	 * Dürfen neue Einsammlungen gestartet werden?
	 *
	 * Fehlt der Schlüssel, gilt das Verfahren als eingeschaltet — so verhalten sich
	 * bestehende Installationen nach dem Update wie vorher. Abgeschaltet ist es nur,
	 * wenn das ausdrücklich gespeichert wurde.
	 */
	public static function is_enabled(): bool {
		$options = get_option( 'mh_fw_settings', [] );

		if ( ! isset( $options[ self::OPTION_KEY ] ) ) {
			return true;
		}

		return '1' === (string) $options[ self::OPTION_KEY ];
	}
}
