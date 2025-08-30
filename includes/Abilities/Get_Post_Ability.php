<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Get_Post_Ability
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use WP_Error;

/**
 * Ability to get the title and content of a WordPress post.
 *
 * @since 0.1.0
 */
class Get_Post_Ability extends Abstract_Ability {

	/**
	 * Returns the description of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the ability.
	 */
	protected function description(): string {
		return 'Returns the title, content, and more information of a WordPress post for a given post ID.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the post to retrieve.',
				),
			),
			'required'   => array( 'post_id' ),
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
				'post_title'    => array(
					'type'        => 'string',
					'description' => 'The title of the post.',
				),
				'post_content'  => array(
					'type'        => 'string',
					'description' => 'The content of the post.',
				),
				'post_status'   => array(
					'type'        => 'string',
					'description' => 'The status of the post.',
				),
				'post_edit_url' => array(
					'type'        => 'string',
					'description' => 'The URL to edit the post in the WordPress admin.',
				),
				'post_url'      => array(
					'type'        => 'string',
					'description' => 'The public URL of the post, if published.',
				),
			),
			'required'   => array( 'post_title', 'post_content', 'post_status', 'post_edit_url' ),
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

	/**
	 * Checks whether the current user has permission to execute the ability with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the ability.
	 * @return bool|WP_Error True if the user has permission, false or WP_Error otherwise.
	 */
	protected function permission_callback( $args ) {
		if ( ! current_user_can( 'read_post', $args['post_id'] ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to read this post.'
			);
		}
		return true;
	}
}
