const plugin = 'category-icon',

	gulp = require('gulp'),
	plugins = require('gulp-load-plugins')(),
	fs = require('fs'),
	del = require('del'),
	cp = require('child_process'),
	commandExistsSync = require('command-exists').sync,
	log = require('fancy-log');

/**
 * Copy plugin folder outside in a build folder, recreate styles before that
 */
function copyFolder () {
	const dir = process.cwd()
	return gulp.src('./*')
		.pipe(plugins.exec('rm -Rf ./../build; mkdir -p ./../build/' + plugin + ';', {
			silent: true,
			continueOnError: true // default: false
		}))
		.pipe(plugins.rsync({
			root: dir,
			destination: '../build/' + plugin + '/',
			progress: false,
			silent: false,
			compress: false,
			recursive: true,
			emptyDirectories: true,
			clean: true,
			exclude: ['node_modules', 'node-tasks']
		}))
}
gulp.task('copy-folder', copyFolder)

/**
 * Clean the folder of unneeded files and folders
 */
function removeUnneededFiles (done) {

	// files that should not be present in build zip
	var files_to_remove = [
		'**/codekit-config.json',
		'node_modules',
		'node-tasks',
		'bin',
		'tests',
		'.travis.yml',
		'.babelrc',
		'.gitignore',
		'.codeclimate.yml',
		'.csslintrc',
		'.eslintignore',
		'.eslintrc',
		'circle.yml',
		'phpunit.xml.dist',
		'.sass-cache',
		'config.rb',
		'gulpfile.js',
		'webpack.config.js',
		'package.json',
		'package-lock.json',
		'pxg.json',
		'build',
		'.idea',
		'**/*.css.map',
		'**/.git*',
		'*.sublime-project',
		'.DS_Store',
		'**/.DS_Store',
		'__MACOSX',
		'**/__MACOSX',
		'+development.rb',
		'+production.rb',
		'README.md',
		'admin/src',
		'admin/scss',
		'admin/js/**/*.map',
		'admin/css/**/*.map',
		'.csscomb',
		'.csscomb.json',
		'.codeclimate.yml',
		'tests',
		'circle.yml',
		'.circleci',
		'.labels',
		'.jscsrc',
		'.jshintignore',
		'browserslist',
		'babel.config.js',

		'admin/scss',
		'admin/src',
		'admin/js/vendor/elasticsearch.js'
	]

	files_to_remove.forEach(function (e, k) {
		files_to_remove[k] = '../build/' + plugin + '/' + e
	})

	return del(files_to_remove, {force: true})
}

gulp.task('remove-files', removeUnneededFiles)

function maybeFixBuildDirPermissions (done) {

	cp.execSync('find ./../build -type d -exec chmod 755 {} \\;')

	return done()
}

maybeFixBuildDirPermissions.description = 'Make sure that all directories in the build directory have 755 permissions.'
gulp.task('fix-build-dir-permissions', maybeFixBuildDirPermissions)

function maybeFixBuildFilePermissions (done) {

	cp.execSync('find ./../build -type f -exec chmod 644 {} \\;')

	return done()
}

maybeFixBuildFilePermissions.description = 'Make sure that all files in the build directory have 644 permissions.'
gulp.task('fix-build-file-permissions', maybeFixBuildFilePermissions)

function maybeFixIncorrectLineEndings (done) {
	if (!commandExistsSync('dos2unix')) {
		log.error('Could not ensure that line endings are correct on the build files since you are missing the "dos2unix" utility! You should install it.')
		log.error('However, this is not a very big deal. The build task will continue.')
	} else {
		cp.execSync('find ./../build -type f -print0 | xargs -0 -n 1 -P 4 dos2unix')
	}

	return done()
}

maybeFixIncorrectLineEndings.description = 'Make sure that all line endings in the files in the build directory are UNIX line endings.'
gulp.task('fix-line-endings', maybeFixIncorrectLineEndings)

// -----------------------------------------------------------------------------
// Replace the plugin's text domain with the actual text domain
// -----------------------------------------------------------------------------
function replaceTextdomainPlaceholder () {
	const textDomain = 'category-icon'

	return gulp.src('../build/' + plugin + '/**/*.php')
		.pipe(plugins.replace(/['|"]__plugin_txtd['|"]/g, '\'' + textDomain + '\''))
		.pipe(gulp.dest('../build/' + plugin))
}

gulp.task('txtdomain-replace', replaceTextdomainPlaceholder)

/**
 * Create a zip archive out of the cleaned folder and delete the folder
 */
function createZipFile () {
	var versionString = ''
	// get plugin version from the main plugin file
	var contents = fs.readFileSync('./' + plugin + '.php', 'utf8')

	// split it by lines
	var lines = contents.split(/[\r\n]/)

	function checkIfVersionLine (value, index, ar) {
		var myRegEx = /^[\s\*]*[Vv]ersion:/
		if (myRegEx.test(value)) {
			return true
		}
		return false
	}

	// apply the filter
	var versionLine = lines.filter(checkIfVersionLine)

	versionString = versionLine[0].replace(/^[\s\*]*[Vv]ersion:/, '').trim()
	versionString = '-' + versionString.replace(/\./g, '-')

	return gulp.src('./')
		.pipe(plugins.exec('cd ./../; rm -rf ' + plugin[0].toUpperCase() + plugin.slice(1) + '*.zip; cd ./build/; zip -r -X ./../' + plugin[0].toUpperCase() + plugin.slice(1) + versionString + '.zip ./; cd ./../; rm -rf build'))

}

gulp.task('make-zip', createZipFile)

function buildSequence (cb) {
	return gulp.series('copy-folder', 'remove-files', 'fix-build-dir-permissions', 'fix-build-file-permissions', 'fix-line-endings', 'txtdomain-replace')(cb)
}

buildSequence.description = 'Sets up the build folder'
gulp.task('build', buildSequence)

function zipSequence (cb) {
	return gulp.series('build', 'make-zip')(cb)
}

zipSequence.description = 'Creates the zip file'
gulp.task('zip', zipSequence)
