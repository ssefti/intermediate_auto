<?php if (!defined('ABSPATH')) exit; ?>

<footer class="site"><div class="wrap">
  <div class="cols">
    <div>
      <img class="logo" src="<?php echo esc_url(ia_img('Logo_intermediate_auto_black.jpeg')); ?>" alt="Intermediate Auto">
      <p style="font-size:14px;max-width:280px">Intermédiaire en importation automobile en Algérie. Conseil, devis et accompagnement complet depuis 2026.</p>
    </div>
    <div>
      <h4>Navigation</h4>
      <a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a>
      <a href="<?php echo esc_url(ia_url('vehicules')); ?>">Véhicules</a>
      <a href="<?php echo esc_url(ia_url('simulateur')); ?>">Simulateur douane</a>
      <a href="<?php echo esc_url(ia_url('a-propos')); ?>">À propos</a>
      <a href="<?php echo esc_url(ia_url('contact')); ?>">Contact</a>
    </div>
    <div>
      <h4>Nos marques</h4>
      <?php
      $f_marques = function_exists('iac_marques') ? iac_marques() : array();
      foreach ($f_marques as $fm):
        if ($fm === '' || strtolower($fm) === 'autre') continue; ?>
        <a href="<?php echo esc_url(add_query_arg('marque', rawurlencode($fm), ia_url('vehicules'))); ?>"><?php echo esc_html($fm); ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Catégories</h4>
      <?php
      $f_types = function_exists('ia_carrosseries_in_use') ? ia_carrosseries_in_use() : array();
      foreach ($f_types as $ft):
        if ($ft === '') continue; ?>
        <a href="<?php echo esc_url(add_query_arg('type', rawurlencode($ft), ia_url('vehicules'))); ?>"><?php echo esc_html($ft); ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Contact</h4>
      <a>📍 <?php echo esc_html(IA_ADDRESS); ?></a>
      <a href="tel:<?php echo esc_attr(preg_replace('/\s+/','',IA_PHONE)); ?>">📞 <?php echo esc_html(IA_PHONE); ?></a>
      <a href="mailto:<?php echo esc_attr(IA_EMAIL); ?>">✉ <?php echo esc_html(IA_EMAIL); ?></a>
    </div>
  </div>
  <div class="copy">© <?php echo date('Y'); ?> Intermediate Auto · Site hébergé en Algérie · Mentions légales · Politique de confidentialité</div>
</div></footer>

<a class="wa-float" href="<?php echo esc_url(ia_wa_link()); ?>" target="_blank" rel="noopener" aria-label="Contacter sur WhatsApp">
  <svg viewBox="0 0 32 32" width="34" height="34" fill="#fff" aria-hidden="true"><path d="M16.04 4C9.96 4 5.02 8.94 5.02 15.02c0 1.94.51 3.84 1.48 5.51L5 27l6.64-1.74a11 11 0 0 0 4.4.92h.01c6.08 0 11.02-4.94 11.02-11.02C27.07 8.94 22.12 4 16.04 4zm0 20.13h-.01a9.1 9.1 0 0 1-4.64-1.27l-.33-.2-3.94 1.03 1.05-3.84-.22-.34a9.07 9.07 0 0 1-1.39-4.82c0-5.02 4.09-9.11 9.12-9.11 2.44 0 4.73.95 6.45 2.67a9.06 9.06 0 0 1 2.67 6.45c0 5.03-4.09 9.12-9.11 9.12zm5-6.82c-.27-.14-1.62-.8-1.87-.89-.25-.09-.43-.14-.62.14-.18.27-.71.89-.87 1.07-.16.18-.32.2-.59.07-.27-.14-1.16-.43-2.2-1.36-.81-.72-1.36-1.62-1.52-1.89-.16-.27-.02-.42.12-.55.12-.12.27-.32.41-.48.14-.16.18-.27.27-.46.09-.18.05-.34-.02-.48-.07-.14-.62-1.49-.85-2.04-.22-.53-.45-.46-.62-.47-.16-.01-.34-.01-.53-.01-.18 0-.48.07-.73.34-.25.27-.96.94-.96 2.29 0 1.35.98 2.66 1.12 2.84.14.18 1.93 2.95 4.68 4.13.65.28 1.16.45 1.56.58.65.21 1.25.18 1.72.11.52-.08 1.62-.66 1.85-1.3.23-.64.23-1.18.16-1.3-.07-.12-.25-.18-.52-.32z"/></svg>
</a>

<script>
(function(){
  var t=document.querySelector('.nav-toggle'),
      h=document.querySelector('header.nav');
  if(!t||!h) return;
  t.addEventListener('click',function(){
    var open=h.classList.toggle('menu-open');
    t.setAttribute('aria-expanded',open?'true':'false');
    t.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');
  });
  // Ferme le menu quand on clique un lien
  h.querySelectorAll('#primary-nav a').forEach(function(a){
    a.addEventListener('click',function(){
      h.classList.remove('menu-open');
      t.setAttribute('aria-expanded','false');
    });
  });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
