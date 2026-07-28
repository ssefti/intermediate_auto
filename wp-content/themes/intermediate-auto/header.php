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
  <div>📞 <a href="<?php echo esc_url(ia_tel_link()); ?>"><?php echo esc_html(IA_PHONE); ?></a> &nbsp;·&nbsp; ✉ <a href="mailto:<?php echo esc_attr(IA_EMAIL); ?>"><?php echo esc_html(IA_EMAIL); ?></a></div>
  <div>📍 <a href="<?php echo esc_url(ia_maps_link()); ?>" target="_blank" rel="noopener"><?php echo esc_html(IA_ADDRESS); ?></a></div>
</div></div>

<header class="nav"><div class="wrap">
  <a href="<?php echo esc_url(home_url('/')); ?>"><img class="logo" src="<?php echo esc_url(ia_img('Logo_intermediate_auto_black.jpeg')); ?>" alt="Intermediate Auto"></a>
  <button class="nav-toggle" aria-label="Ouvrir le menu" aria-controls="primary-nav" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <?php ia_nav(); ?>
  <a class="btn btn-gold nav-cta" href="<?php echo esc_url(ia_url('contact')); ?>">Demander un devis</a>
</div></header>
