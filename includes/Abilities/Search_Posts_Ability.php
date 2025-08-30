<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Search_Posts_Ability
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use WP_Error;

/**
 * Ability to search for WordPress posts.
 *
 * @since 0.1.0
 */
class Search_Posts_Ability extends Abstract_Ability {

	/**
	 * Returns the description of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the ability.
	 */
	protected function description(): string {
		return 'Searches through the site\'s posts (only of the "post" post type) for a given search string and returns an array of up to 20 post IDs and titles.';
	}

	/**
	 * Returns the input schema of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The input schema of the ability.
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'search_string' => array(
					'type'        => 'string',
					'description' => 'The string to search for in post titles and content.',
				),
			),
			'required'   => array( 'search_string' ),
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
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => 'The ID of the post.',
					),
					'post_title' => array(
						'type'        => 'string',
						'description' => 'The title of the post.',
					),
				),
				'required'   => array( 'post_id', 'post_title' ),
			),
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
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ), // Include various statuses for comprehensive search.
			's'              => $args['search_string'],
			'posts_per_page' => 20, // Limit to 20 results for performance.
		);

		$query = new \WP_Query( $query_args );

		$results = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$results[] = array(
					'post_id'    => $post->ID,
					'post_title' => $post->post_title,
				);
			}
		}

		return $results;
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
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to read posts.'
			);
		}
		return true;
	}
}
