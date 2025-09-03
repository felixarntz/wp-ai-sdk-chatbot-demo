<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\MCP\MCP_Client_Manager
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\MCP;

use WP\MCP\Core\McpClient;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use function WordPress\Abilities\wp_register_ability;

/**
 * Class for managing MCP client connections.
 *
 * @since 0.1.0
 */
class MCP_Client_Manager {
	protected const OPTION_MCP_CLIENTS = 'wpaisdk_mcp_clients';
	
	/**
	 * Active MCP client instances.
	 *
	 * @var array<string, McpClient>
	 */
	protected array $client_instances = array();
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		// No presets - all clients are custom
	}
	
	/**
	 * Initialize MCP client connections.
	 */
	public function initialize(): void {
		register_setting(
			'ai-settings',
			self::OPTION_MCP_CLIENTS,
			array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_mcp_clients' ),
			)
		);
		
		// Initialize configured clients
		$this->initialize_configured_clients();
	}
	
	/**
	 * Sanitize MCP client configurations.
	 *
	 * @param mixed $clients Raw client configurations.
	 * @return array Sanitized client configurations.
	 */
	public function sanitize_mcp_clients( $clients ): array {
		if ( ! is_array( $clients ) ) {
			return array();
		}
		
		$sanitized = array();
		
		foreach ( $clients as $client_id => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			
			// Skip if marked for deletion or completely empty
			if ( ! empty( $config['delete'] ) || 
			     ( empty( $config['name'] ) && empty( $config['server_url'] ) && empty( $config['api_key'] ) ) ) {
				continue;
			}
			
			$sanitized[ $client_id ] = array(
				'name'       => isset( $config['name'] ) ? sanitize_text_field( $config['name'] ) : '',
				'enabled'    => ! empty( $config['enabled'] ),
				'server_url' => isset( $config['server_url'] ) ? esc_url_raw( $config['server_url'] ) : '',
				'api_key'    => isset( $config['api_key'] ) ? sanitize_text_field( $config['api_key'] ) : '',
				'transport'  => isset( $config['transport'] ) ? sanitize_text_field( $config['transport'] ) : 'mcp',
			);
		}
		
		return $sanitized;
	}
	
	/**
	 * Initialize configured MCP clients.
	 */
	protected function initialize_configured_clients(): void {
		$configured_clients = get_option( self::OPTION_MCP_CLIENTS, array() );
		
		if ( ! is_array( $configured_clients ) ) {
			return;
		}
		
		foreach ( $configured_clients as $client_id => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			
			$this->connect_client( $client_id, $config );
		}
	}
	
	/**
	 * Connect to an MCP client.
	 *
	 * @param string $client_id Client identifier.
	 * @param array  $config    Client configuration.
	 * @return bool True if connection successful, false otherwise.
	 */
	public function connect_client( string $client_id, array $config ): bool {
		try {
			// Get server URL from config
			$server_url = ! empty( $config['server_url'] ) ? $config['server_url'] : '';
			
			if ( empty( $server_url ) ) {
				return false;
			}
			
			// Prepare client configuration
			$client_config = array(
				'timeout'    => 30,
				'ssl_verify' => true,
			);
			
			// Add authentication if provided
			if ( ! empty( $config['api_key'] ) ) {
				$client_config['auth'] = array(
					'type' => 'api_key',
					'key'  => $config['api_key'],
				);
			}
			
			// Create client instance
			$client = new McpClient(
				$client_id,
				$server_url,
				$client_config,
				new ErrorLogMcpErrorHandler(),
				new NullMcpObservabilityHandler()
			);
			
			// Store client instance
			$this->client_instances[ $client_id ] = $client;
			
			// Register client abilities
			$this->register_client_abilities( $client_id, $client );
			
			return true;
			
		} catch ( \Throwable $e ) {
			error_log( 'MCP Client Manager: Failed to connect client ' . $client_id . ': ' . $e->getMessage() );
			return false;
		}
	}
	
	/**
	 * Register MCP client abilities with WordPress.
	 *
	 * @param string    $client_id Client identifier.
	 * @param McpClient $client    MCP client instance.
	 */
	protected function register_client_abilities( string $client_id, McpClient $client ): void {
		// List available tools from the MCP server
		$tools_response = $client->send_request( 'tools/list' );
		
		if ( is_wp_error( $tools_response ) || ! isset( $tools_response['result']['tools'] ) ) {
			return;
		}
		
		$tools = $tools_response['result']['tools'];
		
		// Register each tool as a WordPress ability
		foreach ( $tools as $tool ) {
			$ability_id = 'mcp_' . $client_id . '_' . $tool['name'];
			
			wp_register_ability(
				$ability_id,
				array(
					'label'       => $tool['description'] ?? $tool['name'],
					'description' => 'MCP Tool: ' . ( $tool['description'] ?? 'No description' ),
					'input_schema' => $tool['inputSchema'] ?? array(),
					'output_schema' => array(
						'type' => 'object',
					),
					'callback' => function( $params ) use ( $client, $tool ) {
						$response = $client->send_request(
							'tools/call',
							array(
								'name'      => $tool['name'],
								'arguments' => $params,
							)
						);
						
						if ( is_wp_error( $response ) ) {
							return $response;
						}
						
						return $response['result'] ?? array();
					},
				)
			);
		}
	}
	
	/**
	 * Test MCP client connection.
	 *
	 * @param string $client_id Client identifier.
	 * @param array  $config    Client configuration.
	 * @return array Test result with success status and message.
	 */
	public function test_connection( string $client_id, array $config ): array {
		try {
			// Get server URL from config
			$server_url = ! empty( $config['server_url'] ) ? $config['server_url'] : '';
			
			if ( empty( $server_url ) ) {
				return array(
					'success' => false,
					'message' => __( 'Server URL is required', 'wp-ai-sdk-chatbot-demo' ),
				);
			}
			
			// Prepare client configuration
			$client_config = array(
				'timeout'    => 10,
				'ssl_verify' => true,
			);
			
			// Add authentication if provided
			if ( ! empty( $config['api_key'] ) ) {
				$client_config['auth'] = array(
					'type' => 'api_key',
					'key'  => $config['api_key'],
				);
			}
			
			// Create temporary client instance
			$client = new McpClient(
				$client_id . '_test',
				$server_url,
				$client_config,
				new ErrorLogMcpErrorHandler(),
				new NullMcpObservabilityHandler()
			);
			
			// Try to list tools to verify connection
			$response = $client->send_request( 'tools/list' );
			
			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						__( 'Connection failed: %s', 'wp-ai-sdk-chatbot-demo' ),
						$response->get_error_message()
					),
				);
			}
			
			$tool_count = isset( $response['result']['tools'] ) ? count( $response['result']['tools'] ) : 0;
			
			return array(
				'success' => true,
				'message' => sprintf(
					__( 'Connection successful! Found %d tools.', 'wp-ai-sdk-chatbot-demo' ),
					$tool_count
				),
			);
			
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Connection error: %s', 'wp-ai-sdk-chatbot-demo' ),
					$e->getMessage()
				),
			);
		}
	}
	
	/**
	 * Get configured MCP clients.
	 *
	 * @return array Configured client settings.
	 */
	public function get_configured_clients(): array {
		return get_option( self::OPTION_MCP_CLIENTS, array() );
	}
}