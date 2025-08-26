<?php
/**
 * WordPress AI SDK Chatbot Demo Abilities Registration
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo;

use WP_Error;

/**
 * Register all chatbot abilities on abilities_api_init hook
 */
add_action( 'abilities_api_init', function() {
	register_chatbot_abilities();
});

/**
 * Register all abilities for the chatbot demo
 */
function register_chatbot_abilities() {
	// Create Post Draft Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/create-post-draft', [
		'label' => 'Create Post Draft',
		'description' => 'Creates a new WordPress post in "draft" status.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'post_title' => [
					'type' => 'string',
					'description' => 'The title of the post.',
				],
				'post_content' => [
					'type' => 'string',
					'description' => 'The content of the post, as HTML. Never include any class or style attributes. Allowed HTML tags are: a, br, code, em, h2, h3, h4, h5, h6, li, ol, p, strong, ul',
				],
			],
			'required' => [ 'post_title', 'post_content' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the created post.',
				],
				'post_edit_url' => [
					'type' => 'string',
					'description' => 'The URL to edit the post.',
				],
				'message' => [
					'type' => 'string',
					'description' => 'Success message.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to create posts.'
				);
			}

			$post_data = [
				'post_title' => $input['post_title'],
				'post_content' => $input['post_content'],
				'post_status' => 'draft',
				'post_type' => 'post',
			];

			$post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			return [
				'post_id' => $post_id,
				'post_edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'message' => 'Post draft created successfully.',
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'edit_posts' );
		},
	]);

	// Generate Post Featured Image Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/generate-post-featured-image', [
		'label' => 'Generate Post Featured Image',
		'description' => 'Generates a featured image for a WordPress post using AI image generation.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the post to generate a featured image for.',
				],
				'image_prompt' => [
					'type' => 'string',
					'description' => 'The prompt to use for generating the image.',
				],
			],
			'required' => [ 'post_id', 'image_prompt' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'attachment_id' => [
					'type' => 'integer',
					'description' => 'The ID of the generated featured image attachment.',
				],
				'attachment_url' => [
					'type' => 'string',
					'description' => 'The URL of the generated featured image.',
				],
				'message' => [
					'type' => 'string',
					'description' => 'Success message.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to edit posts.'
				);
			}

			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error(
					'post_not_found',
					'The specified post was not found.'
				);
			}

			// Use AI SDK to generate image (placeholder implementation)
			// This would need to be implemented with actual AI image generation
			$image_data = generate_ai_image( $input['image_prompt'] );
			
			if ( is_wp_error( $image_data ) ) {
				return $image_data;
			}

			$attachment_id = wp_insert_attachment( $image_data, '', $input['post_id'] );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			set_post_thumbnail( $input['post_id'], $attachment_id );

			return [
				'attachment_id' => $attachment_id,
				'attachment_url' => wp_get_attachment_url( $attachment_id ),
				'message' => 'Featured image generated and set successfully.',
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'edit_posts' );
		},
	]);

	// Get Post Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/get-post', [
		'label' => 'Get Post',
		'description' => 'Retrieves a WordPress post by its ID.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the post to retrieve.',
				],
			],
			'required' => [ 'post_id' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the post.',
				],
				'post_title' => [
					'type' => 'string',
					'description' => 'The title of the post.',
				],
				'post_content' => [
					'type' => 'string',
					'description' => 'The content of the post.',
				],
				'post_status' => [
					'type' => 'string',
					'description' => 'The status of the post.',
				],
				'post_url' => [
					'type' => 'string',
					'description' => 'The URL of the post.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error(
					'post_not_found',
					'The specified post was not found.'
				);
			}

			if ( ! current_user_can( 'read_post', $input['post_id'] ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to read this post.'
				);
			}

			return [
				'post_id' => $post->ID,
				'post_title' => $post->post_title,
				'post_content' => $post->post_content,
				'post_status' => $post->post_status,
				'post_url' => get_permalink( $post->ID ),
			];
		},
		'permission_callback' => function( array $input = [] ) {
			if ( empty( $input['post_id'] ) ) {
				return false;
			}
			return current_user_can( 'read_post', $input['post_id'] );
		},
	]);

	// Publish Post Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/publish-post', [
		'label' => 'Publish Post',
		'description' => 'Changes the status of a WordPress post to "publish".',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the post to publish.',
				],
			],
			'required' => [ 'post_id' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'post_id' => [
					'type' => 'integer',
					'description' => 'The ID of the published post.',
				],
				'post_url' => [
					'type' => 'string',
					'description' => 'The URL of the published post.',
				],
				'message' => [
					'type' => 'string',
					'description' => 'Success message.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'publish_posts' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to publish posts.'
				);
			}

			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error(
					'post_not_found',
					'The specified post was not found.'
				);
			}

			$result = wp_update_post( [
				'ID' => $input['post_id'],
				'post_status' => 'publish',
			], true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return [
				'post_id' => $input['post_id'],
				'post_url' => get_permalink( $input['post_id'] ),
				'message' => 'Post published successfully.',
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'publish_posts' );
		},
	]);

	// Search Posts Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/search-posts', [
		'label' => 'Search Posts',
		'description' => 'Searches for WordPress posts based on a search term.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'search_term' => [
					'type' => 'string',
					'description' => 'The term to search for in post titles and content.',
				],
				'post_status' => [
					'type' => 'string',
					'description' => 'The post status to filter by (optional).',
					'enum' => [ 'publish', 'draft', 'private' ],
				],
				'posts_per_page' => [
					'type' => 'integer',
					'description' => 'The number of posts to return (default: 10, max: 50).',
					'minimum' => 1,
					'maximum' => 50,
				],
			],
			'required' => [ 'search_term' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'posts' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'post_id' => [ 'type' => 'integer' ],
							'post_title' => [ 'type' => 'string' ],
							'post_excerpt' => [ 'type' => 'string' ],
							'post_status' => [ 'type' => 'string' ],
							'post_url' => [ 'type' => 'string' ],
						],
					],
				],
				'found_posts' => [
					'type' => 'integer',
					'description' => 'Total number of posts found.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			$query_args = [
				's' => sanitize_text_field( $input['search_term'] ),
				'post_type' => 'post',
				'posts_per_page' => min( $input['posts_per_page'] ?? 10, 50 ),
			];

			if ( ! empty( $input['post_status'] ) ) {
				$query_args['post_status'] = $input['post_status'];
			}

			$query = new \WP_Query( $query_args );
			$posts = [];

			foreach ( $query->posts as $post ) {
				if ( ! current_user_can( 'read_post', $post->ID ) ) {
					continue;
				}

				$posts[] = [
					'post_id' => $post->ID,
					'post_title' => $post->post_title,
					'post_excerpt' => get_the_excerpt( $post ),
					'post_status' => $post->post_status,
					'post_url' => get_permalink( $post->ID ),
				];
			}

			return [
				'posts' => $posts,
				'found_posts' => $query->found_posts,
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'read' );
		},
	]);

	// Set Permalink Structure Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/set-permalink-structure', [
		'label' => 'Set Permalink Structure',
		'description' => 'Sets the permalink structure for the WordPress site.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'permalink_structure' => [
					'type' => 'string',
					'description' => 'The permalink structure to set. Common structures: "/%postname%/" for post name, "/%year%/%monthnum%/%day%/%postname%/" for date and name, or "" for plain.',
				],
			],
			'required' => [ 'permalink_structure' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'permalink_structure' => [
					'type' => 'string',
					'description' => 'The new permalink structure.',
				],
				'message' => [
					'type' => 'string',
					'description' => 'Success message.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to manage site options.'
				);
			}

			$permalink_structure = sanitize_text_field( $input['permalink_structure'] );
			
			// Validate permalink structure
			$allowed_structures = [
				'',
				'/%postname%/',
				'/%year%/%monthnum%/%day%/%postname%/',
				'/%year%/%monthnum%/%postname%/',
				'/%category%/%postname%/',
			];

			if ( ! in_array( $permalink_structure, $allowed_structures, true ) ) {
				return new WP_Error(
					'invalid_permalink_structure',
					'Invalid permalink structure provided.'
				);
			}

			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( $permalink_structure );
			$wp_rewrite->flush_rules();

			return [
				'permalink_structure' => $permalink_structure,
				'message' => 'Permalink structure updated successfully.',
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'manage_options' );
		},
	]);

	// Change AI Provider Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/change-ai-provider', [
		'label' => 'Change AI Provider',
		'description' => 'Changes the current AI provider used by the chatbot (Anthropic, Google, OpenAI).',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'provider_id' => [
					'type' => 'string',
					'description' => 'The provider ID to switch to.',
					'enum' => [ 'anthropic', 'google', 'openai' ],
				],
			],
			'required' => [ 'provider_id' ],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'provider_id' => [
					'type' => 'string',
					'description' => 'The new current provider ID.',
				],
				'provider_name' => [
					'type' => 'string',
					'description' => 'The display name of the new provider.',
				],
				'message' => [
					'type' => 'string',
					'description' => 'Success message.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to change AI provider settings.'
				);
			}

			$provider_id = sanitize_text_field( $input['provider_id'] );
			
			// Get the provider manager from the plugin main instance
			global $wp_ai_sdk_chatbot_demo;
			if ( ! $wp_ai_sdk_chatbot_demo ) {
				return new WP_Error(
					'plugin_not_initialized',
					'Plugin not properly initialized.'
				);
			}

			$provider_manager = $wp_ai_sdk_chatbot_demo->get_provider_manager();
			$available_providers = $provider_manager->get_available_provider_ids();

			if ( ! in_array( $provider_id, $available_providers, true ) ) {
				return new WP_Error(
					'provider_not_available',
					sprintf(
						'Provider "%s" is not available. Available providers: %s',
						$provider_id,
						implode( ', ', $available_providers )
					)
				);
			}

			// Update the current provider option
			$updated = update_option( 'wpaisdk_current_provider', $provider_id );
			
			if ( ! $updated ) {
				return new WP_Error(
					'update_failed',
					'Failed to update the current provider setting.'
				);
			}

			// Get provider display name
			$registry = $provider_manager->get_registry();
			$provider_class_name = $registry->getProviderClassName( $provider_id );
			$provider_name = $provider_class_name::metadata()->getName();

			return [
				'provider_id' => $provider_id,
				'provider_name' => $provider_name,
				'message' => sprintf( 'AI provider changed to %s successfully.', $provider_name ),
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'manage_options' );
		},
	]);

	// Get Available AI Providers Ability
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/get-available-providers', [
		'label' => 'Get Available AI Providers',
		'description' => 'Lists all available AI providers that have valid API credentials configured.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'current_provider' => [
					'type' => 'object',
					'properties' => [
						'id' => [ 'type' => 'string' ],
						'name' => [ 'type' => 'string' ],
					],
				],
				'available_providers' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'id' => [ 'type' => 'string' ],
							'name' => [ 'type' => 'string' ],
							'model' => [ 'type' => 'string' ],
						],
					],
				],
			],
		],
		'execute_callback' => function( array $input ) {
			// Get the provider manager from the plugin main instance
			global $wp_ai_sdk_chatbot_demo;
			if ( ! $wp_ai_sdk_chatbot_demo ) {
				return new WP_Error(
					'plugin_not_initialized',
					'Plugin not properly initialized.'
				);
			}

			$provider_manager = $wp_ai_sdk_chatbot_demo->get_provider_manager();
			$registry = $provider_manager->get_registry();
			$available_providers = $provider_manager->get_available_provider_ids();
			$current_provider_id = $provider_manager->get_current_provider_id();

			$providers_data = [];
			foreach ( $available_providers as $provider_id ) {
				$provider_class_name = $registry->getProviderClassName( $provider_id );
				$provider_name = $provider_class_name::metadata()->getName();
				$model_id = $provider_manager->get_preferred_model_id( $provider_id );
				
				$providers_data[] = [
					'id' => $provider_id,
					'name' => $provider_name,
					'model' => $model_id,
				];
			}

			$current_provider = null;
			if ( $current_provider_id ) {
				$provider_class_name = $registry->getProviderClassName( $current_provider_id );
				$current_provider = [
					'id' => $current_provider_id,
					'name' => $provider_class_name::metadata()->getName(),
				];
			}

			return [
				'current_provider' => $current_provider,
				'available_providers' => $providers_data,
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'read' );
		},
	]);

	// List All Abilities
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/list-abilities', [
		'label' => 'List Available Abilities',
		'description' => 'Lists all available abilities that the chatbot can perform, showing what the chatbot is capable of doing.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'abilities' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => [
							'name' => [ 'type' => 'string' ],
							'label' => [ 'type' => 'string' ],
							'description' => [ 'type' => 'string' ],
						],
					],
				],
				'total_count' => [
					'type' => 'integer',
					'description' => 'Total number of available abilities.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! function_exists( 'wp_get_abilities' ) ) {
				return new WP_Error(
					'abilities_api_unavailable',
					'Abilities API is not available.'
				);
			}

			$all_abilities = wp_get_abilities();
			$chatbot_abilities = [];

			foreach ( $all_abilities as $ability ) {
				// Only include abilities registered by this chatbot
				if ( strpos( $ability->get_name(), 'wp-ai-sdk-chatbot-demo/' ) === 0 ) {
					$chatbot_abilities[] = [
						'name' => $ability->get_name(),
						'label' => $ability->get_label(),
						'description' => $ability->get_description(),
					];
				}
			}

			return [
				'abilities' => $chatbot_abilities,
				'total_count' => count( $chatbot_abilities ),
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'read' );
		},
	]);

	// Read Error Logs
	\wp_register_ability( 'wp-ai-sdk-chatbot-demo/read-error-logs', [
		'label' => 'Read WordPress Error Logs',
		'description' => 'Reads and displays recent WordPress error log entries to help diagnose issues.',
		'input_schema' => [
			'type' => 'object',
			'properties' => [
				'lines' => [
					'type' => 'integer',
					'description' => 'Number of recent log lines to read (default: 50, max: 200).',
					'minimum' => 1,
					'maximum' => 200,
					'default' => 50,
				],
			],
		],
		'output_schema' => [
			'type' => 'object',
			'properties' => [
				'log_entries' => [
					'type' => 'array',
					'items' => [
						'type' => 'string',
					],
					'description' => 'Array of log entries.',
				],
				'log_file_path' => [
					'type' => 'string',
					'description' => 'Path to the log file that was read.',
				],
				'total_lines' => [
					'type' => 'integer',
					'description' => 'Number of lines returned.',
				],
			],
		],
		'execute_callback' => function( array $input ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'insufficient_capabilities',
					'You do not have permission to read error logs.'
				);
			}

			$lines = min( $input['lines'] ?? 50, 200 );
			
			// Common WordPress log file locations
			$possible_log_files = [
				WP_CONTENT_DIR . '/debug.log',
				ABSPATH . 'wp-content/debug.log',
				ABSPATH . 'debug.log',
				ini_get( 'error_log' ),
			];

			// Find the first existing log file
			$log_file = null;
			foreach ( $possible_log_files as $file ) {
				if ( $file && file_exists( $file ) && is_readable( $file ) ) {
					$log_file = $file;
					break;
				}
			}

			if ( ! $log_file ) {
				return new WP_Error(
					'log_file_not_found',
					'No readable error log file found. Common locations checked: ' . implode( ', ', array_filter( $possible_log_files ) )
				);
			}

			// Read the last N lines efficiently
			$log_entries = [];
			$file_handle = fopen( $log_file, 'r' );
			
			if ( ! $file_handle ) {
				return new WP_Error(
					'log_file_read_error',
					'Could not open log file for reading.'
				);
			}

			// For large files, read from the end
			fseek( $file_handle, 0, SEEK_END );
			$file_size = ftell( $file_handle );
			
			if ( $file_size > 0 ) {
				// Read in chunks from the end to get last N lines
				$chunk_size = min( $file_size, 8192 );
				$buffer = '';
				$lines_found = 0;
				$pos = $file_size;
				
				while ( $lines_found < $lines && $pos > 0 ) {
					$read_size = min( $chunk_size, $pos );
					$pos -= $read_size;
					
					fseek( $file_handle, $pos );
					$chunk = fread( $file_handle, $read_size );
					$buffer = $chunk . $buffer;
					
					$lines_found = substr_count( $buffer, "\n" );
				}
				
				$all_lines = explode( "\n", $buffer );
				$log_entries = array_slice( array_filter( $all_lines ), -$lines );
			}
			
			fclose( $file_handle );

			return [
				'log_entries' => $log_entries,
				'log_file_path' => $log_file,
				'total_lines' => count( $log_entries ),
			];
		},
		'permission_callback' => function( array $input = [] ) {
			return current_user_can( 'manage_options' );
		},
	]);
}

/**
 * Placeholder function for AI image generation
 * This would need to be implemented with actual AI image generation service
 */
function generate_ai_image( string $prompt ) {
	// This is a placeholder - would need actual AI image generation implementation
	return new WP_Error(
		'not_implemented',
		'AI image generation not yet implemented.'
	);
}