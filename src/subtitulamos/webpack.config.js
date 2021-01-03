const path = require("path");
const ManifestPlugin = require("webpack-manifest-plugin");
const CleanWebpackPlugin = require("clean-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyWebpackPlugin = require("copy-webpack-plugin");

module.exports = (env, argv) => {
  return {
    entry: {
      search: "./resources/assets/js/search.js",
      episode: "./resources/assets/js/episode.js",
      index: "./resources/assets/js/index.js",
      overview: "./resources/assets/js/overview.js",
      app: "./resources/assets/js/app.js",
      hammer: "./resources/assets/js/hammer.js",
      rules: "./resources/assets/js/rules.js",
      upload: "./resources/assets/js/upload.js",
      translate: "./resources/assets/js/translate.js",
      user: "./resources/assets/js/user.js",
      panel: "./resources/assets/js/panel/adminlte.js",
      panel_alerts: "./resources/assets/js/panel/alerts.js",
      overview: "./resources/assets/js/overview.js",
      shows_list: "./resources/assets/js/shows_list.js",

      // Vendor JS package
      vendor: ["vue", "timeago.js"],

      // These pages don't have JS, but need to exist to create the corresponding CSS files
      restricted: "./resources/assets/js/restricted.js",
      panel_base: "./resources/assets/js/panel/base.js",
      generic_error: "./resources/assets/js/generic_error.js",
      disclaimer: "./resources/assets/js/disclaimer.js",
    },
    output: {
      filename: "js/[name].[contenthash].bundle.js",
      path: path.resolve(__dirname, "public"),
    },
    plugins: [
      new CleanWebpackPlugin(["public/js", "public/css", "public/img"]),
      new MiniCssExtractPlugin({
        filename: !argv.watch ? "css/[name].[contenthash].css" : "css/[name].css",
      }),
      new ManifestPlugin({
        fileName: "../resources/assets/manifest.json",
        filter: (FileDescriptor) => {
          // Fix for file-loader imported assets (https://github.com/danethurber/webpack-manifest-plugin/issues/208#issuecomment-589080673)
          // ...otherwise this maps CSS files wrong
          return FileDescriptor.isChunk;
        },
      }),
      new CopyWebpackPlugin({
        patterns: [
          {
            from: "resources/static",
            filter: async (resourcePath) => {
              return resourcePath.includes(".gitignore") === false;
            },
          },
        ],
      }),
    ],
    optimization: {
      splitChunks: {
        cacheGroups: {
          vendor: {
            chunks: "initial",
            test: "vendor",
            name: "vendor",
            enforce: true,
          },
        },
      },
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: "babel-loader?cacheDirectory=true",
            options: {
              presets: ["env"],
            },
          },
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
            "css-loader",
            "sass-loader",
          ],
        },
        {
          test: /\.(png|svg|jpg|gif)$/,
          use: ["file-loader?name=/img/[hash].[ext]"],
        },
      ],
    },
    resolve: {
      alias: {
        vue$: "vue/dist/vue.esm.js", // TODO: Fully migrate to Vue files
      },
    },
    watchOptions: {
      ignored: /node_modules/,
    },
  };
};
