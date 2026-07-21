<?php
/**
 * Devis / Factures Proforma.
 * Table wp_devis : estimation remise au client avant l'engagement (commande).
 * Génère une facture Proforma imprimable et permet la conversion en commande.
 */
if (!defined('ABSPATH')) exit;

define('DEVIS_VER', '1.1');

/** Mention légale obligatoire sur le document Proforma */
function devis_mention() {
    return "Cette facture Proforma est fournie à titre indicatif. Le prix indiqué est une estimation susceptible d'être modifié en fonction des frais variables, notamment les frais de transport international et les droits de douane.";
}

function devis_table() {
    global $wpdb;
    return $wpdb->prefix . 'devis';
}

function devis_statuts() { return array('En attente', 'Accepté', 'Converti en commande', 'Expiré'); }

/** Formate un montant en DA */
function devis_money($n) {
    if (function_exists('commande_money')) return commande_money($n);
    return number_format((float)$n, 2, ',', ' ') . ' DA';
}

/** Prix après remise (%) */
function devis_prix_net($d) {
    $r = isset($d->remise) ? max(0, min(100, (float)$d->remise)) : 0;
    return (float)$d->prix * (1 - $r / 100);
}

/* ============================================================
 *  INSTALLATION DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'devis_maybe_install');
function devis_maybe_install() {
    if (get_option('devis_db_ver') === DEVIS_VER) return;
    global $wpdb;
    $table   = devis_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        numero VARCHAR(40) NOT NULL DEFAULT '',
        client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        vehicule_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        date_devis DATE NULL DEFAULT NULL,
        couleur VARCHAR(120) NOT NULL DEFAULT '',
        prix DECIMAL(14,2) NOT NULL DEFAULT 0,
        remise DECIMAL(5,2) NOT NULL DEFAULT 0,
        delai_livraison VARCHAR(120) NOT NULL DEFAULT '',
        statut VARCHAR(30) NOT NULL DEFAULT 'En attente',
        conditions TEXT NULL,
        notes TEXT NULL,
        commande_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY client_id (client_id),
        KEY statut (statut)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('devis_db_ver', DEVIS_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
function devis_get_all($args = array()) {
    global $wpdb;
    $t = devis_table();
    $args = wp_parse_args($args, array('statut' => '', 'search' => '', 'orderby' => 'id', 'order' => 'DESC'));
    $where = array('1=1'); $params = array();
    if ($args['statut'] !== '') { $where[] = 'statut = %s'; $params[] = $args['statut']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(numero LIKE %s OR couleur LIKE %s)';
        array_push($params, $like, $like);
    }
    $allowed = array('id', 'date_devis', 'prix');
    $orderby = in_array($args['orderby'], $allowed, true) ? $args['orderby'] : 'id';
    $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
    $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} {$order}";
    if ($params) $sql = $wpdb->prepare($sql, $params);
    return $wpdb->get_results($sql);
}

function devis_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . devis_table() . " WHERE id = %d", (int)$id));
}

/* ============================================================
 *  ENREGISTREMENT
 * ============================================================ */
