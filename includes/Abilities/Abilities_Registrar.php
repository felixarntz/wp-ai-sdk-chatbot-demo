<?php
/**
 * Class Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities\Abilities_Registrar
 *
 * @since 0.1.0
 * @package wp-ai-sdk-chatbot-demo
 */

namespace Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Abilities;

use function Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies\wp_register_ability;

/**
 * Registers all abilities for the plugin.
 *
 * @since 0.1.0
 */
class Abilities_Registrar {

	/**
	 * Registers all abilities.
	 *
	 * @since 0.1.0
	 */
	public function register_abilities(): void {
		/*
		 * Below, mock descriptions and execute callbacks are provided just so that the API does not fail.
		 * The actual descriptions are provided by each class internally.
		 * TODO: Fix this upstream so that the Abilities API works better with object oriented usage, then remove.
		 */

		$mock_execute_callback = function () {
			return null;
		};

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/get-post',
			array(
				'label'            => __( 'Get Post', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Get_Post_Ability::class,
			)
		);

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/create-post-draft',
			array(
				'label'            => __( 'Create Post Draft', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Create_Post_Draft_Ability::class,
			)
		);

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/generate-post-featured-image',
			array(
				'label'            => __( 'Generate Post Featured Image', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Generate_Post_Featured_Image_Ability::class,
			)
		);

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/publish-post',
			array(
				'label'            => __( 'Publish Post', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Publish_Post_Ability::class,
			)
		);

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/search-posts',
			array(
				'label'            => __( 'Search Posts', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Search_Posts_Ability::class,
			)
		);

		wp_register_ability(
			'wp-ai-sdk-chatbot-demo/set-permalink-structure',
			array(
				'label'            => __( 'Set Permalink Structure', 'wp-ai-sdk-chatbot-demo' ),
				'description'      => 'Mock description.',
				'execute_callback' => $mock_execute_callback,
				'ability_class'    => Set_Permalink_Structure_Ability::class,
			)
		);
	}
}
