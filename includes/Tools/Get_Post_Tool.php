<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Get_Post_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use WP_Error;

/**
 * Tool to get the title and content of a WordPress post.
 *
 * @since 0.1.0
 */
class Get_Post_Tool extends Abstract_Tool {

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'get_post';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Returns the title, content, and more information of a WordPress post for a given post ID.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the post to retrieve.',
				),
			),
			'required'   => array( 'post_id' ),
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
		if ( ! current_user_can( 'read_post', $args['post_id'] ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to read this post.'
			);
		}

		$post = get_post( $args['post_id'] );

		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				'Post with ID ' . $args['post_id'] . ' not found.'
			);
		}

		$result = array(
			'post_title'    => $post->post_title,
			'post_content'  => $post->post_content,
			'post_status'   => $post->post_status,
			'post_edit_url' => get_edit_post_link( $post->ID, 'raw' ),
		);
		if ( 'publish' === $post->post_status ) {
			$result['post_url'] = get_permalink( $post );
		}
		return $result;
	}
}
