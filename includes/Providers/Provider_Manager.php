<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\MCP\MCP_Client_Manager;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for managing the different AI providers.
 *
 * @since 0.1.0
 */
class Provider_Manager {
	protected const OPTION_PROVIDER_CREDENTIALS = 'wpaisdk_provider_credentials';
	protected const OPTION_CURRENT_PROVIDER     = 'wpaisdk_current_provider';

	/**
	 * The provider IDs to consider.
	 *
	 * @since 0.1.0
	 *
	 * @var array<string> List of AI SDK provider IDs.
	 */
	protected array $provider_ids;

	/**
	 * In-memory cache for the available provider IDs.
	 *
	 * @since 0.1.0
	 *
	 * @var array<string>|null List of available AI SDK provider IDs, or null if not determined yet.
	 */
	protected ?array $available_provider_ids = null;

	/**
	 * Track providers with invalid API keys.
	 *
	 * @since 0.1.0
	 *
	 * @var array<string, string> Map of provider IDs to error messages.
	 */
	protected array $invalid_providers = array();

	/**
	 * MCP Client Manager instance.
	 *
	 * @since 0.1.0
	 *
	 * @var MCP_Client_Manager|null
	 */
	protected ?MCP_Client_Manager $mcp_client_manager = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string> $provider_ids List of AI SDK provider IDs to consider.
	 */
	public function __construct( array $provider_ids ) {
		$this->provider_ids = $provider_ids;
		$this->mcp_client_manager = new MCP_Client_Manager();
	}

	/**
	 * Gets the available provider IDs.
	 *
	 * Only providers for which valid API credentials are set are considered available.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string> List of available AI SDK provider IDs.
	 */
	public function get_available_provider_ids(): array {
		if ( is_array( $this->available_provider_ids ) ) {
			return $this->available_provider_ids;
		}

		$registry = AiClient::defaultRegistry();

		$this->available_provider_ids = array_values(
			array_filter(
				$this->provider_ids,
				function ( string $provider_id ) use ( $registry ) {
					try {
						return $registry->hasProvider( $provider_id ) && $registry->isProviderConfigured( $provider_id );
					} catch ( \Exception $e ) {
						// Track invalid providers for admin notices
						if ( strpos( $e->getMessage(), 'Incorrect API key' ) !== false || 
						     strpos( $e->getMessage(), '401' ) !== false ) {
							$this->invalid_providers[ $provider_id ] = 'Invalid API key';
						} else {
							$this->invalid_providers[ $provider_id ] = $e->getMessage();
						}
						error_log( 'WP AI SDK: Failed to check provider configuration for ' . $provider_id . ': ' . $e->getMessage() );
						return false;
					}
				}
			)
		);

		return $this->available_provider_ids;
	}

	/**
	 * Gets the current provider ID.
	 *
	 * @since n.e.x.t
	 *
	 * @return string The provider ID, or an empty string if none.
	 */
	public function get_current_provider_id(): string {
		$provider_id = (string) get_option( self::OPTION_CURRENT_PROVIDER );
		if ( '' !== $provider_id ) {
			return $provider_id;
		}

		$available_provider_ids = $this->get_available_provider_ids();
		if ( ! empty( $available_provider_ids ) ) {
			return $available_provider_ids[0];
		}
		return '';
	}

	/**
	 * Gets the preferred model ID for the given provider.
	 *
	 * @since 0.1.0
	 *
	 * @param string $provider_id The provider ID.
	 * @return string The model ID.
	 */
	public function get_preferred_model_id( string $provider_id ): string {
		switch ( $provider_id ) {
			case 'anthropic':
				$model_id = 'claude-sonnet-4-20250514';
				break;
			case 'google':
				$model_id = 'gemini-2.5-flash';
				break;
			case 'openai':
				$model_id = 'gpt-5-mini';
				break;
			default:
				$model_id = '';
		}

		/**
		 * Filters the preferred model ID for the given provider ID.
		 *
		 * The dynamic portion of the hook name refers to the provider ID.
		 *
		 * @since 0.1.0
		 *
		 * @param string $model_id The preferred model ID for the provider.
		 */
		return (string) apply_filters( "wpaisdk_preferred_{$provider_id}_model", $model_id );
	}

