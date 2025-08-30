<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Abstract_Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Contracts\Agent;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\AiClient;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Builders\PromptBuilder;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\MessagePart;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Tools\DTO\FunctionCall;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Tools\DTO\FunctionResponse;
use RuntimeException;
use WP_Ability;

/**
 * Base class for an agent.
 *
 * @since 0.1.0
 */
abstract class Abstract_Agent implements Agent {

	/**
	 * The allowed abilities for the agent, keyed by their sanitized name.
	 *
	 * @since 0.1.0
	 * @var array<WP_Ability>
	 */
	private array $abilities_map;

	/**
	 * The trajectory of messages exchanged with the agent.
	 *
	 * @since 0.1.0
	 * @var array<Message>
	 */
	private array $trajectory;

	/**
	 * The options for the agent.
	 *
	 * @since 0.1.0
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * The current step index in the agent's execution.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private int $current_step_index = 0;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array<WP_Ability>    $abilities  The abilities available to the agent.
	 * @param array<Message>       $trajectory The initial trajectory of messages. Must contain at least the first message.
	 * @param array<string, mixed> $options    Additional options for the agent.
	 */
	public function __construct( array $abilities, array $trajectory, array $options = array() ) {
		$this->abilities_map = array();
		foreach ( $abilities as $ability ) {
			$this->abilities_map[ $this->sanitize_function_name( $ability->get_name() ) ] = $ability;
		}

		$this->trajectory = $trajectory;

		$this->options = wp_parse_args(
			$options,
			array(
				'max_step_retries' => 3,
			)
		);
	}

	/**
	 * Executes a single step of the agent's execution.
	 *
	 * @since 0.1.0
	 *
	 * @return Agent_Step_Result The result of the step execution.
	 *
	 * @throws RuntimeException If the invalid function calls message is not a user message.
	 */
	final public function step(): Agent_Step_Result {
		$success      = false;
		$retries      = 0;
		$new_messages = array();

		/*
		 * Call the LLM, either to respond to the user query or to trigger function calls.
		 * In case any invalid function calls are returned, retry until a valid response is received or the maximum
		 * number of retries is reached.
		 */
		do {
			++$retries;

			$prompt_builder = AiClient::prompt( $this->trajectory + $new_messages )
				->usingFunctionDeclarations( ...$this->get_function_declarations() );

			$result_message = $this->prompt_llm( $prompt_builder );

			list( $function_call_tools, $invalid_function_call_names ) = $this->extract_function_call_abilities( $result_message );

			$new_messages[] = $result_message;

			if ( count( $invalid_function_call_names ) > 0 ) {
				$invalid_function_calls_message = $this->get_invalid_function_calls_message( $invalid_function_call_names );
				if ( ! $invalid_function_calls_message->getRole()->isUser() ) {
					throw new RuntimeException(
						'Invalid function calls message must be a user message.'
					);
				}
				$new_messages[] = $invalid_function_calls_message;
			} else {
				$success = true;
			}
		} while ( ! $success && $retries < $this->options['max_step_retries'] );

		if ( ! $success ) {
			return $this->complete_step_and_get_result( false, $new_messages );
		}

		// If there are pending function calls, we need to execute them.
		if ( count( $function_call_tools ) > 0 ) {
			$function_responses = array();
			foreach ( $function_call_tools as $pending_tool ) {
				$function_call = $pending_tool['call'];
				$ability       = $pending_tool['tool'];

				$function_responses[] = $this->process_function_call( $ability, $function_call );
			}

			// Append a message to the trajectory with the function responses.
			$new_messages[] = new Message(
				MessageRoleEnum::user(),
				array_map(
					function ( FunctionResponse $function_response ) {
						return new MessagePart( $function_response );
					},
					$function_responses
				)
			);
		}

		return $this->complete_step_and_get_result( $this->is_finished( $new_messages ), $new_messages );
	}

	/**
	 * Prompts the LLM with the current trajectory as input.
	 *
	 * @since 0.1.0
	 *
	 * @param PromptBuilder $prompt The prompt builder instance including the trajectory and function declarations.
	 * @return Message The result message from the LLM.
	 */
	abstract protected function prompt_llm( PromptBuilder $prompt ): Message;

	/**
	 * Checks whether the agent has finished its execution based on the new messages added to the agent's trajectory.
	 *
	 * @since 0.1.0
	 *
	 * @param array<Message> $new_messages The new messages appended to the agent's trajectory during the step.
	 * @return bool True if the agent has finished, false otherwise.
	 */
	abstract protected function is_finished( array $new_messages ): bool;

