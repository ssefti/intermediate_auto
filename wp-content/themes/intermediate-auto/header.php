<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="topbar"><div class="wrap">
  <div>📞 <?php echo esc_html(IA_PHONE); ?> &nbsp;·&nbsp; ✉ <?php echo esc_html(IA_EMAIL); ?></div>
  <div><a href="#" class="gold">FR</a> | <a href="#">AR</a> &nbsp; 📍 <?php echo esc_html(IA_ADDRESS); ?></div>
</div></div>

<header class="nav"><div class="wrap">
  <a href="<?php echo esc_url(home_url('/')); ?>"><img class="logo" src="<?php echo esc_url(ia_img('Logo_intermediate_auto_black.jpeg')); ?>" alt="Intermediate Auto"></a>
  <button class="nav-toggle" aria-label="Ouvrir le menu" aria-controls="primary-nav" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <?php ia_nav(); ?>
  <a class="btn btn-gold nav-cta" href="<?php echo esc_url(ia_url('contact')); ?>">Demander un devis</a>
</div></header>
