# Development Guidelines - OneSearch

Code contributions, bug reports, and feature requests are welcome! The following sections provide guidelines for contributing to this project, as well as information about development processes and testing.

## Table of Contents

- [Development Guidelines - OneSearch](#development-guidelines---onesearch)
  - [Table of Contents](#table-of-contents)
  - [Directory Structure](#directory-structure)
  - [Local setup](#local-setup)
    - [Prerequisites](#prerequisites)
    - [Installation](#installation)
    - [Useful Commands](#useful-commands)
      - [Installing Dependencies](#installing-dependencies)
      - [Accessing the Local Environment](#accessing-the-local-environment)
      - [Linting and Formatting](#linting-and-formatting)
    - [Running Tests](#running-tests)
    - [Building the plugin for distribution](#building-the-plugin-for-distribution)
  - [Code Contributions (Pull Requests)](#code-contributions-pull-requests)
    - [Workflow](#workflow)
    - [Code Quality / Code Standards](#code-quality--code-standards)
      - [PHP\_CodeSniffer](#php_codesniffer)
      - [PHPStan](#phpstan)
      - [ESLint](#eslint)
      - [Stylelint](#stylelint)
  - [Releasing](#releasing)
    - [Release Commands](#release-commands)

## Directory Structure

<details>
<summary> Click to expand </summary>

```bash
.
├── .github/ # GitHub-specific files and CI/CD workflows.
│
│   # Non-php plugin assets.
├── assets/ 
│   └── @todo
│
│   # Project documentation.
├── docs/
│   ├── CODE_OF_CONDUCT.md
│   ├── CONTRIBUTING.md     # 👈 You are here.
│   ├── DEVELOPMENT.md
│   └── SECURITY.md
│
│   # PHP source files.
├── inc/
│   # Legacy files loaded via classmap.
│   ├── classes/
│   ├── traits/
│   │
│   ├── Autoloader.php # Wraps autoloader for WordPress.
│   └── Main.php  # Main plugin class, initializes modules.
│
│   # Tests
├── tests/
│   ├── _output/ # Generated results and caches.
│   │   ├── debug.log/ # `wp-env` debug log file.
│   ├── phpunit/ # PHPUnit tests.
│   │
│   └── bootstrap.php # PHPUnit bootstrapper

│   # Build directories
├── build/        # assets built by webpack
├── node_modules/ # Node.js dependencies
├── vendor/       # Composer dependencies
│
├── onesearch.php        # Root plugin entrypoint.
├── custom-functions.php # Global functions.
├── uninstall.php        # Plugin uninstall script.
│
│   # Important config files.
│   # .dist suffixes mean there may be a user-customized version without the suffix.
├── .editorconfig
├── .eslintrc.json
├── .nvmrc
├── .wp-env.json
├── babel.config.js
├── composer.json
├── package.json
├── phpcs.xml.dist
├── phpstan.neon.dist
├── phpunit.xml.dist
├── README.md
└── webpack.config.js
```

</details>

## Local setup

To set up locally, clone the repository into plugins directory of your WordPress installation:

### Prerequisites

- [Node.js](https://nodejs.org/): v22+ ([NVM](https://nvm.sh/) recommended )
- [Docker](https://www.docker.com/)
- Composer: (if you prefer to run the Composer tools locally)

You can use Docker and the `wp-env` tool to set up a local development environment, instead of manually installing the specific testing versions of WordPress, PHP, and Composer. For more information, see the [wp-env documentation](https://developer.wordpress.org/block-editor/packages/packages-env/).

### Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/rtCamp/onesearch.git
   ```

2. Change into the project folder and install the development dependencies:

   ```bash
   ## If you're using NVM, make sure to use the correct Node.js version:
   nvm install && nvm use

   ## Then install the NPM dependencies:
   npm install

   # And the Composer dependencies:
   composer install
   ```

3. Start the local development environment:

   ```bash
   npm run wp-env start
   ```

The WordPress development site will be available at <http://localhost:8888> and the WP Admin Dashboard will be available at <http://localhost:8888/wp-admin/>. You can log in to the admin using the username `admin` and password `password`.

### Useful Commands

#### Installing Dependencies

- `composer install`: Install PHP dependencies.
- `npm install`: Install JavaScript dependencies.

#### Accessing the Local Environment

- `npm run wp-env start`: Start the local development environment.
- `npm run wp-env stop`: Stop the local development environment.
- `npm run wp-env run tests-cli YOUR_CMD_HERE`: Run WP-CLI commands in the local environment.

For more information on using `wp-env`, see the [wp-env documentation](https://developer.wordpress.org/block-editor/packages/packages-env/).

#### Linting and Formatting

- `npm run lint:css`:      Runs stylelint on the CSS code.
- `npm run lint:js`:       Runs ESLint on the JavaScript code.
- `npm run lint:js:fix`:   Autofixes ESLint issues.
- `npm run lint:js:types`: Runs TypeScript's `tsc` to check for type errors.
- `npm run lint:php`:      Runs PHPCS linting on the PHP code.
- `npm run lint:php:fix`:  Autofixes PHPCS linting issues.
- `npm run lint:php:stan`: Runs PHPStan static analysis on the PHP code.

### Running Tests

PHPUnit tests can be run using the following command:

```bash
npm run test:php
```

To generate a code coverage report, make sure to start the testing environment with coverage mode enabled:

```bash
npm run wp-env start -- --xdebug=coverage

npm run test:php
```

You should see the html coverage report in the `tests/_output/html` directory and the clover XML report in `tests/_output/php-coverage.xml`.

### Building the plugin for distribution

To build the plugin for distribution, you can use the following commands`:

```bash

# Clean install of node modules.
npm ci

# Create a production-ready build:
npm run build:prod

## Create the zip file for distribution:
npm run plugin-zip
```

## Code Contributions (Pull Requests)

### Workflow

The `develop` branch is used for active development, while `main` contains the current stable release. Always create a new branch from `develop` when working on a new feature or bug fix.

Branches should be prefixed with the type of change (e.g. `feat`, `chore`, `tests`, `fix`, etc.) followed by a short description of the change. For example, a branch for a new feature called "Add new feature" could be named `feat/add-new-feature`.

### Code Quality / Code Standards

This project uses several tools to ensure code quality and standards are maintained:

#### PHP_CodeSniffer

This project uses [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/) to enforce WordPress Coding Standards. We use the [WPGraphQL Coding Standards ruleset](https://github.com/AxeWP/WPGraphQL-Coding-Standards), which is a superset of [WPCS](https://github.com/WordPress/WordPress-Coding-Standards), [VIPCS](https://github.com/Automattic/VIP-Coding-Standards), and [Slevomat Coding Standard](https://github.com/slevomat/coding-standard) tailored for the WPGraphQL ecosystem.

Our specific ruleset is defined in the [`phpcs.xml.dist`](../phpcs.xml.dist) file.

You can run the PHP_CodeSniffer checks using the following command:

```bash
npm run lint:php
```

PHP_CodeSniffer can automatically fix some issues. To fix issues automatically, run:

```bash
npm run lint:php:fix
```

#### PHPStan

This project uses [PHPStan](https://phpstan.org/) to perform static analysis on the PHP code. PHPStan is a PHP Static Analysis Tool that focuses on finding errors in your code without actually running it.

Our specific configuration is defined in the [`phpstan.neon.dist`](../phpstan.neon.dist) file.

You can run PHPStan using the following command:

```bash
npm run lint:php:stan
```

#### ESLint

This project uses [ESLint](https://eslint.org) through `@wordpress/scripts` and `@wordpress/eslint-plugin` for JavaScript linting, following WordPress coding standards and best practices.

Our specific ESLint configuration is defined in the [`.eslintrc.json`](../.eslintrc.json) file.

You can run ESLint on JavaScript files using:

```bash
npm run lint:js
```

To automatically fix JavaScript linting issues:

```bash
npm run lint:js:fix
```

#### Stylelint

This project uses [Stylelint](https://stylelint.io/) through `@wordpress/scripts` for CSS linting, following WordPress coding standards and best practices.

Our specific Stylelint configuration is defined in the [`.stylelintrc.json`](../.stylelintrc.json) file.

You can run Stylelint on CSS files using:

```bash
npm run lint:css
```

To automatically fix CSS linting issues:

```bash
npm run lint:css:fix
```

## Releasing

1. Ensure all changes are committed and tested.
2. Update changelogs and version numbers on `develop` branch.
3. Push from `develop` to `main` branch.
4. Tag release and push to remote.
5. Publish packages if needed.
