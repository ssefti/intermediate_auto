<?php
/* Template Name: IA — Simulateur douane */
if (!defined('ABSPATH')) exit; get_header();

$vehicles = ia_vehicles_safe(array('orderby' => 'marque', 'order' => 'ASC'));
$js = array();
foreach ($vehicles as $v) {
    $js[] = array(
        'id'    => (int)$v->id,
        'label' => trim(ia_vehicle_title($v) . ' — ' . $v->boite . ($v->version ? ' (' . $v->version . ')' : '')),
        'prix'  => (int)$v->prix,
        'dmin'  => (int)$v->douane_min,
        'dmax'  => (int)$v->douane_max,
        'couleur' => $v->couleur,
    );
}
?>
<style>
.phero{position:relative;background:#0d0d0d;color:#fff;overflow:hidden}
.phero .veil{position:absolute;inset:0;background:radial-gradient(900px 300px at 80% 0,rgba(212,175,55,.25),transparent)}
.phero .wrap{position:relative;padding:64px 26px 54px}
.phero h1{color:#fff;font-size:38px}.phero h1 span{color:var(--gold)}
.phero p{color:#d8d8d8;margin-top:12px;max-width:640px}
.crumb{color:#bbb;font-size:13px;margin-bottom:12px}.crumb b{color:var(--gold2)}
.simwrap{margin-top:-34px;position:relative;z-index:2}
.panel{background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow);overflow:hidden}
.tabs{display:flex;border-bottom:1px solid var(--line)}
.tabs .t{flex:1;text-align:center;padding:18px;font-weight:800;color:#888;cursor:pointer}
.tabs .t.on{color:var(--black);box-shadow:inset 0 -3px 0 var(--gold)}
.bodyg{display:grid;grid-template-columns:1fr 1fr}
.inputs{padding:34px}
.result{padding:34px;background:#121212;color:#fff}
.lab{font-weight:700;font-size:13px;color:#555;margin:16px 0 7px;text-transform:uppercase;letter-spacing:.5px}
.sel{width:100%;border:1px solid var(--line);border-radius:12px;padding:14px;font-size:15px}
.note{font-size:12.5px;color:var(--muted);margin-top:18px;background:var(--bg2);border-radius:10px;padding:12px 14px}
.result h3{color:#fff;font-size:20px;margin-bottom:18px}
.rrow{display:flex;justify-content:space-between;padding:14px 0;border-bottom:1px dashed #3a3a3a;font-size:15px;color:#ddd}
.rrow b{color:#fff;font-size:16px}
.rtot{display:flex;justify-content:space-between;align-items:center;margin-top:20px;background:var(--grad);border-radius:14px;padding:18px 20px}
.rtot span{font-weight:700}.rtot b{font-size:24px;font-weight:800;color:#fff}
.result .btn{width:100%;margin-top:20px}
.tbl{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
.tbl th{background:var(--black);color:#fff;padding:14px;text-align:left;font-size:13px;text-transform:uppercase;letter-spacing:.5px}
.tbl td{padding:13px 14px;border-bottom:1px solid var(--line)}
.tbl tr:nth-child(even) td{background:var(--bg2)}
.tbl .pr{font-weight:800;color:var(--black)}.tbl .dz{color:var(--orange-d);font-weight:700}
@media(max-width:820px){.bodyg{grid-template-columns:1fr}}
</style>

<section class="phero"><div class="veil"></div><div class="wrap">
  <div class="crumb">Accueil <b>›</b> Simulateur de frais de douane</div>
  <span class="badge">Outil en ligne</span>
  <h1>Simulateur de <span>frais de douane</span></h1>
  <p>Estimez en quelques secondes les frais de dédouanement de votre véhicule et votre budget total.</p>
</div></section>

<section style="padding:0 0 40px"><div class="wrap"><div class="simwrap">
  <div class="panel">
    <div class="tabs"><div class="t on">① Par modèle</div></div>
    <div class="bodyg">
      <div class="inputs">
        <div class="lab">Véhicule</div>
        <select class="sel" id="ia_sim_select">
          <?php foreach ($js as $o): ?>
            <option value="<?php echo (int)$o['id']; ?>"><?php echo esc_html($o['label']); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="note">⚠️ Estimation indicative et non contractuelle. Le montant définitif dépend du barème douanier en vigueur au moment du dédouanement.</div>
      </div>
      <div class="result">
        <span class="badge">Résultat de l'estimation</span>
        <h3 id="r_title">—</h3>
        <div class="rrow"><span>Prix du véhicule</span><b id="r_prix">—</b></div>
        <div class="rrow"><span>Frais de douane estimés</span><b id="r_douane">—</b></div>
        <div class="rrow"><span>Couleur(s)</span><b id="r_couleur">—</b></div>
        <div class="rtot"><span>💰 Budget total estimé</span><b id="r_total">—</b></div>
        <a class="btn btn-gold" id="r_cta" href="#" target="_blank" rel="noopener">Demander un devis pour ce véhicule</a>
      </div>
    </div>
  </div>
</div></div></section>

<section class="sec" style="background:var(--bg2);padding:60px 0"><div class="wrap">
  <div class="sec-c"><span class="eyebrow">Grille de référence</span><h2>Nos modèles & frais de douane indicatifs</h2>
  <p>Prix exprimés en ×10 000 DA · frais de douane en millions de centimes (M), à titre indicatif.</p></div>
  <table class="tbl">
    <tr><th>Véhicule</th><th>Boîte</th><th>Couleur</th><th>Prix</th><th>Frais de douane</th></tr>
    <?php foreach ($vehicles as $v): ?>
      <tr><td><?php echo esc_html(trim(ia_vehicle_title($v) . ($v->version ? ' ' . $v->version : ''))); ?></td>
        <td><?php echo esc_html($v->boite); ?></td>
        <td><?php echo esc_html($v->couleur); ?></td>
        <td class="pr"><?php echo (int)$v->prix; ?></td>
        <td class="dz"><?php echo esc_html(ia_douane_label($v)); ?></td></tr>
    <?php endforeach; ?>
  </table>
</div></section>

<script>
(function(){
  var DATA = <?php echo wp_json_encode($js); ?>;
  var waBase = <?php echo wp_json_encode(ia_wa_link('Bonjour, je souhaite un devis pour : ')); ?>;
  var sel=document.getElementById('ia_sim_select');
  function fmtDouane(o){return o.dmin===o.dmax ? o.dmin+' M' : o.dmin+' – '+o.dmax+' M';}
  function fmtBudget(o){var lo=o.prix+o.dmin, hi=o.prix+o.dmax;return lo===hi ? ('≈ '+lo) : ('≈ '+lo+' – '+hi);}
  function update(){
    var id=+sel.value, o=null;
    for(var i=0;i<DATA.length;i++){if(DATA[i].id===id){o=DATA[i];break;}}
    if(!o)return;
    document.getElementById('r_title').textContent=o.label;
    document.getElementById('r_prix').textContent=o.prix+' ×10 000 DA';
    document.getElementById('r_douane').textContent=fmtDouane(o);
    document.getElementById('r_couleur').textContent=o.couleur||'—';
    document.getElementById('r_total').textContent=fmtBudget(o);
    document.getElementById('r_cta').href=waBase+encodeURIComponent(o.label);
  }
  if(sel && DATA.length){ sel.addEventListener('change',update); update(); }
})();
</script>

<?php get_footer(); ?>
