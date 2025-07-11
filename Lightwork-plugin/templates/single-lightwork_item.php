<?php
/**
 * Template per il Custom Post Type "lightwork_item".
 * Mostra titolo, campo ACF "subtitle" e contenuto.
 */
get_header();
?>
<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
        while ( have_posts() ) :
            the_post();
        ?>
            <article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                    <?php if ( function_exists( 'the_field' ) ) : ?>
                        <h2 class="entry-subtitle"><?php the_field( 'subtitle' ); ?></h2>
                    <?php endif; ?>
                </header>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    </main>
</div>
<?php
get_footer();
