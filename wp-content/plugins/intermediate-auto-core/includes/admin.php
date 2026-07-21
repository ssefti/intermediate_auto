<?php
/**
 * Intermediate Auto Core — Administration (dashboard + formulaire véhicules)
 */
if (!defined('ABSPATH')) exit;

/* ---------- Menu ---------- */
add_action('admin_menu', 'iac_admin_menu');
function iac_admin_menu() {
    // Aucun accès → aucun menu
    if (!acces_any()) return;
    $can_dash = acces_has('dashboard_view');

    // Menu principal « Administration » (chaque section selon les accès)
    add_menu_page('Administration', 'Administration', 'read', 'intermediate-auto', $can_dash ? 'dashboard_page' : 'acces_landing', 'dashicons-chart-area', 25);
    if ($can_dash) add_submenu_page('intermediate-auto', 'Tableau de bord', 'Tableau de bord', 'read', 'intermediate-auto', 'dashboard_page');
    if (acces_can_edit('vehicules')) add_submenu_page('intermediate-auto', 'Voitures', 'Voitures', 'read', 'vehicules', 'iac_page_vehicles_section');
    if (acces_can_view('clients'))   add_submenu_page('intermediate-auto', 'Clients', 'Clients', 'read', 'ia-clients', 'iac_page_clients_section');
    if (acces_can_view('devis'))     add_submenu_page('intermediate-auto', 'Gestion des devis', 'Gestion des devis', 'read', 'devis', 'devis_page_section');
    if (acces_can_view('commandes')) add_submenu_page('intermediate-auto', 'Gestion des commandes', 'Gestion des commandes', 'read', 'commandes', 'commandes_page_section');
    if (acces_can_view('avances'))   add_submenu_page('intermediate-auto', 'Gestion des paiements', 'Gestion des paiements', 'read', 'avances', 'avances_page_section');
}

/* ---------- Barre d'onglets d'une section ---------- */
function iac_section_tabs($section, $current) {
    if ($section === 'vehicles') {
        $base = admin_url('admin.php?page=vehicules');
        $tabs = array('list' => 'Véhicules', 'edit' => 'Ajouter', 'marques' => 'Marques', 'export' => 'Exporter');
    } elseif ($section === 'avances') {
        $base = admin_url('admin.php?page=avances');
        $tabs = array('list' => 'Paiements', 'edit' => 'Ajouter');
    } elseif ($section === 'commandes') {
        $base = admin_url('admin.php?page=commandes');
        $tabs = array('list' => 'Commandes', 'edit' => 'Nouvelle commande');
    } elseif ($section === 'devis') {
        $base = admin_url('admin.php?page=devis');
        $tabs = array('list' => 'Devis', 'edit' => 'Nouveau devis');
    } else {
        $base = admin_url('admin.php?page=ia-clients');
        $tabs = array('list' => 'Clients', 'edit' => 'Ajouter');
    }
    echo '<div class="wrap" style="margin-bottom:0;padding-bottom:0"><h2 class="nav-tab-wrapper" style="margin-bottom:0">';
    foreach ($tabs as $k => $lbl) {
        $active = ($current === $k) ? ' nav-tab-active' : '';
        echo '<a class="nav-tab' . $active . '" href="' . esc_url(add_query_arg('tab', $k, $base)) . '">' . esc_html($lbl) . '</a>';
    }
    echo '</h2></div>';
}

/* ---------- Section Voitures (onglets) ---------- */
function iac_page_vehicles_section() {
    acces_guard(acces_can_edit('vehicules'));
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'list';
    if (!in_array($tab, array('list', 'edit', 'marques', 'export'), true)) $tab = 'list';
    iac_section_tabs('vehicles', $tab);
    switch ($tab) {
        case 'edit':    iac_page_edit();    break;
        case 'marques': iac_page_marques(); break;
        case 'export':  iac_page_export();  break;
        default:        iac_page_list();    break;
    }
}

/* ---------- Ajout / suppression de marques personnalisées ---------- */
add_action('admin_post_iac_add_marque', 'iac_add_marque');
function iac_add_marque() {
    acces_guard(acces_can_edit('vehicules'));
    check_admin_referer('iac_add_marque');
    $name = sanitize_text_field($_POST['marque'] ?? '');
    if ($name !== '') iac_marque_add($name);
    wp_safe_redirect(admin_url('admin.php?page=vehicules&tab=marques&iac_msg=madded'));
    exit;
}

