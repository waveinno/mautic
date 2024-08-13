'use strict';

module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Define the configuration for all the tasks
    grunt.initConfig({

        // mautic assets dir path
        mautic: {
            // configurable paths
            bundleAssets: 'app/bundles/**/Assets/css',
            pluginAssets: 'plugins/**/Assets/css',
            rootAssets: 'media/css'
        },

        // Watches files for changes and runs tasks based on the changed files
        watch: {
            less: {
                files: ['<%= mautic.bundleAssets %>/**/*.less', '<%= mautic.bundleAssets %>/../builder/*.less'],
                tasks: ['less']
            }
        },

        // Compiles less files in bundle's Assets/css root and single level directory to CSS
        less: {
            files: {
                src: ['<%= mautic.bundleAssets %>/*.less', '<%= mautic.pluginAssets %>/*.less', '<%= mautic.bundleAssets %>/*/*.less', '<%= mautic.bundleAssets %>/../builder/*.less'],
                expand: true,
                rename: function (dest, src) {
                    return dest + src.replace('.less', '.css')
                },
                dest: ''
            },
            options: {
                javascriptEnabled: true
            }
        }
    });

    grunt.registerTask('compile-less', [
        'less',
        'watch'
    ]);
};
