<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$f_start = $_GET['start_date'] ?? '';
$f_end   = $_GET['end_date'] ?? '';
$f_user  = $_GET['user_id'] ?? '';
$f_type  = $_GET['form_type'] ?? '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Alle Formular-Einsendungen</h1>
    <hr class="wp-header-end">

    <!-- Erfolgsmeldungen -->
    <?php if ( isset( $_GET['mh_msg'] ) && $_GET['mh_msg'] === 'bulk_deleted' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ <?= intval($_GET['count']) ?> Einträge wurden erfolgreich gelöscht.</p>
        </div>
    <?php endif; ?>

    <!-- FILTER (wie gehabt) -->
    <div class="tablenav top" style="background: #fff; padding: 10px; border: 1px solid #ccd0d4; margin-bottom: 10px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="mh-form-admin-list">
            <div class="alignleft actions">
                <input type="date" name="start_date" value="<?= esc_attr($f_start) ?>">
                <input type="date" name="end_date" value="<?= esc_attr($f_end) ?>">
                <select name="user_id">
                    <option value="">-- Alle Ersteller --</option>
                    <?php foreach($submitters as $user): ?>
                        <option value="<?= $user['user_id'] ?>" <?= selected($f_user, $user['user_id']) ?>><?= esc_html($user['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filtern">
            </div>
        </form>
    </div>

    <!-- BULK ACTION FORMULAR -->
    <form method="post" action="">
        <?php wp_nonce_field( 'bulk-submissions' ); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1">Mehrfachaktionen</option>
                    <option value="delete">Löschen</option>
                </select>
                <input type="submit" class="button action" value="Übernehmen">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th width="140">Datum</th>
                    <th width="150">Benutzer</th>
                    <th width="120">Typ</th>
                    <th width="100">Klasse</th>
                    <th>Name / Betreff</th>
                    <th width="100">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $submissions ) ) : ?>
                    <tr><td colspan="7">Keine Einträge gefunden.</td></tr>
                <?php else : foreach ( $submissions as $sub ) : 
                    $data = $sub['data'];
                    $klasse = $data['class_name'] ?? ($data['sub_rows'][0]['group'] ?? '-');
                    $info = ($sub['form_type'] === 'service_leave_v1') ? ($data['reason_key'] ?? 'Befreiung') : (($data['lastname'] ?? '') . ', ' . ($data['firstname'] ?? ''));
                    $url_down = wp_nonce_url( admin_url('admin.php?page=mh-form-admin-list&mh_admin_action=download&id='.$sub['id']), 'mh_admin_action_'.$sub['id'] );
                ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="bulk_ids[]" value="<?= $sub['id'] ?>">
                        </th>
                        <td>
        <strong><?= date( 'd.m.Y', strtotime( $sub['updated_at'] ) ) ?></strong><br>
        <small><?= date( 'H:i', strtotime( $sub['updated_at'] ) ) ?> Uhr</small>
        
        <?php if ($sub['updated_at'] !== $sub['created_at']): ?>
            <br><small style="color:#999;">Erstellt: <?= date('d.m.y', strtotime($sub['created_at'])) ?></small>
        <?php endif; ?>
    </td><td><?= esc_html( $sub['user_name'] ) ?></td>
                        <td><?= ($sub['form_type'] === 'service_leave_v1') ? 'Befreiung' : 'Abmeldung' ?></td>
                        <td><code><?= esc_html( $klasse ) ?></code></td>
                        <td><strong><?= esc_html( strtoupper( $info ) ) ?></strong></td>
                        <td>
                            <a href="<?= $url_down ?>" class="button button-small" target="_blank">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
    // Kleines JS für "Alle auswählen" (WordPress Standard-Verhalten nachbauen)
    document.getElementById('cb-select-all-1').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="bulk_ids[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>