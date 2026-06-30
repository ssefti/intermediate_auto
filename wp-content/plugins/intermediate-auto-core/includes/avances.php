<?php
/**
 * Gestion des avances (acomptes versés par les clients)
 * Table wp_avances : liste, ajout/édition, justificatifs, suppression.
 * Chaque avance est reliée à un client (et éventuellement à un véhicule).
 */
if (!defined('ABSPATH')) exit;

define('AVANCES_VER', '1.0');

/** Nom complet de la table des avances */
function avances_table() {
    global $wpdb;
    return $wpdb->prefix . 'avances';
}

/* ---------- Listes de référence ---------- */
function avance_modes()   { return array('Espèces', 'Chèque', 'Virement', 'Versement bancaire', 'Carte', 'Autre'); }
function avance_statuts() { return array('Encaissée', 'En attente', 'Annulée'); }

/** Formate un montant en DA */
function avance_money($n) {
    return number_format((float)$n, 2, ',', ' ') . ' DA';
}

/* ============================================================
 *  INSTALLATION / MISE À NIVEAU DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'avances_maybe_install');
function avances_maybe_install() {
    if (get_option('avances_db_ver') === AVANCES_VER) return;
    global $wpdb;
    $table   = avances_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        vehicule_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        montant DECIMAL(14,2) NOT NULL DEFAULT 0,
        date_avance DATE NULL DEFAULT NULL,
        mode_paiement VARCHAR(40) NOT NULL DEFAULT '',
        reference VARCHAR(120) NOT NULL DEFAULT '',
        statut VARCHAR(30) NOT NULL DEFAULT 'Encaissée',
        notes TEXT NULL,
        attachments TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY client_id (client_id),
        KEY statut (statut)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('avances_db_ver', AVANCES_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
function avances_get_all($args = array()) {
    global $wpdb;
    $t = avances_table();
    $args = wp_parse_args($args, array(
        'statut'  => '',
        'search'  => '',
        'orderby' => 'date_avance',
        'order'   => 'DESC',
    ));

    $where = array('1=1'); $params = array();
    if ($args['statut'] !== '') { $where[] = 'statut = %s'; $params[] = $args['statut']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(reference LIKE %s OR notes LIKE %s)';
        array_push($params, $like, $like);
    }

    $allowed = array('date_avance', 'montant', 'id', 'created_at');
    $orderby = in_array($args['orderby'], $allowed, true) ? $args['orderby'] : 'date_avance';
    $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} {$order}, id DESC";
    if ($params) $sql = $wpdb->prepare($sql, $params);
    return $wpdb->get_results($sql);
}

function avance_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . avances_table() . " WHERE id = %d", (int)$id));
}

/** Total des avances par statut (somme des montants) */
function avances_total($statut = 'Encaissée') {
    global $wpdb;
    return (float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(montant),0) FROM " . avances_table() . " WHERE statut = %s", $statut));
}

/** IDs des justificatifs d'une avance */
function avance_attachment_ids($a) {
    if (empty($a->attachments)) return array();
    return array_values(array_filter(array_map('intval', explode(',', $a->attachments))));
}

/** Libellé du client d'une avance */
function avance_client_label($a) {
    if (!$a->client_id || !function_exists('iac_get_client')) return '—';
    $c = iac_get_client($a->client_id);
    return $c ? iac_client_name($c) : '— (client supprimé)';
}

/** Libellé du véhicule d'une avance */
function avance_vehicle_label($a) {
    if (!$a->vehicule_id || !function_exists('ia_get_vehicle')) return '';
    $v = ia_get_vehicle($a->vehicule_id);
    return $v ? ia_vehicle_title($v) : '';
}

/* ============================================================
 *  ENREGISTREMENT (création / édition)
 * ============================================================ */
add_action('admin_post_avance_save', 'avance_save');
function avance_save() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    check_admin_referer('avance_save');

    global $wpdb;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    $att_ids = array_values(array_filter(array_map('intval', explode(',', $_POST['attachments'] ?? ''))));
    $date = sanitize_text_field($_POST['date_avance'] ?? '');
    if ($date === '') $date = null;
    $montant = (float)str_replace(array(' ', ','), array('', '.'), $_POST['montant'] ?? '0');

    $data = array(
        'client_id'     => (int)($_POST['client_id'] ?? 0),
        'vehicule_id'   => (int)($_POST['vehicule_id'] ?? 0),
        'montant'       => round($montant, 2),
        'date_avance'   => $date,
        'mode_paiement' => sanitize_text_field($_POST['mode_paiement'] ?? ''),
        'reference'     => sanitize_text_field($_POST['reference'] ?? ''),
        'statut'        => sanitize_text_field($_POST['statut'] ?? 'Encaissée'),
        'notes'         => sanitize_textarea_field($_POST['notes'] ?? ''),
        'attachments'   => implode(',', $att_ids),
        'updated_at'    => current_time('mysql'),
    );

    if ($id > 0) {
        $wpdb->update(avances_table(), $data, array('id' => $id));
        $msg = 'aupdated';
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert(avances_table(), $data);
        $msg = 'acreated';
    }
    wp_safe_redirect(admin_url('admin.php?page=avances&iac_msg=' . $msg));
    exit;
}

