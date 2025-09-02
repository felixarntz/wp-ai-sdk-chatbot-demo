<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Set_Permalink_Structure_Ability
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use WP_Error;

/**
 * Ability to set the WordPress permalink structure.
 *
 * @since 0.1.0
 */
class Set_Permalink_Structure_Ability extends Abstract_Ability {

	/**
	 * Returns the description of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the ability.
	 */
	protected function description(): string {
		return 'Sets the permalink structure for the WordPress site (enables/disables pretty permalinks).';
	}

	/**
	 * Returns the input schema of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The input schema of the ability.
	 */
	protected function input_schema(): array {
		$allowed_values = array(
			'disabled',
			'/%year%/%monthnum%/%day%/%postname%/',
			'/%year%/%monthnum%/%postname%/',
			'/%postname%/',
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'permalink_structure' => array(
					'type'        => 'string',
					'description' => 'The permalink structure to use. All URL paths must end with a trailing slash. Use "disabled" to turn off pretty permalinks.',
					'enum'        => $allowed_values,
				),
			),
			'required'   => array( 'permalink_structure' ),
		);
	}

	/**
	 * Returns the output schema of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The output schema of the ability.
	 */
	protected function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'message' => array(
					'type'        => 'string',
					'description' => 'A success message.',
				),
			),
			'required'   => array( 'message' ),
		);
	}

	/**
	 * Executes the ability with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the ability.
	 * @return mixed|WP_Error The result of the ability execution, or a WP_Error on failure.
	 */
	protected function execute_callback( $args ) {
		global $wp_rewrite;

		$allowed_values = array(
			'disabled',
			'/%year%/%monthnum%/%day%/%postname%/',
			'/%year%/%monthnum%/%postname%/',
			'/%postname%/',
		);

		if ( ! in_array( $args['permalink_structure'], $allowed_values, true ) ) {
			return new WP_Error(
				'invalid_permalink_structure',
				'Only the following values are allowed: ' . implode( ', ', $allowed_values )
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

	/**
	 * Checks whether the current user has permission to execute the ability with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the ability.
	 * @return bool|WP_Error True if the user has permission, false or WP_Error otherwise.
	 */
	protected function permission_callback( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to set the permalink structure.'
			);
		}
		return true;
	}
}
