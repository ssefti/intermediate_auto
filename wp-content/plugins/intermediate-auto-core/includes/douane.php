<?php
/**
 * Gestion de la douane.
 * Table wp_douanes : liste, création/édition et bon de douane imprimable
 * (client, commande, droits de douane, échange, magasinage, frais annexes, taxes).
 */
if (!defined('ABSPATH')) exit;

define('DOUANE_VER', '1.0');

/** Montant par défaut des trois taxes (DA) */
define('DOUANE_TAXE_DEFAUT', 2000);

function douanes_table() {
    global $wpdb;
    return $wpdb->prefix . 'douanes';
}

/** Lignes de montant : colonne => libellé (ordre d'affichage) */
function douane_lignes() {
    return array(
        'droit_douane'       => 'Montant droit de douane',
        'montant_echange'    => 'Montant de l\'échange',
        'montant_magasinage' => 'Montant du magasinage',
        'frais_annexe'       => 'Frais annexes',
        'taxe_certification' => 'Taxe Certification Check',
        'taxe_tel'           => 'Taxe Tel',
        'taxe_modele'        => 'Taxe Modèle',
    );
}

function douane_total($d) {
    $t = 0;
    foreach (array_keys(douane_lignes()) as $k) $t += (float)($d->$k ?? 0);
    return $t;
}

/* ============================================================
 *  INSTALLATION DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'douanes_maybe_install');
function douanes_maybe_install() {
    if (get_option('douanes_db_ver') === DOUANE_VER) return;
    global $wpdb;
    $table   = douanes_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        numero VARCHAR(40) NOT NULL DEFAULT '',
        client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        commande_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        droit_douane DECIMAL(14,2) NOT NULL DEFAULT 0,
        montant_echange DECIMAL(14,2) NOT NULL DEFAULT 0,
        montant_magasinage DECIMAL(14,2) NOT NULL DEFAULT 0,
        frais_annexe DECIMAL(14,2) NOT NULL DEFAULT 0,
        taxe_certification DECIMAL(14,2) NOT NULL DEFAULT 2000,
        taxe_tel DECIMAL(14,2) NOT NULL DEFAULT 2000,
        taxe_modele DECIMAL(14,2) NOT NULL DEFAULT 2000,
        date_douane DATE NULL DEFAULT NULL,
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
    update_option('douanes_db_ver', DOUANE_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
function douanes_get_all($args = array()) {
    global $wpdb;
    $t = douanes_table();
    $args = wp_parse_args($args, array('search' => ''));
    $sql = "SELECT * FROM {$t}";
    if ($args['search'] !== '') {
        $sql = $wpdb->prepare($sql . " WHERE numero LIKE %s", '%' . $wpdb->esc_like($args['search']) . '%');
    }
    return $wpdb->get_results($sql . " ORDER BY id DESC");
}

function douane_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . douanes_table() . " WHERE id = %d", (int)$id));
}

function douane_num($v) {
    return round(max(0, (float)str_replace(array(' ', ','), array('', '.'), (string)$v)), 2);
}

/* ============================================================
 *  ENREGISTREMENT
 * ============================================================ */
