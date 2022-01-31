<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! function_exists( 'get_term_icon_id' ) ) {
	/**
	 * Get the icon attachment ID of a certain term.
	 *
	 * @param int $term_id Optional. The ID of the term to retrieve the icon of. Defaults to the current queried term.
	 * @param string $taxonomy Optional. The term's taxonomy.
	 *
	 * @return false|mixed False on failure, the icon attachment ID otherwise.
	 */
	function get_term_icon_id( $term_id = null, $taxonomy = null ) {

		if ( function_exists( 'get_term_meta' ) ) {

			if ( null === $term_id ) {
				global $wp_query;
				$term    = $wp_query->queried_object;
				$term_id = $term->term_id;

			}

			return get_term_meta( $term_id, 'pix_term_icon', true );
		}

		return false;
	}
}

if ( ! function_exists( 'get_term_icon_url' ) ) {
	/**
	 * Get the icon URL of a certain term.
	 *
	 * @param int $term_id Optional. The ID of the term to retrieve the icon of. Defaults to the current queried term.
	 * @param string $size Optional. The thumbnail size to retrieve.
	 *
	 * @return false|string False on failure, the icon URL otherwise.
	 */
	function get_term_icon_url( $term_id = null, $size = 'thumbnail' ) {

		$attachment_id = get_term_icon_id( $term_id );

		if ( ! empty( $attachment_id ) ) {
			$attach_args = wp_get_attachment_image_src( $attachment_id, $size );

			// $attach_args[0] should be the url
			if ( isset( $attach_args[0] ) ) {
				return $attach_args[0];
			}
		}

		return false;
	}
}

if ( ! function_exists( 'get_term_image_id' ) ) {
	/**
	 * Get the image attachment ID of a certain term.
	 *
	 * @param int $term_id Optional. The ID of the term to retrieve the icon of. Defaults to the current queried term.
	 * @param string $taxonomy Optional. The term's taxonomy.
	 *
	 * @return false|mixed False on failure, the image attachment ID otherwise.
	 */
	function get_term_image_id( $term_id = null, $taxonomy = null ) {

		if ( function_exists( 'get_term_meta' ) ) {

			if ( null === $term_id ) {
				global $wp_query;
				$term    = $wp_query->queried_object;
				$term_id = $term->term_id;

			}

			return get_term_meta( $term_id, 'pix_term_image', true );
		}

		return false;
	}
}

if ( ! function_exists( 'get_term_image_url' ) ) {
	/**
	 * Get the image URL of a certain term.
	 *
	 * @param int $term_id Optional. The ID of the term to retrieve the icon of. Defaults to the current queried term.
	 * @param string $size Optional. The thumbnail size to retrieve.
	 *
	 * @return false|string False on failure, the image URL otherwise.
	 */
	function get_term_image_url( $term_id = null, $size = 'thumbnail' ) {

		$attachment_id = get_term_image_id( $term_id );

		if ( ! empty( $attachment_id ) ) {
			$attach_args = wp_get_attachment_image_src( $attachment_id, $size );

			// $attach_args[0] should be the url
			if ( isset( $attach_args[0] ) ) {
				return $attach_args[0];
			}
		}

		return false;
	}
}
