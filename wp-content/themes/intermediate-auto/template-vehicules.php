<?php
/* Template Name: Intermediate Auto — Véhicules (catalogue) */
if (!defined('ABSPATH')) exit; get_header();

$marque = isset($_GET['marque']) ? sanitize_text_field($_GET['marque']) : '';
$type   = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$vehicles = ia_vehicles_safe(array('marque' => $marque, 'carrosserie' => $type, 'orderby' => 'prix', 'order' => 'ASC'));
$all_marques = function_exists('ia_marques_in_use') ? ia_marques_in_use() : array();
$all_types   = function_exists('ia_carrosseries_in_use') ? ia_carrosseries_in_use() : array();
$base = get_permalink();
?>
<style>
.phero{position:relative;background:#0d0d0d;color:#fff;overflow:hidden}
.phero .veil{position:absolute;inset:0;background:radial-gradient(900px 320px at 80% 0,rgba(212,175,55,.25),transparent)}
.phero .wrap{position:relative;padding:64px 26px 54px}
.phero h1{color:#fff;font-size:38px}.phero h1 span{color:var(--gold)}
.phero p{color:#d8d8d8;margin-top:12px;max-width:640px}
.crumb{color:#bbb;font-size:13px;margin-bottom:12px}.crumb b{color:var(--gold2)}
.filters{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:34px}
.filters a{background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 18px;font-weight:600;color:#555;font-size:14px;text-decoration:none}
.filters a.on{background:var(--black);color:#fff;border-color:var(--black)}
.filters a.t{padding:7px 15px;font-size:13px}
.filters a.t.on{background:var(--grad);color:#fff;border-color:transparent}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;transition:transform .28s,box-shadow .28s,border-color .28s}
.card:hover{box-shadow:var(--shadow);border-color:var(--gold);transform:translateY(-8px)}
.card .ph{height:190px;background:#f3f3f3 center/contain no-repeat;transition:transform .55s}
.card:hover .ph{transform:scale(1.06)}
.card .bd{padding:18px 20px 22px}
.card .tag{font-size:12px;color:var(--orange-d);font-weight:700}
.card h3{font-size:19px;margin:4px 0 2px}
.card .specs{font-size:13px;color:var(--muted);margin-bottom:10px}
.card .meta{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:12px}
.card .price{font-size:22px;font-weight:800;color:var(--black)}.card .price small{font-size:12px;color:var(--muted);font-weight:600}
.card .dz{font-size:12px;color:var(--orange-d);font-weight:700}
.card .st{display:inline-block;font-size:11px;font-weight:700;border-radius:99px;padding:3px 10px}
.st.ok{background:#e6f4ea;color:#1a7a3c}.st.cmd{background:#fff4e0;color:#b9770e}.st.sold{background:#fde8e8;color:#b23b3b}
.card .det{display:block;margin-top:12px;text-align:center;background:var(--bg2);border-radius:10px;padding:10px;font-weight:700;color:var(--black);text-decoration:none}
.card:hover .det{background:var(--grad);color:#fff}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>

<section class="phero"><div class="veil"></div><div class="wrap">
  <div class="crumb">Accueil <b>›</b> Véhicules</div>
  <span class="badge">Notre catalogue</span>
  <h1>Nos <span>véhicules</span> disponibles</h1>
  <p>Des modèles neufs prêts à être importés et dédouanés. Filtrez par marque et lancez le simulateur de frais de douane.</p>
</div></section>

<section class="sec"><div class="wrap">
  <div class="filters">
    <a class="<?php echo $marque===''?'on':''; ?>" href="<?php echo esc_url(add_query_arg(array_filter(array('type'=>$type)), $base)); ?>">Toutes marques</a>
    <?php foreach ($all_marques as $m): if ($m==='Autre') continue; ?>
      <a class="<?php echo $marque===$m?'on':''; ?>" href="<?php echo esc_url(add_query_arg(array_filter(array('marque'=>$m,'type'=>$type)), $base)); ?>"><?php echo esc_html($m); ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($all_types): ?>
  <div class="filters" style="margin-top:-18px">
    <a class="t <?php echo $type===''?'on':''; ?>" href="<?php echo esc_url(add_query_arg(array_filter(array('marque'=>$marque)), $base)); ?>">Tous types</a>
    <?php foreach ($all_types as $t): ?>
      <a class="t <?php echo $type===$t?'on':''; ?>" href="<?php echo esc_url(add_query_arg(array_filter(array('marque'=>$marque,'type'=>$t)), $base)); ?>"><?php echo esc_html($t); ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid">
    <?php if ($vehicles): foreach ($vehicles as $v):
        $pill = $v->statut==='Disponible' ? 'ok' : ($v->statut==='Vendu' ? 'sold' : 'cmd'); ?>
      <div class="card">
        <div class="ph" style="background-image:url(<?php echo esc_url(ia_vehicle_image($v)); ?>)"></div>
        <div class="bd">
          <div class="tag"><?php echo esc_html($v->version ?: $v->boite); ?> <span class="st <?php echo $pill; ?>"><?php echo esc_html($v->statut); ?></span></div>
          <h3><?php echo esc_html(ia_vehicle_title($v)); ?></h3>
          <div class="specs"><?php echo esc_html(trim(($v->carrosserie ? $v->carrosserie.' · ' : '') . $v->boite . ' · ' . $v->carburant, ' ·')); ?></div>
          <div class="meta">
            <div class="price"><?php echo number_format((int)$v->prix * 10000, 0, ',', ' '); ?> <small>DA</small></div>
            <div class="dz">Douane : <?php echo esc_html(ia_douane_label($v)); ?></div>
          </div>
          <a class="det" href="<?php echo esc_url(ia_vehicle_url($v)); ?>">Voir le détail</a>
        </div>
      </div>
    <?php endforeach; else: ?>
      <p>Aucun véhicule pour ce filtre.</p>
    <?php endif; ?>
  </div>
</div></section>

<?php get_footer(); ?>
