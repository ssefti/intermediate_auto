<?php
/**
 * Intermediate Auto Core — Gestion des clients (particuliers & entreprises)
 * Table propre wp_ia_clients : liste, fiche, création/édition, pièces jointes,
 * activation/désactivation.
 */
if (!defined('ABSPATH')) exit;

define('IAC_CLIENTS_VER', '1.0');

/** Nom complet de la table clients */
function iac_clients_table() {
    global $wpdb;
    return $wpdb->prefix . 'ia_clients';
}

/* ---------- Listes de référence ---------- */
function iac_civilites()      { return array('M.', 'Mme', 'Mlle'); }
function iac_piece_types()    { return array("Carte d'identité (CNI)", 'Permis de conduire', 'Passeport', 'Carte de résidence'); }
function iac_client_statuts() { return array('Prospect', 'Acheteur', 'Ancien client'); }
function iac_wilayas() {
    return array(
        'Adrar','Chlef','Laghouat','Oum El Bouaghi','Batna','Béjaïa','Biskra','Béchar','Blida','Bouira',
        'Tamanrasset','Tébessa','Tlemcen','Tiaret','Tizi Ouzou','Alger','Djelfa','Jijel','Sétif','Saïda',
        'Skikda','Sidi Bel Abbès','Annaba','Guelma','Constantine','Médéa','Mostaganem','MSila','Mascara','Ouargla',
        'Oran','El Bayadh','Illizi','Bordj Bou Arréridj','Boumerdès','El Tarf','Tindouf','Tissemsilt','El Oued','Khenchela',
        'Souk Ahras','Tipaza','Mila','Aïn Defla','Naâma','Aïn Témouchent','Ghardaïa','Relizane','Timimoun','Bordj Badji Mokhtar',
        'Ouled Djellal','Béni Abbès','In Salah','In Guezzam','Touggourt','Djanet','El MGhair','El Meniaa',
    );
}

/* ============================================================
 *  INSTALLATION / MISE À NIVEAU DE LA TABLE
 * ============================================================ */
add_action('plugins_loaded', 'iac_clients_maybe_install');
function iac_clients_maybe_install() {
    if (get_option('iac_clients_db_ver') === IAC_CLIENTS_VER) return;
    global $wpdb;
    $table   = iac_clients_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL DEFAULT 'particulier',
        civilite VARCHAR(10) NOT NULL DEFAULT '',
        nom VARCHAR(120) NOT NULL DEFAULT '',
        prenom VARCHAR(120) NOT NULL DEFAULT '',
        date_naissance DATE NULL DEFAULT NULL,
        piece_type VARCHAR(40) NOT NULL DEFAULT '',
        piece_numero VARCHAR(80) NOT NULL DEFAULT '',
        raison_sociale VARCHAR(200) NOT NULL DEFAULT '',
        nif VARCHAR(60) NOT NULL DEFAULT '',
        nis VARCHAR(60) NOT NULL DEFAULT '',
        rc VARCHAR(60) NOT NULL DEFAULT '',
        art VARCHAR(60) NOT NULL DEFAULT '',
        contact_nom VARCHAR(160) NOT NULL DEFAULT '',
        telephone VARCHAR(40) NOT NULL DEFAULT '',
        telephone2 VARCHAR(40) NOT NULL DEFAULT '',
        email VARCHAR(160) NOT NULL DEFAULT '',
        adresse VARCHAR(255) NOT NULL DEFAULT '',
        ville VARCHAR(120) NOT NULL DEFAULT '',
        wilaya VARCHAR(80) NOT NULL DEFAULT '',
        vehicule_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        statut_client VARCHAR(40) NOT NULL DEFAULT 'Prospect',
        notes TEXT NULL,
        attachments TEXT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY type (type),
        KEY active (active),
        KEY statut_client (statut_client)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('iac_clients_db_ver', IAC_CLIENTS_VER);
}

/* ============================================================
 *  ACCÈS AUX DONNÉES
 * ============================================================ */
/**
 * Récupère des clients.
 * @param array $args  active (0/1/null), type, statut, search, orderby, order
 */
