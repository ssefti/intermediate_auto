<?php
/**
 * Gestion des livraisons.
 * Table wp_livraisons : liste, création/édition et bon de livraison imprimable
 * (mêmes informations que le bon de commande + numéro de châssis obligatoire).
 */
if (!defined('ABSPATH')) exit;

define('LIVRAISONS_VER', '1.0');

function livraisons_table() {
    global $wpdb;
    return $wpdb->prefix . 'livraisons';
}

/* ============================================================
 *  INSTALLATION DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'livraisons_maybe_install');
function livraisons_maybe_install() {
    if (get_option('livraisons_db_ver') === LIVRAISONS_VER) return;
    global $wpdb;
    $table   = livraisons_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        numero VARCHAR(40) NOT NULL DEFAULT '',
        client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        commande_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        numero_chassis VARCHAR(40) NOT NULL DEFAULT '',
        date_livraison DATE NULL DEFAULT NULL,
        notes TEXT NULL,
        created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY client_id (client_id),
        KEY commande_id (commande_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('livraisons_db_ver', LIVRAISONS_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
function livraisons_get_all($args = array()) {
    global $wpdb;
    $t = livraisons_table();
    $args = wp_parse_args($args, array('search' => ''));
    $sql = "SELECT * FROM {$t}";
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $sql = $wpdb->prepare($sql . " WHERE (numero LIKE %s OR numero_chassis LIKE %s)", $like, $like);
    }
    return $wpdb->get_results($sql . " ORDER BY id DESC");
}

function livraison_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . livraisons_table() . " WHERE id = %d", (int)$id));
}

/* ============================================================
 *  ENREGISTREMENT
 * ============================================================ */
add_action('admin_post_livraison_save', 'livraison_save');
function livraison_save() {
    acces_guard(acces_can_edit('livraisons'));
    check_admin_referer('livraison_save');

    global $wpdb;
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $client_id   = (int)($_POST['client_id'] ?? 0);
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    $back        = admin_url('admin.php?page=livraisons&tab=edit' . ($id ? '&id=' . $id : '') . '&commande_id=' . $commande_id);

    $cmd = $commande_id ? commande_get($commande_id) : null;
    if (!$client_id || !$cmd) {
        wp_safe_redirect($back . '&iac_msg=lerr_commande'); exit;
    }
    if ((int)$cmd->client_id !== $client_id) {
        wp_safe_redirect($back . '&iac_msg=lerr_client'); exit;
    }

    // N° de châssis : saisi, sinon repris de la commande, sinon obligatoire
    $chassis = strtoupper(sanitize_text_field($_POST['numero_chassis'] ?? ''));
    if ($chassis === '') $chassis = (string)$cmd->numero_chassis;
    if ($chassis === '') {
        wp_safe_redirect($back . '&iac_msg=lerr_chassis'); exit;
    }

    $date = sanitize_text_field($_POST['date_livraison'] ?? '');
    if ($date === '') $date = current_time('Y-m-d');

    $data = array(
        'client_id'      => $client_id,
        'commande_id'    => $commande_id,
        'numero_chassis' => $chassis,
        'date_livraison' => $date,
        'notes'          => sanitize_textarea_field($_POST['notes'] ?? ''),
        'updated_at'     => current_time('mysql'),
    );

    if ($id > 0) {
        $wpdb->update(livraisons_table(), $data, array('id' => $id));
    } else {
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $wpdb->insert(livraisons_table(), $data);
        $id = (int)$wpdb->insert_id;
        $wpdb->update(livraisons_table(), array('numero' => 'BL-' . substr($date, 0, 4) . '-' . str_pad($id, 4, '0', STR_PAD_LEFT)), array('id' => $id));
    }

    // La commande n'avait pas de n° de châssis : on le renseigne
    if ((string)$cmd->numero_chassis === '') {
        $wpdb->update(commandes_table(), array('numero_chassis' => $chassis, 'updated_at' => current_time('mysql')), array('id' => $commande_id));
    }

    wp_safe_redirect(admin_url('admin.php?page=livraisons&view=' . $id . '&iac_msg=lsaved'));
    exit;
}

