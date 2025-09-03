<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Prompt_Manager
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo;

/**
 * Class for managing prompt templates with placeholder support.
 *
 * @since 0.1.0
 */
class Prompt_Manager {

	/**
	 * The path to the prompts directory.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $prompts_directory;

	/**
	 * Cache for loaded prompts.
	 *
	 * @since 0.1.0
	 * @var array<string, string>
	 */
	private array $prompt_cache = array();

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prompts_directory The path to the prompts directory.
	 */
	public function __construct( string $prompts_directory ) {
		$this->prompts_directory = trailingslashit( $prompts_directory );
	}

	/**
	 * Loads a prompt template from a file and processes placeholders.
	 *
	 * @since 0.1.0
	 *
	 * @param string               $prompt_name The name of the prompt file (without extension).
	 * @param array<string, mixed> $context     Optional context data for placeholder replacement.
	 * @return string The processed prompt content.
	 */
	public function get_prompt( string $prompt_name, array $context = array() ): string {
		$content = $this->load_prompt_file( $prompt_name );
		
		if ( '' === $content ) {
			return $this->get_default_prompt();
		}

		$placeholders = $this->get_placeholder_values( $context );
		return $this->replace_placeholders( $content, $placeholders );
	}

	/**
	 * Loads a prompt file from disk.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prompt_name The name of the prompt file (without extension).
	 * @return string The raw prompt content.
	 */
	private function load_prompt_file( string $prompt_name ): string {
		if ( isset( $this->prompt_cache[ $prompt_name ] ) ) {
			return $this->prompt_cache[ $prompt_name ];
		}

		$file_path = $this->prompts_directory . $prompt_name . '.md';
		
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return '';
		}

		$this->prompt_cache[ $prompt_name ] = $content;
		return $content;
	}

	/**
	 * Gets all placeholder values from WordPress and context.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $context Optional context data.
	 * @return array<string, string> The placeholder values.
	 */
	private function get_placeholder_values( array $context = array() ): array {
		$current_user = wp_get_current_user();
		$timezone     = wp_timezone();
		
		$placeholders = array(
			// Site information
			'{{site.name}}'           => get_bloginfo( 'name' ),
			'{{site.url}}'            => get_site_url(),
			'{{site.description}}'    => get_bloginfo( 'description' ),
			'{{site.admin_url}}'      => admin_url(),
			'{{site.ai_settings_url}}' => admin_url( 'options-general.php?page=ai' ),
			'{{site.timezone}}'       => $timezone->getName(),
			
			// WordPress information
			'{{wp.version}}'          => get_bloginfo( 'version' ),
			'{{wp.language}}'         => get_locale(),
			
			// User information
			'{{user.display_name}}'   => $current_user->display_name,
			'{{user.email}}'          => $current_user->user_email,
			'{{user.role}}'           => $this->get_user_role_name( $current_user ),
			'{{user.id}}'             => (string) $current_user->ID,
			
			// Date and time
			'{{date.today}}'          => wp_date( 'l, F j, Y' ),
			'{{date.time}}'           => wp_date( 'g:i A' ),
			'{{date.datetime}}'       => wp_date( 'Y-m-d H:i:s' ),
			'{{date.year}}'           => wp_date( 'Y' ),
			'{{date.month}}'          => wp_date( 'F' ),
			'{{date.day}}'            => wp_date( 'j' ),
		);

		// Add custom context values
		if ( isset( $context['custom'] ) && is_array( $context['custom'] ) ) {
			foreach ( $context['custom'] as $key => $value ) {
				$placeholders[ '{{custom.' . $key . '}}' ] = (string) $value;
			}
		}

		// Add ability to extend placeholders via filter
		$placeholders = apply_filters( 'wp_ai_chatbot_prompt_placeholders', $placeholders, $context );

		return $placeholders;
	}

	/**
	 * Replaces placeholders in the content.
	 *
	 * @since 0.1.0
	 *
	 * @param string                $content      The content with placeholders.
	 * @param array<string, string> $placeholders The placeholder values.
	 * @return string The processed content.
	 */
	private function replace_placeholders( string $content, array $placeholders ): string {
		// Replace all known placeholders
		$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
		
		// Remove any remaining undefined placeholders (replace with empty string)
		$content = preg_replace( '/\{\{[^}]+\}\}/', '', $content );
		
		return $content;
	}

	/**
	 * Gets the user's primary role name.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_User $user The user object.
	 * @return string The role name.
	 */
	private function get_user_role_name( \WP_User $user ): string {
		if ( empty( $user->roles ) ) {
			return 'None';
		}

		$role = reset( $user->roles );
		$wp_roles = wp_roles();
		
		if ( isset( $wp_roles->role_names[ $role ] ) ) {
			return translate_user_role( $wp_roles->role_names[ $role ] );
		}

		return $role;
	}

	/**
	 * Gets the default prompt if no file is found.
	 *
	 * @since 0.1.0
	 *
	 * @return string The default prompt.
	 */
	private function get_default_prompt(): string {
		return 'You are a knowledgeable WordPress assistant designed to help users manage their WordPress sites.

Your primary role is to provide helpful, friendly, and expert assistance with WordPress tasks. You should:

1. Be conversational and approachable while maintaining professionalism.
2. Provide clear, concise explanations that are easy to understand.
3. Use the available tools/abilities to perform tasks when requested.
4. Ask clarifying questions when needed to better assist the user.
5. Explain what you\'re doing when using tools, so users understand the process.
6. Offer relevant suggestions and best practices when appropriate.

You have access to various WordPress-specific abilities that allow you to:
- Search for and retrieve posts
- Create and publish content
- Generate featured images
- Configure site settings
- And more

Always aim to be helpful and informative while respecting the user\'s time and needs.

When users ask about your capabilities, you can use the list-capabilities function to show them what you can do.';
	}

}