<?php
/**
 * Plugin Name: Intermediate Auto Core
 * Description: Cœur métier d'Intermediate Auto : table véhicules propre (hors articles), dashboard d'administration, formulaire de création de véhicules. Base du futur back-office (commandes, factures, bons de livraison, export).
 * Version: 1.0
 * Author: Intermediate Auto
 * Text Domain: intermediate-auto
 */

if (!defined('ABSPATH')) exit;

define('IAC_VER', '1.4');
define('IAC_DIR', plugin_dir_path(__FILE__));
define('IAC_URL', plugin_dir_url(__FILE__));

/** Nom complet de la table véhicules */
function iac_table() {
    global $wpdb;
    return $wpdb->prefix . 'ia_vehicles';
}

/** Listes de référence (réutilisées par le formulaire et les filtres) */
function iac_marques()    { return array('Geely','MG','Livan','GAC','Jetta','Chery','Changan','T-Roc','Rongwei','Volkswagen','Autre'); }

/** Marques réellement présentes dans le catalogue (pour les filtres) */
function ia_marques_in_use() {
    global $wpdb;
    return $wpdb->get_col("SELECT DISTINCT marque FROM " . iac_table() . " WHERE marque<>'' ORDER BY marque");
}
/** Carrosseries réellement présentes (pour les filtres) */
function ia_carrosseries_in_use() {
    global $wpdb;
    return $wpdb->get_col("SELECT DISTINCT carrosserie FROM " . iac_table() . " WHERE carrosserie<>'' ORDER BY carrosserie");
}
function iac_carburants() { return array('Essence','Diesel','Hybride','Électrique'); }
function iac_boites()     { return array('Manuelle','Automatique'); }
function iac_statuts()    { return array('Disponible','Sur commande','Vendu'); }
function iac_carrosseries(){ return array('SUV','Berline','Citadine','Mini-citadine','Pick-up','Autre'); }

/* ============================================================
 *  ACTIVATION : création de la table + données de démonstration
 * ============================================================ */
register_activation_hook(__FILE__, 'iac_activate');
function iac_activate() {
    global $wpdb;
    $table = iac_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        marque VARCHAR(80) NOT NULL DEFAULT '',
        modele VARCHAR(120) NOT NULL DEFAULT '',
        version VARCHAR(120) NOT NULL DEFAULT '',
        boite VARCHAR(40) NOT NULL DEFAULT '',
        carburant VARCHAR(40) NOT NULL DEFAULT '',
        couleur VARCHAR(120) NOT NULL DEFAULT '',
        prix INT(11) NOT NULL DEFAULT 0,
        douane_min INT(11) NOT NULL DEFAULT 0,
        douane_max INT(11) NOT NULL DEFAULT 0,
        statut VARCHAR(40) NOT NULL DEFAULT 'Disponible',
        featured TINYINT(1) NOT NULL DEFAULT 0,
        image_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        description TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
        PRIMARY KEY (id),
        KEY marque (marque),
        KEY statut (statut),
        KEY featured (featured)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Données de démonstration (une seule fois)
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    if ($count === 0) {
        $now = current_time('mysql');
        $demo = array(
            array('Livan','X3 Pro','','Manuelle','Essence','Blanc / Gris',234,75,85,'Disponible'),
            array('Livan','X3 Pro','','Automatique','Essence','Blanc / Gris',249,75,85,'Disponible'),
            array('MG','MG 5','','Manuelle','Essence','Gris',232,85,95,'Disponible'),
            array('MG','MG 5','Avec toit','Automatique','Essence','Blanc / Gris',283,85,95,'Disponible'),
            array('Jetta','VS5','La gamme','Automatique','Essence','Gris / Noir',390,100,100,'Sur commande'),
            array('GAC','GS3','La base','Automatique','Essence','Blanc',262,100,110,'Disponible'),
            array('GAC','GS3','Medium','Automatique','Essence','Gris / Argent / Blanc',302,100,110,'Disponible'),
            array('Geely','Coolray','','Manuelle','Essence','Gris',265,90,100,'Disponible'),
            array('Geely','Coolray','Super Edition','Automatique','Essence','Gris',298,100,100,'Disponible'),
            array('T-Roc','T-Roc','La gamme','Automatique','Essence','Noir',540,135,135,'Sur commande'),
        );
        foreach ($demo as $d) {
            $wpdb->insert($table, array(
                'marque'=>$d[0],'modele'=>$d[1],'version'=>$d[2],'boite'=>$d[3],
                'carburant'=>$d[4],'couleur'=>$d[5],'prix'=>$d[6],'douane_min'=>$d[7],
                'douane_max'=>$d[8],'statut'=>$d[9],'created_at'=>$now,'updated_at'=>$now,
            ));
        }
        // 3 véhicules en vedette par défaut (les plus chers)
        $wpdb->query("UPDATE {$table} SET featured=1 ORDER BY prix DESC LIMIT 3");
    }
}

