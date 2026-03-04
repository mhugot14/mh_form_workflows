<style>
    .mh-dashboard { max-width: 1100px; margin: 0 auto; font-family: inherit; }
    .mh-year-block { margin-bottom: 30px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .mh-year-header { background: #003E7E; color: #fff; padding: 12px 15px; font-size: 1.1em; font-weight: bold; }
    .mh-dash-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: auto; }
    .mh-dash-table th, .mh-dash-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95em; vertical-align: middle; }
    .mh-dash-table th { background: #fcfcfc; color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75em; letter-spacing: 0.5px; }
    .mh-dash-table tr:hover { background-color: #f9fbff; }
    
    .mh-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75em; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
    .badge-service { background: #e3f2fd; color: #0d47a1; }
    .badge-abmeldung { background: #e8f5e9; color: #1b5e20; }
    
    /* Klasse-Spalte Styling ohne Box-Look */
    .mh-class-text { font-family: monospace; color: #444; font-weight: bold; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; border: 1px solid #ddd; }

    .mh-actions { display: flex; gap: 5px; flex-wrap: nowrap; }
    .mh-actions a { 
        text-decoration: none !important; font-size: 0.8em; padding: 6px 10px; 
        border-radius: 3px; border: 1px solid #ccc; color: #333; background: #fff;
        display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
    }
    .mh-actions a:hover { background: #f0f0f0; border-color: #999; }
    .btn-edit { color: #0073aa !important; border-color: #0073aa !important; }
    .btn-del { color: #d63638 !important; }
    
    .mh-msg { padding: 12px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid #46b450; background: #f0fdf4; color: #1b5e20; }
</style>

<div class="mh-dashboard">
    <?php if(isset($_GET['mh_msg']) && $_GET['mh_msg'] === 'deleted'): ?>
        <div class="mh-msg">✅ Eintrag wurde erfolgreich gelöscht.</div>
    <?php endif; ?>

    <?php if ( empty($grouped) ): ?>
        <div style="text-align:center; padding: 40px; background:#f9f9f9; border:1px solid #eee; border-radius:8px;">
            <h3>Keine Einträge gefunden.</h3>
        </div>
    <?php else: ?>
    
        <?php foreach($grouped as $year => $items): ?>
            <div class="mh-year-block">
                <div class="mh-year-header">Schuljahr <?= esc_html($year) ?></div>
                <table class="mh-dash-table">
                    <thead>
                        <tr>
                            <th width="10%">Datum</th>
                            <th width="10%">Art</th>
                            <th width="10%">Klasse</th>
                            <th width="35%">Name / Betreff</th>
                            <th width="35%">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): 
                            $date = date('d.m.Y', strtotime($item['created_at']));
                            $type = $item['form_type'];
                            $data = $item['data'];
                            
                            // Klasse extrahieren
                            $klasse = $data['class_name'] ?? ($data['sub_rows'][0]['group'] ?? '-');
                            
                            // Anzeige-Logik & Zielseiten-Slugs
                            // WICHTIG: Prüfe ob diese Slugs exakt mit deinen WP-Seiten übereinstimmen!
                            if ($type === 'service_leave_v1') {
                                $label = 'Befreiung'; $badge = 'badge-service';
                                $info = $data['reason_key'] ?? 'Dienstbefreiung';
                                $target_slug = 'dienstbefreiung'; 
                            } else {
                                $label = 'Abmeldung'; $badge = 'badge-abmeldung';
                                $info = ($data['lastname'] ?? '') . ', ' . ($data['firstname'] ?? '');
                                $target_slug = 'formular-zur-ausschulung-von-schuelerinnen-und-schuelern'; 
                            }
                            
                            $url_down = add_query_arg(['mh_action' => 'download', 'id' => $item['id'], '_wpnonce' => wp_create_nonce('mh_dashboard_action_'.$item['id'])]);
                            $url_del  = add_query_arg(['mh_action' => 'delete', 'id' => $item['id'], '_wpnonce' => wp_create_nonce('mh_dashboard_action_'.$item['id'])]);
                            
                            // Dynamische URL Generierung über den Slug
                           // 2. Anzeige-Logik & Zielseiten-Suche
if ($type === 'service_leave_v1') {
    $label = 'Befreiung'; $badge = 'badge-service';
    $info = $data['reason_key'] ?? 'Dienstbefreiung';
    
    // Wir suchen die Seite, die den Shortcode für Dienstbefreiung enthält
    $target_page = get_pages(['meta_key' => '_wp_page_template', 'meta_value' => 'default']); // Dummy-Suche
    // Besser: Wir suchen direkt nach dem Slug, den du vergeben hast
    $url_edit = home_url('/dienstbefreiung/') . '?mh_edit_id=' . $item['id'];
} else {
    $label = 'Abmeldung'; $badge = 'badge-abmeldung';
    $info = ($data['lastname'] ?? '') . ', ' . ($data['firstname'] ?? '');
    
    // Hier dein Slug für die Abmeldung
    $url_edit = home_url('/formular-zur-ausschulung-von-schuelerinnen-und-schuelern/') . '?mh_edit_id=' . $item['id'];
}

// FALLBACK-TEST: Falls Permalinks auf deinem XAMPP gar nicht gehen, 
// kannst du testweise die "hässliche" URL nutzen:
// $url_edit = home_url('index.php?pagename=dienstbefreiung&mh_edit_id=' . $item['id']);
                        ?>
                        <tr>
                            <td><?= $date ?></td>
                            <td><span class="mh-badge <?= $badge ?>"><?= $label ?></span></td>
                            <td><span class="mh-class-text"><?= esc_html($klasse) ?></span></td>
                            <td><strong><?= esc_html($info) ?></strong></td>
                            <td class="mh-actions">
                                <a href="<?= esc_url($url_down) ?>" target="_blank">📄 PDF</a>
                                <a href="<?= esc_url($url_edit) ?>" class="btn-edit">✏️ Bearbeiten</a>
                                <a href="<?= esc_url($url_del) ?>" class="btn-del" onclick="return confirm('Eintrag wirklich löschen?');">🗑 Löschen</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>