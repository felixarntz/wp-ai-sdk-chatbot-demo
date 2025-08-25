<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Publish_Post_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use WP_Error;

/**
 * Tool to publish an existing WordPress post.
 *
 * @since 0.1.0
 */
class Publish_Post_Tool extends Abstract_Tool {

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'publish_post';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Publishes an existing WordPress post.';
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
					'description' => 'The ID of the post to publish.',
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
		if ( ! current_user_can( 'publish_post', $args['post_id'] ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to publish this post.'
			);
		}

		$post = get_post( $args['post_id'] );

		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				'Post with ID ' . $args['post_id'] . ' not found.'
			);
		}

		if ( 'publish' === $post->post_status ) {
			return array(
				'post_id'       => $post->ID,
				'post_edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				'post_url'      => get_permalink( $post ),
				'message'       => 'Post is already published.',
			);
		}

		$updated_post_id = wp_update_post(
			array(
				'ID'          => $args['post_id'],
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $updated_post_id ) ) {
			return $updated_post_id;
		}

		return array(
			'post_id'       => $updated_post_id,
			'post_edit_url' => get_edit_post_link( $updated_post_id, 'raw' ),
			'post_url'      => get_permalink( $updated_post_id ),
			'message'       => 'Post published successfully.',
		);
	}
}
