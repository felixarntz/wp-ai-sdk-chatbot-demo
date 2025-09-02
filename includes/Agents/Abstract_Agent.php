<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Abstract_Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Contracts\Agent;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use RuntimeException;
use WP_Ability;

/**
 * Base class for an agent.
 *
 * @since 0.1.0
 */
abstract class Abstract_Agent implements Agent {

	/**
	 * The available abilities for the agent, keyed by their name.
	 *
	 * @since 0.1.0
	 * @var array<string, array>
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
	 * @param array<string>        $ability_names The names of abilities available to the agent.
	 * @param array<Message>       $trajectory    The initial trajectory of messages. Must contain at least the first message.
	 * @param array<string, mixed> $options       Additional options for the agent.
	 */
	public function __construct( array $ability_names, array $trajectory, array $options = array() ) {
		$this->abilities_map = array();
		
		// Load abilities from the WordPress Abilities API
		if ( function_exists( 'wp_get_ability' ) ) {
			foreach ( $ability_names as $ability_name ) {
				$ability = wp_get_ability( $ability_name );
				if ( $ability ) {
					$this->abilities_map[ $ability_name ] = array(
						'ability' => $ability,
						'name'    => $ability_name,
					);
				} else {
					// Log missing ability for debugging
					error_log( "WP AI Chatbot: Ability not found: " . $ability_name );
				}
			}
		} else {
			// Log missing function for debugging
			error_log( "WP AI Chatbot: wp_get_ability function not available" );
		}

		// Log how many abilities were loaded
		error_log( "WP AI Chatbot: Loaded " . count( $this->abilities_map ) . " abilities out of " . count( $ability_names ) . " requested" );
		error_log( "WP AI Chatbot: Requested abilities: " . implode( ', ', $ability_names ) );
		error_log( "WP AI Chatbot: Loaded ability names: " . implode( ', ', array_keys( $this->abilities_map ) ) );

		$this->trajectory = $trajectory;

		$this->options = wp_parse_args(
			$options,
			array(
				'max_step_retries' => 3,
			)
		);
	}

	/**
	 * Prompts the LLM with the current trajectory as input.
	 *
	 * @since 0.1.0
	 *
	 * @param PromptBuilder $prompt_builder The prompt builder instance.
	 * @return Message The response from the LLM.
	 */
	abstract protected function prompt_llm( PromptBuilder $prompt_builder ): Message;

	/**
	 * Returns the instruction to use for the agent.
	 *
	 * @since 0.1.0
	 *
	 * @return string The instruction.
	 */
	abstract protected function get_instruction(): string;

