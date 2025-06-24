<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LightWork_CPT_System {
    /** @var LightWork_ACF_System */
    private $acf;

    /** @var LightWork_Template_Editor */
    private $editor;

    public function __construct( LightWork_ACF_System $acf, LightWork_Template_Editor $editor ) {
        $this->acf    = $acf;
        $this->editor = $editor;
    }

    /**
     * Register saved Custom Post Types on init.
     */
    public function register_saved_cpts() {
        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
        foreach ( $cpts as $cpt ) {
            $this->register_cpt( $cpt );
        }
    }

    /**
     * Register a single CPT and its ACF fields.
     *
     * @param array $args Post type arguments.
     */
    public function register_cpt( array $args ) {
        $slug        = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
        $single      = isset( $args['single'] ) ? sanitize_text_field( $args['single'] ) : '';
        $plural      = isset( $args['plural'] ) ? sanitize_text_field( $args['plural'] ) : '';
        $public      = isset( $args['public'] ) ? (bool) $args['public'] : true;
        $has_archive = isset( $args['has_archive'] ) ? (bool) $args['has_archive'] : true;
        $supports    = isset( $args['supports'] ) && is_array( $args['supports'] ) ? array_map( 'sanitize_key', $args['supports'] ) : [ 'title', 'editor', 'thumbnail' ];

        $fields        = isset( $args['acf_fields'] ) && is_array( $args['acf_fields'] ) ? $args['acf_fields'] : [];
        $menu_icon     = isset( $args['menu_icon'] ) ? sanitize_text_field( $args['menu_icon'] ) : '';
        $rewrite_slug  = isset( $args['rewrite_slug'] ) ? sanitize_title_with_dashes( $args['rewrite_slug'] ) : $slug;
        $hierarchical  = isset( $args['hierarchical'] ) ? (bool) $args['hierarchical'] : false;
        $template_page = isset( $args['template_page'] ) ? absint( $args['template_page'] ) : 0;

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
            'rewrite'      => [ 'slug' => $rewrite_slug ],
            'supports'     => $supports,
            'hierarchical' => $hierarchical,
        ];
        if ( $menu_icon ) {
            $args['menu_icon'] = $menu_icon;
        }

        register_post_type( $slug, $args );

        $this->acf->register_fields( $slug, $fields );
        $this->acf->add_quick_edit( $slug, $fields );
    }

    /**
     * Register plugin admin menu and sub pages.
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

        $this->editor->register_page();
    }

    /**
     * Render the main admin page for CPT management.
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
            $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
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
        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
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
            $template = '';
            if ( ! empty( $cpt['template_page'] ) ) {
                $tlink = admin_url( 'admin.php?page=lightwork-template-editor&slug=' . $cpt['slug'] );
                $template = ' | <a href="' . esc_url( $tlink ) . '">' . esc_html__( 'Template', 'lightwork-wp-plugin' ) . '</a>';
            }
            echo '<tr>';
            echo '<td>' . esc_html( $cpt['slug'] ) . '</td>';
            echo '<td>' . esc_html( $cpt['single'] ) . '</td>';
            echo '<td>' . esc_html( $cpt['plural'] ) . '</td>';
            echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'lightwork-wp-plugin' ) . '</a> | <a href="' . esc_url( $delete ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure?', 'lightwork-wp-plugin' ) ) . '\');">' . esc_html__( 'Delete', 'lightwork-wp-plugin' ) . '</a>' . $template . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Render CPT creation/edit form.
     */
    private function render_cpt_form( array $cpt = [], $editing = false ) {
        $slug        = $cpt['slug'] ?? '';
        $single      = $cpt['single'] ?? '';
        $plural      = $cpt['plural'] ?? '';
        $public      = isset( $cpt['public'] ) ? (bool) $cpt['public'] : true;
        $archive     = isset( $cpt['has_archive'] ) ? (bool) $cpt['has_archive'] : true;

        $supports      = isset( $cpt['supports'] ) && is_array( $cpt['supports'] ) ? $cpt['supports'] : [ 'title', 'editor', 'thumbnail' ];
        $acf_fields    = isset( $cpt['acf_fields'] ) && is_array( $cpt['acf_fields'] ) ? $cpt['acf_fields'] : [];
        $acf_enabled   = ! empty( $acf_fields );
        $menu_icon     = $cpt['menu_icon'] ?? '';
        $rewrite_slug  = $cpt['rewrite_slug'] ?? $slug;
        $hierarchical  = isset( $cpt['hierarchical'] ) ? (bool) $cpt['hierarchical'] : false;
        $template_page = isset( $cpt['template_page'] ) ? absint( $cpt['template_page'] ) : 0;
        $available     = [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ];

        echo '<h2>' . ( $editing ? esc_html__( 'Edit Custom Post Type', 'lightwork-wp-plugin' ) : esc_html__( 'Add Custom Post Type', 'lightwork-wp-plugin' ) ) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field( 'lw_save_cpt_nonce' );
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="lw-slug">' . esc_html__( 'Slug', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-slug" id="lw-slug" type="text" class="regular-text" value="' . esc_attr( $slug ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="lw-single">' . esc_html__( 'Singular Label', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-single" id="lw-single" type="text" class="regular-text" value="' . esc_attr( $single ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="lw-plural">' . esc_html__( 'Plural Label', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-plural" id="lw-plural" type="text" class="regular-text" value="' . esc_attr( $plural ) . '" required></td></tr>';

        echo '<tr><th scope="row"><label for="lw-menu-icon">' . esc_html__( 'Menu Icon', 'lightwork-wp-plugin' ) . '</label></th><td>';
        echo '<input name="lw-menu-icon" id="lw-menu-icon" type="text" class="regular-text" value="' . esc_attr( $menu_icon ) . '" placeholder="dashicons-admin-post" /> ';
        echo '<button type="button" class="button" id="lw-icon-picker-button">' . esc_html__( 'Choose Icon', 'lightwork-wp-plugin' ) . '</button> ';
        echo '<span id="lw-icon-preview" class="dashicons ' . esc_attr( $menu_icon ) . '" style="margin-left:10px;"></span>';
        echo '<div id="lw-icon-picker" style="display:none;margin-top:10px;">';
        $icons = [ 'dashicons-admin-post', 'dashicons-admin-media', 'dashicons-admin-links', 'dashicons-admin-plugins', 'dashicons-format-image', 'dashicons-format-video', 'dashicons-format-gallery', 'dashicons-admin-comments' ];
        foreach ( $icons as $icon ) {
            echo '<span class="dashicons ' . esc_attr( $icon ) . ' lw-icon-option" data-icon="' . esc_attr( $icon ) . '" style="font-size:24px;margin:5px;cursor:pointer;"></span>';
        }
        echo '</div></td></tr>';
        echo '<tr><th scope="row"><label for="lw-rewrite-slug">' . esc_html__( 'Rewrite Slug', 'lightwork-wp-plugin' ) . '</label></th><td><input name="lw-rewrite-slug" id="lw-rewrite-slug" type="text" class="regular-text" value="' . esc_attr( $rewrite_slug ) . '"></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Hierarchical', 'lightwork-wp-plugin' ) . '</th><td><input type="checkbox" name="lw-hierarchical" value="1"' . checked( $hierarchical, true, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'If enabled, the CPT behaves like pages (hierarchical). Choose carefully when creating.', 'lightwork-wp-plugin' ) . '</p></td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Supports', 'lightwork-wp-plugin' ) . '</th><td>';
        foreach ( $available as $feature ) {
            echo '<label><input type="checkbox" name="lw-supports[]" value="' . esc_attr( $feature ) . '"' . checked( in_array( $feature, $supports, true ), true, false ) . '/> ' . esc_html( $feature ) . '</label><br />';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Enable ACF', 'lightwork-wp-plugin' ) . '</th><td><label><input type="checkbox" id="lw-enable-acf" name="lw-enable-acf" value="1"' . checked( $acf_enabled, true, false ) . ' /> ' . esc_html__( 'Add custom fields', 'lightwork-wp-plugin' ) . '</label><p class="description">' . esc_html__( 'Check to add custom fields with ACF.', 'lightwork-wp-plugin' ) . '</p></td></tr>';

        echo '<tr id="lw-acf-section"><th scope="row">' . esc_html__( 'ACF Fields', 'lightwork-wp-plugin' ) . '</th><td>';
        echo '<table id="lw-acf-table" class="widefat"><tbody>';
        if ( ! empty( $acf_fields ) ) {
            foreach ( $acf_fields as $field ) {
                $label = esc_attr( $field['label'] ?? '' );
                $name  = esc_attr( $field['name'] ?? '' );
                $type  = esc_attr( $field['type'] ?? 'text' );
                echo '<tr><td>';
                echo '<input type="text" name="lw-acf-labels[]" placeholder="Label" value="' . $label . '" /> ';
                echo '<input type="text" name="lw-acf-names[]" placeholder="Name" value="' . $name . '" /> ';
                echo '<select name="lw-acf-types[]">';
                $types = [ 'text', 'textarea', 'number', 'image' ];
                foreach ( $types as $t ) {
                    echo '<option value="' . esc_attr( $t ) . '"' . selected( $type, $t, false ) . '>' . esc_html( ucfirst( $t ) ) . '</option>';
                }
                echo '</select> ';
                echo '<button type="button" class="button lw-remove-field">&times;</button>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="lw-add-field">' . esc_html__( 'Add Field', 'lightwork-wp-plugin' ) . '</button></p>';
        echo '</td></tr>';

        $use_template_checked = $template_page ? 'checked' : '';
        echo '<tr><th scope="row">' . esc_html__( 'Associate Template', 'lightwork-wp-plugin' ) . '</th><td><label><input type="checkbox" id="lw-use-template" name="lw-use-template" value="1" ' . $use_template_checked . ' /> ' . esc_html__( 'Link a template page', 'lightwork-wp-plugin' ) . '</label></td></tr>';
        echo '<tr id="lw-template-row"><th scope="row">' . esc_html__( 'Template Page', 'lightwork-wp-plugin' ) . '</th><td>';
        echo '<select name="lw-template-page" id="lw-template-page">';
        echo '<option value="">' . esc_html__( 'Create New...', 'lightwork-wp-plugin' ) . '</option>';
        $pages = get_pages();
        foreach ( $pages as $page ) {
            echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( $template_page, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Choose existing page or select "Create New..." to generate one.', 'lightwork-wp-plugin' ) . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__( 'Public', 'lightwork-wp-plugin' ) . '</th><td><input type="checkbox" name="lw-public" value="1"' . checked( $public, true, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'Visibility of the CPT on the frontend.', 'lightwork-wp-plugin' ) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Has Archive', 'lightwork-wp-plugin' ) . '</th><td><input type="checkbox" name="lw-archive" value="1"' . checked( $archive, true, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'Enable an archive page for this CPT.', 'lightwork-wp-plugin' ) . '</p></td></tr>';
        echo '</table>';
        if ( $editing ) {
            echo '<input type="hidden" name="lw-old-slug" value="' . esc_attr( $slug ) . '" />';
        }
        submit_button( $editing ? __( 'Save Changes', 'lightwork-wp-plugin' ) : __( 'Create CPT', 'lightwork-wp-plugin' ), 'primary', 'lw_save_cpt' );
        echo '</form>';
        ?>
        <script>
        jQuery(function($){
            $('#lw-add-field').on('click', function(){
                var row = '<tr><td>' +
                    '<input type="text" name="lw-acf-labels[]" placeholder="Label" /> ' +
                    '<input type="text" name="lw-acf-names[]" placeholder="Name" /> ' +
                    '<select name="lw-acf-types[]">' +
                        '<option value="text">Text</option>' +
                        '<option value="textarea">Textarea</option>' +
                        '<option value="number">Number</option>' +
                        '<option value="image">Image</option>' +
                    '</select> ' +
                    '<button type="button" class="button lw-remove-field">&times;</button>' +
                    '</td></tr>';
                $('#lw-acf-table tbody').append(row);
            });
            $(document).on('click', '.lw-remove-field', function(){
                $(this).closest('tr').remove();
            });
            function toggle_acf(){
                if($('#lw-enable-acf').is(':checked')){
                    $('#lw-acf-section').show();
                }else{
                    $('#lw-acf-section').hide();
                }
            }
            $('#lw-enable-acf').on('change', toggle_acf);
            toggle_acf();

            function toggle_template(){
                if($('#lw-use-template').is(':checked')){
                    $('#lw-template-row').show();
                }else{
                    $('#lw-template-row').hide();
                }
            }
            $('#lw-use-template').on('change', toggle_template);
            toggle_template();

            $('#lw-icon-picker-button').on('click', function(){
                $('#lw-icon-picker').toggle();
            });
            $(document).on('click', '.lw-icon-option', function(){
                var icon = $(this).data('icon');
                $('#lw-menu-icon').val(icon);
                $('#lw-icon-preview').attr('class', 'dashicons ' + icon);
                $('#lw-icon-picker').hide();
            });
            $('#lw-menu-icon').on('input', function(){
                $('#lw-icon-preview').attr('class', 'dashicons ' + $(this).val());
            });
        });
        </script>
        <?php
    }

    /**
     * Handle CPT creation or update form submission.
     */
    private function handle_form_submission( $old_slug = null ) {
        $slug     = isset( $_POST['lw-slug'] ) ? sanitize_key( $_POST['lw-slug'] ) : '';
        $single   = isset( $_POST['lw-single'] ) ? sanitize_text_field( $_POST['lw-single'] ) : '';
        $plural   = isset( $_POST['lw-plural'] ) ? sanitize_text_field( $_POST['lw-plural'] ) : '';

        $public       = isset( $_POST['lw-public'] );
        $archive      = isset( $_POST['lw-archive'] );
        $supports     = isset( $_POST['lw-supports'] ) ? array_map( 'sanitize_key', (array) $_POST['lw-supports'] ) : [];
        $menu_icon    = isset( $_POST['lw-menu-icon'] ) ? sanitize_text_field( $_POST['lw-menu-icon'] ) : '';
        $rewrite_slug = isset( $_POST['lw-rewrite-slug'] ) ? sanitize_title_with_dashes( $_POST['lw-rewrite-slug'] ) : $slug;
        $hierarchical = isset( $_POST['lw-hierarchical'] );
        $acf_on       = isset( $_POST['lw-enable-acf'] );
        $labels       = $acf_on ? (array) ( $_POST['lw-acf-labels'] ?? [] ) : [];
        $names        = $acf_on ? (array) ( $_POST['lw-acf-names'] ?? [] ) : [];
        $types        = $acf_on ? (array) ( $_POST['lw-acf-types'] ?? [] ) : [];
        $acf_fields   = [];
        if ( $acf_on ) {
            foreach ( $names as $index => $name ) {
                $name = sanitize_key( $name );
                if ( ! $name ) {
                    continue;
                }
                $acf_fields[] = [
                    'label' => sanitize_text_field( $labels[ $index ] ?? $name ),
                    'name'  => $name,
                    'type'  => sanitize_text_field( $types[ $index ] ?? 'text' ),
                ];
            }
        }

        $use_template  = isset( $_POST['lw-use-template'] );
        $template_page = isset( $_POST['lw-template-page'] ) ? absint( $_POST['lw-template-page'] ) : 0;
        if ( $use_template && ! $template_page ) {
            $template_page = wp_insert_post( [
                'post_title'  => $single . ' Template',
                'post_status' => 'draft',
                'post_type'   => 'page',
            ] );
        }

        if ( empty( $slug ) || empty( $single ) || empty( $plural ) ) {
            add_settings_error( 'lightwork', 'invalid', __( 'All fields are required.', 'lightwork-wp-plugin' ) );
            return;
        }

        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );

        if ( $old_slug ) {
            foreach ( $cpts as &$cpt ) {
                if ( $cpt['slug'] === $old_slug ) {
                    $cpt = [
                        'slug'         => $slug,
                        'single'       => $single,
                        'plural'       => $plural,
                        'public'       => $public,
                        'has_archive'  => $archive,
                        'supports'     => $supports,
                        'acf_fields'   => $acf_fields,
                        'menu_icon'    => $menu_icon,
                        'rewrite_slug' => $rewrite_slug,
                        'hierarchical' => $hierarchical,
                        'template_page' => $use_template ? $template_page : 0,
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
                'slug'         => $slug,
                'single'       => $single,
                'plural'       => $plural,
                'public'       => $public,
                'has_archive'  => $archive,
                'supports'     => $supports,
                'acf_fields'   => $acf_fields,
                'menu_icon'    => $menu_icon,
                'rewrite_slug' => $rewrite_slug,
                'hierarchical' => $hierarchical,
                'template_page' => $use_template ? $template_page : 0,
            ];
            $message = __( 'Custom Post Type created.', 'lightwork-wp-plugin' );
        }

        update_option( LightWork_WP_Plugin::OPTION_CPTS, $cpts );
        $this->register_cpt( [
            'slug'         => $slug,
            'single'       => $single,
            'plural'       => $plural,
            'public'       => $public,
            'has_archive'  => $archive,
            'supports'     => $supports,
            'acf_fields'   => $acf_fields,
            'menu_icon'    => $menu_icon,
            'rewrite_slug' => $rewrite_slug,
            'hierarchical' => $hierarchical,
            'template_page' => $use_template ? $template_page : 0,
        ] );

        add_settings_error( 'lightwork', 'success', $message, 'updated' );
        if ( $use_template ) {
            $link = admin_url( 'admin.php?page=lightwork-template-editor&slug=' . $slug );
            add_settings_error( 'lightwork', 'template', sprintf( __( 'Configure the template <a href="%s">here</a>.', 'lightwork-wp-plugin' ), esc_url( $link ) ), 'updated' );
        }
    }

    /**
     * Delete a CPT and unregister it if possible.
     */
    private function delete_cpt( $slug ) {
        $cpts = get_option( LightWork_WP_Plugin::OPTION_CPTS, [] );
        foreach ( $cpts as $i => $cpt ) {
            if ( $cpt['slug'] === $slug ) {
                unset( $cpts[ $i ] );
                break;
            }
        }
        update_option( LightWork_WP_Plugin::OPTION_CPTS, array_values( $cpts ) );

        if ( function_exists( 'unregister_post_type' ) ) {
            unregister_post_type( $slug );
        }

        add_settings_error( 'lightwork', 'deleted', __( 'Custom Post Type deleted.', 'lightwork-wp-plugin' ), 'updated' );
    }
}
