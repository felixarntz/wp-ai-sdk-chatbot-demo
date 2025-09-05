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
		
		// Initialize configured clients (create connections)
		$this->initialize_configured_clients();
		
		// Register abilities on the correct hook
		add_action( 'abilities_api_init', array( $this, 'register_all_client_abilities' ) );
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
				'auth_type'  => in_array( $config['auth_type'] ?? 'bearer', array( 'bearer', 'x-api-key' ), true )
								? $config['auth_type'] : 'bearer',
				'api_key_header' => isset( $config['api_key_header'] )
								? sanitize_text_field( $config['api_key_header'] ) : 'X-API-Key',
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
		$start_time = microtime( true );
		error_log( 'MCP Client Manager: Starting connection for ' . $client_id );
		
		try {
			// Get server URL from config
			$server_url = ! empty( $config['server_url'] ) ? $config['server_url'] : '';
			
			if ( empty( $server_url ) ) {
				return false;
			}
			
			// Prepare client configuration
			$client_config = array(
				'timeout'    => 8,   // Reduced for faster response
				'ssl_verify' => true,
			);
			
			// Add authentication if provided
			if ( ! empty( $config['api_key'] ) ) {
				$auth_type = $config['auth_type'] ?? 'bearer';
				if ( 'x-api-key' === $auth_type ) {
					$client_config['auth'] = array(
						'type'   => 'api_key_header',
						'header' => $config['api_key_header'] ?? 'X-API-Key',
						'token'  => $config['api_key'],
					);
				} else {
					$client_config['auth'] = array(
						'type'  => 'bearer',
						'token' => $config['api_key'],
					);
				}
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
			
			// Don't register abilities here - they'll be registered in register_all_client_abilities()
			
			$duration = ( microtime( true ) - $start_time ) * 1000;
			error_log( 'MCP Client Manager: Connected to ' . $client_id . ' in ' . round( $duration, 2 ) . 'ms' );
			
			return true;
			
		} catch ( \Throwable $e ) {
			error_log( 'MCP Client Manager: Failed to connect client ' . $client_id . ': ' . $e->getMessage() );
			return false;
		}
	}
	
	/**
	 * Register all MCP client abilities with WordPress.
	 * This is called on the abilities_api_init hook.
	 */
	public function register_all_client_abilities(): void {
		error_log( 'MCP Client Manager: Registering abilities for ' . count( $this->client_instances ) . ' clients' );
		
		// Check if wp_register_ability function exists
		if ( ! function_exists( '\wp_register_ability' ) ) {
			error_log( 'MCP Client Manager: wp_register_ability function does not exist!' );
			return;
		}
		
		foreach ( $this->client_instances as $client_id => $client ) {
			error_log( 'MCP Client Manager: Registering abilities for client: ' . $client_id );
			$this->register_client_abilities( $client_id, $client );
		}
	}
	
	/**
	 * Register MCP client abilities with WordPress.
	 *
	 * @param string    $client_id Client identifier.
	 * @param McpClient $client    MCP client instance.
	 */
	protected function register_client_abilities( string $client_id, McpClient $client ): void {
		$start_time = microtime( true );
		error_log( 'MCP Client Manager: Starting ability registration for ' . $client_id );
		
		// List available tools from the MCP server
		$tools_response = $client->send_request( 'tools/list', array() );
		
		if ( is_wp_error( $tools_response ) ) {
			error_log( 'MCP Client Manager: Failed to list tools for ' . $client_id . ': ' . $tools_response->get_error_message() );
			return;
		}
		
		if ( ! isset( $tools_response['tools'] ) ) {
			error_log( 'MCP Client Manager: No tools found in response for ' . $client_id );
			return;
		}
		
		$tools = $tools_response['tools'];
		error_log( 'MCP Client Manager: Found ' . count( $tools ) . ' tools for client ' . $client_id );
		
		// Register each tool as a WordPress ability
		foreach ( $tools as $tool ) {
			// Ability ID must have namespace/name format
			// Convert underscores to dashes in client_id to comply with WP Abilities API naming convention
			$sanitized_client_id = str_replace( '_', '-', $client_id );
			$ability_id = 'mcp-' . $sanitized_client_id . '/' . strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $tool['name'] ) );
			error_log( 'MCP Client Manager: Registering ability ' . $ability_id );
			
			$callback = function( $params ) use ( $client, $tool, $ability_id ) {
				error_log( 'MCP Client Manager: Executing ability ' . $ability_id . ' with params: ' . json_encode( $params ) );
				
				$response = $client->send_request(
					'tools/call',
					array(
						'name'      => $tool['name'],
						'arguments' => $params,
					)
				);
				
				if ( is_wp_error( $response ) ) {
					error_log( 'MCP Client Manager: Error executing ' . $ability_id . ': ' . $response->get_error_message() );
					return $response;
				}
				
				error_log( 'MCP Client Manager: Raw response for ' . $ability_id . ': ' . json_encode( $response ) );
				
				// MCP responses come in the format: { content: [ { type: "text", text: "JSON_STRING" } ] }
				// We need to extract and parse the actual JSON from the text field
				if ( isset( $response['content'] ) && is_array( $response['content'] ) && ! empty( $response['content'] ) ) {
					$content = $response['content'][0] ?? null;
					
					if ( $content && isset( $content['type'] ) && $content['type'] === 'text' && isset( $content['text'] ) ) {
						// Parse the JSON string in the text field
						$parsed = json_decode( $content['text'], true );
						
						if ( json_last_error() === JSON_ERROR_NONE ) {
							error_log( 'MCP Client Manager: Successfully parsed MCP response for ' . $ability_id );
							return $parsed;
						} else {
							error_log( 'MCP Client Manager: Failed to parse JSON from MCP response for ' . $ability_id . ': ' . json_last_error_msg() );
							// Return the text as-is if it's not JSON
							return array( 'text' => $content['text'] );
						}
					}
				}
				
				// Fallback: return the response as-is
				error_log( 'MCP Client Manager: Returning raw response for ' . $ability_id );
				return $response;
			};
			
			// Log to verify callback is callable
			if ( ! is_callable( $callback ) ) {
				error_log( 'MCP Client Manager: Callback is not callable for ' . $ability_id );
				continue;
			}
			
			$success = \wp_register_ability(
				$ability_id,
				array(
					'label'            => $tool['description'] ?? $tool['name'],
					'description'      => 'MCP Tool: ' . ( $tool['description'] ?? 'No description' ),
					'input_schema'     => $tool['inputSchema'] ?? array(),
					'output_schema'    => array(
						'type' => 'object',
					),
					'execute_callback' => $callback,
				)
			);
			
			if ( is_wp_error( $success ) ) {
				error_log( 'MCP Client Manager: Failed to register ability ' . $ability_id . ': ' . $success->get_error_message() );
			} else {
				error_log( 'MCP Client Manager: Successfully registered ability ' . $ability_id );
			}
		}
		
		$duration = ( microtime( true ) - $start_time ) * 1000;
		error_log( 'MCP Client Manager: Completed ability registration for ' . $client_id . ' in ' . round( $duration, 2 ) . 'ms' );
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
				'timeout'    => 8,   // Reduced for faster response
				'ssl_verify' => true,
			);
			
			// Add authentication if provided
			if ( ! empty( $config['api_key'] ) ) {
				$auth_type = $config['auth_type'] ?? 'bearer';
				if ( 'x-api-key' === $auth_type ) {
					$client_config['auth'] = array(
						'type'   => 'api_key_header',
						'header' => $config['api_key_header'] ?? 'X-API-Key',
						'token'  => $config['api_key'],
					);
				} else {
					$client_config['auth'] = array(
						'type'  => 'bearer',
						'token' => $config['api_key'],
					);
				}
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
			$response = $client->send_request( 'tools/list', array() );
			
			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						__( 'Connection failed: %s', 'wp-ai-sdk-chatbot-demo' ),
						$response->get_error_message()
					),
				);
			}
			
			// The response is already the 'result' part of the JSON-RPC response
			$tool_count = isset( $response['tools'] ) ? count( $response['tools'] ) : 0;
			
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