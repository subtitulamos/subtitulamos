var path = require('path');
var ManifestPlugin = require('webpack-manifest-plugin');

module.exports = {
    entry: {
        search: './resources/assets/js/search.js',
        episode: './resources/assets/js/episode.js',
        index: './resources/assets/js/index.js',
        app: './resources/assets/js/app.js',
        hammer: './resources/assets/js/hammer.js',
        rules: './resources/assets/js/rules.js',
        upload: './resources/assets/js/upload.js',
        translate: './resources/assets/js/translate.js',
    },
    output: {
        filename: 'js/[name].[hash].bundle.js',
        path: path.resolve(__dirname, 'public/')
    },
    plugins: [
        new ManifestPlugin({
            fileName: '../resources/assets/manifest.json'
        })
    ],
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader?cacheDirectory=true',
                    options: {
                        presets: ['env']
                    }
                }
            }
        ]
    }
};