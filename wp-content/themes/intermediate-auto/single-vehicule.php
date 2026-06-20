<?php
/* Fiche véhicule détaillée — route /vehicule/{id}/{slug} */
if (!defined('ABSPATH')) exit;

$vid = (int) get_query_var('ia_vehicle');
$v = function_exists('ia_get_vehicle') ? ia_get_vehicle($vid) : null;

get_header();

if (!$v) {
    echo '<section class="sec"><div class="wrap"><h1>Véhicule introuvable</h1><p><a class="btn btn-gold" href="' . esc_url(ia_url('vehicules')) . '">Retour au catalogue</a></p></div></section>';
    get_footer(); return;
}

$meta   = ia_vehicle_meta($v);
$title  = ia_vehicle_title($v);
$gallery = ia_vehicle_gallery($v);                 // ids photos
$vue    = isset($meta['vue_ensemble']) ? array_filter(array_map('intval',$meta['vue_ensemble'])) : array();
$colors = isset($meta['colors']) ? $meta['colors'] : array();

// Couleurs avec URL de leur photo (couleur ↔ photo)
$color_items = array();
foreach ($colors as $c) {
    $pid = isset($c['photo']) ? (int)$c['photo'] : 0;
    $color_items[] = array(
        'nom'   => isset($c['nom']) ? $c['nom'] : '',
        'hex'   => isset($c['hex']) ? $c['hex'] : '',
        'url'   => $pid ? wp_get_attachment_image_url($pid,'large') : '',
        'thumb' => $pid ? wp_get_attachment_image_url($pid,'medium') : '',
    );
}
// Image principale = 1ʳᵉ couleur avec photo, sinon image_id, sinon galerie
$main_url = '';
foreach ($color_items as $ci) { if ($ci['url']) { $main_url = $ci['url']; break; } }
if (!$main_url) $main_url = $v->image_id ? wp_get_attachment_image_url($v->image_id,'large') : ($gallery ? wp_get_attachment_image_url($gallery[0],'large') : ia_vehicle_image($v));
$has_color_photos = (bool) array_filter(wp_list_pluck($color_items,'url'));
$pill = $v->statut==='Disponible' ? 'ok' : ($v->statut==='Vendu' ? 'sold' : 'cmd');

