/**
 * @file Gruntfile.js
 * @description Grunt build configuration for the Beer Slurper WordPress plugin.
 *              Defines tasks for linting, CSS minification, file watching, release
 *              packaging, internationalization, and test execution.
 */

/**
 * Configures and registers Grunt tasks for the Beer Slurper plugin build process.
 *
 * @param {Object} grunt - The Grunt instance providing task registration and configuration APIs.
 * @description Initializes the Grunt configuration with tasks for:
 *              - JSHint linting of JavaScript files
 *              - CSS minification with version banners
 *              - File watching with LiveReload support
 *              - Release directory cleanup and packaging
 *              - WordPress readme to markdown conversion
 *              - PHPUnit and QUnit test execution
 *              - Internationalization (i18n) text domain and POT file generation
 */
module.exports = function( grunt ) {

	// Project configuration - defines all task options and file patterns
	grunt.initConfig( {
		pkg:    grunt.file.readJSON( 'package.json' ),

		// JSHint - JavaScript code quality and syntax checking
		jshint: {
			all: [
				'Gruntfile.js',
				'assets/js/test/**/*.js'
			]
		},

		// CSS Minification - compresses CSS with version banner header
		cssmin: {
			options: {
				banner: '/*! <%= pkg.title %> - v<%= pkg.version %>\n' +
					' * <%=pkg.homepage %>\n' +
					' * Copyright (c) <%= grunt.template.today("yyyy") %>;' +
					' * Licensed GPLv2+' +
					' */\n',
				processImport: false
			},
			minify: {
				expand: true,

				cwd: 'assets/css/',
				src: ['beer-slurper.css'],

				dest: 'assets/css/',
				ext: '.min.css'
			}
		},

		// Watch - monitors files for changes and triggers appropriate tasks
		watch:  {
			// LiveReload configuration for CSS changes
			livereload: {
				files: ['assets/css/*.css'],
				options: {
					livereload: true
				}
			},
			// CSS file watching - triggers minification on source changes
			styles: {
				files: ['assets/css/*.css', '!assets/css/*.min.css'],
				tasks: ['cssmin'],
				options: {
					debounceDelay: 500
				}
			}
		},

		// Clean - removes previous release build directory
		clean: {
			main: ['release/<%= pkg.version %>']
		},

		// Copy - copies plugin files to versioned release directory
		copy: {
			// Copy the plugin to a versioned release directory
			main: {
				src:  [
					'**',
					'!**/.*',
					'!**/*.md',
					'!node_modules/**',
					'!tests/**',
					'!release/**',
					'!bin/**',
					'!agent-os/**',
					'!.claude/**',
					'!src/**',
					'!assets/css/src/**',
					'!assets/js/src/**',
					'!images/src/**',
					'!bootstrap.php',
					'!bower.json',
					'!composer.json',
					'!composer.lock',
					'!Gruntfile.js',
					'!package.json',
					'!package-lock.json',
					'!phpunit.xml',
					'!phpunit.xml.dist'
				],
				dest: 'release/<%= pkg.version %>/'
			}
		},

		// Compress - creates ZIP archive for distribution
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: './release/beer-slurper.<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'release/<%= pkg.version %>/',
				src: ['**/*'],
				dest: 'beer-slurper/'
			}
		},

		// WordPress Readme to Markdown - converts readme.txt to readme.md
		wp_readme_to_markdown: {
			readme: {
				files: {
					'readme.md': 'readme.txt'
				}
			}
		},

		// PHPUnit - PHP unit test execution configuration
		phpunit: {
			classes: {
				dir: 'tests/phpunit/'
			},
			options: {
				bin: 'vendor/bin/phpunit',
				bootstrap: 'bootstrap.php',
				colors: true
			}
		},

		// QUnit - JavaScript unit test execution configuration
		qunit: {
			all: ['tests/qunit/**/*.html']
		},

		// Add Text Domain - ensures all PHP files have correct text domain
		addtextdomain: {
			files: {
				src: [
				'*.php',
				'**/*.php',
				'!node_modules/**',
				'!tests/**',
				'!vendor/**'
				]
			}
		},

		// Make POT - generates translation template file
		makepot: {
        target: {
            options: {
            	domainPath: 'languages/',
				exclude: [
				'node_modules/',
				'tests/',
				'vendor/'
				],
				mainFile: 'beer-slurper.php',
            	type: 'wp-plugin'
            }
        }
    }
	} );

	// Load tasks - automatically loads all grunt-* tasks from package.json
	require('load-grunt-tasks')(grunt);
	grunt.loadNpmTasks('grunt-wp-i18n');

	// Register tasks - defines composite task aliases

	// Default task: lint, minify CSS, convert readme, and generate POT
	grunt.registerTask( 'default', ['jshint', 'cssmin', 'wp_readme_to_markdown', 'makepot' ] );

	// Build task: runs default tasks then creates release package
	grunt.registerTask( 'build', ['default', 'clean', 'copy', 'compress'] );

	// Test task: runs PHPUnit and QUnit test suites
	grunt.registerTask( 'test', ['phpunit', 'qunit'] );

	grunt.util.linefeed = '\n';
};
