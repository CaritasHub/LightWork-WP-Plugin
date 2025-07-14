<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LightWork_Template_Editor {
    const OPTION_PREFIX = 'lw_template_map_';

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'wp_ajax_lw_update_mapping', [ $this, 'ajax_update_mapping' ] );
    }

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

        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-droppable' );

        $slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
        $cpt  = null;
        foreach ( $cpts as $item ) {
            if ( $item['slug'] === $slug ) {
                $cpt = $item;
                break;
            }
        }
        if ( ! $cpt || empty( $cpt['template_page'] ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'No template configured.', 'lightwork-wp-plugin' ) . '</h1></div>';
            return;
        }

        $page_id   = $cpt['template_page'];
        $acf_fields = $cpt['acf_fields'] ?? [];
        $option     = self::OPTION_PREFIX . $slug;
        $mapping    = get_option( $option, [] );

        if ( isset( $_POST['lw-save-template'] ) && check_admin_referer( 'lw_save_template_' . $slug ) ) {
            $mapping = [];
            foreach ( (array) ( $_POST['lw-mapping'] ?? [] ) as $key => $sel ) {
                $mapping[ sanitize_key( $key ) ] = sanitize_text_field( $sel );
            }
            $missing = [];
            foreach ( $acf_fields as $field ) {
                if ( empty( $mapping[ $field['name'] ] ) ) {
                    $missing[] = $field['name'];
                }
            }
            if ( $missing ) {
                add_settings_error( 'lightwork', 'missing', __( 'Map all fields before saving.', 'lightwork-wp-plugin' ) );
            } else {
                update_option( $option, $mapping );
                add_settings_error( 'lightwork', 'saved', __( 'Template saved.', 'lightwork-wp-plugin' ), 'updated' );
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Template Editor', 'lightwork-wp-plugin' ); ?></h1>
            <?php settings_errors( 'lightwork' ); ?>
            <div id="lw-template-editor" style="display:flex;gap:20px;">
                <div id="lw-template-preview" style="flex:1;">
                    <iframe src="<?php echo esc_url( get_permalink( $page_id ) ); ?>" style="width:100%;height:500px;border:1px solid #ccc;"></iframe>
                </div>
                <div id="lw-template-fields" style="width:250px;">
                    <form method="post">
                        <?php wp_nonce_field( 'lw_save_template_' . $slug ); ?>
                        <?php foreach ( $acf_fields as $field ) :
                            $name  = esc_attr( $field['name'] );
                            if ( ! empty( $mapping[ $name ] ) ) {
                                continue;
                            }
                            $label = esc_html( $field['label'] ); ?>
                            <div class="lw-field" data-field="<?php echo $name; ?>">
                                <?php echo $label; ?>
                                <input type="hidden" name="lw-mapping[<?php echo $name; ?>]" value="" />
                            </div>
                        <?php endforeach; ?>
                        <p><input type="submit" name="lw-save-template" class="button button-primary" value="<?php esc_attr_e( 'Save Template', 'lightwork-wp-plugin' ); ?>" /></p>
                    </form>
                </div>
            </div>
        </div>
        <style>
        #lw-template-editor .lw-field{border:1px solid #ccc;padding:5px;margin-bottom:5px;cursor:move;background:#fff;}
        #lw-template-preview .lw-highlight{outline:2px dashed red;}
        </style>
        <script>
        jQuery(function($){
            $('.lw-field').draggable({helper:'clone'});
            $('#lw-template-preview iframe').on('load', function(){
                var doc = this.contentWindow.document;
                $(doc).find('*').each(function(){
                    $(this).droppable({
                        hoverClass:'lw-highlight',
                        drop:function(e,ui){
                            var selector = lwGetSelector(this);
                            ui.draggable.find('input').val(selector);
                        }
                    });
                });
            });
            function lwGetSelector(el){
                var sel='';
                while(el && el.nodeType===1 && !el.id){
                    var idx=$(el).index();
                    sel=el.tagName.toLowerCase()+(idx?':eq('+idx+')':'')+(sel?'>'+sel:'');
                    el=el.parentElement;
                }
                if(el && el.id){ sel='#'+el.id+(sel?'>'+sel:''); }
                return sel;
            }
        });
        </script>
        <?php
    }

    public function add_meta_boxes() {
        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
        foreach ( $cpts as $cpt ) {
            add_meta_box(
                'lw-template-editor',
                __( 'Template', 'lightwork-wp-plugin' ),
                function () use ( $cpt ) {
                    if ( empty( $cpt['template_page'] ) ) {
                        return;
                    }
                    $link = admin_url( 'admin.php?page=lightwork-template-editor&slug=' . $cpt['slug'] );
                    echo '<a href="' . esc_url( $link ) . '" class="button">' . esc_html__( 'Edit Template', 'lightwork-wp-plugin' ) . '</a>';
                },
                $cpt['slug'],
                'side',
                'high'
            );
        }
    }

    public function ajax_update_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        check_ajax_referer( 'lw_update_mapping', 'nonce' );
        $slug     = sanitize_key( $_POST['slug'] ?? '' );
        $field    = sanitize_key( $_POST['field'] ?? '' );
        $selector = sanitize_text_field( $_POST['selector'] ?? '' );
        if ( ! $slug || ! $field ) {
            wp_send_json_error();
        }
        $option          = self::OPTION_PREFIX . $slug;
        $mapping         = get_option( $option, [] );
        $mapping[ $field ] = $selector;
        update_option( $option, $mapping );
        wp_send_json_success();
    }
}
