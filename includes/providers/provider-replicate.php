<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Symplx_Motion_Provider_Replicate implements Symplx_Motion_Provider_Interface {

    private function get_api_token() {
        $tok = get_option( 'symplx_replicate_api_token', '' );
        return $tok ? trim( (string) $tok ) : '';
    }

    private function get_model_version() {
        // Make the model version configurable; user can paste the version hash.
        // Example (not guaranteed current): stability-ai/stable-video-diffusion-img2vid:VERSION_HASH
        $v = get_option( 'symplx_replicate_model_version', '' );
        return $v ? trim( (string) $v ) : '';
    }

    public function create_job( int $attachment_id, array $args = [] ) {
        $token = $this->get_api_token();
        $version = $this->get_model_version();
        if ( ! $token || ! $version ) {
            return new WP_Error( 'replicate_config', 'Replicate API token or model version missing' );
        }

        $image_url = wp_get_attachment_url( $attachment_id );
        if ( ! $image_url ) {
            return new WP_Error( 'no_image', 'Attachment URL not found' );
        }

        $duration = isset( $args['duration'] ) ? (int) $args['duration'] : 4;
        $fps      = isset( $args['fps'] ) ? (int) $args['fps'] : 24;
        $prompt   = isset( $args['prompt'] ) ? (string) $args['prompt'] : '';

        // Per-post overrides (model + mapping preset) via the attachment's parent post.
        $parent_post_id = wp_get_post_parent_id( $attachment_id );
        if ( $parent_post_id ) {
            $post_version = get_post_meta( $parent_post_id, 'symplx_motion_model_version', true );
            if ( $post_version ) { $version = trim( (string) $post_version ); }
        }

        // Replicate predictions prefer the raw version id (UUID/hash). If the user provided
        // an owner/model:version string, extract just the version segment for the API call.
        $version_for_api = $version;
        if ( preg_match( '/^[\w-]+\/[\w-]+:([\w-]+)/', $version, $mver ) ) {
            $version_for_api = $mver[1];
        }

        // Build input map based on settings and optional preset.
        $image_key   = get_option( 'symplx_replicate_image_key', 'input_image' ) ?: 'input_image';
        $fps_key     = get_option( 'symplx_replicate_fps_key', 'fps' );
        $frames_key  = get_option( 'symplx_replicate_frames_key', 'num_frames' );
        $prompt_key  = get_option( 'symplx_replicate_prompt_key', 'prompt' );
        $duration_key= get_option( 'symplx_replicate_duration_key', '' );
        $extra_json  = get_option( 'symplx_replicate_extra_input', '' );

        $preset = '';
        if ( $parent_post_id ) {
            $preset = get_post_meta( $parent_post_id, 'symplx_motion_mapping_preset', true );
        }
        if ( $preset ) {
            $map = $this->mapping_for_preset( $preset );
            if ( $map ) {
                $image_key    = $map['image']    ?? $image_key;
                $fps_key      = array_key_exists( 'fps', $map ) ? $map['fps'] : $fps_key;
                $frames_key   = array_key_exists( 'frames', $map ) ? $map['frames'] : $frames_key;
                $duration_key = array_key_exists( 'duration', $map ) ? $map['duration'] : $duration_key;
                $prompt_key   = array_key_exists( 'prompt', $map ) ? $map['prompt'] : $prompt_key;
            }
        }

        $input = [];
        $input[ $image_key ] = $image_url;
        if ( $fps_key ) {
            $input[ $fps_key ] = $fps;
        }
        $frames = max( $fps * $duration, 16 );
        if ( $frames_key ) {
            $input[ $frames_key ] = $frames;
        } elseif ( $duration_key ) {
            $input[ $duration_key ] = $duration;
        }
        if ( $prompt && $prompt_key ) {
            $input[ $prompt_key ] = $prompt;
        }
        if ( $extra_json ) {
            $decoded = json_decode( $extra_json, true );
            if ( is_array( $decoded ) ) {
                // Do not overwrite existing keys unintentionally
                foreach ( $decoded as $k => $v ) {
                    if ( ! array_key_exists( $k, $input ) ) {
                        $input[ $k ] = $v;
                    }
                }
            }
        }

        $endpoint = 'https://api.replicate.com/v1/predictions';
        $body = [ 'version' => $version_for_api, 'input' => $input ];

        $res = wp_remote_post( $endpoint, [
            'headers' => [
                'Authorization' => 'Token ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $code = wp_remote_retrieve_response_code( $res );
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code >= 400 || ! is_array( $data ) ) {
            return new WP_Error( 'replicate_http', 'Replicate error creating job', [ 'code' => $code, 'body' => wp_remote_retrieve_body( $res ) ] );
        }
        $id = $data['id'] ?? '';
        $status = $data['status'] ?? 'starting';
        if ( ! $id ) {
            return new WP_Error( 'replicate_no_id', 'Replicate returned no job id' );
        }
        return [ 'job_id' => $id, 'status' => $status, 'raw' => $data ];
    }

    public function get_status( string $job_id ) {
        $token = $this->get_api_token();
        if ( ! $token ) {
            return new WP_Error( 'replicate_config', 'Replicate API token missing' );
        }
        $endpoint = 'https://api.replicate.com/v1/predictions/' . rawurlencode( $job_id );
        $res = wp_remote_get( $endpoint, [
            'headers' => [ 'Authorization' => 'Token ' . $token ],
            'timeout' => 20,
        ] );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        $code = wp_remote_retrieve_response_code( $res );
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code >= 400 || ! is_array( $data ) ) {
            return new WP_Error( 'replicate_http', 'Replicate error getting status', [ 'code' => $code, 'body' => wp_remote_retrieve_body( $res ) ] );
        }
        $status = $data['status'] ?? 'unknown';
        $output = $data['output'] ?? null;
        $video_url = null;
        $progress = null;
        // Best-effort progress extraction
        if ( isset( $data['metrics']['progress'] ) && is_numeric( $data['metrics']['progress'] ) ) {
            $progress = (int) round( 100 * floatval( $data['metrics']['progress'] ) );
        } elseif ( ! empty( $data['logs'] ) && is_string( $data['logs'] ) ) {
            if ( preg_match( '/(\d{1,3})%/', $data['logs'], $m ) ) {
                $progress = min( 100, max( 0, intval( $m[1] ) ) );
            }
        }
        if ( is_array( $output ) ) {
            // Some models return array of frames or a single mp4 url. Attempt to find a video URL.
            foreach ( $output as $item ) {
                if ( is_string( $item ) && preg_match( '/\.(mp4|webm)(\?|$)/i', $item ) ) {
                    $video_url = $item;
                    break;
                }
            }
        } elseif ( is_string( $output ) ) {
            if ( preg_match( '/\.(mp4|webm)(\?|$)/i', $output ) ) {
                $video_url = $output;
            }
        }
        return [ 'status' => $status, 'video_url' => $video_url, 'progress' => $progress, 'raw' => $data ];
    }
    private function mapping_for_preset( string $preset ) : array {
        switch ( $preset ) {
            case 'seedance':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'seedance-lite':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'minimax':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'wan-fast':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'wan-720p':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'ltx-video':
                return [ 'image' => 'image', 'fps' => 'fps', 'duration' => 'duration', 'frames' => '' , 'prompt' => 'prompt' ];
            case 'svd':
                return [ 'image' => 'input_image', 'fps' => 'fps', 'frames' => 'num_frames', 'duration' => '', 'prompt' => 'prompt' ];
        }
        return [];
    }
}
