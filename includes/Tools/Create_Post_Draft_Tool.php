<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Create_Post_Draft_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use WP_Error;

/**
 * Tool to create a new WordPress post in "draft" status.
 *
 * @since 0.1.0
 */
class Create_Post_Draft_Tool extends Abstract_Tool {

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'create_post_draft';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Creates a new WordPress post in "draft" status.';
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
				'post_title'   => array(
					'type'        => 'string',
					'description' => 'The title of the post.',
				),
				'post_content' => array(
					'type'        => 'string',
					'description' => 'The content of the post.',
				),
			),
			'required'   => array( 'post_title', 'post_content' ),
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
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to create posts.'
			);
		}

		$post_data = array(
			'post_title'   => $args['post_title'],
			'post_content' => $args['post_content'],
			'post_status'  => 'draft',
			'post_type'    => 'post',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'post_id' => $post_id,
			'message' => 'Post draft created successfully.',
		);
	}
}
