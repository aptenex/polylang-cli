<?php

namespace Polylang_CLI\Traits;

if ( ! trait_exists( 'Polylang_CLI\Traits\Cpt' ) ) {



trait Cpt {

    private function manage( $action, $type, $data )
    {
        if ( empty( $data ) ) {
            $this->cli->error( 'Specify one or more post types and/or taxonomies you want to enable translation for.' );
        }

        $input = explode( ',', $data );

        # polylang pro has privatized the post_types/taxonomies within their PLL_Settings_CPT
        # we can get these types though by extending the class

        # invoke Polylang settings module
        $settings = new class( $this->pll ) extends \PLL_Settings_CPT {
            public function get_post_types() {
                return (array) $this->post_types;
            }

            public function get_taxonomies() {
                return (array) $this->taxonomies;
            }

            public function update( $options ) {
                return parent::update($options);
            }
        };

        $this->options_cpt = $settings;

        # set current module
        $settings->module = 'cpt';

        # populate the $_POST array
        $_POST = array();

        $this->cli->success( \json_encode($data) );
        $this->cli->success( \json_encode([$settings->get_post_types(), $settings->get_taxonomies()]) );

        # sanitize post types input
        $post_types = array_map( 'sanitize_key', explode( ',', $data ) );
        $post_types = array_combine( $post_types, array_fill( 1, count( $post_types ), 1 ) );
        $post_types = array_intersect_key( $post_types, $settings->get_post_types() );
        $post_types = array_merge( array_combine( $settings->options['post_types'], array_fill( 1, count( $settings->options['post_types'] ), 1 ) ), $post_types );

        # sanitize taxonomies input
        $taxonomies = array_map( 'sanitize_title', explode( ',', $data ) );
        $taxonomies = array_combine( $taxonomies, array_fill( 1, count( $taxonomies ), 1 ) );
        $taxonomies = array_intersect_key( $taxonomies, $settings->get_taxonomies() );
        $taxonomies = array_merge( array_combine( $settings->options['taxonomies'], array_fill( 1, count( $settings->options['taxonomies'] ), 1 ) ), $taxonomies );

        # disable post types or taxonomies
        if ( $action === 'disable' ) {
            foreach ( array( 'post_types', 'taxonomies' ) as $key ) {
                foreach ( $input as $i ) {
                    if ( isset( ${$key}[$i] ) ) {
                        unset( ${$key}[$i] );
                    }
                }
            }
        }

        $_POST = compact( 'post_types', 'taxonomies' );
        $_POST['action'] = 'pll_save_options';
        $_POST['module'] = 'cpt';

        $this->cli->success( \json_encode($_POST) );

        $options = $settings->update( $_POST );

        # update Polylang settings
        $settings->options = array_merge( $settings->options, $options );

        # see below, @todo review
        update_option( 'polylang', $settings->options );

        # set the options
        $this->pll->model->options = $settings->options;

        # update options, default category and nav menu locations
        $this->pll->model->update_default_lang( $this->api->default_language() );

        # refresh language cache in case home urls have been modified
        $settings->model->clean_languages_cache();

        # refresh rewrite rules in case rewrite,  hide_default, post types or taxonomies options have been modified
        # don't use flush_rewrite_rules as we don't have the right links model and permastruct
        delete_option( 'rewrite_rules' );

        $this->cli->success( sprintf( 'Polylang `%s` option updated', $type ) );

/*
        ob_start();

        if ( ! get_settings_errors() ) {
            # send update message
            add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
            settings_errors();
            $response = new \WP_Ajax_Response( array( 'what' => 'success', 'data' => ob_get_clean() ) );
        } else {
            # send error messages
            settings_errors();
            $response = new \WP_Ajax_Response( array( 'what' => 'error', 'data' => ob_get_clean() ) );
        }

        foreach ( $response->responses as $xml ) {
            $object = simplexml_load_string( $xml, null, LIBXML_NOCDATA );
            if ( property_exists( $object, 'success' ) ) {
                \WP_CLI::success( wp_strip_all_tags( $object->success->response_data ) );
            } elseif ( property_exists( $object, 'error' ) ) {
                \WP_CLI::error( wp_strip_all_tags( $object->error->response_data ) );
            } else {
                \WP_CLI::error( 'An unknown error occurred saving the settings.' );
            }
        }
*/

    }

}

}
