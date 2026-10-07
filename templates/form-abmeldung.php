<?php
/**
 * View: Schüler-Abmeldung (Intelligente Version mit WebUntis-Anbindung)
 */

// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';

// Lehrer-Name für Autofüllung
$current_user = wp_get_current_user();
$teacher_default = trim($current_user->first_name . ' ' . $current_user->last_name) ?: $current_user->display_name;

// Fächer-Vorbelegung aus Schild (Stundentafel + Kurse): darf niemals über bereits
// erfasste Zeilen laufen — weder im Bearbeiten-Modus noch nach einem Validierungs-Reload.
$has_existing_subjects = ! empty( $form_data['subjects'] );

// Grundgerüst bleibt bei 12 Zeilen; gespeicherte Formulare können mehr enthalten,
// weitere Zeilen hängt die Vorbelegung bei Bedarf per JS an.
$subject_row_count = max( 12, count( $form_data['subjects'] ?? [] ) );

// Marker im Noten-Dropdown fuer "Note per Mail anfragen".
$collect_marker = \Mh\FormWorkflows\Model\Form\Abmeldung_Student_Form::GRADE_COLLECT_MARKER;

// Protokoll-Modus. Rueckwaertskompatibel: aeltere Datensaetze kennen nur das
// Haekchen protocol_attached. Ein Altdatensatz OHNE Protokoll bekommt bewusst
// keine Vorauswahl - die Entscheidung soll bewusst getroffen werden, statt ihm
// stillschweigend eine Erklaerung unterzuschieben, die nie abgegeben wurde.
$protocol_mode = $form_data['protocol_mode'] ?? '';
if ( '' === $protocol_mode ) {
    if ( empty( $form_data ) ) {
        $protocol_mode = 'create'; // Neues Formular: bisheriger Normalfall
    } elseif ( isset( $form_data['protocol_attached'] ) && '1' === $form_data['protocol_attached'] ) {
        $protocol_mode = 'create';
    }
}

// Warnung extrahieren
$warning_msg = '';
if ( isset( $form_errors['date_autocorrect'] ) ) {
    $warning_msg = $form_errors['date_autocorrect'];
    unset( $form_errors['date_autocorrect'] );
}
?>

