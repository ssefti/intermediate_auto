<?php if (!defined('ABSPATH')) exit; get_header(); ?>
<main class="sec"><div class="wrap">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article style="max-width:820px;margin:0 auto">
      <h1 style="font-size:34px;margin-bottom:18px"><?php the_title(); ?></h1>
      <div class="prose"><?php the_content(); ?></div>
    </article>
  <?php endwhile; else : ?>
    <h1>Rien à afficher</h1>
  <?php endif; ?>
</div></main>
<?php get_footer(); ?>
