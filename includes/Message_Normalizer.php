<?php
/**
 * Message Normalizer for consistent internal format
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Class for normalizing messages to/from a canonical internal format.
 * 
 * This ensures that:
 * 1. Messages stored in the database are provider-agnostic
 * 2. Empty function arguments are consistently objects
 * 3. Provider-specific formats never leak into storage
 * 4. Messages can be reliably converted for any provider
 *
 * @since 0.1.0
 */
class Message_Normalizer {
	
	/**
	 * Coerces "empty" structures used as function args to an empty object (stdClass),
	 * so JSON encodes as {} instead of [].
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private static function coerce_empty_to_object( $value ) {
		// Null → {}
		if ( null === $value ) {
			return (object) array();
		}

		// Stringified JSON → decode (best-effort)
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$value = $decoded;
			} else {
				// It's just a string, leave it as-is
				return $value;
			}
		}

		// Empty array → {}
		if ( is_array( $value ) && 0 === count( $value ) ) {
			return (object) array();
		}

		return $value;
	}

	/**
	 * Best-effort JSON decode for message-part payloads; returns original on failure.
	 *
	 * @param mixed $maybe_json
	 * @return mixed
	 */
	private static function try_json_decode( $maybe_json ) {
		if ( is_string( $maybe_json ) ) {
			$tmp = json_decode( $maybe_json, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $tmp;
			}
		}
		return $maybe_json;
	}
	
	/**
	 * Normalizes a message array for storage.
	 * Removes all provider-specific formats and ensures consistency.
	 *
	 * @since 0.1.0
	 *
	 * @param array $message Raw message array from any source.
	 * @return array Normalized message safe for storage.
	 */
	public static function normalize_for_storage( array $message ): array {
		// Ensure we have the basic structure
		if ( ! isset( $message['role'] ) ) {
			return $message;
		}
		
		// Clean the parts array
		if ( isset( $message['parts'] ) && is_array( $message['parts'] ) ) {
			$message['parts'] = self::normalize_message_parts( $message['parts'] );
		}
		
		// Remove any top-level provider-specific fields
		unset( $message['tool_calls'] );
		unset( $message['content'] );
		unset( $message['tool_use'] );
		
		return $message;
	}
	
	/**
	 * Normalizes message parts array.
	 *
	 * @since 0.1.0
	 *
	 * @param array $parts Array of message parts.
	 * @return array Normalized parts array.
	 */
	private static function normalize_message_parts( array $parts ): array {
		$normalized = array();
		
		foreach ( $parts as $part ) {
			// Skip invalid parts
			if ( ! is_array( $part ) ) {
				continue;
			}
			
			// Handle different part types
			if ( isset( $part['type'] ) ) {
				switch ( $part['type'] ) {
					case 'text':
						$normalized[] = self::normalize_text_part( $part );
						break;
						
					case 'function_call':
						$normalized[] = self::normalize_function_call_part( $part );
						break;
						
					case 'function_response':
						$normalized[] = self::normalize_function_response_part( $part );
						break;
						
					default:
						// Keep unknown types but clean them
						$normalized[] = self::clean_part( $part );
						break;
				}
			} else {
				// Try to detect type from structure
				if ( isset( $part['function_call'] ) || isset( $part['tool_use'] ) ) {
					$normalized[] = self::normalize_function_call_part( $part );
				} elseif ( isset( $part['function_response'] ) || isset( $part['tool_result'] ) ) {
					$normalized[] = self::normalize_function_response_part( $part );
				} elseif ( isset( $part['text'] ) ) {
					$normalized[] = self::normalize_text_part( $part );
				}
			}
		}
		
		return $normalized;
	}
	
	/**
	 * Normalizes a text message part.
	 *
	 * @since 0.1.0
	 *
	 * @param array $part Text message part.
	 * @return array Normalized text part.
	 */
	private static function normalize_text_part( array $part ): array {
		return array(
			'channel' => $part['channel'] ?? 'content',
			'type'    => 'text',
			'text'    => $part['text'] ?? '',
		);
	}
	
	/**
	 * Normalizes a function call message part.
	 *
	 * @since 0.1.0
	 *
	 * @param array $part Function call message part.
	 * @return array Normalized function call part.
	 */
	private static function normalize_function_call_part( array $part ): array {
		// Case A: Already normalized shape: { type: 'function_call', function_call: { name, args, id? } }
		if ( isset( $part['type'] ) && 'function_call' === $part['type'] && isset( $part['function_call'] ) && is_array( $part['function_call'] ) ) {
			$fc = $part['function_call'];
			$fc['args'] = self::coerce_empty_to_object( $fc['args'] ?? array() );
			$part['function_call'] = $fc;
			return $part;
		}

		// Case B: Anthropic "tool_use" part
		//   { type: 'tool_use', name: 'list-capabilities', input: [], id: '...' }
		if ( isset( $part['type'] ) && 'tool_use' === $part['type'] ) {
			return array(
				'type'           => 'function_call',
				// channel is provider-agnostic for storage; omit provider specifics
				'function_call'  => array(
					'id'   => $part['id']   ?? null,
					'name' => $part['name'] ?? null,
					'args' => self::coerce_empty_to_object( $part['input'] ?? array() ),
				),
			);
		}

		// Case C: OpenAI-style nested tool call part (as sometimes seen when pre-parsed)
		//   { id, function: { name, arguments: "{}" } }
		if ( isset( $part['function'] ) && is_array( $part['function'] ) && isset( $part['function']['name'] ) ) {
			$args = $part['function']['arguments'] ?? array();
			$args = self::coerce_empty_to_object( self::try_json_decode( $args ) );
			return array(
				'type'           => 'function_call',
				'function_call'  => array(
					'id'   => $part['id'] ?? null,
					'name' => $part['function']['name'],
					'args' => $args,
				),
			);
		}

		// Case D: Legacy or variant: { function_call: { name, arguments|args } } (no explicit type)
		if ( isset( $part['function_call'] ) && is_array( $part['function_call'] ) ) {
			$fc = $part['function_call'];
			$args = $fc['args'] ?? $fc['arguments'] ?? array();
			$fc = array(
				'id'   => $fc['id']   ?? null,
				'name' => $fc['name'] ?? null,
				'args' => self::coerce_empty_to_object( self::try_json_decode( $args ) ),
			);
			return array(
				'type'          => 'function_call',
				'function_call' => $fc,
			);
		}

		// Last resort: return as-is – higher-level cleaner will strip provider fields
		return $part;
	}
	
