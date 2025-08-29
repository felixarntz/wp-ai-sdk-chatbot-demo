<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\REST_Routes\Chatbot_Messages_REST_Route
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\REST_Routes;

use Exception;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Chatbot_Agent;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Create_Post_Draft_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Generate_Post_Featured_Image_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Get_Post_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Publish_Post_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Search_Posts_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Set_Permalink_Structure_Tool;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\Message;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\DTO\MessagePart;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessagePartTypeEnum;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class for the chatbot messages REST API routes.
 *
 * @since 0.1.0
 */
class Chatbot_Messages_REST_Route {

	/**
	 * The provider manager instance.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * The REST route namespace.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $rest_namespace;

	/**
	 * The REST route base.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected string $rest_base;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Provider_Manager $provider_manager The provider manager instance.
	 * @param string           $rest_namespace   The REST route namespace.
	 * @param string           $rest_base        The REST route base.
	 */
	public function __construct( Provider_Manager $provider_manager, string $rest_namespace, string $rest_base ) {
		$this->provider_manager = $provider_manager;
		$this->rest_namespace   = $rest_namespace;
		$this->rest_base        = $rest_base;
	}

	/**
	 * Registers the REST route with its endpoints.
	 *
	 * @since 0.1.0
	 */
	public function register_route(): void {
		register_rest_route(
			$this->rest_namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema(
						$this->get_message_schema(),
						WP_REST_Server::CREATABLE
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'reset_messages' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(),
				),
				'schema' => array( $this, 'get_message_schema' ),
			)
		);
	}

	/**
	 * Checks the required permissions for the routes.
	 *
	 * @since 0.1.0
	 *
	 * @return bool|WP_Error Whether the user has the required permissions or a WP_Error object if not.
	 */
	public function check_permissions() {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'wpaisdk_access_chatbot' ) ) {
			return new WP_Error(
				'rest_cannot_access_chatbot',
				esc_html__( 'Sorry, you are not allowed to access the chatbot.', 'wp-ai-sdk-chatbot-demo' ),
				is_user_logged_in() ? 403 : 401
			);
		}
		return true;
	}

	/**
	 * Handles the given request to get messages and returns a response.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response WordPress REST response object.
	 */
	public function get_messages(): WP_REST_Response {
		$messages = $this->get_messages_history();

		return rest_ensure_response( $messages );
	}

	/**
	 * Handles the given request to send a message and returns a response.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request WordPress REST request object, including parameters.
	 * @return WP_REST_Response WordPress REST response object.
	 */
	public function send_message( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace, @phpstan-ignore-line
		$messages = $this->get_messages_history();

		$message_schema = $this->get_message_schema();

		$new_message = array();
		foreach ( $message_schema['properties'] as $prop => $schema ) {
			if ( isset( $request[ $prop ] ) ) {
				$new_message[ $prop ] = $request[ $prop ];
			} elseif ( isset( $schema['default'] ) ) {
				$new_message[ $prop ] = $schema['default'];
			}
		}

		$messages[] = $new_message;

		$message_instances = $this->prepare_message_instances( $messages );

		try {
			$featured_image_generation_tool = new Generate_Post_Featured_Image_Tool();

			$tools = array(
				new Search_Posts_Tool(),
				new Get_Post_Tool(),
				new Create_Post_Draft_Tool(),
				$featured_image_generation_tool,
				new Publish_Post_Tool(),
				new Set_Permalink_Structure_Tool(),
			);

			$agent = new Chatbot_Agent( $this->provider_manager, $tools, $message_instances );
			do {
				$agent_result = $agent->step();
			} while ( ! $agent_result->finished() );

			$result_message = array_merge(
				array( 'type' => 'regular' ),
				$agent_result->last_message()->toArray()
			);
		} catch ( Exception $e ) {
			$error_message = sprintf(
				/* translators: %s: original error message */
				esc_html__( 'An error occurred while processing the request: %s', 'wp-ai-sdk-chatbot-demo' ),
				esc_html( $e->getMessage() )
			);

			$result_message = array(
				'type'             => 'error',
				Message::KEY_ROLE  => MessageRoleEnum::model()->value,
				Message::KEY_PARTS => array(
					array(
						MessagePart::KEY_CHANNEL => MessagePartChannelEnum::content()->value,
						MessagePart::KEY_TYPE    => MessagePartTypeEnum::text()->value,
						MessagePart::KEY_TEXT    => $error_message,
					),
				),
			);
		}

		$messages[] = $result_message;

		update_user_option( get_current_user_id(), 'wpaisdk_chatbot_messages', $messages );

		return rest_ensure_response( $result_message );
	}

	/**
	 * Handles the request to reset the messages history.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response WordPress REST response object.
	 */
	public function reset_messages(): WP_REST_Response {
		// Gets the (previous) messages history to return it after resetting.
		$messages = $this->get_messages_history();

		delete_user_option( get_current_user_id(), 'wpaisdk_chatbot_messages' );

		return rest_ensure_response( $messages );
	}

	/**
	 * Returns the schema for a single message object.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The schema for the message object.
	 */
	public function get_message_schema(): array {
		$message_schema = Message::getJsonSchema();

		$properties = array();
		foreach ( $message_schema['properties'] as $prop => $schema ) {
			// Transform the more modern JSON schema format from the PHP SDK to the format expected by WordPress.
			if ( isset( $schema['required'] ) && in_array( $prop, $message_schema['required'], true ) ) {
				$schema['required'] = true;
			}
			$properties[ $prop ] = $schema;
		}

		/*
		 * Additional property to indicate whether a message is an error response from a model API.
		 * This is purely for visual purposes in the UI. Under the hood it is treated like a regular message.
		 */
		$properties['type'] = array(
			'description' => __( 'Type of the message.', 'wp-ai-sdk-chatbot-demo' ),
			'type'        => 'string',
			'enum'        => array( 'regular', 'error' ),
			'default'     => 'regular',
		);

		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'chatbot_message',
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * Gets the list of messages history for the current user.
	 *
	 * @since 0.1.0
	 *
	 * @return array<array<string, mixed>> The list of messages, conforming to the Message schema plus an additional
	 *                                    'type' property.
	 */
	protected function get_messages_history(): array {
		$messages = get_user_option( 'wpaisdk_chatbot_messages' );
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		return $messages;
	}

	/**
	 * Prepares the given list of messages history to be returned as Message instances.
	 *
	 * @since 0.1.0
	 *
	 * @param array<array<string, mixed>> $messages The list of messages to prepare.
	 * @return array<Message> The list of prepared Message instances.
	 */
	protected function prepare_message_instances( array $messages ): array {
		return array_map(
			static function ( $message ) {
				// The 'type' property is not part of the original message schema, so we unset it here.
				unset( $message['type'] );
				return Message::fromArray( $message );
			},
			$messages
		);
	}
}
