const path = require('path');
const ManifestPlugin = require('webpack-manifest-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    return {
        entry: {
            search: './resources/assets/js/search.js',
            episode: './resources/assets/js/episode.js',
            index: './resources/assets/js/index.js',
            app: './resources/assets/js/app.js',
            hammer: './resources/assets/js/hammer.js',
            rules: './resources/assets/js/rules.js',
            upload: './resources/assets/js/upload.js',
            upload_resync: './resources/assets/js/upload_resync.js',
            translate: './resources/assets/js/translate.js',
            user_settings: './resources/assets/js/user_settings.js',
            comment_list: './resources/assets/js/comment_list.js',
            user_profile: './resources/assets/js/user_profile.js',
            panel: './resources/assets/js/panel/adminlte.js',
            panel_alerts: './resources/assets/js/panel/alerts.js',
            // Vendor JS package
            vendor: ['vue', 'timeago.js'],

            // These pages don't have JS, but need to exist to create the corresponding CSS files
            banned_error: './resources/assets/js/banned_error.js',
            edit_episode: './resources/assets/js/edit_episode.js',
            shows_list: './resources/assets/js/shows_list.js',
            show_seasons: './resources/assets/js/show_seasons.js',
            restricted: './resources/assets/js/restricted.js',
            panel_banlist: './resources/assets/js/panel/banlist.js',
            panel_base: './resources/assets/js/panel/base.js',
            edit_show: './resources/assets/js/edit_show.js',

        },
        output: {
            filename: 'js/[name].[contenthash].bundle.js',
            path: path.resolve(__dirname, 'public')
        },
        plugins: [
            new CleanWebpackPlugin(['public/js', 'public/css', 'public/img']),
            new MiniCssExtractPlugin({
                filename: !argv.watch ? 'css/[name].[contenthash].css' : 'css/[name].css',
            }),
            new ManifestPlugin({
                fileName: '../resources/assets/manifest.json',
                filter: (FileDescriptor) => {
                    // Fix for file-loader imported assets (https://github.com/danethurber/webpack-manifest-plugin/issues/208#issuecomment-589080673)
                    // ...otherwise this maps CSS files wrong
                    return FileDescriptor.isChunk;
                }
            }),
        ],
        optimization: {
            splitChunks: {
                cacheGroups: {
                    vendor: {
                        chunks: "initial",
                        test: "vendor",
                        name: "vendor",
                        enforce: true
                    }
                }
            }
        },
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
                },
                {
                    test: /\.s?css$/,
                    exclude: /node_modules/,
                    use: [
                        {
                            loader: MiniCssExtractPlugin.loader,
                            options: {
                                hmr: argv.watch,
                            },
                        },
                        'css-loader',
                        'sass-loader'
                    ],
                },
                {
                    test: /\.(png|svg|jpg|gif)$/,
                    use: [
                        'file-loader?name=/img/[hash].[ext]',
                    ],
                },
            ]
        },
        resolve: {
            alias: {
                'vue$': 'vue/dist/vue.esm.js' // TODO: Fully migrate to Vue files
            }
        },
        watchOptions: {
            ignored: /node_modules/,
        }
    }
};
