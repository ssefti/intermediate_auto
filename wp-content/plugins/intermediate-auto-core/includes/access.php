<?php
/**
 * Gestion des accès par utilisateur.
 * Ajoute un champ « Accès » (choix multiple, obligatoire) sur la fiche
 * utilisateur, et restreint les sections d'administration en conséquence.
 * Les administrateurs disposent automatiquement de tous les accès.
 */
if (!defined('ABSPATH')) exit;

/** Valeurs possibles : clé interne => libellé affiché */
function acces_options() {
    return array(
        'dashboard_view' => 'Affichage du sous-menu Tableau de bord',
        'vehicules_edit' => 'Création / Modification de véhicules',
        'clients_edit'   => 'Création / Modification des clients',
        'clients_view'   => 'Affichage des clients',
        'avances_edit'   => 'Création / Modification des avances',
        'avances_view'   => 'Affichage des avances',
        'commandes_edit' => 'Création / Modification des commandes',
        'commandes_view' => 'Affichage des commandes',
    );
}

/** Accès accordés à un utilisateur (y compris les admins : accès explicites) */
function acces_user($uid = null) {
    $uid = $uid ?: get_current_user_id();
    $v = get_user_meta($uid, 'acces', true);
    return is_array($v) ? $v : array();
}

function acces_has($key, $uid = null)         { return in_array($key, acces_user($uid), true); }
function acces_can_edit($module, $uid = null) { return acces_has($module . '_edit', $uid); }
function acces_can_view($module, $uid = null) { return acces_has($module . '_view', $uid) || acces_has($module . '_edit', $uid); }
function acces_any($uid = null)               { return (bool) acces_user($uid); }

/** Bloque l'accès (403) si la condition n'est pas remplie */
function acces_guard($ok) {
    if (!$ok) wp_die('Accès refusé : vous n’avez pas la permission requise pour cette page.', 'Accès refusé', array('response' => 403));
}

/** URL de la première section autorisée (pour les non-admins) */
function acces_first_section_url() {
    if (acces_can_edit('vehicules')) return admin_url('admin.php?page=vehicules');
    if (acces_can_view('clients'))   return admin_url('admin.php?page=ia-clients');
    if (acces_can_view('avances'))   return admin_url('admin.php?page=avances');
    if (acces_can_view('commandes')) return admin_url('admin.php?page=commandes');
    return '';
}

/** Page d'accueil pour un non-admin : redirige vers sa première section */
function acces_landing() {
    $url = acces_first_section_url();
    echo '<div class="wrap"><h1>Administration</h1>';
    if ($url) {
        echo '<p>Redirection vers votre espace… <a href="' . esc_url($url) . '">Cliquez ici si rien ne se passe.</a></p>';
        echo '<script>location.replace(' . wp_json_encode($url) . ')</script>';
    } else {
        echo '<p>Vous n’avez accès à aucune section. Contactez l’administrateur.</p>';
    }
    echo '</div>';
}

/* ============================================================
 *  CHAMP « ACCÈS » SUR LA FICHE UTILISATEUR
 * ============================================================ */
function acces_render_field($granted) {
    ?>
    <h2>Accès à l’interface d’administration</h2>
    <table class="form-table" role="presentation"><tr>
        <th><label>Accès <span style="color:#d63638">*</span></label></th>
        <td>
            <fieldset>
            <?php foreach (acces_options() as $k => $lbl): ?>
                <label style="display:block;margin:0 0 8px">
                    <input type="checkbox" name="acces[]" value="<?php echo esc_attr($k); ?>" <?php checked(in_array($k, (array)$granted, true)); ?>>
                    <?php echo esc_html($lbl); ?>
                </label>
            <?php endforeach; ?>
            </fieldset>
            <p class="description">Cochez les interfaces autorisées pour cet utilisateur (au moins une obligatoire). S’applique à tous les rôles, administrateurs compris.</p>
        </td>
    </tr></table>
    <?php
}

add_action('show_user_profile', 'acces_user_field');
add_action('edit_user_profile', 'acces_user_field');
function acces_user_field($user) {
    if (!current_user_can('manage_options')) return;
    acces_render_field(get_user_meta($user->ID, 'acces', true));
}

add_action('user_new_form', 'acces_user_new_field');
function acces_user_new_field($type) {
    if (!current_user_can('create_users')) return;
    acces_render_field(array());
}

add_action('personal_options_update', 'acces_user_save');
add_action('edit_user_profile_update', 'acces_user_save');
add_action('user_register', 'acces_user_save');
function acces_user_save($user_id) {
    if (!current_user_can('manage_options') && !current_user_can('create_users')) return;
    $vals = (isset($_POST['acces']) && is_array($_POST['acces'])) ? array_map('sanitize_text_field', $_POST['acces']) : array();
    $vals = array_values(array_intersect($vals, array_keys(acces_options())));
    update_user_meta($user_id, 'acces', $vals);
}

add_action('user_profile_update_errors', 'acces_user_validate', 10, 3);
function acces_user_validate($errors, $update, $user) {
    if (!current_user_can('manage_options') && !current_user_can('create_users')) return;
    $vals = (isset($_POST['acces']) && is_array($_POST['acces'])) ? $_POST['acces'] : array();
    if (empty($vals)) {
        $errors->add('acces_required', '<strong>Erreur :</strong> vous devez sélectionner au moins un accès pour cet utilisateur.');
    }
}

/* ---- Amorçage : les administrateurs existants reçoivent tous les accès (une seule fois) ---- */
add_action('admin_init', 'acces_bootstrap');
function acces_bootstrap() {
    if (get_option('acces_bootstrap_v1')) return;
    $admins = get_users(array('role' => 'administrator', 'fields' => array('ID')));
    foreach ($admins as $a) {
        $cur = get_user_meta($a->ID, 'acces', true);
        if (!is_array($cur) || empty($cur)) {
            update_user_meta($a->ID, 'acces', array_keys(acces_options()));
        }
    }
    update_option('acces_bootstrap_v1', 1);
}
