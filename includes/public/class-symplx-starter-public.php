<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Symplx_Motion_Public {

    public function __construct() {
        // Shortcode for explicitly motion-izing a specific image attachment.
        add_shortcode( 'symplx_motion', [ $this, 'shortcode_motion' ] );
        // Apply motion to all images in content if enabled per post.
        add_filter( 'the_content', [ $this, 'filter_content_images' ], 20 );
    }

    private function render_motion_wrapper( $img_html, $effect = null ) {
        $effect = $effect ?: get_option( 'symplx_motion_default_effect', 'kenburns' );
        return '<div class="symplx-motion symplx-effect-' . esc_attr( $effect ) . '">' . $img_html . '</div>';
    }

    public function shortcode_motion( $atts ) {
        $atts = shortcode_atts( [
            'id'     => 0,
            'effect' => get_option( 'symplx_motion_default_effect', 'kenburns' ),
            'prompt' => '',
        ], $atts, 'symplx_motion' );

        $id = absint( $atts['id'] );
        if ( ! $id ) {
            return '';
        }

        // If AI-generated video exists, render it.
        $video_url = esc_url( (string) get_post_meta( $id, '_symplx_motion_video_url', true ) );
        if ( $video_url ) {
            $video = sprintf( '<video class="symplx-motion-video" src="%s" autoplay muted loop playsinline></video>', $video_url );
            return '<div class="symplx-motion symplx-effect-video">' . $video . '</div>';
        }

        $img = wp_get_attachment_image( $id, 'large', false, [ 'class' => 'symplx-motion-img' ] );
        if ( ! $img ) {
            return '';
        }

        // Future: if AI asset exists for $id, output <video> instead of CSS wrapper.
        return $this->render_motion_wrapper( $img, $atts['effect'] );
    }

    public function filter_content_images( $content ) {
        if ( is_admin() ) {
            return $content;
        }

        $post = get_post();
        if ( ! $post ) {
            return $content;
        }

        $enable_all = (bool) get_post_meta( $post->ID, 'symplx_motion_enable_all', true );
        $default_mode = get_option( 'symplx_motion_default_mode', 'off' );
        $should_apply = $enable_all || ( 'all' === $default_mode );
        if ( ! $should_apply ) {
            return $content;
        }

        // Use DOMDocument to wrap <img> tags.
        $html = $content;
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $encoding = '<?xml encoding="utf-8" ?>';
        if ( ! $dom->loadHTML( $encoding . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
            return $content;
        }
        $imgs = $dom->getElementsByTagName( 'img' );
        // Because live NodeList changes when wrapping, collect first.
        $to_wrap = [];
        foreach ( $imgs as $img ) {
            $to_wrap[] = $img;
        }
        foreach ( $to_wrap as $img ) {
            $classes = $img->getAttribute( 'class' );
            $id = 0;
            if ( $classes && preg_match( '/wp-image-(\d+)/', $classes, $m ) ) {
                $id = absint( $m[1] );
            }
            if ( $id ) {
                $video_url = get_post_meta( $id, '_symplx_motion_video_url', true );
                if ( $video_url ) {
                    $video = $dom->createElement( 'video' );
                    $video->setAttribute( 'src', esc_url( $video_url ) );
                    $video->setAttribute( 'autoplay', 'autoplay' );
                    $video->setAttribute( 'muted', 'muted' );
                    $video->setAttribute( 'loop', 'loop' );
                    $video->setAttribute( 'playsinline', 'playsinline' );
                    $wrapper = $dom->createElement( 'div' );
                    $wrapper->setAttribute( 'class', 'symplx-motion symplx-effect-video' );
                    $img->parentNode->replaceChild( $wrapper, $img );
                    $wrapper->appendChild( $video );
                    continue;
                }
            }

            $wrapper = $dom->createElement( 'div' );
            $wrapper->setAttribute( 'class', 'symplx-motion symplx-effect-' . esc_attr( get_option( 'symplx_motion_default_effect', 'kenburns' ) ) );
            $img->parentNode->replaceChild( $wrapper, $img );
            $wrapper->appendChild( $img );
        }
        $result = $dom->saveHTML();
        libxml_clear_errors();
        return $result ?: $content;
    }
}
