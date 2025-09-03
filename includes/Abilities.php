<?php
/**
 * WordPress Abilities Registration
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo;

use WP_Error;

/**
 * Class for registering WordPress abilities.
 *
 * @since 0.1.0
 */
class Abilities {

	/**
	 * Flag to track if abilities have been registered.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register all abilities for the chatbot demo.
	 *
	 * @since 0.1.0
	 */
	public static function register_all(): void {
		error_log( "WP AI Chatbot: Abilities::register_all() called" );
		
		if ( self::$registered ) {
			error_log( "WP AI Chatbot: Abilities already registered, skipping" );
			return;
		}

		if ( ! function_exists( 'wp_register_ability' ) ) {
			error_log( "WP AI Chatbot: wp_register_ability function not available in Abilities::register_all()" );
			return;
		}
		
		error_log( "WP AI Chatbot: Starting ability registration..." );

		self::register_list_capabilities();
		self::register_get_post();
		self::register_create_post_draft();
		self::register_search_posts();
		self::register_publish_post();
		self::register_set_permalink_structure();
		self::register_generate_post_featured_image();
		self::register_list_providers();
		self::register_change_provider();
		self::register_page_content_markdown();
		self::register_configure_mcp_client();
		
		self::$registered = true;
		error_log( "WP AI Chatbot: All abilities registered successfully" );
	}

