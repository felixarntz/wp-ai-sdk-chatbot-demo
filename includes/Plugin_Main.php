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

		// Add hooks to register abilities.
		add_action(
			'abilities_api_init',
			function () {
				$registrar = new Abilities_Registrar();
				$registrar->register_abilities();
			}
		);

		// Add hooks to initialize provider configuration settings and related WP Admin UI.
		add_action(
			'init',
			function () {
				$this->provider_manager->initialize_provider_credentials();
				$this->provider_manager->initialize_current_provider();

				add_action(
					'admin_menu',
					function () {
						$this->provider_manager->add_settings_screen();
					}
				);
			}
		);

		// Add hook to register REST API route.
		add_action(
			'rest_api_init',
			function () {
				$chatbot_route = new Chatbot_Messages_REST_Route(
					$this->provider_manager,
					'wpaisdk-chatbot-demo/v1',
					'messages'
				);
				$chatbot_route->register_route();
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

				$available_provider_ids = $this->provider_manager->get_available_provider_ids();
				if ( count( $available_provider_ids ) === 0 ) {
					return;
				}

				// Enqueue chatbot app scripts and styles.
				add_action(
					'admin_enqueue_scripts',
					function () {
						$current_provider_id = $this->provider_manager->get_current_provider_id();
						$script_config       = array(
							'messagesRoute'           => 'wpaisdk-chatbot-demo/v1/messages',
							'currentProviderMetadata' => $this->provider_manager->get_provider_metadata( $current_provider_id ),
							'currentModelMetadata'    => $this->provider_manager->get_model_metadata(
								$current_provider_id,
								$this->provider_manager->get_preferred_model_id( $current_provider_id )
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

						wp_enqueue_style(
							'wp-ai-sdk-chatbot-demo',
							plugin_dir_url( $this->main_file ) . 'build/style-index.css',
							array(),
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
	}
}