<style>
    /* CSS RESET & LAYOUT (Erhalten & Erweitert) */
    .mh-form-wrapper { max-width: 900px; margin: 0 auto; box-sizing: border-box; font-family: inherit; }
    .mh-form-wrapper * { box-sizing: border-box !important; float: none !important; position: static !important; }
    .mh-form-wrapper .mh-info-icon { position: relative !important; }
    .mh-form-wrapper .mh-info-icon:hover::after { position: absolute !important; }
    
    .mh-form-section { background: #f9f9f9; border: 1px solid #ccc; padding: 20px; margin-bottom: 25px; border-radius: 4px; width: 100% !important; display: block !important; }
    .mh-form-section h4 { margin-top: 0 !important; margin-bottom: 20px !important; border-bottom: 1px solid #ddd !important; padding-bottom: 10px; color: #333; }

    .mh-grid-row { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important; gap: 20px !important; margin-bottom: 15px !important; width: 100% !important; }
    .mh-grid-2 { grid-template-columns: 1fr 1fr !important; }
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr !important; }
    @media (max-width: 768px) { .mh-grid-2, .mh-grid-3 { grid-template-columns: 1fr !important; } }

    .mh-input-group { display: flex !important; flex-direction: column !important; width: 100% !important; margin: 0 !important; border: none !important; padding: 0 !important; }
    .mh-input-group label { display: block !important; width: 100% !important; margin: 0 0 5px 0 !important; font-weight: bold; line-height: 1.4 !important; height: auto !important; }
    
    .mh-input-group input, .mh-input-group select, .mh-input-group textarea {
        display: block !important; width: 100% !important; height: 40px !important; padding: 6px 12px !important; margin: 0 !important; border: 1px solid #aaa !important; background-color: #fff !important; border-radius: 4px !important; font-size: 15px !important;
    }
    .mh-input-group textarea { height: auto !important; }
    .mh-input-group input[readonly] { background-color: #e9e9e9 !important; color: #555 !important; cursor: not-allowed; }
    .mh-fake-input { display: flex !important; align-items: center; height: 40px; width: 100%; background: #e9e9e9; border: 1px solid #aaa; border-radius: 4px; padding: 0 10px; color: #555; }

    .radio-group { display: flex !important; flex-direction: row !important; align-items: flex-start !important; margin-bottom: 8px !important; gap: 10px !important; }
    .radio-group input { width: 18px !important; height: 18px !important; margin-top: 4px !important; flex-shrink: 0; }
    .radio-group label { font-weight: normal !important; margin: 0 !important; display: inline-block !important; }

    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; }
    .mh-warning-box { background: #fff8e5; border-left: 5px solid #e5a912; padding: 20px; margin-bottom: 30px; }
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }

    /* Inline-Prüfung: Fehler direkt am Feld statt in einem eigenen Fenster */
    .mh-form-wrapper .mh-error-field { box-shadow: 0 0 0 1px #d63638 !important; }
    .mh-form-wrapper .mh-group-error { border: 2px solid #d63638 !important; background-color: #fff5f5 !important; }
    .mh-form-wrapper .mh-field-error { display: block !important; margin: 5px 0 0 0 !important; color: #b32d2e !important; font-size: 0.85em !important; font-weight: bold !important; line-height: 1.4 !important; }
    .mh-form-wrapper .mh-field-error::before { content: "⚠ "; }
    .mh-form-wrapper .mh-field-warning { display: block !important; margin: 5px 0 0 0 !important; color: #8a6d3b !important; font-size: 0.85em !important; font-weight: bold !important; line-height: 1.4 !important; }
    .mh-validation-status { margin-top: 15px; padding: 12px 16px; border-radius: 4px; line-height: 1.45; }
    .mh-validation-status:empty { display: none !important; }
    .mh-validation-status.is-error { background: #fff5f5; border-left: 5px solid #d63638; color: #8a1f1f; }
    .mh-validation-status.is-success { background: #f0f8f0; border-left: 5px solid #46b450; color: #1e5e20; }
    .mh-validation-status.is-busy { background: #f0f6fb; border-left: 5px solid #0073aa; color: #1d3f5e; }
    .mh-validation-status ul { margin: 6px 0 0 20px !important; padding: 0 !important; }
    .mh-validation-status button { margin-top: 8px; }
    .btn-group button[disabled] { opacity: 0.6; cursor: wait !important; }
    .mh-date-hint { margin: -5px 0 15px 0; padding: 10px 14px; background: #fff8e5; border-left: 4px solid #e5a912; color: #6b4f12; font-size: 0.9em; line-height: 1.45; }
    .mh-form-wrapper .mh-input-group input.mh-date-corrected { border: 2px solid #e5a912 !important; background-color: #fff8e5 !important; color: #6b4f12 !important; }
    .mh-date-hint.is-loading { background: #f0f6fb; border-left-color: #0073aa; color: #1d3f5e; }
    
    .mh-sub-group { margin-left: 28px; padding: 15px; border-left: 3px solid #ddd; background: #fff; margin-bottom: 15px; margin-top: 5px; }
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    .mh-hidden { display: none !important; }
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 12px 24px !important; cursor: pointer; }
    
    .mh-info-icon { display: inline-block; width: 18px; height: 18px; background: #0073aa; color: #fff; border-radius: 50%; text-align: center; line-height: 18px; font-size: 12px; font-weight: bold; cursor: help; margin-left: 5px; }
    .mh-info-icon:hover::after { content: attr(data-tooltip); position: absolute; bottom: 25px; left: -100px; width: 250px; padding: 10px; background: #333; color: #fff; font-size: 12px; font-weight: normal; line-height: 1.4; border-radius: 4px; z-index: 9999; }
    
    .mh-form-wrapper input, .mh-form-wrapper select, .mh-form-wrapper label, .mh-form-wrapper span, .mh-form-wrapper div { text-transform: none !important; font-variant: normal !important; }
	
	/* Kompakte Notentabelle */
    .mh-subject-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px; /* Etwas kleinere Schrift für die Tabelle */
    }
    .mh-subject-table th {
        background: #eee;
        padding: 8px 5px;
        text-align: left;
        border: 1px solid #ccc;
    }
    .mh-subject-table td {
        padding: 4px;
        border: 1px solid #ccc;
        vertical-align: middle;
    }
    /* Zwinge die Inputs in der Tabelle klein zu sein */
    .mh-subject-table input[type="text"],
    .mh-subject-table input[type="number"],
    .mh-subject-table select {
        height: 32px !important;
        font-size: 13px !important;
        padding: 2px 8px !important;
        margin: 0 !important;
    }
    .mh-subject-table .mh-subj-remove {
        background: none !important; border: 1px solid transparent !important; color: #d63638 !important;
        cursor: pointer; line-height: 0; padding: 4px 6px !important; border-radius: 3px;
    }
    .mh-subject-table .mh-subj-remove svg { display: block; }
    .mh-subject-table .mh-subj-remove:hover { background: #fcf0f1 !important; border-color: #d63638 !important; }
    .mh-subject-table input[type="checkbox"] {
        width: 18px !important;
        height: 18px !important;
        margin: 0 auto !important;
        display: block;
    }
	
	/* Notenfeld schmaler machen */
    .mh-subject-table input[name="subj_grade[]"] {
        width: 50px !important; /* Feste schmale Breite */
        text-align: center;
        margin: 0 auto !important;
        display: block;
	}

   /* Gehärtetes CSS für die Hilfe-Box */
details.mh-help-notice-box {
    background-color: #f0f6fb !important;
    border: 1px solid #003E7E !important;
    border-left: 5px solid #003E7E !important;
    border-radius: 4px !important;
    margin: 20px 0 !important;
    display: block !important;
    width: 100% !important;
    padding: 0 !important;
}

details.mh-help-notice-box summary {
    padding: 15px !important;
    color: #003E7E !important;
    font-weight: bold !important;
    cursor: pointer !important;
    list-style: none !important;
    display: flex !important;
    align-items: center !important;
    outline: none !important;
    background: none !important;
    border: none !important;
}

/* Standard-Pfeil von Browsern verstecken */
details.mh-help-notice-box summary::-webkit-details-marker {
    display: none !important;
}

/* Eigenes Icon vor den Text setzen */
details.mh-help-notice-box summary::before {
    content: "\f140" !important; /* Dashicon Pfeil */
    font-family: dashicons !important;
    font-size: 20px !important;
    margin-right: 10px !important;
    transition: transform 0.2s ease !important;
}

details.mh-help-notice-box[open] summary::before {
    transform: rotate(180deg) !important;
}

/* Der weiße Inhaltsbereich */
.mh-help-content-inner {
    padding: 0 20px 20px 20px !important;
    background-color: #f0f6fb !important; /* Gleicher Hintergrund wie Box */
    color: #333 !important;
}

.mh-help-content-inner h5 {
    margin: 15px 0 10px 0 !important;
    color: #003E7E !important;
    font-weight: bold !important;
    border-bottom: 1px solid #d1e3ef !important;
    padding-bottom: 5px !important;
}

.mh-help-content-inner ul {
    margin: 0 0 15px 20px !important;
    padding: 0 !important;
    list-style-type: disc !important;
}

.mh-help-content-inner li {
    margin-bottom: 8px !important;
    float: none !important; /* Wichtig gegen Theme-Floats */
}
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box"><h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3></div>
    <?php endif; ?>

    <?php if ( ! empty( $warning_msg ) ): ?>
        <div class="mh-warning-box">
             <h3 style="margin-top:0; color:#b7791f;">⚠️ Hinweis zur Datumsänderung:</h3>
             <div style="color: #8a6d3b; line-height: 1.4;"><?= $warning_msg ?></div>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">❌ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;"><?php foreach($form_errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form" novalidate>
        <input type="hidden" name="action" value="mh_submit_form">
        <input type="hidden" name="form_type" value="abmeldung_student_v1">
        <input type="hidden" name="submission_id" value="<?= $val('id') ?>">
        <?php // Erkennt die Formularsitzung wieder: erneutes PDF-Erzeugen aktualisiert dieselbe Einsendung. ?>
        <input type="hidden" name="client_token" value="<?= esc_attr( (string) ( $form_data['client_token'] ?? wp_generate_uuid4() ) ) ?>">
        <input type="hidden" name="pdf_in_window" value="1">
        
        <!-- NEU: Flag für Vollzeit-Logik -->
        <input type="hidden" name="is_fulltime_class" id="is_fulltime_class" value="<?= $val('is_fulltime_class') ?>">

        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- NEU: SEKTION 0: Auswahl aus Stammdaten -->
        <div class="mh-form-section">
            <h4>Klassen- & Schülerwahl</h4>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Klasse <span class="req">*</span></label>
                    <select name="class_wu_id" id="mh_class_select" required>
                        <option value="">-- Bitte wählen --</option>
                        <?php if(!empty($classes_list)): foreach($classes_list as $c): ?>
                            <option value="<?= $c['wu_id'] ?>" 
                                    data-fulltime="<?= $c['is_fulltime'] ?>" 
                                    data-name="<?= esc_attr($c['name']) ?>"
                                    data-track="<?= esc_attr($c['track_key'] ?? '') ?>"
                                    <?= selected($val('class_wu_id'), $c['wu_id']) ?>>
                                <?= esc_html($c['name']) ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <input type="hidden" name="class_name" id="class_name_hidden" value="<?= $val('class_name') ?>">
                </div>

                <div class="mh-input-group">
                    <label>Schüler*in <span class="req">*</span></label>
                    <select name="student_wu_id" id="mh_student_select" required <?= empty($val('class_wu_id')) ? 'disabled' : '' ?>>
						<option value="">-- Erst Klasse wählen --</option>
						<!-- Diese Option erlaubt den manuellen Override -->
						<option value="manual">-- Manueller Eintrag (Schüler*in nicht in der Liste) --</option>
						<?php if(!empty($val('student_wu_id'))): ?>
							<!-- Wir setzen den gespeicherten Schüler als erste Option ein -->
							<option value="<?= $val('student_wu_id') ?>" selected>
								<?= $val('lastname') ?>, <?= $val('firstname') ?>
							</option>
						<?php endif; ?>
					</select>
                    <input type="hidden" name="lastname" id="student_lastname" value="<?= $val('lastname') ?>">
                    <input type="hidden" name="firstname" id="student_firstname" value="<?= $val('firstname') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 1: Stammdaten (Erhalten & Lehrer Autofill) -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            <div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Nachname <span class="req">*</span></label><input type="text" name="lastname_manual" id="display_lastname" readonly class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>"></div>
                <div class="mh-input-group"><label>Vorname <span class="req">*</span></label><input type="text" name="firstname_manual" id="display_firstname" readonly class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>"></div>
                <div class="mh-input-group">
                    <label>Geburtsdatum <span class="req">*</span></label>
                    <input type="date" name="dob" id="field_dob" required readonly class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Klasse (Anzeige)</label><input type="text" id="display_classname" readonly value="<?= $val('class_name') ?>"></div>
                <div class="mh-input-group">
                    <label>Klassenlehrer*in (angemeldet) <span class="req">*</span></label>
                    <input type="text" name="teacher" required readonly value="<?= $val('teacher') ?: $teacher_default ?>">
                </div>  
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Status <span class="mh-info-icon" data-tooltip="Ermittelt Volljährigkeit zum Stichtag 01.08.">?</span></label><div class="mh-fake-input"><span id="status_display">...</span><input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>"></div></div>
                <div class="mh-input-group"><label>Datum der Abmeldung / Kündigung <span class="req">*</span><span class="mh-info-icon" data-tooltip="Datum des Endes des Schulverhältnisses. Das Formular rechnet basierend darauf den letzten Schultag (Konferenz- und Zeugnisdatum) aus.">?</span> </label><input type="date" name="date_off" id="field_date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>"></div>
            </div>
        </div>

        <!-- SEKTION 2: Grund (Erhalten) -->
        <div class="mh-form-section">
            <h4>Grund der Abmeldung <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="reason" value="schulwechsel" id="r_wechsel" class="toggle-trigger" data-target="new_school_wrap" required <?= $chk('reason', 'schulwechsel') ?>> <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule)</label></div>
            <div id="new_school_wrap" class="mh-sub-group toggle-target"><div class="mh-input-group"><label>Name der Schule <span class="req">*</span></label><input type="text" name="new_school" placeholder="Schulname" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>"></div></div>
            <div class="radio-group"><input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> <label for="r_beschl">Ausschulung Beschluss Teillehrerkonferenz</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> <label for="r_abm">Abmeldung</label></div>
        </div>

        <!-- SEKTION 3: Schulpflicht (Erhalten) -->
	    <div class="mh-form-section">
            <h4>Schulpflicht <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="compulsory" value="fulfilled" id="c_full" class="toggle-trigger" required <?= $chk('compulsory', 'fulfilled') ?>> <label for="c_full">Die Schulpflicht ist erfüllt.</label></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="not_fulfilled" id="c_not" class="toggle-trigger" <?= $chk('compulsory', 'not_fulfilled') ?>> <label for="c_not">Die Schulpflicht ist NICHT erfüllt (Schulpflichtverfolgung...).</label></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="av_klasse" id="c_av" class="toggle-trigger" data-target="av_details" <?= $chk('compulsory', 'av_klasse') ?>> <label for="c_av">Wechsel in AV-Klasse</label></div>
            <div id="av_details" class="mh-sub-group toggle-target"><div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Zum Datum <span class="req">*</span></label><input type="date" name="av_date_start" value="<?= $val('av_date_start') ?>"></div>
                <div class="mh-input-group"><label>Gespräch mit <span class="req">*</span></label><input type="text" name="av_talk_with" value="<?= $val('av_talk_with') ?>"></div>
                <div class="mh-input-group"><label>am <span class="req">*</span></label><input type="date" name="av_talk_date" value="<?= $val('av_talk_date') ?>"></div>
            </div></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="bildungsgang" id="c_bg" class="toggle-trigger" data-target="bg_details" <?= $chk('compulsory', 'bildungsgang') ?>> <label for="c_bg">Wechsel in den Bildungsgang...</label></div>
            <div id="bg_details" class="mh-sub-group toggle-target"><div class="mh-input-group"><label>Name des Bildungsgangs <span class="req">*</span></label><input type="text" name="new_education_track" value="<?= $val('new_education_track') ?>"></div></div>
	
		<div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px;">
			<details class="mh-help-notice-box">
				<summary>
					Wie prüfe ich die Schulpflicht? Hinweise & Tipps
				</summary>
				<div class="mh-help-content-inner">

					<h5>Allgemeine Regeln (§ 34-38 SchulG)</h5>
					<ul>
						<li><strong>Volljährig:</strong> Schulpflicht endet mit 18 Jahren (außer bei bestehendem Ausbildungsverhältnis).</li>
						<li><strong>Minderjährig:</strong> Berufsschulpflicht bis zum Ende des Schuljahres, in dem das 18. Lebensjahr vollendet wird.</li>
					</ul>

					<h5>Besonderheit Berufsfachschule (Anlage B)</h5>
					<ul>
						<li><strong>BF I (Erster Abschluss / HS9):</strong> Erfüllt ein Jahr der Berufsschulpflicht. Wer ohne Abschluss abgeht, bleibt schulpflichtig.</li>
						<li>
							<strong>BF II (Erw. Erster Abschluss / HS10):</strong> Mit erfolgreichem Abschluss ist die Schulpflicht in der Regel <strong>erfüllt</strong> (§ 38 Abs. 3). 
							<br><strong style="color: #d63638;">Wichtig: Dies gilt auch, wenn der/die SchülerIn noch unter 18 Jahren alt ist</strong>, sofern kein Ausbildungsverhältnis beginnt.
						</li>
						<li><strong>Abbruch:</strong> Bei Abbruch vor Schuljahresende lebt die Schulpflicht sofort wieder auf!</li>
					</ul>

					<div style="background: #fff8e5; padding: 12px; border-radius: 4px; border: 1px solid #f5e7c1; font-size: 0.95em; display: block !important;">
						<span class="dashicons dashicons-warning" style="color: #d6a100; vertical-align: text-bottom;"></span> 
						<strong>Nachweispflicht:</strong> Bei Schulpflichtigen muss die Aufnahmebestätigung der Folgeschule oder der Ausbildungsvertrag zwingend vorliegen.
					</div>
				</div>
			</details>
		</div>
		
		</div>
		
        <!-- SEKTION: ANSCHLUSSPERSPEKTIVE (Bedingt Pflicht) -->
        <div id="section_perspective" class="mh-form-section">
            <h4 style="margin-bottom:5px;">Anschlussperspektive <span class="req" id="perspective_req">*</span></h4>
            <p style="font-size:0.85em; color:#666; margin-bottom:15px;">Auszufüllen für Vollzeit-Bildungsgänge. Im Speziellen AV, BFI, BFII, HH, KA, WG. </p>
            
            <div class="mh-input-group" style="margin-bottom: 5px !important;">
                <div class="radio-group">
                    <input type="radio" name="perspective" value="exists" id="p_exists" class="toggle-trigger" data-target="perspective_details_wrap" <?= $chk('perspective', 'exists') ?>> 
                    <label for="p_exists"><b>Es liegt eine konkrete Anschlussperspektive vor.</b></label>
                </div>
            </div>

            <div id="perspective_details_wrap" class="mh-sub-group toggle-target" style="background:#f0f0f0;">
                <div class="radio-group"><input type="radio" name="perspective_detail" value="ausbildung" <?= $chk('perspective_detail', 'ausbildung') ?>> <label>unterschriebener Ausbildungsvertrag</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="schule" <?= $chk('perspective_detail', 'schule') ?>> <label>Aufnahmebestätigung einer anderen Schule</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="studium" <?= $chk('perspective_detail', 'studium') ?>> <label>schriftliche Zusage eines Studienplatzes</label></div>
                <div class="radio-group"><input type="radio" name="perspective_detail" value="fsj" <?= $chk('perspective_detail', 'fsj') ?>> <label>schriftliche Zusage eines FSJ, FÖJ oder BFD</label></div>
                <div class="radio-group" style="align-items: center;"><input type="radio" name="perspective_detail" value="sonstiges" class="toggle-trigger" data-target="p_other_wrap" <?= $chk('perspective_detail', 'sonstiges') ?>> <label>sonstiges:</label></div>
                <div id="p_other_wrap" class="toggle-target" style="margin-left: 25px; margin-top:5px;"><input type="text" name="perspective_other" placeholder="Bitte angeben..." style="width:100%;" value="<?= $val('perspective_other') ?>"></div>
            </div>

            <div class="mh-input-group" style="margin-top: 15px;">
                <div class="radio-group"><input type="radio" name="perspective" value="none" id="p_none" class="toggle-trigger" <?= $chk('perspective', 'none') ?>> <label for="p_none"><b>Es liegt KEINE konkrete Anschlussperspektive vor.</b></label></div>
                <div style="margin-left: 28px; font-size: 0.85em; color: #6f6f6f;">(Name wird zur Nachverfolgung an die Agentur für Arbeit weitergegeben)</div>
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis (Ohne Fehlstunden) -->
        <div class="mh-form-section" style="<?= isset($form_errors['certificate']) ? 'border:2px solid #d63638;' : '' ?>">
            <h4>3. Zeugnis <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="certificate" value="abgang" id="z_ab" class="toggle-trigger" required <?= $chk('certificate', 'abgang') ?>> <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <small>(Ohne Abschluss)</small></label></div>
            <div class="radio-group"><input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="toggle-trigger" required <?= $chk('certificate', 'ueberweisung') ?>> <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small>(Wechsel innerhalb der Schulstufe)</small></label></div>
            <div class="radio-group"><input type="radio" name="certificate" value="none" id="z_kein" class="toggle-trigger" data-target="cert_none_wrap" required <?= $chk('certificate', 'none') ?>> <label for="z_kein">Kein Zeugnis <small>(Begründung erforderlich)</small></label></div>
            <!-- Die drei von der Schulleitung vorgegebenen Fälle. Alle Blöcke sind
                 mh-collapsible-section, damit sie erst bei Auswahl erscheinen und beim
                 Abwählen wieder komplett verschwinden (statt nur ausgegraut zu werden). -->
            <div id="cert_none_wrap" class="mh-sub-group toggle-target mh-collapsible-section <?= $err_cls('certificate_none_type') ?>">
                <div style="font-weight:bold; margin-bottom:8px;">Warum wird kein Zeugnis erteilt? <span class="req">*</span></div>

                <div class="radio-group"><input type="radio" name="certificate_none_type" value="gast" id="zn_gast" class="toggle-trigger" data-target="cert_none_gast_wrap" <?= $chk('certificate_none_type', 'gast') ?>> <label for="zn_gast"><b>Gastschüler*in / Zeugnis bereits erteilt</b></label></div>
                <div id="cert_none_gast_wrap" class="mh-sub-group toggle-target mh-collapsible-section">
                    <p style="margin:0 0 8px; font-size:0.85em; color:#6f6f6f;">
                        Schüler*in hat bereits ein Abschluss- oder Abgangszeugnis erhalten und wird aus organisatorischen
                        Gründen weiterhin im System geführt, z. B. aufgrund einer noch ausstehenden oder nicht bestandenen IHK-Prüfung.
                    </p>
                    <div class="mh-input-group">
                        <label for="certificate_issued_date">Datum des bereits ausgestellten Abschluss- oder Abgangszeugnisses <span class="req">*</span></label>
                        <input type="date" name="certificate_issued_date" id="certificate_issued_date" class="<?= $err_cls('certificate_issued_date') ?>" value="<?= $val('certificate_issued_date') ?>">
                    </div>
                </div>

                <div class="radio-group"><input type="radio" name="certificate_none_type" value="andere_schule" id="zn_schule" class="toggle-trigger" data-target="cert_none_schule_wrap" <?= $chk('certificate_none_type', 'andere_schule') ?>> <label for="zn_schule"><b>Besuch einer anderen Schule</b></label></div>
                <div id="cert_none_schule_wrap" class="mh-sub-group toggle-target mh-collapsible-section <?= $err_cls('certificate_proof') ?>">
                    <p style="margin:0 0 8px; font-size:0.85em; color:#6f6f6f;">
                        Schüler*in besucht seit Beginn des Schuljahres eine andere Schule, ohne dass die Abmeldung bzw. der
                        Schulwechsel dem LEBK ordnungsgemäß mitgeteilt wurde.
                    </p>
                    <div style="font-weight:bold; margin-bottom:5px;">Beigefügter Nachweis <span class="req">*</span></div>
                    <div class="radio-group"><input type="radio" name="certificate_proof" value="ausbildungsvertrag" id="zp_av" <?= $chk('certificate_proof', 'ausbildungsvertrag') ?>> <label for="zp_av">Ausbildungsvertrag</label></div>
                    <div class="radio-group"><input type="radio" name="certificate_proof" value="schulbescheinigung" id="zp_sb" <?= $chk('certificate_proof', 'schulbescheinigung') ?>> <label for="zp_sb">Schulbescheinigung</label></div>
                    <div class="radio-group"><input type="radio" name="certificate_proof" value="sekretariat" id="zp_sek" <?= $chk('certificate_proof', 'sekretariat') ?>> <label for="zp_sek">Bestätigung des Schulbesuchs durch das Sekretariat der aufnehmenden Schule</label></div>
                    <p style="margin:4px 0 0; font-size:0.85em; color:#6f6f6f;">Den Nachweis bitte der Abmeldung beifügen.</p>
                </div>

                <div class="radio-group"><input type="radio" name="certificate_none_type" value="keine_aufnahme" id="zn_aufnahme" class="toggle-trigger" data-target="cert_none_aufnahme_wrap" <?= $chk('certificate_none_type', 'keine_aufnahme') ?>> <label for="zn_aufnahme"><b>Fehlerhafte Aufnahme / Schulverhältnis nicht zustande gekommen</b></label></div>
                <div id="cert_none_aufnahme_wrap" class="mh-sub-group toggle-target mh-collapsible-section">
                    <p style="margin:0 0 8px; font-size:0.85em; color:#6f6f6f;">
                        Eine Anmeldung liegt vor, die Aufnahmevoraussetzungen wurden jedoch nicht erfüllt und ein
                        Schulverhältnis am LEBK ist nicht zustande gekommen.
                    </p>
                    <div class="mh-input-group">
                        <label for="certificate_none_reason">Grund für die nicht erfolgte Aufnahme <span class="req">*</span></label>
                        <textarea name="certificate_none_reason" id="certificate_none_reason" rows="3" style="width:100%;" class="<?= $err_cls('certificate_none_reason') ?>"><?= esc_textarea($form_data['certificate_none_reason'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div id="prot_mode_block" style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px; <?= isset($form_errors['protocol_mode']) ? 'border:2px solid #d63638; padding:10px;' : '' ?>">
                <div style="font-weight:bold; margin-bottom:10px;">Zeugniskonferenzprotokoll <span class="req" id="prot_mode_req">*</span></div>

                <div id="prot_none_hint" style="margin-bottom:10px; font-size:0.85em; color:#6f6f6f; display:none;">
                    Entfällt: Ohne Zeugnis gibt es keine Zeugniskonferenz und damit auch kein Protokoll.
                </div>

                <div class="radio-group">
                    <input type="radio" name="protocol_mode" value="create" id="prot_mode_create" class="toggle-trigger" data-target="protocol_wrapper" <?= 'create' === $protocol_mode ? 'checked' : '' ?>>
                    <label for="prot_mode_create" style="font-weight:bold;">Zeugniskonferenzprotokoll jetzt erstellen</label>
                </div>

                <div class="radio-group" style="align-items:flex-start; margin-top:12px;">
                    <input type="radio" name="protocol_mode" value="existing" id="prot_mode_existing" class="toggle-trigger" style="margin-top:3px;" <?= 'existing' === $protocol_mode ? 'checked' : '' ?>>
                    <label for="prot_mode_existing" style="line-height:1.45;">Ich lege ein bestehendes Zeugniskonferenzprotokoll bei. Konferenzdatum und Zeugnisdatum werden daraus ersichtlich. Änderungen sind mit der Abteilungsleitung abgesprochen und von ihr abgezeichnet.</label>
                </div>

                <div id="prot_existing_hint" style="margin-top:10px; margin-left:28px; font-size:0.85em; color:#6f6f6f; display:none;">
                    Abschnitt 4 entfällt, dem PDF wird kein Protokoll angehängt. Die digitale Noteneinsammlung
                    ist in diesem Fall nicht möglich, weil die Noten aus dem beigefügten Protokoll stammen.
                </div>
            </div>
        </div>

        <!-- SEKTION 5: Protokoll (Inkl. Fehlstunden) -->
        <div id="protocol_wrapper" class="mh-form-section toggle-target mh-collapsible-section" style="border-left: 5px solid #0073aa;">
            <h4>4. Angaben zum Konferenzprotokoll</h4>
           <!-- Ersetzt die Radio-Buttons für Teilzeit/Vollzeit -->
<input type="hidden" name="prot_type" id="input_prot_type" value="<?= $val('prot_type') ?>">
            <div class="mh-grid-row mh-grid-3" style="margin-top:20px;">
                <div class="mh-input-group">
                    <label>Konferenzdatum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Das Konferenzdatum wird der Einfachheit halber auf das Zeugnisdatum gesetzt. Es sollte ein Schultag sein.">?</span></label>
                    <input type="date" name="prot_date" id="field_prot_date" readonly value="<?= $val('prot_date') ?>" class="<?= ! empty( $form_data['prot_was_corrected'] ) ? 'mh-date-corrected' : '' ?>">
                </div>
                <div class="mh-input-group"><label>Ausgabedatum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Dieses wird automatisch berechnet. Es ist der letzte Schultag, ausgehend vom Abmeldedatum.">?</span></label><input type="date" name="prot_issue_date" id="field_prot_issue_date" readonly value="<?= $val('prot_issue_date') ?>" class="<?= ! empty( $form_data['prot_was_corrected'] ) ? 'mh-date-corrected' : '' ?>"></div>
                <div class="mh-input-group"><label>Vorsitzende/r <span class="req">*</span><span class="mh-info-icon" data-tooltip="Der/die Vorsitzende ist in der Regel die Abteilungsleitung des Bildungsgangs.">?</span></label><input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>"></div>
            </div>
            <?php // Erklärung, wenn Konferenz-/Zeugnisdatum vom Abmeldedatum abweicht. Wird per JS bei jeder Datumsänderung neu gesetzt. ?>
            <div id="prot_date_hint" class="mh-date-hint" style="<?= ! empty( $form_data['prot_was_corrected'] ) ? '' : 'display:none;' ?>">
                <?php if ( ! empty( $form_data['prot_was_corrected'] ) ) : ?>ℹ️ Konferenz- und Zeugnisdatum wurden auf den letzten Schultag vor dem Abmeldedatum gelegt.<?php endif; ?>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                 <div class="mh-input-group"><label>Raum <span class="req">*</span><span class="mh-info-icon" data-tooltip="Gib hier eine Raumnummer oder das LZ für Lehrerzimmer an.">?</span></label><input type="text" name="prot_room" value="<?= $val('prot_room') ?>"></div>
                 <div class="mh-grid-row mh-grid-2" style="margin-bottom:0 !important; gap: 10px !important;">
                    <div class="mh-input-group"><label>Fehlstunden <span class="req">*</span></label><input type="number" name="missed_hours" value="<?= $val('missed_hours') ?>"></div>
                    <div class="mh-input-group"><label>Unentschuldigt <span class="req">*</span></label><input type="number" name="missed_ue" value="<?= $val('missed_ue') ?>"></div>
                 </div>
            </div>
<!-- SEKTION: FÄCHER & NOTEN -->
        <div style="margin-top: 25px; margin-bottom: 20px;">
            <h5 style="margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Fächer &amp; Noten (Vorausfüllung für Protokoll)</h5>

            <div style="background:#f6f7f7; border:1px solid #dcdcde; border-left:4px solid #0073aa; padding:14px 16px; margin-bottom:15px; font-size:0.9em; line-height:1.5;">
                <p style="margin:0 0 10px; padding:8px 12px; background:#eaf3fb; border-radius:4px; font-size:1.05em;">
                    <strong>Du entscheidest für jedes Fach einzeln</strong>, woher die Note kommt – über die Spalte
                    <em>Note</em> in der jeweiligen Zeile. Die Wege lassen sich beliebig mischen.
                </p>

                <div style="margin-bottom:8px;">
                    <strong>1. Selbst eintragen:</strong> Note liegt dir vor → in der Spalte <em>Note</em> auswählen.
                </div>

                <?php if ( $noten_enabled ) : ?>
                <div style="margin-bottom:8px;">
                    <strong style="color:#1b5e20;">2. Automatisch einsammeln:</strong>
                    <span style="display:inline-block; padding:1px 6px; border-radius:3px; background:#1b5e20; color:#fff; font-size:0.75em; font-weight:700; letter-spacing:0.5px; vertical-align:middle;">BETA</span>
                    <em>„automatisch einsammeln“</em> wählen und die Fachlehrkraft angeben. Sie bekommt eine Mail mit Link;
                    du wirst benachrichtigt, sobald alle Noten da sind, und lädst das PDF im Dashboard herunter.
                </div>
                <?php endif; ?>

                <div style="margin-bottom:8px;">
                    <strong><?= $noten_enabled ? '3.' : '2.' ?> Im ausgedruckten PDF handschriftlich ergänzen:</strong>
                    Spalte <em>Note</em> leer lassen, PDF erzeugen und die Note auf dem Papier eintragen lassen.
                </div>

                <p style="margin:0; padding-top:8px; border-top:1px solid #dcdcde; color:#50575e; font-size:0.95em;">
                    Die Häkchen <em>WebUntis</em> und <em>vorher abgeschlossen</em> werden erst bedienbar, wenn in der Zeile eine Note steht.
                </p>
            </div>

            <table class="mh-subject-table">
                <thead>
                    <tr>
                        <th width="23%">Fach</th>
                        <th width="23%">Lehrkraft</th>
                        <th width="10%">Note</th>
                        <th width="19%" style="text-align:center;">Teilnoten in WebUntis eingetragen?</th>
                        <th width="19%" style="text-align:center;">Fach vorher abgeschlossen?</th>
                        <th width="6%" style="text-align:center;">Löschen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Grundgerüst: 12 Zeilen, bei gespeicherten Formularen ggf. mehr
                    $known_subject_names = array_flip( array_column( $subjects_list ?? [], 'short_name' ) );
                    for($i=0; $i<$subject_row_count; $i++):
                        $s = $form_data['subjects'][$i] ?? [];
                    ?>
                    <tr>
                        <td>
                            
                                <select name="subj_name[]" style="width:100%;">
                                    <option value="">-- Fach wählen --</option>
                                    <?php
                                    // Kurse (z. B. "D-D12_KA_WEIE") stehen in keiner Fächerliste. Ohne eigene
                                    // Option wäre nach einem Neuladen oder beim Bearbeiten nichts ausgewählt,
                                    // und der Kurs fiele beim nächsten Absenden stillschweigend weg.
                                    $saved_name = (string) ( $s['name'] ?? '' );
                                    if ( '' !== $saved_name && ! isset( $known_subject_names[ $saved_name ] ) ) : ?>
                                        <option value="<?= esc_attr($saved_name) ?>" selected><?= esc_html($saved_name) ?></option>
                                    <?php endif; ?>
                                    <?php foreach($subjects_list as $sub): ?>
                                        <option value="<?= esc_attr($sub['short_name']) ?>" <?= selected($s['name'] ?? '', $sub['short_name']) ?>>
                                            <?= esc_html($sub['short_name']) ?> - <?= esc_html($sub['display_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            
                        </td>
                        <td>
                            <select name="subj_teacher[]">
                                <option value="">-- Lehrkraft --</option>
                                <?php if(!empty($teachers_list)): foreach($teachers_list as $t): ?>
                                    <option value="<?= esc_attr($t['name']) ?>" <?= selected($s['teacher'] ?? '', $t['name']) ?>>
                                        <?= esc_html($t['name']) ?> (<?= esc_html($t['long_name']) ?>)
                                    </option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                       <td>
						<select name="subj_grade[]" class="mh-grade-select mh-no-validate">
							<option value="">-</option>
							<?php foreach(['1','2','3','4','5','6','NB','NE'] as $n): ?>
								<option value="<?= $n ?>" <?= selected($s['grade'] ?? '', $n) ?>><?= $n ?></option>
							<?php endforeach; ?>
							<?php if ( $noten_enabled ) : ?>
								<option value="<?= esc_attr($collect_marker) ?>" <?= selected(($s['collect'] ?? '0'), '1') ?>>automatisch einsammeln</option>
							<?php endif; ?>

						</select>
					</td>
                        <td>
                            <input type="checkbox" name="subj_webuntis[<?= $i ?>]" value="1" <?= (isset($s['webuntis']) && $s['webuntis'] == '1') ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <input type="checkbox" name="subj_completed[<?= $i ?>]" value="1" <?= (isset($s['completed']) && $s['completed'] == '1') ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="mh-subj-remove" title="Zeile löschen" aria-label="Zeile löschen">
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>

            </table>
			<div style="margin-top:8px;">
				<button type="button" id="btn_add_subject" class="button button-secondary">+ Weiteres Fach hinzufügen</button>
			</div>
			<div id="course_hint" style="display:none; margin-top:8px; padding:8px 11px; background:#eef4f8; border-left:3px solid #0073aa; font-size:0.85em; line-height:1.45;">
				<strong>Kursbelegungen ergänzt.</strong> Sie stehen oben in der Tabelle und bringen die Kurslehrkraft mit.
				Das Fach, auf das ein Kurs gebucht ist (z.&nbsp;B. <em>Reli/PRPH</em>), entfällt dafür als eigene Zeile –
				im Protokoll steht der belegte Kurs statt des Platzhalters.
			</div>
			<p style="margin-top:8px;font-size:9pt;">NB = nicht bewertbar | NE = nicht erteilt</p>
        </div>
            <div class="mh-input-group"><label>Beschlussfassung / Bemerkungen:<span class="mh-info-icon" data-tooltip="Sollten Fächer mit NB bewertet werden, brauchen wir auf jeden Fall eine Bemerkung.">?</span></label><textarea name="prot_remarks" style="width:100%; height:80px;"><?= $val('prot_remarks') ?></textarea></div>            
        </div>
		
		<div id="notice_block" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
			<div class="radio-group">
				<input type="checkbox" name="notice_accepted" value="1" id="chk_notice" required <?= $chk('notice_accepted', '1') ?>>
				<label for="chk_notice" style="font-weight:bold;">
					Ich habe zur Kenntnis genommen, dass dieses Formular inkl. aller Daten gespeichert wird und zu einem späteren Zeitpunkt weiter bearbeitet bzw. korrigiert werden kann. <span class="req">*</span>
				</label>
			</div>
		</div>
		
        <div class="btn-group">
            <button type="submit" name="submit_mode" value="pdf" formtarget="_blank" class="button button-primary button-large" title="Das PDF öffnet sich in einem neuen Fenster. Das Formular bleibt offen und kann weiter geändert werden.">Prüfen &amp; PDF erstellen ↗</button>
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular nur prüfen</button>
            <?php if ( $noten_enabled ) : ?>
            <button type="submit" name="submit_mode" value="collect" id="btn_collect" class="button button-secondary button-large" style="background:#1b5e20 !important; color:#fff !important; border-color:#1b5e20 !important; display:none;">
                Noteneinsammlung starten <span id="btn_collect_count"></span>
                <span style="display:inline-block; margin-left:6px; padding:1px 6px; border-radius:3px; background:#fff; color:#1b5e20; font-size:0.7em; font-weight:700; letter-spacing:0.5px; vertical-align:middle;">BETA</span>
            </button>
            <?php endif; ?>
        </div>
        <div id="mh_validation_status" class="mh-validation-status" role="alert" aria-live="assertive"></div>
        <?php if ( $noten_enabled ) : ?>
        <p id="collect_hint" style="font-size:0.9em; color:#555; margin-top:10px; display:none;">
            <strong>Noten automatisch einsammeln <span style="color:#1b5e20;">(BETA)</span>:</strong> Dieses Verfahren ist
            neu und wird noch erprobt. Die betroffenen Fachlehrer*innen erhalten eine E-Mail mit Link zur Noteneingabe und
            werden bei Bedarf automatisch erinnert; in keiner Mail steht eine Note. Sobald alle eingesammelten Noten
            vorliegen, wirst du benachrichtigt und lädst das fertige Formular im Dashboard herunter. Bis dahin gibt es
            hier kein PDF. Für jedes eingesammelte Fach muss eine Lehrkraft ausgewählt sein, zu der sich eine
            E-Mail-Adresse auflösen lässt. Wenn etwas klemmt, sag Bescheid – und sammle im Zweifel auf Papier.
        </p>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. ELEMENT-REFERENZEN
    const classSelect = document.getElementById('mh_class_select');
    const studentSelect = document.getElementById('mh_student_select');
    const perspectiveSection = document.getElementById('section_perspective');
    const isFulltimeInput = document.getElementById('is_fulltime_class');
    const classHidden = document.getElementById('class_name_hidden');
    const displayClass = document.getElementById('display_classname');
    
    const f_last = document.getElementById('display_lastname');
    const f_first = document.getElementById('display_firstname');
    const f_dob = document.getElementById('field_dob');
    
    const h_last = document.getElementById('student_lastname');
    const h_first = document.getElementById('student_firstname');

    // 2. HELPER: SCHÜLER PER AJAX LADEN
    function fetchStudents(classId, selectedStudentId = null) {
        if (!classId) { 
            studentSelect.disabled = true; 
            studentSelect.innerHTML = '<option value="">-- Erst Klasse wählen --</option>'; 
            return; 
        }

        studentSelect.disabled = false; 
        studentSelect.innerHTML = `
            <option value="">-- Schüler*in wählen --</option>
            <option value="manual" ${selectedStudentId === 'manual' ? 'selected' : ''}>-- Manueller Eintrag (Schüler*in nicht in Liste) --</option>
        `;

        if (!selectedStudentId) {
            const loadingOpt = document.createElement('option');
            loadingOpt.text = 'Lade Klassenliste...';
            studentSelect.add(loadingOpt);
        }

        const formData = new FormData();
        formData.append('action', 'mh_get_students');
        formData.append('class_id', classId);
        formData.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            studentSelect.innerHTML = `
                <option value="">-- Schüler*in wählen --</option>
                <option value="manual" ${selectedStudentId === 'manual' ? 'selected' : ''}>-- Manueller Eintrag (Schüler*in nicht in Liste) --</option>
            `;
            if (data.success && data.data) {
                data.data.forEach(s => {
                    const isSelected = (selectedStudentId && s.wu_id == selectedStudentId) ? 'selected' : '';
                    studentSelect.innerHTML += `<option value="${s.wu_id}" data-last="${s.name}" data-first="${s.fore_name}" data-dob="${s.dob || ''}" data-schild="${s.schild_id || ''}" data-track="${s.track_key || ''}" ${isSelected}>${s.name}, ${s.fore_name}</option>`;
                });
            }
        }).catch(err => console.error("Fehler:", err));
    }

    // 3. HELPER: VOLLZEIT/TEILZEIT UI
    function updatePerspectiveUI() {
        const opt = classSelect.options[classSelect.selectedIndex];
        const protTypeInput = document.getElementById('input_prot_type');
        if (!opt || !opt.value) return;

        const isFulltime = opt.dataset.fulltime === "1";
        classHidden.value = opt.dataset.name || '';
        if(displayClass) displayClass.value = opt.dataset.name || '';

        if (isFulltime) {
            perspectiveSection.style.opacity = "1"; perspectiveSection.style.pointerEvents = "auto";
            isFulltimeInput.value = "1";
            if(protTypeInput) protTypeInput.value = "vollzeit";
            document.getElementById('perspective_req').style.display = "inline";
            perspectiveSection.querySelectorAll('input').forEach(i => i.disabled = false);
        } else {
            perspectiveSection.style.opacity = "0.4"; perspectiveSection.style.pointerEvents = "none";
            isFulltimeInput.value = "0";
            if(protTypeInput) protTypeInput.value = "berufsschule";
            document.getElementById('perspective_req').style.display = "none";
            perspectiveSection.querySelectorAll('input').forEach(i => { i.disabled = true; i.required = false; });
        }
    }

    // 4. MANUELLE EINGABE SYNCHRONISIEREN (Wichtig für deinen Fehler!)
    // Wenn der User tippt, kopieren wir den Wert in das versteckte Feld für PHP
    f_last.addEventListener('input', function() {
        if (studentSelect.value === 'manual') h_last.value = this.value;
    });
    f_first.addEventListener('input', function() {
        if (studentSelect.value === 'manual') h_first.value = this.value;
    });
    f_dob.addEventListener('input', function() {
        if (studentSelect.value === 'manual') calcAge();
    });

    // 5. CHANGE LISTENERS

    // Bildungsgang der gewählten Klasse. Die Stundentafel hängt am Bildungsgang und ist
    // für alle Schüler einer Klasse dieselbe - deshalb kommt der Schlüssel von der Klasse
    // und nicht vom einzelnen Schüler. Nur so bekommt auch ein manuell eingetragener
    // Schüler seine Fächer. Individuell bleiben allein die Kursbelegungen (schild_id).
    let currentClassTrack = '';

    function readClassTrack() {
        const opt = classSelect.options[classSelect.selectedIndex];
        currentClassTrack = (opt && opt.dataset.track) ? opt.dataset.track : '';
    }

    classSelect.addEventListener('change', function() {
        readClassTrack();
        fetchStudents(this.value);
        updatePerspectiveUI();
    });

    studentSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const isManual = this.value === 'manual';
        
        if (isManual) {
            f_last.value = ''; f_first.value = ''; f_dob.value = '';
            f_last.readOnly = false; f_first.readOnly = false; f_dob.readOnly = false;
            f_last.style.backgroundColor = '#fff'; f_first.style.backgroundColor = '#fff'; f_dob.style.backgroundColor = '#fff';
            h_last.value = ''; h_first.value = '';
            // Kein Schild-Datensatz, also keine Kursbelegungen - die Stundentafel der
            // Klasse gibt es aber trotzdem.
            fetchSubjectRows(currentClassTrack, '');
        } else if (this.value !== '') {
            f_last.value = opt.dataset.last || '';
            f_first.value = opt.dataset.first || '';
            f_dob.value = opt.dataset.dob || '';
            f_last.readOnly = true; f_first.readOnly = true; f_dob.readOnly = true;
            f_last.style.backgroundColor = '#e9e9e9'; f_first.style.backgroundColor = '#e9e9e9'; f_dob.style.backgroundColor = '#e9e9e9';
            h_last.value = f_last.value; h_first.value = f_first.value;
            calcAge();
            // Fächer aus Stundentafel des Bildungsgangs + Kursbelegungen vorbelegen.
            // Die Klasse gewinnt; der Schülerwert greift nur, solange die Klasse noch
            // keinen Bildungsgang zugeordnet hat.
            fetchSubjectRows(currentClassTrack || opt.dataset.track || '', opt.dataset.schild || '');
        }
    });

    // 6. INITIALISIERUNG (Edit-Modus)
    const initialClassId = classSelect.value;
    const initialStudentId = "<?= $val('student_wu_id') ?>";
    readClassTrack();
    if (initialClassId) {
        fetchStudents(initialClassId, initialStudentId);
        updatePerspectiveUI();
        if (initialStudentId === 'manual') {
            setTimeout(() => { studentSelect.dispatchEvent(new Event('change')); }, 200);
        }
    }

    // 7. ALTER & DATUM SYNC
    const dobInput = document.getElementById('field_dob');
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');
    function calcAge() {
        if(!dobInput.value) return;
        const dob = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) { age--; }
        let outputHtml = '';
        if (age < 18) { outputHtml = '<b style="color:#d63638">Minderjährig</b> (' + age + ')'; statusInput.value = '1'; } 
        else { 
            let schoolYearStart = today.getFullYear();
            if (today.getMonth() < 7) schoolYearStart--;
            let ageAtStart = schoolYearStart - dob.getFullYear();
            if (7 < dob.getMonth() || (7 === dob.getMonth() && 1 < dob.getDate())) ageAtStart--;
            outputHtml = '<b style="color:#46b450">Volljährig</b> (' + age + ')';
            outputHtml += ageAtStart >= 18 ? '<br><small>(Schuljahresbeginn volljährig)</small>' : '<br><small>(Schuljahresbeginn <u style="color:#d63638">nicht</u> volljährig)</small>';
            statusInput.value = '0';
        }
        statusDisplay.innerHTML = outputHtml;
    }
    if(dobInput) { dobInput.addEventListener('change', calcAge); if(dobInput.value) calcAge(); }

    const dateOffInput = document.getElementById('field_date_off'); 
    const protDateInput = document.getElementById('field_prot_date'); 
    const protIssueInput = document.getElementById('field_prot_issue_date'); 
    const protDateHint = document.getElementById('prot_date_hint');

    // Konferenz- und Zeugnisdatum müssen auf einen Schultag fallen. Statt das erst beim
    // Absenden zu bemängeln, werden sie schon bei der Eingabe des Abmeldedatums auf den
    // letzten Schultag gelegt - mit Erklärung, falls sie vom Abmeldedatum abweichen.
    // Gerechnet wird auf dem Server (Feiertage und Ferien NRW), und zwar nur, wenn das
    // Protokoll hier erstellt wird: sonst werden beide Felder gar nicht gebraucht.
    let schoolDayRequest  = 0;
    let lastSyncedDateOff = null;

    function protocolActive() {
        const wrap = document.getElementById('protocol_wrapper');
        return !!wrap && !wrap.classList.contains('mh-hidden');
    }

    function setProtDates(value, corrected) {
        [protDateInput, protIssueInput].forEach(inp => {
            if (!inp) return;
            inp.value = value;
            // Klasse statt Inline-Style: das Formular-CSS setzt Rahmen mit !important.
            inp.classList.toggle('mh-date-corrected', !!corrected);
        });
    }

    function showDateHint(text, loading) {
        if (!protDateHint) return;
        protDateHint.textContent   = text || '';
        protDateHint.style.display = text ? '' : 'none';
        protDateHint.classList.toggle('is-loading', !!loading);
    }

    function syncProtocolDates(force) {
        if (!dateOffInput || !protDateInput) return;
        const raw = dateOffInput.value;

        if (!raw) {
            schoolDayRequest++;
            setProtDates('', false);
            showDateHint('');
            lastSyncedDateOff = null;
            return;
        }
        if (!protocolActive()) {
            // Vorläufig übernehmen; gerechnet wird, sobald das Protokoll aufgeklappt wird.
            schoolDayRequest++;
            setProtDates(raw, false);
            showDateHint('');
            lastSyncedDateOff = null;
            return;
        }
        if (!force && raw === lastSyncedDateOff) return;
        lastSyncedDateOff = raw;

        const req = ++schoolDayRequest;
        showDateHint('Schultag wird geprüft …', true);

        const fd = new FormData();
        fd.append('action', 'mh_school_day');
        fd.append('date', raw);
        fd.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (req !== schoolDayRequest) return; // inzwischen neueres Datum eingegeben
                if (!res || !res.success) {
                    setProtDates(raw, false);
                    showDateHint('');
                    lastSyncedDateOff = null;
                    return;
                }
                setProtDates(res.data.date, res.data.changed);
                showDateHint(res.data.changed ? 'ℹ️ ' + res.data.explanation : '');
            })
            .catch(err => {
                if (req !== schoolDayRequest) return;
                console.error('Fehler bei der Schultag-Prüfung:', err);
                // Ohne Antwort das Datum unverändert übernehmen - der Server korrigiert
                // spätestens bei der Prüfung vor dem Absenden.
                setProtDates(raw, false);
                showDateHint('');
                lastSyncedDateOff = null;
            });
    }

    if (dateOffInput && protDateInput) {
        if (dateOffInput.value && !protDateInput.value) setProtDates(dateOffInput.value, false);
        dateOffInput.addEventListener('change', () => syncProtocolDates());

        // Wird das Protokoll erst später gewählt (oder wieder ein Zeugnis), jetzt rechnen.
        // setTimeout: erst nachdem die Toggles den Bereich auf-/zugeklappt haben.
        document.querySelectorAll('input[name="protocol_mode"], input[name="certificate"]').forEach(r => {
            r.addEventListener('change', () => setTimeout(() => syncProtocolDates(), 0));
        });
        // Beim Laden (auch im Bearbeiten-Modus) nach der ersten Toggle-Runde prüfen.
        setTimeout(() => syncProtocolDates(), 150);
    }

    // 8. ALLGEMEINE TOGGLES
    const triggers = document.querySelectorAll('.toggle-trigger');
    const allTargets = document.querySelectorAll('.toggle-target');
    // Ohne Zeugnis keine Zeugniskonferenz: die Protokoll-Auswahl wird gesperrt und ist
    // kein Pflichtfeld mehr. Gesperrte Radios werden nicht mitgeschickt, die Auswahl
    // bleibt aber erhalten, falls doch wieder ein Zeugnis gewählt wird.
    const certNoneRadio = document.getElementById('z_kein');
    const protModeBlock = document.getElementById('prot_mode_block');
    function syncCertificateProtocol() {
        if (!certNoneRadio || !protModeBlock) return;
        const none = certNoneRadio.checked;
        protModeBlock.querySelectorAll('input[name="protocol_mode"]').forEach(r => { r.disabled = none; });
        protModeBlock.querySelectorAll('.radio-group').forEach(g => { g.style.opacity = none ? '0.4' : '1'; });
        const req  = document.getElementById('prot_mode_req');
        const hint = document.getElementById('prot_none_hint');
        if (req)  req.style.display  = none ? 'none' : '';
        if (hint) hint.style.display = none ? 'block' : 'none';
    }

    function updateToggles() {
        syncCertificateProtocol();
        // Gesperrte Auslöser zählen nicht - sonst bliebe z. B. der Protokollbereich
        // offen, obwohl "Kein Zeugnis" gewählt ist. Der Zustand wird pro Ziel erst hier
        // abgefragt (Dokumentreihenfolge): ein Elternblock entsperrt so seine Auslöser,
        // bevor die verschachtelten Unterblöcke geprüft werden.
        allTargets.forEach(t => {
            const isActive = Array.from(triggers).some(tr => tr.checked && !tr.disabled && tr.dataset.target === t.id);
            const parentTarget = t.parentElement.closest('.toggle-target');
            const isParentInactive = parentTarget && (parentTarget.style.opacity === '0.4' || parentTarget.classList.contains('mh-hidden'));
            if (!isActive || isParentInactive) {
                if (t.classList.contains('mh-collapsible-section')) t.classList.add('mh-hidden');
                else { t.style.opacity = "0.4"; t.style.pointerEvents = "none"; }
                t.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = true; i.required = false; });
            } else {
                t.classList.remove('mh-hidden'); t.style.opacity = "1"; t.style.pointerEvents = "auto";
                t.querySelectorAll('input, select, textarea').forEach(i => {
                    if (i.closest('.toggle-target').id === t.id) {
                        i.disabled = false;
                        if (i.type !== 'hidden' && i.type !== 'checkbox' && i.tagName !== 'TEXTAREA' && !i.closest('.mh-subject-table') && !i.classList.contains('mh-no-validate')) { i.required = true; }
                    }
                });
            }
        });
    }
    // updateProtocolMode() gleich mitlaufen lassen: die Zeugniswahl beeinflusst über die
    // Protokoll-Auswahl auch den Hinweistext und den Einsammel-Knopf.
    // Ein Radio meldet nur sein eigenes Anwählen, nicht das Abwählen. Deshalb hören wir
    // auf ALLE Radios einer Gruppe, in der es einen Auslöser gibt - sonst bliebe z. B.
    // das Feld "sonstiges" offen und Pflicht, wenn danach "FSJ" gewählt wird.
    const triggerGroups = new Set(Array.from(triggers).filter(t => t.type === 'radio').map(t => t.name));
    const toggleListeners = new Set(triggers);
    triggerGroups.forEach(name => document.querySelectorAll('input[type="radio"][name="' + name + '"]').forEach(r => toggleListeners.add(r)));
    toggleListeners.forEach(r => r.addEventListener('change', () => {
        updateToggles();
        updateProtocolMode();
        if (typeof pruneInactiveErrors === 'function') pruneInactiveErrors();
    }));
    setTimeout(() => { updateToggles(); updateProtocolMode(); }, 100);

    // 8b. PROTOKOLL-MODUS
    // Liegt ein bestehendes Protokoll bei, gibt es hier nichts einzusammeln - die Noten
    // stehen im beigefügten Protokoll. Der Button würde sonst einen Umlauf starten,
    // dessen Ergebnis niemand braucht.
    const protModeCreate   = document.getElementById('prot_mode_create');
    const protModeExisting = document.getElementById('prot_mode_existing');
    const protExistingHint = document.getElementById('prot_existing_hint');
    const collectBtn       = document.querySelector('button[name="submit_mode"][value="collect"]');
    const collectHint      = document.getElementById('collect_hint');

    // Der Knopf zur Noteneinsammlung erscheint nur, wenn es tatsächlich etwas
    // einzusammeln gibt - also mindestens eine Fächerzeile auf "per Mail anfragen"
    // steht. So kann es keinen Widerspruch zwischen Knopf und Tabelle geben, und der
    // Knopf erklärt sich aus der Tabelle heraus.
    const COLLECT_MARKER = <?= json_encode($collect_marker) ?>;
    const countLabel     = document.getElementById('btn_collect_count');

    function countCollectRows() {
        let n = 0;
        document.querySelectorAll('select[name="subj_grade[]"]').forEach(sel => {
            if (sel.value === COLLECT_MARKER && !sel.disabled) n++;
        });
        return n;
    }

    // Die beiden Häkchen beziehen sich auf eine konkrete, selbst eingetragene Note.
    // Sie sind deshalb standardmäßig gesperrt und werden erst frei, sobald in der
    // Zeile wirklich eine Note steht. Solange das Fach leer ist, gäbe es nichts zu
    // bestätigen; wird es eingesammelt, bestätigt die Fachlehrkraft den
    // WebUntis-Eintrag im eigenen Formular und würde alles hier gleich überschreiben.
    function syncRowChecks() {
        document.querySelectorAll('select[name="subj_grade[]"]').forEach(sel => {
            const row = sel.closest('tr');
            if (!row) return;

            const isCollect = sel.value === COLLECT_MARKER;
            const hasGrade  = sel.value !== '' && !isCollect;

            row.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                if (!hasGrade) {
                    cb.checked  = false;
                    cb.disabled = true;
                    cb.title    = isCollect
                        ? 'Wird von der Fachlehrkraft beim Eintragen der Note bestätigt.'
                        : 'Erst auswählbar, sobald eine Note eingetragen ist.';
                } else {
                    cb.disabled = false;
                    cb.title    = '';
                }
                cb.parentElement.style.opacity = hasGrade ? '1' : '0.35';
            });
        });
    }

    function updateProtocolMode() {
        const existing = protModeExisting && protModeExisting.checked && !protModeExisting.disabled;
        const n        = existing ? 0 : countCollectRows();
        const show     = n > 0;

        if (protExistingHint) protExistingHint.style.display = existing ? 'block' : 'none';
        if (collectBtn)  collectBtn.style.display  = show ? '' : 'none';
        if (collectHint) collectHint.style.display = show ? '' : 'none';
        if (countLabel)  countLabel.textContent    = show ? '(' + n + (n === 1 ? ' Fach)' : ' Fächer)') : '';

        // Muss NACH updateToggles() laufen: das schaltet beim Aufklappen des
        // Protokollbereichs pauschal alle Felder wieder frei.
        syncRowChecks();
    }

    if (protModeCreate)   protModeCreate.addEventListener('change', updateProtocolMode);
    if (protModeExisting) protModeExisting.addEventListener('change', updateProtocolMode);

    // Auch auf später per Vorbelegung angehängte Zeilen reagieren: ein Listener am
    // Container statt einer pro Select.
    const subjTable = document.querySelector('.mh-subject-table');
    if (subjTable) subjTable.addEventListener('change', updateProtocolMode);

    updateProtocolMode();
	// Logik für NB -> Bemerkungspflicht
    const remarksField = document.querySelector('textarea[name="prot_remarks"]');

    function checkNBRequirement() {
        let nbFound = false;
        // Frisch abfragen: die Vorbelegung kann Zeilen nachträglich angehängt haben.
        document.querySelectorAll('.mh-grade-select').forEach(select => {
            if (select.value === 'NB') nbFound = true;
        });

        if (nbFound) {
            remarksField.required = true;
            remarksField.style.borderColor = '#d63638';
            remarksField.placeholder = 'Begründung für NB hier zwingend erforderlich...';
        } else {
            remarksField.required = false;
            remarksField.style.borderColor = '';
            remarksField.placeholder = '';
        }
    }

    // Delegation statt Einzel-Listener: die Vorbelegung kann Zeilen nachträglich anhängen.
    const subjectTableEl = document.querySelector('.mh-subject-table');
    if (subjectTableEl) {
        subjectTableEl.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('mh-grade-select')) checkNBRequirement();
        });
    }
    // Initialer Check beim Laden (für Edit-Modus)
    checkNBRequirement();

    // ---------------------------------------------------------------
    // FÄCHER-VORBELEGUNG (Stundentafel des Bildungsgangs + Kursbelegungen)
    // ---------------------------------------------------------------
    const subjectTbody = document.querySelector('.mh-subject-table tbody');
    // Stehen schon Fächer im Formular (Bearbeiten-Modus / Reload nach Fehler),
    // wird nicht vorbelegt — sonst gingen erfasste Noten verloren.
    const subjectsLocked = <?= $has_existing_subjects ? 'true' : 'false' ?>;

    function subjectRows() {
        return subjectTbody ? Array.from(subjectTbody.rows) : [];
    }

    // subj_name/subj_teacher/subj_grade laufen über [], die Checkboxen über feste
    // Indizes — das Model greift sie per Index ab. Beim Klonen müssen die Indizes
    // deshalb lückenlos weiterlaufen.
    function renumberRow(row, index) {
        const wu = row.querySelector('input[name^="subj_webuntis"]');
        const co = row.querySelector('input[name^="subj_completed"]');
        if (wu) wu.name = 'subj_webuntis[' + index + ']';
        if (co) co.name = 'subj_completed[' + index + ']';
    }

    function ensureRowCount(needed) {
        const rows = subjectRows();
        if (!subjectTbody || rows.length === 0 || rows.length >= needed) return;
        const blueprint = rows[rows.length - 1];
        for (let i = rows.length; i < needed; i++) {
            const clone = blueprint.cloneNode(true);
            clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
            clone.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            subjectTbody.appendChild(clone);
            renumberRow(clone, i);
        }
    }

    function buildSubjectOptions(select, trackOptions, otherOptions, selectedValue, courseOptions) {
        courseOptions = courseOptions || [];
        select.innerHTML = '';
        select.add(new Option('-- Fach wählen --', ''));

        // Kurse zuerst: für Schüler*innen mit Kursbelegung ist das die wahrscheinlichste
        // Auswahl. Eine Einrückung unter das Fach ist nicht möglich, weil das Trägerfach
        // in Schild nur ein Sammelbegriff ("Kurs_11_12") ist und kein echtes Fach.
        if (courseOptions.length) {
            const gc = document.createElement('optgroup');
            gc.label = 'Kurse dieser Person';
            courseOptions.forEach(o => gc.appendChild(new Option(o.label, o.value)));
            select.add(gc);
        }

        if (trackOptions.length) {
            const g = document.createElement('optgroup');
            g.label = 'Fächer des Bildungsgangs';
            trackOptions.forEach(o => g.appendChild(new Option(o.label, o.value)));
            select.add(g);
        }
        if (otherOptions.length) {
            const g2 = document.createElement('optgroup');
            // Notausgang für Fachwechsler/Wiederholer: der Rest der Schild-Fächerliste
            // bleibt erreichbar, steht aber unterhalb der Bildungsgang-Fächer.
            g2.label = trackOptions.length ? 'Weitere Fächer' : 'Alle Fächer';
            otherOptions.forEach(o => g2.appendChild(new Option(o.label, o.value)));
            select.add(g2);
        }
        if (selectedValue) {
            // Kursbezeichnungen stehen in keiner Fächerliste — Option ergänzen.
            if (!Array.from(select.options).some(o => o.value === selectedValue)) {
                select.add(new Option(selectedValue, selectedValue));
            }
            select.value = selectedValue;
        }
    }

    function fillSubjectRows(payload) {
        if (!subjectTbody || subjectsLocked) return;

        const rowsData      = payload.rows || [];
        const trackOptions  = payload.track_options || [];
        const otherOptions  = payload.other_options || [];
        const courseOptions = payload.course_options || [];

        const courseHint = document.getElementById('course_hint');
        if (courseHint) courseHint.style.display = courseOptions.length ? 'block' : 'none';

        ensureRowCount(Math.max(rowsData.length, subjectRows().length));

        subjectRows().forEach((row, i) => {
            const nameSel  = row.querySelector('select[name="subj_name[]"]');
            const teachSel = row.querySelector('select[name="subj_teacher[]"]');
            const gradeSel = row.querySelector('select[name="subj_grade[]"]');
            const data     = rowsData[i];

            if (nameSel) buildSubjectOptions(nameSel, trackOptions, otherOptions, data ? data.value : '', courseOptions);
            if (gradeSel) gradeSel.value = '';
            if (teachSel) {
                // Lehrkraft nur bei Kursen: die Stundentafel kennt keine Fachlehrer.
                const wanted = (data && data.teacher) ? data.teacher : '';
                teachSel.value = Array.from(teachSel.options).some(o => o.value === wanted) ? wanted : '';
            }
            row.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            renumberRow(row, i);
        });

        // Neu angehängte Zeilen müssen denselben Aktiv/Inaktiv-Zustand bekommen
        // wie der Rest des Protokollbereichs.
        if (typeof updateToggles === 'function') updateToggles();
        if (typeof updateProtocolMode === 'function') updateProtocolMode();
        checkNBRequirement();
    }

    // Weitere Fachzeile anhängen. Die Vorbelegung füllt oft alle Zeilen, und ohne
    // diesen Knopf ließe sich dann kein zusätzliches Fach mehr eintragen.
    const btnAddSubject = document.getElementById('btn_add_subject');
    if (btnAddSubject) {
        btnAddSubject.addEventListener('click', function() {
            const rows = subjectRows();
            if (!rows.length) return;
            const clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('select').forEach(s => { s.value = ''; s.disabled = false; });
            clone.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            subjectTbody.appendChild(clone);
            renumberRow(clone, rows.length);
            // Häkchen-Sperre, Einsammel-Knopf und NB-Pflicht an die neue Zeile anpassen.
            updateProtocolMode();
            checkNBRequirement();
            const first = clone.querySelector('select[name="subj_name[]"]');
            if (first) first.focus();
        });
    }

    // Zeile entfernen. Die Checkboxen tragen feste Indizes (siehe renumberRow), deshalb
    // danach alle Zeilen neu durchnummerieren - sonst rutschen die Häkchen auf das
    // falsche Fach. Die letzte Zeile wird nur geleert, damit die Tabelle als Vorlage
    // für "Weiteres Fach hinzufügen" erhalten bleibt.
    if (subjectTbody) {
        subjectTbody.addEventListener('click', function(e) {
            const btn = e.target.closest('.mh-subj-remove');
            if (!btn) return;
            const row = btn.closest('tr');
            if (subjectRows().length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('select').forEach(s => { s.value = ''; });
                row.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
            }
            subjectRows().forEach((r, i) => renumberRow(r, i));
            updateProtocolMode();
            checkNBRequirement();
        });
    }

    function fetchSubjectRows(trackKey, schildId) {
        if (!subjectTbody || subjectsLocked) return;
        if (!trackKey && !schildId) return;

        const fd = new FormData();
        fd.append('action', 'mh_get_subject_rows');
        fd.append('track_key', trackKey || '');
        fd.append('schild_id', schildId || '');
        fd.append('nonce', '<?php echo wp_create_nonce("mh_form_nonce"); ?>');

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success && data.data) fillSubjectRows(data.data); })
        .catch(err => console.error("Fehler bei der Fächer-Vorbelegung:", err));
    }

    // ---------------------------------------------------------------
    // INLINE-PRÜFUNG
    // Jeder der drei Knöpfe (prüfen, PDF, Noteneinsammlung) lässt das Formular zuerst
    // per AJAX gegen dieselben Regeln prüfen wie der echte Submit. Fehler erscheinen
    // rot direkt am Feld; abgeschickt wird erst, wenn nichts mehr bemängelt wird.
    // So öffnet sich nie ein PDF-Fenster, das nur eine Fehlerliste enthält.
    // ---------------------------------------------------------------
    const mhForm      = document.getElementById('mh-abmeldung-form');
    const statusBox   = document.getElementById('mh_validation_status');
    const submitBtns  = mhForm.querySelectorAll('button[type="submit"]');
    const ajaxUrl     = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    const subjTableEl = document.querySelector('.mh-subject-table');

    let activeErrors      = [];   // { anchor, msgEl, isGroup }
    let bypassValidation  = false;
    let validationBusy    = false;
    let lastClickedButton = null;
    // Nach einem blockierten PDF-Fenster: geprüfter Formularstand, damit der zweite
    // Klick ohne erneute (asynchrone) Prüfung direkt öffnen darf.
    let pdfReadySnapshot  = null;

    submitBtns.forEach(b => b.addEventListener('click', () => { lastClickedButton = b; }));

    // Gruppen (Radio-Auswahlen, Tabelle): Fehler am Container statt an einem Einzelfeld.
    const GROUP_ANCHORS = {
        reason:                () => document.getElementById('r_wechsel').closest('.mh-form-section'),
        compulsory:            () => document.getElementById('c_full').closest('.mh-form-section'),
        perspective:           () => document.getElementById('section_perspective'),
        perspective_detail:    () => document.getElementById('perspective_details_wrap'),
        certificate:           () => document.getElementById('z_ab').closest('.mh-form-section'),
        certificate_none_type: () => document.getElementById('cert_none_wrap'),
        certificate_proof:     () => document.getElementById('cert_none_schule_wrap'),
        protocol_mode:         () => document.getElementById('prot_mode_block'),
        notice_accepted:       () => document.getElementById('notice_block'),
    };
    // Fehler rund um die Fächertabelle stehen direkt unter der Tabelle.
    const TABLE_KEYS = ['subjects', 'subjects_teacher', 'subjects_address', 'collect_disabled'];

    // Ohne gewählte Schüler*in zeigt der Fehler auf die Auswahl - die Namensfelder
    // sind dann schreibgeschützt und ließen sich gar nicht korrigieren.
    function studentFieldAnchor(field) {
        return studentSelect.value === '' ? studentSelect : field;
    }

    const FIELD_ANCHORS = {
        lastname:         () => studentFieldAnchor(f_last),
        firstname:        () => studentFieldAnchor(f_first),
        dob:              () => studentFieldAnchor(f_dob),
        class_name:       () => classSelect,
        prot_type:        () => classSelect,
        teacher:          () => mhForm.querySelector('input[name="teacher"]'),
        date_off:         () => dateOffInput,
        date_autocorrect: () => dateOffInput,
        // Konferenzdatum ist schreibgeschützt und folgt dem Abmeldedatum.
        prot_date:        () => dateOffInput,
    };

    function isVisible(el) {
        return !!el && el.offsetParent !== null && !el.closest('.mh-hidden');
    }

    function friendlyMessage(key, msg) {
        if (studentSelect.value === '' && ['lastname', 'firstname', 'dob'].includes(key)) {
            return 'Bitte eine Schüler*in auswählen (oder „Manueller Eintrag“).';
        }
        if (key === 'prot_type') return 'Bitte die Klasse auswählen – davon hängt die Protokollart ab.';
        return msg;
    }

    function resolveAnchor(key) {
        if (TABLE_KEYS.includes(key) && subjTableEl) return { el: subjTableEl, isGroup: true, after: true };
        if (GROUP_ANCHORS[key]) return { el: GROUP_ANCHORS[key](), isGroup: true, after: false };
        if (FIELD_ANCHORS[key]) return { el: FIELD_ANCHORS[key](), isGroup: false, after: true };
        const byName = mhForm.querySelector('[name="' + CSS.escape(key) + '"]');
        if (!byName) return null;
        if (byName.type === 'radio' || byName.type === 'checkbox') {
            return { el: byName.closest('.mh-sub-group, .mh-input-group, .mh-form-section'), isGroup: true, after: false };
        }
        return { el: byName, isGroup: false, after: true };
    }

    function addMessage(anchor, text, cssClass) {
        const existing = activeErrors.find(a => a.anchor === anchor.el && a.msgEl && a.msgEl.className === cssClass);
        // Pro Feld nur eine Meldung. Die erste gewinnt - die Server-Meldungen kommen
        // zuerst und sind konkreter als das allgemeine "Bitte ausfüllen" des Browsers.
        if (existing) return existing;
        const msg = document.createElement('div');
        msg.className = cssClass;
        msg.textContent = text;
        if (anchor.after) anchor.el.insertAdjacentElement('afterend', msg);
        else anchor.el.appendChild(msg);
        const entry = { anchor: anchor.el, msgEl: msg, isGroup: anchor.isGroup };
        activeErrors.push(entry);
        return entry;
    }

    function markField(el, isGroup) {
        el.classList.add(isGroup ? 'mh-group-error' : 'mh-error-field');
        if (!isGroup) el.setAttribute('aria-invalid', 'true');
    }

    function clearEntry(entry) {
        entry.anchor.classList.remove('mh-group-error', 'mh-error-field');
        entry.anchor.removeAttribute('aria-invalid');
        if (entry.msgEl) entry.msgEl.remove();
    }

    function clearAllErrors() {
        activeErrors.forEach(clearEntry);
        activeErrors = [];
        // Auch serverseitig beim Neuladen gesetzte Markierungen entfernen.
        mhForm.querySelectorAll('.mh-error-field').forEach(el => el.classList.remove('mh-error-field'));
        setStatus('', '');
    }

    function setStatus(type, html) {
        statusBox.className = 'mh-validation-status' + (type ? ' is-' + type : '');
        statusBox.innerHTML = html;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Einfache Browser-Prüfung (Pflichtfelder in aufgeklappten Unterbereichen usw.).
    // Ergänzt nur, was der Server nicht ohnehin schon gemeldet hat.
    function collectClientErrors() {
        const errs = {};
        Array.from(mhForm.elements).forEach(el => {
            if (!el.name || el.disabled || el.type === 'hidden' || el.type === 'submit' || el.tagName === 'BUTTON') return;
            if (el.closest('.mh-hidden') || el.checkValidity()) return;
            const key = el.name;
            if (errs[key]) return;
            if (el.validity.valueMissing) {
                errs[key] = el.type === 'radio' ? 'Bitte eine Auswahl treffen.'
                          : el.type === 'checkbox' ? 'Bitte bestätigen.'
                          : 'Bitte ausfüllen.';
            } else {
                errs[key] = el.validationMessage || 'Ungültige Eingabe.';
            }
        });
        return errs;
    }

    function showErrors(errors, badTeachers) {
        const unplaced = [];
        Object.keys(errors).forEach(key => {
            const text   = friendlyMessage(key, String(errors[key]));
            const anchor = resolveAnchor(key);
            if (!anchor || !anchor.el || !isVisible(anchor.el)) { unplaced.push(text); return; }
            markField(anchor.el, anchor.isGroup);
            addMessage(anchor, text, 'mh-field-error');
        });

        // In der Tabelle zusätzlich die betroffenen Lehrkraft-Felder markieren.
        if (subjTableEl && (errors.subjects_teacher || errors.subjects_address)) {
            subjTableEl.querySelectorAll('tbody tr').forEach(row => {
                const grade   = row.querySelector('select[name="subj_grade[]"]');
                const teacher = row.querySelector('select[name="subj_teacher[]"]');
                if (!grade || !teacher || grade.value !== COLLECT_MARKER) return;
                const missing = errors.subjects_teacher && teacher.value === '';
                const noMail  = (badTeachers || []).includes(teacher.value);
                if (missing || noMail) {
                    markField(teacher, false);
                    teacher.title = missing ? 'Lehrkraft fehlt' : 'Keine E-Mail-Adresse hinterlegt';
                    activeErrors.push({ anchor: teacher, msgEl: null, isGroup: false });
                }
            });
        }
        return unplaced;
    }

    function firstErrorElement() {
        const marked = mhForm.querySelectorAll('.mh-error-field, .mh-group-error');
        return Array.from(marked).find(isVisible) || null;
    }

    function jumpToFirstError() {
        const first = firstErrorElement();
        if (!first) return;
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const focusable = first.matches('input, select, textarea') ? first : first.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');
        if (focusable) setTimeout(() => focusable.focus({ preventScroll: true }), 350);
    }

    function reportErrors(errors, badTeachers) {
        const unplaced = showErrors(errors, badTeachers);
        // Gezählt werden die markierten Stellen, nicht die Rohmeldungen (mehrere
        // Meldungen können auf dasselbe Feld zeigen).
        const count = activeErrors.filter(en => en.msgEl).length + unplaced.length;
        let html = '<strong>❌ Das Formular wurde nicht abgeschickt.</strong> '
            + (count === 1 ? 'Ein Feld ist' : count + ' Angaben sind') + ' noch nicht in Ordnung – die betroffenen Stellen sind rot markiert.';
        if (unplaced.length) html += '<ul>' + unplaced.map(t => '<li>' + escapeHtml(t) + '</li>').join('') + '</ul>';
        html += '<br><button type="button" class="button" id="mh_jump_first_error">Zum ersten Fehler springen</button>';
        setStatus('error', html);
        const jumpBtn = document.getElementById('mh_jump_first_error');
        if (jumpBtn) jumpBtn.addEventListener('click', jumpToFirstError);
        jumpToFirstError();
    }

    // Vom Server korrigiertes Konferenz-/Zeugnisdatum sofort übernehmen: der nächste
    // Klick geht dann ohne Korrektur durch.
    // (Normalerweise schon bei der Eingabe passiert - das hier greift nur, wenn die
    // Schultag-Prüfung bei der Eingabe nicht durchkam.)
    function applyCorrectedDate(dateStr) {
        if (!dateStr || !protDateInput) return;
        setProtDates(dateStr, true);
        lastSyncedDateOff = dateOffInput ? dateOffInput.value : null;
        showDateHint('ℹ️ Konferenz- und Zeugnisdatum wurden auf den letzten Schultag vor dem Abmeldedatum gelegt: '
            + dateStr.split('-').reverse().join('.') + '.');
    }

    function formSnapshot() {
        const fd = new FormData(mhForm);
        return JSON.stringify(Array.from(fd.entries()));
    }

    function setBusy(busy) {
        validationBusy = busy;
        submitBtns.forEach(b => { b.disabled = busy; });
    }

    function submitForReal(submitter, target) {
        const originalTarget = submitter.getAttribute('formtarget');
        if (target) submitter.setAttribute('formtarget', target);
        bypassValidation = true;
        // Gesperrte Knöpfe werden nicht als submitter akzeptiert.
        submitter.disabled = false;
        if (typeof mhForm.requestSubmit === 'function') {
            mhForm.requestSubmit(submitter);
        } else {
            // Ältere Browser: Modus von Hand mitgeben.
            let hidden = mhForm.querySelector('input[type="hidden"][name="submit_mode"]');
            if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'submit_mode'; mhForm.appendChild(hidden); }
            hidden.value = submitter.value;
            mhForm.target = target || originalTarget || '';
            mhForm.submit();
            hidden.remove();
            mhForm.target = '';
        }
        if (originalTarget === null) submitter.removeAttribute('formtarget');
        else submitter.setAttribute('formtarget', originalTarget);
    }

    function openPdf(submitter) {
        // Das PDF-Fenster wird erst NACH erfolgreicher Prüfung geöffnet.
        const name = 'mh_pdf_' + Date.now();
        const win  = window.open('', name);
        if (!win) {
            pdfReadySnapshot = formSnapshot();
            setStatus('error', '<strong>Das Formular ist in Ordnung, aber der Browser hat das PDF-Fenster blockiert.</strong> '
                + 'Bitte noch einmal auf „Prüfen &amp; PDF erstellen“ klicken – oder Pop-ups für diese Seite erlauben.');
            return;
        }
        try {
            win.document.write('<p style="font-family:sans-serif;padding:20px;">PDF wird erzeugt …</p>');
        } catch (e) { /* egal - das PDF ersetzt den Inhalt ohnehin */ }
        pdfReadySnapshot = null;
        submitForReal(submitter, name);
        setStatus('success', '<strong>✅ Prüfung erfolgreich.</strong> Das PDF öffnet sich in einem neuen Fenster; dieses Formular bleibt offen und kann weiter geändert werden.');
    }

    mhForm.addEventListener('submit', function(e) {
        if (bypassValidation) { bypassValidation = false; return; }
        e.preventDefault();
        if (validationBusy) return;

        const submitter = e.submitter || lastClickedButton || mhForm.querySelector('button[value="check"]');
        const mode      = submitter ? submitter.value : 'check';

        // Zweiter Klick nach blockiertem Fenster: unveränderter, schon geprüfter Stand
        // wird direkt (noch innerhalb des Klicks) geöffnet.
        if (mode === 'pdf' && pdfReadySnapshot !== null && pdfReadySnapshot === formSnapshot()) {
            openPdf(submitter);
            return;
        }
        pdfReadySnapshot = null;

        clearAllErrors();
        const clientErrors = collectClientErrors();

        const fd = new FormData(mhForm);
        fd.set('action', 'mh_validate_form');
        fd.set('submit_mode', mode);

        setBusy(true);
        setStatus('busy', 'Formular wird geprüft …');

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                setBusy(false);
                if (res && !res.success && res.data && res.data.message) {
                    // Bekannter Grund (z. B. abgelaufene Sitzung): im Formular anzeigen.
                    setStatus('error', '<strong>❌ ' + escapeHtml(res.data.message) + '</strong>');
                    return;
                }
                if (!res || !res.success || !res.data) {
                    // Keine verwertbare Antwort (z. B. "0" = Endpunkt nicht erreichbar):
                    // die Inline-Prüfung darf das Absenden nie blockieren. Dann wie früher
                    // normal abschicken - der Server prüft beim Absenden ohnehin selbst.
                    setStatus('', '');
                    submitForReal(submitter, null);
                    return;
                }
                const data   = res.data;
                // Server-Meldungen zuerst (Reihenfolge = Vorrang am selben Feld), dann nur
                // die Browser-Befunde, die der Server nicht selbst gemeldet hat.
                const errors = Object.assign({}, data.errors || {});
                Object.keys(clientErrors).forEach(k => { if (!(k in errors)) errors[k] = clientErrors[k]; });

                applyCorrectedDate(data.corrected_date);

                if (Object.keys(errors).length) {
                    reportErrors(errors, data.bad_teachers);
                    return;
                }

                if (mode === 'pdf') {
                    openPdf(submitter);
                } else if (mode === 'collect') {
                    setStatus('busy', 'Prüfung erfolgreich – die Noteneinsammlung wird gestartet …');
                    submitForReal(submitter, null);
                    // Die Formulardaten sind beim Absenden schon übernommen; ein zweiter
                    // Klick würde nur einen Doppelstart versuchen.
                    setBusy(true);
                } else {
                    setStatus('success', '<strong>✅ Prüfung erfolgreich.</strong> Das Formular ist vollständig ausgefüllt.');
                }
            })
            .catch(err => {
                console.error('Fehler bei der Formularprüfung:', err);
                setBusy(false);
                setStatus('error', '<strong>❌ Die Prüfung konnte nicht durchgeführt werden</strong> (Verbindungsproblem). Bitte erneut versuchen.');
            });
    });

    // Sobald ein markiertes Feld geändert wird, verschwindet seine Fehlermeldung.
    function clearErrorsFor(target) {
        activeErrors = activeErrors.filter(entry => {
            if (entry.anchor === target || entry.anchor.contains(target)) { clearEntry(entry); return false; }
            return true;
        });
        target.classList.remove('mh-error-field');
        if (!activeErrors.some(en => en.msgEl && en.msgEl.className === 'mh-field-error') && statusBox.classList.contains('is-error')) {
            setStatus('', '');
        }
    }
    // Fehler an Feldern, die durch eine andere Auswahl inaktiv geworden sind (gesperrt
    // oder zugeklappt), sind gegenstandslos und verschwinden.
    function pruneInactiveErrors() {
        activeErrors = activeErrors.filter(entry => {
            const el = entry.anchor;
            const inactive = !el.isConnected
                || (!entry.isGroup && el.disabled)
                || !!el.closest('.mh-hidden')
                || el.closest('.toggle-target[style*="opacity: 0.4"]') !== null;
            if (inactive) { clearEntry(entry); return false; }
            return true;
        });
        if (!activeErrors.some(en => en.msgEl && en.msgEl.className === 'mh-field-error') && statusBox.classList.contains('is-error')) {
            setStatus('', '');
        }
    }

    mhForm.addEventListener('input', e => clearErrorsFor(e.target));
    mhForm.addEventListener('change', e => clearErrorsFor(e.target));

    // Nach einem Neuladen mit Fehlern (Fallback ohne AJAX) dieselbe Darstellung nutzen.
    const initialErrors = <?= wp_json_encode( (object) $form_errors ) ?>;
    if (Object.keys(initialErrors).length) {
        // Erst nach Toggles und Schülerliste markieren, sonst gelten Bereiche als verborgen.
        setTimeout(() => showErrors(initialErrors, []), 400);
    }

});
</script>