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
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
use WordPress\AiClient\Messages\Enums\MessagePartTypeEnum;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
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
	 * The namespace for the REST API routes.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected const NAMESPACE = 'wpaisdk-chatbot/v1';

	/**
	 * The route for the messages.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected const ROUTE = '/messages';

	/**
	 * The provider manager instance.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager
	 */
	protected Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Provider_Manager $provider_manager The provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
	}

	/**
	 * Registers the REST API routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		// Route to get all messages.
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_get_request' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_post_request' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_delete_request' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Checks if the current user has permission to access the endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if the user has permission, false otherwise.
	 */
	public function check_permission(): bool {
		if ( ! current_user_can( 'wpaisdk_access_chatbot' ) ) {
			return false;
		}

		// Additionally, limit access to the current provider.
		$provider_id = $this->provider_manager->get_current_provider_id();
		if ( ! $provider_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles the request to get the messages history.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function handle_get_request(): WP_REST_Response {
		return rest_ensure_response(
			get_user_option( 'wpaisdk_chatbot_messages', get_current_user_id() ) ?: array()
		);
	}

	/**
	 * Handles the request to create a new message.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object or error.
	 */
	public function handle_post_request( WP_REST_Request $request ) {
		$messages = get_user_option( 'wpaisdk_chatbot_messages', get_current_user_id() ) ?: array();

		$params = $request->get_params();

		// Validate message structure.
		if ( ! isset( $params[ Message::KEY_ROLE ] ) || ! isset( $params[ Message::KEY_PARTS ] ) ) {
			return new WP_Error(
				'invalid_message',
				__( 'Invalid message structure.', 'wp-ai-sdk-chatbot-demo' ),
				array( 'status' => 400 )
			);
		}

		$new_message = $params;

		// Note: Provider metadata handling removed as MessagePartChannelEnum 
		// doesn't have a 'metadata' channel type (only 'content' and 'thought').

		$messages[] = $new_message;

		$message_instances = $this->prepare_message_instances( $messages );

		try {
			$agent = new Chatbot_Agent( $this->provider_manager, $message_instances );
			do {
				$agent_result = $agent->step();
			} while ( ! $agent_result->finished() );

			$result_message = array_merge(
				array( 'type' => 'regular' ),
				$agent_result->last_message()->toArray()
			);
		} catch ( Exception $e ) {
			$error_message  = __( 'An error occurred while processing your request.', 'wp-ai-sdk-chatbot-demo' );
			$result_message = array(
				'type'                    => 'error',
				Message::KEY_ROLE         => MessageRoleEnum::model()->value,
				Message::KEY_PARTS        => array(
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
	 * @return WP_REST_Response The response object.
	 */
	public function handle_delete_request(): WP_REST_Response {
		delete_user_option( get_current_user_id(), 'wpaisdk_chatbot_messages' );
		return rest_ensure_response( array() );
	}

	/**
	 * Returns the message schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The message schema.
	 */
	protected function get_message_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				Message::KEY_ROLE  => array(
					'type' => 'string',
					'enum' => array(
						MessageRoleEnum::user()->value,
						MessageRoleEnum::model()->value,
						MessageRoleEnum::system()->value,
					),
				),
				Message::KEY_PARTS => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							MessagePart::KEY_CHANNEL => array(
								'type' => 'string',
								'enum' => array(
									MessagePartChannelEnum::content()->value,
									MessagePartChannelEnum::metadata()->value,
								),
							),
							MessagePart::KEY_TYPE    => array(
								'type' => 'string',
								'enum' => array(
									MessagePartTypeEnum::text()->value,
									MessagePartTypeEnum::functionCall()->value,
									MessagePartTypeEnum::functionResponse()->value,
								),
							),
							MessagePart::KEY_TEXT    => array(
								'type' => 'string',
							),
							MessagePart::KEY_DATA    => array(
								'type' => 'object',
							),
						),
					),
				),
			),
		);
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
			function ( $message ) {
				// The 'type' property is not part of the original message schema, so we unset it here.
				unset( $message['type'] );
				
				return Message::fromArray( $message );
			},
			$messages
		);
	}
}