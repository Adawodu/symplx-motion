<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Symplx_Motion_Media_UI {

    public function __construct() {
        add_filter( 'media_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_filter( 'manage_upload_columns', [ $this, 'add_column' ] );
        add_action( 'manage_media_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'admin_action_symplx_generate_motion', [ $this, 'action_generate_motion' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
    }

    public function row_actions( $actions, $post ) {
        if ( 'attachment' === $post->post_type && wp_attachment_is_image( $post->ID ) && current_user_can( 'upload_files' ) ) {
            $url = wp_nonce_url( add_query_arg( [
                'action'        => 'symplx_generate_motion',
                'attachment_id' => $post->ID,
            ], admin_url( 'admin.php' ) ), 'symplx_generate_motion_' . $post->ID );
            $actions['symplx_generate_motion'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Generate Motion', 'symplx-motion' ) . '</a>';
        }
        return $actions;
    }

    public function add_column( $cols ) {
        $cols['symplx_motion'] = __( 'Motion', 'symplx-motion' );
        return $cols;
    }

    public function render_column( $column_name, $post_id ) {
        if ( 'symplx_motion' !== $column_name ) return;
        if ( ! wp_attachment_is_image( $post_id ) ) { echo '—'; return; }
        $status = get_post_meta( $post_id, '_symplx_motion_status', true ) ?: '—';
        $video = get_post_meta( $post_id, '_symplx_motion_video_url', true );
        $preset = get_post_meta( $post_id, '_symplx_motion_preset_resolved', true );
        $model  = get_post_meta( $post_id, '_symplx_motion_model_version_resolved', true );
        $labels = [
            'svd' => 'SVD',
            'seedance' => 'Seedance',
            'seedance-lite' => 'Seedance Lite',
            'minimax' => 'MiniMax',
            'wan-fast' => 'WAN Fast',
            'wan-720p' => 'WAN 720p',
            'ltx-video' => 'LTX Video',
        ];
        $suffix = '';
        if ( $preset ) {
            $suffix = ' (' . ( $labels[ $preset ] ?? $preset ) . ')';
        }
        $title = $model ? ' title="' . esc_attr( $model ) . '"' : '';
        if ( $video ) {
            echo '<span class="dashicons dashicons-yes" style="color:#46b450"' . $title . '></span> ' . esc_html__( 'Ready', 'symplx-motion' ) . esc_html( $suffix );
        } else {
            echo '<span' . $title . '>' . esc_html( ucfirst( $status ) . $suffix ) . '</span>';
        }
    }

    public function action_generate_motion() {
        if ( ! current_user_can( 'upload_files' ) ) wp_die( __( 'Permission denied', 'symplx-motion' ) );
        $attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
        if ( ! $attachment_id || ! wp_verify_nonce( (string) $_GET['_wpnonce'], 'symplx_generate_motion_' . $attachment_id ) ) {
            wp_die( __( 'Invalid request', 'symplx-motion' ) );
        }
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_redirect( add_query_arg( 'symplx_motion_notice', 'notimage', admin_url( 'upload.php' ) ) );
            exit;
        }

        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/providers/interface-provider.php';
        require_once SYMPLX_MOTION_PLUGIN_DIR . 'includes/jobs/class-symplx-starter-jobs.php';

        $provider = Symplx_Motion_Providers_Registry::get();
        if ( ! $provider ) {
            wp_redirect( add_query_arg( 'symplx_motion_notice', 'noprovider', admin_url( 'upload.php' ) ) );
            exit;
        }

        $res = $provider->create_job( $attachment_id, [] );
        if ( is_wp_error( $res ) ) {
            update_post_meta( $attachment_id, '_symplx_motion_status', 'failed' );
            update_post_meta( $attachment_id, '_symplx_motion_error', $res->get_error_message() );
            wp_redirect( add_query_arg( 'symplx_motion_notice', 'error', admin_url( 'upload.php' ) ) );
            exit;
        }
        $job_id = $res['job_id'] ?? '';
        $status = $res['status'] ?? 'queued';
        update_post_meta( $attachment_id, '_symplx_motion_provider', Symplx_Motion_Providers_Registry::get_active_slug() );
        update_post_meta( $attachment_id, '_symplx_motion_job_id', $job_id );
        update_post_meta( $attachment_id, '_symplx_motion_status', $status );
        if ( isset( $res['raw'] ) ) {
            update_post_meta( $attachment_id, '_symplx_motion_last_raw', wp_json_encode( $res['raw'] ) );
        }
        $now = current_time( 'mysql', true );
        update_post_meta( $attachment_id, '_symplx_motion_created_at', $now );
        update_post_meta( $attachment_id, '_symplx_motion_updated_at', $now );
        // Save resolved model/preset for visibility
        $parent = wp_get_post_parent_id( $attachment_id );
        $resolved_model = '';
        $resolved_preset = '';
        if ( $parent ) {
            $resolved_model = get_post_meta( $parent, 'symplx_motion_model_version', true );
            $resolved_preset = get_post_meta( $parent, 'symplx_motion_mapping_preset', true );
        }
        if ( ! $resolved_model ) {
            $resolved_model = get_option( 'symplx_motion_replicate_model_version', '' );
        }
        update_post_meta( $attachment_id, '_symplx_motion_model_version_resolved', $resolved_model );
        if ( $resolved_preset ) {
            update_post_meta( $attachment_id, '_symplx_motion_preset_resolved', $resolved_preset );
        }

        Symplx_Motion_Jobs::schedule_check( $attachment_id, 30 );

        wp_redirect( add_query_arg( 'symplx_motion_notice', 'started', admin_url( 'upload.php' ) ) );
        exit;
    }

    public function admin_notices() {
        if ( ! isset( $_GET['symplx_motion_notice'] ) ) return;
        $msg = sanitize_text_field( wp_unslash( $_GET['symplx_motion_notice'] ) );
        $text = '';
        $class = 'notice-info';
        switch ( $msg ) {
            case 'started': $text = __( 'Motion generation started. Status will update automatically.', 'symplx-motion' ); break;
            case 'noprovider': $text = __( 'No motion provider configured. Set one under Settings → Symplx Motion.', 'symplx-motion' ); break;
            case 'notimage': $text = __( 'Selected item is not an image.', 'symplx-motion' ); break;
            case 'error': $text = __( 'Failed to start generation. Check provider settings.', 'symplx-motion' ); $class = 'notice-error'; break;
        }
        if ( $text ) {
            echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
        }
    }
}
