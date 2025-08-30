<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Generate_Post_Featured_Image_Ability
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use Exception;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\AiClient;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Files\Enums\FileTypeEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\MessagePart;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use RuntimeException;
use WP_Error;

/**
 * Ability to generate and assign a featured image for a given post.
 *
 * @since 0.1.0
 */
class Generate_Post_Featured_Image_Ability extends Abstract_Ability {

	/**
	 * Returns the description of the ability.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the ability.
	 */
	protected function description(): string {
		return 'Generates a featured image for a given post using an LLM and assigns it to the post.';
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
				'post_id'     => array(
					'type'        => 'integer',
					'description' => 'The ID of the post to generate a featured image for.',
				),
				'instruction' => array(
					'type'        => 'string',
					'description' => 'Optional instruction for what kind of image to generate.',
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
				'attachment_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the newly created attachment.',
				),
				'message'       => array(
					'type'        => 'string',
					'description' => 'A success message.',
				),
			),
			'required'   => array( 'attachment_id', 'message' ),
		);
	}

	/**
	 * Executes the ability with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the ability.
	 * @return mixed|WP_Error The result of the ability execution, or a WP_Error on failure.
	 *
	 * @throws RuntimeException If the generated image is not an inline image (which should never happen given the config).
	 */
	protected function execute_callback( $args ) {
		$post = get_post( $args['post_id'] );

		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				'Post with ID ' . $args['post_id'] . ' not found.'
			);
		}

		$prompt = 'Generate a featured image for the post titled "' . $post->post_title . '".';

		if ( ! empty( $args['instruction'] ) ) {
			$prompt .= ' Instruction: ' . $args['instruction'];
		} else {
			$trimmed_content = wp_trim_words( $post->post_content, 200, '...' );
			$prompt         .= ' Post content: ' . $trimmed_content;
		}

		$prompt = array(
			new Message(
				MessageRoleEnum::user(),
				array(
					new MessagePart( $prompt ),
				)
			),
		);

		try {
			$image_file = AiClient::prompt( $prompt )
				->asOutputFileType( FileTypeEnum::inline() )
				->generateImageResult()
				->toImageFile();
			if ( ! $image_file->getFileType()->isInline() ) {
				throw new RuntimeException( 'Generated image is not inline.' );
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'image_generation_failed',
				$e->getMessage()
			);
		}

		$base64_data = $image_file->getBase64Data();
		$mime_type   = $image_file->getMimeType();

		if ( empty( $base64_data ) || empty( $mime_type ) ) {
			return new WP_Error(
				'image_generation_failed',
				'Image generation failed or returned empty data.'
			);
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error(
				'upload_dir_error',
				'Could not get upload directory: ' . $upload_dir['error']
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$image_data = base64_decode( $base64_data );
		$filename   = sanitize_file_name( 'featured-image-' . $post->ID . '.' . explode( '/', $mime_type )[1] );
		$filepath   = $upload_dir['path'] . '/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $filepath, $image_data ) === false ) {
			return new WP_Error(
				'file_write_error',
				'Could not write image file to ' . $filepath
			);
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $filepath,
			'type'     => $mime_type,
		);

		// Required for wp_handle_sideload to work.
		require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore-line
		require_once ABSPATH . 'wp-admin/includes/media.php'; // @phpstan-ignore-line
		require_once ABSPATH . 'wp-admin/includes/image.php'; // @phpstan-ignore-line

		$sideload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

		// @phpstan-ignore-next-line
		if ( is_wp_error( $sideload ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $filepath ); // Clean up the temporary file.
			return $sideload;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $sideload['url'],
				'post_mime_type' => $sideload['type'],
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$sideload['file'],
			$post->ID
		);

		// @phpstan-ignore-next-line
		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $sideload['file'] ); // Clean up the uploaded file.
			return $attachment_id;
		}

		// Generate attachment metadata and update the attachment.
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $sideload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Set the featured image.
		set_post_thumbnail( $post->ID, $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'message'       => 'Featured image generated and assigned successfully.',
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
		if ( ! current_user_can( 'edit_post', $args['post_id'] ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to edit this post.'
			);
		}
		return true;
	}
}
