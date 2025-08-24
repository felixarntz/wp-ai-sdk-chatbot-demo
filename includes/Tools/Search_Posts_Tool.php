<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Search_Posts_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use WP_Error;

/**
 * Tool to search for WordPress posts.
 *
 * @since 0.1.0
 */
class Search_Posts_Tool extends Abstract_Tool {

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'search_posts';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Searches through the site\'s posts (only of the "post" post type) for a given search string and returns an array of up to 20 post IDs and titles.';
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
				'search_string' => array(
					'type'        => 'string',
					'description' => 'The string to search for in post titles and content.',
				),
			),
			'required'   => array( 'search_string' ),
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
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to read posts.'
			);
		}

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
}
