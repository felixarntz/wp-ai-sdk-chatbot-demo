<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Managers\Ability_Manager
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Managers;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Adapters\Abilities_Tool_Adapter;
use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Tools\Contracts\Tool;
use WP_Ability;

/**
 * Manages abilities for the chatbot and converts them to tools.
 *
 * This manager handles the discovery of chatbot-specific abilities and their
 * conversion to Tool interface objects that can be used by the PHP AI Client SDK.
 *
 * @since 0.1.0
 */
class Ability_Manager {

	/**
	 * The namespace prefix for chatbot abilities.
	 *
	 * @since 0.1.0
	 */
	private const CHATBOT_ABILITY_PREFIX = 'wp-ai-sdk-chatbot-demo/';

	/**
	 * Gets all chatbot tools converted from abilities.
	 *
	 * This method discovers all abilities registered with the chatbot namespace
	 * and converts them to Tool interface objects using the adapter pattern.
	 *
	 * @since 0.1.0
	 *
	 * @return array<Tool> Array of Tool interface objects.
	 */
	public function get_chatbot_tools(): array {
		$abilities = $this->get_chatbot_abilities();
		$tools = [];

		foreach ( $abilities as $ability ) {
			$tools[] = new Abilities_Tool_Adapter( $ability );
		}

		return $tools;
	}

	/**
	 * Gets all abilities registered for the chatbot.
	 *
	 * This method filters the global abilities registry to only return
	 * abilities that belong to the chatbot demo namespace.
	 *
	 * @since 0.1.0
	 *
	 * @return array<WP_Ability> Array of WP_Ability objects for the chatbot.
	 */
	public function get_chatbot_abilities(): array {
		// Check if abilities API functions are available
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$all_abilities = wp_get_abilities();
		$chatbot_abilities = [];

		foreach ( $all_abilities as $ability ) {
			if ( $this->is_chatbot_ability( $ability ) ) {
				$chatbot_abilities[] = $ability;
			}
		}

		return $chatbot_abilities;
	}

	/**
	 * Checks if an ability belongs to the chatbot.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Ability $ability The ability to check.
	 * @return bool True if the ability belongs to the chatbot, false otherwise.
	 */
	private function is_chatbot_ability( WP_Ability $ability ): bool {
		return strpos( $ability->get_name(), self::CHATBOT_ABILITY_PREFIX ) === 0;
	}

	/**
	 * Gets a specific chatbot ability by its short name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $short_name The short name of the ability (without namespace prefix).
	 * @return WP_Ability|null The ability if found, null otherwise.
	 */
	public function get_chatbot_ability_by_name( string $short_name ): ?WP_Ability {
		$full_name = self::CHATBOT_ABILITY_PREFIX . $short_name;
		
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return null;
		}

		return wp_get_ability( $full_name );
	}

	/**
	 * Gets available ability names for debugging or administration.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string> Array of ability names.
	 */
	public function get_available_ability_names(): array {
		$abilities = $this->get_chatbot_abilities();
		
		return array_map(
			static function ( WP_Ability $ability ): string {
				return $ability->get_name();
			},
			$abilities
		);
	}

	/**
	 * Gets the count of available chatbot abilities.
	 *
	 * @since 0.1.0
	 *
	 * @return int The number of available chatbot abilities.
	 */
	public function get_ability_count(): int {
		return count( $this->get_chatbot_abilities() );
	}

	/**
	 * Checks if a specific chatbot ability is available.
	 *
	 * @since 0.1.0
	 *
	 * @param string $short_name The short name of the ability (without namespace prefix).
	 * @return bool True if the ability is available, false otherwise.
	 */
	public function has_ability( string $short_name ): bool {
		return $this->get_chatbot_ability_by_name( $short_name ) !== null;
	}

	/**
	 * Gets tools organized by category for administration purposes.
	 *
	 * This method can be used to organize tools in admin interfaces
	 * or for more advanced tool management.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<Tool>> Tools organized by category.
	 */
	public function get_tools_by_category(): array {
		$tools = $this->get_chatbot_tools();
		$categorized = [
			'content' => [],
			'management' => [],
			'system' => [],
		];

		foreach ( $tools as $tool ) {
			$tool_name = $tool->get_name();
			
			// Categorize based on tool name patterns
			if ( $this->is_content_tool( $tool_name ) ) {
				$categorized['content'][] = $tool;
			} elseif ( $this->is_management_tool( $tool_name ) ) {
				$categorized['management'][] = $tool;
			} else {
				$categorized['system'][] = $tool;
			}
		}

		return $categorized;
	}

	/**
	 * Checks if a tool is content-related.
	 *
	 * @since 0.1.0
	 *
	 * @param string $tool_name The name of the tool.
	 * @return bool True if content-related, false otherwise.
	 */
	private function is_content_tool( string $tool_name ): bool {
		$content_patterns = [ 'post', 'search', 'content', 'image' ];
		
		foreach ( $content_patterns as $pattern ) {
			if ( strpos( $tool_name, $pattern ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Checks if a tool is management-related.
	 *
	 * @since 0.1.0
	 *
	 * @param string $tool_name The name of the tool.
	 * @return bool True if management-related, false otherwise.
	 */
	private function is_management_tool( string $tool_name ): bool {
		$management_patterns = [ 'provider', 'settings', 'permalink', 'config' ];
		
		foreach ( $management_patterns as $pattern ) {
			if ( strpos( $tool_name, $pattern ) !== false ) {
				return true;
			}
		}
		
		return false;
	}
}