# {{PROJECT_TITLE}}

The WordPress site for {{PROJECT_TITLE}}.

The requirements and local development setup assumes you are using macOS, however it should be fairly easy to adapt for Linux or Windows.

## Requirements

- [Laravel Valet](https://laravel.com/docs/12.x/valet#installation)
- [Node](https://nodejs.org/en/)
- [NVM](https://github.com/nvm-sh/nvm#installing-and-updating)
- [Homebrew](https://brew.sh)
- [DBngin](https://dbngin.com)
- [MySQL Client 8.4](https://formulae.brew.sh/formula/mysql@8.4)

## Local Development Setup

### Clone the repo

If you're not using the recommended `~/Sites` directory for projects change the path below to where you want to clone the project.

```bash
  cd ~/Sites
  git clone {{GIT_REMOTE_SSH}}
```

### Setup your local environment

You can run the following commands to setup the basic features of the project, this will setup the Valet link, install packages using composer and npm, and compile assets.

```bash
    cd {{PROJECT_NAME}}
    nvm install
    npm run setup
```

That's it! You should now have a working local development setup. If you want to refresh your local database content at any point in the future you can run the following command.

```bash
	npm run db:pull-from-staging
```

You can also download media gallery images using the following command. By default it will download the last 3 months of media but you can change this in your `.env` file.

```bash
	npm run media:pull-from-staging
```

_Note:_ See Important Developer Notes further down for caveats to running the above database pull process.

## Git Branch Structure

It's very important to follow the branching structure below, if you have a suggestion to change or improve this workflow please raise it through the Coding Working Group.

### Feature & bugfix branches

Feature & bugfix branches always get merged into `release/*` branches. You should use either the Monday.com task title and pulse ID from the end of the URL or for bugfixes from a zoho help desk ticket use the format shown below

```
	feature/monday-task-title-goes-here-1234567890
	bugfix/monday-task-title-goes-here-1234567890
	bugfix/z123-zoho-ticket-subject-goes-here
```

### Hotfix branches

Hotfix branches are usually directly merged into the `main` branch via a pull/merge request

```
  hotfix/monday-task-title-goes-here-1234567890
```

### Release branches

Release branches are where you should merge `feature/*` and `bugfix/*` branches. You should never commit directly to a `release/*` branch, only merge into it as they are disposable and should be deleted once finished with (or merged into `main`). You should NEVER commit directly to a release branch.

```
  release/2025-06-04
  release/2025-06-04--2
```

More information on the branching structure can be [found here](https://coda.io/d/External-Git-Branching-Strategy_dxA565SyoPQ/External-Git-Branching-Strategy_su0Nr?searchClick=0e597281-84de-448e-907f-bc4603069270_xA565SyoPQ).

## Deployment Workflows

This project uses Capistrano to deploy to Kinsta, please use the instructions below to deploy your work:

### Staging (UAT)

First make sure you've followed the instructions above to create a `release/*` branch, make sure you've pushed your branch to the remote repository and then on the command line run `npm run deploy`. You will be prompted to either select a `release/*` branch from the list or type in the name of a branch to deploy.

Note: When typing in a branch name the script will check that it exists in the remote repository to avoid Capistrano errors.

### Production

To deploy to the Production environment you need to open a pull request for your `release/*` branch into `main` and merge in Gitlab if it passes all the checks, and then run `npm run deploy:prod`, you will be prompted to type `yes` to confirm you want to deploy to production before the process starts.

## Important Developer Notes

### Pulling from Staging

By default running the `npm run media:pull-from-staging` command limits the media gallery download to only the previous 3 months of images. If you require older media than this you can specify using the `MEDIA_DOWNLOAD_MONTHS` setting in your `.env` file. If you want to download the entire media gallery you can set the value to `-1`, just make sure you have enough disk space available locally before running this command.

#### Troubleshooting

You will need to ensure you have MySQL 8.4 Client installed via Homebrew to use the Pull from Staging feature. If you need to check what version you have installed you can run:

`mysql --version`

To remove v9.0 run the following:

`brew uninstall mysql`

You can now install v8.4 by running:

`brew install mysql@8.4`

Make sure you follow the instructions to symlink that are displayed after installing this keg-only version.

### Rebuilding Composer vendor / NPM node_modules folders

There is a built-in command to delete the lock files and `vendor`/`node_modules`/`wp` folders, re-runs `valet use` to ensure the correct PHP version is being used, and then re-runs the install commands for Composer and NPM which is useful when switching branches:

`npm run setup:rebuild`

It runs the following commands:

`rm composer.lock package-lock.json; rm -r node_modules vendor wp; valet use; composer install; npm install;`

### Asset directories

This project uses the following folder structure for assets within the theme folder:

- dist
  - css
  - js
- src
  - js
  - scss
- static
  - css
  - fonts
  - img
  - js
  - svg

`dist` is the build location for compiled styles and scripts, Laravel Mix takes the source files found in `src` to create the two versioned source files in `dist/css` and `dist/js`.

`src` contains Javascript ES6 modules that are included into `main.js` and SCSS stylesheets that are included into `main.scss`, you should not put any static resources inside any of the folders in `src`. If you have to use a static `.js` or `.css` file (ideally don't) make sure it's included in the `static` sub-folders.

`static` is for all static resources included legacy Javascript and Stylesheets that haven't yet been incorporated in the build process, as well as fonts, images and SVGs.