/* ============================================================
 *  MISE À NIVEAU (ajout de colonnes sur une table déjà créée)
 * ============================================================ */
/** Colonnes étendues ajoutées après la création initiale */
function iac_columns_ext() {
    return array(
        'featured'      => "TINYINT(1) NOT NULL DEFAULT 0",
        'slogan'        => "VARCHAR(255) NOT NULL DEFAULT ''",
        'frais_douane'  => "VARCHAR(80) NOT NULL DEFAULT ''",
        'moteur'        => "VARCHAR(150) NOT NULL DEFAULT ''",
        'puissance'     => "VARCHAR(60) NOT NULL DEFAULT ''",
        'couple'        => "VARCHAR(60) NOT NULL DEFAULT ''",
        'acceleration'  => "VARCHAR(60) NOT NULL DEFAULT ''",
        'vitesse_max'   => "VARCHAR(60) NOT NULL DEFAULT ''",
        'consommation'  => "VARCHAR(60) NOT NULL DEFAULT ''",
        'volume_coffre' => "VARCHAR(60) NOT NULL DEFAULT ''",
        'dimensions'    => "VARCHAR(150) NOT NULL DEFAULT ''",
        'equipements'   => "TEXT NULL",
        'gallery'       => "TEXT NULL",
        'old_post_id'   => "BIGINT(20) UNSIGNED NOT NULL DEFAULT 0",
        'meta'          => "LONGTEXT NULL",
        'carrosserie'   => "VARCHAR(60) NOT NULL DEFAULT ''",
    );
}

add_action('plugins_loaded', 'iac_maybe_upgrade');
function iac_maybe_upgrade() {
    if (get_option('iac_db_ver') === IAC_VER) return;
    global $wpdb;
    $table = iac_table();
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) return;
    $existing = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
    $featured_added = false;
    foreach (iac_columns_ext() as $col => $def) {
        if (!in_array($col, $existing, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
            if ($col === 'featured') $featured_added = true;
        }
    }
    if ($featured_added && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE featured=1") === 0) {
        $wpdb->query("UPDATE {$table} SET featured=1 ORDER BY prix DESC LIMIT 3");
    }
    update_option('iac_db_ver', IAC_VER);
    update_option('iac_flush', 1); // demandera un flush des permaliens à l'init
}

/* ============================================================
 *  ROUTE FICHE VÉHICULE : /vehicule/{id}/{slug}
 * ============================================================ */
add_action('init', 'iac_rewrite');
function iac_rewrite() {
    add_rewrite_rule('^vehicule/([0-9]+)(?:/[^/]*)?/?$', 'index.php?ia_vehicle=$matches[1]', 'top');
    if (get_option('iac_flush')) { flush_rewrite_rules(false); delete_option('iac_flush'); }
}
add_filter('query_vars', function($v){ $v[] = 'ia_vehicle'; return $v; });