add_action('admin_post_iac_delete_marque', 'iac_delete_marque');
function iac_delete_marque() {
    acces_guard(acces_can_edit('vehicules'));
    check_admin_referer('iac_delete_marque');
    $name = isset($_GET['m']) ? sanitize_text_field(wp_unslash($_GET['m'])) : '';
    $list = array_values(array_filter(iac_marques_custom(), function ($x) use ($name) {
        return strtolower(trim($x)) !== strtolower(trim($name));
    }));
    update_option('iac_marques_custom', $list);
    wp_safe_redirect(admin_url('admin.php?page=vehicules&tab=marques&iac_msg=mdeleted'));
    exit;
}

/* ---------- PAGE : Marques ---------- */
function iac_page_marques() {
    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Marques</h1></div>';

    if (isset($_GET['iac_msg'])) {
        $m = array('madded' => 'Marque ajoutée.', 'mdeleted' => 'Marque supprimée.');
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    // Ajouter une marque
    echo '<div class="iac-card" style="max-width:560px;margin-bottom:22px">';
    echo '<h2 style="font-size:15px;margin:0 0 12px">Ajouter une marque</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:10px">';
    wp_nonce_field('iac_add_marque');
    echo '<input type="hidden" name="action" value="iac_add_marque">';
    echo '<input type="text" name="marque" placeholder="Nom de la marque" required style="flex:1">';
    echo '<button class="iac-btn">Ajouter</button>';
    echo '</form></div>';

    // Marques personnalisées
    $custom = iac_marques_custom();
    echo '<div class="iac-card" style="max-width:560px">';
    echo '<h2 style="font-size:15px;margin:0 0 12px">Marques ajoutées</h2>';
    if (!$custom) {
        echo '<p style="color:#777">Aucune marque personnalisée pour l\'instant.</p>';
    } else {
        echo '<table class="wp-list-table widefat striped"><tbody>';
        foreach ($custom as $mk) {
            $del = wp_nonce_url(admin_url('admin-post.php?action=iac_delete_marque&m=' . rawurlencode($mk)), 'iac_delete_marque');
            echo '<tr><td>' . esc_html($mk) . '</td><td style="width:100px;text-align:right"><a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer cette marque ?\')" style="color:#b23b3b">Supprimer</a></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p style="color:#777;font-size:12px;margin-top:10px">Supprimer une marque la retire de la liste et du footer. Les véhicules qui l\'utilisent déjà ne sont pas modifiés.</p>';
    }
    echo '</div>';

    // Marques de référence (non modifiables)
    echo '<div class="iac-card" style="max-width:560px;margin-top:22px">';
    echo '<h2 style="font-size:15px;margin:0 0 10px">Marques de référence</h2>';
    echo '<p style="color:#777">' . esc_html(implode(', ', iac_marques_base())) . '</p>';
    echo '</div>';

    echo '</div>';
}

/* ---------- Assets admin (médiathèque + style) ---------- */
add_action('admin_enqueue_scripts', 'iac_admin_assets');
function iac_admin_assets($hook) {
    $on_admin = (strpos($hook, 'vehicules') !== false || strpos($hook, 'ia-clients') !== false || strpos($hook, 'avances') !== false);
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
    if ($on_admin && $tab === 'edit') {
        wp_enqueue_media();
    }
}

function iac_admin_style() {
    echo '<style>
    .iac-wrap{max-width:1100px}
    .iac-head{display:flex;align-items:center;justify-content:space-between;margin:14px 0 22px}
    .iac-head h1{margin:0}
    .iac-btn{display:inline-block;background:linear-gradient(135deg,#D4AF37,#E07B20);color:#fff!important;border:0;border-radius:8px;padding:9px 18px;font-weight:600;text-decoration:none;cursor:pointer}
    .iac-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:26px}
    .iac-card{background:#fff;border:1px solid #e2e4e9;border-left:4px solid #D4AF37;border-radius:10px;padding:18px}
    .iac-card .n{font-size:30px;font-weight:800;color:#1a1a1a}
    .iac-card .l{color:#777;font-size:13px}
    .iac-thumb{width:64px;height:44px;object-fit:cover;border-radius:6px;background:#f0f0f0}
    .iac-form{background:#fff;border:1px solid #e2e4e9;border-radius:10px;padding:24px;max-width:760px}
    .iac-form .row{display:flex;gap:18px;flex-wrap:wrap}
    .iac-form .fld{flex:1;min-width:220px;margin-bottom:16px}
    .iac-form label{display:block;font-weight:600;margin-bottom:5px}
    .iac-form input[type=text],.iac-form input[type=number],.iac-form select,.iac-form textarea{width:100%}
    .iac-prev{margin-top:10px}
    .iac-prev img{max-width:240px;border:1px solid #e2e4e9;border-radius:8px}
    .iac-pill{padding:2px 9px;border-radius:99px;font-size:12px;font-weight:600}
    .iac-pill.ok{background:#e6f4ea;color:#1a7a3c}.iac-pill.cmd{background:#fff4e0;color:#b9770e}.iac-pill.sold{background:#fde8e8;color:#b23b3b}
    </style>';
}

/* ---------- Enregistrement (création / édition) ---------- */
add_action('admin_post_iac_save_vehicle', 'iac_save_vehicle');
function iac_save_vehicle() {
    acces_guard(acces_can_edit('vehicules'));
    check_admin_referer('iac_save_vehicle');

    global $wpdb;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Marque : une nouvelle marque saisie a la priorite et est memorisee
    $marque_new = sanitize_text_field($_POST['marque_new'] ?? '');
    $marque     = $marque_new !== '' ? $marque_new : sanitize_text_field($_POST['marque'] ?? '');
    if ($marque_new !== '') iac_marque_add($marque_new);

    $data = array(
        'marque'     => $marque,
        'modele'     => sanitize_text_field($_POST['modele'] ?? ''),
        'version'    => sanitize_text_field($_POST['version'] ?? ''),
        'boite'      => sanitize_text_field($_POST['boite'] ?? ''),
        'carburant'  => sanitize_text_field($_POST['carburant'] ?? ''),
        'couleur'    => sanitize_text_field($_POST['couleur'] ?? ''),
        'carrosserie'=> sanitize_text_field($_POST['carrosserie'] ?? ''),
        'prix'       => (int)($_POST['prix'] ?? 0),
        'douane_min' => (int)($_POST['douane_min'] ?? 0),
        'douane_max' => (int)($_POST['douane_max'] ?? 0),
        'statut'     => sanitize_text_field($_POST['statut'] ?? 'Disponible'),
        'featured'   => isset($_POST['featured']) ? 1 : 0,
        'image_id'   => (int)($_POST['image_id'] ?? 0),
        'description'=> wp_kses_post($_POST['description'] ?? ''),
        'slogan'        => sanitize_text_field($_POST['slogan'] ?? ''),
        'frais_douane'  => sanitize_text_field($_POST['frais_douane'] ?? ''),
        'moteur'        => sanitize_text_field($_POST['moteur'] ?? ''),
        'puissance'     => sanitize_text_field($_POST['puissance'] ?? ''),
        'couple'        => sanitize_text_field($_POST['couple'] ?? ''),
        'acceleration'  => sanitize_text_field($_POST['acceleration'] ?? ''),
        'vitesse_max'   => sanitize_text_field($_POST['vitesse_max'] ?? ''),
        'consommation'  => sanitize_text_field($_POST['consommation'] ?? ''),
        'volume_coffre' => sanitize_text_field($_POST['volume_coffre'] ?? ''),
        'dimensions'    => sanitize_text_field($_POST['dimensions'] ?? ''),
        'updated_at' => current_time('mysql'),
    );

    // Galerie + vue d'ensemble (listes d'IDs)
    $gallery_ids = array_values(array_filter(array_map('intval', explode(',', $_POST['gallery'] ?? ''))));
    $vue_ids     = array_values(array_filter(array_map('intval', explode(',', $_POST['vue_ensemble'] ?? ''))));
    $data['gallery'] = implode(',', $gallery_ids);
    if (!$data['image_id'] && $gallery_ids) $data['image_id'] = $gallery_ids[0];

    // Couleurs (4 max : nom + hex)
    $colors = array();
    for ($i=1;$i<=4;$i++){
        $nom = sanitize_text_field($_POST["coul{$i}_nom"] ?? '');
        $hex = sanitize_text_field($_POST["coul{$i}_hex"] ?? '');
        $photo = (int)($_POST["coul{$i}_photo"] ?? 0);
        if ($nom!=='' || $hex!=='' || $photo) $colors[] = array('nom'=>$nom, 'hex'=>$hex, 'photo'=>$photo);
    }

    $data['meta'] = wp_json_encode(array(
        'colors'           => $colors,
        'photos'           => $gallery_ids,
        'vue_ensemble'     => $vue_ids,
        'agilite'          => sanitize_text_field($_POST['agilite'] ?? ''),
        'conduite'         => sanitize_text_field($_POST['conduite'] ?? ''),
        'empattement'      => wp_kses_post($_POST['empattement'] ?? ''),
        'equip_securite'   => wp_kses_post($_POST['equip_securite'] ?? ''),
        'equip_confort'    => wp_kses_post($_POST['equip_confort'] ?? ''),
        'equip_multimedia' => wp_kses_post($_POST['equip_multimedia'] ?? ''),
        'transmission_txt' => sanitize_text_field($_POST['transmission_txt'] ?? ''),
    ));

    if ($id > 0) {
        $wpdb->update(iac_table(), $data, array('id' => $id));
        $msg = 'updated';
    } else {
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = get_current_user_id();
        $wpdb->insert(iac_table(), $data);
        $msg = 'created';
    }
    wp_safe_redirect(admin_url('admin.php?page=vehicules&tab=list&iac_msg=' . $msg));
    exit;
}

/* ---------- Suppression ---------- */
add_action('admin_post_iac_delete_vehicle', 'iac_delete_vehicle');
function iac_delete_vehicle() {
    acces_guard(acces_can_edit('vehicules'));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    check_admin_referer('iac_delete_' . $id);
    if ($id > 0) {
        global $wpdb;
        $wpdb->delete(iac_table(), array('id' => $id));
    }
    wp_safe_redirect(admin_url('admin.php?page=vehicules&tab=list&iac_msg=deleted'));
    exit;
}

/* ============================================================
 *  EXPORT EXCEL (CSV ; + BOM UTF-8, compatible Excel)
 * ============================================================ */
add_action('admin_post_iac_export_csv', 'iac_export_csv');
function iac_export_csv() {
    acces_guard(acces_can_edit('vehicules'));
    check_admin_referer('iac_export_csv');

    $vehicles = ia_get_vehicles(array('orderby' => 'marque', 'order' => 'ASC'));
    $date = date('Y-m-d');
    $filename = 'voitures-intermediate-auto-' . $date . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel

    $headers = array('ID','Marque','Modèle','Version','Carrosserie','Boîte','Carburant','Couleurs',
        'Prix (×10 000 DA)','Douane min (M)','Douane max (M)','Frais de douane','Statut','Vedette',
        'Moteur','Puissance','Couple','Accélération','Vitesse max','Consommation','Volume coffre','Dimensions',
        'Slogan','Créé le','Mis à jour le');
    fputcsv($out, $headers, ';');

    foreach ($vehicles as $v) {
        $meta = function_exists('ia_vehicle_meta') ? ia_vehicle_meta($v) : array();
        $colnames = array();
        if (!empty($meta['colors'])) foreach ($meta['colors'] as $c) { if (!empty($c['nom'])) $colnames[] = $c['nom']; }
        if (!$colnames && $v->couleur) $colnames[] = $v->couleur;

        fputcsv($out, array(
            $v->id, $v->marque, $v->modele, $v->version, $v->carrosserie, $v->boite, $v->carburant,
            implode(', ', $colnames),
            (int)$v->prix, (int)$v->douane_min, (int)$v->douane_max, $v->frais_douane,
            $v->statut, ((int)$v->featured ? 'Oui' : 'Non'),
            $v->moteur, $v->puissance, $v->couple, $v->acceleration, $v->vitesse_max,
            $v->consommation, $v->volume_coffre, $v->dimensions,
            $v->slogan, $v->created_at, $v->updated_at,
        ), ';');
    }
    fclose($out);
    exit;
}

/* ============================================================
 *  PAGE : Exporter
 * ============================================================ */
function iac_page_export() {
    global $wpdb;
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . iac_table());
    $url = wp_nonce_url(admin_url('admin-post.php?action=iac_export_csv'), 'iac_export_csv');
    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Exporter les voitures</h1></div>';
    echo '<div class="iac-card" style="max-width:620px">';
    echo '<p style="font-size:15px">Téléchargez l\'intégralité du catalogue (<strong>' . $count . ' véhicule' . ($count>1?'s':'') . '</strong>) dans un fichier <strong>Excel (CSV)</strong> : marque, modèle, version, prix, frais de douane, caractéristiques…</p>';
    echo '<p style="margin-top:16px"><a class="iac-btn" href="' . esc_url($url) . '">⬇ Télécharger le fichier Excel</a></p>';
    echo '<p style="color:#777;font-size:13px;margin-top:14px">Le fichier s\'ouvre directement dans Excel (ou LibreOffice / Google Sheets). Accents et colonnes correctement gérés.</p>';
    echo '</div></div>';
}

/* ============================================================
 *  PAGE : Tableau de bord
 * ============================================================ */
function iac_page_dashboard() {
    global $wpdb;
    $t = iac_table();
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");
    $dispo = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE statut=%s", 'Disponible'));
    $cmd   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE statut=%s", 'Sur commande'));
    $marques = (int)$wpdb->get_var("SELECT COUNT(DISTINCT marque) FROM {$t}");
    $clients = 0;
    if (function_exists('iac_clients_table')) {
        $ct = iac_clients_table();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ct)) === $ct) {
            $clients = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$ct} WHERE active=1");
        }
    }
    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Tableau de bord — Intermediate Auto</h1>';
    echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=vehicules&tab=edit')) . '">+ Ajouter un véhicule</a></div>';
    echo '<div class="iac-cards">';
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Véhicules au total</div></div>', $total);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Disponibles</div></div>', $dispo);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Sur commande</div></div>', $cmd);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Clients actifs</div></div>', $clients);
    echo '</div>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=vehicules&tab=list')) . '">Gérer les véhicules</a> ';
    echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=ia-clients')) . '">Gérer les clients</a></p>';
    echo '<p style="color:#777">Prochaines étapes prévues : commandes, factures et bons de livraison.</p>';
    echo '</div>';
}

/* ============================================================
 *  PAGE : Liste des véhicules
 * ============================================================ */
function iac_page_list() {
    $vehicles = ia_get_vehicles(array('orderby' => 'id', 'order' => 'DESC'));
    iac_admin_style();
    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Véhicules</h1>';
    echo '<a class="iac-btn" href="' . esc_url(admin_url('admin.php?page=vehicules&tab=edit')) . '">+ Ajouter un véhicule</a></div>';

    if (isset($_GET['iac_msg'])) {
        $m = array('created'=>'Véhicule ajouté.','updated'=>'Véhicule mis à jour.','deleted'=>'Véhicule supprimé.');
        $k = sanitize_key($_GET['iac_msg']);
        if (isset($m[$k])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($m[$k]) . '</p></div>';
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th style="width:80px">Photo</th><th>Véhicule</th><th>Boîte</th><th>Couleur</th><th>Prix</th><th>Douane</th><th>Statut</th><th>Créé par</th><th style="width:140px">Actions</th></tr></thead><tbody>';

    if (!$vehicles) {
        echo '<tr><td colspan="9">Aucun véhicule. <a href="' . esc_url(admin_url('admin.php?page=vehicules&tab=edit')) . '">Ajoutez-en un</a>.</td></tr>';
    } else {
        foreach ($vehicles as $v) {
            $edit = admin_url('admin.php?page=vehicules&tab=edit&id=' . $v->id);
            $del  = wp_nonce_url(admin_url('admin-post.php?action=iac_delete_vehicle&id=' . $v->id), 'iac_delete_' . $v->id);
            $pill = $v->statut==='Disponible' ? 'ok' : ($v->statut==='Vendu' ? 'sold' : 'cmd');
            echo '<tr>';
            echo '<td><img class="iac-thumb" src="' . esc_url(ia_vehicle_image($v, 'thumbnail')) . '"></td>';
            echo '<td>' . (!empty($v->featured) ? '<span title="En vedette">⭐ </span>' : '') . '<strong>' . esc_html(ia_vehicle_title($v)) . '</strong>' . ($v->version ? ' <span style="color:#777">'.esc_html($v->version).'</span>' : '') . '</td>';
            echo '<td>' . esc_html($v->boite) . '</td>';
            echo '<td>' . esc_html($v->couleur) . '</td>';
            echo '<td><strong>' . (int)$v->prix . '</strong> <span style="color:#999">×10⁴ DA</span></td>';
            echo '<td>' . esc_html(ia_douane_label($v)) . '</td>';
            echo '<td><span class="iac-pill ' . $pill . '">' . esc_html($v->statut) . '</span></td>';
            echo '<td style="font-size:12px;color:#555">' . esc_html(meta_created_text($v->created_by ?? 0, $v->created_at ?? '')) . '</td>';
            echo '<td><a href="' . esc_url($edit) . '">Modifier</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'Supprimer ce véhicule ?\')" style="color:#b23b3b">Suppr.</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

/** Sélecteur multi-images réutilisable (liste d'IDs séparés par des virgules) */
function iac_multi_picker($name, $label, $ids) {
    $ids = array_filter(array_map('intval', (array)$ids));
    $h  = '<div class="fld"><label>' . esc_html($label) . '</label>';
    $h .= '<div class="iac-multi" data-name="' . esc_attr($name) . '">';
    $h .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(implode(',', $ids)) . '">';
    $h .= '<div class="iac-mprev" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">';
    foreach ($ids as $gid) {
        $u = wp_get_attachment_image_url($gid, 'thumbnail');
        if (!$u) continue;
        $h .= '<span data-id="' . $gid . '" style="position:relative;display:inline-block">'
            . '<img src="' . esc_url($u) . '" style="width:70px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #ddd">'
            . '<a href="#" class="iac-mrm" style="position:absolute;top:-6px;right:-6px;background:#b23b3b;color:#fff;border-radius:50%;width:18px;height:18px;line-height:18px;text-align:center;text-decoration:none;font-size:12px">×</a></span>';
    }
    $h .= '</div><button type="button" class="button iac-madd">📷 Ajouter des images</button></div>';
    return $h;
}

/* ============================================================
 *  PAGE : Ajouter / Modifier un véhicule
 * ============================================================ */
function iac_page_edit() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $v  = $id ? ia_get_vehicle($id) : null;
    $get = function($k, $d='') use ($v) { return $v && isset($v->$k) ? $v->$k : $d; };
    $meta = ($v && function_exists('ia_vehicle_meta')) ? ia_vehicle_meta($v) : array();
    $gm = function($k, $d='') use ($meta) { return isset($meta[$k]) ? $meta[$k] : $d; };
    $colors = isset($meta['colors']) && is_array($meta['colors']) ? $meta['colors'] : array();
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>' . ($id ? 'Modifier un véhicule' : 'Ajouter un véhicule') . '</h1>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=vehicules&tab=list')) . '">← Retour à la liste</a></div>';

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('iac_save_vehicle');
    echo '<input type="hidden" name="action" value="iac_save_vehicle">';
    echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

    // Marque + Modèle
    echo '<div class="row">';
    echo '<div class="fld"><label>Marque</label><select name="marque">';
    foreach (iac_marques() as $m) echo '<option ' . selected($get('marque'), $m, false) . '>' . esc_html($m) . '</option>';
    echo '</select>';
    echo '<input type="text" name="marque_new" value="" placeholder="➕ ou saisir une nouvelle marque" style="margin-top:6px;width:100%">';
    echo '<span style="color:#777;font-size:12px">Si la marque n\'est pas dans la liste, saisissez-la ici : elle sera ajoutée à la liste.</span></div>';
    echo '<div class="fld"><label>Modèle</label><input type="text" name="modele" value="' . esc_attr($get('modele')) . '" required></div>';
    echo '</div>';

    // Version + Couleur
    echo '<div class="row">';
    echo '<div class="fld"><label>Version / Finition</label><input type="text" name="version" value="' . esc_attr($get('version')) . '" placeholder="Ex : Basic, Enjoy, R-Style, Full Option…"></div>';
    echo '<div class="fld"><label>Couleur(s)</label><input type="text" name="couleur" value="' . esc_attr($get('couleur')) . '" placeholder="Ex : Blanc / Gris"></div>';
    echo '</div>';

    // Boîte + Carburant
    echo '<div class="row">';
    echo '<div class="fld"><label>Boîte</label><select name="boite">';
    foreach (iac_boites() as $b) echo '<option ' . selected($get('boite'), $b, false) . '>' . esc_html($b) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Carburant</label><select name="carburant">';
    foreach (iac_carburants() as $c) echo '<option ' . selected($get('carburant'), $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select></div>';
    echo '</div>';

    // Carrosserie (type)
    echo '<div class="row"><div class="fld"><label>Carrosserie / Type</label><select name="carrosserie">';
    echo '<option value="">— Choisir —</option>';
    foreach (iac_carrosseries() as $c) echo '<option ' . selected($get('carrosserie'), $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select></div><div class="fld"></div></div>';

    // Prix + douane min/max + statut
    echo '<div class="row">';
    echo '<div class="fld"><label>Prix (×10 000 DA)</label><input type="number" name="prix" value="' . esc_attr($get('prix',0)) . '" min="0"></div>';
    echo '<div class="fld"><label>Douane min (M)</label><input type="number" name="douane_min" value="' . esc_attr($get('douane_min',0)) . '" min="0"></div>';
    echo '<div class="fld"><label>Douane max (M)</label><input type="number" name="douane_max" value="' . esc_attr($get('douane_max',0)) . '" min="0"></div>';
    echo '</div>';
    echo '<div class="row"><div class="fld"><label>Statut</label><select name="statut">';
    foreach (iac_statuts() as $s) echo '<option ' . selected($get('statut','Disponible'), $s, false) . '>' . esc_html($s) . '</option>';
    echo '</select></div>';
    echo '<div class="fld"><label>Mise en avant</label><label style="font-weight:400;display:flex;align-items:center;gap:8px;margin-top:6px"><input type="checkbox" name="featured" value="1" ' . checked((int)$get('featured',0), 1, false) . '> ⭐ Mettre ce véhicule en vedette sur l\'accueil</label></div></div>';

    // Slogan + frais de douane (texte)
    echo '<div class="row">';
    echo '<div class="fld"><label>Slogan (accroche)</label><input type="text" name="slogan" value="' . esc_attr($get('slogan')) . '"></div>';
    echo '<div class="fld"><label>Frais de douane (texte affiché)</label><input type="text" name="frais_douane" value="' . esc_attr($get('frais_douane')) . '" placeholder="Ex : 950 000 DZD"></div>';
    echo '</div>';

    // Fiche technique
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Fiche technique</h2>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Moteur</label><input type="text" name="moteur" value="' . esc_attr($get('moteur')) . '" placeholder="Ex : 1.5L Essence (4 cyl.)"></div>';
    echo '<div class="fld"><label>Puissance</label><input type="text" name="puissance" value="' . esc_attr($get('puissance')) . '" placeholder="Ex : 116 ch"></div>';
    echo '</div><div class="row">';
    echo '<div class="fld"><label>Couple</label><input type="text" name="couple" value="' . esc_attr($get('couple')) . '" placeholder="Ex : 141 Nm"></div>';
    echo '<div class="fld"><label>Accélération 0-100</label><input type="text" name="acceleration" value="' . esc_attr($get('acceleration')) . '"></div>';
    echo '</div><div class="row">';
    echo '<div class="fld"><label>Vitesse max</label><input type="text" name="vitesse_max" value="' . esc_attr($get('vitesse_max')) . '"></div>';
    echo '<div class="fld"><label>Consommation</label><input type="text" name="consommation" value="' . esc_attr($get('consommation')) . '"></div>';
    echo '</div><div class="row">';
    echo '<div class="fld"><label>Volume coffre</label><input type="text" name="volume_coffre" value="' . esc_attr($get('volume_coffre')) . '"></div>';
    echo '<div class="fld"><label>Dimensions</label><input type="text" name="dimensions" value="' . esc_attr($get('dimensions')) . '"></div>';
    echo '</div><div class="row">';
    echo '<div class="fld"><label>Transmission (texte complet)</label><input type="text" name="transmission_txt" value="' . esc_attr($gm('transmission_txt')) . '" placeholder="Ex : Automatique CVT"></div>';
    echo '<div class="fld"><label>Empattement</label><input type="text" name="empattement" value="' . esc_attr(wp_strip_all_tags($gm('empattement'))) . '"></div>';
    echo '</div><div class="row">';
    echo '<div class="fld"><label>Agilité</label><input type="text" name="agilite" value="' . esc_attr($gm('agilite')) . '"></div>';
    echo '<div class="fld"><label>Conduite</label><input type="text" name="conduite" value="' . esc_attr($gm('conduite')) . '"></div>';
    echo '</div>';

    // Équipements (3 catégories)
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Équipements</h2>';
    echo '<div class="fld"><label>Sécurité</label><textarea name="equip_securite" rows="3">' . esc_textarea($gm('equip_securite')) . '</textarea></div>';
    echo '<div class="fld"><label>Confort</label><textarea name="equip_confort" rows="3">' . esc_textarea($gm('equip_confort')) . '</textarea></div>';
    echo '<div class="fld"><label>Multimédia</label><textarea name="equip_multimedia" rows="3">' . esc_textarea($gm('equip_multimedia')) . '</textarea></div>';

    // Couleurs (4 max)
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Couleurs</h2>';
    echo '<p style="color:#777;font-size:13px;margin:-4px 0 10px">Pour chaque couleur : le nom, le code couleur, et la photo du véhicule dans cette couleur (elle s\'affichera quand on clique sur la couleur sur la fiche).</p>';
    for ($i=1;$i<=4;$i++){
        $cn = isset($colors[$i-1]['nom']) ? $colors[$i-1]['nom'] : '';
        $ch = isset($colors[$i-1]['hex']) ? $colors[$i-1]['hex'] : '';
        $cp = isset($colors[$i-1]['photo']) ? (int)$colors[$i-1]['photo'] : 0;
        $cpu = $cp ? wp_get_attachment_image_url($cp, 'thumbnail') : '';
        echo '<div class="row" style="align-items:flex-start">';
        echo '<div class="fld"><label>Couleur ' . $i . ' — nom</label><input type="text" name="coul' . $i . '_nom" value="' . esc_attr($cn) . '"></div>';
        echo '<div class="fld" style="max-width:120px"><label>Code</label><input type="color" name="coul' . $i . '_hex" value="' . esc_attr($ch ?: '#cccccc') . '" style="height:42px;padding:3px;width:100%"></div>';
        echo '<div class="fld"><label>Photo (couleur ' . $i . ')</label>';
        echo '<div class="iac-cimg"><input type="hidden" name="coul' . $i . '_photo" value="' . esc_attr($cp) . '">';
        echo '<button type="button" class="button iac-cadd">📷 Choisir</button> <button type="button" class="button iac-cclr">Retirer</button>';
        echo '<div class="iac-cprev" style="margin-top:6px">' . ($cpu ? '<img src="' . esc_url($cpu) . '" style="width:80px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ddd">' : '') . '</div>';
        echo '</div></div>';
        echo '</div>';
    }

    // Description
    echo '<div class="fld"><label>Description (facultatif)</label><textarea name="description" rows="4">' . esc_textarea($get('description')) . '</textarea></div>';

    // Image
    $img_id = (int)$get('image_id', 0);
    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
    echo '<div class="fld"><label>Photo du véhicule</label>';
    echo '<input type="hidden" id="ia_image_id" name="image_id" value="' . esc_attr($img_id) . '">';
    echo '<button type="button" class="button" id="ia_pick_img">📷 Choisir dans la médiathèque</button> ';
    echo '<button type="button" class="button" id="ia_clear_img">Retirer</button>';
    echo '<div class="iac-prev" id="ia_prev">' . ($img_url ? '<img src="' . esc_url($img_url) . '">' : '') . '</div>';
    echo '</div>';

    // Galerie + vue d'ensemble (multi-images)
    echo '<h2 style="font-size:16px;margin:18px 0 6px;border-top:1px solid #eee;padding-top:16px">Galerie & vue d\'ensemble</h2>';
    $gallery_ids = $v ? array_filter(array_map('intval', explode(',', (string)$v->gallery))) : array();
    echo iac_multi_picker('gallery', 'Galerie (photos du véhicule)', $gallery_ids);
    echo iac_multi_picker('vue_ensemble', 'Vue d\'ensemble (images « En images »)', $gm('vue_ensemble', array()));

    echo '<p><button type="submit" class="iac-btn">' . ($id ? 'Enregistrer les modifications' : 'Ajouter le véhicule') . '</button></p>';
    echo '</form></div>';

    // JS médiathèque
    ?>
    <script>
    jQuery(function($){
      var frame;
      $('#ia_pick_img').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame = wp.media({ title:'Choisir une photo', button:{text:'Utiliser cette photo'}, multiple:false });
        frame.on('select', function(){
          var a = frame.state().get('selection').first().toJSON();
          $('#ia_image_id').val(a.id);
          var u = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
          $('#ia_prev').html('<img src="'+u+'">');
        });
        frame.open();
      });
      $('#ia_clear_img').on('click', function(e){ e.preventDefault(); $('#ia_image_id').val(''); $('#ia_prev').empty(); });

      // Sélecteurs multi-images (galerie, vue d'ensemble)
      var thumbHtml = function(id,u){ return '<span data-id="'+id+'" style="position:relative;display:inline-block"><img src="'+u+'" style="width:70px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #ddd"><a href="#" class="iac-mrm" style="position:absolute;top:-6px;right:-6px;background:#b23b3b;color:#fff;border-radius:50%;width:18px;height:18px;line-height:18px;text-align:center;text-decoration:none;font-size:12px">&times;</a></span>'; };
      $('.iac-madd').on('click', function(e){
        e.preventDefault();
        var wrap = $(this).closest('.iac-multi');
        var f = wp.media({ title:'Ajouter des images', button:{text:'Ajouter'}, multiple:true });
        f.on('select', function(){
          var input = wrap.find('input[type=hidden]');
          var ids = input.val() ? input.val().split(',') : [];
          f.state().get('selection').each(function(a){
            a = a.toJSON();
            if (ids.indexOf(String(a.id)) === -1){
              ids.push(String(a.id));
              var u = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
              wrap.find('.iac-mprev').append(thumbHtml(a.id, u));
            }
          });
          input.val(ids.join(','));
        });
        f.open();
      });
      $(document).on('click', '.iac-mrm', function(e){
        e.preventDefault();
        var span = $(this).closest('span'), id = String(span.data('id'));
        var input = span.closest('.iac-multi').find('input[type=hidden]');
        var ids = input.val() ? input.val().split(',') : [];
        input.val(ids.filter(function(x){ return x !== id; }).join(','));
        span.remove();
      });

      // Photo par couleur (image unique)
      $('.iac-cadd').on('click', function(e){
        e.preventDefault();
        var wrap = $(this).closest('.iac-cimg');
        var f = wp.media({ title:'Photo de la couleur', button:{text:'Utiliser'}, multiple:false });
        f.on('select', function(){
          var a = f.state().get('selection').first().toJSON();
          wrap.find('input[type=hidden]').val(a.id);
          var u = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
          wrap.find('.iac-cprev').html('<img src="'+u+'" style="width:80px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #ddd">');
        });
        f.open();
      });
      $('.iac-cclr').on('click', function(e){ e.preventDefault(); var w=$(this).closest('.iac-cimg'); w.find('input[type=hidden]').val(''); w.find('.iac-cprev').empty(); });
    });
    </script>
    <?php
}