function iac_get_clients($args = array()) {
    global $wpdb;
    $t = iac_clients_table();
    $args = wp_parse_args($args, array(
        'active'  => null,
        'type'    => '',
        'statut'  => '',
        'search'  => '',
        'orderby' => 'created_at',
        'order'   => 'DESC',
    ));

    $where = array('1=1'); $params = array();
    if ($args['active'] !== null) { $where[] = 'active = %d'; $params[] = (int)$args['active']; }
    if ($args['type'] !== '')     { $where[] = 'type = %s';   $params[] = $args['type']; }
    if ($args['statut'] !== '')   { $where[] = 'statut_client = %s'; $params[] = $args['statut']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(nom LIKE %s OR prenom LIKE %s OR raison_sociale LIKE %s OR telephone LIKE %s OR email LIKE %s)';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $allowed = array('created_at','nom','raison_sociale','id');
    $orderby = in_array($args['orderby'], $allowed, true) ? $args['orderby'] : 'created_at';
    $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} {$order}";
    if ($params) $sql = $wpdb->prepare($sql, $params);
    return $wpdb->get_results($sql);
}

function iac_get_client($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . iac_clients_table() . " WHERE id = %d", (int)$id));
}

/** Libellé d'affichage d'un client (raison sociale ou nom + prénom) */
function iac_client_name($c) {
    if (!$c) return '';
    if ($c->type === 'entreprise') {
        return $c->raison_sociale !== '' ? $c->raison_sociale : '(Entreprise sans nom)';
    }
    $n = trim($c->nom . ' ' . $c->prenom);
    return $n !== '' ? $n : '(Client sans nom)';
}

/** IDs des pièces jointes d'un client */
function iac_client_attachment_ids($c) {
    if (empty($c->attachments)) return array();
    return array_values(array_filter(array_map('intval', explode(',', $c->attachments))));
}

/* ============================================================
 *  ENREGISTREMENT (création / édition)
 * ============================================================ */
add_action('admin_post_iac_save_client', 'iac_save_client');
function iac_save_client() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    check_admin_referer('iac_save_client');

    global $wpdb;
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $type = (isset($_POST['type']) && $_POST['type'] === 'entreprise') ? 'entreprise' : 'particulier';

    $att_ids = array_values(array_filter(array_map('intval', explode(',', $_POST['attachments'] ?? ''))));
    $dob = sanitize_text_field($_POST['date_naissance'] ?? '');
    if ($dob === '') $dob = null;

    $data = array(
        'type'          => $type,
        'civilite'      => sanitize_text_field($_POST['civilite'] ?? ''),
        'nom'           => sanitize_text_field($_POST['nom'] ?? ''),
        'prenom'        => sanitize_text_field($_POST['prenom'] ?? ''),
        'date_naissance'=> $dob,
        'piece_type'    => sanitize_text_field($_POST['piece_type'] ?? ''),
        'piece_numero'  => sanitize_text_field($_POST['piece_numero'] ?? ''),
        'raison_sociale'=> sanitize_text_field($_POST['raison_sociale'] ?? ''),
        'nif'           => sanitize_text_field($_POST['nif'] ?? ''),
        'nis'           => sanitize_text_field($_POST['nis'] ?? ''),
        'rc'            => sanitize_text_field($_POST['rc'] ?? ''),
        'art'           => sanitize_text_field($_POST['art'] ?? ''),
        'contact_nom'   => sanitize_text_field($_POST['contact_nom'] ?? ''),
        'telephone'     => sanitize_text_field($_POST['telephone'] ?? ''),
        'telephone2'    => sanitize_text_field($_POST['telephone2'] ?? ''),
        'email'         => sanitize_email($_POST['email'] ?? ''),
        'adresse'       => sanitize_text_field($_POST['adresse'] ?? ''),
        'ville'         => sanitize_text_field($_POST['ville'] ?? ''),
        'wilaya'        => sanitize_text_field($_POST['wilaya'] ?? ''),
        'vehicule_id'   => (int)($_POST['vehicule_id'] ?? 0),
        'statut_client' => sanitize_text_field($_POST['statut_client'] ?? 'Prospect'),
        'notes'         => sanitize_textarea_field($_POST['notes'] ?? ''),
        'attachments'   => implode(',', $att_ids),
        'updated_at'    => current_time('mysql'),
    );

    if ($id > 0) {
        $wpdb->update(iac_clients_table(), $data, array('id' => $id));
        $msg = 'cupdated';
    } else {
        $data['active']     = 1;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert(iac_clients_table(), $data);
        $msg = 'ccreated';
    }
    wp_safe_redirect(admin_url('admin.php?page=ia-clients&iac_msg=' . $msg));
    exit;
}

