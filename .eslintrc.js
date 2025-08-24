/**
 * WordPress dependencies
 */
const config = require( '@wordpress/scripts/config/.eslintrc' );

module.exports = {
	...config,
	extends: [
		...( config.extends || [] ),
		'plugin:@wordpress/eslint-plugin/i18n',
	],
	overrides: [
		...( config.overrides || [] ),
		{
			files: [ '**/*.ts', '**/*.tsx' ],
			extends: [
				...( config.extends || [] ),
				'plugin:@wordpress/eslint-plugin/i18n',
				'plugin:@typescript-eslint/recommended',
			],
			plugins: [ ...( config.plugins || [] ), '@typescript-eslint' ],
			parser: '@typescript-eslint/parser',
			parserOptions: {
				tsconfigRootDir: __dirname,
			},
			settings: {
				...( config.settings || {} ),
				jsdoc: {
					mode: 'typescript',
					// TSDoc expects `@returns` and `@yields`.
					tagNamePreference: {
						returns: 'returns',
						yields: 'yields',
					},
				},
			},
		},
	],
};