add_action('admin_post_devis_save', 'devis_save');
function devis_save() {
    acces_guard(acces_can_edit('devis'));
    check_admin_referer('devis_save');

    global $wpdb;
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $date = sanitize_text_field($_POST['date_devis'] ?? '');
    if ($date === '') $date = null;
    $prix   = (float)str_replace(array(' ', ','), array('', '.'), $_POST['prix'] ?? '0');
    $remise = max(0, min(100, (float)str_replace(',', '.', $_POST['remise'] ?? '0')));

    $data = array(
        'client_id'       => (int)($_POST['client_id'] ?? 0),
        'vehicule_id'     => (int)($_POST['vehicule_id'] ?? 0),
        'date_devis'      => $date,
        'couleur'         => sanitize_text_field($_POST['couleur'] ?? ''),
        'prix'            => round($prix, 2),
        'remise'          => round($remise, 2),
        'delai_livraison' => sanitize_text_field($_POST['delai_livraison'] ?? ''),
        'statut'          => sanitize_text_field($_POST['statut'] ?? 'En attente'),
        'conditions'      => sanitize_textarea_field($_POST['conditions'] ?? ''),
        'notes'           => sanitize_textarea_field($_POST['notes'] ?? ''),
        'updated_at'      => current_time('mysql'),
    );

    if ($id > 0) {
        $wpdb->update(devis_table(), $data, array('id' => $id));
    } else {
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $wpdb->insert(devis_table(), $data);
        $id = (int)$wpdb->insert_id;
        $year = $date ? substr($date, 0, 4) : current_time('Y');
        $wpdb->update(devis_table(), array('numero' => 'PRO-' . $year . '-' . str_pad($id, 4, '0', STR_PAD_LEFT)), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=devis&proforma=' . $id . '&iac_msg=dsaved'));
    exit;
}

/* ---------- Suppression ---------- */
add_action('admin_post_devis_delete', 'devis_delete');
function devis_delete() {
    acces_guard(acces_can_edit('devis'));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('devis_delete_' . $id);
    if ($id > 0) { global $wpdb; $wpdb->delete(devis_table(), array('id' => $id)); }
    wp_safe_redirect(admin_url('admin.php?page=devis&iac_msg=ddeleted'));
    exit;
}

/* ---------- Conversion en commande ---------- */
add_action('admin_post_devis_convert', 'devis_convert');
function devis_convert() {
    acces_guard(acces_can_edit('devis'));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('devis_convert_' . $id);
    $d = $id ? devis_get($id) : null;
    if (!$d || !function_exists('commandes_table')) {
        wp_safe_redirect(admin_url('admin.php?page=devis')); exit;
    }
    // Déjà converti → on ouvre la commande existante
    if ((int)$d->commande_id > 0) {
        wp_safe_redirect(admin_url('admin.php?page=commandes&view=' . (int)$d->commande_id)); exit;
    }
    global $wpdb;
    $ct = commandes_table();
    $wpdb->insert($ct, array(
        'client_id'       => (int)$d->client_id,
        'vehicule_id'     => (int)$d->vehicule_id,
        'date_commande'   => current_time('Y-m-d'),
        'couleur'         => $d->couleur,
        'prix'            => $d->prix,
        'remise'          => $d->remise,
        'avance'          => 0,
        'mode_paiement'   => '',
        'delai_livraison' => $d->delai_livraison,
        'statut'          => 'En cours',
        'conditions'      => $d->conditions,
        'notes'           => $d->notes,
        'created_by'      => get_current_user_id(),
        'created_at'      => current_time('mysql'),
        'updated_at'      => current_time('mysql'),
    ));
    $cid = (int)$wpdb->insert_id;
    $wpdb->update($ct, array('numero' => 'BC-' . current_time('Y') . '-' . str_pad($cid, 4, '0', STR_PAD_LEFT)), array('id' => $cid));
    $wpdb->update(devis_table(), array('statut' => 'Converti en commande', 'commande_id' => $cid, 'updated_at' => current_time('mysql')), array('id' => $id));

    wp_safe_redirect(admin_url('admin.php?page=commandes&view=' . $cid . '&iac_msg=csaved'));
    exit;
}

/* ============================================================
 *  SECTION DEVIS
 * ============================================================ */
function devis_page_section() {
    acces_guard(acces_can_view('devis'));
    if (isset($_GET['proforma']) && (int)$_GET['proforma'] > 0) { devis_page_proforma(); return; }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit'), true)) $tab = 'list';
    iac_section_tabs('devis', $tab);
    if ($tab === 'edit') { acces_guard(acces_can_edit('devis')); devis_page_edit(); }
    else                 devis_page_list();
}

/* ============================================================
 *  PAGE : Liste des devis
 * ============================================================ */
function devis_page_list() {
    $statut = isset($_GET['statut']) ? sanitize_text_field(wp_unslash($_GET['statut'])) : '';
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $devis  = devis_get_all(array('statut' => $statut, 'search' => $search));
    $can_edit = acces_can_edit('devis');

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Devis (Proforma)</h1>';
    if ($can_edit) echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=devis&tab=edit')) . '">+ Nouveau devis</a>';
    echo '</div>';

    if (isset($_GET['iac_msg'])) {
        $m = array('dsaved' => 'Devis enregistré.', 'ddeleted' => 'Devis supprimé.');
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    $base = admin_url('admin.php?page=devis');
    echo '<p style="margin:0 0 12px">';
    $tabs = array('' => 'Tous') + array_combine(devis_statuts(), devis_statuts());
    foreach ($tabs as $key => $lbl) {
        $url = $key === '' ? $base : add_query_arg('statut', $key, $base);
        $style = ($statut === $key) ? 'font-weight:700;text-decoration:none' : 'text-decoration:none';
        echo '<a href="' . esc_url($url) . '" style="' . $style . ';margin-right:14px">' . esc_html($lbl) . '</a>';
    }
    echo '</p>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:130px">N°</th><th>Client</th><th>Véhicule</th><th>Montant estimé</th><th>Statut</th><th>Créé par</th><th style="width:280px">Actions</th></tr></thead><tbody>';

    if (!$devis) {
        echo '<tr><td colspan="7">Aucun devis. <a href="' . esc_url(admin_url('admin.php?page=devis&tab=edit')) . '">Créez-en un</a>.</td></tr>';
    } else {
        foreach ($devis as $d) {
            $pro  = admin_url('admin.php?page=devis&proforma=' . $d->id);
            $edit = admin_url('admin.php?page=devis&tab=edit&id=' . $d->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=devis_delete&id=' . $d->id), 'devis_delete_' . $d->id);
            $conv = wp_nonce_url(admin_url('admin-post.php?action=devis_convert&id=' . $d->id), 'devis_convert_' . $d->id);
            $pill = $d->statut === 'Accepté' ? 'ok' : ($d->statut === 'Converti en commande' ? 'ok' : ($d->statut === 'Expiré' ? 'sold' : 'cmd'));
            $client = '—'; if ($d->client_id && function_exists('iac_get_client')) { $cl = iac_get_client($d->client_id); if ($cl) $client = iac_client_name($cl); }
            $veh = ''; if ($d->vehicule_id && function_exists('ia_get_vehicle')) { $vv = ia_get_vehicle($d->vehicule_id); if ($vv) $veh = ia_vehicle_title($vv); }
            echo '<tr>';
            echo '<td><strong>' . esc_html($d->numero) . '</strong></td>';
            echo '<td>' . esc_html($client) . '</td>';
            echo '<td>' . esc_html($veh) . '</td>';
            echo '<td>' . esc_html(devis_money(devis_prix_net($d))) . '</td>';
            echo '<td><span class="iac-pill ' . $pill . '">' . esc_html($d->statut) . '</span></td>';
            echo '<td style="font-size:12px;color:#555">' . esc_html(meta_created_text($d->created_by ?? 0, $d->created_at ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($pro) . '">📄 Proforma</a>';
            if ($can_edit) {
                echo ' | <a href="' . esc_url($edit) . '">Modifier</a>';
                if ((int)$d->commande_id > 0) {
                    echo ' | <a href="' . esc_url(admin_url('admin.php?page=commandes&view=' . (int)$d->commande_id)) . '">Voir la commande</a>';
                } else {
                    echo ' | <a href="' . esc_url($conv) . '" style="color:#1a7a3c" onclick="return confirm(\'Convertir ce devis en commande ?\')">➜ Convertir</a>';
                }
                echo ' | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer ce devis ?\')" style="color:#b23b3b">Suppr.</a>';
            }
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Ajouter / Modifier un devis
 * ============================================================ */
function devis_page_edit() {
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $d   = $id ? devis_get($id) : null;
    $get = function($k, $dft = '') use ($d) { return $d && isset($d->$k) ? $d->$k : $dft; };
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier le devis' : 'Nouveau devis') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=devis')) . '">← Retour à la liste</a></div>';

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('devis_save');
    echo '<input type="hidden" name="action" value="devis_save">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    echo '<div class="row">';
    echo '<div class="fld"><label>Client</label><select name="client_id" required>';
    echo '<option value="">— Choisir un client —</option>';
    if (function_exists('iac_get_clients')) {
        foreach (iac_get_clients(array('active' => 1, 'orderby' => 'nom', 'order' => 'ASC')) as $cc) {
            echo '<option value="' . (int)$cc->id . '" ' . selected((int)$get('client_id', 0), (int)$cc->id, false) . '>' . esc_html(iac_client_name($cc)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '<div class="fld"><label>Véhicule</label><select id="devis_vehicule" name="vehicule_id" required>';
    echo '<option value="">— Choisir un véhicule —</option>';
    if (function_exists('ia_get_vehicles')) {
        foreach (ia_get_vehicles(array('orderby' => 'marque', 'order' => 'ASC')) as $vv) {
            $vprix = (int)$vv->prix * 10000;
            echo '<option value="' . (int)$vv->id . '" data-prix="' . esc_attr($vprix) . '" ' . selected((int)$get('vehicule_id', 0), (int)$vv->id, false) . '>' . esc_html(ia_vehicle_title($vv)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="fld"><label>Couleur souhaitée</label><input type="text" name="couleur" value="' . esc_attr($get('couleur')) . '"></div>';
    echo '<div class="fld"><label>Date du devis</label><input type="date" name="date_devis" value="' . esc_attr(($get('date_devis') && $get('date_devis') !== '0000-00-00') ? $get('date_devis') : current_time('Y-m-d')) . '"></div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="fld"><label>Prix estimé (DA)</label><input type="number" step="0.01" min="0" id="devis_prix" name="prix" value="' . esc_attr($get('prix', '')) . '" required></div>';
    echo '<div class="fld"><label>Remise (%)</label><input type="number" step="0.01" min="0" max="100" id="devis_remise" name="remise" value="' . esc_attr($get('remise', '0')) . '"></div>';
    echo '<div class="fld"><label>Statut</label><select name="statut">';
    foreach (devis_statuts() as $s) echo '<option ' . selected($get('statut', 'En attente'), $s, false) . '>' . esc_html($s) . '</option>';
    echo '</select></div>';
    echo '</div>';
    echo '<p style="margin:-6px 0 16px;color:#555">Prix estimé après remise : <strong id="devis_net">—</strong></p>';

    echo '<div class="row"><div class="fld"><label>Délai de livraison estimé</label><input type="text" name="delai_livraison" value="' . esc_attr($get('delai_livraison')) . '" placeholder="Ex : 90 jours"></div><div class="fld"></div></div>';
    echo '<div class="fld"><label>Conditions particulières</label><textarea name="conditions" rows="3">' . esc_textarea($get('conditions')) . '</textarea></div>';
    echo '<div class="fld"><label>Notes internes</label><textarea name="notes" rows="2">' . esc_textarea($get('notes')) . '</textarea></div>';

    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer et voir la Proforma' : 'Créer le devis') . '</button></p>';
    echo '</form></div>';
    ?>
    <script>
    jQuery(function($){
      function fmt(n){ return (Number(n)||0).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' DA'; }
      function net(){
        var p = parseFloat($('#devis_prix').val()) || 0;
        var r = parseFloat($('#devis_remise').val()) || 0;
        if (r < 0) r = 0; if (r > 100) r = 100;
        $('#devis_net').text(fmt(p * (1 - r / 100)));
      }
      $('#devis_vehicule').on('change', function(){
        var p = $(this).find('option:selected').data('prix');
        if (p !== undefined && parseFloat(p) > 0) $('#devis_prix').val(p);
        net();
      });
      $('#devis_prix, #devis_remise').on('input', net);
      net();
    });
    </script>
    <?php
}

/* ============================================================
 *  PAGE : Facture Proforma imprimable
 * ============================================================ */
function devis_page_proforma() {
    $id = isset($_GET['proforma']) ? (int)$_GET['proforma'] : 0;
    $d  = $id ? devis_get($id) : null;
    iac_admin_style();
    if (!$d) {
        echo '<div class="wrap"><h1>Facture Proforma</h1><p>Devis introuvable.</p>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=devis')) . '">← Retour</a></div>';
        return;
    }

    $client = ($d->client_id && function_exists('iac_get_client')) ? iac_get_client($d->client_id) : null;
    $veh    = ($d->vehicule_id && function_exists('ia_get_vehicle')) ? ia_get_vehicle($d->vehicule_id) : null;
    $logo   = function_exists('ia_img') ? ia_img('Logo_intermediate_auto_black.jpeg') : '';
    $soc    = defined('SOCIETE_NOM') ? SOCIETE_NOM : get_bloginfo('name');
    $addr   = defined('IA_ADDRESS') ? IA_ADDRESS : '';
    $phone  = defined('IA_PHONE') ? IA_PHONE : '';
    $email  = defined('IA_EMAIL') ? IA_EMAIL : '';
    $date   = ($d->date_devis && $d->date_devis !== '0000-00-00') ? date_i18n('j F Y', strtotime($d->date_devis)) : '';
    $net    = devis_prix_net($d);
    $can_edit = acces_can_edit('devis');
    $conv   = wp_nonce_url(admin_url('admin-post.php?action=devis_convert&id=' . $d->id), 'devis_convert_' . $d->id);

    echo '<div class="wrap no-print" style="margin-bottom:14px">';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=devis')) . '">← Retour à la liste</a> ';
    if ($can_edit) echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=devis&tab=edit&id=' . $d->id)) . '">✎ Modifier</a> ';
    echo '<button class="iac-btn" onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button> ';
    if ($can_edit) {
        if ((int)$d->commande_id > 0) {
            echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=commandes&view=' . (int)$d->commande_id)) . '">Voir la commande</a>';
        } else {
            echo '<a class="button button-primary" href="' . esc_url($conv) . '" onclick="return confirm(\'Convertir ce devis en commande ?\')">➜ Convertir en commande</a>';
        }
    }
    echo '</div>';
    ?>
    <style>
    .pro{max-width:820px;background:#fff;margin:0 20px 30px;padding:38px 42px;border:1px solid #e2e4e9;border-radius:8px;color:#222;font-size:14px;line-height:1.5}
    .pro-top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #D4AF37;padding-bottom:18px}
    .pro-top img{height:74px}
    .pro-soc{text-align:right;font-size:12.5px;color:#444}
    .pro-soc b{font-size:15px;color:#1a1a1a;display:block;margin-bottom:4px}
    .pro-title{text-align:center;margin:22px 0}
    .pro-title h2{font-size:23px;letter-spacing:1px;margin:0;color:#1a1a1a}
    .pro-title .num{display:inline-block;margin-top:8px;background:#1A1A1A;color:#fff;padding:5px 16px;border-radius:6px;font-weight:700;letter-spacing:1px}
    .pro-cols{display:flex;gap:24px;margin:22px 0}
    .pro-box{flex:1;border:1px solid #e2e4e9;border-radius:8px;padding:14px 16px}
    .pro-box h3{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:#C05A00;border-bottom:1px solid #eee;padding-bottom:6px}
    .pro-box .l{display:flex;justify-content:space-between;gap:12px;padding:3px 0}
    .pro-box .l span{color:#777}
    table.pro-amt{width:100%;border-collapse:collapse;margin:18px 0}
    table.pro-amt td{padding:10px 14px;border:1px solid #e2e4e9}
    table.pro-amt .lbl{background:#f7f8fa;font-weight:600;width:60%}
    table.pro-amt .tot{background:#fff7e6;font-weight:800;font-size:16px}
    .pro-mention{margin:18px 0;padding:14px 16px;background:#fff8e1;border:1px solid #f0d98a;border-radius:8px;font-size:12.5px;color:#5a4a00;font-style:italic}
    .pro-foot{margin-top:26px;border-top:1px solid #eee;padding-top:12px;text-align:center;font-size:11.5px;color:#999}
    @media print{
        #adminmenumain,#wpadminbar,#wpfooter,#screen-meta,#screen-meta-links,.no-print,.update-nag,.notice{display:none!important}
        #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
        html.wp-toolbar{padding-top:0!important}
        .pro{border:0;margin:0;max-width:100%}
        @page{margin:14mm}
    }
    </style>
    <div class="pro">
        <div class="pro-top">
            <?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($soc); ?>"><?php else: ?><b><?php echo esc_html($soc); ?></b><?php endif; ?>
            <div class="pro-soc">
                <b><?php echo esc_html($soc); ?></b>
                <?php if ($addr)  echo esc_html($addr) . '<br>'; ?>
                <?php if ($phone) echo 'Tél : ' . esc_html($phone) . '<br>'; ?>
                <?php if ($email) echo esc_html($email); ?>
            </div>
        </div>

        <div class="pro-title">
            <h2>FACTURE PROFORMA (DEVIS)</h2>
            <div class="num"><?php echo esc_html($d->numero); ?></div>
            <?php if ($date): ?><div style="margin-top:8px;color:#666">Date : <?php echo esc_html($date); ?></div><?php endif; ?>
        </div>

        <div class="pro-cols">
            <div class="pro-box">
                <h3>Client</h3>
                <?php if ($client) {
                    echo '<div class="l"><span>Nom</span><b>' . esc_html(iac_client_name($client)) . '</b></div>';
                    if ($client->telephone) echo '<div class="l"><span>Téléphone</span><span>' . esc_html($client->telephone) . '</span></div>';
                    if ($client->adresse || $client->wilaya) echo '<div class="l"><span>Adresse</span><span>' . esc_html(trim($client->adresse . ' ' . $client->wilaya)) . '</span></div>';
                } else echo '<p>—</p>'; ?>
            </div>
            <div class="pro-box">
                <h3>Véhicule</h3>
                <?php if ($veh) {
                    echo '<div class="l"><span>Modèle</span><b>' . esc_html(ia_vehicle_title($veh)) . '</b></div>';
                    if ($veh->carburant) echo '<div class="l"><span>Carburant</span><span>' . esc_html($veh->carburant) . '</span></div>';
                    if ($veh->boite)     echo '<div class="l"><span>Boîte</span><span>' . esc_html($veh->boite) . '</span></div>';
                } else echo '<div class="l"><span>Véhicule</span><span>—</span></div>';
                if ($d->couleur)         echo '<div class="l"><span>Couleur</span><span>' . esc_html($d->couleur) . '</span></div>';
                if ($d->delai_livraison) echo '<div class="l"><span>Délai estimé</span><span>' . esc_html($d->delai_livraison) . '</span></div>';
                ?>
            </div>
        </div>

        <table class="pro-amt">
            <tr><td class="lbl">Prix estimé du véhicule</td><td><?php echo esc_html(devis_money($d->prix)); ?></td></tr>
            <?php if ((float)$d->remise > 0):
                $rlabel = rtrim(rtrim(number_format((float)$d->remise, 2, ',', ''), '0'), ',');
            ?>
            <tr><td class="lbl">Remise (<?php echo esc_html($rlabel); ?> %)</td><td>- <?php echo esc_html(devis_money((float)$d->prix * (float)$d->remise / 100)); ?></td></tr>
            <?php endif; ?>
            <tr><td class="lbl tot">Total estimé</td><td class="tot"><?php echo esc_html(devis_money($net)); ?></td></tr>
        </table>

        <div class="pro-mention"><?php echo esc_html(devis_mention()); ?></div>

        <?php if ($d->conditions): ?>
        <div style="font-size:13px;color:#444"><strong>Conditions :</strong><br><?php echo nl2br(esc_html($d->conditions)); ?></div>
        <?php endif; ?>

        <div class="pro-foot">Établi par <?php echo esc_html(acces_user_name($d->created_by ?? 0)); ?> · <?php echo esc_html($soc); ?> · Facture Proforma <?php echo esc_html($d->numero); ?> · Document non contractuel</div>
    </div>
    <?php
}
