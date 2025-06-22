<?php
/**
 * Plugin Name: LightWork WP Plugin
 * Description: Gestione dei Custom Post Types integrata con ACF e REST API.
 * Version: 0.1.1
 * Author: LightWork
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LightWork_WP_Plugin {

    const OPTION_CPTS = 'lightwork_cpts';
    const CRON_HOOK   = 'lightwork_batch_update';

    public function __construct() {
        add_action( 'init', [ $this, 'register_saved_cpts' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( self::CRON_HOOK, [ $this, 'batch_update' ] );
    }

    /**
     * Register saved Custom Post Types on init.
     */
    public function register_saved_cpts() {
        $cpts = get_option( self::OPTION_CPTS, [] );
        foreach ( $cpts as $cpt ) {
            $this->register_cpt( $cpt );
        }
    }

    /**
     * Register a single Custom Post Type.
     *
     * @param array $args Arguments for register_post_type.
     */
    private function register_cpt( array $args ) {
        $slug   = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
        $single = isset( $args['single'] ) ? sanitize_text_field( $args['single'] ) : '';
        $plural = isset( $args['plural'] ) ? sanitize_text_field( $args['plural'] ) : '';

        if ( empty( $slug ) || empty( $single ) || empty( $plural ) ) {
            return;
        }

        $labels = [
            'name'               => $plural,
            'singular_name'      => $single,
            'add_new_item'       => __( 'Add New', 'lightwork-wp-plugin' ) . ' ' . $single,
            'edit_item'          => __( 'Edit', 'lightwork-wp-plugin' ) . ' ' . $single,
            'new_item'           => __( 'New', 'lightwork-wp-plugin' ) . ' ' . $single,
            'view_item'          => __( 'View', 'lightwork-wp-plugin' ) . ' ' . $single,
            'search_items'       => __( 'Search', 'lightwork-wp-plugin' ) . ' ' . $plural,
            'not_found'          => __( 'No items found', 'lightwork-wp-plugin' ),
            'not_found_in_trash' => __( 'No items found in Trash', 'lightwork-wp-plugin' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => [ 'slug' => $slug ],
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
        ];

        register_post_type( $slug, $args );

        $this->register_acf_fields( $slug );
    }

    /**
     * Register ACF fields for a CPT if ACF is available.
     *
     * @param string $slug Post type slug.
     */
    private function register_acf_fields( $slug ) {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'      => 'group_' . $slug,
            'title'    => ucfirst( $slug ) . ' Fields',
            'fields'   => [
                [
                    'key'   => 'field_' . $slug . '_subtitle',
                    'label' => __( 'Subtitle', 'lightwork-wp-plugin' ),
                    'name'  => 'subtitle',
                    'type'  => 'text',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => $slug,
                    ],
                ],
            ],
        ] );
    }

    /**
     * Register admin menu and page.
     */
    public function register_admin_menu() {
        add_menu_page(
            'LightWork',
            'LightWork',
            'manage_options',
            'lightwork-wp-plugin',
            [ $this, 'render_admin_page' ],
            'dashicons-admin-generic'
        );
    }

    /**
     * Render plugin admin page with simple form to create CPTs.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['lw_create_cpt'] ) && check_admin_referer( 'lw_create_cpt_nonce' ) ) {
            $this->handle_form_submission();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LightWork - Create Custom Post Type', 'lightwork-wp-plugin' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'lw_create_cpt_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="lw-slug"><?php esc_html_e( 'Slug', 'lightwork-wp-plugin' ); ?></label></th>
                        <td><input name="lw-slug" id="lw-slug" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lw-single"><?php esc_html_e( 'Singular Label', 'lightwork-wp-plugin' ); ?></label></th>
                        <td><input name="lw-single" id="lw-single" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lw-plural"><?php esc_html_e( 'Plural Label', 'lightwork-wp-plugin' ); ?></label></th>
                        <td><input name="lw-plural" id="lw-plural" type="text" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Create CPT', 'lightwork-wp-plugin' ), 'primary', 'lw_create_cpt' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle CPT creation form submission.
     */
    private function handle_form_submission() {
        $slug   = isset( $_POST['lw-slug'] ) ? sanitize_key( $_POST['lw-slug'] ) : '';
        $single = isset( $_POST['lw-single'] ) ? sanitize_text_field( $_POST['lw-single'] ) : '';
        $plural = isset( $_POST['lw-plural'] ) ? sanitize_text_field( $_POST['lw-plural'] ) : '';

        if ( empty( $slug ) || empty( $single ) || empty( $plural ) ) {
            add_settings_error( 'lightwork', 'invalid', __( 'All fields are required.', 'lightwork-wp-plugin' ) );
            return;
        }

        $cpts   = get_option( self::OPTION_CPTS, [] );
        $cpts[] = [
            'slug'   => $slug,
            'single' => $single,
            'plural' => $plural,
        ];

        update_option( self::OPTION_CPTS, $cpts );
        $this->register_cpt( [ 'slug' => $slug, 'single' => $single, 'plural' => $plural ] );

        add_settings_error( 'lightwork', 'success', __( 'Custom Post Type created.', 'lightwork-wp-plugin' ), 'updated' );
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route( 'lightwork/v1', '/(?P<type>[a-z_-]+)/', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_items' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'type' => [
                    'validate_callback' => 'sanitize_key',
                ],
                'page' => [
                    'validate_callback' => 'absint',
                ],
                'orderby' => [
                    'validate_callback' => function ( $param ) {
                        $allowed = [ 'date', 'title' ];
                        return in_array( $param, $allowed, true );
                    },
                ],
            ],
        ] );
    }

    /**
     * REST API callback to get posts of a CPT.
     */
    public function rest_get_items( WP_REST_Request $request ) {
        $post_type = $request->get_param( 'type' );
        $args      = [
            'post_type'      => $post_type,
            'posts_per_page' => 10,
            'paged'          => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
            'orderby'        => $request->get_param( 'orderby' ) ?: 'date',
        ];

        $query = new WP_Query( $args );
        $data  = [];

        foreach ( $query->posts as $post ) {
            $item          = [
                'ID'    => $post->ID,
                'title' => get_the_title( $post ),
                'link'  => get_permalink( $post ),
            ];
            if ( function_exists( 'get_field' ) ) {
                $item['subtitle'] = get_field( 'subtitle', $post->ID );
            }
            $data[] = $item;
        }

        return rest_ensure_response( $data );
    }

    /**
     * Example batch update using WP Cron.
     */
    public function batch_update() {
        $cpts = get_option( self::OPTION_CPTS, [] );
        foreach ( $cpts as $cpt ) {
            $args  = [
                'post_type'      => $cpt['slug'],
                'posts_per_page' => 100,
                'paged'          => 1,
            ];
            $query = new WP_Query( $args );
            while ( $query->have_posts() ) {
                $query->the_post();
                if ( function_exists( 'update_field' ) ) {
                    update_field( 'subtitle', 'Updated via cron', get_the_ID() );
                }
            }
            wp_reset_postdata();
        }
    }

    /**
     * Activate the plugin.
     */
    public static function activate() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}

// Initialize the plugin.
$lightwork_plugin = new LightWork_WP_Plugin();

register_activation_hook( __FILE__, [ 'LightWork_WP_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'LightWork_WP_Plugin', 'deactivate' ] );

