<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Chatbot_Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Prompt_Manager;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;

/**
 * Class for the chatbot agent.
 *
 * @since 0.1.0
 */
class Chatbot_Agent extends Abstract_Agent {

	/**
	 * The provider manager instance.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * The prompt manager instance.
	 *
	 * @since 0.1.0
	 * @var Prompt_Manager
	 */
	private Prompt_Manager $prompt_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Provider_Manager     $provider_manager The provider manager instance.
	 * @param array<Message>       $trajectory       The initial trajectory of messages. Must contain at least the
	 *                                               first message.
	 * @param array<string, mixed> $options          Additional options for the agent.
	 */
	public function __construct( Provider_Manager $provider_manager, array $trajectory, array $options = array() ) {
		$ability_names = array(
			'wp-ai-chatbot-demo/list-capabilities',
			'wp-ai-chatbot-demo/get-post',
			'wp-ai-chatbot-demo/create-post-draft',
			'wp-ai-chatbot-demo/search-posts',
			'wp-ai-chatbot-demo/publish-post',
			'wp-ai-chatbot-demo/set-permalink-structure',
			'wp-ai-chatbot-demo/generate-post-featured-image',
			'wp-ai-chatbot-demo/list-providers',
			'wp-ai-chatbot-demo/change-provider',
		);
		
		// Add additional registered abilities (fetch-url and configure-mcp-client)
		if ( function_exists( 'wp_get_ability' ) ) {
			// Check if fetch-url ability is registered
			try {
				\wp_get_ability( 'wp/fetch-url-as-markdown' );
				$ability_names[] = 'wp/fetch-url-as-markdown';
			} catch ( \Exception $e ) {
				// Ability not registered
			}
			
			// Check if configure-mcp-client ability is registered
			try {
				\wp_get_ability( 'wp-ai-chatbot-demo/configure-mcp-client' );
				$ability_names[] = 'wp-ai-chatbot-demo/configure-mcp-client';
			} catch ( \Exception $e ) {
				// Ability not registered
			}
		}
		
		// Add MCP abilities dynamically
		$mcp_abilities = $this->get_mcp_abilities();
		$ability_names = array_merge( $ability_names, $mcp_abilities );

		// Call parent constructor with ability names to use abilities API
		parent::__construct( $ability_names, $trajectory, $options );

		$this->provider_manager = $provider_manager;
		
		// Initialize prompt manager with the prompts directory
		$prompts_dir = dirname( dirname( __DIR__ ) ) . '/prompts';
		$this->prompt_manager = new Prompt_Manager( $prompts_dir );
	}

	/**
	 * Get MCP abilities dynamically by querying all registered abilities.
	 *
	 * @since 0.1.0
	 *
	 * @return array List of MCP ability names.
	 */
	protected function get_mcp_abilities(): array {
		$mcp_abilities = array();
		
		// Get all registered abilities and filter for MCP ones
		if ( function_exists( 'wp_list_abilities' ) ) {
			try {
				$all_abilities = wp_list_abilities();
				foreach ( $all_abilities as $ability ) {
					$ability_id = $ability['name'] ?? '';
					// Check if this is an MCP ability (starts with 'mcp-')
					if ( strpos( $ability_id, 'mcp-' ) === 0 ) {
						$mcp_abilities[] = $ability_id;
						error_log( 'Chatbot Agent: Found MCP ability: ' . $ability_id );
					}
				}
			} catch ( \Exception $e ) {
				error_log( 'Chatbot Agent: Failed to list abilities: ' . $e->getMessage() );
			}
		} else {
			// Fallback: Check for abilities based on configured clients
			$mcp_clients = get_option( 'wpaisdk_mcp_clients', array() );
			
			if ( is_array( $mcp_clients ) ) {
				foreach ( $mcp_clients as $client_id => $config ) {
					// Skip if not enabled
					if ( empty( $config['enabled'] ) || empty( $config['server_url'] ) ) {
						continue;
					}
					
					// Try to get the MCP client and query its tools
					try {
						$mcp_manager = new \Felix_Arntz\WP_AI_SDK_Chatbot_Demo\MCP\MCP_Client_Manager();
						$test_result = $mcp_manager->test_connection( $client_id, $config );
						
						if ( $test_result['success'] ) {
							// Try common tool name patterns for this client
							$potential_tools = array( 'searchdomains', 'checkdomainavailability', 'getsuggestedtlds' );
							foreach ( $potential_tools as $tool ) {
								// Convert underscores to dashes in client_id to match WP Abilities API naming convention
								$sanitized_client_id = str_replace( '_', '-', $client_id );
								$ability_id = 'mcp-' . $sanitized_client_id . '/' . $tool;
								if ( function_exists( 'wp_get_ability' ) ) {
									try {
										\wp_get_ability( $ability_id );
										$mcp_abilities[] = $ability_id;
										error_log( 'Chatbot Agent: Found MCP ability: ' . $ability_id );
									} catch ( \Exception $e ) {
										// Ability not registered
									}
								}
							}
						}
					} catch ( \Exception $e ) {
						error_log( 'Chatbot Agent: Error checking MCP client ' . $client_id . ': ' . $e->getMessage() );
					}
				}
			}
		}
		
		error_log( 'Chatbot Agent: Total MCP abilities found: ' . count( $mcp_abilities ) );
		
		return $mcp_abilities;
	}

	/**
	 * Prompts the LLM with the current trajectory as input.
	 *
	 * @since 0.1.0
	 *
	 * @param PromptBuilder $prompt_builder The prompt builder instance.
	 * @return Message The response from the LLM.
	 */
	protected function prompt_llm( PromptBuilder $prompt_builder ): Message {
		$provider_id = $this->provider_manager->get_current_provider_id();
		if ( '' !== $provider_id ) {
			$model_id = $this->provider_manager->get_preferred_model_id( $provider_id );
			if ( '' !== $model_id ) {
				$prompt_builder = $prompt_builder->usingModel(
					AiClient::defaultRegistry()->getProviderModel( $provider_id, $model_id )
				);
			}
		}

		return $prompt_builder
			->usingSystemInstruction( $this->get_instruction() )
			->generateTextResult()
			->toMessage();
	}

	/**
	 * Returns the instruction to use for the agent.
	 *
	 * @since 0.1.0
	 *
	 * @return string The instruction.
	 */
	protected function get_instruction(): string {
		// Allow custom context to be passed via filter
		$context = apply_filters( 'wp_ai_chatbot_prompt_context', array() );
		
		// Load the prompt from file with placeholder replacement
		$prompt = $this->prompt_manager->get_prompt( 'chatbot-system-prompt', $context );
		
		// Allow the final prompt to be filtered
		return apply_filters( 'wp_ai_chatbot_system_prompt', $prompt, $context );
	}
}