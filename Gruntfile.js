'use strict';

module.exports = function (grunt) {
    
    // Load all Grunt tasks matching the `grunt-*` pattern
    require('load-grunt-tasks')(grunt);

    // Time how long tasks take. Can help when optimizing build times
    require('time-grunt')(grunt);

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        watch: {
            gruntfile: {
                files: ['Gruntfile.js']
            },
            js: {
                files: ['js/src/**/*.js','js/src/**/*.jsx'],
                tasks: ['browserify', 'exorcise']
            },
            sass: {
                files: ['scss/**/*.scss'],
                tasks: ['sass', 'autoprefixer']
            }
        },
        browserify: {
            dist: {
                files: {
                    'js/build/mirror.js': ['js/src/mirror.js']
                },
                options: {
                    transform: [['babelify', { presets: ["react", "es2015"] }]],
                    browserifyOptions: {
                        debug: true
                    }
                }
            }
        },
        exorcise: {
            bundle: {
                options: {},
                files: {
                    'js/build/mirror.map': ['js/build/mirror.js']
                }
            }
        },
//        sass_globbing: {
//            glob: {
//                files: {
//                    'scss/_globalsMap.scss': 'scss/globals/**/*.scss',
//                    'scss/_componentsMap.scss': 'scss/components/**/*.scss'
//                },
//                options: {
//                    useSingleQuotes: false,
//                    signature: '// Hello, World!'
//                }
//            }
//        },
        sass: {
            options: {
                sourceMap: true,
                outputStyle: "expanded"
            },
            dist: {
                files: [{
                    expand: true,
                    cwd: 'scss/',
                    src: [
                        '*.scss',
                        '!_*.scss'
                    ],
                    dest: 'design/',
                    ext: '.css'
                }]
            }
        },
        autoprefixer: {
            dist: {
                src: ['design/**/*.css']
            }
        },
        imagemin: {
            dist: {
                files: [{
                    expand: true,
                    cwd: 'design/images',
                    src: '**/*.{gif,jpeg,jpg,png,svg}',
                    dest: 'design/images'
                }]
            }
        }

    });

    grunt.registerTask('default', [
        'browserify',
        'exorcise',
        'sass',
        'autoprefixer',
        'imagemin'
    ]);
    
    grunt.registerTask('css', [
        'sass',
        'autoprefixer'
    ]);
    
    grunt.registerTask('js', [
        'browserify',
        'exorcise'
    ]);
};