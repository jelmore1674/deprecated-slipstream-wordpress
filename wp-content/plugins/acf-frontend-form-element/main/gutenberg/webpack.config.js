const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'acf-frontend-form': './src/acf-frontend-form.js'
	},
	output: {
		path: path.join(__dirname, 'assets/js'),
		filename: '[name].js'
	}
}