$specs = array(
    'Carrosserie'       => $v->carrosserie,
    'Moteur'            => $v->moteur,
    'Puissance'         => $v->puissance,
    'Couple'            => $v->couple,
    'Transmission'      => $v->boite ?: (isset($meta['transmission_txt'])?$meta['transmission_txt']:''),
    'Accélération 0-100'=> $v->acceleration,
    'Vitesse max'       => $v->vitesse_max,
    'Consommation'      => $v->consommation,
    'Volume coffre'     => $v->volume_coffre,
    'Dimensions'        => $v->dimensions,
    'Empattement'       => isset($meta['empattement']) ? wp_strip_all_tags($meta['empattement']) : '',
    'Agilité'           => isset($meta['agilite']) ? $meta['agilite'] : '',
    'Conduite'          => isset($meta['conduite']) ? $meta['conduite'] : '',
);
?>
<style>
.vhero{background:#0d0d0d;color:#fff;padding:26px 0}
.vhero .crumb{color:#bbb;font-size:13px}.vhero .crumb a{color:#bbb;text-decoration:none}.vhero .crumb b{color:var(--gold2)}
.vtop{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;padding:30px 0 10px}
.vgal .main{background:#fff;border-radius:18px;overflow:hidden;border:1px solid #2a2a2a}
.vgal .main img{width:100%;height:420px;object-fit:contain;background:#fff}
.vthumbs{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.vthumbs img{width:84px;height:60px;object-fit:cover;border-radius:8px;border:2px solid transparent;cursor:pointer;background:#fff}
.vthumbs img.on,.vthumbs img:hover{border-color:var(--gold)}
.vinfo h1{color:#fff;font-size:34px;margin-bottom:6px}
.vinfo .slogan{color:var(--gold2);font-size:16px;margin-bottom:18px;font-style:italic}
.vbadges{display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap}
.vbadges .st{font-size:12px;font-weight:800;border-radius:99px;padding:5px 12px}
.st.ok{background:#173f27;color:#7be0a0}.st.cmd{background:#3f3517;color:#f0c46b}.st.sold{background:#3f1d1d;color:#f0a0a0}
.vprice{display:flex;gap:26px;align-items:flex-end;margin:14px 0 22px;flex-wrap:wrap}
.vprice .p b{display:block;font-size:34px;color:#fff;font-weight:800;line-height:1}
.vprice .p span,.vprice .d span{font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px}
.vprice .d b{display:block;font-size:22px;color:var(--gold2);font-weight:800}
.vcolors{margin:16px 0}
.vcolors .lbl{font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.swatch{display:inline-flex;align-items:center;gap:8px;background:#1f1f1f;border:1px solid #333;border-radius:99px;padding:5px 12px 5px 6px;margin:0 8px 8px 0;font-size:13px}
.swatch i{width:20px;height:20px;border-radius:50%;border:1px solid #555;display:inline-block}
.swatch.clk{cursor:pointer;transition:border-color .15s}
.swatch.clk:hover,.swatch.clk.on{border-color:var(--gold)}
.vcta{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
/* sections */
.vsec{padding:42px 0;border-top:1px solid var(--line)}
.vsec h2{font-size:24px;margin-bottom:20px}
.specgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.specgrid .it{background:var(--bg2);border-radius:12px;padding:16px 18px}
.specgrid .it span{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.specgrid .it b{font-size:15px;color:var(--black)}
.equip{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.equip .col{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px}
.equip .col h3{font-size:16px;color:var(--orange-d);margin-bottom:10px}
.equip .col .c{font-size:14px;color:#444}.equip .col .c ul{margin:0 0 0 18px}.equip .col .c li{margin-bottom:6px}
.vuewrap{background:#fff;border-bottom:1px solid var(--line);padding:18px 0}
.vuehead{font-size:12.5px;font-weight:800;color:var(--orange-d);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
.vue{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
.vue img{width:100%;height:118px;object-fit:cover;border-radius:10px;border:1px solid var(--line);transition:transform .2s,border-color .2s;cursor:pointer}
.vue a:hover img{transform:scale(1.03);border-color:var(--gold)}
@media(max-width:900px){.vue{grid-template-columns:repeat(3,1fr)}.vue img{height:100px}}
@media(max-width:560px){.vue{grid-template-columns:repeat(2,1fr)}}
.prose-desc{font-size:15.5px;color:#444;line-height:1.7;max-width:900px}
.prose-desc mark{background:transparent;color:inherit}
@media(max-width:900px){.vtop{grid-template-columns:1fr}.specgrid,.equip,.vue{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.specgrid,.equip,.vue{grid-template-columns:1fr}.vgal .main img{height:280px}}
</style>

<section class="vhero"><div class="wrap">
  <div class="crumb"><a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a> <b>›</b> <a href="<?php echo esc_url(ia_url('vehicules')); ?>">Véhicules</a> <b>›</b> <?php echo esc_html($title); ?></div>
  <div class="vtop">
    <div class="vgal">
      <div class="main"><img id="vmain" src="<?php echo esc_url($main_url); ?>" alt="<?php echo esc_attr($title); ?>"></div>
      <?php if ($has_color_photos): ?>
      <div class="vthumbs">
        <?php $first=true; foreach ($color_items as $ci): if(!$ci['url']) continue; ?>
          <img class="<?php echo $first?'on':''; ?>" src="<?php echo esc_url($ci['thumb']?:$ci['url']); ?>" data-full="<?php echo esc_url($ci['url']); ?>" title="<?php echo esc_attr($ci['nom']); ?>">
        <?php $first=false; endforeach; ?>
      </div>
      <?php elseif ($gallery): ?>
      <div class="vthumbs">
        <?php foreach ($gallery as $k=>$gid): $u=wp_get_attachment_image_url($gid,'large'); $tu=wp_get_attachment_image_url($gid,'thumbnail'); if(!$u) continue; ?>
          <img class="<?php echo $k===0?'on':''; ?>" src="<?php echo esc_url($tu?:$u); ?>" data-full="<?php echo esc_url($u); ?>">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="vinfo">
      <h1><?php echo esc_html($title); ?></h1>
      <?php if (!empty($v->slogan)): ?><div class="slogan"><?php echo esc_html($v->slogan); ?></div><?php endif; ?>
      <div class="vbadges"><span class="st <?php echo $pill; ?>"><?php echo esc_html($v->statut); ?></span>
        <?php if($v->boite): ?><span class="st" style="background:#222;color:#ddd"><?php echo esc_html($v->boite); ?></span><?php endif; ?>
        <?php if($v->carburant): ?><span class="st" style="background:#222;color:#ddd"><?php echo esc_html($v->carburant); ?></span><?php endif; ?>
      </div>
      <div class="vprice">
        <div class="p"><span>Prix</span><b><?php echo (int)$v->prix; ?> <small style="font-size:13px;color:#aaa">Millions</small></b></div>
        <div class="d"><span>Frais de douane</span><b><?php echo esc_html($v->frais_douane ?: ia_douane_label($v)); ?></b></div>
      </div>
      <?php if ($color_items): ?>
      <div class="vcolors"><div class="lbl">Couleurs disponibles <span style="text-transform:none;color:#888">— cliquez pour changer la photo</span></div>
        <?php foreach ($color_items as $ci): $hex=$ci['hex']?:'#cccccc'; ?>
          <span class="swatch<?php echo $ci['url']?' clk':''; ?>"<?php if($ci['url']) echo ' data-full="'.esc_url($ci['url']).'"'; ?>><i style="background:<?php echo esc_attr($hex); ?>"></i><?php echo esc_html($ci['nom'] ?: $hex); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="vcta">
        <a class="btn btn-gold" href="<?php echo esc_url(ia_wa_link('Bonjour, je souhaite un devis pour la ' . $title . '.')); ?>" target="_blank" rel="noopener">Demander un devis</a>
        <a class="btn btn-ghost" href="<?php echo esc_url(ia_url('simulateur')); ?>">Simuler la douane</a>
      </div>
    </div>
  </div>
</div></section>

<?php if ($vue): ?>
<div class="vuewrap"><div class="wrap">
  <div class="vuehead">Vue d'ensemble</div>
  <div class="vue">
    <?php foreach ($vue as $gid): $u=wp_get_attachment_image_url($gid,'large'); $tu=wp_get_attachment_image_url($gid,'medium'); if(!$u) continue; ?>
      <a href="<?php echo esc_url($u); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($tu?:$u); ?>" alt="<?php echo esc_attr($title); ?>"></a>
    <?php endforeach; ?>
  </div>
</div></div>
<?php endif; ?>

<!-- FICHE TECHNIQUE -->
<section class="vsec"><div class="wrap">
  <h2>Fiche technique</h2>
  <div class="specgrid">
    <?php foreach ($specs as $lbl=>$val): if (trim((string)$val)==='') continue; ?>
      <div class="it"><span><?php echo esc_html($lbl); ?></span><b><?php echo esc_html($val); ?></b></div>
    <?php endforeach; ?>
  </div>
</div></section>

<?php
$equip_blocks = array(
    'Sécurité'   => isset($meta['equip_securite']) ? $meta['equip_securite'] : '',
    'Confort'    => isset($meta['equip_confort']) ? $meta['equip_confort'] : '',
    'Multimédia' => isset($meta['equip_multimedia']) ? $meta['equip_multimedia'] : '',
);
$has_equip = false; foreach ($equip_blocks as $e) if (trim(wp_strip_all_tags($e))!=='') $has_equip = true;
if ($has_equip): ?>
<section class="vsec" style="background:var(--bg2)"><div class="wrap">
  <h2>Équipements</h2>
  <div class="equip">
    <?php foreach ($equip_blocks as $lbl=>$html): if (trim(wp_strip_all_tags($html))==='') continue; ?>
      <div class="col"><h3><?php echo esc_html($lbl); ?></h3><div class="c"><?php echo wp_kses_post($html); ?></div></div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if (trim(wp_strip_all_tags($v->description))!==''): ?>
<section class="vsec"><div class="wrap">
  <h2>Présentation</h2>
  <div class="prose-desc"><?php echo wp_kses_post($v->description); ?></div>
</div></section>
<?php endif; ?>

<section class="vsec" style="text-align:center"><div class="wrap">
  <h2>Intéressé(e) par la <?php echo esc_html($title); ?> ?</h2>
  <p style="color:var(--muted);margin-bottom:20px">Contactez-nous pour un devis et l'estimation de vos frais de douane.</p>
  <a class="btn btn-gold" href="<?php echo esc_url(ia_wa_link('Bonjour, je souhaite un devis pour la ' . $title . '.')); ?>" target="_blank" rel="noopener">Demander un devis sur WhatsApp</a>
</div></section>

<script>
(function(){
  var main=document.getElementById('vmain');
  function setMain(url){
    if(!url||!main) return;
    main.src=url;
    document.querySelectorAll('.vthumbs img').forEach(function(x){ x.classList.toggle('on', x.dataset.full===url); });
    document.querySelectorAll('.swatch.clk').forEach(function(x){ x.classList.toggle('on', x.dataset.full===url); });
  }
  document.querySelectorAll('.vthumbs img').forEach(function(t){ t.addEventListener('click',function(){ setMain(t.dataset.full); }); });
  document.querySelectorAll('.swatch.clk').forEach(function(s){ s.addEventListener('click',function(){ setMain(s.dataset.full); }); });
  // état initial : 1ère couleur active
  var first=document.querySelector('.swatch.clk'); if(first) first.classList.add('on');
})();
</script>

<?php get_footer(); ?>