	/**
	 * Register ability to list available capabilities.
	 *
	 * @since 0.1.0
	 */
	private static function register_list_capabilities(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/list-capabilities',
			array(
				'label'            => __( 'List Available Capabilities', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Lists all available capabilities and tools that the chatbot can use.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => (object) array(), // Ensure this is always an object in JSON
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'capabilities' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'label'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback' => function () {
					$capabilities = array(
						array(
							'name'        => 'list_capabilities',
							'label'       => 'List Available Capabilities',
							'description' => 'Lists all available capabilities and tools that the chatbot can use.',
						),
						array(
							'name'        => 'get_post',
							'label'       => 'Get WordPress Post',
							'description' => 'Returns the title, content, and more information of a WordPress post for a given post ID.',
						),
						array(
							'name'        => 'create_post_draft',
							'label'       => 'Create Post Draft',
							'description' => 'Creates a new WordPress post in "draft" status.',
						),
						array(
							'name'        => 'search_posts',
							'label'       => 'Search Posts',
							'description' => 'Searches for WordPress posts based on title and content.',
						),
						array(
							'name'        => 'publish_post',
							'label'       => 'Publish Post',
							'description' => 'Publishes a WordPress post that is currently in "draft" status.',
						),
						array(
							'name'        => 'set_permalink_structure',
							'label'       => 'Set Permalink Structure',
							'description' => 'Sets the permalink structure for the WordPress site.',
						),
						array(
							'name'        => 'generate_post_featured_image',
							'label'       => 'Generate Post Featured Image',
							'description' => 'Generates a featured image for a WordPress post using AI.',
						),
						array(
							'name'        => 'list_providers',
							'label'       => 'List Available AI Providers',
							'description' => 'Lists all available AI providers that have been configured with valid API credentials.',
						),
						array(
							'name'        => 'change_provider',
							'label'       => 'Change AI Provider',
							'description' => 'Changes the current AI provider being used by the chatbot.',
						),
						array(
							'name'        => 'fetch_url_as_markdown',
							'label'       => 'Fetch URL as Markdown',
							'description' => 'Fetches any URL and converts its content to clean markdown format using Jina AI Reader.',
						),
						array(
							'name'        => 'configure_mcp_client',
							'label'       => 'Configure MCP Client',
							'description' => 'Add, update, delete, list, or test MCP client connections.',
						),
					);

					return array(
						'success'      => true,
						'capabilities' => $capabilities,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'read' );
				},
			)
		);
	}

	/**
	 * Register ability to get a WordPress post.
	 *
	 * @since 0.1.0
	 */
	private static function register_get_post(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/get-post',
			array(
				'label'            => __( 'Get WordPress Post', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Returns the title, content, and more information of a WordPress post for a given post ID.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to retrieve.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'post_title'     => array( 'type' => 'string' ),
						'post_content'   => array( 'type' => 'string' ),
						'post_status'    => array( 'type' => 'string' ),
						'post_edit_url'  => array( 'type' => 'string' ),
						'post_url'       => array( 'type' => 'string' ),
						'error'          => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'read_post', $args['post_id'] ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to read this post.',
						);
					}

					$post = get_post( $args['post_id'] );

					if ( ! $post ) {
						return array(
							'success' => false,
							'error'   => 'Post with ID ' . $args['post_id'] . ' not found.',
						);
					}

					$result = array(
						'success'       => true,
						'post_title'    => $post->post_title,
						'post_content'  => $post->post_content,
						'post_status'   => $post->post_status,
						'post_edit_url' => get_edit_post_link( $post->ID, 'raw' ),
					);

					if ( 'publish' === $post->post_status ) {
						$result['post_url'] = get_permalink( $post );
					}

					return $result;
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register ability to create a post draft.
	 *
	 * @since 0.1.0
	 */
	private static function register_create_post_draft(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/create-post-draft',
			array(
				'label'            => __( 'Create Post Draft', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Creates a new WordPress post in "draft" status.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'post_title'   => array(
							'type'        => 'string',
							'description' => 'The title of the post.',
						),
						'post_content' => array(
							'type'        => 'string',
							'description' => 'The content of the post.',
						),
						'post_excerpt' => array(
							'type'        => 'string',
							'description' => 'The excerpt of the post.',
						),
					),
					'required'   => array( 'post_title', 'post_content' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'post_id'       => array( 'type' => 'integer' ),
						'post_edit_url' => array( 'type' => 'string' ),
						'error'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'edit_posts' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to create posts.',
						);
					}

					$post_data = array(
						'post_title'   => sanitize_text_field( $args['post_title'] ),
						'post_content' => wp_kses_post( $args['post_content'] ),
						'post_status'  => 'draft',
						'post_author'  => get_current_user_id(),
					);

					if ( ! empty( $args['post_excerpt'] ) ) {
						$post_data['post_excerpt'] = sanitize_text_field( $args['post_excerpt'] );
					}

					$post_id = wp_insert_post( $post_data );

					if ( is_wp_error( $post_id ) ) {
						return array(
							'success' => false,
							'error'   => $post_id->get_error_message(),
						);
					}

					return array(
						'success'       => true,
						'post_id'       => $post_id,
						'post_edit_url' => get_edit_post_link( $post_id, 'raw' ),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register ability to search posts.
	 *
	 * @since 0.1.0
	 */
	private static function register_search_posts(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/search-posts',
			array(
				'label'            => __( 'Search Posts', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Searches for WordPress posts based on title and content.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'search_query' => array(
							'type'        => 'string',
							'description' => 'The search query to look for in post titles and content.',
						),
						'limit'        => array(
							'type'        => 'integer',
							'description' => 'The maximum number of posts to return.',
							'minimum'     => 1,
							'maximum'     => 50,
							'default'     => 10,
						),
					),
					'required'   => array( 'search_query' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'posts'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'post_id'       => array( 'type' => 'integer' ),
									'post_title'    => array( 'type' => 'string' ),
									'post_excerpt'  => array( 'type' => 'string' ),
									'post_status'   => array( 'type' => 'string' ),
									'post_edit_url' => array( 'type' => 'string' ),
									'post_url'      => array( 'type' => 'string' ),
								),
							),
						),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'read' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to search posts.',
						);
					}

					$limit = isset( $args['limit'] ) ? min( 50, max( 1, (int) $args['limit'] ) ) : 10;

					$posts = get_posts(
						array(
							's'              => sanitize_text_field( $args['search_query'] ),
							'numberposts'    => $limit,
							'post_status'    => 'publish',
							'post_type'      => 'post',
							'suppress_filters' => false,
						)
					);

					$results = array();
					foreach ( $posts as $post ) {
						$result = array(
							'post_id'       => $post->ID,
							'post_title'    => $post->post_title,
							'post_excerpt'  => get_the_excerpt( $post ),
							'post_status'   => $post->post_status,
							'post_edit_url' => get_edit_post_link( $post->ID, 'raw' ),
						);

						if ( 'publish' === $post->post_status ) {
							$result['post_url'] = get_permalink( $post );
						}

						$results[] = $result;
					}

					return array(
						'success' => true,
						'posts'   => $results,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'read' );
				},
			)
		);
	}

	/**
	 * Register ability to publish a post.
	 *
	 * @since 0.1.0
	 */
	private static function register_publish_post(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/publish-post',
			array(
				'label'            => __( 'Publish Post', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Publishes a WordPress post that is currently in "draft" status.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to publish.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'post_url' => array( 'type' => 'string' ),
						'error'    => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'publish_posts' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to publish posts.',
						);
					}

					$post = get_post( $args['post_id'] );
					if ( ! $post ) {
						return array(
							'success' => false,
							'error'   => 'Post with ID ' . $args['post_id'] . ' not found.',
						);
					}

					if ( ! current_user_can( 'edit_post', $args['post_id'] ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to edit this post.',
						);
					}

					$result = wp_update_post(
						array(
							'ID'          => $args['post_id'],
							'post_status' => 'publish',
						)
					);

					if ( is_wp_error( $result ) ) {
						return array(
							'success' => false,
							'error'   => $result->get_error_message(),
						);
					}

					return array(
						'success'  => true,
						'post_url' => get_permalink( $args['post_id'] ),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'publish_posts' );
				},
			)
		);
	}

	/**
	 * Register ability to set permalink structure.
	 *
	 * @since 0.1.0
	 */
	private static function register_set_permalink_structure(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/set-permalink-structure',
			array(
				'label'            => __( 'Set Permalink Structure', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Sets the permalink structure for the WordPress site.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'permalink_structure' => array(
							'type'        => 'string',
							'description' => 'The permalink structure to set (e.g., "/%year%/%monthnum%/%postname%/").',
							'enum'        => array(
								'',
								'/%year%/%monthnum%/%day%/%postname%/',
								'/%year%/%monthnum%/%postname%/',
								'/archives/%post_id%',
								'/%postname%/',
							),
						),
					),
					'required'   => array( 'permalink_structure' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'manage_options' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to manage site options.',
						);
					}

					$permalink_structure = sanitize_text_field( $args['permalink_structure'] );

					// Validate the permalink structure
					$allowed_structures = array(
						'',
						'/%year%/%monthnum%/%day%/%postname%/',
						'/%year%/%monthnum%/%postname%/',
						'/archives/%post_id%',
						'/%postname%/',
					);

					if ( ! in_array( $permalink_structure, $allowed_structures, true ) ) {
						return array(
							'success' => false,
							'error'   => 'Invalid permalink structure provided.',
						);
					}

					// Update the permalink structure
					update_option( 'permalink_structure', $permalink_structure );

					// Flush rewrite rules
					flush_rewrite_rules();

					$message = empty( $permalink_structure ) ? 
						'Permalink structure set to default (plain).' :
						'Permalink structure updated to: ' . $permalink_structure;

					return array(
						'success' => true,
						'message' => $message,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Register ability to generate post featured image.
	 * Note: This is a placeholder implementation - actual image generation would require AI service integration.
	 *
	 * @since 0.1.0
	 */
	private static function register_generate_post_featured_image(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/generate-post-featured-image',
			array(
				'label'            => __( 'Generate Post Featured Image', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Generates a featured image for a WordPress post using AI.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to generate a featured image for.',
						),
						'prompt'  => array(
							'type'        => 'string',
							'description' => 'Optional prompt to guide image generation. If not provided, will be derived from post content.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'attachment_id'    => array( 'type' => 'integer' ),
						'featured_image_url' => array( 'type' => 'string' ),
						'error'            => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'edit_posts' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to edit posts.',
						);
					}

					$post = get_post( $args['post_id'] );
					if ( ! $post ) {
						return array(
							'success' => false,
							'error'   => 'Post with ID ' . $args['post_id'] . ' not found.',
						);
					}

					if ( ! current_user_can( 'edit_post', $args['post_id'] ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to edit this post.',
						);
					}

					// TODO: Implement actual AI image generation
					// For now, return a placeholder response
					return array(
						'success' => false,
						'error'   => 'Image generation feature not yet implemented. This would integrate with an AI image generation service.',
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register ability to list available providers.
	 *
	 * @since 0.1.0
	 */
	private static function register_list_providers(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/list-providers',
			array(
				'label'            => __( 'List Available AI Providers', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Lists all available AI providers that have been configured with valid API credentials.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => (object) array(), // No input required
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'current_provider' => array( 'type' => 'string' ),
						'providers'        => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'is_current'  => array( 'type' => 'boolean' ),
								),
							),
						),
						'error'            => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to view provider settings.',
						);
					}

					// Get the provider manager instance
					$provider_manager = new \Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager(
						array( 'anthropic', 'google', 'openai' )
					);
					$provider_manager->initialize_provider_credentials();

					$available_provider_ids = $provider_manager->get_available_provider_ids();
					$current_provider_id    = $provider_manager->get_current_provider_id();

					if ( empty( $available_provider_ids ) ) {
						return array(
							'success' => false,
							'error'   => 'No AI providers are configured. Please add API credentials in the AI Settings page.',
						);
					}

					$providers = array();
					foreach ( $available_provider_ids as $provider_id ) {
						try {
							$metadata = $provider_manager->get_provider_metadata( $provider_id );
							$providers[] = array(
								'id'          => $provider_id,
								'name'        => $metadata->getName(),
								'description' => $metadata->getName() . ' AI Provider', // No getDescription method available
								'is_current'  => ( $provider_id === $current_provider_id ),
							);
						} catch ( \Exception $e ) {
							error_log( 'WP AI SDK: Failed to get provider metadata for ' . $provider_id . ': ' . $e->getMessage() );
						}
					}

					return array(
						'success'          => true,
						'current_provider' => $current_provider_id,
						'providers'        => $providers,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Register ability to change the current AI provider.
	 *
	 * @since 0.1.0
	 */
	private static function register_change_provider(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/change-provider',
			array(
				'label'            => __( 'Change AI Provider', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Changes the current AI provider being used by the chatbot.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'provider_id' => array(
							'type'        => 'string',
							'description' => 'The ID of the AI provider to switch to (e.g., "anthropic", "google", "openai").',
						),
					),
					'required'   => array( 'provider_id' ),
				),
				'output_schema'    => array(
					'$schema'    => 'http://json-schema.org/draft-07/schema#',
					'type'       => 'object',
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'message'          => array( 'type' => 'string' ),
						'provider_name'    => array( 'type' => 'string' ),
						'previous_provider' => array( 'type' => 'string' ),
						'error'            => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => function ( $args ) {
					if ( ! current_user_can( 'manage_options' ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to change provider settings.',
						);
					}

					$provider_id = sanitize_text_field( $args['provider_id'] );

					// Get the provider manager instance
					$provider_manager = new \Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager(
						array( 'anthropic', 'google', 'openai' )
					);
					$provider_manager->initialize_provider_credentials();

					$available_provider_ids = $provider_manager->get_available_provider_ids();
					$previous_provider_id   = $provider_manager->get_current_provider_id();

					// Check if the provider is available
					if ( ! in_array( $provider_id, $available_provider_ids, true ) ) {
						return array(
							'success' => false,
							'error'   => sprintf(
								'The provider "%s" is not available. Available providers: %s',
								$provider_id,
								implode( ', ', $available_provider_ids )
							),
						);
					}

					// Check if it's already the current provider
					if ( $provider_id === $previous_provider_id ) {
						try {
							$metadata = $provider_manager->get_provider_metadata( $provider_id );
							$provider_name = $metadata->getName();
						} catch ( \Exception $e ) {
							error_log( 'WP AI SDK: Failed to get provider metadata for ' . $provider_id . ': ' . $e->getMessage() );
							$provider_name = ucfirst( $provider_id );
						}
						return array(
							'success'       => true,
							'message'       => sprintf( '%s is already the current provider.', $provider_name ),
							'provider_name' => $provider_name,
						);
					}

					// Update the current provider
					update_option( 'wpaisdk_current_provider', $provider_id );

					try {
						$metadata = $provider_manager->get_provider_metadata( $provider_id );
					} catch ( \Exception $e ) {
						error_log( 'WP AI SDK: Failed to get provider metadata for ' . $provider_id . ': ' . $e->getMessage() );
						$metadata = null;
					}
					
					$previous_metadata = null;
					if ( $previous_provider_id ) {
						try {
							$previous_metadata = $provider_manager->get_provider_metadata( $previous_provider_id );
						} catch ( \Exception $e ) {
							error_log( 'WP AI SDK: Failed to get previous provider metadata for ' . $previous_provider_id . ': ' . $e->getMessage() );
						}
					}

					$provider_name = $metadata ? $metadata->getName() : ucfirst( $provider_id );
					$previous_provider_name = $previous_metadata ? $previous_metadata->getName() : 
						( $previous_provider_id ? ucfirst( $previous_provider_id ) : '' );
					
					return array(
						'success'           => true,
						'message'           => sprintf(
							'Successfully switched to %s.',
							$provider_name
						),
						'provider_name'     => $provider_name,
						'previous_provider' => $previous_provider_name,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Register ability to fetch URL as markdown.
	 *
	 * @since 0.1.0
	 */
	private static function register_page_content_markdown(): void {
		// Only register if Jina API key is configured
		$api_key = get_option( 'wpaisdk_jina_api_key' );
		if ( empty( $api_key ) && ! defined( 'JINA_API_KEY' ) ) {
			return;
		}
		
		wp_register_ability(
			'wp/fetch-url-as-markdown',
			array(
				'label'            => __( 'Fetch URL as Markdown', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Fetches any URL and converts its content to clean markdown format using Jina AI Reader. Perfect for reading articles, documentation, or any web content.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'type'       => 'object',
					'properties' => array(
						'url' => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => __( 'The URL of the page to fetch', 'wp-ai-sdk-chatbot-demo' ),
						),
					),
					'required'   => array( 'url' ),
					'additionalProperties' => false,
				),
				'output_schema'    => array(
					'type'       => 'object',
					'properties' => array(
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
				'execute_callback' => function( $input ) {
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

					// Get API key from options or environment
					$api_key = get_option( 'wpaisdk_jina_api_key' );
					
					if ( empty( $api_key ) && defined( 'JINA_API_KEY' ) ) {
						$api_key = JINA_API_KEY;
					}
					
					if ( empty( $api_key ) ) {
						return new \WP_Error(
							'missing_api_key',
							__( 'Jina AI API key is not configured. Please add it in the AI Settings page.', 'wp-ai-sdk-chatbot-demo' )
						);
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
					$lines = explode( "\n", $body );
					$title = '';
					$content = $body;
					$source_url = '';
					
					// Check if first line starts with "Title:"
					if ( ! empty( $lines ) && 0 === strpos( $lines[0], 'Title:' ) ) {
						$title = trim( substr( $lines[0], 6 ) );
						array_shift( $lines );
					}
					
					// Check if there's a URL Source line
					if ( ! empty( $lines ) && 0 === strpos( $lines[0], 'URL Source:' ) ) {
						$source_url = trim( substr( $lines[0], 11 ) );
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
				},
				'permission_callback' => function( $input ) {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Register ability to configure MCP clients.
	 *
	 * @since 0.1.0
	 */
	private static function register_configure_mcp_client(): void {
		wp_register_ability(
			'wp-ai-chatbot-demo/configure-mcp-client',
			array(
				'label'            => __( 'Configure MCP Client', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => __( 'Add, update, delete, list, or test MCP client connections.', 'wp-ai-sdk-chatbot-demo' ),
				'input_schema'     => array(
					'type'       => 'object',
					'properties' => array(
						'action' => array(
							'type'        => 'string',
							'enum'        => array( 'add', 'update', 'delete', 'list', 'test' ),
							'description' => 'The action to perform on MCP clients',
						),
						'client_id' => array(
							'type'        => 'string',
							'description' => 'The client ID (required for update, delete, test)',
						),
						'config' => array(
							'type'       => 'object',
							'properties' => array(
								'name'       => array(
									'type'        => 'string',
									'description' => 'Display name for the client',
								),
								'server_url' => array(
									'type'        => 'string',
									'description' => 'MCP server URL',
								),
								'api_key'    => array(
									'type'        => 'string',
									'description' => 'API key for authentication (optional)',
								),
								'enabled'    => array(
									'type'        => 'boolean',
									'description' => 'Whether the client is enabled',
								),
							),
							'description' => 'Client configuration (required for add, update)',
						),
					),
					'required' => array( 'action' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'data'    => array( 'type' => 'object' ),
					),
				),
				'execute_callback' => function( $args ) {
					if ( ! current_user_can( 'manage_options' ) ) {
						return array(
							'success' => false,
							'message' => 'You do not have permission to manage MCP clients.',
						);
					}

					$action = $args['action'];
					$mcp_clients = get_option( 'wpaisdk_mcp_clients', array() );
					
					if ( ! is_array( $mcp_clients ) ) {
						$mcp_clients = array();
					}

					switch ( $action ) {
						case 'list':
							$clients_list = array();
							foreach ( $mcp_clients as $id => $config ) {
								$clients_list[] = array(
									'client_id'  => $id,
									'name'       => $config['name'] ?? '',
									'server_url' => $config['server_url'] ?? '',
									'enabled'    => ! empty( $config['enabled'] ),
								);
							}
							return array(
								'success' => true,
								'message' => sprintf( 'Found %d MCP clients', count( $clients_list ) ),
								'data'    => array( 'clients' => $clients_list ),
							);

						case 'add':
							if ( empty( $args['config'] ) ) {
								return array(
									'success' => false,
									'message' => 'Configuration is required for adding a client',
								);
							}
							
							$config = $args['config'];
							if ( empty( $config['server_url'] ) ) {
								return array(
									'success' => false,
									'message' => 'Server URL is required',
								);
							}
							
							// Generate a new client ID
							$client_id = 'client_' . count( $mcp_clients );
							while ( isset( $mcp_clients[ $client_id ] ) ) {
								$client_id = 'client_' . ( intval( substr( $client_id, 7 ) ) + 1 );
							}
							
							$mcp_clients[ $client_id ] = array(
								'name'       => sanitize_text_field( $config['name'] ?? 'MCP Client' ),
								'server_url' => esc_url_raw( $config['server_url'] ),
								'api_key'    => sanitize_text_field( $config['api_key'] ?? '' ),
								'enabled'    => ! empty( $config['enabled'] ),
								'transport'  => 'mcp',
							);
							
							update_option( 'wpaisdk_mcp_clients', $mcp_clients );
							
							return array(
								'success' => true,
								'message' => sprintf( 'MCP client "%s" added successfully', $mcp_clients[ $client_id ]['name'] ),
								'data'    => array( 'client_id' => $client_id ),
							);

						case 'update':
							if ( empty( $args['client_id'] ) || empty( $args['config'] ) ) {
								return array(
									'success' => false,
									'message' => 'Client ID and configuration are required for updating',
								);
							}
							
							$client_id = $args['client_id'];
							if ( ! isset( $mcp_clients[ $client_id ] ) ) {
								return array(
									'success' => false,
									'message' => sprintf( 'Client "%s" not found', $client_id ),
								);
							}
							
							$config = $args['config'];
							if ( isset( $config['name'] ) ) {
								$mcp_clients[ $client_id ]['name'] = sanitize_text_field( $config['name'] );
							}
							if ( isset( $config['server_url'] ) ) {
								$mcp_clients[ $client_id ]['server_url'] = esc_url_raw( $config['server_url'] );
							}
							if ( isset( $config['api_key'] ) ) {
								$mcp_clients[ $client_id ]['api_key'] = sanitize_text_field( $config['api_key'] );
							}
							if ( isset( $config['enabled'] ) ) {
								$mcp_clients[ $client_id ]['enabled'] = ! empty( $config['enabled'] );
							}
							
							update_option( 'wpaisdk_mcp_clients', $mcp_clients );
							
							return array(
								'success' => true,
								'message' => sprintf( 'MCP client "%s" updated successfully', $mcp_clients[ $client_id ]['name'] ),
								'data'    => array( 'client_id' => $client_id ),
							);

						case 'delete':
							if ( empty( $args['client_id'] ) ) {
								return array(
									'success' => false,
									'message' => 'Client ID is required for deletion',
								);
							}
							
							$client_id = $args['client_id'];
							if ( ! isset( $mcp_clients[ $client_id ] ) ) {
								return array(
									'success' => false,
									'message' => sprintf( 'Client "%s" not found', $client_id ),
								);
							}
							
							$client_name = $mcp_clients[ $client_id ]['name'];
							unset( $mcp_clients[ $client_id ] );
							
							update_option( 'wpaisdk_mcp_clients', $mcp_clients );
							
							return array(
								'success' => true,
								'message' => sprintf( 'MCP client "%s" deleted successfully', $client_name ),
								'data'    => array( 'client_id' => $client_id ),
							);

						case 'test':
							if ( empty( $args['client_id'] ) ) {
								return array(
									'success' => false,
									'message' => 'Client ID is required for testing',
								);
							}
							
							$client_id = $args['client_id'];
							if ( ! isset( $mcp_clients[ $client_id ] ) ) {
								return array(
									'success' => false,
									'message' => sprintf( 'Client "%s" not found', $client_id ),
								);
							}
							
							// Use the MCP_Client_Manager to test the connection
							$mcp_manager = new \Felix_Arntz\WP_AI_SDK_Chatbot_Demo\MCP\MCP_Client_Manager();
							$test_result = $mcp_manager->test_connection( $client_id, $mcp_clients[ $client_id ] );
							
							return array(
								'success' => $test_result['success'],
								'message' => $test_result['message'],
								'data'    => array( 'client_id' => $client_id ),
							);

						default:
							return array(
								'success' => false,
								'message' => sprintf( 'Invalid action: %s', $action ),
							);
					}
				},
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
}