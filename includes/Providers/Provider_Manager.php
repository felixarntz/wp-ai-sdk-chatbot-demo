<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\AiClient;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\DTO\ProviderMetadata;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string> $provider_ids List of AI SDK provider IDs to consider.
	 */
	public function __construct( array $provider_ids ) {
		$this->provider_ids = $provider_ids;
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
				static function ( string $provider_id ) use ( $registry ) {
					return $registry->hasProvider( $provider_id ) && $registry->isProviderConfigured( $provider_id );
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
				$model_id = 'gpt-4-turbo';
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
		register_setting(
			'wpaisdk-chatbot-demo-settings',
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
			'wpaisdk-chatbot-demo-settings',
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
	 * Adds the provider settings screen to the WP Admin interface.
	 *
	 * @since 0.1.0
	 */
	public function add_settings_screen(): void {
		$hook_suffix = add_options_page(
			__( 'AI SDK Chatbot Demo Settings', 'wp-ai-sdk-chatbot-demo' ),
			__( 'AI SDK Chatbot Demo', 'wp-ai-sdk-chatbot-demo' ),
			'manage_options',
			'wpaisdk-chatbot-demo-settings',
			array( $this, 'render_settings_screen' )
		);

		if ( ! is_string( $hook_suffix ) ) {
			return;
		}

		add_action(
			"load-{$hook_suffix}",
			array( $this, 'initialize_settings_screen' )
		);
	}

	/**
	 * Initializes the provider settings screen.
	 *
	 * @since 0.1.0
	 */
	public function initialize_settings_screen(): void {
		add_settings_section(
			'provider-credentials',
			__( 'Credentials', 'wp-ai-sdk-chatbot-demo' ),
			static function () {
				?>
				<p class="description">
					<?php esc_html_e( 'Paste your API credentials for the different providers you would like to use here.', 'wp-ai-sdk-chatbot-demo' ); ?>
				</p>
				<?php
			},
			'wpaisdk-chatbot-demo-settings'
		);

		$registry = AiClient::defaultRegistry();
		foreach ( $this->provider_ids as $provider_id ) {
			$field_id            = "provider-api-key-{$provider_id}";
			$provider_class_name = $registry->getProviderClassName( $provider_id );
			add_settings_field(
				$field_id,
				$provider_class_name::metadata()->getName(),
				array( $this, 'render_settings_field' ),
				'wpaisdk-chatbot-demo-settings',
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
					<?php esc_html_e( 'Choose the provider you would like to use for the chatbot demo. Only providers with valid API credentials can be selected.', 'wp-ai-sdk-chatbot-demo' ); ?>
				</p>
				<?php
			},
			'wpaisdk-chatbot-demo-settings'
		);

		$current_provider_choices = array();
		foreach ( $this->get_available_provider_ids() as $provider_id ) {
			$provider_class_name                      = $registry->getProviderClassName( $provider_id );
			$current_provider_choices[ $provider_id ] = $provider_class_name::metadata()->getName();
		}

		add_settings_field(
			'current-provider',
			__( 'Current Provider', 'wp-ai-sdk-chatbot-demo' ),
			array( $this, 'render_settings_field' ),
			'wpaisdk-chatbot-demo-settings',
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
	 * Renders the provider settings screen.
	 *
	 * @since 0.1.0
	 */
	public function render_settings_screen(): void {
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'AI SDK Chatbot Demo Settings', 'wp-ai-sdk-chatbot-demo' ); ?>
			</h1>

			<form action="options.php" method="post">
				<?php settings_fields( 'wpaisdk-chatbot-demo-settings' ); ?>
				<?php do_settings_sections( 'wpaisdk-chatbot-demo-settings' ); ?>
				<?php submit_button(); ?>
			</form>
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
}
