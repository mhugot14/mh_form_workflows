<?php
/**
 * View: User Dashboard - Meine Anträge
 * 
 * @var array $grouped  Die nach Schuljahr gruppierten Einsendungen
 * @var array $urls     Die im Controller ermittelten Basis-URLs für die Formulare
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<style>
    .mh-dashboard { max-width: 1100px; margin: 20px auto; font-family: inherit; }
    .mh-year-block { margin-bottom: 40px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .mh-year-header { background: #003E7E; color: #fff; padding: 15px 20px; font-size: 1.2em; font-weight: bold; }
    
    .mh-dash-table { width: 100%; border-collapse: collapse; background: #fff; table-layout: fixed; }
    .mh-dash-table th, .mh-dash-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
    .mh-dash-table th { background: #f8f9fa; color: #666; font-weight: 700; text-transform: uppercase; font-size: 0.75em; letter-spacing: 1px; border-bottom: 2px solid #eee; }
    .mh-dash-table tr:hover { background-color: #fcfdfe; }
    
    /* Datums-Spalte */
    .mh-date-block { line-height: 1.4; }
    .mh-date-main { font-weight: bold; color: #333; font-size: 0.95em; }
    .mh-date-sub { font-size: 0.8em; color: #666; margin-bottom: 8px; }
    .mh-date-created { font-size: 0.75em; color: #0073aa; padding-top: 5px; border-top: 1px dotted #ddd; margin-top: 5px; }

    .mh-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 0.7em; font-weight: bold; text-transform: uppercase; }
    .badge-service { background: #e3f2fd; color: #0d47a1; }
    .badge-abmeldung { background: #e8f5e9; color: #1b5e20; }
    
    .mh-class-text { font-weight: 600; color: #444; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }

    .mh-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .mh-actions a { 
        text-decoration: none !important; font-size: 0.8em; padding: 6px 12px; 
        border-radius: 4px; border: 1px solid #ccc; color: #333; background: #fff;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s ease;
    }
    .mh-actions a:hover { background: #f0f0f0; border-color: #888; transform: translateY(-1px); }
    .btn-edit { color: #0073aa !important; border-color: #0073aa !important; }
    .btn-del { color: #d63638 !important; border-color: #f8d7da !important; }
    .btn-del:hover { background: #fff5f5 !important; border-color: #d63638 !important; }
    
    .mh-msg { padding: 15px; margin-bottom: 25px; border-radius: 4px; border-left: 4px solid #46b450; background: #f0fdf4; color: #1b5e20; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
</style>

<div class="mh-dashboard">
    <?php if(isset($_GET['mh_msg']) && $_GET['mh_msg'] === 'deleted'): ?>
        <div class="mh-msg">✅ Der Eintrag wurde erfolgreich aus Ihrer Übersicht gelöscht.</div>
    <?php endif; ?>

    <?php if ( empty($grouped) ): ?>
        <div style="text-align:center; padding: 60px; background:#fff; border:1px solid #ddd; border-radius:8px;">
            <span class="dashicons dashicons-media-document" style="font-size: 48px; width: 48px; height: 48px; color: #ccc; margin-bottom: 20px;"></span>
            <h3>Noch keine Anträge vorhanden</h3>
            <p>Ihre eingereichten Formulare erscheinen hier automatisch.</p>
        </div>
    <?php else: ?>
    
        <?php foreach($grouped as $year => $items): ?>
            <div class="mh-year-block">
                <div class="mh-year-header">Schuljahr <?= esc_html($year) ?></div>
                <table class="mh-dash-table">
                    <thead>
                        <tr>
                            <th width="18%">Datum / Status</th>
                            <th width="12%">Art</th>
                            <th width="12%">Klasse</th>
                            <th width="33%">Name / Betreff</th>
                            <th width="25%">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): 
                            // DATEN EXTRAHIEREN (WICHTIG!)
                            $data = $item['data']; 
                            $type = $item['form_type'];
                            
                            $created_ts = strtotime($item['created_at']);
                            $updated_ts = strtotime($item['updated_at']);
                            $is_updated = ($item['updated_at'] !== $item['created_at']);
                            
                            // 1. Klasse extrahieren
                            $klasse = $data['class_name'] ?? ($data['sub_rows'][0]['group'] ?? '-');
                            
                            // 2. Anzeige-Logik für Typen
                            if ($type === 'service_leave_v1') {
                                $label = 'Befreiung'; $badge = 'badge-service';
                                $info = $data['reason_key'] ?? 'Dienstbefreiung';
                            } else {
                                $label = 'Abmeldung'; $badge = 'badge-abmeldung';
                                $info = ($data['lastname'] ?? '') . ', ' . ($data['firstname'] ?? '');
                            }
                            
                            // 3. URLs generieren
                            $url_down = add_query_arg(['mh_action' => 'download', 'id' => $item['id'], '_wpnonce' => wp_create_nonce('mh_dashboard_action_'.$item['id'])]);
                            $url_del  = add_query_arg(['mh_action' => 'delete', 'id' => $item['id'], '_wpnonce' => wp_create_nonce('mh_dashboard_action_'.$item['id'])]);
                            
                            $base_page_url = $urls[$type] ?? '';
                            $url_edit = !empty($base_page_url) ? add_query_arg('mh_edit_id', $item['id'], $base_page_url) : '#';
                        ?>
                        <tr>
                            <td>
                                <div class="mh-date-block">
                                    <div class="mh-date-sub">Zuletzt geändert:</div>
                                    <div class="mh-date-main"><?= date('d.m.Y', $updated_ts) ?></div>
                                    <div class="mh-date-sub"><?= date('H:i', $updated_ts) ?> Uhr</div>
                                    
                                    <?php if ($is_updated): ?>
                                        <div class="mh-date-created">
                                            Erstellt:<br>
                                            <?= date('d.m.Y', $created_ts) ?> (<?= date('H:i', $created_ts) ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="mh-badge <?= $badge ?>"><?= $label ?></span></td>
                            <td><span class="mh-class-text"><?= esc_html($klasse) ?></span></td>
                            <td><strong><?= esc_html(strtoupper($info)) ?></strong></td>
                            <td class="mh-actions">
                                <a href="<?= esc_url($url_down) ?>" target="_blank" title="PDF herunterladen">📄 PDF</a>
                                
                                <?php if ($url_edit !== '#'): ?>
                                    <a href="<?= esc_url($url_edit) ?>" class="btn-edit" title="Im Formular öffnen">✏️ Bearbeiten</a>
                                <?php endif; ?>

                                <a href="<?= esc_url($url_del) ?>" class="btn-del" onclick="return confirm('Möchten Sie diesen Eintrag wirklich löschen?');" title="Eintrag löschen">🗑</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>