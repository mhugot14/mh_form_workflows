<?php
// Helper
$val = fn($key) => isset($form_data[$key]) ? esc_attr($form_data[$key]) : '';
$err_cls = fn($key) => isset($form_errors[$key]) ? 'mh-error-field' : '';
$chk = fn($key, $val) => (isset($form_data[$key]) && $form_data[$key] == $val) ? 'checked' : '';
?>

<style>
    /* CSS RESET & LAYOUT */
    .mh-form-wrapper { max-width: 950px; margin: 0 auto; box-sizing: border-box; font-family: inherit; }
    .mh-form-wrapper * { box-sizing: border-box !important; float: none !important; }
    
    .mh-section { background:#f9f9f9; border:1px solid #ccc; padding:25px; margin-bottom:25px; border-radius:4px; }
    .mh-section h3 { margin-top:0; border-bottom:1px solid #ddd; padding-bottom:15px; margin-bottom: 20px; color:#333; font-size: 1.3em; }
    .mh-section h4 { margin-top:0; color:#0073aa; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; border-bottom: none; }

    .mh-grid-row { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important; gap: 20px !important; margin-bottom: 15px !important; width: 100% !important; }
    .mh-grid-2 { grid-template-columns: 1fr 1fr !important; }
    @media (max-width: 700px) { .mh-grid-2 { grid-template-columns: 1fr !important; } }

    .mh-input-group { display: flex !important; flex-direction: column !important; width: 100% !important; margin-bottom: 0 !important; }
    .mh-input-group label { display: block !important; width: 100% !important; margin: 0 0 6px 0 !important; font-weight: bold; line-height: 1.3; }
    
    .mh-input-group input[type="text"], 
    .mh-input-group input[type="date"], 
    .mh-input-group select, 
    .mh-input-group textarea {
        display: block !important; width: 100% !important; height: 42px !important; padding: 0 12px !important; margin: 0 !important; border: 1px solid #aaa !important; background-color: #fff !important; border-radius: 4px !important; font-size: 15px !important;
    }
    .mh-input-group textarea { height: auto !important; padding-top: 10px !important; }

    .reason-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
    .reason-col { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    
    .mh-radio-row { margin-bottom: 8px; display: flex; align-items: flex-start; gap: 10px; }
    .mh-radio-row label { font-weight: normal !important; font-size: 0.95em; cursor: pointer; }
    .mh-radio-row input { margin-top: 4px !important; width: 16px !important; height: 16px !important; flex-shrink: 0; cursor: pointer; }

    .sub-table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; }
    .sub-table th, .sub-table td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: middle; }
    .sub-table th { background: #eee; font-weight: bold; font-size: 0.9em; }
    .sub-table input { width: 100% !important; border: 1px solid transparent !important; background: transparent !important; height: 30px !important; }
    .sub-table input:focus { border: 1px solid #0073aa !important; background: #fff !important; }

    .req { color: #d63638; font-weight: bold; margin-left: 3px; }
    .mh-error-field { border-color: #d63638 !important; background-color: #fff5f5 !important; }
    .btn-group { margin-top: 30px; }
    .btn-group button { padding: 12px 24px !important; height: auto !important; font-size: 1.1em !important; cursor: pointer; }
    
    .mh-form-wrapper input, .mh-form-wrapper select, .mh-form-wrapper label { text-transform: none !important; font-variant: normal !important; }
</style>

<div class="mh-form-wrapper">
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
        <input type="hidden" name="action" value="mh_submit_form">
        <input type="hidden" name="form_type" value="service_leave_v1">
        <?php wp_nonce_field( 'mh_form_submit' ); ?>

        <?php if (!empty($form_errors)): ?>
            <div style="background:#fff; border-left:5px solid #d63638; padding:20px; margin-bottom:25px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; color:#d63638; border:none; padding:0;">⚠️ Bitte prüfen:</h3>
                <p style="margin:0;">Es fehlen notwendige Angaben (siehe rot markierte Felder).</p>
            </div>
        <?php endif; ?>

      <!-- SEKTION 1: ART DES ANTRAGS (Angepasst an PDF) -->
        <div class="mh-section">
            <h3>Art des Antrags <span class="req">*</span></h3>
            <div class="reason-grid">
                
                <!-- Spalte 1: Dienstbefreiung -->
                <div class="reason-col">
                    <h4>Dienstbefreiung</h4>
                    <p style="font-size:0.8em; color:#666; margin-bottom:5px;">Rücksprache Schulleitung!</p>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="krank_planbar" <?= $chk('reason_key', 'krank_planbar') ?> onclick="setCat('dienst')"> <label>Planbare Krankheit</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="beerdigung" <?= $chk('reason_key', 'beerdigung') ?> onclick="setCat('dienst')"> <label>Beerdigungen</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="einschulung" <?= $chk('reason_key', 'einschulung') ?> onclick="setCat('dienst')"> <label>Einschulung Kind</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="versorgung" <?= $chk('reason_key', 'versorgung') ?> onclick="setCat('dienst')"> <label>Versorgung Angehörige</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="sonstige" <?= $chk('reason_key', 'sonstige') ?> onclick="setCat('dienst')"> <label>Anderer Anlass</label></div>
                </div>

                <!-- Spalte 2: Unterrichtsbefreiung -->
                <div class="reason-col">
                    <h4>Unterrichtsbefreiung</h4>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="fb_br" <?= $chk('reason_key', 'fb_br') ?> onclick="setCat('unterricht')"> <label>Fortbildung BR **</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="fb_schelf" <?= $chk('reason_key', 'fb_schelf') ?> onclick="setCat('unterricht')"> <label>Fortbildung SchELF **</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="fb_schilf" <?= $chk('reason_key', 'fb_schilf') ?> onclick="setCat('unterricht')"> <label>Fortbildung SchiLF</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="allg_br" <?= $chk('reason_key', 'allg_br') ?> onclick="setCat('unterricht')"> <label>Einladung BR</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="pruefung" <?= $chk('reason_key', 'pruefung') ?> onclick="setCat('unterricht')"> <label>Vorprüfung / Termine</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="fachberater" <?= $chk('reason_key', 'fachberater') ?> onclick="setCat('unterricht')"> <label>Fachberater</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="ihk" <?= $chk('reason_key', 'ihk') ?> onclick="setCat('unterricht')"> <label>IHK-Prüfungen</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="sonstige_unt" <?= $chk('reason_key', 'sonstige_unt') ?> onclick="setCat('unterricht')"> <label>Sonstige</label></div>
                    
                    <h4 style="margin-top:10px; background:#B4C6E7; padding:2px; text-align:center; color:#000;">Dienstunfallschutz</h4>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="dienstunfall" <?= $chk('reason_key', 'dienstunfall') ?> onclick="setCat('unfall')"> <label>Dienstveranstaltung (ohne U-Befr.)</label></div>
                </div>

                <!-- Spalte 3: Sonderurlaub -->
                <div class="reason-col">
                    <h4>Sonderurlaub</h4>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="jubilaeum" <?= $chk('reason_key', 'jubilaeum') ?> onclick="setCat('sonder')"> <label>Dienstjubiläum</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="geburt_tod" <?= $chk('reason_key', 'geburt_tod') ?> onclick="setCat('sonder')"> <label>Tod Angehörige*</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="niederkunft" <?= $chk('reason_key', 'niederkunft') ?> onclick="setCat('sonder')"> <label>Niederkunft Frau/Partnerin*</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="umzug" <?= $chk('reason_key', 'umzug') ?> onclick="setCat('sonder')"> <label>Umzug dienstl.</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="erkrankung_ange" <?= $chk('reason_key', 'erkrankung_ange') ?> onclick="setCat('sonder')"> <label>Schwere Erkr. Angehörige*</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="erkrankung_kind" <?= $chk('reason_key', 'erkrankung_kind') ?> onclick="setCat('sonder')"> <label>Erkr. Kind (< 12)*</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="betreuung" <?= $chk('reason_key', 'betreuung') ?> onclick="setCat('sonder')"> <label>Erkr. Betreuer (< 8)*</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="pol_bildung" <?= $chk('reason_key', 'pol_bildung') ?> onclick="setCat('sonder')"> <label>Politische Bildung</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="sport" <?= $chk('reason_key', 'sport') ?> onclick="setCat('sonder')"> <label>Sport-Meistersch.</label></div>
                    <div class="mh-radio-row"><input type="radio" name="reason_key" value="sonstige_dringend" <?= $chk('reason_key', 'sonstige_dringend') ?> onclick="setCat('sonder')"> <label>Sonstige dringende*</label></div>
                </div>
            </div>
            <input type="hidden" name="category" id="input_category" value="<?= $val('category') ?>">
        </div>

        <!-- SEKTION 2: PERSON & ZEIT -->
        <div class="mh-section">
            <h3>Persönliche Angaben & Zeit</h3>
            
            <div style="margin-bottom: 25px;">
                <div class="mh-input-group">
                    <label>Antragsteller*in <span class="req">*</span></label>
                    <select id="teacher_select" onchange="updateNameFields(this)" style="font-size:1.1em; padding:8px; width: 100%;">
                        <option value="">-- Bitte wählen --</option>
                        <?php 
                        $current_last = $val('lastname'); 
                        $current_first = $val('firstname');
                        if ( isset($teachers_list) && is_array($teachers_list) ): 
                            foreach($teachers_list as $t): 
                                $display = esc_html($t['long_name'] . ', ' . $t['fore_name'] . ' (' . $t['name'] . ')');
                                $selected = ($current_last === $t['long_name'] && $current_first === $t['fore_name']) ? 'selected' : '';
                        ?>
                            <option value="<?= esc_attr($t['name']) ?>" 
                                    <?= $selected ?>
                                    data-last="<?= esc_attr($t['long_name']) ?>" 
                                    data-first="<?= esc_attr($t['fore_name']) ?>">
                                <?= $display ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <input type="hidden" name="lastname" id="hidden_lastname" value="<?= $val('lastname') ?>">
                    <input type="hidden" name="firstname" id="hidden_firstname" value="<?= $val('firstname') ?>">
                </div>
            </div>

            <h4 style="margin-bottom:10px; font-size:1em; color:#555; text-transform:uppercase; border-bottom:none;">Zeitraum (Datum)</h4>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Von Datum <span class="req">*</span></label>
                    <input type="date" name="date_start" id="field_date_start" required 
                           style="<?= $err_cls('date_start') ?>" 
                           value="<?= $val('date_start') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Bis Datum <small>(nur bei mehrtägig)</small></label>
                    <input type="date" name="date_end" id="field_date_end" 
                           value="<?= $val('date_end') ?>">
                </div>
            </div>

            <h4 style="margin-bottom:10px; font-size:1em; color:#555; text-transform:uppercase; border-bottom:none; margin-top:20px;">Uhrzeit / Stunden (nur bei eintägig/stundenweise)</h4>
            <div class="mh-grid-row mh-grid-2">
                <div class="mh-input-group">
                    <label>Von Stunde / Uhrzeit</label>
                    <input type="text" name="time_start" placeholder="z.B. 1. Stunde oder 08:00" value="<?= $val('time_start') ?>">
                </div>
                <div class="mh-input-group">
                    <label>Bis Stunde / Uhrzeit</label>
                    <input type="text" name="time_end" placeholder="z.B. 6. Stunde oder 13:00" value="<?= $val('time_end') ?>">
                </div>
            </div>
        </div>

        <!-- SEKTION 3: DETAILS -->
        <div class="mh-section">
            <h3>Begründung & Details</h3>
            <div class="mh-input-group" style="margin-bottom:15px;">
                <label>Grund für den Antrag / Erläuterung</label>
                <textarea name="reason_text" style="height:80px;"><?= $val('reason_text') ?></textarea>
            </div>
            
            <div class="mh-input-group" style="margin-bottom:15px;">
                <label>Weitere beteiligte KollegInnen</label>
                <input type="text" name="colleagues" value="<?= $val('colleagues') ?>">
            </div>
            
            <div class="mh-input-group">
                <label>Kollision mit schulinternem Termin (Welcher?)</label>
                <input type="text" name="collision" placeholder="z.B. Zeugniskonferenz" value="<?= $val('collision') ?>">
            </div>
        </div>

        <!-- SEKTION 4: VERTRETUNG -->
        <div class="mh-section">
            <h3>Hinweise für die Vertretungsplanung</h3>
            <p style="color:#666; margin-bottom:15px;">Bitte mindestens 14 Tage vorher bei der Schulleitung einreichen! <br><small>(Datumsauswahl ist auf den oben angegebenen Zeitraum beschränkt)</small></p>
            
            <table class="sub-table">
                <thead>
                    <tr style="background:#eee;">
                        <th width="20%">Datum</th>
                        <th width="30%">Lerngruppe / Klasse</th>
                        <th width="10%">Stunde</th>
                        <th>Vertretungshinweise / Aufgaben</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // SCHLEIFE AUF 10 ERHÖHT
                    for($i=0; $i<10; $i++): 
                        $d = $form_data['sub_date'][$i] ?? '';
                        $g = $form_data['sub_group'][$i] ?? '';
                        $h = $form_data['sub_hour'][$i] ?? '';
                        $in = $form_data['sub_info'][$i] ?? '';
                    ?>
                    <tr>
                        <!-- Klasse 'sub-date' für JavaScript hinzugefügt -->
                        <td><input type="date" name="sub_date[]" class="sub-date" value="<?= esc_attr($d) ?>"></td>
                        <td><input type="text" name="sub_group[]" value="<?= esc_attr($g) ?>"></td>
                        <td><input type="text" name="sub_hour[]" value="<?= esc_attr($h) ?>"></td>
                        <td><input type="text" name="sub_info[]" value="<?= esc_attr($in) ?>"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_mode" value="pdf" class="button button-primary button-large">Antrag prüfen & PDF erstellen</button>
        </div>
    </form>
</div>

<script>
    function setCat(cat) {
        document.getElementById('input_category').value = cat;
    }

    function updateNameFields(selectEl) {
        var option = selectEl.options[selectEl.selectedIndex];
        var last = option.getAttribute('data-last') || '';
        var first = option.getAttribute('data-first') || '';
        document.getElementById('hidden_lastname').value = last;
        document.getElementById('hidden_firstname').value = first;
    }

    // LOGIK: Datumsübernahme & Einschränkung
    document.addEventListener('DOMContentLoaded', function() {
        const startInput = document.getElementById('field_date_start');
        const endInput   = document.getElementById('field_date_end');
        const subDateInputs = document.querySelectorAll('.sub-date'); // Die 10 Felder

        function updateSubDateConstraints() {
            if(!startInput.value) return;

            // 1. Min/Max ermitteln
            const minDate = startInput.value;
            // Wenn Bis-Datum leer, ist Max = Start (Eintägig), sonst Bis-Datum
            const maxDate = endInput.value ? endInput.value : minDate;

            // 2. Auf alle Felder anwenden
            subDateInputs.forEach(input => {
                input.min = minDate;
                input.max = maxDate;
                
                // Optional: Leere Felder auf Startdatum vor-füllen, falls komfort gewünscht
                // if (!input.value) input.value = minDate; 
            });
        }

        if(startInput && endInput) {
            // Listener Start-Datum
            startInput.addEventListener('change', function() {
                // Auto-Fill Bis-Datum nur wenn leer
                if (this.value && !endInput.value) {
                    endInput.value = this.value;
                }
                updateSubDateConstraints();
            });

            // Listener End-Datum
            endInput.addEventListener('change', function() {
                updateSubDateConstraints();
            });
            
            // Init beim Laden (falls Werte vorhanden)
            updateSubDateConstraints();
        }
    });
</script>