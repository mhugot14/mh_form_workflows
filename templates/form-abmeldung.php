<?php
// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';
?>

<style>
    /* --- CSS RESET & ISOLATION (Verschärft) --- */
    .mh-form-wrapper {
        max-width: 900px;
        margin: 0 auto;
        box-sizing: border-box;
        font-family: inherit;
    }
    
    /* Zwinge alle Elemente im Formular zur Box-Sizing Order */
    .mh-form-wrapper *, 
    .mh-form-wrapper *::before, 
    .mh-form-wrapper *::after {
        box-sizing: border-box !important;
        float: none !important; /* Floats töten */
        position: static !important; /* Absolute Positionierung töten */
    }

    /* Ausnahme für Tooltips (die brauchen position absolute) */
    .mh-form-wrapper .mh-info-icon,
    .mh-form-wrapper .mh-info-icon::after,
    .mh-form-wrapper .mh-info-icon::before {
        position: relative !important;
    }
    .mh-form-wrapper .mh-info-icon:hover::after,
    .mh-form-wrapper .mh-info-icon:hover::before {
        position: absolute !important;
    }

    /* --- LAYOUT SEKTIONEN --- */
    .mh-form-section {
        background: #f9f9f9;
        border: 1px solid #ccc;
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 4px;
        display: block !important;
        width: 100% !important;
    }

    .mh-form-section h4 {
        margin-top: 0 !important;
        margin-bottom: 20px !important;
        padding-bottom: 10px !important;
        border-bottom: 1px solid #ddd !important;
        color: #333;
        clear: both;
    }

    /* --- GRID SYSTEM (Stabilisiert) --- */
    .mh-grid-row {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
        gap: 20px !important;
        margin-bottom: 15px !important;
        width: 100% !important;
    }
    .mh-grid-2 { grid-template-columns: 1fr 1fr !important; }
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr !important; }
    
    @media (max-width: 768px) {
        .mh-grid-2, .mh-grid-3 { grid-template-columns: 1fr !important; }
    }

    /* --- INPUT GROUPS (Der Layout Retter) --- */
    .mh-input-group {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        margin-bottom: 0 !important;
        position: relative !important;
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
    }

    /* Labels: Expliziter Block, Margin nur unten */
    .mh-input-group label {
        display: block !important;
        width: 100% !important;
        margin: 0 0 5px 0 !important;
        padding: 0 !important;
        font-weight: bold;
        line-height: 1.4 !important;
        height: auto !important;
    }

    /* Inputs: Zwinge Margin 0, damit sie nicht hochrutschen */
    .mh-input-group input[type="text"], 
    .mh-input-group input[type="date"], 
    .mh-input-group input[type="number"], 
    .mh-input-group select {
        display: block !important;
        width: 100% !important;
        height: 40px !important;
        padding: 6px 12px !important;
        margin: 0 !important; /* WICHTIG: Theme Reset */
        border: 1px solid #aaa !important;
        background-color: #fff !important;
        border-radius: 4px !important;
        box-shadow: none !important;
        font-size: 15px !important;
        line-height: normal !important;
    }
    
    /* Readonly Felder */
    .mh-input-group input[readonly] {
        background-color: #e9e9e9 !important;
        color: #555 !important;
        cursor: not-allowed;
    }

    .mh-fake-input {
        display: flex !important;
        align-items: center;
        height: 40px;
        width: 100%;
        background: #e9e9e9;
        border: 1px solid #aaa;
        border-radius: 4px;
        padding: 0 10px;
        color: #555;
    }

    /* --- RADIO BUTTONS (Horizontal) --- */
    .radio-group {
        display: flex !important;
        flex-direction: row !important;
        align-items: flex-start !important;
        margin-bottom: 8px !important;
        gap: 10px !important;
    }
    .radio-group input {
        width: 18px !important;
        height: 18px !important;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        flex-shrink: 0;
    }
    .radio-group label {
        font-weight: normal !important;
        margin: 0 !important;
        display: inline-block !important;
        line-height: 1.4 !important;
    }

    /* --- TOOLTIP --- */
    .mh-info-icon {
        display: inline-block;
        width: 18px; height: 18px;
        background: #0073aa; color: #fff;
        border-radius: 50%;
        text-align: center; line-height: 18px;
        font-size: 12px; font-weight: bold;
        cursor: help;
        margin-left: 5px;
    }
    .mh-info-icon:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 25px; left: -100px;
        width: 250px;
        padding: 10px;
        background: #333; color: #fff;
        font-size: 12px; font-weight: normal;
        line-height: 1.4;
        border-radius: 4px;
        z-index: 9999;
    }

    /* --- SONSTIGES --- */
    .mh-sub-group {
        margin-left: 28px;
        padding: 15px;
        border-left: 3px solid #ddd;
        background: #fff;
        margin-bottom: 15px;
        margin-top: 5px;
    }
    #protocol_wrapper { border-left: 5px solid #0073aa; }
    .mh-hidden { display: none !important; }
    .toggle-target { transition: opacity 0.3s ease; }
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 12px 24px !important; cursor: pointer; }
    
    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; }
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box"><h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3></div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">⚠️ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;"><?php foreach($form_errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- SEKTION 1: Stammdaten -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            <div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group"><label>Nachname <span class="req">*</span></label><input type="text" name="lastname" required class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>"></div>
                <div class="mh-input-group"><label>Vorname <span class="req">*</span></label><input type="text" name="firstname" required class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>"></div>
                <div class="mh-input-group"><label>Geburtsdatum <span class="req">*</span></label><input type="date" name="dob" id="field_dob" required class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>"></div>
            </div>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group"><label>Klasse <span class="req">*</span></label><input type="text" name="class_name" required class="<?= $err_cls('class_name') ?>" value="<?= $val('class_name') ?>"></div>
                <div class="mh-input-group"><label>Klassenlehrer/in (Kürzel)</label><input type="text" name="teacher" class="<?= $err_cls('teacher') ?>" value="<?= $val('teacher') ?>"></div>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Status (berechnet)</label>
                    <div class="mh-fake-input">
                        <span id="status_display">...</span>
                        <input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>">
                    </div>
                </div>
                
                <div class="mh-input-group">
                    <label>
                        Datum der Abmeldung / Kündigung <span class="req">*</span>
                        <span class="mh-info-icon" data-tooltip="Bei BerufsschülerInnen zählt das Datum, zu dem das Arbeitsverhältnis endet, bei Vollzeit-SchülerInnen das Abmeldedatum.">?</span>
                    </label>
                    <input type="date" name="date_off" id="field_date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 2: Grund -->
        <div class="mh-form-section">
            <h4>1. Grund der Abmeldung <span class="req">*</span></h4>
            <div class="radio-group">
                <input type="radio" name="reason" value="schulwechsel" id="r_wechsel" class="toggle-trigger" data-target="new_school_wrap" required <?= $chk('reason', 'schulwechsel') ?>> 
                <label for="r_wechsel">Schulwechsel (Name & Ort der aufnehmenden Schule)</label>
            </div>
            
            <div id="new_school_wrap" class="mh-sub-group toggle-target">
                <div class="mh-input-group"><label>Name und Ort der Schule <span class="req">*</span></label><input type="text" name="new_school" placeholder="z.B. Muster-Berufskolleg" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>"></div>
            </div>

            <div class="radio-group"><input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> <label for="r_beschl">Ausschulung Beschluss Teilkonferenz</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label></div>
            <div class="radio-group"><input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> <label for="r_abm">Abmeldung</label></div>
        </div>

        <!-- SEKTION 3: Schulpflicht -->
        <div class="mh-form-section">
            <h4>2. Schulpflicht <span class="req">*</span></h4>
            <div class="radio-group"><input type="radio" name="compulsory" value="fulfilled" id="c_full" class="toggle-trigger" required <?= $chk('compulsory', 'fulfilled') ?>> <label for="c_full">Die Schulpflicht ist erfüllt.</label></div>
            <div class="radio-group"><input type="radio" name="compulsory" value="not_fulfilled" id="c_not" class="toggle-trigger" <?= $chk('compulsory', 'not_fulfilled') ?>> <label for="c_not">Die Schulpflicht ist NICHT erfüllt (Schulpflichtverfolgung...).</label></div>
            
            <div class="radio-group">
                <input type="radio" name="compulsory" value="av_klasse" id="c_av" class="toggle-trigger" data-target="av_details" <?= $chk('compulsory', 'av_klasse') ?>> 
                <label for="c_av">Wechsel in AV-Klasse</label>
            </div>
            <div id="av_details" class="mh-sub-group toggle-target">
                <div class="mh-grid-row mh-grid-3">
                    <div class="mh-input-group"><label>Zum Datum <span class="req">*</span></label><input type="date" name="av_date_start" value="<?= $val('av_date_start') ?>"></div>
                    <div class="mh-input-group"><label>Gespräch mit <span class="req">*</span></label><input type="text" name="av_talk_with" placeholder="Lehrername" value="<?= $val('av_talk_with') ?>"></div>
                    <div class="mh-input-group"><label>am <span class="req">*</span></label><input type="date" name="av_talk_date" value="<?= $val('av_talk_date') ?>"></div>
                </div>
            </div>

            <div class="radio-group">
                <input type="radio" name="compulsory" value="bildungsgang" id="c_bg" class="toggle-trigger" data-target="bg_details" <?= $chk('compulsory', 'bildungsgang') ?>> 
                <label for="c_bg">Wechsel in den Bildungsgang...</label>
            </div>
            <div id="bg_details" class="mh-sub-group toggle-target">
                <div class="mh-input-group"><label>Name des Bildungsgangs <span class="req">*</span></label><input type="text" name="new_education_track" value="<?= $val('new_education_track') ?>"></div>
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis -->
        <div class="mh-form-section">
            <h4>3. Zeugnis</h4>
            
            <div class="radio-group">
                <input type="radio" name="certificate" value="abgang" id="z_ab" class="toggle-trigger" <?= $chk('certificate', 'abgang') ?>>
                <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <small style="color:#666;">(Ohne Abschluss)</small></label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="toggle-trigger" data-target="missed_hours_wrapper" <?= $chk('certificate', 'ueberweisung') ?>>
                <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small style="color:#666;">(Wechsel innerhalb Schulstufe)</small></label>
            </div>

            <div id="missed_hours_wrapper" class="mh-grid-row mh-grid-2 toggle-target" style="margin-top:15px; background: #fff; padding: 10px; border-left: 3px solid #ddd;">
                <div class="mh-input-group">
                    <label>Fehlstunden Gesamt <span class="req">*</span></label>
                    <input type="number" name="missed_hours" value="<?= $val('missed_hours') ?>">
                </div>
                <div class="mh-input-group">
                    <label>davon unentschuldigt <span class="req">*</span></label>
                    <input type="number" name="missed_ue" value="<?= $val('missed_ue') ?>">
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ccc;">
                <div class="radio-group">
                    <input type="checkbox" name="protocol_attached" value="1" id="chk_protocol" class="toggle-trigger" data-target="protocol_wrapper" <?= $chk('protocol_attached', '1') ?>>
                    <label for="chk_protocol" style="font-weight:bold;">Zeugniskonferenzprotokoll beifügen</label>
                </div>
            </div>
        </div>

        <!-- SEKTION 5: Protokoll -->
        <div id="protocol_wrapper" class="mh-form-section toggle-target mh-collapsible-section" style="border-left: 5px solid #0073aa;">
            <h4>4. Angaben zum Konferenzprotokoll</h4>
            <p style="font-size:0.9em; color:#555;">Bitte wählen Sie den Typ der Konferenz, um die entsprechenden Felder anzuzeigen.</p>

            <div class="radio-group">
                <input type="radio" name="prot_type" value="berufsschule" id="pt_bs" class="toggle-trigger" data-target="prot_bs_fields" <?= $chk('prot_type', 'berufsschule') ?>>
                <label for="pt_bs"><b>Berufsschule</b> (Teilzeit)</label>
            </div>
            <div class="radio-group">
                <input type="radio" name="prot_type" value="vollzeit" id="pt_vz" class="toggle-trigger" data-target="prot_vz_fields" <?= $chk('prot_type', 'vollzeit') ?>>
                <label for="pt_vz"><b>Vollzeit</b> (Abgang / Überweisung)</label>
            </div>

            <div class="mh-grid-row mh-grid-3" style="margin-top:20px;">
                <div class="mh-input-group"><label>Konferenzdatum <span class="req">*</span></label><input type="date" name="prot_date" id="field_prot_date" readonly value="<?= $val('prot_date') ?>"></div>
                <div class="mh-input-group"><label>Ausgabedatum <span class="req">*</span></label><input type="date" name="prot_issue_date" id="field_prot_issue_date" readonly value="<?= $val('prot_issue_date') ?>"></div>
                <div class="mh-input-group"><label>Vorsitzende/r <span class="req">*</span></label><input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>"></div>
            </div>
            
            <div class="mh-grid-row mh-grid-2">
                 <div class="mh-input-group"><label>Raum</label><input type="text" name="prot_room" value="<?= $val('prot_room') ?>"></div>
                 <div></div> 
            </div>

            <div class="mh-input-group" style="margin-bottom: 15px;">
                <label>Beschlussfassung / Bemerkungen:</label>
                <textarea name="prot_remarks" style="width:100%; height:80px; padding:10px; border:1px solid #aaa; border-radius:4px; font-family:inherit; margin:0 !important;"><?= $val('prot_remarks') ?></textarea>
            </div>

            <div id="prot_vz_fields" class="mh-sub-group toggle-target">
                <h5 style="margin-top:0;">Details Vollzeit</h5>
                <div class="mh-grid-row mh-grid-2">
                     <div class="mh-input-group"><label>Ende des Schulverhältnisses:</label><input type="date" name="prot_end_school" value="<?= $val('prot_end_school') ?>"></div>
                     <div class="mh-input-group" style="justify-content:center;">
                        <div class="radio-group">
                            <input type="checkbox" name="prot_check_compulsory" value="1" id="p_chk_c" <?= $chk('prot_check_comp', '1') ?>>
                            <label for="p_chk_c">Schulpflicht überprüft?</label>
                        </div>
                    </div>
                </div>
                 <div class="mh-input-group"><label>Überwiesen an folgende Schule:</label><input type="text" name="prot_transfer" value="<?= $val('prot_transfer') ?>"></div>
            </div>

            <div id="prot_bs_fields" class="mh-sub-group toggle-target">
                <h5 style="margin-top:0;">Details Berufsschule</h5>
                <p style="margin:0; font-size:0.9em; font-style:italic;">Keine weiteren Eingaben notwendig.</p>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular prüfen</button>
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ALTER
    const dobInput = document.getElementById('field_dob');
    const dateOffInput = document.getElementById('field_date_off'); 
    const protDateInput = document.getElementById('field_prot_date'); 
    const protIssueInput = document.getElementById('field_prot_issue_date'); 
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');

    function calcAge() {
        if(!dobInput.value) return;
        const dob = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }

        let outputHtml = '';
        let isMinor = 0;

        if (age < 18) {
            outputHtml = '<span style="color:#d63638; font-weight:bold;">Minderjährig</span> (' + age + ')';
            isMinor = 1;
        } else {
            let schoolYearStartYear = today.getFullYear();
            if (today.getMonth() < 7) { schoolYearStartYear--; }
            
            const schoolStart = new Date(schoolYearStartYear, 7, 1);
            let ageAtSchoolStart = schoolYearStartYear - dob.getFullYear();
            const mStart = 7 - dob.getMonth(); 
            if (mStart < 0 || (mStart === 0 && 1 < dob.getDate())) { ageAtSchoolStart--; }

            outputHtml = '<span style="color:#46b450; font-weight:bold;">Volljährig</span> (' + age + ')';
            if (ageAtSchoolStart >= 18) { outputHtml += '<br><small>(Zu Schuljahresbeginn schon volljährig)</small>'; } 
            else { outputHtml += '<br><small>(Zu Schuljahresbeginn <u style="color:#d63638">nicht</u> volljährig)</small>'; }
            isMinor = 0;
        }
        
        statusDisplay.innerHTML = outputHtml;
        statusInput.value = isMinor;
    }

    if(dobInput) { 
        dobInput.addEventListener('change', calcAge); 
        if(dobInput.value) calcAge(); 
    }

    // Sync Dates
    function syncDates() {
        if(!dateOffInput) return;
        const val = dateOffInput.value;
        if(protDateInput) protDateInput.value = val;
        if(protIssueInput) protIssueInput.value = val;
    }
    if(dateOffInput) {
        dateOffInput.addEventListener('change', syncDates);
        if(dateOffInput.value && !protDateInput.value) syncDates();
    }

    // TOGGLE LOGIC (Recursive)
    const triggers = document.querySelectorAll('.toggle-trigger');
    const allTargets = document.querySelectorAll('.toggle-target');

    function updateToggles() {
        let activeTargetIds = new Set();
        triggers.forEach(tr => {
            if(tr.checked && tr.dataset.target) activeTargetIds.add(tr.dataset.target);
        });

        allTargets.forEach(target => {
            const isActive = activeTargetIds.has(target.id);
            let parent = target.parentElement.closest('.toggle-target');
            let isParentInactive = false;
            if(parent) {
                if (parent.style.opacity === '0.5' || parent.classList.contains('mh-hidden')) {
                    isParentInactive = true;
                }
            }

            if (!isActive || isParentInactive) {
                // INAKTIV
                if (target.classList.contains('mh-collapsible-section')) {
                    target.classList.add('mh-hidden');
                } else {
                    target.style.opacity = '0.5';
                    target.style.pointerEvents = 'none';
                }
                target.querySelectorAll('input, select').forEach(i => { i.disabled = true; i.required = false; });
            } else {
                // AKTIV
                target.classList.remove('mh-hidden');
                target.style.opacity = '1';
                target.style.pointerEvents = 'auto';
                
                const inputs = target.querySelectorAll('input, select, textarea'); // Auch Textarea beachten!
                inputs.forEach(i => {
                    const closestTarget = i.closest('.toggle-target');
                    if (closestTarget.id === target.id) {
                        i.disabled = false;
                        if(i.type !== 'hidden' && i.type !== 'checkbox' && i.tagName !== 'TEXTAREA') i.required = true;
                    }
                });
            }
        });
    }

    triggers.forEach(radio => radio.addEventListener('change', updateToggles));
    
    // Init
    updateToggles();
});
</script>