/* ---------- Suppression ---------- */
add_action('admin_post_avance_delete', 'avance_delete');
function avance_delete() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('avance_delete_' . $id);
    if ($id > 0) {
        global $wpdb;
        $wpdb->delete(avances_table(), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=avances&iac_msg=adeleted'));
    exit;
}

/* ============================================================
 *  SECTION AVANCES (onglets : Avances / Ajouter)
 * ============================================================ */
function avances_page_section() {
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit'), true)) $tab = 'list';
    iac_section_tabs('avances', $tab);
    if ($tab === 'edit') avance_page_edit();
    else                 avances_page_list();
}

/* ============================================================
 *  PAGE : Liste des avances
 * ============================================================ */
function avances_page_list() {
    $statut  = isset($_GET['statut']) ? sanitize_text_field(wp_unslash($_GET['statut'])) : '';
    $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $avances = avances_get_all(array('statut' => $statut, 'search' => $search));
    $total   = avances_total('Encaissée');

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Gestion des avances</h1>';
    echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=avances&tab=edit')) . '">+ Enregistrer une avance</a></div>';

    if (isset($_GET['iac_msg'])) {
        $m = array(
            'acreated' => 'Avance enregistrée.',
            'aupdated' => 'Avance mise à jour.',
            'adeleted' => 'Avance supprimée.',
        );
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    // Total encaissé
    echo '<div class="iac-cards" style="grid-template-columns:repeat(2,1fr);max-width:520px">';
    printf('<div class="iac-card"><div class="n">%s</div><div class="l">Total encaissé</div></div>', esc_html(avance_money($total)));
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Avances enregistrées</div></div>', count(avances_get_all()));
    echo '</div>';

    // Filtres statut + recherche
    $base = admin_url('admin.php?page=avances');
    echo '<p style="margin:0 0 12px">';
    $tabs = array('' => 'Toutes') + array_combine(avance_statuts(), avance_statuts());
    foreach ($tabs as $key => $lbl) {
        $url = $key === '' ? $base : add_query_arg('statut', $key, $base);
        $style = ($statut === $key) ? 'font-weight:700;text-decoration:none' : 'text-decoration:none';
        echo '<a href="' . esc_url($url) . '" style="' . $style . ';margin-right:14px">' . esc_html($lbl) . '</a>';
    }
    echo '</p>';
    echo '<form method="get" style="margin-bottom:16px"><input type="hidden" name="page" value="avances">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher (référence, note…)" style="width:300px">';
    echo ' <button class="button">Rechercher</button></form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:110px">Date</th><th>Client</th><th>Véhicule</th><th>Montant</th><th>Mode</th><th>Référence</th><th>Statut</th><th style="width:140px">Actions</th></tr></thead><tbody>';

    if (!$avances) {
        echo '<tr><td colspan="8">Aucune avance. <a href="' . esc_url(admin_url('admin.php?page=avances&tab=edit')) . '">Enregistrez-en une</a>.</td></tr>';
    } else {
        foreach ($avances as $a) {
            $edit = admin_url('admin.php?page=avances&tab=edit&id=' . $a->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=avance_delete&id=' . $a->id), 'avance_delete_' . $a->id);
            $pill = $a->statut === 'Encaissée' ? 'ok' : ($a->statut === 'Annulée' ? 'sold' : 'cmd');
            $date = ($a->date_avance && $a->date_avance !== '0000-00-00') ? $a->date_avance : '—';
            echo '<tr>';
            echo '<td>' . esc_html($date) . '</td>';
            echo '<td><strong>' . esc_html(avance_client_label($a)) . '</strong></td>';
            echo '<td>' . esc_html(avance_vehicle_label($a)) . '</td>';
            echo '<td><strong>' . esc_html(avance_money($a->montant)) . '</strong></td>';
            echo '<td>' . esc_html($a->mode_paiement) . '</td>';
            echo '<td>' . esc_html($a->reference) . '</td>';
            echo '<td><span class="iac-pill ' . $pill . '">' . esc_html($a->statut) . '</span></td>';
            echo '<td><a href="' . esc_url($edit) . '">Modifier</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer cette avance ?\')" style="color:#b23b3b">Suppr.</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Ajouter / Modifier une avance
 * ============================================================ */
function avance_page_edit() {
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $a   = $id ? avance_get($id) : null;
    $get = function($k, $d = '') use ($a) { return $a && isset($a->$k) ? $a->$k : $d; };
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier une avance' : 'Enregistrer une avance') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=avances')) . '">← Retour à la liste</a></div>';

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('avance_save');
    echo '<input type="hidden" name="action" value="avance_save">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    // Client + véhicule
    echo '<div class="row">';
    echo '<div class="fld"><label>Client</label><select name="client_id" required>';
    echo '<option value="">— Choisir un client —</option>';
    if (function_exists('iac_get_clients')) {
        foreach (iac_get_clients(array('active' => 1, 'orderby' => 'nom', 'order' => 'ASC')) as $cc) {
            echo '<option value="' . (int)$cc->id . '" ' . selected((int)$get('client_id', 0), (int)$cc->id, false) . '>' . esc_html(iac_client_name($cc)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '<div class="fld"><label>Véhicule (facultatif)</label><select name="vehicule_id">';
    echo '<option value="0">— Aucun —</option>';
    if (function_exists('ia_get_vehicles')) {
        foreach (ia_get_vehicles(array('orderby' => 'marque', 'order' => 'ASC')) as $vv) {
            echo '<option value="' . (int)$vv->id . '" ' . selected((int)$get('vehicule_id', 0), (int)$vv->id, false) . '>' . esc_html(ia_vehicle_title($vv)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '</div>';

    // Montant + date
    echo '<div class="row">';
    echo '<div class="fld"><label>Montant (DA)</label><input type="number" step="0.01" min="0" name="montant" value="' . esc_attr($get('montant', '')) . '" required></div>';
    echo '<div class="fld"><label>Date de l\'avance</label><input type="date" name="date_avance" value="' . esc_attr(($get('date_avance') && $get('date_avance') !== '0000-00-00') ? $get('date_avance') : '') . '"></div>';
    echo '</div>';

    // Mode + référence + statut
    echo '<div class="row">';
    echo '<div class="fld"><label>Mode de paiement</label><select name="mode_paiement">';
    echo '<option value="">— Choisir —</option>';
    foreach (avance_modes() as $mode) echo '<option ' . selected($get('mode_paiement'), $mode, false) . '>' . esc_html($mode) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Référence (n° chèque, réf. virement…)</label><input type="text" name="reference" value="' . esc_attr($get('reference')) . '"></div>';
    echo '<div class="fld"><label>Statut</label><select name="statut">';
    foreach (avance_statuts() as $s) echo '<option ' . selected($get('statut', 'Encaissée'), $s, false) . '>' . esc_html($s) . '</option>';
    echo '</select></div>';
    echo '</div>';

    echo '<div class="fld"><label>Notes</label><textarea name="notes" rows="3">' . esc_textarea($get('notes')) . '</textarea></div>';

    // Justificatifs
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Justificatifs</h2>';
    echo '<p style="color:#777;font-size:13px;margin:-4px 0 10px">Reçu de paiement, scan du chèque, ordre de virement… (PDF, image, etc.)</p>';
    $att_ids = $a ? avance_attachment_ids($a) : array();
    echo '<input type="hidden" id="av_att_ids" name="attachments" value="' . esc_attr(implode(',', $att_ids)) . '">';
    echo '<ul id="av_att_list" style="margin:0 0 10px;list-style:none;padding:0">';
    foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename($url);
        $icon = wp_attachment_is_image($aid)
            ? '<img src="' . esc_url(wp_get_attachment_image_url($aid, 'thumbnail')) . '" style="width:34px;height:34px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">'
            : '<span style="font-size:18px;margin-right:8px">📄</span>';
        echo '<li data-id="' . (int)$aid . '" style="padding:6px 0;border-bottom:1px solid #f3f3f3">' . $icon
            . '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($name) . '</a> '
            . '<a href="#" class="av-att-rm" style="color:#b23b3b;margin-left:8px">retirer</a></li>';
    }
    echo '</ul>';
    echo '<button type="button" class="button" id="av_att_add">📎 Ajouter un justificatif</button>';

    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer les modifications' : 'Enregistrer l\'avance') . '</button></p>';
    echo '</form></div>';

    // JS : médiathèque justificatifs
    ?>
    <script>
    jQuery(function($){
      var frame;
      $('#av_att_add').on('click', function(e){
        e.preventDefault();
        frame = wp.media({ title:'Ajouter un justificatif', button:{text:'Ajouter'}, multiple:true });
        frame.on('select', function(){
          var input = $('#av_att_ids');
          var ids = input.val() ? input.val().split(',') : [];
          frame.state().get('selection').each(function(a){
            a = a.toJSON();
            if (ids.indexOf(String(a.id)) === -1){
              ids.push(String(a.id));
              var name = a.filename || a.title || ('#' + a.id);
              var icon = (a.type === 'image' && a.sizes && a.sizes.thumbnail)
                ? '<img src="'+a.sizes.thumbnail.url+'" style="width:34px;height:34px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">'
                : '<span style="font-size:18px;margin-right:8px">📄</span>';
              $('#av_att_list').append('<li data-id="'+a.id+'" style="padding:6px 0;border-bottom:1px solid #f3f3f3">'+icon+'<a href="'+a.url+'" target="_blank">'+name+'</a> <a href="#" class="av-att-rm" style="color:#b23b3b;margin-left:8px">retirer</a></li>');
            }
          });
          input.val(ids.join(','));
        });
        frame.open();
      });
      $(document).on('click', '.av-att-rm', function(e){
        e.preventDefault();
        var li = $(this).closest('li'), id = String(li.data('id'));
        var input = $('#av_att_ids');
        var ids = input.val() ? input.val().split(',') : [];
        input.val(ids.filter(function(x){ return x !== id; }).join(','));
        li.remove();
      });
    });
    </script>
    <?php
}
