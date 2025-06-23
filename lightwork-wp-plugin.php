<?php
/**
 * Plugin Name: LightWork WP Plugin
 * Description: Gestione dei Custom Post Types integrata con ACF e REST API.
 * Version: 0.2.0
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
        $slug        = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
        $single      = isset( $args['single'] ) ? sanitize_text_field( $args['single'] ) : '';
        $plural      = isset( $args['plural'] ) ? sanitize_text_field( $args['plural'] ) : '';
        $public      = isset( $args['public'] ) ? (bool) $args['public'] : true;
        $has_archive = isset( $args['has_archive'] ) ? (bool) $args['has_archive'] : true;
        $supports    = isset( $args['supports'] ) && is_array( $args['supports'] ) ? array_map( 'sanitize_key', $args['supports'] ) : [ 'title', 'editor', 'thumbnail' ];

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
            'labels'       => $labels,
            'public'       => $public,
            'show_in_rest' => true,
            'has_archive'  => $has_archive,
            'rewrite'      => [ 'slug' => $slug ],
            'supports'     => $supports,
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
     * Render plugin admin page with CPT list and management form.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
        $slug   = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';

        if ( 'delete' === $action && $slug && check_admin_referer( 'lw_delete_cpt_' . $slug ) ) {
            $this->delete_cpt( $slug );
            $action = '';
        }

        if ( isset( $_POST['lw_save_cpt'] ) && check_admin_referer( 'lw_save_cpt_nonce' ) ) {
            $old = isset( $_POST['lw-old-slug'] ) ? sanitize_key( $_POST['lw-old-slug'] ) : null;
            $this->handle_form_submission( $old );
            $action = '';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'LightWork - Manage Custom Post Types', 'lightwork-wp-plugin' ) . '</h1>';
        settings_errors( 'lightwork' );

        if ( 'new' === $action ) {
            $this->render_cpt_form();
        } elseif ( 'edit' === $action && $slug ) {
            $cpts = get_option( self::OPTION_CPTS, [] );
            foreach ( $cpts as $cpt ) {
                if ( $cpt['slug'] === $slug ) {
                    $this->render_cpt_form( $cpt, true );
                    break;
                }
            }
        } else {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=lightwork-wp-plugin&action=new' ) ) . '" class="page-title-action">' . esc_html__( 'Add New CPT', 'lightwork-wp-plugin' ) . '</a>';
            $this->render_cpt_list();
        }

        echo '</div>';
    }

    /**
     * Output list of registered CPTs.
     */
    private function render_cpt_list() {
        $cpts = get_option( self::OPTION_CPTS, [] );
        if ( empty( $cpts ) ) {
            echo '<p>' . esc_html__( 'No Custom Post Types found.', 'lightwork-wp-plugin' ) . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__( 'Slug', 'lightwork-wp-plugin' ) . '</th><th>' . esc_html__( 'Singular', 'lightwork-wp-plugin' ) . '</th><th>' . esc_html__( 'Plural', 'lightwork-wp-plugin' ) . '</th><th>' . esc_html__( 'Actions', 'lightwork-wp-plugin' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $cpts as $cpt ) {
            $edit   = admin_url( 'admin.php?page=lightwork-wp-plugin&action=edit&slug=' . $cpt['slug'] );
            $delete = wp_nonce_url( admin_url( 'admin.php?page=lightwork-wp-plugin&action=delete&slug=' . $cpt['slug'] ), 'lw_delete_cpt_' . $cpt['slug'] );
            echo '<tr>';
            echo '<td>' . esc_html( $cpt['slug'] ) . '</td>';
            echo '<td>' . esc_html( $cpt['single'] ) . '</td>';
            echo '<td>' . esc_html( $cpt['plural'] ) . '</td>';
            echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'lightwork-wp-plugin' ) . '</a> | <a href="' . esc_url( $delete ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure?', 'lightwork-wp-plugin' ) ) . '\');">' . esc_html__( 'Delete', 'lightwork-wp-plugin' ) . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Render CPT creation/edit form.
     *
     * @param array $cpt    Existing CPT args when editing.
     * @param bool  $editing Whether we are editing an existing CPT.
     */
    private function render_cpt_form( array $cpt = [], $editing = false ) {
        $slug        = $cpt['slug'] ?? '';
        $single      = $cpt['single'] ?? '';
        $plural      = $cpt['plural'] ?? '';
        $public      = isset( $cpt['public'] ) ? (bool) $cpt['public'] : true;
        $archive     = isset( $cpt['has_archive'] ) ? (bool) $cpt['has_archive'] : true;
        $supports    = isset( $cpt['supports'] ) && is_array( $cpt['supports'] ) ? $cpt['supports'] : [ 'title', 'editor', 'thumbnail' ];
        $available   = [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ];

        echo '<h2>' . ( $editing ? esc_html__( 'Edit Custom Post Type', 'lightwork-wp-plugin' ) : esc_html__( 'Add Custom Post Type', 'lightwork-wp-plugin' ) ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'lw_save_cpt_nonce' );
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="lw-slug">' . esc_html__( 'Slug', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-slug" id="lw-slug" type="text" class="regular-text" value="' . esc_attr( $slug ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="lw-single">' . esc_html__( 'Singular Label', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-single" id="lw-single" type="text" class="regular-text" value="' . esc_attr( $single ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="lw-plural">' . esc_html__( 'Plural Label', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-plural" id="lw-plural" type="text" class="regular-text" value="' . esc_attr( $plural ) . '" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Supports', 'lightwork-wp-plugin' ) . '</th><td>';
        foreach ( $available as $feature ) {
            echo '<label><input type="checkbox" name="lw-supports[]" value="' . esc_attr( $feature ) . '"' . checked( in_array( $feature, $supports, true ), true, false ) . '/> ' . esc_html( $feature ) . '</label><br />';
        }
        echo '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Public', 'lightwork-wp-plugin' ) . '</th><td><input type="checkbox" name="lw-public" value="1"' . checked( $public, true, false ) . ' /></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Has Archive', 'lightwork-wp-plugin' ) . '</th><td><input type="checkbox" name="lw-archive" value="1"' . checked( $archive, true, false ) . ' /></td></tr>';
        echo '</table>';
        if ( $editing ) {
            echo '<input type="hidden" name="lw-old-slug" value="' . esc_attr( $slug ) . '" />';
        }
        submit_button( $editing ? __( 'Save Changes', 'lightwork-wp-plugin' ) : __( 'Create CPT', 'lightwork-wp-plugin' ), 'primary', 'lw_save_cpt' );
        echo '</form>';
    }

    /**
     * Handle CPT creation or update form submission.
     *
     * @param string|null $old_slug Slug of CPT being edited.
     */
    private function handle_form_submission( $old_slug = null ) {
        $slug     = isset( $_POST['lw-slug'] ) ? sanitize_key( $_POST['lw-slug'] ) : '';
        $single   = isset( $_POST['lw-single'] ) ? sanitize_text_field( $_POST['lw-single'] ) : '';
        $plural   = isset( $_POST['lw-plural'] ) ? sanitize_text_field( $_POST['lw-plural'] ) : '';
        $public   = isset( $_POST['lw-public'] );
        $archive  = isset( $_POST['lw-archive'] );
        $supports = isset( $_POST['lw-supports'] ) ? array_map( 'sanitize_key', (array) $_POST['lw-supports'] ) : [];

        if ( empty( $slug ) || empty( $single ) || empty( $plural ) ) {
            add_settings_error( 'lightwork', 'invalid', __( 'All fields are required.', 'lightwork-wp-plugin' ) );
            return;
        }

        $cpts = get_option( self::OPTION_CPTS, [] );

        if ( $old_slug ) {
            foreach ( $cpts as &$cpt ) {
                if ( $cpt['slug'] === $old_slug ) {
                    $cpt = [
                        'slug'        => $slug,
                        'single'      => $single,
                        'plural'      => $plural,
                        'public'      => $public,
                        'has_archive' => $archive,
                        'supports'    => $supports,
                    ];
                    if ( $old_slug !== $slug ) {
                        global $wpdb;
                        $wpdb->update( $wpdb->posts, [ 'post_type' => $slug ], [ 'post_type' => $old_slug ] );
                    }
                    break;
                }
            }
            unset( $cpt );
            $message = __( 'Custom Post Type updated.', 'lightwork-wp-plugin' );
        } else {
            $cpts[] = [
                'slug'        => $slug,
                'single'      => $single,
                'plural'      => $plural,
                'public'      => $public,
                'has_archive' => $archive,
                'supports'    => $supports,
            ];
            $message = __( 'Custom Post Type created.', 'lightwork-wp-plugin' );
        }

        update_option( self::OPTION_CPTS, $cpts );
        $this->register_cpt( [
            'slug'        => $slug,
            'single'      => $single,
            'plural'      => $plural,
            'public'      => $public,
            'has_archive' => $archive,
            'supports'    => $supports,
        ] );

        add_settings_error( 'lightwork', 'success', $message, 'updated' );
    }

    /**
     * Delete a CPT and unregister it if possible.
     *
     * @param string $slug CPT slug to delete.
     */
    private function delete_cpt( $slug ) {
        $cpts = get_option( self::OPTION_CPTS, [] );
        foreach ( $cpts as $i => $cpt ) {
            if ( $cpt['slug'] === $slug ) {
                unset( $cpts[ $i ] );
                break;
            }
        }
        update_option( self::OPTION_CPTS, array_values( $cpts ) );

        if ( function_exists( 'unregister_post_type' ) ) {
            unregister_post_type( $slug );
        }

        add_settings_error( 'lightwork', 'deleted', __( 'Custom Post Type deleted.', 'lightwork-wp-plugin' ), 'updated' );
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

