<?php
/* Template Name: Intermediate Auto — Contact */
if (!defined('ABSPATH')) exit; get_header();
$models = ia_vehicles_safe(array('orderby'=>'marque','order'=>'ASC'));
?>
<style>
.phero{position:relative;background:#0d0d0d;color:#fff;overflow:hidden}
.phero .veil{position:absolute;inset:0;background:radial-gradient(900px 320px at 80% 0,rgba(212,175,55,.25),transparent)}
.phero .wrap{position:relative;padding:64px 26px 54px}
.phero h1{color:#fff;font-size:38px}.phero h1 span{color:var(--gold)}
.phero p{color:#d8d8d8;margin-top:12px;max-width:640px}
.crumb{color:#bbb;font-size:13px;margin-bottom:12px}.crumb b{color:var(--gold2)}
.qc{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:-44px;position:relative;z-index:2}
.qc .c{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px 20px;text-align:center;box-shadow:var(--shadow)}
.qc .ic{width:54px;height:54px;border-radius:14px;background:var(--grad);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:23px}
.qc h3{font-size:15px;margin-bottom:6px}.qc p{font-size:14px;color:var(--muted)}.qc b{color:var(--black);font-size:15px}
.lay{display:grid;grid-template-columns:1.05fr .95fr;gap:34px;align-items:start}
.formcard{background:#fff;border:1px solid var(--line);border-radius:20px;padding:38px;box-shadow:var(--shadow)}
.formcard h2{font-size:26px;margin-bottom:6px}.formcard .sub{color:var(--muted);margin-bottom:24px;font-size:15px}
.f2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:14px}
.field label{display:block;font-size:13px;font-weight:700;color:#555;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:11px;padding:13px;font-size:15px;font-family:inherit}
.infocard{background:#121212;color:#fff;border-radius:20px;padding:32px;margin-bottom:20px}
.infocard h3{color:#fff;font-size:20px;margin-bottom:18px}
.irow{display:flex;gap:14px;padding:13px 0;border-bottom:1px dashed #3a3a3a}.irow:last-child{border:0}
.irow .ic{width:40px;height:40px;border-radius:10px;background:var(--grad);display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:18px}
.irow .tx span{display:block;font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px}.irow .tx b{color:#fff;font-size:15px}
.hours{background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px;box-shadow:var(--shadow)}
.hours h3{font-size:18px;margin-bottom:14px}
.hrow{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--line);font-size:14px}.hrow:last-child{border:0}.hrow b{color:var(--black)}
.hrow .open{color:#1a7a3c;font-weight:700}.hrow .closed{color:#b23b3b;font-weight:700}
.mapbox{position:relative;border-radius:20px;overflow:hidden;box-shadow:var(--shadow);height:380px;background:center/cover;margin-top:14px}
.mapbox .pin{position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);background:var(--black);color:#fff;border-radius:12px;padding:12px 16px;font-size:14px;font-weight:700}
.mapbox .pin small{display:block;color:var(--gold2);font-weight:600;font-size:12px}
@media(max-width:900px){.qc{grid-template-columns:repeat(2,1fr)}.lay{grid-template-columns:1fr}}
</style>

<section class="phero"><div class="veil"></div><div class="wrap">
  <div class="crumb">Accueil <b>›</b> Contact</div>
  <span class="badge">Parlons de votre projet</span>
  <h1>Contactez <span>Intermediate Auto</span></h1>
  <p>Une question, un devis, un véhicule en tête ? Notre équipe vous répond et vous accueille dans notre showroom à Boufarik.</p>
</div></section>

<section style="padding:0 0 18px"><div class="wrap"><div class="qc">
  <div class="c"><div class="ic">📞</div><h3>Téléphone</h3><b><?php echo esc_html(IA_PHONE); ?></b><p>Dim – Jeu, 9h30 – 18h</p></div>
  <div class="c"><div class="ic">✉️</div><h3>E-mail</h3><b style="font-size:13px"><?php echo esc_html(IA_EMAIL); ?></b></div>
  <div class="c"><div class="ic">📍</div><h3>Showroom</h3><b>Boufarik</b><p>Algérie</p></div>
  <div class="c"><div class="ic">💬</div><h3>WhatsApp</h3><b><?php echo esc_html(IA_PHONE); ?></b><p>Réponse rapide</p></div>
</div></div></section>

<section class="sec" style="padding:46px 0"><div class="wrap"><div class="lay">
  <div class="formcard">
    <h2>Envoyez-nous un message</h2>
    <p class="sub">Remplissez le formulaire : il ouvrira WhatsApp avec votre demande pré-remplie pour une réponse rapide.</p>
    <div class="f2">
      <div class="field"><label>Nom complet</label><input type="text" id="c_nom" placeholder="Votre nom"></div>
      <div class="field"><label>Téléphone</label><input type="text" id="c_tel" placeholder="06 00 00 00 00"></div>
    </div>
    <div class="field"><label>Véhicule concerné (facultatif)</label>
      <select id="c_veh"><option value="">— Choisir un modèle —</option>
        <?php foreach ($models as $v): ?><option><?php echo esc_html(trim(ia_vehicle_title($v).' '.$v->version)); ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label>Votre message</label><textarea id="c_msg" rows="5" placeholder="Décrivez votre besoin…"></textarea></div>
    <a class="btn btn-gold" style="width:100%;text-align:center" id="c_send" href="#">Envoyer via WhatsApp</a>
  </div>

  <div>
    <div class="infocard">
      <h3>Nos coordonnées</h3>
      <div class="irow"><div class="ic">📍</div><div class="tx"><span>Showroom</span><b><?php echo esc_html(IA_ADDRESS); ?>, Algérie</b></div></div>
      <div class="irow"><div class="ic">📞</div><div class="tx"><span>Téléphone</span><b><?php echo esc_html(IA_PHONE); ?></b></div></div>
      <div class="irow"><div class="ic">✉️</div><div class="tx"><span>E-mail</span><b><?php echo esc_html(IA_EMAIL); ?></b></div></div>
      <div class="irow"><div class="ic">💬</div><div class="tx"><span>WhatsApp</span><b><?php echo esc_html(IA_PHONE); ?></b></div></div>
    </div>
    <div class="hours">
      <h3>Horaires du showroom</h3>
      <div class="hrow"><span>Dimanche – Jeudi</span><b class="open">9h30 – 18h00</b></div>
      <div class="hrow"><span>Vendredi</span><b style="color:#b9770e;font-weight:700">Sur demande</b></div>
      <div class="hrow"><span>Samedi</span><b class="closed">Fermé</b></div>
    </div>
    <div class="mapbox" style="background-image:linear-gradient(rgba(20,20,20,.25),rgba(20,20,20,.35)),url(<?php echo esc_url(ia_img('MG5_intermediate_auto_coverpage2-1.jpeg')); ?>)">
      <div class="pin">📍 Intermediate Auto<small><?php echo esc_html(IA_ADDRESS); ?></small></div>
    </div>
  </div>
</div></div></section>

<script>
(function(){
  var base=<?php echo wp_json_encode('https://wa.me/' . IA_WHATSAPP . '?text='); ?>;
  document.getElementById('c_send').addEventListener('click',function(e){
    e.preventDefault();
    var nom=document.getElementById('c_nom').value, tel=document.getElementById('c_tel').value,
        veh=document.getElementById('c_veh').value, msg=document.getElementById('c_msg').value;
    var t='Bonjour, je suis '+(nom||'(nom)')+'.';
    if(veh) t+=' Véhicule : '+veh+'.';
    if(msg) t+=' '+msg;
    if(tel) t+=' Tél : '+tel;
    window.open(base+encodeURIComponent(t),'_blank');
  });
})();
</script>

<?php get_footer(); ?>
