<?php
/**
 * View: PDF „Anmeldung zum Nachschreibtermin“
 *
 * Seite 1 entspricht der Papiervorlage der jeweiligen Terminart (Meldung mit Tabelle,
 * Spalte „Unterlagen vollständig“ und Feld „Nicht ausfüllen!“ für die Aufsicht).
 * Ab Seite 2 je Schüler*in ein ausgefülltes Deckblatt-Etikett mit den Angaben, die laut
 * Vorlage auf dem ersten Blatt der Aufgabenstellung stehen müssen.
 *
 * Vom Nachschreib_Controller bereitgestellt:
 * @var array  $data      form_data der Anmeldung
 * @var int    $entry_id
 * @var array  $def       Katalogeintrag der Terminart
 * @var array  $termin    Slot (Momentaufnahme bei der Anmeldung)
 * @var string $logo_src  data:-URI des Schullogos oder ''
 * @var string $signer    Unterschriftszeile „Vorname Nachname (KÜR)“
 * @var Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog $katalog
 */

use Mh\FormWorkflows\Service\Nachschreib_Termin_Katalog;

if ( ! defined( 'ABSPATH' ) ) exit;

$h    = static fn( $v ): string => htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
$x    = '<span class="sym">&#9746;</span>';
$o    = '<span class="sym">&#9744;</span>';
$rows = is_array( $data['rows'] ?? null ) ? array_values( $data['rows'] ) : [];