add_filter('template_include', 'iac_template');
function iac_template($template) {
    $id = (int) get_query_var('ia_vehicle');
    if ($id) {
        $t = locate_template('single-vehicule.php');
        if ($t) return $t;
    }
    return $template;
}

/** URL de la fiche d'un véhicule */
function ia_vehicle_url($v) {
    return home_url('/vehicule/' . (int)$v->id . '/' . sanitize_title(ia_vehicle_title($v)));
}

/** Métadonnées JSON décodées d'un véhicule */
function ia_vehicle_meta($v) {
    if (empty($v->meta)) return array();
    $d = json_decode($v->meta, true);
    return is_array($d) ? $d : array();
}

/* ============================================================
 *  FONCTIONS D'ACCÈS AUX DONNÉES (utilisées par le thème)
 * ============================================================ */

/**
 * Récupère des véhicules.
 * @param array $args  limit, status, marque, orderby, search
 * @return array       liste d'objets
 */
function ia_get_vehicles($args = array()) {
    global $wpdb;
    $table = iac_table();
    $args = wp_parse_args($args, array(
        'limit'    => 0,
        'status'   => '',
        'marque'   => '',
        'carrosserie' => '',
        'search'   => '',
        'featured' => null,
        'orderby'  => 'created_at',
        'order'    => 'DESC',
    ));

    $where = array('1=1');
    $params = array();
    if ($args['status'] !== '') { $where[] = 'statut = %s'; $params[] = $args['status']; }
    if ($args['marque'] !== '') { $where[] = 'marque = %s'; $params[] = $args['marque']; }
    if ($args['carrosserie'] !== '') { $where[] = 'carrosserie = %s'; $params[] = $args['carrosserie']; }
    if ($args['featured'] !== null) { $where[] = 'featured = %d'; $params[] = (int)$args['featured']; }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(marque LIKE %s OR modele LIKE %s OR version LIKE %s)';
        array_push($params, $like, $like, $like);
    }

    $allowed_orderby = array('created_at','prix','marque','modele','id');
    $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
    $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} {$order}";
    if ((int)$args['limit'] > 0) $sql .= ' LIMIT ' . (int)$args['limit'];

    if ($params) $sql = $wpdb->prepare($sql, $params);
    return $wpdb->get_results($sql);
}

/** Un véhicule par id */
function ia_get_vehicle($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . iac_table() . " WHERE id = %d", (int)$id));
}

/** URL de l'image d'un véhicule (média WP ou placeholder thème) */
function ia_vehicle_image($v, $size = 'large') {
    if (!empty($v->image_id)) {
        $url = wp_get_attachment_image_url((int)$v->image_id, $size);
        if ($url) return $url;
    }
    return get_template_directory_uri() . '/assets/img/placeholder-vehicle.svg';
}

/** Libellé titre d'un véhicule (marque + modèle + version, sans répétition) */
function ia_vehicle_title($v) {
    $marque  = trim($v->marque);
    $modele  = trim($v->modele);
    $version = trim(isset($v->version) ? $v->version : '');
    if ($modele === '') {
        $base = $marque !== '' ? $marque : 'Véhicule';
    } elseif ($marque !== '' && stripos($modele, $marque) === false) {
        $base = $marque . ' ' . $modele;
    } else {
        $base = $modele;
    }
    if ($version !== '' && stripos($base, $version) === false) $base .= ' ' . $version;
    return $base;
}

/** Galerie : tableau d'IDs d'attachements */
function ia_vehicle_gallery($v) {
    if (empty($v->gallery)) return array();
    return array_filter(array_map('intval', explode(',', $v->gallery)));
}

/** Fourchette de douane formatée */
function ia_douane_label($v) {
    if ((int)$v->douane_min === (int)$v->douane_max) return (int)$v->douane_min . ' M';
    return (int)$v->douane_min . ' – ' . (int)$v->douane_max . ' M';
}

/* ============================================================
 *  ADMINISTRATION
 * ============================================================ */
require_once IAC_DIR . 'includes/admin.php';
