<?php
/**
 * PHP-Scoper configuration file.
 *
 * @package wp-ai-sdk-chatbot-demo
 */

use Symfony\Component\Finder\Finder;

$dependencies_regex = '/^(wordpress|guzzlehttp|php-http|psr)\/[a-z0-9-]+\/(includes|src)\//';

return array(
	'prefix'        => 'Felix_Arntz\WP_AI_SDK_Chatbot_Demo_Dependencies',
	'finders'       => array(
		Finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/LICENSE|.*\\.md|.*\\.json|.*\\.lock|.*\\.dist/' )
			->exclude( array( 'docs', 'tests' ) )
			->path( $dependencies_regex )
			->in( 'vendor' ),
	),
	'exclude-files' => array( 'polyfills.php' ),
);
