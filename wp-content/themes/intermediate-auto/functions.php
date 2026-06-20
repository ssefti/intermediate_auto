<?php
/**
 * Intermediate Auto — fonctions du thème
 */
if (!defined('ABSPATH')) exit;

define('IA_WHATSAPP', '213560020837');     // numéro WhatsApp (format international)
define('IA_PHONE',    '0560 02 08 37');
define('IA_EMAIL',    'contact@intermediate-auto.com');
define('IA_ADDRESS',  'Rue colonel Ahmed Bougura, Boufarik');

function ia_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form','gallery','caption','style','script'));
    register_nav_menus(array('primary' => 'Menu principal'));
}
add_action('after_setup_theme', 'ia_setup');

function ia_assets() {
    wp_enqueue_style('intermediate-auto', get_stylesheet_uri(), array(), '1.0');
}
add_action('wp_enqueue_scripts', 'ia_assets');

/** URL d'un asset image du thème */
function ia_img($file) {
    return get_template_directory_uri() . '/assets/img/' . $file;
}

/** Lien d'une page par slug (avec repli #) */
function ia_url($slug) {
    $p = get_page_by_path($slug);
    return $p ? get_permalink($p->ID) : home_url('/');
}

/** Lien WhatsApp pré-rempli */
function ia_wa_link($text = 'Bonjour, je vous contacte depuis votre site.') {
    return 'https://wa.me/' . IA_WHATSAPP . '?text=' . rawurlencode($text);
}

/** Navigation principale avec état actif */
function ia_nav() {
    $items = array(
        ''           => 'Accueil',
        'vehicules'  => 'Véhicules',
        'simulateur' => 'Simulateur Douane',
        'a-propos'   => 'À propos',
        'contact'    => 'Contact',
    );
    $current = '';
    if (is_page()) $current = get_post_field('post_name', get_queried_object_id());
    if (is_front_page()) $current = '';
    echo '<nav>';
    foreach ($items as $slug => $label) {
        $url = $slug === '' ? home_url('/') : ia_url($slug);
        $active = ($slug === $current) ? ' class="active"' : '';
        echo '<a' . $active . ' href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

/** Ajoute la classe "js" à <html> au plus tôt (pour les animations) */
function ia_js_class() {
    echo "<script>document.documentElement.className+=' js';</script>\n";
}
add_action('wp_head', 'ia_js_class', 1);

/** Favicon / icône d'onglet (logo Intermediate Auto) */
function ia_favicon() {
    if (get_site_icon_url()) return; // ne pas dupliquer si une icône WP est définie
    $u = ia_img('Logo_intermediate_auto_black.jpeg');
    echo '<link rel="icon" type="image/jpeg" href="' . esc_url($u) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url($u) . '">' . "\n";
}
add_action('wp_head', 'ia_favicon', 2);

/** Déclare les templates de page (fallback si non détectés) */
function ia_page_templates($templates) {
    $templates['template-apropos.php']    = 'IA — À propos';
    $templates['template-simulateur.php'] = 'IA — Simulateur douane';
    $templates['template-contact.php']    = 'IA — Contact';
    $templates['template-vehicules.php']  = 'IA — Véhicules (catalogue)';
    return $templates;
}
add_filter('theme_page_templates', 'ia_page_templates');

/** Repli : si le plugin n'est pas actif, fournir des données de démo */
function ia_vehicles_safe($args = array()) {
    if (function_exists('ia_get_vehicles')) return ia_get_vehicles($args);
    return array();
}
