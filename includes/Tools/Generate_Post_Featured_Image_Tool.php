<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Generate_Post_Featured_Image_Tool
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\PromptBuilder;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\AiClient;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Files\Enums\FileTypeEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\MessagePart;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Providers\ProviderRegistry;
use WP_Error;
use RuntimeException;

/**
 * Tool to generate and assign a featured image for a given post.
 *
 * @since 0.1.0
 */
class Generate_Post_Featured_Image_Tool extends Abstract_Tool {

	/**
	 * Temporary registry to use if AiClient is not available.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager|null
	 */
	public ?ProviderRegistry $temp_registry = null;

	/**
	 * Returns the name of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The name of the tool.
	 */
	protected function name(): string {
		return 'generate_post_featured_image';
	}

	/**
	 * Returns the description of the tool.
	 *
	 * @since 0.1.0
	 *
	 * @return string The description of the tool.
	 */
	protected function description(): string {
		return 'Generates a featured image for a given post using an LLM and assigns it to the post.';
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
	 * Executes the tool with the given input arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $args The input arguments to the tool.
	 * @return mixed|WP_Error The result of the tool execution, or a WP_Error on failure.
	 *
	 * @throws RuntimeException If the generated image is not an inline image (which should never happen given the config).
	 */
	public function execute( $args ) {
		if ( ! current_user_can( 'edit_post', $args['post_id'] ) ) {
			return new WP_Error(
				'insufficient_capabilities',
				'You do not have permission to edit this post.'
			);
		}

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

		if ( class_exists( AiClient::class ) ) {
			$prompt_builder = AiClient::prompt( $prompt );
		} else {
			if ( ! isset( $this->temp_registry ) ) {
				throw new RuntimeException( 'Temporary provider registry not set.' );
			}
			$prompt_builder = new PromptBuilder( $this->temp_registry, $prompt );
		}

		try {
			$image_file = $prompt_builder
				->usingOutputFileType( FileTypeEnum::inline() )
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
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$sideload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

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
}