add_action('admin_post_douane_save', 'douane_save');
function douane_save() {
    acces_guard(acces_can_edit('douane'));
    check_admin_referer('douane_save');

    global $wpdb;
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $client_id   = (int)($_POST['client_id'] ?? 0);
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    $back        = admin_url('admin.php?page=douane&tab=edit' . ($id ? '&id=' . $id : '') . '&commande_id=' . $commande_id);

    $cmd = $commande_id ? commande_get($commande_id) : null;
    if (!$client_id || !$cmd) { wp_safe_redirect($back . '&iac_msg=derr_commande'); exit; }
    if ((int)$cmd->client_id !== $client_id) { wp_safe_redirect($back . '&iac_msg=derr_client'); exit; }

    $date = sanitize_text_field($_POST['date_douane'] ?? '');
    if ($date === '') $date = current_time('Y-m-d');

    $data = array(
        'client_id'   => $client_id,
        'commande_id' => $commande_id,
        'date_douane' => $date,
        'notes'       => sanitize_textarea_field($_POST['notes'] ?? ''),
        'updated_at'  => current_time('mysql'),
    );
    foreach (array_keys(douane_lignes()) as $k) $data[$k] = douane_num($_POST[$k] ?? 0);

    if ($id > 0) {
        $wpdb->update(douanes_table(), $data, array('id' => $id));
    } else {
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $wpdb->insert(douanes_table(), $data);
        $id = (int)$wpdb->insert_id;
        $wpdb->update(douanes_table(), array('numero' => 'BD-' . substr($date, 0, 4) . '-' . str_pad($id, 4, '0', STR_PAD_LEFT)), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=douane&view=' . $id . '&iac_msg=dsaved'));
    exit;
}

add_action('admin_post_douane_delete', 'douane_delete');
function douane_delete() {
    acces_guard(acces_can_edit('douane'));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('douane_delete_' . $id);
    if ($id > 0) {
        global $wpdb;
        $wpdb->delete(douanes_table(), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=douane&iac_msg=ddeleted'));
    exit;
}

/* ============================================================
 *  SECTION DOUANE
 * ============================================================ */
function douane_page_section() {
    acces_guard(acces_can_view('douane'));
    if (isset($_GET['view']) && (int)$_GET['view'] > 0) { douane_page_bon(); return; }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit'), true)) $tab = 'list';
    iac_section_tabs('douane', $tab);
    if ($tab === 'edit') { acces_guard(acces_can_edit('douane')); douane_page_edit(); }
    else                 douane_page_list();
}

function douane_notice() {
    if (!isset($_GET['iac_msg'])) return;
    $m = array(
        'dsaved'        => array('success', 'Bon de douane enregistré.'),
        'ddeleted'      => array('success', 'Bon de douane supprimé.'),
        'derr_commande' => array('error', 'Veuillez choisir un client et une de ses commandes.'),
        'derr_client'   => array('error', 'La commande choisie n\'appartient pas à ce client.'),
    );
    $k = sanitize_key($_GET['iac_msg']);
    if (isset($m[$k])) echo '<div class="notice notice-' . $m[$k][0] . ' is-dismissible"><p>' . esc_html($m[$k][1]) . '</p></div>';
}

/* ============================================================
 *  PAGE : Liste
 * ============================================================ */
function douane_page_list() {
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $rows   = douanes_get_all(array('search' => $search));
    $can_edit = acces_can_edit('douane');

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Douane</h1><div>';
    if ($can_edit) echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=douane&tab=edit')) . '">+ Nouveau bon de douane</a>';
    echo '</div></div>';
    douane_notice();

    echo '<form method="get" style="margin-bottom:16px"><input type="hidden" name="page" value="douane">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher (n° bon de douane)" style="width:300px">';
    echo ' <button class="button">Rechercher</button></form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:130px">N°</th><th>Client</th><th>Commande</th><th>Total</th><th>Date</th><th>Créé par</th><th style="width:230px">Actions</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="7">Aucun bon de douane.' . ($can_edit ? ' <a href="' . esc_url(admin_url('admin.php?page=douane&tab=edit')) . '">Créez-en un</a>.' : '') . '</td></tr>';
    } else {
        foreach ($rows as $d) {
            $client = '—'; if ($d->client_id && function_exists('iac_get_client')) { $cl = iac_get_client($d->client_id); if ($cl) $client = iac_client_name($cl); }
            $cmd  = $d->commande_id ? commande_get($d->commande_id) : null;
            $view = admin_url('admin.php?page=douane&view=' . $d->id);
            $edit = admin_url('admin.php?page=douane&tab=edit&id=' . $d->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=douane_delete&id=' . $d->id), 'douane_delete_' . $d->id);
            echo '<tr>';
            echo '<td><strong>' . esc_html($d->numero) . '</strong></td>';
            echo '<td>' . esc_html($client) . '</td>';
            echo '<td>' . esc_html($cmd ? $cmd->numero : '—') . '</td>';
            echo '<td>' . esc_html(commande_money(douane_total($d))) . '</td>';
            echo '<td>' . esc_html(($d->date_douane && $d->date_douane !== '0000-00-00') ? date_i18n('j/m/Y', strtotime($d->date_douane)) : '') . '</td>';
            echo '<td style="font-size:12px;color:#555">' . esc_html(meta_created_text($d->created_by ?? 0, $d->created_at ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($view) . '">📄 Bon de douane</a>';
            if ($can_edit) echo ' | <a href="' . esc_url($edit) . '">Modifier</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer ce bon de douane ?\')" style="color:#b23b3b">Suppr.</a>';
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Créer / modifier un bon de douane
 * ============================================================ */
function douane_page_edit() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $d  = $id ? douane_get($id) : null;
    $cur_commande = $d ? (int)$d->commande_id : (isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0);
    $pre_cmd      = $cur_commande ? commande_get($cur_commande) : null;
    $cur_client   = $d ? (int)$d->client_id : ($pre_cmd ? (int)$pre_cmd->client_id : 0);
    $date         = ($d && $d->date_douane && $d->date_douane !== '0000-00-00') ? $d->date_douane : current_time('Y-m-d');
    $val = function($k) use ($d) {
        if ($d) return $d->$k;
        return in_array($k, array('taxe_certification', 'taxe_tel', 'taxe_modele'), true) ? DOUANE_TAXE_DEFAUT : '0';
    };

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier le bon de douane' : 'Nouveau bon de douane') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=douane')) . '">← Retour à la liste</a></div>';
    douane_notice();

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('douane_save');
    echo '<input type="hidden" name="action" value="douane_save">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    echo '<div class="row">';
    echo '<div class="fld"><label>Client</label><select id="dou_client" name="client_id" required>';
    echo '<option value="">— Choisir un client —</option>';
    if (function_exists('iac_get_clients')) {
        foreach (iac_get_clients(array('active' => 1, 'orderby' => 'nom', 'order' => 'ASC')) as $cc) {
            echo '<option value="' . (int)$cc->id . '" ' . selected($cur_client, (int)$cc->id, false) . '>' . esc_html(iac_client_name($cc)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '<div class="fld"><label>Commande</label><select id="dou_commande" name="commande_id" required>';
    echo '<option value="">— Choisir une commande —</option>';
    foreach (commandes_get_all() as $cmd) {
        $lbl = $cmd->numero;
        if ($cmd->vehicule_id && function_exists('ia_get_vehicle')) { $vv = ia_get_vehicle($cmd->vehicule_id); if ($vv) $lbl .= ' — ' . ia_vehicle_title($vv); }
        echo '<option value="' . (int)$cmd->id . '" data-client="' . (int)$cmd->client_id . '" ' . selected($cur_commande, (int)$cmd->id, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';

    echo '<h2 style="font-size:16px;margin:6px 0 12px;border-top:1px solid #eee;padding-top:16px">Montants (DA)</h2>';
    $keys = array_keys(douane_lignes());
    foreach (array_chunk($keys, 2) as $pair) {
        echo '<div class="row">';
        foreach ($pair as $k) {
            echo '<div class="fld"><label>' . esc_html(douane_lignes()[$k]) . '</label><input type="number" step="0.01" min="0" class="dou-m" name="' . esc_attr($k) . '" value="' . esc_attr($val($k)) . '"></div>';
        }
        echo '</div>';
    }
    echo '<p style="margin:-6px 0 16px;padding:10px 14px;background:#f7f8fa;border-radius:8px;color:#555">Total à payer : <strong id="dou_total">—</strong></p>';

    echo '<div class="row">';
    echo '<div class="fld"><label>Date</label><input type="date" name="date_douane" value="' . esc_attr($date) . '"></div>';
    echo '</div>';
    echo '<div class="fld"><label>Notes</label><textarea name="notes" rows="3">' . esc_textarea($d ? $d->notes : '') . '</textarea></div>';
    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer et voir le bon' : 'Créer le bon de douane') . '</button></p>';
    echo '</form></div>';
    ?>
    <script>
    jQuery(function($){
      var $cl = $('#dou_client'), $cmd = $('#dou_commande');
      function fmt(n){ return n.toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' DA'; }
      function total(){ var t = 0; $('.dou-m').each(function(){ t += parseFloat($(this).val()) || 0; }); $('#dou_total').text(fmt(t)); }
      function filterCommandes(){
        var c = $cl.val();
        $cmd.find('option').each(function(){
          var show = !$(this).val() || !c || String($(this).data('client')) === String(c);
          $(this).prop('hidden', !show).prop('disabled', !show);
        });
        var sel = $cmd.find('option:selected');
        if (sel.val() && sel.prop('disabled')) $cmd.val('');
      }
      $cl.on('change', filterCommandes);
      $cmd.on('change', function(){
        var oc = $cmd.find('option:selected').data('client');
        if (oc) $cl.val(String(oc));
        filterCommandes();
      });
      $('.dou-m').on('input', total);
      filterCommandes(); total();
    });
    </script>
    <?php
}

/* ============================================================
 *  PAGE : Bon de douane imprimable
 * ============================================================ */
function douane_page_bon() {
    $id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $d  = $id ? douane_get($id) : null;
    iac_admin_style();

    $c = $d ? commande_get($d->commande_id) : null;
    if (!$d || !$c) {
        echo '<div class="wrap"><h1>Bon de douane</h1><p>Bon de douane ou commande introuvable.</p>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=douane')) . '">← Retour</a></div>';
        return;
    }

    $client = $d->client_id && function_exists('iac_get_client') ? iac_get_client($d->client_id) : null;
    $veh    = $c->vehicule_id && function_exists('ia_get_vehicle') ? ia_get_vehicle($c->vehicule_id) : null;
    $logo   = function_exists('ia_img') ? ia_img('Logo_intermediate_auto_black.jpeg') : '';
    $date   = ($d->date_douane && $d->date_douane !== '0000-00-00') ? date_i18n('j F Y', strtotime($d->date_douane)) : '';
    $soc_addr  = defined('IA_ADDRESS') ? IA_ADDRESS : '';
    $soc_phone = defined('IA_PHONE')   ? IA_PHONE   : '';
    $soc_email = defined('IA_EMAIL')   ? IA_EMAIL   : '';

    echo '<div class="wrap no-print" style="margin-bottom:14px">';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=douane')) . '">← Retour à la liste</a> ';
    if (acces_can_edit('douane')) echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=douane&tab=edit&id=' . $d->id)) . '">✎ Modifier</a> ';
    echo '<button class="iac-btn" onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>';
    echo '</div>';
    douane_notice();
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
            <h2>BON DE DOUANE</h2>
            <div class="num"><?php echo esc_html($d->numero); ?></div>
            <?php if ($date): ?><div style="margin-top:8px;color:#666">Date : <?php echo esc_html($date); ?></div><?php endif; ?>
            <div style="margin-top:4px;color:#666">Commande <?php echo esc_html($c->numero); ?></div>
        </div>

        <div class="bon-cols">
            <div class="bon-box">
                <h3>Client</h3>
                <?php
                if ($client) {
                    echo '<div class="l"><span>Nom</span><b>' . esc_html(iac_client_name($client)) . '</b></div>';
                    if ($client->telephone) echo '<div class="l"><span>Téléphone</span><span>' . esc_html($client->telephone) . '</span></div>';
                    if ($client->adresse || $client->wilaya) echo '<div class="l"><span>Adresse</span><span>' . esc_html(trim($client->adresse . ' ' . $client->wilaya)) . '</span></div>';
                } else { echo '<p>—</p>'; }
                ?>
            </div>
            <div class="bon-box">
                <h3>Véhicule</h3>
                <?php
                if ($veh) echo '<div class="l"><span>Modèle</span><b>' . esc_html(ia_vehicle_title($veh)) . '</b></div>';
                else      echo '<div class="l"><span>Véhicule</span><span>—</span></div>';
                if ($c->couleur)        echo '<div class="l"><span>Couleur</span><span>' . esc_html($c->couleur) . '</span></div>';
                if ($c->numero_chassis) echo '<div class="l"><span>N° de châssis</span><b>' . esc_html($c->numero_chassis) . '</b></div>';
                ?>
            </div>
        </div>

        <table class="bon-amounts">
            <?php foreach (douane_lignes() as $k => $lbl): ?>
            <tr><td class="lbl"><?php echo esc_html($lbl); ?></td><td><?php echo esc_html(commande_money($d->$k)); ?></td></tr>
            <?php endforeach; ?>
            <tr><td class="lbl reste">Total à payer</td><td class="reste"><?php echo esc_html(commande_money(douane_total($d))); ?></td></tr>
        </table>

        <?php if ($d->notes): ?>
        <div class="bon-cond"><strong>Remarques :</strong><br><?php echo nl2br(esc_html($d->notes)); ?></div>
        <?php endif; ?>

        <div class="bon-sign">
            <div><div class="line">Signature du client</div></div>
            <div><div class="line">Signature et cachet — <?php echo esc_html(SOCIETE_NOM); ?></div></div>
        </div>

        <div class="bon-foot">Établi par <?php echo esc_html(acces_user_name($d->created_by ?? 0)); ?> · <?php echo esc_html(SOCIETE_NOM); ?> · Bon de douane <?php echo esc_html($d->numero); ?> · Commande <?php echo esc_html($c->numero); ?></div>
    </div>
    <?php
}
