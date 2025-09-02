<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Create_Post_Draft_Ability
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use WP_Error;

/**
 * Ability to create a new WordPress post in "draft" status.
 *
 * @since 0.1.0
 */
class Create_Post_Draft_Ability extends Abstract_Ability {

	/**
	 * Returns the description of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the ability.
	 */
	protected function description(): string {
		return 'Creates a new WordPress post in "draft" status.';
	}

	/**
	 * Returns the input schema of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The input schema of the ability.
	 */
	protected function input_schema(): array {
		$allowed_post_tags = array(
			'a',
			'br',
			'code',
			'em',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'li',
			'ol',
			'p',
			'strong',
			'ul',
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'post_title'   => array(
					'type'        => 'string',
					'description' => 'The title of the post.',
				),
				'post_content' => array(
					'type'        => 'string',
					'description' => 'The content of the post, as HTML. Never include any class or style attributes. Allowed HTML tags are: ' . implode( ', ', $allowed_post_tags ),
				),
			),
			'required'   => array( 'post_title', 'post_content' ),
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
				'post_id'       => array(
					'type'        => 'integer',
					'description' => 'The ID of the newly created post.',
				),
				'post_edit_url' => array(
					'type'        => 'string',
					'description' => 'The URL to edit the post in the WordPress admin.',
				),
				'message'       => array(
					'type'        => 'string',
					'description' => 'A success message.',
				),
			),
			'required'   => array( 'post_id', 'post_edit_url', 'message' ),
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
			'post_id'       => $post_id,
			'post_edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'message'       => 'Post draft created successfully.',
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
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to create posts.'
			);
		}
		return true;
	}
}
