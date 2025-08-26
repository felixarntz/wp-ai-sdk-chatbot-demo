<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Set_Permalink_Structure_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use WP_Error;

/**
 * Tool to set the WordPress permalink structure.
 *
 * @since 0.1.0
 */
class Set_Permalink_Structure_Tool extends Abstract_Tool {

	/**
	 * The allowed values to set the permalink structure to.
	 *
	 * @since 0.1.0
	 * @var array<string>
	 */
	protected array $allowed_values = array(
		'disabled',
		'/%year%/%monthnum%/%day%/%postname%/',
		'/%year%/%monthnum%/%postname%/',
		'/%postname%/',
	);

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'set_permalink_structure';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Sets the permalink structure for the WordPress site (enables/disables pretty permalinks).';
	}

	/**
	 * Returns the parameters of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The parameters of the tool.
	 */
	protected function parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'permalink_structure' => array(
					'type'        => 'string',
					'description' => 'The permalink structure to use. All URL paths must end with a trailing slash. Use "disabled" to turn off pretty permalinks.',
					'enum'        => $this->allowed_values,
				),
			),
			'required'   => array( 'permalink_structure' ),
		);
	}

	/**
	 * Executes the tool with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the tool.
	 * @return mixed|WP_Error The result of the tool execution, or a WP_Error on failure.
	 */
	public function execute( $args ) {
		global $wp_rewrite;

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to set the permalink structure.'
			);
		}

		if ( ! in_array( $args['permalink_structure'], $this->allowed_values, true ) ) {
			return new WP_Error(
				'invalid_permalink_structure',
				'Only the following values are allowed: ' . implode( ', ', $this->allowed_values )
			);
		}

		$permalink_structure = $args['permalink_structure'];
		if ( 'disabled' === $permalink_structure ) {
			$permalink_structure = '';
		}

		$wp_rewrite->set_permalink_structure( $permalink_structure );
		flush_rewrite_rules();

		return array(
			'message' => 'Permalink structure successfully updated.',
		);
	}
}