$termin_label = (string) ( $termin['label'] ?? '' );
$zeit         = (string) ( $termin['zeit'] ?? Nachschreib_Termin_Katalog::zeit_label( (string) ( $def['zeit_von'] ?? '' ), (string) ( $def['zeit_bis'] ?? '' ) ) );
$raum         = (string) ( $termin['raum'] ?? ( $def['raum'] ?? '' ) );
$hinweis      = (string) ( $termin['hinweis'] ?? '' );
// Vom Controller: Name und Kürzel aus derselben Quelle (siehe signer_line()).
$teacher_line = (string) ( $signer ?? '' );
$print_rows   = max( 5, count( $rows ) );
?>
<html>
<head>
<meta charset="utf-8">
<style>
	@page { margin: 1.1cm 1.4cm 1.4cm 1.4cm; }
	body { font-family: Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.3; color: #000; }
	.sym { font-family: "DejaVu Sans", sans-serif; }
	table { width: 100%; border-collapse: collapse; }
	td, th { vertical-align: top; }

	.head td { border: none; padding: 0; }
	.title { font-size: 15pt; font-weight: bold; color: #004f9f; margin: 0 0 3px; }
	.subtitle { font-size: 11pt; font-weight: bold; margin: 0; }
	.subtitle small { font-weight: normal; font-size: 9pt; }

	.rules { margin: 10px 0 10px; border: 1px solid #9aa9bb; background: #f3f6fa; padding: 6px 10px; font-size: 8.3pt; }
	.rules ul { margin: 0; padding-left: 14px; }
	.rules li { margin: 1px 0; }

	.grid td, .grid th { border: 1px solid #000; padding: 4px 5px; }
	.grid th { background: #dfe7f1; font-size: 8pt; text-align: left; font-weight: bold; vertical-align: middle; }
	.termin td { vertical-align: middle; }
	.termin .lbl { width: 24%; font-weight: bold; background: #dfe7f1; }
	.termin .big { font-size: 12pt; font-weight: bold; }
	.termin .office { width: 17%; background: #e6e6e6; font-size: 7.5pt; color: #444; text-align: center; }
	.deadline { margin: 5px 0 12px; font-size: 9pt; }

	.students td { height: 26px; vertical-align: middle; font-size: 9pt; }
	.students .nr { width: 5%; text-align: center; }
	.students .chk { width: 6%; text-align: center; background: #f2f2f2; }
	.students .center { text-align: center; }

	.remark { margin-top: 8px; border: 1px solid #000; padding: 5px 7px; font-size: 9pt; }
	.confirm { margin-top: 14px; }
	.sign td { border: none; padding: 0; }
	.sign .line { border-bottom: 1px solid #000; height: 32px; vertical-align: bottom; padding-bottom: 2px; }
	.sign .cap { font-size: 7.5pt; color: #333; padding-top: 2px; }

	.page-break { page-break-before: always; }
	.labels-intro { font-size: 9pt; margin: 4px 0 10px; }
	.labels { table-layout: fixed; }
	.labels > tbody > tr > td { width: 50%; padding: 6px; border: 1px dashed #777; }
	.labels tr { page-break-inside: avoid; }
	.label-head { font-weight: bold; font-size: 9pt; color: #004f9f; border-bottom: 1px solid #004f9f; padding-bottom: 2px; margin-bottom: 4px; }
	.label-tbl td { border: none; padding: 2px 3px; font-size: 9.5pt; }
	.label-tbl .k { width: 36%; color: #333; font-size: 8.5pt; }
	.label-tbl .v { font-weight: bold; }

	#footer { position: fixed; bottom: -0.9cm; left: 0; right: 0; height: 14px; font-size: 7.5pt; color: #666;
		border-top: 1px solid #ccc; padding-top: 3px; }
	#footer .r { float: right; }
</style>
</head>
<body>

<div id="footer">
	<span class="r">Anmeldung Nr. <?= (int) $entry_id ?> · erstellt <?= $h( date_i18n( 'd.m.Y H:i' ) ) ?></span>
	Ludwig-Erhard-Berufskolleg Münster · Nachschreibtermin
</div>

<!-- Kopf -->
<table class="head">
	<tr>
		<td>
			<div class="title">Anmeldung zum Nachschreibtermin</div>
			<p class="subtitle"><?= $h( $def['pdf_titel'] ?? '' ) ?><br><small>(<?= $h( $def['pdf_zusatz'] ?? '' ) ?>)</small></p>
		</td>
		<td style="width:120px; text-align:right;">
			<?php if ( '' !== $logo_src ) : ?>
				<img src="<?= $logo_src ?>" style="width:110px;" alt="LEBK">
			<?php endif; ?>
		</td>
	</tr>
</table>

<!-- Regeln der Terminart -->
<div class="rules">
	<ul>
		<?php foreach ( (array) ( $def['hinweise'] ?? [] ) as $line ) : ?>
			<li><?= $h( $line ) ?></li>
		<?php endforeach; ?>
		<li>Eintreffen der Schüler*innen: <?= $h( $def['eintreffen'] ?? '' ) ?>.</li>
		<?php foreach ( Nachschreib_Termin_Katalog::ALLGEMEINE_HINWEISE as $line ) : ?>
			<li><?= $h( $line ) ?></li>
		<?php endforeach; ?>
	</ul>
</div>

<!-- Termin -->
<table class="grid termin">
	<tr>
		<td class="lbl">Tag und Datum des gewünschten Termins</td>
		<td>
			<span class="big"><?= $h( $termin_label ) ?></span><br>
			<?= $h( $zeit ) ?> · Raum: <?= $h( $raum ) ?><?= '' !== $hinweis ? ' · ' . $h( $hinweis ) : '' ?>
		</td>
		<td class="office">Nicht ausfüllen!<br><br><br></td>
	</tr>
</table>
<div class="deadline">
	<strong>Abgabe</strong> dieser Meldung mit den Arbeitsunterlagen bis
	<strong><?= $h( $termin['frist_label'] ?? '' ) ?></strong> im Postfach
	<strong>„<?= $h( $termin['postfach'] ?? '' ) ?>“</strong> (<?= $h( Nachschreib_Termin_Katalog::POSTFACH_ORT ) ?>).
</div>

<!-- Schüler*innen -->
<table class="grid students">
	<thead>
		<tr>
			<th class="nr" rowspan="2" style="text-align:center;">lfd.<br>Nr.</th>
			<th rowspan="2" style="width:27%;">Name, Vorname</th>
			<th rowspan="2" style="width:9%;">Klasse</th>
			<th rowspan="2" style="width:10%;">Fach</th>
			<th rowspan="2" style="width:10%;">Fachlehr&shy;kraft<br>(Kürzel)</th>
			<th rowspan="2" style="width:7%;">Dauer<br>in Min.</th>
			<th rowspan="2">Hilfsmittel</th>
			<th colspan="2" style="text-align:center; background:#e6e6e6;">Unterlagen vollständig</th>
		</tr>
		<tr>
			<th class="chk" style="text-align:center; background:#e6e6e6;">ja</th>
			<th class="chk" style="text-align:center; background:#e6e6e6;">nein</th>
		</tr>
	</thead>
	<tbody>
		<?php for ( $i = 0; $i < $print_rows; $i++ ) :
			$r = $rows[ $i ] ?? null; ?>
			<tr>
				<td class="nr"><?= $i + 1 ?>.</td>
				<td><?= $r ? '<strong>' . $h( mb_strtoupper( (string) $r['lastname'] ) ) . '</strong>, ' . $h( $r['firstname'] ) : '' ?></td>
				<td><?= $r ? $h( $r['class_name'] ) : '' ?></td>
				<td><?= $r ? $h( $r['subject'] ) : '' ?></td>
				<td class="center"><?= $r ? $h( $r['teacher'] ) : '' ?></td>
				<td class="center"><?= $r ? (int) $r['duration'] : '' ?></td>
				<td><?= $r ? $h( $r['aids'] ) : '' ?></td>
				<td class="chk"></td>
				<td class="chk"></td>
			</tr>
		<?php endfor; ?>
	</tbody>
</table>

<?php if ( ! empty( $data['remark'] ) ) : ?>
	<div class="remark"><strong>Bemerkung:</strong> <?= nl2br( $h( $data['remark'] ) ) ?></div>
<?php endif; ?>

<!-- Bestätigung und Unterschrift -->
<div class="confirm">
	<?= ( '1' === ( $data['confirm_berechtigung'] ?? '' ) ) ? $x : $o ?>
	Ich habe überprüft, dass die Berechtigung der o. g. Schüler*innen zur Teilnahme am Nachschreibtermin gegeben ist.<br>
	<?= ( '1' === ( $data['confirm_info'] ?? '' ) ) ? $x : $o ?>
	Weitere Nachschreibverpflichtungen sind geprüft; die Schüler*innen werden über Termin, Raum und Ausweispflicht informiert.
</div>

<table class="sign" style="margin-top:16px;">
	<tr>
		<td class="line" style="width:30%;">Münster, <?= $h( date_i18n( 'd.m.Y' ) ) ?></td>
		<td style="width:6%;"></td>
		<td class="line"></td>
	</tr>
	<tr>
		<td class="cap">Datum</td>
		<td></td>
		<td class="cap">Unterschrift der Fachlehrkraft<?= '' !== $teacher_line ? ' – ' . $h( $teacher_line ) : '' ?></td>
	</tr>
</table>

<?php if ( ! empty( $rows ) ) : ?>
	<!-- Deckblätter -->
	<div class="page-break"></div>
	<div class="title" style="font-size:13pt;">Deckblätter für die Aufgabenstellungen</div>
	<div class="labels-intro">
		Diese Angaben müssen auf dem ersten Blatt der Aufgabenstellung stehen. Etikett ausschneiden und aufkleben –
		oder die Seite der jeweiligen Aufgabenstellung voranheften.
	</div>
	<table class="labels">
		<tbody>
		<?php foreach ( array_chunk( $rows, 2 ) as $pair ) : ?>
			<tr>
				<?php for ( $k = 0; $k < 2; $k++ ) :
					$r = $pair[ $k ] ?? null; ?>
					<td style="<?= $r ? '' : 'border:none;' ?>">
						<?php if ( $r ) : ?>
							<div class="label-head">Nachschreibtermin <?= $h( $termin_label ) ?></div>
							<table class="label-tbl">
								<tr><td class="k">Fach</td><td class="v"><?= $h( $r['subject'] ) ?></td></tr>
								<tr><td class="k">Name der Lernenden</td><td class="v"><?= $h( $r['lastname'] ) ?>, <?= $h( $r['firstname'] ) ?></td></tr>
								<tr><td class="k">Fachlehrkraft</td><td class="v"><?= $h( $r['teacher'] ) ?></td></tr>
								<tr><td class="k">Klasse</td><td class="v"><?= $h( $r['class_name'] ) ?></td></tr>
								<tr><td class="k">Arbeitszeit</td><td class="v"><?= (int) $r['duration'] ?> Minuten</td></tr>
								<tr><td class="k">Hilfsmittel</td><td class="v"><?= $h( $r['aids'] ) ?></td></tr>
							</table>
						<?php endif; ?>
					</td>
				<?php endfor; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

</body>
</html>
