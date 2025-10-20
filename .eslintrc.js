module.exports = {
	extends: [ 'plugin:@woocommerce/eslint-plugin/recommended' ],
	globals: {
		jQuery: 'readonly',
		wc_mnm_params: true,
		WC_MNM_ADD_TO_CART_REACT_PARAMS: true,
	},
	rules: {
		'woocommerce/feature-flag': 'off',
		'react-hooks/exhaustive-deps': 'error',
		'react/jsx-fragments': [ 'error', 'syntax' ],
		'@wordpress/no-global-active-element': 'warn',
		'@wordpress/i18n-text-domain': [
			'error',
			{
				allowedTextDomain: [ 'wc-mnm-variable' ],
			},
		],
		'@typescript-eslint/no-restricted-imports': [
			'error',
			{
				paths: [
					{
						name: 'react',
						message:
							'Please use React API through `@wordpress/element` instead.',
						allowTypeImports: true,
					},
				],
			},
		],
		camelcase: [
			'error',
			{
				properties: 'never',
				ignoreGlobals: true,
				// allow: [ 'wc_mnm_params', 'WC_MNM_ADD_TO_CART_REACT_PARAMS' ]
			},
		],
		'react/react-in-jsx-scope': 'off',
		// Turned off because conflicts with the ones above and does not support aliases
		'node/no-missing-require': 'off',
		'node/no-extraneous-import': 'off',
	},

	settings: {
		'import/resolver': {
			webpack: {
				config: 'webpack.config.js',
			},
		},
	},
};