	/**
	 * Gets the message to send to the LLM when invalid function calls were made.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string> $invalid_function_call_names The names of the invalid function calls.
	 * @return Message The message to return. Must be a user message.
	 */
	protected function get_invalid_function_calls_message( array $invalid_function_call_names ): Message {
		$message_suffix  = 'Please try again. Make sure to only call functions that are available.' . "\n";
		$message_suffix .= 'None of the function calls from your last message were executed. You must re-send all of them, including the invalid ones, in your next message.';

		$message_text = (
			count( $invalid_function_call_names ) > 1 ?
				'You called some functions that are not available: ' . implode( ', ', $invalid_function_call_names ) :
				'You called a function that is not available: ' . implode( ', ', $invalid_function_call_names )
		) . "\n" . $message_suffix;

		return new Message(
			MessageRoleEnum::user(),
			array(
				new MessagePart( $message_text ),
			)
		);
	}

	/**
	 * Processes a function call by executing the corresponding ability and returning the function response.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Ability   $ability       The ability to execute.
	 * @param FunctionCall $function_call The function call to process.
	 * @return FunctionResponse The response from the executed function.
	 */
	protected function process_function_call( WP_Ability $ability, FunctionCall $function_call ): FunctionResponse {
		// Call the ability with the provided arguments.
		$response = $ability->execute( $function_call->getArgs() );
		if ( is_wp_error( $response ) ) {
			$response = 'The function call failed with an error: ' . $response->get_error_message();
		}

		return new FunctionResponse(
			$function_call->getId(),
			$function_call->getName(),
			$response
		);
	}

	/**
	 * Completes the step and returns the result.
	 *
	 * @since 0.1.0
	 *
	 * @param bool           $finished     Whether the agent has finished its execution.
	 * @param array<Message> $new_messages The new messages appended to the agent's trajectory during the step.
	 * @return Agent_Step_Result The result of the step execution.
	 */
	private function complete_step_and_get_result( bool $finished, array $new_messages ): Agent_Step_Result {
		// Append the new messages to the trajectory, so that they are available for the next step.
		foreach ( $new_messages as $new_message ) {
			$this->trajectory[] = $new_message;
		}

		return new Agent_Step_Result(
			$this->current_step_index++,
			$finished,
			$new_messages
		);
	}

	/**
	 * Finds an ability by its name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the ability to find.
	 * @return WP_Ability|null The ability if found, null otherwise.
	 */
	private function find_ability_by_name( string $name ): ?WP_Ability {
		return $this->abilities_map[ $name ] ?? null;
	}

	/**
	 * Gets the function declarations for the abilities available to the agent.
	 *
	 * @since 0.1.0
	 *
	 * @return array<FunctionDeclaration> The function declarations.
	 */
	private function get_function_declarations(): array {
		$function_declarations = array();
		foreach ( $this->abilities_map as $ability ) {
			$function_declarations[] = new FunctionDeclaration(
				$this->sanitize_function_name( $ability->get_name() ),
				$ability->get_description(),
				$ability->get_input_schema()
			);
		}
		return $function_declarations;
	}

	/**
	 * Sanitizes a function name so that it can be processed by LLMs.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The function or tool name to sanitize.
	 * @return string The sanitized function name.
	 */
	private function sanitize_function_name( string $name ): string {
		return str_replace( array( '/', '-' ), '_', $name );
	}

	/**
	 * Extracts the function call abilities from the result message.
	 *
	 * @since 0.1.0
	 *
	 * @param Message $result_message The result message to extract function calls from.
	 * @return array{0: array<array<string, mixed>>, 1: array<string>} The first element is a list of function call
	 *                                                                 abilities, the second element is a list of
	 *                                                                 invalid function call names.
	 */
	private function extract_function_call_abilities( Message $result_message ): array {
		$function_call_abilities     = array();
		$invalid_function_call_names = array();
		foreach ( $result_message->getParts() as $message_part ) {
			if ( $message_part->getType()->isFunctionCall() ) {
				$function_call = $message_part->getFunctionCall();

				$found_ability = $this->find_ability_by_name( $function_call->getName() );
				if ( null === $found_ability ) {
					$invalid_function_call_names[] = $function_call->getName();
					continue;
				}

				$function_call_abilities[] = array(
					'call' => $function_call,
					'tool' => $found_ability,
				);
			}
		}

		return array( $function_call_abilities, $invalid_function_call_names );
	}
}
