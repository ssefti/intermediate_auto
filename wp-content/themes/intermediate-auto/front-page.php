<?php if (!defined('ABSPATH')) exit; get_header(); ?>
<style>
.hero{position:relative;background:#0d0d0d;color:#fff;overflow:hidden}
.hero .bg{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.38;animation:kenburns 16s ease-in-out infinite alternate}
.hero .veil{position:absolute;inset:0;background:linear-gradient(90deg,rgba(13,13,13,.92) 30%,rgba(13,13,13,.4))}
.hero .wrap{position:relative;padding:96px 26px 86px}
.hero h1{color:#fff;font-size:50px;max-width:760px}
.hero h1 span{color:var(--gold)}
.hero p{color:#d8d8d8;font-size:19px;margin:20px 0 30px;max-width:560px}
.hero .cta{display:flex;gap:16px;flex-wrap:wrap}
.trust{display:flex;gap:30px;margin-top:42px;flex-wrap:wrap}
.trust div{font-size:14px;color:#eee}.trust b{color:var(--gold2)}
.js .hero .badge,.js .hero h1,.js .hero p,.js .hero .cta,.js .hero .trust{opacity:0;animation:upIn .9s cubic-bezier(.2,.7,.2,1) forwards}
.js .hero h1{animation-delay:.10s}.js .hero p{animation-delay:.26s}.js .hero .cta{animation-delay:.42s}.js .hero .trust{animation-delay:.58s}
.brands{background:var(--bg2);padding:26px 0;border-bottom:1px solid var(--line);overflow:hidden}
.brands .track{display:flex;gap:64px;width:max-content;animation:marquee 24s linear infinite}
.brands:hover .track{animation-play-state:paused}
.brands .b{font-weight:800;font-size:22px;color:#9aa0a6;letter-spacing:1px;flex:0 0 auto}
.brands .b.on{color:var(--orange-d)}
@keyframes marquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;transition:transform .28s,box-shadow .28s,border-color .28s}
.card:hover{box-shadow:var(--shadow);border-color:var(--gold);transform:translateY(-9px)}
.card .ph{height:160px;background:#f3f3f3 center/contain no-repeat;transition:transform .55s}
.card:hover .ph{transform:scale(1.07)}
.card .bd{padding:16px 18px 20px}
.card .tag{font-size:12px;color:var(--orange-d);font-weight:700}
.card h3{font-size:18px;margin:4px 0 2px}
.card .specs{font-size:12.5px;color:var(--muted);margin-bottom:12px}
.card .price{font-size:22px;font-weight:800;color:var(--black)}
.card .price small{font-size:12px;color:var(--muted);font-weight:600}
.card .det{display:block;margin-top:12px;text-align:center;background:var(--bg2);border-radius:10px;padding:9px;font-weight:700;color:var(--black);text-decoration:none;font-size:14px}
.card:hover .det{background:var(--grad);color:#fff}
.simband{background:#121212;color:#fff;border-radius:24px;padding:46px;display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;position:relative;overflow:hidden}
.simband:before{content:"";position:absolute;right:-80px;top:-80px;width:300px;height:300px;background:var(--grad);filter:blur(80px);opacity:.45}
.simband h2{color:#fff;font-size:32px}.simband p{color:#cfcfcf;margin:14px 0 24px}
.simcard{background:#fff;color:var(--ink);border-radius:16px;padding:22px;box-shadow:var(--shadow)}
.simrow{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px dashed var(--line);font-size:14px}
.simtot{display:flex;justify-content:space-between;margin-top:12px;background:var(--grad);color:#fff;border-radius:10px;padding:12px 16px;font-weight:800}
.svc{display:grid;grid-template-columns:repeat(4,1fr);gap:22px}
.svc .it{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px 22px;text-align:center;transition:transform .28s,box-shadow .28s}
.svc .it:hover{transform:translateY(-7px);box-shadow:var(--shadow)}
.svc .ic{width:60px;height:60px;border-radius:14px;background:var(--grad);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:26px;transition:transform .4s}
.svc .it:hover .ic{transform:rotate(-8deg) scale(1.08)}
.svc h3{font-size:17px;margin-bottom:8px}.svc p{font-size:14px;color:var(--muted)}
.steps{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.steps .st{text-align:center;padding:0 6px}
.steps .n{width:52px;height:52px;border-radius:50%;background:#fff;border:3px solid var(--gold);color:var(--orange-d);font-weight:800;font-size:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;animation:bob 3.4s ease-in-out infinite}
.steps .st:nth-child(2) .n{animation-delay:.25s}.steps .st:nth-child(3) .n{animation-delay:.5s}.steps .st:nth-child(4) .n{animation-delay:.75s}.steps .st:nth-child(5) .n{animation-delay:1s}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(7px)}}
.steps .st h3{font-size:15px}.steps .st p{font-size:13px;color:var(--muted)}
.stats{background:var(--black);border-radius:24px;color:#fff;display:grid;grid-template-columns:repeat(4,1fr);text-align:center;padding:44px 0}
.stats .s b{display:block;font-size:40px;color:var(--gold2);font-weight:800}
.stats .s span{color:#cfcfcf;font-size:14px}.stats .s{border-right:1px solid #333}.stats .s:last-child{border:0}
.tst{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.tst .q{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;transition:transform .28s,box-shadow .28s}
.tst .q:hover{transform:translateY(-6px);box-shadow:var(--shadow)}
.tst .star{color:var(--gold);letter-spacing:2px;margin-bottom:10px}
.tst .q p{font-size:14.5px;color:#444}.tst .who{margin-top:14px;font-weight:700;font-size:14px;color:var(--black)}
.faq{max-width:820px;margin:0 auto}
.faq .q{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px 22px;margin-bottom:12px;display:flex;justify-content:space-between;font-weight:700;color:var(--black)}
.faq .q .p{color:var(--gold);font-size:22px}.faq .q.open{border-color:var(--gold)}
.faq .a{padding:0 22px 18px;color:var(--muted);font-size:14.5px;margin-top:-6px;display:none}
.faq .a.show{display:block}
@media(max-width:980px){.grid,.svc{grid-template-columns:repeat(2,1fr)}.simband{grid-template-columns:1fr}.steps{grid-template-columns:repeat(2,1fr);gap:24px}.stats{grid-template-columns:repeat(2,1fr)}.tst{grid-template-columns:1fr}.hero h1{font-size:34px}}
.scrolldown{position:absolute;left:50%;bottom:22px;transform:translateX(-50%);color:var(--gold2);font-size:24px;animation:bounceDown 1.8s ease-in-out infinite}
@keyframes bounceDown{0%,100%{transform:translateX(-50%) translateY(0);opacity:.9}50%{transform:translateX(-50%) translateY(10px);opacity:.4}}
/* Véhicules en vedette */
.featured-sec{background:linear-gradient(180deg,#fff,#fbf6ea)}
.fgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:26px}
.fcard{position:relative;background:#fff;border:1px solid #efe3c2;border-radius:20px;overflow:hidden;box-shadow:0 16px 40px rgba(212,175,55,.14);transition:transform .28s,box-shadow .28s}
.fcard:hover{transform:translateY(-10px);box-shadow:0 24px 54px rgba(212,175,55,.26)}
.fcard .star{position:absolute;top:14px;left:14px;z-index:2;background:var(--grad);color:#fff;font-weight:800;font-size:12px;letter-spacing:.5px;padding:6px 12px;border-radius:999px;box-shadow:0 6px 14px rgba(224,123,32,.35)}
.fcard .ph{height:200px;background:#f3f3f3 center/contain no-repeat;transition:transform .55s}
.fcard:hover .ph{transform:scale(1.06)}
.fcard .bd{padding:20px 22px 24px}
.fcard .tag{font-size:12px;color:var(--orange-d);font-weight:700}
.fcard h3{font-size:21px;margin:5px 0 4px}
.fcard .specs{font-size:13px;color:var(--muted);margin-bottom:12px}
.fcard .meta{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:14px}
.fcard .price{font-size:24px;font-weight:800;color:var(--black)}.fcard .price small{font-size:12px;color:var(--muted);font-weight:600}
.fcard .dz{font-size:12px;color:var(--orange-d);font-weight:700}
.fcard .det{display:block;text-align:center;background:var(--grad);color:#fff;border-radius:10px;padding:11px;font-weight:700;text-decoration:none}
@media(max-width:980px){.fgrid{grid-template-columns:1fr;max-width:460px;margin:0 auto}}
</style>

<?php
$home_vehicles = ia_vehicles_safe(array('limit' => 4, 'orderby' => 'id', 'order' => 'ASC'));
$featured = ia_vehicles_safe(array('featured' => 1, 'limit' => 3, 'orderby' => 'prix', 'order' => 'DESC'));
if (!$featured) $featured = ia_vehicles_safe(array('limit' => 3, 'orderby' => 'prix', 'order' => 'DESC'));
$brands = array('GEELY','MG','LIVAN','GAC','JETTA','T-ROC','RONGWEI');
?>

<!-- HERO -->
<section class="hero">
  <div class="bg" style="background-image:url(<?php echo esc_url(ia_img('Livan_Gold_Intermediate_auto_cover4.png')); ?>)"></div>
  <div class="veil"></div>
  <div class="wrap">
    <span class="badge">Intermédiaire automobile · Alger</span>
    <h1>Votre intermédiaire de <span>confiance</span> pour importer votre voiture en Algérie</h1>
    <p>Choix du véhicule, devis, expédition et dédouanement : nous vous accompagnons de A à Z, depuis notre showroom à Boufarik.</p>
    <div class="cta">
      <a class="btn btn-gold" href="<?php echo esc_url(ia_url('vehicules')); ?>">Voir nos véhicules</a>
      <a class="btn btn-ghost" href="<?php echo esc_url(ia_url('simulateur')); ?>">Simuler mes frais de douane</a>
    </div>
    <div class="trust">
      <div>✔ <b>Showroom</b> physique à Alger</div>
      <div>✔ Accompagnement <b>A → Z</b></div>
      <div>✔ Marques <b>neuves</b> importées</div>
    </div>
  </div>
  <div class="scrolldown">⌄</div>
</section>

<!-- BRANDS -->
<div class="brands"><div class="track">
  <?php for ($i=0;$i<2;$i++) foreach ($brands as $k=>$b) echo '<span class="b' . ($k===0?' on':'') . '">' . esc_html($b) . '</span>'; ?>
</div></div>

<!-- VEHICULES EN VEDETTE -->
<?php if ($featured): ?>
<section class="sec featured-sec"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">★ Coups de cœur</span><h2>Véhicules en vedette</h2>
  <p>Notre sélection du moment, mise en avant par notre équipe.</p></div>
  <div class="fgrid">
    <?php foreach ($featured as $v):
        $pill = $v->statut==='Disponible' ? 'Disponible' : $v->statut; ?>
      <div class="fcard reveal">
        <span class="star">★ EN VEDETTE</span>
        <div class="ph" style="background-image:url(<?php echo esc_url(ia_vehicle_image($v)); ?>)"></div>
        <div class="bd">
          <div class="tag"><?php echo esc_html(($v->version ?: $v->boite) . ' · ' . $pill); ?></div>
          <h3><?php echo esc_html(ia_vehicle_title($v)); ?></h3>
          <div class="specs"><?php echo esc_html(trim($v->couleur . ' · ' . $v->boite . ' · ' . $v->carburant, ' ·')); ?></div>
          <div class="meta">
            <div class="price"><?php echo (int)$v->prix; ?> <small>Millions</small></div>
            <div class="dz">Douane : <?php echo esc_html(ia_douane_label($v)); ?></div>
          </div>
          <a class="det" href="<?php echo esc_url(ia_vehicle_url($v)); ?>">Voir le détail</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<!-- VEHICULES -->
<section class="sec"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">Notre catalogue</span><h2>Choisissez votre véhicule</h2>
  <p>Une sélection de modèles neufs prêts à être importés et dédouanés.</p></div>
  <div class="grid">
    <?php if ($home_vehicles): foreach ($home_vehicles as $v): ?>
      <div class="card reveal">
        <div class="ph" style="background-image:url(<?php echo esc_url(ia_vehicle_image($v)); ?>)"></div>
        <div class="bd">
          <div class="tag"><?php echo esc_html($v->version ?: $v->boite); ?></div>
          <h3><?php echo esc_html(ia_vehicle_title($v)); ?></h3>
          <div class="specs"><?php echo esc_html(trim($v->couleur . ($v->boite ? ' · ' . $v->boite : ''))); ?></div>
          <div class="price"><?php echo (int)$v->prix; ?> <small>Millions</small></div>
          <a class="det" href="<?php echo esc_url(ia_vehicle_url($v)); ?>">Voir le détail</a>
        </div>
      </div>
    <?php endforeach; else: ?>
      <p>Le catalogue sera bientôt disponible.</p>
    <?php endif; ?>
  </div>
  <div style="text-align:center;margin-top:40px"><a class="btn btn-dark" href="<?php echo esc_url(ia_url('vehicules')); ?>">Voir tout le catalogue</a></div>
</div></section>

<!-- SIMULATEUR BAND -->
<section style="padding:10px 0 78px"><div class="wrap"><div class="simband reveal">
  <div>
    <span class="badge">Nouveau</span>
    <h2>Estimez vos frais de douane en 30 secondes</h2>
    <p>Sélectionnez un modèle ou renseignez les caractéristiques de votre véhicule : notre simulateur vous donne instantanément une estimation des frais de dédouanement et du budget total.</p>
    <a class="btn btn-gold" href="<?php echo esc_url(ia_url('simulateur')); ?>">Lancer le simulateur →</a>
  </div>
  <div class="simcard">
    <div style="font-weight:800;margin-bottom:6px">MG 5 — Automatique</div>
    <div class="simrow"><span>Prix véhicule</span><b>283 Millions</b></div>
    <div class="simrow"><span>Frais de douane estimés</span><b>85 – 95 M</b></div>
    <div class="simtot"><span>Budget total estimé</span><span>≈ 368 – 378</span></div>
  </div>
</div></div></section>

<!-- SERVICES -->
<section class="sec" style="background:var(--bg2)"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">Ce que nous faisons</span><h2>Nos services</h2></div>
  <div class="svc">
    <div class="it reveal"><div class="ic">🔎</div><h3>Recherche véhicule</h3><p>On vous aide à choisir le modèle adapté à votre budget.</p></div>
    <div class="it reveal"><div class="ic">📑</div><h3>Devis & démarches</h3><p>Devis clair, transparent, sans mauvaise surprise.</p></div>
    <div class="it reveal"><div class="ic">🚢</div><h3>Expédition</h3><p>Organisation de l'acheminement de votre véhicule.</p></div>
    <div class="it reveal"><div class="ic">🛡️</div><h3>Douane & immatriculation</h3><p>Accompagnement jusqu'à la mise en circulation.</p></div>
  </div>
</div></section>

<!-- STEPS -->
<section class="sec"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">Simple & transparent</span><h2>Comment ça marche</h2></div>
  <div class="steps">
    <div class="st reveal"><div class="n">1</div><h3>Choix</h3><p>Vous sélectionnez votre véhicule.</p></div>
    <div class="st reveal"><div class="n">2</div><h3>Devis</h3><p>On chiffre prix + frais de douane.</p></div>
    <div class="st reveal"><div class="n">3</div><h3>Commande</h3><p>Validation et lancement.</p></div>
    <div class="st reveal"><div class="n">4</div><h3>Expédition</h3><p>Acheminement du véhicule.</p></div>
    <div class="st reveal"><div class="n">5</div><h3>Dédouanement</h3><p>Livraison clé en main.</p></div>
  </div>
</div></section>

<!-- STATS -->
<section style="padding:10px 0 78px"><div class="wrap"><div class="stats reveal">
  <div class="s"><b data-to="2026">2026</b><span>Année de création</span></div>
  <div class="s"><b>Boufarik</b><span>Notre showroom</span></div>
  <div class="s"><b data-to="7" data-suffix="+">7+</b><span>Marques partenaires</span></div>
  <div class="s"><b data-to="100" data-suffix="%">100%</b><span>Accompagnement A→Z</span></div>
</div></div></section>

<!-- TESTIMONIALS -->
<section class="sec" style="background:var(--bg2)"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">Ils nous font confiance</span><h2>Avis de nos clients</h2></div>
  <div class="tst">
    <div class="q reveal"><div class="star">★★★★★</div><p>« Accompagnement du début à la fin, frais de douane annoncés sans surprise. Je recommande. »</p><div class="who">— Karim B., Alger</div></div>
    <div class="q reveal"><div class="star">★★★★★</div><p>« Le simulateur m'a permis de connaître mon budget réel avant de commander. Très pro. »</p><div class="who">— Sara M., Blida</div></div>
    <div class="q reveal"><div class="star">★★★★★</div><p>« Showroom sérieux, équipe à l'écoute, livraison conforme. »</p><div class="who">— Yacine T., Boumerdès</div></div>
  </div>
</div></section>

<!-- FAQ -->
<section class="sec"><div class="wrap">
  <div class="sec-c reveal"><span class="eyebrow">Questions fréquentes</span><h2>Tout savoir avant d'importer</h2></div>
  <div class="faq">
    <div class="qa"><div class="q open">Comment importer une voiture en Algérie ?<span class="p">–</span></div>
      <div class="a show">Nous vous accompagnons à chaque étape : choix du véhicule, devis, commande, expédition puis dédouanement et immatriculation, depuis notre showroom à Alger.</div></div>
    <div class="qa"><div class="q">Comment sont calculés les frais de douane ?<span class="p">+</span></div>
      <div class="a">Les frais dépendent du modèle, de la motorisation et du barème en vigueur. Notre simulateur vous en donne une estimation immédiate.</div></div>
    <div class="qa"><div class="q">Quels sont les délais de livraison ?<span class="p">+</span></div>
      <div class="a">Ils varient selon le modèle et l'acheminement ; nous vous communiquons une estimation lors du devis.</div></div>
    <div class="qa"><div class="q">Puis-je visiter votre showroom à Alger ?<span class="p">+</span></div>
      <div class="a">Oui, vous êtes les bienvenus à Boufarik pendant nos horaires d'ouverture.</div></div>
  </div>
</div></section>

<script>
(function(){
  // Reveal au scroll
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}})},{threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el,i){el.style.transitionDelay=(Math.min(i,6)*0.06)+'s';io.observe(el);});
  // Compteurs
  function cnt(el){var to=+el.dataset.to,sfx=el.dataset.suffix||'',s=null,d=1400;el.textContent='0'+sfx;function st(t){if(!s)s=t;var p=Math.min((t-s)/d,1);el.textContent=Math.floor(p*to)+sfx;if(p<1)requestAnimationFrame(st);else el.textContent=to+sfx;}requestAnimationFrame(st);}
  var sb=document.querySelector('.stats'),done=false;
  if(sb){var o=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting&&!done){done=true;sb.querySelectorAll('[data-to]').forEach(cnt);o.disconnect();}})},{threshold:.3});o.observe(sb);}
  // FAQ
  document.querySelectorAll('.faq .q').forEach(function(q){q.addEventListener('click',function(){var a=q.nextElementSibling;var open=a.classList.contains('show');document.querySelectorAll('.faq .a').forEach(function(x){x.classList.remove('show');});document.querySelectorAll('.faq .q').forEach(function(x){x.classList.remove('open');x.querySelector('.p').textContent='+';});if(!open){a.classList.add('show');q.classList.add('open');q.querySelector('.p').textContent='–';}});});
})();
</script>

<?php get_footer(); ?>
