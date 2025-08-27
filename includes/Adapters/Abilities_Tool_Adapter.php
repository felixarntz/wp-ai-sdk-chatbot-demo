<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Adapters\Abilities_Tool_Adapter
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Adapters;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool;
use WP_Ability;
use WP_Error;

/**
 * Adapter that converts WordPress Abilities API abilities into Tool interface objects.
 *
 * This adapter allows the PHP AI Client SDK to work with WordPress abilities
 * without modification, bridging the gap between the two systems.
 *
 * @since 0.1.0
 */
class Abilities_Tool_Adapter implements Tool {

	/**
	 * The underlying ability instance.
	 *
	 * @since 0.1.0
	 * @var WP_Ability
	 */
	private WP_Ability $ability;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Ability $ability The ability instance to adapt.
	 */
	public function __construct( WP_Ability $ability ) {
		$this->ability = $ability;
	}

	/**
	 * Gets the name of the tool.
	 *
	 * Converts the namespaced ability name to a snake_case format suitable for LLM function calls.
	 * Example: "wp-ai-sdk-chatbot-demo/create-post-draft" becomes "create_post_draft"
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	public function get_name(): string {
		$ability_name = $this->ability->get_name();
		
		// Remove the namespace prefix for cleaner function names
		$name_parts = explode( '/', $ability_name, 2 );
		$function_name = isset( $name_parts[1] ) ? $name_parts[1] : $ability_name;
		
		// Convert kebab-case to snake_case for function naming convention
		return str_replace( '-', '_', $function_name );
	}

	/**
	 * Gets the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	public function get_description(): string {
		return $this->ability->get_description();
	}

	/**
	 * Gets the parameters of the tool.
	 *
	 * Converts the ability's input schema to the tool parameter format expected by the PHP AI Client SDK.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The parameters of the tool.
	 */
	public function get_parameters(): array {
		$input_schema = $this->ability->get_input_schema();
		
		// If the ability has no input schema, return empty parameters
		if ( empty( $input_schema ) ) {
			return [
				'type' => 'object',
				'properties' => [],
				'additionalProperties' => false,
			];
		}

		// The input schema from abilities API should already be in the correct JSON Schema format
		// that the PHP AI Client SDK expects for FunctionDeclaration parameters
		return $input_schema;
	}

	/**
	 * Executes the tool with the given input arguments.
	 *
	 * This method serves as a bridge between the Tool interface and the Abilities API,
	 * handling argument conversion and error formatting.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the tool.
	 * @return mixed|WP_Error The result of the tool execution, or a WP_Error on failure.
	 */
	public function execute( $args ) {
		// Convert args to array format expected by abilities
		$input_args = $this->convert_args_to_array( $args );

		// Execute the underlying ability
		$result = $this->ability->execute( $input_args );

		// If the result is a WP_Error, we can return it directly
		// as the Abstract_Agent will handle WP_Error conversion to string
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// For successful results, return them as-is
		// The abilities API handles output validation
		return $result;
	}

	/**
	 * Converts function call arguments to array format.
	 *
	 * The PHP AI Client SDK may pass arguments in various formats,
	 * so this method normalizes them to the array format expected by abilities.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The arguments to convert.
	 * @return array<string, mixed> The converted arguments.
	 */
	private function convert_args_to_array( $args ): array {
		// If args is already an array, use it as-is
		if ( is_array( $args ) ) {
			return $args;
		}

		// If args is an object, convert to array
		if ( is_object( $args ) ) {
			return (array) $args;
		}

		// For primitive types or null, return empty array
		// This handles cases where no arguments are provided
		if ( is_null( $args ) || is_scalar( $args ) ) {
			return [];
		}

		// Fallback: try to convert to array
		return (array) $args;
	}

	/**
	 * Gets the underlying ability instance.
	 *
	 * This method provides access to the original ability for debugging
	 * or advanced use cases.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_Ability The underlying ability instance.
	 */
	public function get_ability(): WP_Ability {
		return $this->ability;
	}
}