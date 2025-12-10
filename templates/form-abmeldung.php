<?php
// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : ( isset($form_data[$key]) && $is_success ? 'mh-valid-field' : '' );
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';
?>

<style>
    /* --- ISOLATION ROOT --- */
    .mh-form-wrapper {
        max-width: 900px;
        margin: 0 auto;
        /* Box-Sizing Reset für alles innerhalb des Formulars */
        box-sizing: border-box; 
    }
    .mh-form-wrapper *, 
    .mh-form-wrapper *::before, 
    .mh-form-wrapper *::after {
        box-sizing: border-box !important;
    }

    /* --- LAYOUT CONTAINER --- */
    .mh-form-section {
        background: #f9f9f9;
        border: 1px solid #ccc;
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 4px;
    }

    .mh-form-section h4 {
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
        font-size: 1.2em;
        color: #333;
        clear: both;
    }

    /* --- GRID SYSTEM --- */
    .mh-grid-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 15px;
    }
    .mh-grid-2 { grid-template-columns: 1fr 1fr; } 
    .mh-grid-3 { grid-template-columns: 1fr 1fr 1fr; }

    /* Falls der Bildschirm zu klein ist, Grid auf 1 Spalte zwingen */
    @media (max-width: 700px) {
        .mh-grid-2, .mh-grid-3 { grid-template-columns: 1fr !important; }
    }

    /* --- DIE LÖSUNG GEGEN ZERSCHOSSENES LAYOUT (.mh-input-group) --- */
    .mh-input-group {
        display: flex !important;
        flex-direction: column !important; /* Zwingt Label nach OBEN und Input nach UNTEN */
        align-items: flex-start !important;
        justify-content: flex-start !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }

    /* Labels hart resetten */
    .mh-input-group label {
        display: block !important;
        width: 100% !important;
        margin: 0 0 5px 0 !important; /* Nur Abstand nach unten */
        padding: 0 !important;
        float: none !important;
        text-align: left !important;
        font-weight: bold;
        line-height: 1.3;
    }

    /* Inputs hart resetten */
    .mh-input-group input[type="text"],
    .mh-input-group input[type="date"],
    .mh-input-group input[type="number"],
    .mh-input-group select {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: 42px !important;
        padding: 0 10px !important;
        margin: 0 !important;
        border: 1px solid #aaa !important;
        background-color: #fff !important;
        border-radius: 4px !important;
        float: none !important;
        box-shadow: none !important;
        position: static !important; /* Gegen Themes mit position:absolute inputs */
    }

    /* Readonly Fake-Input */
    .mh-fake-input {
        display: flex;
        align-items: center;
        height: 42px;
        width: 100%;
        background: #e9e9e9;
        border: 1px solid #aaa;
        border-radius: 4px;
        padding: 0 10px;
        color: #555;
    }

    /* --- RADIO & CHECKBOX --- */
    .radio-group {
        display: flex !important;
        flex-direction: row !important; /* Nebeneinander */
        align-items: flex-start !important;
        margin-bottom: 8px;
        gap: 10px;
    }
    
    .radio-group input[type="radio"],
    .radio-group input[type="checkbox"] {
        margin-top: 4px !important;
        width: 18px !important;
        height: 18px !important;
        flex-shrink: 0;
        cursor: pointer;
        display: inline-block !important;
        float: none !important;
    }
    
    .radio-group label {
        font-weight: normal !important;
        margin: 0 !important;
        display: inline-block !important;
        cursor: pointer;
        width: auto !important;
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
    
    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    
    .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-group button { height: auto !important; padding: 12px 24px !important; cursor: pointer; }

    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
    .mh-valid-field { border-color: #46b450 !important; background-color: #f6fff7 !important; }
    .mh-success-box { background: #fff; border-left: 5px solid #46b450; padding: 20px; margin-bottom: 30px; }
    .mh-error-box { background: #fff; border-left: 5px solid #d63638; padding: 20px; margin-bottom: 30px; }
</style>

<div class="mh-form-wrapper">

    <?php if ( $is_success ): ?>
        <div class="mh-success-box">
            <h3 style="margin-top:0; color:#46b450;">✅ Prüfung erfolgreich!</h3>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $form_errors ) ): ?>
        <div class="mh-error-box">
             <h3 style="margin-top:0; color:#d63638;">⚠️ Bitte korrigieren:</h3>
             <ul style="margin-bottom:0; padding-left:20px;">
                 <?php foreach($form_errors as $e) echo "<li>$e</li>"; ?>
             </ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" id="mh-abmeldung-form">
        <input type="hidden" name="action" value="mh_submit_form">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <!-- SEKTION 1: Stammdaten -->
        <div class="mh-form-section">
            <h4>Schülerdaten</h4>
            
            <div class="mh-grid-row mh-grid-3">
                <div class="mh-input-group">
                    <label>Nachname <span class="req">*</span></label>
                    <input type="text" name="lastname" required class="<?= $err_cls('lastname') ?>" value="<?= $val('lastname') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Vorname <span class="req">*</span></label>
                    <input type="text" name="firstname" required class="<?= $err_cls('firstname') ?>" value="<?= $val('firstname') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Geburtsdatum <span class="req">*</span></label>
                    <input type="date" name="dob" id="field_dob" required class="<?= $err_cls('dob') ?>" value="<?= $val('dob') ?>">
                </div>
            </div>

            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Klasse <span class="req">*</span></label>
                    <input type="text" name="class_name" required class="<?= $err_cls('class_name') ?>" value="<?= $val('class_name') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Klassenlehrer/in (Kürzel)</label>
                    <input type="text" name="teacher" class="<?= $err_cls('teacher') ?>" value="<?= $val('teacher') ?>">
                </div>
            </div>

            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Status</label>
                    <div class="mh-fake-input">
                        <span id="status_display">...</span>
                        <input type="hidden" name="is_minor" id="input_is_minor" value="<?= $val('is_minor') ?>">
                    </div>
                </div>
                <div class="mh-input-group">
                    <label>Datum der Abmeldung <span class="req">*</span></label>
                    <input type="date" name="date_off" required value="<?= $val('date_off') ?: date('Y-m-d') ?>">
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
                <div class="mh-input-group">
                    <label>Name und Ort der Schule <span class="req">*</span></label>
                    <input type="text" name="new_school" placeholder="z.B. Muster-Berufskolleg" class="<?= $err_cls('new_school') ?>" value="<?= $val('new_school') ?>">
                </div>
            </div>

            <div class="radio-group">
                <input type="radio" name="reason" value="aufloesung" id="r_aufl" class="toggle-trigger" <?= $chk('reason', 'aufloesung') ?>> 
                <label for="r_aufl">Auflösung Ausbildungsvertrag / Beendigung Verhältnis</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="ausschulung_beschluss" id="r_beschl" class="toggle-trigger" <?= $chk('reason', 'ausschulung_beschluss') ?>> 
                <label for="r_beschl">Ausschulung Beschluss Teilkonferenz</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="ausschulung_47" id="r_47" class="toggle-trigger" <?= $chk('reason', 'ausschulung_47') ?>> 
                <label for="r_47">Ausschulung nach §47 Abs. 1 Nr. 8 SchulG (20 Tage)</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="reason" value="abmeldung" id="r_abm" class="toggle-trigger" <?= $chk('reason', 'abmeldung') ?>> 
                <label for="r_abm">Abmeldung</label>
            </div>
        </div>

        <!-- SEKTION 3: Schulpflicht -->
        <div class="mh-form-section">
            <h4>2. Schulpflicht <span class="req">*</span></h4>
            
            <div class="radio-group">
                <input type="radio" name="compulsory" value="fulfilled" id="c_full" class="toggle-trigger" required <?= $chk('compulsory', 'fulfilled') ?>> 
                <label for="c_full">Die Schulpflicht ist erfüllt.</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="compulsory" value="not_fulfilled" id="c_not" class="toggle-trigger" <?= $chk('compulsory', 'not_fulfilled') ?>> 
                <label for="c_not">Die Schulpflicht ist NICHT erfüllt (Schulpflichtverfolgung...).</label>
            </div>

            <div class="radio-group">
                <input type="radio" name="compulsory" value="av_klasse" id="c_av" class="toggle-trigger" data-target="av_details" <?= $chk('compulsory', 'av_klasse') ?>> 
                <label for="c_av">Wechsel in AV-Klasse</label>
            </div>
            
            <div id="av_details" class="mh-sub-group toggle-target">
                <div class="mh-grid-row mh-grid-3">
                    <div class="mh-input-group">
                        <label>Zum Datum <span class="req">*</span></label>
                        <input type="date" name="av_date_start" value="<?= $val('av_date_start') ?>">
                    </div>
                    <div class="mh-input-group">
                        <label>Gespräch mit <span class="req">*</span></label>
                        <input type="text" name="av_talk_with" placeholder="Lehrername" value="<?= $val('av_talk_with') ?>">
                    </div>
                    <div class="mh-input-group">
                        <label>am <span class="req">*</span></label>
                        <input type="date" name="av_talk_date" value="<?= $val('av_talk_date') ?>">
                    </div>
                </div>
            </div>

            <div class="radio-group">
                <input type="radio" name="compulsory" value="bildungsgang" id="c_bg" class="toggle-trigger" data-target="bg_details" <?= $chk('compulsory', 'bildungsgang') ?>> 
                <label for="c_bg">Wechsel in den Bildungsgang...</label>
            </div>
            <div id="bg_details" class="mh-sub-group toggle-target">
                <div class="mh-input-group">
                    <label>Name des Bildungsgangs <span class="req">*</span></label>
                    <input type="text" name="new_education_track" value="<?= $val('new_education_track') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 4: Zeugnis -->
        <div class="mh-form-section">
            <h4>3. Zeugnis</h4>
             <div class="radio-group" style="padding-bottom:15px; margin-bottom:15px; border-bottom:1px dashed #ccc;">
                <input type="checkbox" name="protocol_attached" value="1" id="chk_protocol" <?= $chk('protocol_attached', '1') ?>>
                <label for="chk_protocol" style="font-weight:bold;">Zeugniskonferenzprotokoll beifügen</label>
             </div>

             <div class="radio-group">
                <input type="radio" name="certificate" value="ueberweisung" id="z_ue" class="cert-trigger toggle-trigger" <?= $chk('certificate', 'ueberweisung') ?>>
                <label for="z_ue">Überweisungszeugnis gem. § 49 SchulG <small style="color:#666;">(Wechsel innerhalb Schulstufe)</small></label>
            </div>
             
             <div class="radio-group">
                <input type="radio" name="certificate" value="abgang" id="z_ab" class="cert-trigger toggle-trigger" <?= $chk('certificate', 'abgang') ?>>
                <label for="z_ab">Abgangszeugnis gem. § 49 SchulG <small style="color:#666;">(Ohne Abschluss)</small></label>
            </div>
            
            <div class="mh-grid-row mh-grid-2" style="margin-top:15px;">
                <div class="mh-input-group">
                    <label>Fehlstunden Gesamt <span class="req" id="req_hours" style="display:none">*</span></label>
                    <input type="number" name="missed_hours" class="hours-input" disabled value="<?= $val('missed_hours') ?>">
                </div>
                <div class="mh-input-group">
                    <label>davon unentschuldigt <span class="req" id="req_ue" style="display:none">*</span></label>
                    <input type="number" name="missed_ue" class="hours-input" disabled value="<?= $val('missed_ue') ?>">
                </div>
            </div>
        </div>
<!-- ... SEKTION 4: Protokoll (Erweiterung) ... -->
        
        <!-- Diesen Block UNTER dem Checkbox div für Protokoll einfügen oder als neue Sektion -->
        <!-- Wir nutzen hier data-target="protocol_wrapper", damit es aufgeht, wenn Checkbox aktiv -->
        
        <!-- ÄNDERUNG: Gib der Checkbox oben im Zeugnis-Bereich die Klasse "toggle-trigger" und data-target -->
        <!-- <input type="checkbox" ... id="chk_protocol" class="toggle-trigger" data-target="protocol_wrapper"> -->
        
        <div id="protocol_wrapper" class="mh-form-section toggle-target" style="border-left: 5px solid #0073aa;">
            <h4>4. Angaben zum Konferenzprotokoll</h4>
            <p>Bitte wählen Sie den Typ der Konferenz, um die entsprechenden Felder anzuzeigen.</p>

            <div class="radio-group">
                <input type="radio" name="prot_type" value="berufsschule" id="pt_bs" class="toggle-trigger" data-target="prot_bs_fields" <?= $chk('prot_type', 'berufsschule') ?>>
                <label for="pt_bs"><b>Berufsschule</b> (Teilzeit)</label>
            </div>
            
            <div class="radio-group">
                <input type="radio" name="prot_type" value="vollzeit" id="pt_vz" class="toggle-trigger" data-target="prot_vz_fields" <?= $chk('prot_type', 'vollzeit') ?>>
                <label for="pt_vz"><b>Vollzeit</b> (Abgang / Überweisung)</label>
            </div>

            <!-- GEMEINSAME FELDER -->
            <div class="mh-grid-row mh-grid-3" style="margin-top:20px;">
                <div class="mh-input-group">
                    <label>Konferenzdatum <span class="req">*</span></label>
                    <input type="date" name="prot_date" value="<?= $val('prot_date') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Raum</label>
                    <input type="text" name="prot_room" value="<?= $val('prot_room') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Vorsitzende/r <span class="req">*</span></label>
                    <input type="text" name="prot_chair" value="<?= $val('prot_chair') ?>">
                </div>
            </div>

            <!-- SPEZIFISCH: VOLLZEIT -->
            <div id="prot_vz_fields" class="mh-sub-group toggle-target">
                <h5>Details Vollzeit</h5>
                <div class="mh-grid-row mh-grid-2">
                     <div class="mh-input-group">
                        <label>Ende des Schulverhältnisses:</label>
                        <input type="date" name="prot_end_school" value="<?= $val('prot_end_school') ?>">
                    </div>
                     <div class="mh-input-group" style="flex-direction: row !important; align-items: center;">
                        <input type="checkbox" name="prot_check_compulsory" value="1" id="p_chk_c" <?= $chk('prot_check_comp', '1') ?> style="width:20px !important; margin:0 10px 0 0 !important;">
                        <label for="p_chk_c" style="margin:0 !important;">Schulpflicht überprüft?</label>
                    </div>
                </div>
                 <div class="mh-input-group">
                    <label>Überwiesen an folgende Schule:</label>
                    <input type="text" name="prot_transfer" value="<?= $val('prot_transfer') ?>">
                </div>
            </div>

            <!-- SPEZIFISCH: BERUFSSCHULE -->
            <div id="prot_bs_fields" class="mh-sub-group toggle-target">
                <h5>Details Berufsschule</h5>
                <p><i>Für die Berufsschule werden die Standardtexte und die Tabelle für nicht erreichte Abschlüsse generiert. Keine weiteren Eingaben hier nötig.</i></p>
            </div>
        </div>
        <div class="btn-group">
            <button type="submit" name="submit_mode" value="check" class="button button-secondary button-large">Formular prüfen</button>
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Prüfen & PDF erstellen</button>
        </div>
    </form>
</div>

<!-- JAVASCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Generic Toggle + Required Logic
    const triggers = document.querySelectorAll('.toggle-trigger');
    const targets  = document.querySelectorAll('.toggle-target');

    function updateToggles() {
        targets.forEach(el => {
            const inputs = el.querySelectorAll('input, select');
            inputs.forEach(i => {
                i.disabled = true;
                i.required = false; 
            });
            el.style.opacity = '0.5';
            el.style.pointerEvents = 'none';
        });

        triggers.forEach(radio => {
            if(radio.checked && radio.dataset.target) {
                const targetId = radio.dataset.target;
                const targetEl = document.getElementById(targetId);
                if(targetEl) {
                    targetEl.style.opacity = '1';
                    targetEl.style.pointerEvents = 'auto';
                    const inputs = targetEl.querySelectorAll('input, select');
                    inputs.forEach(i => {
                        i.disabled = false;
                        if(i.type !== 'hidden') i.required = true;
                    });
                }
            }
        });
    }
    triggers.forEach(radio => radio.addEventListener('change', updateToggles));
    
    // 2. Fehlstunden Logic
    const certRadios = document.querySelectorAll('.cert-trigger');
    const hourInputs = document.querySelectorAll('.hours-input');
    const reqLabels  = [document.getElementById('req_hours'), document.getElementById('req_ue')];

    function updateHours() {
        let isUe = false;
        certRadios.forEach(r => { if(r.checked && r.value === 'ueberweisung') isUe = true; });
        
        hourInputs.forEach(i => {
            i.disabled = !isUe;
            i.required = isUe; 
            if(!isUe) i.style.backgroundColor = '#eee';
            else i.style.backgroundColor = '#fff';
        });

        reqLabels.forEach(span => {
            if(span) span.style.display = isUe ? 'inline' : 'none';
        });
    }
    certRadios.forEach(r => r.addEventListener('change', updateHours));

    // 3. Alter Logic
    const dobInput = document.getElementById('field_dob');
    const statusDisplay = document.getElementById('status_display');
    const statusInput = document.getElementById('input_is_minor');

    function calcAge() {
        if(!dobInput.value) return;
        const dob = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }

        if (age < 18) {
            statusDisplay.innerHTML = '<span style="color:#d63638; font-weight:bold;">Minderjährig</span> (' + age + ')';
            statusInput.value = '1';
        } else {
            statusDisplay.innerHTML = '<span style="color:#46b450; font-weight:bold;">Volljährig</span> (' + age + ')';
            statusInput.value = '0';
        }
    }
    if(dobInput) {
        dobInput.addEventListener('change', calcAge);
        if(dobInput.value) calcAge();
    }

    updateToggles();
    setTimeout(() => { updateHours(); }, 100);
});
</script>