	/**
	 * Gets the metadata for the given provider.
	 *
	 * @since 0.1.0
	 *
	 * @param string $provider_id The provider ID.
	 * @return ProviderMetadata The provider metadata.
	 */
	public function get_provider_metadata( string $provider_id ): ProviderMetadata {
		$provider_class_name = AiClient::defaultRegistry()->getProviderClassName( $provider_id );
		return $provider_class_name::metadata();
	}

	/**
	 * Gets the metadata for the given model of the given provider.
	 *
	 * @since 0.1.0
	 *
	 * @param string $provider_id The provider ID.
	 * @param string $model_id The model ID.
	 * @return ModelMetadata The model metadata.
	 */
	public function get_model_metadata( string $provider_id, string $model_id ): ModelMetadata {
		$model_instance = AiClient::defaultRegistry()->getProviderModel( $provider_id, $model_id );
		return $model_instance->metadata();
	}

	/**
	 * Initializes the provider credentials.
	 *
	 * This registers the option to store credentials in WordPress and hooks up the PHP AI Client SDK with the stored
	 * credentials.
	 *
	 * @since 0.1.0
	 */
	public function initialize_provider_credentials(): void {
		// Initialize MCP clients
		if ( $this->mcp_client_manager ) {
			$this->mcp_client_manager->initialize();
		}
		
		register_setting(
			'ai-settings',
			self::OPTION_PROVIDER_CREDENTIALS,
			array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => function ( $credentials ) {
					if ( ! is_array( $credentials ) ) {
						return array();
					}

					$credentials = array_intersect_key( $credentials, array_flip( $this->provider_ids ) );
					foreach ( $credentials as $provider_id => $api_key ) {
						$credentials[ $provider_id ] = sanitize_text_field( $api_key );
					}
					return $credentials;
				},
			)
		);

		$current_credentials = get_option( self::OPTION_PROVIDER_CREDENTIALS );
		if ( ! is_array( $current_credentials ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		foreach ( $current_credentials as $provider_id => $api_key ) {
			if ( '' === $api_key ) {
				continue;
			}
			$registry->setProviderRequestAuthentication(
				$provider_id,
				new ApiKeyRequestAuthentication( $api_key )
			);
		}
	}

	/**
	 * Initializes the current provider configuration.
	 *
	 * This registers the option to store the current provider.
	 */
	public function initialize_current_provider(): void {
		register_setting(
			'ai-settings',
			self::OPTION_CURRENT_PROVIDER,
			array(
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $provider_id ) {
					if ( ! is_string( $provider_id ) || '' === $provider_id ) {
						return '';
					}

					$available_provider_ids = $this->get_available_provider_ids();
					if ( ! in_array( $provider_id, $available_provider_ids, true ) ) {
						return '';
					}

					return $provider_id;
				},
			)
		);
	}

	/**
	 * Track a provider as invalid for admin notices.
	 *
	 * @since 0.1.0
	 *
	 * @param string $provider_id The provider ID.
	 * @param string $error The error message.
	 */
	public function track_invalid_provider( string $provider_id, string $error ): void {
		$this->invalid_providers[ $provider_id ] = $error;
	}

