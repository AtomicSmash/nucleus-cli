require("laravel-mix-tailwind");
require('laravel-mix-clean');
const mix = require("laravel-mix");
const path = require("path");

// Use environment variables or default values
const rootPath = "./";
const themePath = "wp-content/themes/{{THEME_NAME}}";
const rootThemePath = path.join(rootPath, themePath);
const publicPath = path.join(rootThemePath, "dist");
const valetHost = process.env.MIX_VALET_HOST || "https://{{PROJECT_NAME}}.test";

mix.setPublicPath(publicPath);

// Compile SASS, Tailwind, and JavaScript
mix
  .sass(path.join(rootThemePath, "src/scss/main.scss"), "css/main.css")
  .options({
    autoprefixer: false,
    processCssUrls: false,
  })
  .tailwind()
  .js(path.join(rootThemePath, "src/js/main.js"), "js/main.js")
  .version()
  .clean();

// Configure BrowserSync
mix.browserSync({
  proxy: valetHost,
  https: true,
  files: [
    path.join(rootThemePath, "**/*.(php|css|js)"),
    path.join(publicPath, "*.(css|html|js)"),
  ],
  injectChanges: true,
  reloadThrottle: 3000,
});

// Webpack specific configurations
mix.webpackConfig({
	externals: {
		jquery: 'jQuery'
  },
  output: {
    publicPath: "/" + path.join(themePath, "dist"),
    chunkFilename: "[name].js?id=[chunkhash]",
  },
  plugins: [
    {
      apply: (compiler) => {
        compiler.hooks.done.tap("AfterEmitPlugin", (stats) => {
          if (mix.inProduction()) {
            const convertToFileHash = require("laravel-mix-make-file-hash");
            convertToFileHash({
              publicPath: publicPath,
              manifestFilePath: path.join(publicPath, "mix-manifest.json"),
            });
          }
        });
      },
    },
  ],
});
