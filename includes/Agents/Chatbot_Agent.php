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

		// Call parent constructor with ability names to use abilities API
		parent::__construct( $ability_names, $trajectory, $options );

		$this->provider_manager = $provider_manager;
		
		// Initialize prompt manager with the prompts directory
		$prompts_dir = dirname( dirname( __DIR__ ) ) . '/prompts';
		$this->prompt_manager = new Prompt_Manager( $prompts_dir );
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