	/**
	 * Display admin notices for invalid API keys.
	 *
	 * @since 0.1.0
	 */
	public function display_admin_notices(): void {
		// Only show on admin pages
		if ( ! is_admin() ) {
			return;
		}

		// Check if we have any invalid providers
		if ( empty( $this->invalid_providers ) ) {
			return;
		}

		// Get current credentials to check which ones are actually set
		$credentials = get_option( self::OPTION_PROVIDER_CREDENTIALS, array() );
		
		foreach ( $this->invalid_providers as $provider_id => $error ) {
			// Only show notice if API key is actually configured
			if ( ! isset( $credentials[ $provider_id ] ) || empty( $credentials[ $provider_id ] ) ) {
				continue;
			}

			// Get proper provider name
			$provider_names = array(
				'openai' => 'OpenAI',
				'anthropic' => 'Anthropic',
				'google' => 'Google AI',
			);
			$provider_name = isset( $provider_names[ $provider_id ] ) ? $provider_names[ $provider_id ] : ucfirst( $provider_id );
			$settings_url = admin_url( 'options-general.php?page=ai' );
			
			if ( $error === 'Invalid API key' ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php echo esc_html( $provider_name ); ?> API Error:</strong> 
						The configured API key is invalid or has been revoked. 
						<a href="<?php echo esc_url( $settings_url ); ?>">Update your API credentials</a> to use <?php echo esc_html( $provider_name ); ?>.
					</p>
				</div>
				<?php
			} else {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong><?php echo esc_html( $provider_name ); ?> Configuration Error:</strong> 
						Unable to connect to the provider. 
						<a href="<?php echo esc_url( $settings_url ); ?>">Check your settings</a>.
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Adds the provider settings screen to the WP Admin interface.
	 *
	 * @since 0.1.0
	 */
	public function add_settings_screen(): void {
		$hook_suffix = add_options_page(
			__( 'AI Settings', 'wp-ai-sdk-chatbot-demo' ),
			__( 'AI', 'wp-ai-sdk-chatbot-demo' ),
			'manage_options',
			'ai',
			array( $this, 'render_settings_screen' )
		);

		if ( ! is_string( $hook_suffix ) ) {
			return;
		}

		add_action(
			"load-{$hook_suffix}",
			array( $this, 'initialize_settings_screen' )
		);
		
		// Add AJAX handlers for MCP testing
		add_action( 'wp_ajax_test_mcp_connection', array( $this, 'ajax_test_mcp_connection' ) );
	}

	/**
	 * Initializes the provider settings screen.
	 *
	 * @since 0.1.0
	 */
	public function initialize_settings_screen(): void {
		// Get current tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'providers';
		
		if ( 'mcp' === $current_tab ) {
			$this->initialize_mcp_settings();
		} else {
			$this->initialize_provider_settings();
		}
	}
	
	/**
	 * Initialize provider settings sections and fields.
	 *
	 * @since 0.1.0
	 */
	protected function initialize_provider_settings(): void {
		add_settings_section(
			'provider-credentials',
			__( 'API Credentials', 'wp-ai-sdk-chatbot-demo' ),
			static function () {
				?>
				<p class="description">
					<?php esc_html_e( 'Configure API credentials for AI providers. Only providers with valid credentials will be available for use.', 'wp-ai-sdk-chatbot-demo' ); ?>
				</p>
				<?php
			},
			'ai-settings'
		);

		$registry = AiClient::defaultRegistry();
		foreach ( $this->provider_ids as $provider_id ) {
			$field_id            = "provider-api-key-{$provider_id}";
			$provider_class_name = $registry->getProviderClassName( $provider_id );
			add_settings_field(
				$field_id,
				$provider_class_name::metadata()->getName(),
				array( $this, 'render_settings_field' ),
				'ai-settings',
				'provider-credentials',
				array(
					'type'      => 'password',
					'label_for' => $field_id,
					'id'        => $field_id,
					'name'      => self::OPTION_PROVIDER_CREDENTIALS . '[' . $provider_id . ']',
				)
			);
		}

		add_settings_section(
			'provider-preferences',
			__( 'Provider Preferences', 'wp-ai-sdk-chatbot-demo' ),
			static function () {
				?>
				<p class="description">
					<?php esc_html_e( 'Choose the default AI provider. Only providers with valid API credentials can be selected.', 'wp-ai-sdk-chatbot-demo' ); ?>
				</p>
				<?php
			},
			'ai-settings'
		);

		$current_provider_choices = array();
		try {
			foreach ( $this->get_available_provider_ids() as $provider_id ) {
				try {
					$provider_class_name                      = $registry->getProviderClassName( $provider_id );
					$current_provider_choices[ $provider_id ] = $provider_class_name::metadata()->getName();
				} catch ( \Exception $e ) {
					error_log( 'WP AI SDK: Failed to get provider metadata for ' . $provider_id . ': ' . $e->getMessage() );
				}
			}
		} catch ( \Exception $e ) {
			error_log( 'WP AI SDK: Failed to get available providers for settings screen: ' . $e->getMessage() );
		}

		add_settings_field(
			'current-provider',
			__( 'Default Provider', 'wp-ai-sdk-chatbot-demo' ),
			array( $this, 'render_settings_field' ),
			'ai-settings',
			'provider-preferences',
			array(
				'type'      => 'select',
				'label_for' => 'current-provider',
				'id'        => 'current-provider',
				'name'      => self::OPTION_CURRENT_PROVIDER,
				'choices'   => $current_provider_choices,
			)
		);
	}

	/**
	 * Initialize MCP client settings.
	 *
	 * @since 0.1.0
	 */
	protected function initialize_mcp_settings(): void {
		add_settings_section(
			'mcp-clients',
			__( 'MCP Client Connections', 'wp-ai-sdk-chatbot-demo' ),
			static function () {
				?>
				<p class="description">
					<?php esc_html_e( 'Connect to external MCP servers to use their capabilities within WordPress.', 'wp-ai-sdk-chatbot-demo' ); ?>
				</p>
				<?php
			},
			'ai-settings'
		);
		
		if ( $this->mcp_client_manager ) {
			$available_clients = $this->mcp_client_manager->get_available_clients();
			$configured_clients = $this->mcp_client_manager->get_configured_clients();
			
			foreach ( $available_clients as $client_id => $client_info ) {
				$field_id = "mcp-client-{$client_id}";
				$config = isset( $configured_clients[ $client_id ] ) ? $configured_clients[ $client_id ] : array();
				
				add_settings_field(
					$field_id,
					$client_info['name'],
					array( $this, 'render_mcp_client_field' ),
					'ai-settings',
					'mcp-clients',
					array(
						'client_id'   => $client_id,
						'client_info' => $client_info,
						'config'      => $config,
					)
				);
			}
		}
	}
	
	/**
	 * Render MCP client configuration field.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Field arguments.
	 */
	public function render_mcp_client_field( array $args ): void {
		$client_id = $args['client_id'];
		$client_info = $args['client_info'];
		$config = $args['config'];
		$enabled = ! empty( $config['enabled'] );
		?>
		<div style="border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
			<p style="margin-top: 0;">
				<em><?php echo esc_html( $client_info['description'] ); ?></em>
			</p>
			
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="mcp-<?php echo esc_attr( $client_id ); ?>-enabled">
							<?php esc_html_e( 'Enable', 'wp-ai-sdk-chatbot-demo' ); ?>
						</label>
					</th>
					<td>
						<input
							type="checkbox"
							id="mcp-<?php echo esc_attr( $client_id ); ?>-enabled"
							name="wpaisdk_mcp_clients[<?php echo esc_attr( $client_id ); ?>][enabled]"
							value="1"
							<?php checked( $enabled ); ?>
						/>
					</td>
				</tr>
				
				<?php if ( 'custom' === $client_id || empty( $client_info['server_url'] ) ) : ?>
				<tr>
					<th scope="row">
						<label for="mcp-<?php echo esc_attr( $client_id ); ?>-server-url">
							<?php esc_html_e( 'Server URL', 'wp-ai-sdk-chatbot-demo' ); ?>
						</label>
					</th>
					<td>
						<input
							type="url"
							id="mcp-<?php echo esc_attr( $client_id ); ?>-server-url"
							name="wpaisdk_mcp_clients[<?php echo esc_attr( $client_id ); ?>][server_url]"
							value="<?php echo esc_attr( $config['server_url'] ?? '' ); ?>"
							class="regular-text"
							placeholder="https://example.com/mcp"
						/>
					</td>
				</tr>
				<?php endif; ?>
				
				<?php if ( in_array( 'api_key', $client_info['requires'] ?? array(), true ) ) : ?>
				<tr>
					<th scope="row">
						<label for="mcp-<?php echo esc_attr( $client_id ); ?>-api-key">
							<?php esc_html_e( 'API Key', 'wp-ai-sdk-chatbot-demo' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="mcp-<?php echo esc_attr( $client_id ); ?>-api-key"
							name="wpaisdk_mcp_clients[<?php echo esc_attr( $client_id ); ?>][api_key]"
							value="<?php echo esc_attr( $config['api_key'] ?? '' ); ?>"
							class="regular-text"
						/>
					</td>
				</tr>
				<?php endif; ?>
				
				<tr>
					<th scope="row"></th>
					<td>
						<button
							type="button"
							class="button button-secondary"
							onclick="testMcpConnection('<?php echo esc_js( $client_id ); ?>')"
						>
							<?php esc_html_e( 'Test Connection', 'wp-ai-sdk-chatbot-demo' ); ?>
						</button>
						<span id="mcp-test-<?php echo esc_attr( $client_id ); ?>-result" style="margin-left: 10px;"></span>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
	
	/**
	 * Renders the provider settings screen.
	 *
	 * @since 0.1.0
	 */
	public function render_settings_screen(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'providers';
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'AI Settings', 'wp-ai-sdk-chatbot-demo' ); ?>
			</h1>
			
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secondary menu', 'wp-ai-sdk-chatbot-demo' ); ?>">
				<a href="?page=ai&tab=providers" class="nav-tab <?php echo 'providers' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'AI Providers', 'wp-ai-sdk-chatbot-demo' ); ?>
				</a>
				<a href="?page=ai&tab=mcp" class="nav-tab <?php echo 'mcp' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'MCP Clients', 'wp-ai-sdk-chatbot-demo' ); ?>
				</a>
			</nav>

			<form action="options.php" method="post">
				<?php settings_fields( 'ai-settings' ); ?>
				<?php do_settings_sections( 'ai-settings' ); ?>
				<?php submit_button(); ?>
			</form>
			
			<script>
			function testMcpConnection(clientId) {
				const resultSpan = document.getElementById('mcp-test-' + clientId + '-result');
				resultSpan.innerHTML = '<span style="color: #666;"><?php esc_html_e( 'Testing...', 'wp-ai-sdk-chatbot-demo' ); ?></span>';
				
				const formData = new FormData();
				formData.append('action', 'test_mcp_connection');
				formData.append('client_id', clientId);
				formData.append('nonce', '<?php echo wp_create_nonce( 'test_mcp_connection' ); ?>');
				
				// Get current form values
				const enabled = document.getElementById('mcp-' + clientId + '-enabled');
				if (enabled && enabled.checked) {
					formData.append('enabled', '1');
				}
				
				const serverUrl = document.getElementById('mcp-' + clientId + '-server-url');
				if (serverUrl) {
					formData.append('server_url', serverUrl.value);
				}
				
				const apiKey = document.getElementById('mcp-' + clientId + '-api-key');
				if (apiKey) {
					formData.append('api_key', apiKey.value);
				}
				
				fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						resultSpan.innerHTML = '<span style="color: green;">✓ ' + data.data.message + '</span>';
					} else {
						resultSpan.innerHTML = '<span style="color: red;">✗ ' + (data.data ? data.data.message : 'Connection failed') + '</span>';
					}
				})
				.catch(error => {
					resultSpan.innerHTML = '<span style="color: red;">✗ <?php esc_html_e( 'Connection test failed', 'wp-ai-sdk-chatbot-demo' ); ?></span>';
				});
			}
			</script>
		</div>
		<?php
	}

	/**
	 * Renders a settings field based on the given arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $args Field arguments set up during `add_settings_field()`.
	 */
	public function render_settings_field( array $args ): void {
		$type = $args['type'] ?? 'text';
		$id   = $args['id'] ?? '';
		$name = $args['name'] ?? '';

		if ( str_contains( $name, '[' ) ) {
			$parts  = explode( '[', $name, 2 );
			$option = get_option( $parts[0] );
			$subkey = trim( $parts[1], ']' );
			if ( is_array( $option ) && isset( $option[ $subkey ] ) ) {
				$value = $option[ $subkey ];
			} else {
				$value = '';
			}
		} else {
			$option = get_option( $name );
			if ( is_string( $option ) ) {
				$value = $option;
			} else {
				$value = '';
			}
		}

		if ( 'select' === $type ) {
			$choices = $args['choices'] ?? array();
			?>
			<select
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
			>
				<option value="" <?php selected( $value, '' ); ?>>
					<?php esc_html_e( 'None', 'wp-ai-sdk-chatbot-demo' ); ?>
				</option>
				<?php
				foreach ( $choices as $choice_value => $choice_label ) {
					?>
					<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, $choice_value ); ?>>
						<?php echo esc_html( $choice_label ); ?>
					</option>
					<?php
				}
				?>
			</select>
			<?php
		} else {
			?>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
			>
			<?php
		}
	}
	
	/**
	 * AJAX handler for testing MCP connections.
	 *
	 * @since 0.1.0
	 */
	public function ajax_test_mcp_connection(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'test_mcp_connection' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'wp-ai-sdk-chatbot-demo' ) ) );
		}
		
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wp-ai-sdk-chatbot-demo' ) ) );
		}
		
		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( $_POST['client_id'] ) : '';
		
		if ( empty( $client_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid client ID', 'wp-ai-sdk-chatbot-demo' ) ) );
		}
		
		// Get configuration from POST data
		$config = array(
			'enabled'    => ! empty( $_POST['enabled'] ),
			'server_url' => isset( $_POST['server_url'] ) ? esc_url_raw( $_POST['server_url'] ) : '',
			'api_key'    => isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '',
		);
		
		if ( $this->mcp_client_manager ) {
			$result = $this->mcp_client_manager->test_connection( $client_id, $config );
			
			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'MCP Client Manager not initialized', 'wp-ai-sdk-chatbot-demo' ) ) );
		}
	}
}
