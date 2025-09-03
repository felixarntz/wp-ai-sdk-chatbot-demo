<?php
/**
 * Page Content to Markdown Ability
 *
 * Retrieves page content and converts it to markdown using Jina AI Reader.
 *
 * @package WP_AI_SDK_Chatbot_Demo
 * @subpackage Abilities
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace WP_AI_SDK_Chatbot_Demo\Abilities;

/**
 * Gets page content as markdown using Jina AI Reader.
 *
 * @since 0.1.0
 *
 * @param array<string,mixed> $input The input containing the URL to fetch.
 * @return array<string,mixed>|\WP_Error The markdown content and metadata, or WP_Error on failure.
 */
function get_page_content_markdown( array $input = array() ) {
	// Validate URL
	if ( empty( $input['url'] ) ) {
		return new \WP_Error(
			'missing_url',
			__( 'URL is required', 'wp-ai-sdk-chatbot-demo' )
		);
	}

	$url = $input['url'];
	
	// Validate URL format
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return new \WP_Error(
			'invalid_url',
			__( 'Invalid URL provided', 'wp-ai-sdk-chatbot-demo' )
		);
	}

	// Get API key from environment or options
	$api_key = defined( 'JINA_API_KEY' ) ? JINA_API_KEY : get_option( 'jina_api_key' );
	
	if ( empty( $api_key ) ) {
		// Use the default key from the example for now
		// In production, this should be configured properly
		$api_key = 'jina_57d1a8b1f2c34a99a5451124f5873fe02SsHc6i-EttQtsJ_OEpzuPuXqTuo';
	}

	// Construct Jina AI Reader URL
	$jina_url = 'https://r.jina.ai/' . $url;

	// Make the request
	$response = wp_remote_get( $jina_url, array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
		),
	) );

	// Handle errors
	if ( is_wp_error( $response ) ) {
		return new \WP_Error(
			'request_failed',
			sprintf( __( 'Failed to fetch content: %s', 'wp-ai-sdk-chatbot-demo' ), $response->get_error_message() )
		);
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	
	if ( 200 !== $response_code ) {
		return new \WP_Error(
			'request_error',
			sprintf( __( 'Request failed with status code: %d', 'wp-ai-sdk-chatbot-demo' ), $response_code )
		);
	}

	$body = wp_remote_retrieve_body( $response );
	
	if ( empty( $body ) ) {
		return new \WP_Error(
			'empty_response',
			__( 'Received empty response from Jina AI', 'wp-ai-sdk-chatbot-demo' )
		);
	}

	// Parse the markdown content
	// Jina AI returns markdown directly in the response body
	// We'll extract the title if present
	$lines = explode( "\n", $body );
	$title = '';
	$content = $body;
	$source_url = '';
	
	// Check if first line starts with "Title:"
	if ( ! empty( $lines ) && 0 === strpos( $lines[0], 'Title:' ) ) {
		$title = trim( substr( $lines[0], 6 ) );
		// Remove the title line from content
		array_shift( $lines );
	}
	
	// Check if there's a URL Source line (after removing title)
	if ( ! empty( $lines ) && 0 === strpos( $lines[0], 'URL Source:' ) ) {
		$source_url = trim( substr( $lines[0], 11 ) );
		// Remove the source line from content
		array_shift( $lines );
	}
	
	// Check for "Markdown Content:" header and remove it
	if ( ! empty( $lines ) && 0 === strpos( trim( $lines[0] ), 'Markdown Content:' ) ) {
		array_shift( $lines );
	}
	
	// Rebuild content without the metadata lines
	if ( count( $lines ) !== count( explode( "\n", $body ) ) ) {
		$content = implode( "\n", $lines );
	}

	return array(
		'title'      => $title,
		'url'        => $url,
		'source_url' => $source_url ?: $url,
		'content'    => trim( $content ),
		'format'     => 'markdown',
	);
}

/**
 * Register the page content markdown ability.
 *
 * @since 0.1.0
 */
function register() {
	wp_register_ability( 'wp/get-page-content-markdown', array(
		'label'               => __( 'Get Page Content as Markdown', 'wp-ai-sdk-chatbot-demo' ),
		'description'         => __( 'Retrieves the content of a web page and converts it to markdown format using Jina AI Reader.', 'wp-ai-sdk-chatbot-demo' ),
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'url' => array(
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'The URL of the page to fetch', 'wp-ai-sdk-chatbot-demo' ),
				),
			),
			'required'             => array( 'url' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'        => 'object',
			'properties'  => array(
				'title'      => array(
					'type'        => 'string',
					'description' => __( 'The page title', 'wp-ai-sdk-chatbot-demo' ),
				),
				'url'        => array(
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'The original URL', 'wp-ai-sdk-chatbot-demo' ),
				),
				'source_url' => array(
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'The source URL from the page', 'wp-ai-sdk-chatbot-demo' ),
				),
				'content'    => array(
					'type'        => 'string',
					'description' => __( 'The page content in markdown format', 'wp-ai-sdk-chatbot-demo' ),
				),
				'format'     => array(
					'type'        => 'string',
					'enum'        => array( 'markdown' ),
					'description' => __( 'The content format', 'wp-ai-sdk-chatbot-demo' ),
				),
			),
		),
		'execute_callback'    => __NAMESPACE__ . '\\get_page_content_markdown',
		'permission_callback' => function( $input ) {
			// Allow logged-in users with edit_posts capability
			return current_user_can( 'edit_posts' );
		},
		'meta'                => array(
			'category'    => 'content',
			'api_version' => '1.0',
		),
	) );
}

// Register the ability when the API is ready
add_action( 'abilities_api_init', __NAMESPACE__ . '\\register' );