<?php
/**
 * Interface Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts;

use WP_Error;

/**
 * Interface for a tool.
 *
 * @since 0.1.0
 */
interface Tool {

	/**
	 * Gets the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	public function get_name(): string;

	/**
	 * Gets the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	public function get_description(): string;

	/**
	 * Gets the parameters of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The parameters of the tool.
	 */
	public function get_parameters(): array;

	/**
	 * Executes the tool with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the tool.
	 * @return mixed|WP_Error The result of the tool execution, or a WP_Error on failure.
	 */
	public function execute( $args );
}
