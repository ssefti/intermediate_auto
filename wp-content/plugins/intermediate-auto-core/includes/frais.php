<?php
/**
 * Frais variables : paramètres globaux (tous facultatifs).
 * Taux de change, frais de transport, marge bénéficiaire (200 000 DA par défaut).
 */
if (!defined('ABSPATH')) exit;

/** Marge bénéficiaire par défaut (DA) */
define('FRAIS_MARGE_DEFAUT', 200000);

/** Valeurs actuelles (chaîne vide = non renseigné) */
function frais_get() {
    $v = get_option('iac_frais_variables', null);
    if (!is_array($v)) {
        return array('taux_change' => '', 'frais_transport' => '', 'marge_beneficiaire' => (string)FRAIS_MARGE_DEFAUT);
    }
    return wp_parse_args($v, array('taux_change' => '', 'frais_transport' => '', 'marge_beneficiaire' => ''));
}

/** Nombre décimal ou chaîne vide si non renseigné */
function frais_num($v) {
    $v = trim((string)$v);
    if ($v === '') return '';
    return (string)round(max(0, (float)str_replace(array(' ', ','), array('', '.'), $v)), 2);
}

add_action('admin_post_frais_save', 'frais_save');
function frais_save() {
    acces_guard(acces_can_edit('frais'));
    check_admin_referer('frais_save');
    update_option('iac_frais_variables', array(
        'taux_change'        => frais_num($_POST['taux_change'] ?? ''),
        'frais_transport'    => frais_num($_POST['frais_transport'] ?? ''),
        'marge_beneficiaire' => frais_num($_POST['marge_beneficiaire'] ?? ''),
        'updated_by'         => get_current_user_id(),
        'updated_at'         => current_time('mysql'),
    ), false);
    wp_safe_redirect(admin_url('admin.php?page=frais-variables&iac_msg=fsaved'));
    exit;
}

function frais_page_section() {
    acces_guard(acces_can_view('frais'));
    $can_edit = acces_can_edit('frais');
    $f = frais_get();
    iac_admin_style();

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Frais variables</h1></div>';
    if (isset($_GET['iac_msg']) && sanitize_key($_GET['iac_msg']) === 'fsaved') {
        echo '<div class="notice notice-success is-dismissible"><p>Frais variables enregistrés.</p></div>';
    }

    echo '<form class="iac-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('frais_save');
    echo '<input type="hidden" name="action" value="frais_save">';
    $dis = $can_edit ? '' : ' disabled';
    echo '<div class="row">';
    echo '<div class="fld"><label>Taux de change <span style="font-weight:400;color:#999">— facultatif</span></label><input type="number" step="0.0001" min="0" name="taux_change" value="' . esc_attr($f['taux_change']) . '"' . $dis . '></div>';
    echo '<div class="fld"><label>Frais de transport ($) <span style="font-weight:400;color:#999">— facultatif</span></label><input type="number" step="0.01" min="0" name="frais_transport" value="' . esc_attr($f['frais_transport']) . '"' . $dis . '></div>';
    echo '</div>';
    echo '<div class="row">';
    echo '<div class="fld"><label>Marge bénéficiaire (DA) <span style="font-weight:400;color:#999">— facultatif, 200 000 DA par défaut</span></label><input type="number" step="0.01" min="0" name="marge_beneficiaire" value="' . esc_attr($f['marge_beneficiaire']) . '"' . $dis . '></div>';
    echo '</div>';
    if ($can_edit) echo '<p style="margin-top:12px"><button type="submit" class="iac-btn">Enregistrer</button></p>';
    echo '</form></div>';
}
