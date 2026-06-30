<?php
/**
 * Gestion des commandes de véhicules.
 * Table wp_commandes : liste, ajout/édition, et génération d'un bon de commande
 * imprimable (logo, informations complètes, signatures client + entreprise).
 */
if (!defined('ABSPATH')) exit;

define('COMMANDES_VER', '1.0');

/** Coordonnées légales de la société (modifiables ici si besoin) */
if (!defined('SOCIETE_NOM'))     define('SOCIETE_NOM', 'Intermediate Auto');
if (!defined('SOCIETE_NIF'))     define('SOCIETE_NIF', '');
if (!defined('SOCIETE_RC'))      define('SOCIETE_RC', '');
if (!defined('SOCIETE_ART'))     define('SOCIETE_ART', '');

/** Nom complet de la table des commandes */
function commandes_table() {
    global $wpdb;
    return $wpdb->prefix . 'commandes';
}

function commande_statuts() { return array('En cours', 'Confirmée', 'Livrée', 'Annulée'); }
function commande_modes()   { return array('Espèces', 'Chèque', 'Virement', 'Versement bancaire', 'Crédit', 'Autre'); }

/** Formate un montant en DA */
function commande_money($n) {
    return number_format((float)$n, 2, ',', ' ') . ' DA';
}

/* ============================================================
 *  INSTALLATION DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'commandes_maybe_install');
function commandes_maybe_install() {
    if (get_option('commandes_db_ver') === COMMANDES_VER) return;
    global $wpdb;
    $table   = commandes_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        numero VARCHAR(40) NOT NULL DEFAULT '',
        client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        vehicule_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        date_commande DATE NULL DEFAULT NULL,
        couleur VARCHAR(120) NOT NULL DEFAULT '',
        prix DECIMAL(14,2) NOT NULL DEFAULT 0,
        avance DECIMAL(14,2) NOT NULL DEFAULT 0,
        mode_paiement VARCHAR(40) NOT NULL DEFAULT '',
        delai_livraison VARCHAR(120) NOT NULL DEFAULT '',
        statut VARCHAR(30) NOT NULL DEFAULT 'En cours',
        conditions TEXT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY client_id (client_id),
        KEY statut (statut)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('commandes_db_ver', COMMANDES_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
function commandes_get_all($args = array()) {
    global $wpdb;
    $t = commandes_table();
    $args = wp_parse_args($args, array('statut' => '', 'search' => '', 'orderby' => 'id', 'order' => 'DESC'));

    $where = array('1=1'); $params = array();
    if ($args['statut'] !== '') { $where[] = 'statut = %s'; $params[] = $args['statut']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(numero LIKE %s OR couleur LIKE %s)';
        array_push($params, $like, $like);
    }
    $allowed = array('id', 'date_commande', 'prix');
    $orderby = in_array($args['orderby'], $allowed, true) ? $args['orderby'] : 'id';
    $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} {$order}";
    if ($params) $sql = $wpdb->prepare($sql, $params);
    return $wpdb->get_results($sql);
}

function commande_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . commandes_table() . " WHERE id = %d", (int)$id));
}

/** Avance effective : somme des avances encaissées liées, sinon le champ saisi à la main */
function commande_avance_effective($c) {
    if (function_exists('avances_sum_for_commande')) {
        $s = avances_sum_for_commande($c->id);
        if ($s > 0) return $s;
    }
    return (float)$c->avance;
}

/** Reste à payer d'une commande */
function commande_reste($c) {
    return max(0, (float)$c->prix - commande_avance_effective($c));
}

/* ============================================================
 *  ENREGISTREMENT (création / édition)
 * ============================================================ */
