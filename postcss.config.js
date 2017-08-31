module.exports = (ctx) => ({
    map: ctx.options.map ? { inline: false } : false,
    plugins: {
        /*'stylelint': {
            "extends": "stylelint-config-standard",
            "rules": {
                "max-empty-lines": 2
            }
        },*/
        'precss': {},
        'postcss-cssnext': {},
        'cssnano': ctx.env === 'production' ? { 'autoprefixer': false } : false
    }
});