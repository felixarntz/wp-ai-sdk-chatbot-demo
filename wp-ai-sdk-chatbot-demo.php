<?php
/**
 * Plugin Name: WP AI SDK Chatbot Demo
 * Plugin URI: https://github.com/felixarntz/wp-ai-sdk-chatbot-demo/
 * Description: Implements a basic AI chatbot demo using the PHP AI Client SDK.
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Version: 0.1.0
 * Author: Felix Arntz
 * Author URI: https://felix-arntz.me
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: wp-ai-sdk-chatbot-demo
 *
 * @package wp-ai-sdk-chatbot-demo
 */

// This loader file should remain compatible with PHP 5.2.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WP_AI_SDK_CHATBOT_DEMO_VERSION', '0.1.0' );
define( 'WP_AI_SDK_CHATBOT_DEMO_MINIMUM_PHP', '7.4' );
define( 'WP_AI_SDK_CHATBOT_DEMO_MINIMUM_WP', '6.8' );

/**
 * Checks basic requirements and loads the plugin.
 *
 * @since 0.1.0
 */
function wp_ai_sdk_chatbot_demo_load() /* @phpstan-ignore-line */ {
	static $loaded = false;

	// Check for supported PHP version.
	if (
		version_compare( phpversion(), WP_AI_SDK_CHATBOT_DEMO_MINIMUM_PHP, '<' )
		|| version_compare( get_bloginfo( 'version' ), WP_AI_SDK_CHATBOT_DEMO_MINIMUM_WP, '<' )
	) {
		add_action( 'admin_notices', 'wp_ai_sdk_chatbot_demo_display_version_requirements_notice' );
		return;
	}

	// Register the autoloader.
	if ( ! wp_ai_sdk_chatbot_demo_register_autoloader() ) {
		add_action( 'admin_notices', 'wp_ai_sdk_chatbot_demo_display_composer_autoload_notice' );
		return;
	}

	// Prevent loading the plugin twice.
	if ( $loaded ) {
		return;
	}
	$loaded = true;

	// Load the plugin.
	$class_name = 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Plugin_Main';
	$instance   = new $class_name( __FILE__ );
	$instance->add_hooks();
}

/**
 * Registers the plugin autoloader.
 *
 * @since 0.1.0
 *
 * @return bool True on success, false on failure.
 */
function wp_ai_sdk_chatbot_demo_register_autoloader() {
	static $registered = null;

	// Prevent multiple executions.
	if ( null !== $registered ) {
		return $registered;
	}

	// Check for the built autoloader class map as that needs to be used for a production build.
	$autoload_file             = plugin_dir_path( __FILE__ ) . 'includes/vendor/composer/autoload_classmap.php';
	$third_party_autoload_file = plugin_dir_path( __FILE__ ) . 'third-party/vendor/composer/autoload_classmap.php';
	if ( file_exists( $autoload_file ) && file_exists( $third_party_autoload_file ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin_Autoloader.php';

		$class_name = 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo\Plugin_Autoloader';

		$instance = new $class_name( 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo', $autoload_file );
		spl_autoload_register( array( $instance, 'autoload' ), true, true );

		$third_party_instance = new $class_name( 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies', $third_party_autoload_file );
		spl_autoload_register( array( $third_party_instance, 'autoload' ), true, true );

		// Manually load the WordPress Abilities API.
		require_once plugin_dir_path( __FILE__ ) . 'third-party/wordpress/abilities-api/includes/bootstrap.php';

		$registered = true;
		return true;
	}

	// Otherwise, the autoloader is missing.
	$registered = false;
	return false;
}

/**
 * Displays admin notice about unmet PHP version requirement.
 *
 * @since 0.1.0
 */
function wp_ai_sdk_chatbot_demo_display_version_requirements_notice() /* @phpstan-ignore-line */ {
	echo '<div class="notice notice-error"><p>';
	echo esc_html(
		sprintf(
			/* translators: 1: required PHP version, 2: required WP version, 3: current PHP version, 4: current WP version */
			__( 'WP AI SDK Chatbot Demo requires at least PHP version %1$s and WordPress version %2$s. Your site is currently using PHP %3$s and WordPress %4$s.', 'wp-ai-sdk-chatbot-demo' ),
			WP_AI_SDK_CHATBOT_DEMO_MINIMUM_PHP,
			phpversion(),
			WP_AI_SDK_CHATBOT_DEMO_MINIMUM_WP,
			get_bloginfo( 'version' )
		)
	);
	echo '</p></div>';
}

/**
 * Displays admin notice about missing Composer autoload files.
 *
 * @since 0.1.0
 */
function wp_ai_sdk_chatbot_demo_display_composer_autoload_notice() /* @phpstan-ignore-line */ {
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: composer install command */
		esc_html__( 'Your installation of WP AI SDK Chatbot Demo is incomplete. Please run %s.', 'wp-ai-sdk-chatbot-demo' ),
		'<code>composer install</code>'
	);
	echo '</p></div>';
}

wp_ai_sdk_chatbot_demo_load();