add_action('admin_post_commande_save', 'commande_save');
function commande_save() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    check_admin_referer('commande_save');

    global $wpdb;
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $date = sanitize_text_field($_POST['date_commande'] ?? '');
    if ($date === '') $date = null;
    $prix   = (float)str_replace(array(' ', ','), array('', '.'), $_POST['prix'] ?? '0');
    $avance = (float)str_replace(array(' ', ','), array('', '.'), $_POST['avance'] ?? '0');

    $data = array(
        'client_id'       => (int)($_POST['client_id'] ?? 0),
        'vehicule_id'     => (int)($_POST['vehicule_id'] ?? 0),
        'date_commande'   => $date,
        'couleur'         => sanitize_text_field($_POST['couleur'] ?? ''),
        'prix'            => round($prix, 2),
        'avance'          => round($avance, 2),
        'mode_paiement'   => sanitize_text_field($_POST['mode_paiement'] ?? ''),
        'delai_livraison' => sanitize_text_field($_POST['delai_livraison'] ?? ''),
        'statut'          => sanitize_text_field($_POST['statut'] ?? 'En cours'),
        'conditions'      => sanitize_textarea_field($_POST['conditions'] ?? ''),
        'notes'           => sanitize_textarea_field($_POST['notes'] ?? ''),
        'updated_at'      => current_time('mysql'),
    );

    if ($id > 0) {
        $wpdb->update(commandes_table(), $data, array('id' => $id));
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert(commandes_table(), $data);
        $id = (int)$wpdb->insert_id;
        // Numéro séquentiel BC-AAAA-0001
        $year   = $date ? substr($date, 0, 4) : current_time('Y');
        $numero = 'BC-' . $year . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
        $wpdb->update(commandes_table(), array('numero' => $numero), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=commandes&view=' . $id . '&iac_msg=csaved'));
    exit;
}

/* ---------- Suppression ---------- */
add_action('admin_post_commande_delete', 'commande_delete');
function commande_delete() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('commande_delete_' . $id);
    if ($id > 0) {
        global $wpdb;
        $wpdb->delete(commandes_table(), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=commandes&iac_msg=cdeleted'));
    exit;
}

/* ============================================================
 *  SECTION COMMANDES
 * ============================================================ */
function commandes_page_section() {
    if (isset($_GET['view']) && (int)$_GET['view'] > 0) {
        commande_page_bon();
        return;
    }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit'), true)) $tab = 'list';
    iac_section_tabs('commandes', $tab);
    if ($tab === 'edit') commande_page_edit();
    else                 commandes_page_list();
}

/* ============================================================
 *  PAGE : Liste des commandes
 * ============================================================ */
function commandes_page_list() {
    $statut   = isset($_GET['statut']) ? sanitize_text_field(wp_unslash($_GET['statut'])) : '';
    $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $commandes = commandes_get_all(array('statut' => $statut, 'search' => $search));

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Gestion des commandes</h1>';
    echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=commandes&tab=edit')) . '">+ Nouvelle commande</a></div>';

    if (isset($_GET['iac_msg'])) {
        $m = array('csaved' => 'Commande enregistrée.', 'cdeleted' => 'Commande supprimée.');
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    // Filtres + recherche
    $base = admin_url('admin.php?page=commandes');
    echo '<p style="margin:0 0 12px">';
    $tabs = array('' => 'Toutes') + array_combine(commande_statuts(), commande_statuts());
    foreach ($tabs as $key => $lbl) {
        $url = $key === '' ? $base : add_query_arg('statut', $key, $base);
        $style = ($statut === $key) ? 'font-weight:700;text-decoration:none' : 'text-decoration:none';
        echo '<a href="' . esc_url($url) . '" style="' . $style . ';margin-right:14px">' . esc_html($lbl) . '</a>';
    }
    echo '</p>';
    echo '<form method="get" style="margin-bottom:16px"><input type="hidden" name="page" value="commandes">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher (n° commande, couleur…)" style="width:300px">';
    echo ' <button class="button">Rechercher</button></form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:130px">N°</th><th>Client</th><th>Véhicule</th><th>Prix</th><th>Reste</th><th>Statut</th><th style="width:230px">Actions</th></tr></thead><tbody>';

    if (!$commandes) {
        echo '<tr><td colspan="7">Aucune commande. <a href="' . esc_url(admin_url('admin.php?page=commandes&tab=edit')) . '">Créez-en une</a>.</td></tr>';
    } else {
        foreach ($commandes as $c) {
            $bon  = admin_url('admin.php?page=commandes&view=' . $c->id);
            $edit = admin_url('admin.php?page=commandes&tab=edit&id=' . $c->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=commande_delete&id=' . $c->id), 'commande_delete_' . $c->id);
            $pill = ($c->statut === 'Confirmée' || $c->statut === 'Livrée') ? 'ok' : ($c->statut === 'Annulée' ? 'sold' : 'cmd');
            $client = '—'; if ($c->client_id && function_exists('iac_get_client')) { $cl = iac_get_client($c->client_id); if ($cl) $client = iac_client_name($cl); }
            $veh = ''; if ($c->vehicule_id && function_exists('ia_get_vehicle')) { $vv = ia_get_vehicle($c->vehicule_id); if ($vv) $veh = ia_vehicle_title($vv); }
            echo '<tr>';
            echo '<td><strong>' . esc_html($c->numero) . '</strong></td>';
            echo '<td>' . esc_html($client) . '</td>';
            echo '<td>' . esc_html($veh) . '</td>';
            echo '<td>' . esc_html(commande_money($c->prix)) . '</td>';
            echo '<td>' . esc_html(commande_money(commande_reste($c))) . '</td>';
            echo '<td><span class="iac-pill ' . $pill . '">' . esc_html($c->statut) . '</span></td>';
            echo '<td><a href="' . esc_url($bon) . '">📄 Bon de commande</a> | <a href="' . esc_url($edit) . '">Modifier</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer cette commande ?\')" style="color:#b23b3b">Suppr.</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Ajouter / Modifier une commande
 * ============================================================ */
function commande_page_edit() {
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $c   = $id ? commande_get($id) : null;
    $get = function($k, $d = '') use ($c) { return $c && isset($c->$k) ? $c->$k : $d; };
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier la commande' : 'Nouvelle commande') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=commandes')) . '">← Retour à la liste</a></div>';

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('commande_save');
    echo '<input type="hidden" name="action" value="commande_save">';
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
    echo '<div class="fld"><label>Véhicule</label><select name="vehicule_id" required>';
    echo '<option value="">— Choisir un véhicule —</option>';
    if (function_exists('ia_get_vehicles')) {
        foreach (ia_get_vehicles(array('orderby' => 'marque', 'order' => 'ASC')) as $vv) {
            echo '<option value="' . (int)$vv->id . '" ' . selected((int)$get('vehicule_id', 0), (int)$vv->id, false) . '>' . esc_html(ia_vehicle_title($vv)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '</div>';

    // Couleur + date
    echo '<div class="row">';
    echo '<div class="fld"><label>Couleur choisie</label><input type="text" name="couleur" value="' . esc_attr($get('couleur')) . '"></div>';
    echo '<div class="fld"><label>Date de commande</label><input type="date" name="date_commande" value="' . esc_attr(($get('date_commande') && $get('date_commande') !== '0000-00-00') ? $get('date_commande') : current_time('Y-m-d')) . '"></div>';
    echo '</div>';

    // Prix + avance
    echo '<div class="row">';
    echo '<div class="fld"><label>Prix total (DA)</label><input type="number" step="0.01" min="0" name="prix" value="' . esc_attr($get('prix', '')) . '" required></div>';
    echo '<div class="fld"><label>Avance versée (DA) <span style="font-weight:400;color:#999">— si aucune avance liée</span></label><input type="number" step="0.01" min="0" name="avance" value="' . esc_attr($get('avance', '0')) . '"></div>';
    echo '</div>';

    // Avances liées (module Avances)
    if ($id && function_exists('avances_sum_for_commande')) {
        $linkedsum = avances_sum_for_commande($id);
        $addurl    = admin_url('admin.php?page=avances&tab=edit&commande_id=' . $id);
        echo '<p style="margin:-4px 0 16px;padding:10px 14px;background:#f7f8fa;border-radius:8px;color:#555">';
        echo 'Avances encaissées liées à cette commande : <strong>' . esc_html(commande_money($linkedsum)) . '</strong>';
        echo ' &nbsp;·&nbsp; <a href="' . esc_url($addurl) . '">+ Ajouter une avance pour cette commande</a>';
        echo '<br><span style="font-size:12px;color:#888">Si des avances sont liées, elles remplacent automatiquement le champ « Avance versée » ci-dessus dans le bon de commande.</span></p>';
    }

    // Mode + délai + statut
    echo '<div class="row">';
    echo '<div class="fld"><label>Mode de paiement</label><select name="mode_paiement">';
    echo '<option value="">— Choisir —</option>';
    foreach (commande_modes() as $mode) echo '<option ' . selected($get('mode_paiement'), $mode, false) . '>' . esc_html($mode) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Délai de livraison</label><input type="text" name="delai_livraison" value="' . esc_attr($get('delai_livraison')) . '" placeholder="Ex : 30 jours"></div>';
    echo '<div class="fld"><label>Statut</label><select name="statut">';
    foreach (commande_statuts() as $s) echo '<option ' . selected($get('statut', 'En cours'), $s, false) . '>' . esc_html($s) . '</option>';
    echo '</select></div>';
    echo '</div>';

    echo '<div class="fld"><label>Conditions particulières</label><textarea name="conditions" rows="3" placeholder="Conditions de vente, garanties, clauses…">' . esc_textarea($get('conditions')) . '</textarea></div>';
    echo '<div class="fld"><label>Notes internes</label><textarea name="notes" rows="2">' . esc_textarea($get('notes')) . '</textarea></div>';

    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer et voir le bon' : 'Créer la commande') . '</button></p>';
    echo '</form></div>';
}

/* ============================================================
 *  PAGE : Bon de commande imprimable
 * ============================================================ */
function commande_page_bon() {
    $id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $c  = $id ? commande_get($id) : null;
    iac_admin_style();

    if (!$c) {
        echo '<div class="wrap"><h1>Bon de commande</h1><p>Commande introuvable.</p>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=commandes')) . '">← Retour</a></div>';
        return;
    }

    $client = $c->client_id && function_exists('iac_get_client') ? iac_get_client($c->client_id) : null;
    $veh    = $c->vehicule_id && function_exists('ia_get_vehicle') ? ia_get_vehicle($c->vehicule_id) : null;
    $logo   = function_exists('ia_img') ? ia_img('Logo_intermediate_auto_black.jpeg') : '';
    $date   = ($c->date_commande && $c->date_commande !== '0000-00-00') ? date_i18n('j F Y', strtotime($c->date_commande)) : '';
    $reste  = commande_reste($c);

    // Coordonnées société (depuis le thème si dispo)
    $soc_addr  = defined('IA_ADDRESS') ? IA_ADDRESS : '';
    $soc_phone = defined('IA_PHONE')   ? IA_PHONE   : '';
    $soc_email = defined('IA_EMAIL')   ? IA_EMAIL   : '';

    // Barre d'outils (non imprimée)
    echo '<div class="wrap no-print" style="margin-bottom:14px">';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=commandes')) . '">← Retour à la liste</a> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=commandes&tab=edit&id=' . $c->id)) . '">✎ Modifier</a> ';
    echo '<button class="iac-btn" onclick="window.print()">🖨 Imprimer / Enregistrer en PDF</button>';
    echo '</div>';

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
        #adminmenumain,#wpadminbar,#wpfooter,#screen-meta,#screen-meta-links,.no-print,.update-nag{display:none!important}
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
            <h2>BON DE COMMANDE</h2>
            <div class="num"><?php echo esc_html($c->numero); ?></div>
            <?php if ($date): ?><div style="margin-top:8px;color:#666">Date : <?php echo esc_html($date); ?></div><?php endif; ?>
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
                if ($c->couleur)         echo '<div class="l"><span>Couleur</span><span>' . esc_html($c->couleur) . '</span></div>';
                if ($c->delai_livraison) echo '<div class="l"><span>Délai</span><span>' . esc_html($c->delai_livraison) . '</span></div>';
                ?>
            </div>
        </div>

        <?php
        $avance_eff = commande_avance_effective($c);
        $linked = function_exists('avances_for_commande') ? avances_for_commande($c->id) : array();
        ?>
        <table class="bon-amounts">
            <tr><td class="lbl">Prix total du véhicule</td><td><?php echo esc_html(commande_money($c->prix)); ?></td></tr>
            <?php if ($linked): ?>
                <?php foreach ($linked as $av): ?>
                <tr><td class="lbl" style="font-weight:400">Avance<?php
                    if ($av->date_avance && $av->date_avance !== '0000-00-00') echo ' du ' . esc_html(date_i18n('j/m/Y', strtotime($av->date_avance)));
                    if ($av->mode_paiement) echo ' · ' . esc_html($av->mode_paiement);
                    if ($av->statut !== 'Encaissée') echo ' (' . esc_html($av->statut) . ')';
                ?></td><td><?php echo esc_html(commande_money($av->montant)); ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="lbl">Total avances encaissées</td><td><?php echo esc_html(commande_money($avance_eff)); ?></td></tr>
            <?php else: ?>
                <tr><td class="lbl">Avance versée<?php if ($c->mode_paiement) echo ' (' . esc_html($c->mode_paiement) . ')'; ?></td><td><?php echo esc_html(commande_money($c->avance)); ?></td></tr>
            <?php endif; ?>
            <tr><td class="lbl reste">Reste à payer</td><td class="reste"><?php echo esc_html(commande_money($reste)); ?></td></tr>
        </table>

        <?php if ($c->conditions): ?>
        <div class="bon-cond"><strong>Conditions :</strong><br><?php echo nl2br(esc_html($c->conditions)); ?></div>
        <?php endif; ?>

        <div class="bon-sign">
            <div><div class="line">Signature du client</div></div>
            <div><div class="line">Signature et cachet — <?php echo esc_html(SOCIETE_NOM); ?></div></div>
        </div>

        <div class="bon-foot"><?php echo esc_html(SOCIETE_NOM); ?> · Bon de commande <?php echo esc_html($c->numero); ?></div>
    </div>
    <?php
}
