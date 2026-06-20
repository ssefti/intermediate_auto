<?php
/* Template Name: IA — À propos */
if (!defined('ABSPATH')) exit; get_header(); ?>
<style>
.phero{position:relative;background:#0d0d0d;color:#fff;overflow:hidden}
.phero .bg{position:absolute;inset:0;background:center/cover;opacity:.34}
.phero .veil{position:absolute;inset:0;background:linear-gradient(90deg,rgba(13,13,13,.92),rgba(13,13,13,.55))}
.phero .wrap{position:relative;padding:84px 26px}
.phero h1{color:#fff;font-size:40px;max-width:780px}.phero h1 span{color:var(--gold)}
.phero p{color:#d8d8d8;margin-top:16px;max-width:620px;font-size:17px}
.crumb{color:#bbb;font-size:13px;margin-bottom:14px}.crumb b{color:var(--gold2)}
.lay{display:grid;grid-template-columns:1fr 320px;gap:48px}
.prose h2{font-size:26px;margin:34px 0 12px}.prose h2:first-child{margin-top:0}
.prose p{margin-bottom:14px;font-size:15.5px;color:#444}
.prose ul{margin:0 0 16px 20px}.prose li{margin-bottom:8px;font-size:15.5px;color:#444}
.prose .lead{font-size:18px;color:#222;border-left:4px solid var(--gold);background:var(--bg2);padding:16px 18px;border-radius:0 10px 10px 0}
.prose a{color:var(--orange-d);font-weight:700}
.aside .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:20px}
.aside h4{font-size:16px;margin-bottom:14px}
.aside .r{display:flex;justify-content:space-between;gap:10px;padding:8px 0;font-size:14px;border-bottom:1px dashed var(--line)}
.aside .r b{color:var(--black)}
.aside .cta{background:#121212;color:#fff;text-align:center}.aside .cta h4{color:#fff}.aside .cta p{color:#cfcfcf;font-size:13px;margin-bottom:14px}
.vals{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin:16px 0}
.vals .v{background:var(--bg2);border-radius:12px;padding:18px;font-size:14px;color:#444}
.vals .v b{display:block;color:var(--orange-d);margin-bottom:4px}
.brandgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:14px 0}
.brandgrid .bb{background:var(--bg2);border-radius:12px;text-align:center;padding:16px 8px;font-weight:800;color:#555}
@media(max-width:860px){.lay{grid-template-columns:1fr}.phero h1{font-size:30px}}
</style>

<section class="phero">
  <div class="bg" style="background-image:url(<?php echo esc_url(ia_img('Livan-Intermediate-auto_cover_page1-1.jpeg')); ?>)"></div>
  <div class="veil"></div>
  <div class="wrap">
    <div class="crumb">Accueil <b>›</b> À propos</div>
    <h1>Intermediate Auto — votre <span>intermédiaire automobile</span> de confiance en Algérie</h1>
    <p>Depuis notre showroom à Boufarik, nous accompagnons particuliers et professionnels dans l'achat et l'importation de véhicules neufs, en toute transparence.</p>
  </div>
</section>

<section class="sec"><div class="wrap"><div class="lay">
  <article class="prose">
    <p class="lead">Fondée en 2026 à Boufarik (wilaya de Blida), <b>Intermediate Auto</b> est une société spécialisée dans l'intermédiation et l'accompagnement à l'importation de véhicules neufs en Algérie. Notre mission : rendre l'achat d'une voiture importée simple, clair et sans mauvaise surprise.</p>

    <h2>Qui sommes-nous ?</h2>
    <p>Intermediate Auto est née d'un constat simple : importer une voiture en Algérie peut vite devenir compliqué, entre le choix du modèle, l'estimation des frais de douane, l'expédition et les démarches administratives. Nous avons créé une structure dédiée pour <b>accompagner chaque client de A à Z</b>, de la sélection du véhicule jusqu'à sa mise en circulation.</p>
    <p>Basés à Boufarik, dans la wilaya de Blida, nous mettons à votre disposition notre connaissance du marché automobile et des marques importées (Geely, MG, Livan, GAC, Jetta, T-Roc…), afin de vous orienter vers le véhicule le plus adapté à votre budget et à vos besoins.</p>

    <h2>Notre showroom à Boufarik</h2>
    <p>Contrairement à beaucoup d'intermédiaires en ligne, Intermediate Auto dispose d'un <b>showroom physique à Boufarik</b>. Vous pouvez venir échanger avec notre équipe, découvrir les modèles disponibles et obtenir un conseil personnalisé en face à face. Ce point de contact réel est un gage de sérieux et de proximité pour nos clients algériens.</p>

    <h2>Notre mission : transparence et simplicité</h2>
    <p>Nous pensons que vous devez connaître votre <b>budget réel</b> avant de vous engager. C'est pourquoi nous avons développé un <a href="<?php echo esc_url(ia_url('simulateur')); ?>">simulateur de frais de douane</a> qui vous donne une estimation immédiate du coût de dédouanement de votre véhicule.</p>
    <div class="vals">
      <div class="v"><b>Transparence</b>Des devis clairs, frais de douane annoncés à l'avance.</div>
      <div class="v"><b>Proximité</b>Un showroom à Boufarik (Blida) et une équipe à votre écoute.</div>
      <div class="v"><b>Accompagnement</b>Un suivi complet jusqu'à la livraison du véhicule.</div>
      <div class="v"><b>Expertise</b>Une bonne connaissance des marques importées.</div>
    </div>

    <h2>Nos marques partenaires</h2>
    <div class="brandgrid">
      <div class="bb">GEELY</div><div class="bb">MG</div><div class="bb">LIVAN</div>
      <div class="bb">GAC</div><div class="bb">JETTA</div><div class="bb">T-ROC</div>
    </div>
    <p>Que vous recherchiez une berline compacte comme la <b>MG 5</b>, un SUV urbain comme le <b>Geely Coolray</b> ou le <b>GAC GS3</b>, ou un modèle au meilleur prix comme le <b>Livan X3 Pro</b>, nous vous aidons à comparer et à choisir.</p>

    <h2>Comment nous travaillons</h2>
    <ul>
      <li><b>Le choix du véhicule</b> — nous vous présentons les modèles disponibles et leurs prix.</li>
      <li><b>Le devis</b> — nous chiffrons le prix d'achat ainsi qu'une estimation des frais de douane.</li>
      <li><b>La commande</b> — une fois votre choix validé, nous lançons la procédure.</li>
      <li><b>L'expédition</b> — nous organisons l'acheminement de votre véhicule.</li>
      <li><b>Le dédouanement</b> — nous vous accompagnons jusqu'à la mise en circulation.</li>
    </ul>

    <h2>Pourquoi nous faire confiance ?</h2>
    <p>Choisir Intermediate Auto, c'est bénéficier d'un interlocuteur unique pour tout le parcours d'importation de votre voiture en Algérie. Notre <b>showroom à Boufarik (Blida)</b>, notre transparence sur les frais de douane et notre suivi personnalisé font la différence. Vous êtes les bienvenus dans notre showroom pour découvrir les véhicules et être conseillé, en toute proximité.</p>
  </article>

  <aside class="aside">
    <div class="card">
      <h4>En bref</h4>
      <div class="r"><span>📅 Création</span><b>2026</b></div>
      <div class="r"><span>📍 Showroom</span><b>Boufarik</b></div>
      <div class="r"><span>🏙️ Wilaya</span><b>Blida</b></div>
      <div class="r" style="border:0"><span>🚗 Marques</span><b>7+</b></div>
    </div>
    <div class="card cta">
      <h4>Un projet d'achat ?</h4>
      <p>Obtenez un devis et l'estimation de vos frais de douane.</p>
      <a class="btn btn-gold" style="width:100%" href="<?php echo esc_url(ia_url('contact')); ?>">Demander un devis</a>
      <a class="btn btn-ghost" style="width:100%;margin-top:10px" href="<?php echo esc_url(ia_url('simulateur')); ?>">Simuler la douane</a>
    </div>
  </aside>
</div></div></section>

<?php get_footer(); ?>
