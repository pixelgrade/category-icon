/*
 * Load Plugins
 */
var gulp = require( 'gulp' ),
    exec = require( 'gulp-exec' ),
    clean = require( 'gulp-clean' ),
    zip = require( 'gulp-zip' );



/**
 * Create a zip archive out of the cleaned folder and delete the folder
 */
gulp.task( 'zip', ['build'], function() {

    return gulp.src( './' )
        .pipe( exec( 'cd ./../; rm -rf category-icon.zip; cd ./build/; zip -r -X ./../category-icon.zip ./category-icon; cd ./../; rm -rf build' ) );

} );

/**
 * Copy theme folder outside in a build folder, recreate styles before that
 */
gulp.task( 'copy-folder', function() {

    return gulp.src( './' )
        .pipe( exec( 'rm -Rf ./../build; mkdir -p ./../build/category-icon; cp -Rf ./* ./../build/category-icon/' ) );
} );

/**
 * Clean the folder of unneeded files and folders
 */
gulp.task( 'build', ['copy-folder'], function() {

    // files that should not be present in build zip
    files_to_remove = [
        '**/codekit-config.json',
        'node_modules',
        'config.rb',
        'gulpfile.js',
        'package-lock.json',
        'package.json',
        'wpgrade-core/vendor/redux2',
        'wpgrade-core/features',
        'wpgrade-core/tests',
        'wpgrade-core/**/*.less',
        'wpgrade-core/**/*.scss',
        'wpgrade-core/**/*.rb',
        'wpgrade-core/**/sass',
        'wpgrade-core/**/scss',
        'pxg.json',
        'build',
        '.idea',
        '**/*.css.map',
        '**/.sass*',
        '.sass*',
        '**/.git*',
        '*.sublime-project',
        '.DS_Store',
        '**/.DS_Store',
        '__MACOSX',
        '**/__MACOSX'
    ];

    files_to_remove.forEach( function( e, k ) {
        files_to_remove[k] = '../build/category-icon/' + e;
    } );

    return gulp.src( files_to_remove, {read: false} )
        .pipe( clean( {force: true} ) );
} );
