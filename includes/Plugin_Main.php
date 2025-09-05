<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Plugin_Main
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Abilities_Registrar;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\REST_Routes\Chatbot_Messages_REST_Route;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

/**
 * Plugin main class.
 *
 * @since 0.1.0
 */
class Plugin_Main {

	/**
	 * Plugin main file path.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $main_file;

	/**
	 * The provider manager instance.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 */
	public function __construct( string $main_file ) {
		$this->main_file        = $main_file;
		$this->provider_manager = new Provider_Manager( array( 'anthropic', 'google', 'openai' ) );
	}

	/**
	 * Initializes the plugin functionality by adding WordPress hooks.
	 *
	 * @since 0.1.0
	 */
	public function add_hooks(): void {
		// Add hook to handle custom capability.
		add_action(
			'plugins_loaded',
			static function () {
				/**
				 * Filters the WordPress base capability needed to be granted the 'wpaisdk_access_chatbot' capability.
				 *
				 * @since 0.1.0
				 *
				 * @param string $capability WordPress base capability (default 'manage_options').
				 */
				$base_capability = (string) apply_filters( 'wpaisdk_access_chatbot_necessary_base_capability', 'manage_options' );
				if ( ! $base_capability ) {
					// Returning an empty string disables this, allowing for custom grant logic outside of this plugin.
					return;
				}

				add_filter(
					'user_has_cap',
					static function ( $allcaps ) use ( $base_capability ) {
						if ( isset( $allcaps[ $base_capability ] ) ) {
							$allcaps['wpaisdk_access_chatbot'] = $allcaps[ $base_capability ];
						}
						return $allcaps;
					}
				);
			}
		);

		// Removed Abilities_Registrar - using Abilities::register_all() instead

		// Add hooks to initialize provider configuration settings and related WP Admin UI.
		// Initialize at priority 0 to ensure MCP clients are ready before abilities_api_init
		add_action(
			'init',
			function () {
				$this->provider_manager->initialize_provider_credentials();
				$this->provider_manager->initialize_current_provider();

				// Register user meta for chatbot UI size and position
				register_meta(
					'user',
					'chatbot_size',
					array(
						'type'              => 'object',
						'description'       => 'Chatbot UI size settings',
						'single'            => true,
						'show_in_rest'      => array(
							'schema' => array(
								'type'       => 'object',
								'properties' => array(
									'width'  => array( 'type' => 'number' ),
									'height' => array( 'type' => 'number' ),
								),
							),
						),
						'auth_callback'     => function() {
							return current_user_can( 'wpaisdk_access_chatbot' );
						},
						'sanitize_callback' => array( $this, 'sanitize_chatbot_size' ),
					)
				);

				register_meta(
					'user',
					'chatbot_position',
					array(
						'type'              => 'object',
						'description'       => 'Chatbot UI position settings',
						'single'            => true,
						'show_in_rest'      => array(
							'schema' => array(
								'type'       => 'object',
								'properties' => array(
									'bottom' => array( 'type' => 'number' ),
									'right'  => array( 'type' => 'number' ),
								),
							),
						),
						'auth_callback'     => function() {
							return current_user_can( 'wpaisdk_access_chatbot' );
						},
						'sanitize_callback' => array( $this, 'sanitize_chatbot_position' ),
					)
				);

				add_action(
					'admin_menu',
					function () {
						$this->provider_manager->add_settings_screen();
					}
				);
			},
			0
		);

		// Initialize the Abilities API and register abilities
		add_action(
			'init',
			function () {
				error_log( "WP AI Chatbot: init hook called, initializing abilities registry" );
				// Initialize the abilities registry (this triggers the abilities_api_init hook)
				if ( class_exists( 'WP_Abilities_Registry' ) ) {
					error_log( "WP AI Chatbot: WP_Abilities_Registry class exists, calling get_instance()" );
					\WP_Abilities_Registry::get_instance();
				} else {
					error_log( "WP AI Chatbot: WP_Abilities_Registry class does not exist!" );
				}
			},
			1
		);

		// Register abilities on the correct hook
		add_action(
			'abilities_api_init',
			function () {
				error_log( "WP AI Chatbot: abilities_api_init hook called" );
				if ( function_exists( 'wp_register_ability' ) ) {
					error_log( "WP AI Chatbot: wp_register_ability function exists, calling Abilities::register_all()" );
					Abilities::register_all();
				} else {
					error_log( "WP AI Chatbot: wp_register_ability function does not exist in abilities_api_init hook!" );
				}
			}
		);

		// Initialize REST API endpoints for abilities
		add_action(
			'rest_api_init',
			function () {
				if ( class_exists( 'WP_REST_Abilities_Init' ) ) {
					\WP_REST_Abilities_Init::register_routes();
				}
			}
		);

		// Add hook to register REST API route.
		add_action(
			'rest_api_init',
			function () {
				$chatbot_route = new Chatbot_Messages_REST_Route(
					$this->provider_manager
				);
				$chatbot_route->register_routes();
			}
		);

		// Check providers and display admin notices for any issues
		add_action(
			'admin_init',
			function () {
				// Always check providers to display notices about invalid keys
				try {
					$this->provider_manager->get_available_provider_ids();
					// Hook up admin notices after checking providers
					add_action( 'admin_notices', array( $this->provider_manager, 'display_admin_notices' ) );
				} catch ( \Exception $e ) {
					error_log( 'WP AI SDK: Failed to check available providers: ' . $e->getMessage() );
				}
			}
		);

		// Load the chatbot in WP Admin if the user has access.
		add_action(
			'admin_init',
			function () {
				// phpcs:ignore WordPress.WP.Capabilities.Unknown
				if ( ! current_user_can( 'wpaisdk_access_chatbot' ) ) {
					return;
				}

				try {
					$available_provider_ids = $this->provider_manager->get_available_provider_ids();
					if ( count( $available_provider_ids ) === 0 ) {
						return;
					}
				} catch ( \Exception $e ) {
					error_log( 'WP AI SDK: Failed to check available providers in admin_init: ' . $e->getMessage() );
					return;
				}

				// Enqueue chatbot app scripts and styles.
				add_action(
					'admin_enqueue_scripts',
					function () {
						$current_provider_id = $this->provider_manager->get_current_provider_id();

						// Try to get provider and model metadata, but handle errors gracefully
						$provider_metadata = null;
						$model_metadata = null;

						try {
							$provider_metadata = $this->provider_manager->get_provider_metadata( $current_provider_id );
						} catch ( \Exception $e ) {
							error_log( 'WP AI SDK: Failed to get provider metadata: ' . $e->getMessage() );
						}

						try {
							$model_metadata = $this->provider_manager->get_model_metadata(
								$current_provider_id,
								$this->provider_manager->get_preferred_model_id( $current_provider_id )
							);
						} catch ( \Exception $e ) {
							error_log( 'WP AI SDK: Failed to get model metadata: ' . $e->getMessage() );
							// Track this as an invalid provider for admin notice
							if ( strpos( $e->getMessage(), 'Incorrect API key' ) !== false ||
							     strpos( $e->getMessage(), '401' ) !== false ) {
								// Access the invalid_providers array to show notice
								$this->provider_manager->track_invalid_provider( $current_provider_id, 'Invalid API key' );
							}
						}

						$current_user = wp_get_current_user();

						$script_config = array(
							'messagesRoute'           => 'wpaisdk-chatbot/v1/messages',
							'currentProviderMetadata' => $provider_metadata,
							'currentModelMetadata'    => $model_metadata,
							'currentUser'             => array(
								'displayName' => $current_user->display_name,
								'email'       => $current_user->user_email,
								'id'          => $current_user->ID,
							),
						);

						$manifest = require plugin_dir_path( $this->main_file ) . 'build/index.asset.php';

						wp_enqueue_script(
							'wp-ai-sdk-chatbot-demo',
							plugin_dir_url( $this->main_file ) . 'build/index.js',
							$manifest['dependencies'],
							$manifest['version'],
							array(
								'in_footer' => true,
								'strategy'  => 'defer',
							)
						);
						wp_add_inline_script(
							'wp-ai-sdk-chatbot-demo',
							'window.wpAiSdkChatbotDemo.loadChatbot(' . wp_json_encode( $script_config ) . ');',
							'after'
						);

						// Enqueue main CSS (includes Agenttic UI styles)
						wp_enqueue_style(
							'wp-ai-sdk-chatbot-demo-main',
							plugin_dir_url( $this->main_file ) . 'build/index.css',
							array(),
							$manifest['version']
						);
						wp_style_add_data( 'wp-ai-sdk-chatbot-demo-main', 'path', plugin_dir_path( $this->main_file ) . 'build/index.css' );

						// Enqueue component-specific styles
						wp_enqueue_style(
							'wp-ai-sdk-chatbot-demo',
							plugin_dir_url( $this->main_file ) . 'build/style-index.css',
							array( 'wp-ai-sdk-chatbot-demo-main' ),
							$manifest['version']
						);
						wp_style_add_data( 'wp-ai-sdk-chatbot-demo', 'path', plugin_dir_path( $this->main_file ) . 'build/style-index.css' );
					}
				);

				// Render chatbot app root.
				add_action(
					'admin_footer',
					function () {
						?>
						<div id="wp-ai-sdk-chatbot-demo-root" class="chatbot-root"></div>
						<?php
					}
				);
			}
		);

		// Set up MCP Adapter integration
		add_action(
			'mcp_adapter_init',
			function ( $adapter ) {
				// Initialize MCP Adapter singleton if available
				$mcp_adapter_class = 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WP\MCP\Core\McpAdapter';
				if ( class_exists( $mcp_adapter_class ) ) {
					$adapter->create_server(
						'wp-ai-chatbot-demo',
						'wp-ai-chatbot-demo/v1',
						'mcp',
						'WP AI Chatbot Demo MCP Server',
						'MCP server exposing WordPress chatbot abilities',
						'1.0.0',
						array(
							'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WP\MCP\Transport\Http\RestTransport',
						),
						'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
						'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
						array(
							'wp-ai-chatbot-demo/list-capabilities',
							'wp-ai-chatbot-demo/get-post',
							'wp-ai-chatbot-demo/create-post-draft',
							'wp-ai-chatbot-demo/search-posts',
							'wp-ai-chatbot-demo/publish-post',
							'wp-ai-chatbot-demo/set-permalink-structure',
							'wp-ai-chatbot-demo/generate-post-featured-image',
						),
						array(),
						array()
					);
				}
			}
		);

		// Initialize MCP Adapter singleton early
		add_action(
			'init',
			function () {
				$mcp_adapter_class = 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WP\MCP\Core\McpAdapter';
				if ( class_exists( $mcp_adapter_class ) ) {
					$mcp_adapter_class::instance();
				}
			},
			5
		);
	}

	/**
	 * Sanitize chatbot size settings.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array|null Sanitized size settings or null if invalid.
	 */
	public function sanitize_chatbot_size( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$sanitized = array();

		if ( isset( $value['width'] ) && is_numeric( $value['width'] ) ) {
			$sanitized['width'] = max( 320, min( 1200, (int) $value['width'] ) );
		}

		if ( isset( $value['height'] ) && is_numeric( $value['height'] ) ) {
			$sanitized['height'] = max( 400, min( 1000, (int) $value['height'] ) );
		}

		return empty( $sanitized ) ? null : $sanitized;
	}

	/**
	 * Sanitize chatbot position settings.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array|null Sanitized position settings or null if invalid.
	 */
	public function sanitize_chatbot_position( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$sanitized = array();

		if ( isset( $value['bottom'] ) && is_numeric( $value['bottom'] ) ) {
			$sanitized['bottom'] = max( 20, (int) $value['bottom'] );
		}

		if ( isset( $value['right'] ) && is_numeric( $value['right'] ) ) {
			$sanitized['right'] = max( 20, (int) $value['right'] );
		}

		return empty( $sanitized ) ? null : $sanitized;
	}

}
