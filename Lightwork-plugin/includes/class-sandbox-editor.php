<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LightWork_Sandbox_Editor {
    const OPTION_NAME = 'lw_sandbox_html';
    const PAGE_OPTION = 'lw_sandbox_page_id';

    public function register_page() {
        add_submenu_page(
            'lightwork-wp-plugin',
            __( 'Sandbox Editor', 'lightwork-wp-plugin' ),
            __( 'Sandbox Editor', 'lightwork-wp-plugin' ),
            'manage_options',
            'lightwork-sandbox-editor',
            [ $this, 'render_page' ]
        );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_lw_save_sandbox', [ $this, 'ajax_save' ] );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'lightwork-sandbox-editor' ) === false ) {
            return;
        }
        wp_enqueue_script(
            'lw-sandbox',
            plugins_url( 'assets/sandbox.js', dirname( __DIR__ ) . '/lightwork-wp-plugin.php' ),
            [ 'jquery' ],
            '0.3.7',
            true
        );
        wp_enqueue_style(
            'lw-sandbox',
            plugins_url( 'assets/sandbox.css', dirname( __DIR__ ) . '/lightwork-wp-plugin.php' ),
            [],
            '0.3.7'
        );
        wp_localize_script( 'lw-sandbox', 'lwSandbox', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'lw_sandbox_save' ),
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $html = get_option( self::OPTION_NAME, '<p>Hello World</p>' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sandbox Editor', 'lightwork-wp-plugin' ); ?></h1>
            <div id="lw-sandbox">
                <div id="lw-preview">
                    <iframe></iframe>
                </div>
                <div id="lw-editors">
                    <textarea id="lw-html" placeholder="HTML"><?php echo esc_textarea( $html ); ?></textarea>
                    <div id="lw-sub">
                        <textarea id="lw-css" placeholder="CSS"></textarea>
                        <textarea id="lw-js" placeholder="JS"></textarea>
                    </div>
                    <p>
                        <button id="lw-run" class="button button-primary"><?php esc_html_e( 'Preview', 'lightwork-wp-plugin' ); ?></button>
                        <button id="lw-save" class="button"><?php esc_html_e( 'Save', 'lightwork-wp-plugin' ); ?></button>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        check_ajax_referer( 'lw_sandbox_save', 'nonce' );
        $html = wp_unslash( $_POST['html'] ?? '' );
        $css  = wp_unslash( $_POST['css'] ?? '' );
        $js   = wp_unslash( $_POST['js'] ?? '' );

        $html = preg_replace( '#</?(head|body)[^>]*>#i', '', $html );
        $html = wp_kses_post( $html );
        $css  = sanitize_textarea_field( $css );
        $js   = sanitize_textarea_field( $js );

        $final = $html;
        if ( $css ) {
            $final .= '<style>' . $css . '</style>';
        }
        if ( $js ) {
            $final .= '<script>' . $js . '</script>';
        }
        update_option( self::OPTION_NAME, $final );

        $page_id = (int) get_option( self::PAGE_OPTION );
        $page_data = [
            'post_title'   => 'Sandbox Template',
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_content' => $final,
            'post_name'    => 'lw-sandbox-template',
        ];
        if ( $page_id && get_post( $page_id ) ) {
            $page_data['ID'] = $page_id;
            wp_update_post( $page_data );
        } else {
            $page_id = wp_insert_post( $page_data );
            update_option( self::PAGE_OPTION, $page_id );
        }

        wp_send_json_success();
    }
}