/* ---------- Activer / Désactiver ---------- */
add_action('admin_post_iac_toggle_client', 'iac_toggle_client');
function iac_toggle_client() {
    if (!current_user_can('manage_options')) wp_die('Accès refusé');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('iac_toggle_' . $id);
    $msg = '';
    if ($id > 0) {
        global $wpdb;
        $cur = (int)$wpdb->get_var($wpdb->prepare("SELECT active FROM " . iac_clients_table() . " WHERE id = %d", $id));
        $new = $cur ? 0 : 1;
        $wpdb->update(iac_clients_table(), array('active' => $new, 'updated_at' => current_time('mysql')), array('id' => $id));
        $msg = $new ? 'cactivated' : 'cdeactivated';
    }
    wp_safe_redirect(admin_url('admin.php?page=ia-clients&iac_msg=' . $msg));
    exit;
}

/* ============================================================
 *  PAGE : Liste des clients
 * ============================================================ */
function iac_page_clients_list() {
    $f_active = '';
    if (isset($_GET['filtre'])) {
        if ($_GET['filtre'] === 'actifs')   $f_active = 1;
        if ($_GET['filtre'] === 'inactifs') $f_active = 0;
    }
    $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $clients = iac_get_clients(array(
        'active'  => ($f_active === '' ? null : $f_active),
        'search'  => $search,
        'orderby' => 'id', 'order' => 'DESC',
    ));

    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Clients</h1>';
    echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=ia-client-edit')) . '">+ Ajouter un client</a></div>';

    if (isset($_GET['iac_msg'])) {
        $m = array(
            'ccreated'     => 'Client ajouté.',
            'cupdated'     => 'Client mis à jour.',
            'cactivated'   => 'Client réactivé.',
            'cdeactivated' => 'Client désactivé.',
        );
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    // Filtres + recherche
    $base = admin_url('admin.php?page=ia-clients');
    echo '<p style="margin:0 0 12px">';
    $tabs = array(''=>'Tous','actifs'=>'Actifs','inactifs'=>'Inactifs');
    $cur_f = isset($_GET['filtre']) ? $_GET['filtre'] : '';
    foreach ($tabs as $key=>$lbl) {
        $url = $key === '' ? $base : add_query_arg('filtre', $key, $base);
        $style = ($cur_f === $key) ? 'font-weight:700;text-decoration:none' : 'text-decoration:none';
        echo '<a href="' . esc_url($url) . '" style="' . $style . ';margin-right:14px">' . esc_html($lbl) . '</a>';
    }
    echo '</p>';
    echo '<form method="get" style="margin-bottom:16px"><input type="hidden" name="page" value="ia-clients">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rechercher un client (nom, téléphone, email…)" style="width:320px">';
    echo ' <button class="button">Rechercher</button></form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Client</th><th>Type</th><th>Téléphone</th><th>Wilaya</th><th>Véhicule</th><th>Statut</th><th style="width:90px">État</th><th style="width:200px">Actions</th></tr></thead><tbody>';

    if (!$clients) {
        echo '<tr><td colspan="8">Aucun client. <a href="' . esc_url(admin_url('admin.php?page=ia-client-edit')) . '">Ajoutez-en un</a>.</td></tr>';
    } else {
        foreach ($clients as $c) {
            $view   = admin_url('admin.php?page=ia-client-view&id=' . $c->id);
            $edit   = admin_url('admin.php?page=ia-client-edit&id=' . $c->id);
            $toggle = wp_nonce_url(admin_url('admin-post.php?action=iac_toggle_client&id=' . $c->id), 'iac_toggle_' . $c->id);
            $pill   = $c->statut_client === 'Acheteur' ? 'ok' : ($c->statut_client === 'Ancien client' ? 'sold' : 'cmd');
            $veh    = '';
            if ($c->vehicule_id && function_exists('ia_get_vehicle')) {
                $vv = ia_get_vehicle($c->vehicule_id);
                if ($vv) $veh = ia_vehicle_title($vv);
            }
            $rowstyle = $c->active ? '' : ' style="opacity:.5"';
            echo '<tr' . $rowstyle . '>';
            echo '<td><strong><a href="' . esc_url($view) . '">' . esc_html(iac_client_name($c)) . '</a></strong></td>';
            echo '<td>' . ($c->type === 'entreprise' ? '🏢 Entreprise' : '👤 Particulier') . '</td>';
            echo '<td>' . esc_html($c->telephone) . '</td>';
            echo '<td>' . esc_html($c->wilaya) . '</td>';
            echo '<td>' . esc_html($veh) . '</td>';
            echo '<td><span class="iac-pill ' . $pill . '">' . esc_html($c->statut_client) . '</span></td>';
            echo '<td>' . ($c->active ? '<span class="iac-pill ok">Actif</span>' : '<span class="iac-pill sold">Inactif</span>') . '</td>';
            echo '<td><a href="' . esc_url($view) . '">Voir</a> | <a href="' . esc_url($edit) . '">Modifier</a> | ';
            if ($c->active) {
                echo '<a href="' . esc_url($toggle) . '" onclick="return confirm(\'Désactiver ce client ?\')" style="color:#b9770e">Désactiver</a>';
            } else {
                echo '<a href="' . esc_url($toggle) . '" style="color:#1a7a3c">Réactiver</a>';
            }
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
}

/* ============================================================
 *  PAGE : Fiche client (consultation)
 * ============================================================ */
function iac_page_client_view() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $c  = $id ? iac_get_client($id) : null;
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    if (!$c) {
        echo '<div class="iac-head"><h1>Fiche client</h1></div><p>Client introuvable.</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=ia-clients')) . '">← Retour à la liste</a></p></div>';
        return;
    }

    $edit   = admin_url('admin.php?page=ia-client-edit&id=' . $c->id);
    $toggle = wp_nonce_url(admin_url('admin-post.php?action=iac_toggle_client&id=' . $c->id), 'iac_toggle_' . $c->id);

    echo '<div class="iac-head"><h1>' . esc_html(iac_client_name($c)) . ' ';
    echo $c->active ? '<span class="iac-pill ok">Actif</span>' : '<span class="iac-pill sold">Inactif</span>';
    echo '</h1><div>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=ia-clients')) . '">← Retour</a> ';
    echo '<a class="iac-btn" href="' . esc_url($edit) . '">✎ Modifier</a></div></div>';

    // Helper d'affichage d'une ligne
    $row = function($label, $value) {
        $value = trim((string)$value);
        if ($value === '') return;
        echo '<div class="irow"><span>' . esc_html($label) . '</span><b>' . esc_html($value) . '</b></div>';
    };

    echo '<style>.iac-fiche{display:grid;grid-template-columns:1fr 1fr;gap:18px}.iac-fiche .iac-card{padding:20px}
    .iac-fiche h3{margin:0 0 12px;font-size:15px;color:#1a1a1a;border-bottom:1px solid #eee;padding-bottom:8px}
    .irow{display:flex;justify-content:space-between;gap:14px;padding:6px 0;border-bottom:1px dashed #f0f0f0}
    .irow span{color:#777}.irow b{text-align:right}</style>';

    echo '<div class="iac-fiche">';

    // Identité
    echo '<div class="iac-card"><h3>Identité</h3>';
    $row('Type', $c->type === 'entreprise' ? 'Entreprise' : 'Particulier');
    if ($c->type === 'entreprise') {
        $row('Raison sociale', $c->raison_sociale);
        $row('Interlocuteur', $c->contact_nom);
        $row('NIF', $c->nif);
        $row('NIS', $c->nis);
        $row('RC', $c->rc);
        $row("Article d'imposition", $c->art);
    } else {
        $row('Civilité', $c->civilite);
        $row('Nom', $c->nom);
        $row('Prénom', $c->prenom);
        $row('Date de naissance', ($c->date_naissance && $c->date_naissance !== '0000-00-00') ? $c->date_naissance : '');
        $row('Pièce', trim($c->piece_type . ' ' . $c->piece_numero));
    }
    echo '</div>';

    // Coordonnées
    echo '<div class="iac-card"><h3>Coordonnées</h3>';
    $row('Téléphone', $c->telephone);
    $row('Téléphone 2', $c->telephone2);
    $row('Email', $c->email);
    $row('Adresse', $c->adresse);
    $row('Ville', $c->ville);
    $row('Wilaya', $c->wilaya);
    echo '</div>';

    // Suivi commercial
    echo '<div class="iac-card"><h3>Suivi commercial</h3>';
    $row('Statut', $c->statut_client);
    $veh = '';
    if ($c->vehicule_id && function_exists('ia_get_vehicle')) {
        $vv = ia_get_vehicle($c->vehicule_id);
        if ($vv) $veh = ia_vehicle_title($vv);
    }
    $row('Véhicule concerné', $veh);
    $row('Créé le', $c->created_at !== '1000-01-01 00:00:00' ? $c->created_at : '');
    if ($c->notes) {
        echo '<div style="padding-top:10px"><span style="color:#777">Notes</span><p style="margin-top:6px">' . nl2br(esc_html($c->notes)) . '</p></div>';
    }
    echo '</div>';

    // Pièces jointes
    echo '<div class="iac-card"><h3>Pièces jointes</h3>';
    $atts = iac_client_attachment_ids($c);
    if (!$atts) {
        echo '<p style="color:#777">Aucune pièce jointe.</p>';
    } else {
        echo '<ul style="margin:0;list-style:none;padding:0">';
        foreach ($atts as $aid) {
            $url = wp_get_attachment_url($aid);
            if (!$url) continue;
            $name = get_the_title($aid) ?: basename($url);
            $icon = wp_attachment_is_image($aid)
                ? '<img src="' . esc_url(wp_get_attachment_image_url($aid, 'thumbnail')) . '" style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">'
                : '<span style="font-size:20px;margin-right:8px">📄</span>';
            echo '<li style="padding:7px 0;border-bottom:1px solid #f3f3f3">' . $icon . '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($name) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '</div>'; // .iac-fiche

    echo '<p style="margin-top:22px">';
    if ($c->active) {
        echo '<a href="' . esc_url($toggle) . '" class="button" onclick="return confirm(\'Désactiver ce client ?\')">Désactiver le client</a>';
    } else {
        echo '<a href="' . esc_url($toggle) . '" class="button">Réactiver le client</a>';
    }
    echo '</p>';
    echo '</div>';
}

/* ============================================================
 *  PAGE : Ajouter / Modifier un client
 * ============================================================ */
function iac_page_client_edit() {
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $c   = $id ? iac_get_client($id) : null;
    $get = function($k, $d = '') use ($c) { return $c && isset($c->$k) ? $c->$k : $d; };
    $type = $get('type', 'particulier');
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier un client' : 'Ajouter un client') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=ia-clients')) . '">← Retour à la liste</a></div>';

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('iac_save_client');
    echo '<input type="hidden" name="action" value="iac_save_client">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    // Type de client
    echo '<div class="fld"><label>Type de client</label>';
    echo '<label style="font-weight:400;margin-right:20px"><input type="radio" name="type" value="particulier" ' . checked($type, 'particulier', false) . '> 👤 Particulier</label>';
    echo '<label style="font-weight:400"><input type="radio" name="type" value="entreprise" ' . checked($type, 'entreprise', false) . '> 🏢 Entreprise</label></div>';

    /* ---- Bloc Particulier ---- */
    echo '<div class="iac-part">';
    echo '<div class="row">';
    echo '<div class="fld" style="max-width:120px"><label>Civilité</label><select name="civilite">';
    echo '<option value="">—</option>';
    foreach (iac_civilites() as $civ) echo '<option ' . selected($get('civilite'), $civ, false) . '>' . esc_html($civ) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Nom</label><input type="text" name="nom" value="' . esc_attr($get('nom')) . '"></div>';
    echo '<div class="fld"><label>Prénom</label><input type="text" name="prenom" value="' . esc_attr($get('prenom')) . '"></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Date de naissance</label><input type="date" name="date_naissance" value="' . esc_attr($get('date_naissance') && $get('date_naissance') !== '0000-00-00' ? $get('date_naissance') : '') . '"></div>';
    echo '<div class="fld"><label>Type de pièce</label><select name="piece_type">';
    echo '<option value="">— Choisir —</option>';
    foreach (iac_piece_types() as $pt) echo '<option ' . selected($get('piece_type'), $pt, false) . '>' . esc_html($pt) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>N° de pièce</label><input type="text" name="piece_numero" value="' . esc_attr($get('piece_numero')) . '"></div>';
    echo '</div>';
    echo '</div>'; // .iac-part

    /* ---- Bloc Entreprise ---- */
    echo '<div class="iac-ent">';
    echo '<div class="row">';
    echo '<div class="fld"><label>Raison sociale</label><input type="text" name="raison_sociale" value="' . esc_attr($get('raison_sociale')) . '"></div>';
    echo '<div class="fld"><label>Interlocuteur (contact)</label><input type="text" name="contact_nom" value="' . esc_attr($get('contact_nom')) . '"></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="fld"><label>NIF</label><input type="text" name="nif" value="' . esc_attr($get('nif')) . '"></div>';
    echo '<div class="fld"><label>NIS</label><input type="text" name="nis" value="' . esc_attr($get('nis')) . '"></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="fld"><label>RC (Registre du commerce)</label><input type="text" name="rc" value="' . esc_attr($get('rc')) . '"></div>';
    echo '<div class="fld"><label>Article d\'imposition</label><input type="text" name="art" value="' . esc_attr($get('art')) . '"></div>';
    echo '</div>';
    echo '</div>'; // .iac-ent

    /* ---- Coordonnées (commun) ---- */
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Coordonnées</h2>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Téléphone</label><input type="text" name="telephone" value="' . esc_attr($get('telephone')) . '"></div>';
    echo '<div class="fld"><label>Téléphone 2</label><input type="text" name="telephone2" value="' . esc_attr($get('telephone2')) . '"></div>';
    echo '<div class="fld"><label>Email</label><input type="text" name="email" value="' . esc_attr($get('email')) . '"></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Adresse</label><input type="text" name="adresse" value="' . esc_attr($get('adresse')) . '"></div>';
    echo '<div class="fld"><label>Ville / Commune</label><input type="text" name="ville" value="' . esc_attr($get('ville')) . '"></div>';
    echo '<div class="fld"><label>Wilaya</label><select name="wilaya">';
    echo '<option value="">— Choisir —</option>';
    foreach (iac_wilayas() as $w) echo '<option ' . selected($get('wilaya'), $w, false) . '>' . esc_html($w) . '</option>';
    echo '</select></div>';
    echo '</div>';

    /* ---- Suivi commercial ---- */
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Suivi commercial</h2>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Statut</label><select name="statut_client">';
    foreach (iac_client_statuts() as $s) echo '<option ' . selected($get('statut_client', 'Prospect'), $s, false) . '>' . esc_html($s) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Véhicule concerné</label><select name="vehicule_id">';
    echo '<option value="0">— Aucun —</option>';
    if (function_exists('ia_get_vehicles')) {
        foreach (ia_get_vehicles(array('orderby' => 'marque', 'order' => 'ASC')) as $vv) {
            echo '<option value="' . (int)$vv->id . '" ' . selected((int)$get('vehicule_id', 0), (int)$vv->id, false) . '>' . esc_html(ia_vehicle_title($vv)) . '</option>';
        }
    }
    echo '</select></div>';
    echo '</div>';
    echo '<div class="fld"><label>Notes</label><textarea name="notes" rows="3">' . esc_textarea($get('notes')) . '</textarea></div>';

    /* ---- Pièces jointes ---- */
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Pièces jointes</h2>';
    echo '<p style="color:#777;font-size:13px;margin:-4px 0 10px">Carte d\'identité, permis, registre du commerce, bon de commande signé… (PDF, image, etc.)</p>';
    $att_ids = $c ? iac_client_attachment_ids($c) : array();
    echo '<input type="hidden" id="iac_att_ids" name="attachments" value="' . esc_attr(implode(',', $att_ids)) . '">';
    echo '<ul id="iac_att_list" style="margin:0 0 10px;list-style:none;padding:0">';
    foreach ($att_ids as $aid) {
        $url = wp_get_attachment_url($aid);
        if (!$url) continue;
        $name = get_the_title($aid) ?: basename($url);
        $icon = wp_attachment_is_image($aid)
            ? '<img src="' . esc_url(wp_get_attachment_image_url($aid, 'thumbnail')) . '" style="width:34px;height:34px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">'
            : '<span style="font-size:18px;margin-right:8px">📄</span>';
        echo '<li data-id="' . (int)$aid . '" style="padding:6px 0;border-bottom:1px solid #f3f3f3">' . $icon
            . '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($name) . '</a> '
            . '<a href="#" class="iac-att-rm" style="color:#b23b3b;margin-left:8px">retirer</a></li>';
    }
    echo '</ul>';
    echo '<button type="button" class="button" id="iac_att_add">📎 Ajouter des pièces jointes</button>';

    echo '<p style="margin-top:22px"><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer les modifications' : 'Ajouter le client') . '</button></p>';
    echo '</form></div>';

    // JS : bascule type + médiathèque pièces jointes
    ?>
    <script>
    jQuery(function($){
      function syncType(){
        var t = $('input[name=type]:checked').val();
        $('.iac-part').toggle(t === 'particulier');
        $('.iac-ent').toggle(t === 'entreprise');
      }
      $('input[name=type]').on('change', syncType);
      syncType();

      var frame;
      $('#iac_att_add').on('click', function(e){
        e.preventDefault();
        frame = wp.media({ title:'Ajouter des pièces jointes', button:{text:'Ajouter'}, multiple:true });
        frame.on('select', function(){
          var input = $('#iac_att_ids');
          var ids = input.val() ? input.val().split(',') : [];
          frame.state().get('selection').each(function(a){
            a = a.toJSON();
            if (ids.indexOf(String(a.id)) === -1){
              ids.push(String(a.id));
              var name = a.filename || a.title || ('#' + a.id);
              var icon = (a.type === 'image' && a.sizes && a.sizes.thumbnail)
                ? '<img src="'+a.sizes.thumbnail.url+'" style="width:34px;height:34px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px">'
                : '<span style="font-size:18px;margin-right:8px">📄</span>';
              $('#iac_att_list').append('<li data-id="'+a.id+'" style="padding:6px 0;border-bottom:1px solid #f3f3f3">'+icon+'<a href="'+a.url+'" target="_blank">'+name+'</a> <a href="#" class="iac-att-rm" style="color:#b23b3b;margin-left:8px">retirer</a></li>');
            }
          });
          input.val(ids.join(','));
        });
        frame.open();
      });
      $(document).on('click', '.iac-att-rm', function(e){
        e.preventDefault();
        var li = $(this).closest('li'), id = String(li.data('id'));
        var input = $('#iac_att_ids');
        var ids = input.val() ? input.val().split(',') : [];
        input.val(ids.filter(function(x){ return x !== id; }).join(','));
        li.remove();
      });
    });
    </script>
    <?php
}