	/**
	 * Normalizes a function response message part.
	 *
	 * @since 0.1.0
	 *
	 * @param array $part Function response message part.
	 * @return array Normalized function response part.
	 */
	private static function normalize_function_response_part( array $part ): array {
		// Case A: Already normalized: { type: 'function_response', function_response: { name, response, id? } }
		if ( isset( $part['type'] ) && 'function_response' === $part['type'] && isset( $part['function_response'] ) && is_array( $part['function_response'] ) ) {
			$fr = $part['function_response'];
			$payload = $fr['response'] ?? $fr['output'] ?? null;
			$payload = self::try_json_decode( $payload );
			// For responses, empty array/object are both fine; do not force empty object unless it's strictly empty array.
			if ( is_array( $payload ) && 0 === count( $payload ) ) {
				$payload = (object) array();
			}
			$fr['response'] = $payload;
			unset( $fr['output'] );
			$part['function_response'] = $fr;
			return $part;
		}

		// Case B: Anthropic tool_result: { type: 'tool_result', tool_use_id, content|output }
		if ( isset( $part['type'] ) && 'tool_result' === $part['type'] ) {
			$content = $part['content'] ?? $part['output'] ?? null;
			$content = self::try_json_decode( $content );
			if ( is_array( $content ) && 0 === count( $content ) ) {
				$content = (object) array();
			}
			return array(
				'type'               => 'function_response',
				'function_response'  => array(
					'id'       => $part['tool_use_id'] ?? null,
					'name'     => $part['name']        ?? null, // may or may not be present
					'response' => $content,
				),
			);
		}

		// Case C: Variant: { function_response: { name, response|output } } (no explicit type)
		if ( isset( $part['function_response'] ) && is_array( $part['function_response'] ) ) {
			$fr = $part['function_response'];
			$payload = $fr['response'] ?? $fr['output'] ?? null;
			$payload = self::try_json_decode( $payload );
			if ( is_array( $payload ) && 0 === count( $payload ) ) {
				$payload = (object) array();
			}
			return array(
				'type'               => 'function_response',
				'function_response'  => array(
					'id'       => $fr['id']   ?? null,
					'name'     => $fr['name'] ?? null,
					'response' => $payload,
				),
			);
		}

		return $part;
	}
	
	/**
	 * Cleans a message part by removing provider-specific fields.
	 *
	 * @since 0.1.0
	 *
	 * @param array $part Message part to clean.
	 * @return array Cleaned message part.
	 */
	private static function clean_part( array $part ): array {
		// Remove provider-specific fields commonly seen in OpenAI/Anthropic shapes
		unset( $part['tool_calls'], $part['function'], $part['tool_use'], $part['tool_result'] );
		unset( $part['content'] ); // When it's nested Anthropic format

		// If something left looks like an empty arguments list, coerce to {}
		if ( isset( $part['function_call']['args'] ) ) {
			$part['function_call']['args'] = self::coerce_empty_to_object( $part['function_call']['args'] );
		}
		return $part;
	}
	
	/**
	 * Prepares messages for sending to AI provider.
	 * Ensures compatibility with provider expectations.
	 *
	 * @since 0.1.0
	 *
	 * @param array $messages Array of normalized messages.
	 * @param string $provider Provider identifier (openai, anthropic, google).
	 * @return array Messages prepared for the specific provider.
	 */
	public static function prepare_for_provider( array $messages, string $provider ): array {
		// For now, we rely on the PHP AI Client SDK to handle provider-specific conversion
		// But we ensure our normalized format is clean
		return array_map( array( __CLASS__, 'normalize_for_storage' ), $messages );
	}
	
	/**
	 * Validates and fixes a message structure.
	 * Ensures it conforms to the expected format.
	 *
	 * @since 0.1.0
	 *
	 * @param array $message Message to validate.
	 * @return array Valid message structure.
	 */
	public static function validate_message( array $message ): array {
		// Ensure required fields
		if ( ! isset( $message['role'] ) ) {
			$message['role'] = 'user';
		}
		
		if ( ! isset( $message['parts'] ) || ! is_array( $message['parts'] ) ) {
			$message['parts'] = array();
		}
		
		// Normalize the message
		return self::normalize_for_storage( $message );
	}
	
	/**
	 * Batch normalizes an array of messages.
	 *
	 * @since 0.1.0
	 *
	 * @param array $messages Array of messages to normalize.
	 * @return array Array of normalized messages.
	 */
	public static function normalize_all( array $messages ): array {
		return array_map( array( __CLASS__, 'normalize_for_storage' ), $messages );
	}
}