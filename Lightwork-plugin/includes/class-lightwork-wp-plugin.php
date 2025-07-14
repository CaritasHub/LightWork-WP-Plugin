<?php
class LightWork_WP_Plugin {
    private static $instance = null;

    const OPTION_CPTS = 'lightwork_cpts';
    const CRON_HOOK   = 'lightwork_batch_update';


    /** @var LightWork_ACF_System */
    private $acf_system;

    /** @var LightWork_CPT_System */
    private $cpt_system;

    /** @var LightWork_Sandbox_Editor */
    private $sandbox_editor;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->sandbox_editor = new LightWork_Sandbox_Editor();
        $this->acf_system      = new LightWork_ACF_System();
        $this->cpt_system      = new LightWork_CPT_System( $this->acf_system );

        add_action( 'init', [ $this->cpt_system, 'register_saved_cpts' ] );
        add_action( 'admin_menu', [ $this->cpt_system, 'register_admin_menu' ] );
        add_action( 'admin_menu', [ $this->sandbox_editor, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this->cpt_system, 'enqueue_assets' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( self::CRON_HOOK, [ $this, 'batch_update' ] );
    }

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
            $item = [
                'ID'    => $post->ID,
                'title' => get_the_title( $post ),
                'link'  => get_permalink( $post ),
            ];
            if ( function_exists( 'get_fields' ) ) {
                $fields = get_fields( $post->ID );
                if ( $fields ) {
                    $item['acf'] = $fields;
                }
            }
            $data[] = $item;
        }

        return rest_ensure_response( $data );
    }

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
                if ( function_exists( 'update_field' ) && ! empty( $cpt['acf_fields'] ) ) {
                    foreach ( $cpt['acf_fields'] as $field ) {
                        if ( ! empty( $field['name'] ) ) {
                            update_field( $field['name'], 'Updated via cron', get_the_ID() );
                        }
                    }
                }
            }
            wp_reset_postdata();
        }
    }

    public static function activate() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
