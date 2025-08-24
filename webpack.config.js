/**
 * WordPress dependencies
 */
const config = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Gets the entry points for the webpack configuration.
 *
 * This function extends the original entry points from the relevant '@wordpress/scripts' function with any index files
 * in one level deep directories in src.
 *
 * @return {Function} A function returning the entry points object.
 */
function getEntryPoints() {
	return () => {
		return {
			index: {
				import: './src/index.tsx',
				library: {
					type: 'window',
					name: [ 'wpAiSdkChatbotDemo' ],
				},
			},
		};
	};
}

module.exports = {
	...config,
	output: {
		...config.output,
		enabledLibraryTypes: [ 'window' ],
	},
	entry: getEntryPoints(),
};
