<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
Plugin Name: Hide Page And Post Title
Plugin URI: https://profiles.wordpress.org/arjunthakur#content-plugins/
Description: Hide the title on individual pages, posts and public custom post types.
Author: Arjun Thakur
Version: 1.6.2
Requires at least: 4.1
Requires PHP: 5.6
Tested up to: 7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: https://profiles.wordpress.org/arjunthakur
Text Domain: hide-page-and-post-title
*/

if ( ! class_exists( 'hpt_hidepagetitle' ) ) {

    class hpt_hidepagetitle {

        private $hpt_slug = 'hpt_headertitle';
        private $hpt_selector = '.entry-title';

        public function __construct() {
            add_action( 'add_meta_boxes', array( $this, 'hpt_hptaddbox' ) );
            add_action( 'save_post', array( $this, 'hpt_hptsave' ), 10, 3 );
            add_action( 'delete_post', array( $this, 'hpt_hptdelete' ) );
            add_action( 'wp_head', array( $this, 'hpt_hptheadinsert' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'hpt_hptloadscripts' ) );

            add_filter(
                'render_block_core/post-title',
                array( $this, 'hpt_hide_block_post_title' ),
                10,
                2
            );
        }

        private function hpt_ishidden( $post_id = 0 ) {
            if ( ! is_singular() ) {
                return false;
            }

            if ( ! $post_id ) {
                $post_id = get_queried_object_id();
            }

            if ( ! $post_id ) {
                return false;
            }

            return (bool) get_post_meta( $post_id, $this->hpt_slug, true );
        }

        public function hpt_hptheadinsert() {
            if ( ! $this->hpt_ishidden() || $this->hpt_is_block_theme() ) {
                return;
            }

            $selector    = trim( $this->hpt_selector );
            $title       = get_the_title( get_queried_object_id() );
            $selector_js = wp_json_encode( $selector );
            $title_js    = wp_json_encode( wp_strip_all_tags( $title ) );
            ?>
            <!-- Hide Page Title -->
            <script type="text/javascript">
            (function () {
                'use strict';

                function hptHideTitle() {
                    var selector = <?php echo esc_js( $selector_js ); ?>;
                    var titleText = <?php echo esc_js( $title_js ); ?>;
                    var target = null;

                    if (selector) {
                        try {
                            target = document.querySelector(selector);
                        } catch (e) {
                            target = null;
                        }
                    }

                    if (!target) {
                        var headings = document.querySelectorAll('h1, h2');
                        var normalizedTitle = String(titleText || '')
                            .replace(/\s+/g, ' ')
                            .trim();

                        for (var i = 0; i < headings.length; i++) {
                            var headingText = (headings[i].textContent || '')
                                .replace(/\s+/g, ' ')
                                .trim();

                            if (normalizedTitle && headingText === normalizedTitle) {
                                target = headings[i];
                                break;
                            }
                        }
                    }

                    if (target) {
                        target.style.display = 'none';
                        target.setAttribute('aria-hidden', 'true');
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', hptHideTitle);
                } else {
                    hptHideTitle();
                }
            }());
            </script>
            <noscript>
                <style type="text/css">
                    <?php echo esc_html( $selector ); ?> { display:none !important; }
                </style>
            </noscript>
            <!-- END Hide Page Title -->
            <?php
        }

        private function hpt_is_block_theme() {
            return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
        }

        public function hpt_hide_block_post_title( $block_content, $block ) {
            if ( ! $this->hpt_is_block_theme() || ! is_singular() ) {
                return $block_content;
            }

            $post_id = get_queried_object_id();

            if ( ! $post_id || ! $this->hpt_ishidden( $post_id ) ) {
                return $block_content;
            }

            return '';
        }

        public function hpt_hptaddbox() {
            $posttypes = array( 'post', 'page' );

            $post_types = get_post_types(
                array(
                    'public'   => true,
                    '_builtin' => false,
                ),
                'names',
                'and'
            );

            foreach ( $post_types as $post_type ) {
                $posttypes[] = $post_type;
            }

            foreach ( array_unique( $posttypes ) as $posttype ) {
                add_meta_box(
                    $this->hpt_slug,
                    'Hide Page and Post Title',
                    array( $this, 'build_hptbox' ),
                    $posttype,
                    'side'
                );
            }
        }

        public function build_hptbox( $post ) {
            $value   = get_post_meta( $post->ID, $this->hpt_slug, true );
            $checked = $value ? ' checked="checked"' : '';

            wp_nonce_field(
                $this->hpt_slug . '_dononce',
                $this->hpt_slug . '_noncename'
            );
            ?>
            <label>
                <input type="checkbox"
                    name="<?php echo esc_attr( $this->hpt_slug ); ?>"
                    value="1"<?php echo esc_attr( $checked ); ?> />
                Hide the title.
            </label>
            <?php
        }

        public function hpt_hptloadscripts() {
            if ( $this->hpt_ishidden() && ! $this->hpt_is_block_theme() ) {
                wp_enqueue_script( 'jquery' );
            }
        }

        public function hpt_hptsave( $postID, $post = null, $update = false ) {
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return $postID;
            }

            if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
                return $postID;
            }

            if ( defined( 'WP_REST_REQUEST' ) && WP_REST_REQUEST ) {
                return $postID;
            }

            if ( wp_is_post_revision( $postID ) ) {
                return $postID;
            }

            if ( ! isset( $_POST[ $this->hpt_slug . '_noncename' ] ) ) {
                return $postID;
            }

            $nonce = sanitize_text_field(
                wp_unslash( $_POST[ $this->hpt_slug . '_noncename' ] )
            );

            if ( ! wp_verify_nonce( $nonce, $this->hpt_slug . '_dononce' ) ) {
                return $postID;
            }

            if ( ! current_user_can( 'edit_post', $postID ) ) {
                return $postID;
            }

            if ( isset( $_POST[ $this->hpt_slug ] ) ) {
                update_post_meta( $postID, $this->hpt_slug, '1' );
            } else {
                delete_post_meta( $postID, $this->hpt_slug );
            }

            return $postID;
        }

        public function hpt_hptdelete( $postID ) {
            delete_post_meta( $postID, $this->hpt_slug );
            return $postID;
        }

        public function set_hpt_selector( $hpt_selector ) {
            if ( is_string( $hpt_selector ) && '' !== trim( $hpt_selector ) ) {
                $this->hpt_selector = $hpt_selector;
            }
        }
    }

    $hpt_hidepagetitle = new hpt_hidepagetitle;
}