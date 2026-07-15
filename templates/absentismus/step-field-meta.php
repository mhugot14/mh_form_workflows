<?php
/**
 * Feld-Metadaten für die aufklappbare Detailansicht in fall-timeline.php:
 * deutsche Beschriftung je Feldname, Klartext-Übersetzung für codierte Werte
 * (Radio-Auswahlen) und die Liste der Checkbox-Felder (deren '1'/'0'-Wert als
 * Ja/Nein statt als Rohwert angezeigt werden soll — bewusst als Positivliste,
 * damit z. B. "fehlstunden_gesamt: 0" nicht fälschlich als "Nein" erscheint).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

return [
	'labels' => [
		'datum'                     => 'Datum',
		'uhrzeit_von'               => 'Uhrzeit von',
		'uhrzeit_bis'               => 'Uhrzeit bis',
		'ort'                       => 'Ort',
		'schulsozialarbeit'         => 'Schulsozialarbeit',
		'weitere'                   => 'Weitere Teilnehmer',
		'erziehungsberechtigte'     => 'Erziehungsberechtigte',
		'erziehungsberechtigte_1'   => 'Erziehungsberechtigte (1)',
		'erziehungsberechtigte_2'   => 'Erziehungsberechtigte (2)',
		'fehlstunden_gesamt'        => 'Fehlstunden gesamt',
		'fehlstunden_unentschuldigt' => 'davon unentschuldigt',
		'inhalte'                   => 'Inhalte des Gesprächs',
		'ergebnisse'                => 'Ergebnisse / Vereinbarungen',
		'ueberpruefung_am'          => 'Überprüfung der Vereinbarungen am',
		'einladung_datum'           => 'Datum der Einladung',
		'einladung_uhrzeit'         => 'Uhrzeit der Einladung',
		'versand_einladung'         => 'Versand der Einladung (Sekretariat)',
		'abteilungsleitung'         => 'Abteilungsleitung',
		'ankuendigung_attestpflicht' => 'Ankündigung Attestpflicht',
		'kontakt_sozialarbeit'      => 'Kontaktaufnahme Schulsozialarbeit',
		'bezirkssozialarbeiterin'   => 'Bezirkssozialarbeiterin',
		'telefon'                   => 'Telefon',
		'zufuehrung_geplant_am'     => 'Zuführung geplant am',
		'zufuehrung_erfolgreich'    => 'Zuführung erfolgreich',
		'zufuehrung_grund'         => 'Grund (falls nicht erfolgreich)',
		'ankuendigung_datum'        => 'Datum der Ankündigung',
		'paraphe_al'                => 'Abteilungsleitung informiert (Paraphe)',
		'grund_haeufige_verspaetungen' => 'Grund: häufige Verspätungen',
		'grund_vorzeitiges_beenden' => 'Grund: vorzeitiges Beenden des Schultages',
		'grund_nicht_zusammenhaengende_fehltage' => 'Grund: nicht zusammenhängende Fehltage',
		'grund_bestimmte_wochentage' => 'Grund: bestimmte Wochentage',
		'grund_sonstige'            => 'Grund: sonstige',
		'grund_sonstige_text'       => 'Sonstiger Grund (Freitext)',
		'weekday_mo'                => 'Montag',
		'weekday_di'                => 'Dienstag',
		'weekday_mi'                => 'Mittwoch',
		'weekday_do'                => 'Donnerstag',
		'weekday_fr'                => 'Freitag',
		'anlage_webuntis'           => 'Anlage: WebUntis-Auszug',
		'anlage_protokoll'          => 'Anlage: Protokoll der Gespräche',
		'versand_datum'             => 'Versanddatum (Sekretariat)',
		'versand_anhoerungsbogen'   => 'Versand Anhörungsbogen (Sekretariat)',
		'mail_bezirksregierung'     => 'Mail an Bezirksregierung versendet',
		'mail_bezirksregierung_datum' => 'Datum Mail an Bezirksregierung',
		'trigger'                   => 'Grund für die Teilkonferenz',
		'zeitraum_von'              => 'Zeitraum von',
		'zeitraum_bis'              => 'Zeitraum bis',
		'beschluss'                 => 'Beschluss der Teilkonferenz',
		'tage_am_stueck'            => 'Unentschuldigte Tage am Stück',
		'erinnerungsschreiben'      => 'Erinnerungsschreiben (ab 15. Tag)',
		'erinnerung_tage_seitdem'   => 'Fehlt seitdem (Tage am Stück)',
		'erinnerung_versand'        => 'Versand Erinnerungsschreiben',
		'ausschulungsschreiben'     => 'Ausschulungsschreiben',
		'ausschulung_versand'       => 'Versand der Ausschulung',
		'ausschulung_grund'         => 'Grund, warum nicht',
	],
	'value_labels' => [
		'ort'     => [ 'schule' => 'Schule', 'telefonat' => 'Telefonat' ],
		'trigger' => [ 'beendigung_53' => 'Mögliche Beendigung nach § 53 SchulG', 'hohe_fehlstunden' => 'Hohe unentschuldigte Fehlstunden' ],
		'beschluss' => [ 'androhung_entlassung' => 'Androhung der Entlassung', 'entlassung' => 'Entlassung' ],
		'zufuehrung_erfolgreich' => [ 'ja' => 'Ja', 'nein' => 'Nein' ],
	],
	'checkbox_fields' => [
		'ankuendigung_attestpflicht', 'kontakt_sozialarbeit',
		'grund_haeufige_verspaetungen', 'grund_vorzeitiges_beenden', 'grund_nicht_zusammenhaengende_fehltage',
		'grund_bestimmte_wochentage', 'grund_sonstige',
		'weekday_mo', 'weekday_di', 'weekday_mi', 'weekday_do', 'weekday_fr',
		'anlage_webuntis', 'anlage_protokoll', 'mail_bezirksregierung',
		'erinnerungsschreiben', 'ausschulungsschreiben',
	],
	// Fall-Stammdaten, die manche Schritte redundant in ihre eigenen Step-Daten
	// mitspeichern (z. B. Ordnungsamt) — im Fall-Header bereits sichtbar, daher
	// hier nicht nochmal anzeigen.
	'hidden_fields' => [ 'is_schulpflichtig' ],
];
