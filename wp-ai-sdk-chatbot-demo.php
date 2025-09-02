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

	// Check for Jetpack Autoloader.
	$jetpack_autoloader = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	if ( file_exists( $jetpack_autoloader ) ) {
		require_once $jetpack_autoloader;
		
		// Initialize Jetpack Autoloader
		if ( class_exists( 'Automattic\Jetpack\Autoloader\jpAutoload' ) ) {
			\Automattic\Jetpack\Autoloader\jpAutoload::init();
		}

		// Only load abilities API if constant not already defined
		// (avoids conflict with MCP Adapter plugin)
		if ( ! defined( 'WP_ABILITIES_API_DIR' ) ) {
			$abilities_bootstrap = plugin_dir_path( __FILE__ ) . 'vendor/wordpress/abilities-api/includes/bootstrap.php';
			if ( file_exists( $abilities_bootstrap ) ) {
				require_once $abilities_bootstrap;
			}
		}

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