add_action('admin_post_livraison_delete', 'livraison_delete');
function livraison_delete() {
    acces_guard(acces_can_edit('livraisons'));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('livraison_delete_' . $id);
    if ($id > 0) {
        global $wpdb;
        $wpdb->delete(livraisons_table(), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=livraisons&iac_msg=ldeleted'));
    exit;
}

/* ============================================================
 *  SECTION LIVRAISONS
 * ============================================================ */
function livraisons_page_section() {
    acces_guard(acces_can_view('livraisons'));
    if (isset($_GET['view']) && (int)$_GET['view'] > 0) { livraison_page_bon(); return; }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit'), true)) $tab = 'list';
    iac_section_tabs('livraisons', $tab);
    if ($tab === 'edit') { acces_guard(acces_can_edit('livraisons')); livraison_page_edit(); }
    else                 livraisons_page_list();
}

function livraison_notice() {
    if (!isset($_GET['iac_msg'])) return;
    $m = array(
        'lsaved'        => array('success', 'Bon de livraison enregistré.'),
        'ldeleted'      => array('success', 'Bon de livraison supprimé.'),
        'lerr_commande' => array('error', 'Veuillez choisir un client et une de ses commandes.'),
        'lerr_client'   => array('error', 'La commande choisie n\'appartient pas à ce client.'),
        'lerr_chassis'  => array('error', 'Le numéro de châssis est obligatoire : il n\'est pas renseigné sur la commande.'),
    );
    $k = sanitize_key($_GET['iac_msg']);
    if (isset($m[$k])) echo '<div class="notice notice-' . $m[$k][0] . ' is-dismissible"><p>' . esc_html($m[$k][1]) . '</p></div>';
}

/* ============================================================
 *  PAGE : Liste
 * ============================================================ */
function livraisons_page_list() {
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $rows   = livraisons_get_all(array('search' => $search));
    $can_edit = acces_can_edit('livraisons');

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Gestion des livraisons</h1><div>';
    if ($can_edit) echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=livraisons&tab=edit')) . '">+ Nouveau bon de livraison</a>';
    echo '</div></div>';
    livraison_notice();

    echo '<form method="get" style="margin-bottom:16px"><input type="hidden" name="page" value="livraisons">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher (n° bon, n° de châssis…)" style="width:300px">';
    echo ' <button class="button">Rechercher</button></form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:130px">N°</th><th>Client</th><th>Commande</th><th>N° de châssis</th><th>Date</th><th>Créé par</th><th style="width:230px">Actions</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="7">Aucun bon de livraison.' . ($can_edit ? ' <a href="' . esc_url(admin_url('admin.php?page=livraisons&tab=edit')) . '">Créez-en un</a>.' : '') . '</td></tr>';
    } else {
        foreach ($rows as $l) {
            $client = '—'; if ($l->client_id && function_exists('iac_get_client')) { $cl = iac_get_client($l->client_id); if ($cl) $client = iac_client_name($cl); }
            $cmd = $l->commande_id ? commande_get($l->commande_id) : null;
            $view = admin_url('admin.php?page=livraisons&view=' . $l->id);
            $edit = admin_url('admin.php?page=livraisons&tab=edit&id=' . $l->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=livraison_delete&id=' . $l->id), 'livraison_delete_' . $l->id);
            echo '<tr>';
            echo '<td><strong>' . esc_html($l->numero) . '</strong></td>';
            echo '<td>' . esc_html($client) . '</td>';
            echo '<td>' . esc_html($cmd ? $cmd->numero : '—') . '</td>';
            echo '<td>' . esc_html($l->numero_chassis) . '</td>';
            echo '<td>' . esc_html(($l->date_livraison && $l->date_livraison !== '0000-00-00') ? date_i18n('j/m/Y', strtotime($l->date_livraison)) : '') . '</td>';
            echo '<td style="font-size:12px;color:#555">' . esc_html(meta_created_text($l->created_by ?? 0, $l->created_at ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($view) . '">📄 Bon de livraison</a>';
            if ($can_edit) echo ' | <a href="' . esc_url($edit) . '">Modifier</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer ce bon de livraison ?\')" style="color:#b23b3b">Suppr.</a>';
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Créer / modifier un bon de livraison
 * ============================================================ */
function livraison_page_edit() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $l  = $id ? livraison_get($id) : null;
    $cur_commande = $l ? (int)$l->commande_id : (isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0);
    $pre_cmd      = $cur_commande ? commande_get($cur_commande) : null;
    $cur_client   = $l ? (int)$l->client_id : ($pre_cmd ? (int)$pre_cmd->client_id : 0);
    $chassis      = $l ? $l->numero_chassis : ($pre_cmd ? $pre_cmd->numero_chassis : '');
    $date         = ($l && $l->date_livraison && $l->date_livraison !== '0000-00-00') ? $l->date_livraison : current_time('Y-m-d');

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier le bon de livraison' : 'Nouveau bon de livraison') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=livraisons')) . '">← Retour à la liste</a></div>';
    livraison_notice();

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('livraison_save');
    echo '<input type="hidden" name="action" value="livraison_save">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    echo '<div class="row">';
    echo '<div class="fld"><label>Client</label><select id="liv_client" name="client_id" required>';
    echo '<option value="">— Choisir un client —</option>';
    if (function_exists('iac_get_clients')) {
        foreach (iac_get_clients(array('active' => 1, 'orderby' => 'nom', 'order' => 'ASC')) as $cc) {
            echo '<option value="' . (int)$cc->id . '" ' . selected($cur_client, (int)$cc->id, false) . '>' . esc_html(iac_client_name($cc)) . '</option>';
        }
    }
    echo '</select></div>';

    echo '<div class="fld"><label>Commande</label><select id="liv_commande" name="commande_id" required>';
    echo '<option value="">— Choisir une commande —</option>';
    foreach (commandes_get_all() as $cmd) {
        $lbl = $cmd->numero;
        if ($cmd->vehicule_id && function_exists('ia_get_vehicle')) { $vv = ia_get_vehicle($cmd->vehicule_id); if ($vv) $lbl .= ' — ' . ia_vehicle_title($vv); }
        echo '<option value="' . (int)$cmd->id . '" data-client="' . (int)$cmd->client_id . '" data-chassis="' . esc_attr($cmd->numero_chassis) . '" ' . selected($cur_commande, (int)$cmd->id, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<div class="fld"><label>Numéro de châssis <span style="color:#d63638">*</span> <span id="liv_chassis_hint" style="font-weight:400;color:#999"></span></label><input type="text" id="liv_chassis" name="numero_chassis" maxlength="40" required style="text-transform:uppercase" value="' . esc_attr($chassis) . '"></div>';
    echo '<div class="fld"><label>Date de livraison</label><input type="date" name="date_livraison" value="' . esc_attr($date) . '"></div>';
    echo '</div>';

    echo '<div class="fld"><label>Notes</label><textarea name="notes" rows="3">' . esc_textarea($l ? $l->notes : '') . '</textarea></div>';
    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer et voir le bon' : 'Créer le bon de livraison') . '</button></p>';
    echo '</form></div>';
    ?>
    <script>
    jQuery(function($){
      var $cl = $('#liv_client'), $cmd = $('#liv_commande'), $ch = $('#liv_chassis'), $hint = $('#liv_chassis_hint');
      // N'affiche que les commandes du client choisi
      function filterCommandes(){
        var c = $cl.val();
        $cmd.find('option').each(function(){
          var oc = $(this).data('client');
          var show = !$(this).val() || !c || String(oc) === String(c);
          $(this).prop('hidden', !show).prop('disabled', !show);
        });
        var sel = $cmd.find('option:selected');
        if (sel.val() && sel.prop('disabled')) $cmd.val('');
      }
      // Reprend le n° de châssis de la commande ; sinon obligatoire à saisir
      function syncChassis(){
        var sel = $cmd.find('option:selected');
        if (!sel.val()) { $hint.text(''); return; }
        var ch = sel.data('chassis') || '';
        if (ch) { $ch.val(ch); $hint.text('— repris de la commande'); }
        else    { $hint.text('— non renseigné sur la commande, à saisir'); }
      }
      $cl.on('change', function(){ filterCommandes(); syncChassis(); });
      $cmd.on('change', function(){
        var oc = $cmd.find('option:selected').data('client');
        if (oc) $cl.val(String(oc));
        filterCommandes(); syncChassis();
      });
      filterCommandes();
      if (!$ch.val()) syncChassis();
    });
    </script>
    <?php
}

/* ============================================================
 *  PAGE : Bon de livraison imprimable
 * ============================================================ */
function livraison_page_bon() {
    $id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $l  = $id ? livraison_get($id) : null;
    iac_admin_style();

    $c = $l ? commande_get($l->commande_id) : null;
    if (!$l || !$c) {
        echo '<div class="wrap"><h1>Bon de livraison</h1><p>Bon de livraison ou commande introuvable.</p>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=livraisons')) . '">← Retour</a></div>';
        return;
    }

    $client = $l->client_id && function_exists('iac_get_client') ? iac_get_client($l->client_id) : null;
    $veh    = $c->vehicule_id && function_exists('ia_get_vehicle') ? ia_get_vehicle($c->vehicule_id) : null;
    $logo   = function_exists('ia_img') ? ia_img('Logo_intermediate_auto_black.jpeg') : '';
    $date   = ($l->date_livraison && $l->date_livraison !== '0000-00-00') ? date_i18n('j F Y', strtotime($l->date_livraison)) : '';
    $cdate  = ($c->date_commande && $c->date_commande !== '0000-00-00') ? date_i18n('j F Y', strtotime($c->date_commande)) : '';
    $reste  = commande_reste($c);
    $avance_eff = commande_avance_effective($c);
    $linked = function_exists('avances_for_commande') ? avances_for_commande($c->id) : array();

    $soc_addr  = defined('IA_ADDRESS') ? IA_ADDRESS : '';
    $soc_phone = defined('IA_PHONE')   ? IA_PHONE   : '';
    $soc_email = defined('IA_EMAIL')   ? IA_EMAIL   : '';

    echo '<div class="wrap no-print" style="margin-bottom:14px">';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=livraisons')) . '">← Retour à la liste</a> ';
    if (acces_can_edit('livraisons')) echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=livraisons&tab=edit&id=' . $l->id)) . '">✎ Modifier</a> ';
    if (acces_can_view('commandes')) echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=commandes&view=' . $c->id)) . '">📄 Bon de commande</a> ';
    echo '<button class="iac-btn" onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>';
    echo '</div>';
    livraison_notice();
    ?>
    <style>
    .bon{max-width:820px;background:#fff;margin:0 20px 30px;padding:38px 42px;border:1px solid #e2e4e9;border-radius:8px;color:#222;font-size:14px;line-height:1.5}
    .bon-top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #D4AF37;padding-bottom:18px;margin-bottom:8px}
    .bon-top img{height:74px}
    .bon-soc{text-align:right;font-size:12.5px;color:#444}
    .bon-soc b{font-size:15px;color:#1a1a1a;display:block;margin-bottom:4px}
    .bon-title{text-align:center;margin:22px 0}
    .bon-title h2{font-size:24px;letter-spacing:1px;margin:0;color:#1a1a1a}
    .bon-title .num{display:inline-block;margin-top:8px;background:#1A1A1A;color:#fff;padding:5px 16px;border-radius:6px;font-weight:700;letter-spacing:1px}
    .bon-cols{display:flex;gap:24px;margin:22px 0}
    .bon-box{flex:1;border:1px solid #e2e4e9;border-radius:8px;padding:14px 16px}
    .bon-box h3{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:#C05A00;border-bottom:1px solid #eee;padding-bottom:6px}
    .bon-box .l{display:flex;justify-content:space-between;gap:12px;padding:3px 0}
    .bon-box .l span{color:#777}
    .bon-chassis{margin:18px 0;padding:12px 16px;border:2px solid #1A1A1A;border-radius:8px;text-align:center;font-size:16px}
    .bon-chassis b{letter-spacing:2px;font-size:18px}
    table.bon-amounts{width:100%;border-collapse:collapse;margin:18px 0}
    table.bon-amounts td{padding:10px 14px;border:1px solid #e2e4e9}
    table.bon-amounts .lbl{background:#f7f8fa;font-weight:600;width:60%}
    table.bon-amounts .reste{background:#fff7e6;font-weight:800;font-size:16px}
    .bon-cond{margin:14px 0;font-size:13px;color:#444}
    .bon-sign{display:flex;gap:40px;margin-top:46px}
    .bon-sign div{flex:1;text-align:center}
    .bon-sign .line{border-top:1px solid #999;margin-top:64px;padding-top:8px;font-weight:600;color:#555}
    .bon-foot{margin-top:30px;border-top:1px solid #eee;padding-top:12px;text-align:center;font-size:11.5px;color:#999}
    @media print{
        #adminmenumain,#wpadminbar,#wpfooter,#screen-meta,#screen-meta-links,.no-print,.update-nag,.notice{display:none!important}
        #wpcontent,#wpbody-content{margin:0!important;padding:0!important}
        html.wp-toolbar{padding-top:0!important}
        .bon{border:0;margin:0;max-width:100%}
        @page{margin:14mm}
    }
    </style>
    <div class="bon">
        <div class="bon-top">
            <?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(SOCIETE_NOM); ?>"><?php else: ?><b><?php echo esc_html(SOCIETE_NOM); ?></b><?php endif; ?>
            <div class="bon-soc">
                <b><?php echo esc_html(SOCIETE_NOM); ?></b>
                <?php if ($soc_addr)  echo esc_html($soc_addr) . '<br>'; ?>
                <?php if ($soc_phone) echo 'Tél : ' . esc_html($soc_phone) . '<br>'; ?>
                <?php if ($soc_email) echo esc_html($soc_email) . '<br>'; ?>
                <?php if (SOCIETE_NIF) echo 'NIF : ' . esc_html(SOCIETE_NIF) . '<br>'; ?>
                <?php if (SOCIETE_RC)  echo 'RC : ' . esc_html(SOCIETE_RC) . ' '; ?>
                <?php if (SOCIETE_ART) echo 'Art : ' . esc_html(SOCIETE_ART); ?>
            </div>
        </div>

        <div class="bon-title">
            <h2>BON DE LIVRAISON</h2>
            <div class="num"><?php echo esc_html($l->numero); ?></div>
            <?php if ($date): ?><div style="margin-top:8px;color:#666">Date de livraison : <?php echo esc_html($date); ?></div><?php endif; ?>
            <div style="margin-top:4px;color:#666">Commande <?php echo esc_html($c->numero); ?><?php if ($cdate) echo ' du ' . esc_html($cdate); ?></div>
        </div>

        <div class="bon-cols">
            <div class="bon-box">
                <h3>Client</h3>
                <?php
                if ($client) {
                    echo '<div class="l"><span>Nom</span><b>' . esc_html(iac_client_name($client)) . '</b></div>';
                    echo '<div class="l"><span>Type</span><span>' . ($client->type === 'entreprise' ? 'Entreprise' : 'Particulier') . '</span></div>';
                    if ($client->telephone) echo '<div class="l"><span>Téléphone</span><span>' . esc_html($client->telephone) . '</span></div>';
                    if ($client->adresse || $client->wilaya) echo '<div class="l"><span>Adresse</span><span>' . esc_html(trim($client->adresse . ' ' . $client->wilaya)) . '</span></div>';
                    if ($client->type === 'entreprise') {
                        if ($client->nif) echo '<div class="l"><span>NIF</span><span>' . esc_html($client->nif) . '</span></div>';
                        if ($client->rc)  echo '<div class="l"><span>RC</span><span>' . esc_html($client->rc) . '</span></div>';
                    } elseif ($client->piece_numero) {
                        echo '<div class="l"><span>' . esc_html($client->piece_type ?: 'Pièce') . '</span><span>' . esc_html($client->piece_numero) . '</span></div>';
                    }
                } else { echo '<p>—</p>'; }
                ?>
            </div>
            <div class="bon-box">
                <h3>Véhicule</h3>
                <?php
                if ($veh) {
                    echo '<div class="l"><span>Modèle</span><b>' . esc_html(ia_vehicle_title($veh)) . '</b></div>';
                    if ($veh->carburant) echo '<div class="l"><span>Carburant</span><span>' . esc_html($veh->carburant) . '</span></div>';
                    if ($veh->boite)     echo '<div class="l"><span>Boîte</span><span>' . esc_html($veh->boite) . '</span></div>';
                } else { echo '<div class="l"><span>Véhicule</span><span>—</span></div>'; }
                if ($c->couleur) echo '<div class="l"><span>Couleur</span><span>' . esc_html($c->couleur) . '</span></div>';
                ?>
            </div>
        </div>

        <div class="bon-chassis">N° de châssis : <b><?php echo esc_html($l->numero_chassis); ?></b></div>

        <table class="bon-amounts">
            <tr><td class="lbl">Prix total du véhicule</td><td><?php echo esc_html(commande_money(commande_prix_net($c))); ?></td></tr>
            <?php if ($linked): ?>
                <?php foreach ($linked as $av): ?>
                <tr><td class="lbl" style="font-weight:400"><?php
                    echo esc_html(isset($av->type_paiement) && $av->type_paiement ? $av->type_paiement : 'Paiement');
                    if ($av->date_avance && $av->date_avance !== '0000-00-00') echo ' du ' . esc_html(date_i18n('j/m/Y', strtotime($av->date_avance)));
                    if ($av->mode_paiement) echo ' · ' . esc_html($av->mode_paiement);
                    if ($av->statut !== 'Encaissée') echo ' (' . esc_html($av->statut) . ')';
                ?></td><td><?php echo esc_html(commande_money($av->montant)); ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="lbl">Total des paiements encaissés</td><td><?php echo esc_html(commande_money($avance_eff)); ?></td></tr>
            <?php else: ?>
                <tr><td class="lbl">Avance versée<?php if ($c->mode_paiement) echo ' (' . esc_html($c->mode_paiement) . ')'; ?></td><td><?php echo esc_html(commande_money($c->avance)); ?></td></tr>
            <?php endif; ?>
            <tr><td class="lbl reste">Reste à payer</td><td class="reste"><?php echo esc_html(commande_money($reste)); ?></td></tr>
        </table>

        <?php if ($c->conditions): ?>
        <div class="bon-cond"><strong>Conditions :</strong><br><?php echo nl2br(esc_html($c->conditions)); ?></div>
        <?php endif; ?>
        <?php if ($l->notes): ?>
        <div class="bon-cond"><strong>Remarques :</strong><br><?php echo nl2br(esc_html($l->notes)); ?></div>
        <?php endif; ?>

        <p class="bon-cond" style="margin-top:22px">Le client reconnaît avoir reçu le véhicule désigné ci-dessus, identifié par le numéro de châssis indiqué, en bon état apparent.</p>

        <div class="bon-sign">
            <div><div class="line">Signature du client (réception)</div></div>
            <div><div class="line">Signature et cachet — <?php echo esc_html(SOCIETE_NOM); ?></div></div>
        </div>

        <div class="bon-foot">Établi par <?php echo esc_html(acces_user_name($l->created_by ?? 0)); ?> · <?php echo esc_html(SOCIETE_NOM); ?> · Bon de livraison <?php echo esc_html($l->numero); ?> · Commande <?php echo esc_html($c->numero); ?></div>
    </div>
    <?php
}
