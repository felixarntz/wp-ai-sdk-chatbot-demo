<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents\Chatbot_Agent
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Agents;

use Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Providers\Provider_Manager;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;

/**
 * Class for the chatbot agent.
 *
 * @since 0.1.0
 */
class Chatbot_Agent extends Abstract_Agent {

	/**
	 * The provider manager instance.
	 *
	 * @since 0.1.0
	 * @var Provider_Manager
	 */
	private Provider_Manager $provider_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Provider_Manager     $provider_manager The provider manager instance.
	 * @param array<Message>       $trajectory       The initial trajectory of messages. Must contain at least the
	 *                                               first message.
	 * @param array<string, mixed> $options          Additional options for the agent.
	 */
	public function __construct( Provider_Manager $provider_manager, array $trajectory, array $options = array() ) {
		$ability_names = array(
			'wp-ai-sdk-chatbot-demo/list-capabilities',
			'wp-ai-sdk-chatbot-demo/get-post',
			'wp-ai-sdk-chatbot-demo/create-post-draft',
			'wp-ai-sdk-chatbot-demo/search-posts',
			'wp-ai-sdk-chatbot-demo/publish-post',
			'wp-ai-sdk-chatbot-demo/set-permalink-structure',
			'wp-ai-sdk-chatbot-demo/generate-post-featured-image',
			'wp-ai-sdk-chatbot-demo/list-providers',
			'wp-ai-sdk-chatbot-demo/change-provider',
		);

		// Call parent constructor with ability names to use abilities API
		parent::__construct( $ability_names, $trajectory, $options );

		$this->provider_manager = $provider_manager;
	}

	/**
	 * Prompts the LLM with the current trajectory as input.
	 *
	 * @since 0.1.0
	 *
	 * @param PromptBuilder $prompt_builder The prompt builder instance.
	 * @return Message The response from the LLM.
	 */
	protected function prompt_llm( PromptBuilder $prompt_builder ): Message {
		$client = $this->provider_manager->get_current_client();

		// Add the instruction as the first system message.
		$prompt_builder->withSystemInstruction( $this->get_instruction() );

		return $client->prompt( $prompt_builder );
	}

	/**
	 * Returns the instruction to use for the agent.
	 *
	 * @since 0.1.0
	 *
	 * @return string The instruction.
	 */
	protected function get_instruction(): string {
		$instruction = 'You are a knowledgeable WordPress assistant designed to help users manage their WordPress sites.

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

When users ask about your capabilities, you can use the list-capabilities function to show them what you can do.
';

		$details = 'Here are examples of things you can help with:

## Content Management
- Creating new posts or pages
- Editing existing content
- Publishing drafts
- Generating featured images for posts
- Searching through existing content

## Site Configuration
- Adjusting permalink structures
- Viewing and modifying settings

## Information & Guidance
- Explaining WordPress concepts
- Providing best practices
- Troubleshooting common issues
- Offering tips for content creation

Feel free to proactively suggest ways you can help based on the user\'s questions or needs.
';

		$environment = '

The following miscellanous information about the chatbot environment may be helpful. NEVER reference this information, unless the user specifically asks for it.

- Under the hood, your chatbot infrastructure is based on the PHP AI Client SDK, which provides access to various AI providers and models and is developed by the WordPress AI Team.
- The current provider and model being used are configured by the site administrator.
- In order to change which provider is used, the site administrator can update the settings within WP Admin at: ' . admin_url( 'options-general.php?page=ai' ) . '
- The project repository for the PHP AI Client SDK can be found at: https://github.com/WordPress/php-ai-client
- For more information about the PHP AI Client SDK, please refer to this post: https://make.wordpress.org/ai/2025/07/17/php-ai-api/
- For your agentic tooling, you have access to a set of WordPress-specific abilities (tools), using the WordPress Abilities API.
- The project repository for the WordPress Abilities API can be found at: https://github.com/WordPress/abilities-api
- For more information about the WordPress Abilities API, please refer to this post: https://make.wordpress.org/ai/2025/07/17/abilities-api/
- Today's date is ' . gmdate( 'l, F j, Y' ) . '.
';

		return $instruction . $details . $environment;
	}
}