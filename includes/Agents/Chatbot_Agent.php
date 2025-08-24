<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Chatbot_Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Contracts\Agent;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\PromptBuilder;

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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Provider_Manager     $provider_manager The provider manager instance.
	 * @param array<Tool>          $tools            The tools available to the agent.
	 * @param array<Message>       $trajectory       The initial trajectory of messages. Must contain at least the
	 *                                               first message.
	 * @param array<string, mixed> $options          Additional options for the agent.
	 */
	public function __construct( Provider_Manager $provider_manager, array $tools, array $trajectory, array $options = array() ) {
		parent::__construct( $tools, $trajectory, $options );

		$this->provider_manager = $provider_manager;
	}

	/**
	 * Prompts the LLM with the current trajectory as input.
	 *
	 * @since 0.1.0
	 *
	 * @param PromptBuilder $prompt The prompt builder instance including the trajectory and function declarations.
	 * @return Message The result message from the LLM.
	 */
	protected function prompt_llm( PromptBuilder $prompt ): Message {
		$provider_id = $this->provider_manager->get_current_provider_id();
		if ( '' !== $provider_id ) {

			$model_id = $this->provider_manager->get_preferred_model_id( $provider_id );
			if ( '' !== $model_id ) {
				$prompt = $prompt->usingModel(
					$this->provider_manager->get_registry()->getProviderModel( $provider_id, $model_id )
				);
			}
		}

		return $prompt
			->usingSystemInstruction( $this->get_system_instruction() )
			->generateTextResult()
			->toMessage();
	}

	/**
	 * Checks whether the agent has finished its execution based on the new messages added to the agent's trajectory.
	 *
	 * @since 0.1.0
	 *
	 * @param array<Message> $new_messages The new messages appended to the agent's trajectory during the step.
	 * @return bool True if the agent has finished, false otherwise.
	 */
	protected function is_finished( array $new_messages ): bool {
		$last_message = end( $new_messages );

		// If the last message is from the user (e.g. a function response), the agent has not finished yet.
		return ! $last_message->getRole()->isUser();
	}

	/**
	 * Gets the system instructions for the chatbot agent.
	 *
	 * @since 0.1.0
	 *
	 * @return string The system instructions.
	 */
	protected function get_system_instruction(): string {
		return ''; // TODO.
	}
}
