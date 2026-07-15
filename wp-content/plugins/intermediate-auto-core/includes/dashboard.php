<?php
/**
 * Tableau de bord d'administration : chiffre d'affaires, commandes, clients,
 * encaissements… avec un filtre par intervalle de dates.
 */
if (!defined('ABSPATH')) exit;

/** La table existe-t-elle ? */
function dashboard_table_exists($t) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) === $t;
}

/** Formatage monétaire (réutilise celui des commandes si présent) */
function dashboard_money($n) {
    if (function_exists('commande_money')) return commande_money($n);
    return number_format((float)$n, 2, ',', ' ') . ' DA';
}

/* ============================================================
 *  PAGE : Tableau de bord
 * ============================================================ */
function dashboard_page() {
    acces_guard(acces_has('dashboard_view'));
    global $wpdb;
    iac_admin_style();

    // ---- Intervalle de dates ----
    $today = current_time('Y-m-d');
    $from  = (isset($_GET['from']) && $_GET['from']) ? sanitize_text_field($_GET['from']) : current_time('Y') . '-01-01';
    $to    = (isset($_GET['to'])   && $_GET['to'])   ? sanitize_text_field($_GET['to'])   : $today;

    $ct = function_exists('commandes_table') ? commandes_table() : '';
    $at = function_exists('avances_table')   ? avances_table()   : '';
    $clt = function_exists('iac_clients_table') ? iac_clients_table() : '';
    $vt = function_exists('iac_table') ? iac_table() : '';

    $has_cmd = $ct && dashboard_table_exists($ct);
    $has_av  = $at && dashboard_table_exists($at);
    $has_cl  = $clt && dashboard_table_exists($clt);
    $has_vh  = $vt && dashboard_table_exists($vt);

    // ---- KPI principaux (sur la période) ----
    $ca = $nb_cmd = $enc = $reste_total = 0;
    $clients_periode = 0;
    if ($has_cmd) {
        $ca = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(prix),0) FROM {$ct} WHERE statut <> %s AND date_commande BETWEEN %s AND %s", 'Annulée', $from, $to));
        $nb_cmd = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ct} WHERE date_commande BETWEEN %s AND %s", $from, $to));
        $clients_periode = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT client_id) FROM {$ct} WHERE client_id>0 AND date_commande BETWEEN %s AND %s", $from, $to));
        // Reste à encaisser (commandes non annulées de la période)
        $cmds = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$ct} WHERE statut <> %s AND date_commande BETWEEN %s AND %s", 'Annulée', $from, $to));
        if (function_exists('commande_reste')) foreach ($cmds as $cm) $reste_total += commande_reste($cm);
    }
    if ($has_av) {
        $enc = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(montant),0) FROM {$at} WHERE statut=%s AND date_avance BETWEEN %s AND %s", 'Encaissée', $from, $to));
    }
    $clients_actifs = $has_cl ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$clt} WHERE active=1") : 0;

    // ---- Filtre de dates (formulaire + raccourcis) ----
    $base = admin_url('admin.php?page=intermediate-auto');
    $preset = function($label, $f, $t) use ($base, $from, $to) {
        $active = ($from === $f && $to === $t) ? 'button-primary' : 'button';
        return '<a class="button ' . $active . '" href="' . esc_url(add_query_arg(array('from' => $f, 'to' => $t), $base)) . '">' . esc_html($label) . '</a> ';
    };
    $mois_debut   = current_time('Y-m') . '-01';
    $annee_debut  = current_time('Y') . '-01-01';
    $ml_end       = date('Y-m-d', strtotime($mois_debut . ' -1 day'));
    $ml_start     = date('Y-m-01', strtotime($ml_end));

    echo '<div class="wrap iac-wrap">';
    echo '<div class="iac-head"><h1>Tableau de bord</h1></div>';

    echo '<form method="get" style="background:#fff;border:1px solid #e2e4e9;border-radius:10px;padding:14px 16px;margin-bottom:22px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">';
    echo '<input type="hidden" name="page" value="intermediate-auto">';
    echo '<strong>Période :</strong> du <input type="date" name="from" value="' . esc_attr($from) . '"> ';
    echo 'au <input type="date" name="to" value="' . esc_attr($to) . '"> ';
    echo '<button class="button button-primary">Filtrer</button>';
    echo '<span style="margin-left:auto"></span>';
    echo $preset('Ce mois', $mois_debut, $today);
    echo $preset('Mois dernier', $ml_start, $ml_end);
    echo $preset('Cette année', $annee_debut, $today);
    echo $preset('Tout', '2000-01-01', $today);
    echo '</form>';

    // ---- Cartes KPI ----
    echo '<div class="iac-cards" style="grid-template-columns:repeat(3,1fr)">';
    printf('<div class="iac-card"><div class="n">%s</div><div class="l">Chiffre d\'affaires (commandes)</div></div>', esc_html(dashboard_money($ca)));
    printf('<div class="iac-card"><div class="n">%s</div><div class="l">Encaissé (avances)</div></div>', esc_html(dashboard_money($enc)));
    printf('<div class="iac-card" style="border-left-color:#E07B20"><div class="n">%s</div><div class="l">Reste à encaisser</div></div>', esc_html(dashboard_money($reste_total)));
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Commandes (période)</div></div>', $nb_cmd);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Clients actifs</div></div>', $clients_actifs);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Clients ayant commandé</div></div>', $clients_periode);
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:1.3fr 1fr;gap:22px;margin-top:8px">';

    // ---- CA par client (top 10) ----
    echo '<div class="iac-card" style="padding:18px">';
    echo '<h2 style="font-size:15px;margin:0 0 12px">Chiffre d\'affaires par client</h2>';
    if ($has_cmd) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT client_id, SUM(prix) total, COUNT(*) n FROM {$ct} WHERE statut <> %s AND date_commande BETWEEN %s AND %s GROUP BY client_id ORDER BY total DESC LIMIT 10",
            'Annulée', $from, $to));
        if ($rows) {
            echo '<table class="wp-list-table widefat striped"><thead><tr><th>Client</th><th>Commandes</th><th>CA</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $name = '—';
                if ($r->client_id && function_exists('iac_get_client')) { $cl = iac_get_client($r->client_id); if ($cl) $name = iac_client_name($cl); }
                echo '<tr><td>' . esc_html($name) . '</td><td>' . (int)$r->n . '</td><td><strong>' . esc_html(dashboard_money($r->total)) . '</strong></td></tr>';
            }
            echo '</tbody></table>';
        } else echo '<p style="color:#777">Aucune commande sur la période.</p>';
    } else echo '<p style="color:#777">Module commandes indisponible.</p>';
    echo '</div>';

    // ---- Commandes par statut + Top véhicules ----
    echo '<div>';
    echo '<div class="iac-card" style="padding:18px;margin-bottom:22px">';
    echo '<h2 style="font-size:15px;margin:0 0 12px">Commandes par statut</h2>';
    if ($has_cmd) {
        $st = $wpdb->get_results($wpdb->prepare("SELECT statut, COUNT(*) n, COALESCE(SUM(prix),0) total FROM {$ct} WHERE date_commande BETWEEN %s AND %s GROUP BY statut", $from, $to));
        if ($st) {
            echo '<table class="wp-list-table widefat striped"><tbody>';
            foreach ($st as $r) echo '<tr><td>' . esc_html($r->statut) . '</td><td>' . (int)$r->n . '</td><td>' . esc_html(dashboard_money($r->total)) . '</td></tr>';
            echo '</tbody></table>';
        } else echo '<p style="color:#777">—</p>';
    }
    echo '</div>';

    echo '<div class="iac-card" style="padding:18px">';
    echo '<h2 style="font-size:15px;margin:0 0 12px">Véhicules les plus commandés</h2>';
    if ($has_cmd) {
        $tv = $wpdb->get_results($wpdb->prepare("SELECT vehicule_id, COUNT(*) n FROM {$ct} WHERE vehicule_id>0 AND date_commande BETWEEN %s AND %s GROUP BY vehicule_id ORDER BY n DESC LIMIT 5", $from, $to));
        if ($tv) {
            echo '<table class="wp-list-table widefat striped"><tbody>';
            foreach ($tv as $r) {
                $vname = '—';
                if (function_exists('ia_get_vehicle')) { $vv = ia_get_vehicle($r->vehicule_id); if ($vv) $vname = ia_vehicle_title($vv); }
                echo '<tr><td>' . esc_html($vname) . '</td><td><strong>' . (int)$r->n . '</strong></td></tr>';
            }
            echo '</tbody></table>';
        } else echo '<p style="color:#777">Aucune commande sur la période.</p>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // grid

    // ---- Évolution mensuelle du CA ----
    if ($has_cmd) {
        $mois = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(date_commande,'%%Y-%%m') ym, COALESCE(SUM(prix),0) total, COUNT(*) n FROM {$ct} WHERE statut <> %s AND date_commande BETWEEN %s AND %s GROUP BY ym ORDER BY ym",
            'Annulée', $from, $to));
        if ($mois) {
            $maxv = 0; foreach ($mois as $m) $maxv = max($maxv, (float)$m->total);
            echo '<div class="iac-card" style="padding:18px;margin-top:22px">';
            echo '<h2 style="font-size:15px;margin:0 0 14px">Évolution mensuelle du chiffre d\'affaires</h2>';
            foreach ($mois as $m) {
                $w = $maxv > 0 ? round((float)$m->total / $maxv * 100) : 0;
                echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">';
                echo '<div style="width:80px;color:#555;font-size:13px">' . esc_html($m->ym) . '</div>';
                echo '<div style="flex:1;background:#f0f0f0;border-radius:6px;overflow:hidden"><div style="width:' . $w . '%;min-width:2px;height:22px;background:linear-gradient(135deg,#D4AF37,#E07B20)"></div></div>';
                echo '<div style="width:170px;text-align:right;font-size:13px"><strong>' . esc_html(dashboard_money($m->total)) . '</strong> <span style="color:#999">(' . (int)$m->n . ')</span></div>';
                echo '</div>';
            }
            echo '</div>';
        }
    }

    // ---- Stats secondaires (catalogue + avances en attente) ----
    $av_attente = $has_av ? (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(montant),0) FROM {$at} WHERE statut=%s", 'En attente')) : 0;
    $veh_total  = $has_vh ? (int)$wpdb->get_var("SELECT COUNT(*) FROM {$vt}") : 0;
    $veh_dispo  = $has_vh ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$vt} WHERE statut=%s", 'Disponible')) : 0;
    echo '<div class="iac-cards" style="grid-template-columns:repeat(3,1fr);margin-top:22px">';
    printf('<div class="iac-card"><div class="n">%s</div><div class="l">Avances en attente (à encaisser)</div></div>', esc_html(dashboard_money($av_attente)));
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Véhicules au catalogue</div></div>', $veh_total);
    printf('<div class="iac-card"><div class="n">%d</div><div class="l">Véhicules disponibles</div></div>', $veh_dispo);
    echo '</div>';

    echo '<p style="color:#777;margin-top:16px">Période analysée : <strong>' . esc_html($from) . '</strong> → <strong>' . esc_html($to) . '</strong>. Les indicateurs « Clients actifs » et « Catalogue » sont à jour (hors période).</p>';
    echo '</div>';
}
