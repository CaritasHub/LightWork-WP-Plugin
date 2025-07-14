<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LightWork_Template_Editor {
    const OPTION_PREFIX = 'lw_template_map_';

    public function register_page() {
        add_submenu_page(
            null,
            __( 'Template Editor', 'lightwork-wp-plugin' ),
            __( 'Template Editor', 'lightwork-wp-plugin' ),
            'manage_options',
            'lightwork-template-editor',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
        $url  = admin_url( 'admin.php?page=lightwork-sandbox-editor' );
        if ( $slug ) {
            $url = add_query_arg( 'slug', $slug, $url );
        }
        wp_safe_redirect( $url );
        exit;
    }
}
