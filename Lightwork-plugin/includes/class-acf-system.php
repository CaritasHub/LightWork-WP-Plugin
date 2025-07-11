<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LightWork_ACF_System {
    /**
     * Register ACF fields for a CPT if ACF is available.
     *
     * @param string $slug   Post type slug.
     * @param array  $fields Array of field definitions.
     */
    public function register_fields( $slug, array $fields = [] ) {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        if ( empty( $fields ) ) {
            $fields = [
                [
                    'label' => __( 'Subtitle', 'lightwork-wp-plugin' ),
                    'name'  => 'subtitle',
                    'type'  => 'text',
                ],
            ];
        }

        $acf_fields = [];
        foreach ( $fields as $field ) {
            $name  = sanitize_key( $field['name'] ?? '' );
            $label = sanitize_text_field( $field['label'] ?? '' );
            $type  = sanitize_text_field( $field['type'] ?? 'text' );
            if ( ! $name ) {
                continue;
            }
            $acf_fields[] = [
                'key'   => 'field_' . $slug . '_' . $name,
                'label' => $label ?: $name,
                'name'  => $name,
                'type'  => $type,
            ];
        }

        if ( empty( $acf_fields ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'      => 'group_' . $slug,
            'title'    => ucfirst( $slug ) . ' Fields',
            'fields'   => $acf_fields,
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
     * Add Quick Edit support for ACF fields.
     *
     * @param string $slug   Post type slug.
     * @param array  $fields Array of ACF fields.
     */
    public function add_quick_edit( $slug, array $fields ) {
        if ( empty( $fields ) ) {
            return;
        }

        add_filter( "manage_edit-{$slug}_columns", function ( $cols ) use ( $fields ) {
            foreach ( $fields as $field ) {
                $name = sanitize_key( $field['name'] );
                $cols[ $name ] = esc_html( $field['label'] );
            }
            return $cols;
        } );

        add_action( "manage_{$slug}_posts_custom_column", function ( $column, $post_id ) use ( $fields ) {
            foreach ( $fields as $field ) {
                $name = sanitize_key( $field['name'] );
                if ( $column === $name ) {
                    echo '<span class="lw-' . esc_attr( $name ) . '">' . esc_html( get_post_meta( $post_id, $name, true ) ) . '</span>';
                    return;
                }
            }
        }, 10, 2 );

        add_action( 'quick_edit_custom_box', function ( $column_name, $post_type ) use ( $slug, $fields ) {
            if ( $post_type !== $slug ) {
                return;
            }
            foreach ( $fields as $field ) {
                $name = sanitize_key( $field['name'] );
                if ( $column_name === $name ) {
                    echo '<fieldset class="inline-edit-col">';
                    echo '<label>';
                    echo '<span class="title">' . esc_html( $field['label'] ) . '</span>';
                    echo '<span class="input-text-wrap"><input type="text" name="' . esc_attr( $name ) . '" /></span>';
                    echo '</label>';
                    echo '</fieldset>';
                }
            }
        }, 10, 2 );

        add_action( "save_post_{$slug}", function ( $post_id ) use ( $fields ) {
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }
            foreach ( $fields as $field ) {
                $name = sanitize_key( $field['name'] );
                if ( isset( $_POST[ $name ] ) ) {
                    update_post_meta( $post_id, $name, sanitize_text_field( $_POST[ $name ] ) );
                }
            }
        } );

        add_action( 'admin_footer-edit.php', function () use ( $slug, $fields ) {
            $screen = get_current_screen();
            if ( $screen->post_type !== $slug ) {
                return;
            }
            $js = [];
            foreach ( $fields as $field ) {
                $name = sanitize_key( $field['name'] );
                $js[] = "var val_{$name} = jQuery('#post-' + id).find('.lw-{$name}').text();jQuery('#edit-' + id + ' input[name=\\'{$name}\\']').val(val_{$name});";
            }
            ?>
            <script>
            jQuery(function($){
                var $edit = inlineEditPost.edit;
                inlineEditPost.edit = function(id){
                    $edit.apply(this, arguments);
                    id = typeof(id) === 'object' ? $(id).attr('id').replace('post-','') : id;
                    <?php echo implode( '', $js ); ?>
                };
            });
            </script>
            <?php
        } );
    }
}
