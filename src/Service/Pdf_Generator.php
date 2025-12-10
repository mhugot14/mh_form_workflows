<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Service;

// WICHTIG: Diese Zeilen importieren die PDF Bibliothek
use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf_Generator {

	/**
	 * Generiert das PDF aus HTML und sendet es an den Browser.
	 *
	 * @param int    $entry_id Die ID des Eintrags (für Dateinamen).
	 * @param string $html     Der vollständige HTML Code für das PDF.
	 * @param string $filename Basis-Dateiname ohne Endung.
	 * @return void
	 */
	public function generate_and_stream( int $entry_id, string $html, string $filename = 'document' ): void {
		
		// 1. Optionen setzen
		// Hier tritt dein Fehler auf, wenn "use Dompdf\Options;" oben fehlt!
		$options = new Options();
		$options->set( 'defaultFont', 'Helvetica' );
		$options->set( 'isRemoteEnabled', true ); // Erlaubt Bilder

		// 2. Dompdf instanziieren
		$dompdf = new Dompdf( $options );

		// 3. HTML laden
		$dompdf->loadHtml( $html );

		// 4. Papierformat
		$dompdf->setPaper( 'A4', 'portrait' );

		// 5. Rendern
		$dompdf->render();

		// 6. Output streamen (Dateiname bauen)
		$final_filename = sprintf( '%s_%d.pdf', $filename, $entry_id );
		
		// False = Öffnen im Browser, True = Download erzwingen
		$dompdf->stream( $final_filename, [ 'Attachment' => false ] );
	}
}