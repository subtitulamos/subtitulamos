const path = require('path');
const ManifestPlugin = require('webpack-manifest-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const webpack = require('webpack');

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
        user_settings: './resources/assets/js/user_settings.js',
        user_public_profile: './resources/assets/js/user_public_profile.js',
        vendor: ['jquery', 'vue', 'timeago.js']
    },
    output: {
        filename: 'js/[name].[chunkhash].bundle.js',
        path: path.resolve(__dirname, 'public')
    },
    plugins: [
        new CleanWebpackPlugin(['public/js']),
        new ManifestPlugin({
            fileName: '../resources/assets/manifest.json'
        }),
        new webpack.optimize.CommonsChunkPlugin({
            name: "vendor",
            filename: "js/vendor.[chunkhash].js",
            minChunks: Infinity,
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
    },
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js' // TODO: Fully migrate to Vue files
        }
    }
};