	/**
	 * Performs the next step of the agent.
	 *
	 * @since 0.1.0
	 *
	 * @return Agent_Step_Result The result of the agent step.
	 */
	public function step(): Agent_Step_Result {
		$new_messages = array();

		// Build the prompt with the trajectory and function declarations.
		$success = false;
		$retries = 0;
		do {
			++$retries;

			$prompt_builder = AiClient::prompt( $this->trajectory + $new_messages )
				->usingFunctionDeclarations( ...$this->get_function_declarations() );

			$result_message = $this->prompt_llm( $prompt_builder );

			// Log the AI response for debugging
			error_log( "WP AI Chatbot: AI model response role: " . $result_message->getRole()->value );
			$message_parts = $result_message->getParts();
			error_log( "WP AI Chatbot: AI model response parts count: " . count( $message_parts ) );
			foreach ( $message_parts as $i => $part ) {
				$part_type = $part->getType();
				error_log( "WP AI Chatbot: AI model part $i type: " . $part_type->value );
				if ( $part_type->isFunctionCall() ) {
					$func_call = $part->getFunctionCall();
					error_log( "WP AI Chatbot: AI model part $i has function call: " . $func_call->getName() );
				}
			}

			list( $function_call_abilities, $invalid_function_call_names ) = $this->extract_function_call_tools( $result_message );

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
		if ( count( $function_call_abilities ) > 0 ) {
			$function_responses = array();
			foreach ( $function_call_abilities as $pending_ability ) {
				$function_call = $pending_ability['call'];
				$ability_data  = $pending_ability['ability'];

				$function_responses[] = $this->process_function_call( $ability_data, $function_call );
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

			// Now we need to call the model again to get the next step.
			return $this->complete_step_and_get_result( false, $new_messages );
		}

		// Agent is finished as there are no function calls to execute.
		return $this->complete_step_and_get_result( true, $new_messages );
	}

	/**
	 * Gets the invalid function calls message.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string> $invalid_function_call_names The names of the invalid function calls.
	 * @return Message The invalid function calls message.
	 */
	protected function get_invalid_function_calls_message( array $invalid_function_call_names ): Message {
		$count = count( $invalid_function_call_names );
		if ( $count === 1 ) {
			$message_text = sprintf(
				'The function `%s` does not exist. Please use only the available functions.',
				$invalid_function_call_names[0]
			);
		} else {
			$message_text = sprintf(
				'The functions `%s` do not exist. Please use only the available functions.',
				implode( '`, `', $invalid_function_call_names )
			);
		}

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
	 * @param array        $ability_data  The ability data containing the ability object.
	 * @param FunctionCall $function_call The function call to process.
	 * @return FunctionResponse The response from the executed function.
	 */
	protected function process_function_call( array $ability_data, FunctionCall $function_call ): FunctionResponse {
		$ability = $ability_data['ability'];
		
		// Get function arguments
		$args = $function_call->getArgs();
		error_log( "WP AI Chatbot: Processing function call with args: " . wp_json_encode( $args ) );
		
		// Execute the ability using WordPress abilities API
		if ( $ability instanceof \WP_Ability ) {
			$response = $ability->execute( $args );
			error_log( "WP AI Chatbot: Ability execution response: " . wp_json_encode( $response ) );
		} else {
			$response = array(
				'success' => false,
				'error'   => 'Invalid ability instance',
			);
		}

		// If the response is a WP_Error, convert it to a readable message
		if ( is_wp_error( $response ) ) {
			$response = array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		return new FunctionResponse(
			$function_call->getId(),
			$function_call->getName(),
			$response
		);
	}

	/**
	 * Completes the current step and returns the result.
	 *
	 * @since 0.1.0
	 *
	 * @param bool            $finished     Whether the agent has finished.
	 * @param array<Message> $new_messages The new messages from the step.
	 * @return Agent_Step_Result The result of the agent step.
	 */
	protected function complete_step_and_get_result( bool $finished, array $new_messages ): Agent_Step_Result {
		$this->trajectory = array_merge( $this->trajectory, $new_messages );
		return new Agent_Step_Result(
			$this->current_step_index++,
			$finished,
			$new_messages
		);
	}

	/**
	 * Finds an ability by its function name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $function_name The function name to find.
	 * @return array|null The ability data if found, null otherwise.
	 */
	private function find_ability_by_function_name( string $function_name ): ?array {
		// Look for ability that matches the function name (after the slash)
		foreach ( $this->abilities_map as $ability_name => $ability_data ) {
			$extracted_name = substr( strrchr( $ability_name, '/' ), 1 ) ?: $ability_name;
			if ( $extracted_name === $function_name ) {
				return $ability_data;
			}
		}
		return null;
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
		foreach ( $this->abilities_map as $ability_name => $ability_data ) {
			$ability = $ability_data['ability'];
			
			// Extract just the function name part (after the slash)
			$function_name = substr( strrchr( $ability_name, '/' ), 1 ) ?: $ability_name;
			
			$input_schema = $ability->get_input_schema();
			error_log( "WP AI Chatbot: Raw input schema for '$function_name': " . wp_json_encode( $input_schema ) );
			
			// For both Anthropic and OpenAI, we need to provide the complete schema structure
			// Remove the $schema property as it's not needed by the AI providers
			$properties = $input_schema['properties'] ?? array();
			
			// Ensure properties is always an object for JSON encoding
			if ( is_array( $properties ) && empty( $properties ) ) {
				$properties = (object) array();
			} elseif ( is_object( $properties ) && empty( (array) $properties ) ) {
				$properties = (object) array();
			}
			
			error_log( "WP AI Chatbot: Properties extracted for '$function_name': " . wp_json_encode( $properties ) );
			
			// Always provide a schema, even for functions with no parameters
			$parameters_schema = array(
				'type' => $input_schema['type'] ?? 'object',
				'properties' => $properties,
			);
			
			// Only add required if it exists and is not empty
			if ( isset( $input_schema['required'] ) && !empty( $input_schema['required'] ) ) {
				$parameters_schema['required'] = $input_schema['required'];
			}
			
			// Debug log what we're passing
			error_log( "WP AI Chatbot: Function '$function_name' full schema: " . wp_json_encode( $parameters_schema ) );
			
			// Special attention to list-capabilities
			if ( $function_name === 'list-capabilities' ) {
				error_log( "WP AI Chatbot: *** SPECIAL LOG for list-capabilities ***" );
				error_log( "WP AI Chatbot: list-capabilities properties type: " . gettype( $properties ) );
				error_log( "WP AI Chatbot: list-capabilities properties is_array: " . ( is_array( $properties ) ? 'true' : 'false' ) );
				if ( is_array( $properties ) || is_object( $properties ) ) {
					$count = is_array( $properties ) ? count( $properties ) : count( (array) $properties );
					error_log( "WP AI Chatbot: list-capabilities properties count: " . $count );
				}
				error_log( "WP AI Chatbot: list-capabilities properties encoded: " . wp_json_encode( $properties ) );
			}
			
			$function_declarations[] = new FunctionDeclaration(
				$function_name,
				$ability->get_description(),
				$parameters_schema
			);
		}
		error_log( "WP AI Chatbot: Generated " . count( $function_declarations ) . " function declarations for AI model" );
		if ( count( $function_declarations ) === 0 ) {
			error_log( "WP AI Chatbot: WARNING - No function declarations available! This will cause the 'tools: List should have at least 1 item' error." );
		}
		return $function_declarations;
	}

	/**
	 * Extracts the function call abilities from the result message.
	 *
	 * @since 0.1.0
	 *
	 * @param Message $result_message The result message to extract function calls from.
	 * @return array{0: array<array<string, mixed>>, 1: array<string>} The first element is a list of function call
	 *                                                                 abilities, the second element is a list of invalid
	 *                                                                 function call names.
	 */
	private function extract_function_call_tools( Message $result_message ): array {
		$function_call_abilities     = array();
		$invalid_function_call_names = array();
		
		foreach ( $result_message->getParts() as $message_part ) {
			if ( $message_part->getType()->isFunctionCall() ) {
				$function_call = $message_part->getFunctionCall();
				
				// Debug log the function call details
				error_log( "WP AI Chatbot: AI model wants to call function: " . $function_call->getName() );
				error_log( "WP AI Chatbot: Function call args: " . wp_json_encode( $function_call->getArgs() ) );

				$found_ability = $this->find_ability_by_function_name( $function_call->getName() );
				if ( null === $found_ability ) {
					$invalid_function_call_names[] = $function_call->getName();
					continue;
				}

				$function_call_abilities[] = array(
					'call'    => $function_call,
					'ability' => $found_ability,
				);
			}
		}

		return array( $function_call_abilities, $invalid_function_call_names );
	}
}