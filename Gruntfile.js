module.exports = function( grunt ) {

	// Project configuration
	grunt.initConfig( {
		pkg:    grunt.file.readJSON( 'package.json' ),
		jshint: {
			all: [
				'Gruntfile.js',
				'assets/js/test/**/*.js'
			]
		},

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
		watch:  {
			livereload: {
				files: ['assets/css/*.css'],
				options: {
					livereload: true
				}
			},
			styles: {
				files: ['assets/css/*.css', '!assets/css/*.min.css'],
				tasks: ['cssmin'],
				options: {
					debounceDelay: 500
				}
			}
		},
		clean: {
			main: ['release/<%= pkg.version %>']
		},
		copy: {
			// Copy the plugin to a versioned release directory
			main: {
				src:  [
					'**',
					'!**/.*',
					'!**/readme.md',
					'!node_modules/**',
					'!vendor/**',
					'!tests/**',
					'!release/**',
					'!assets/css/src/**',
					'!images/src/**',
					'!bootstrap.php',
					'!bower.json',
					'!composer.json',
					'!composer.lock',
					'!Gruntfile.js',
					'!package.json',
					'!phpunit.xml',
					'!phpunit.xml.dist'
				],
				dest: 'release/<%= pkg.version %>/'
			}
		},
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: './release/beer_slurper.<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'release/<%= pkg.version %>/',
				src: ['**/*'],
				dest: 'beer_slurper/'
			}
		},
		wp_readme_to_markdown: {
			readme: {
				files: {
					'readme.md': 'readme.txt'
				}
			}
		},
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
		qunit: {
			all: ['tests/qunit/**/*.html']
		},
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

	// Load tasks
	require('load-grunt-tasks')(grunt);
	grunt.loadNpmTasks('grunt-wp-i18n');

	// Register tasks

	grunt.registerTask( 'default', ['jshint', 'cssmin', 'wp_readme_to_markdown', 'makepot' ] );


	grunt.registerTask( 'build', ['default', 'clean', 'copy', 'compress'] );

	grunt.registerTask( 'test', ['phpunit', 'qunit'] );

	grunt.util.linefeed = '\n';
};
