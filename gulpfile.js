const { src, dest } = require('gulp');
const path = require('path');
const { Transform } = require('stream');
const pkg = require('./package.json');

// WordPress names the plugin directory after the ZIP's top-level folder. Without one it
// falls back to the ZIP filename (divi-csv-events-v1.5.0), so every version installs as a
// separate plugin. The folder name must match the update checker slug.
const PLUGIN_DIR = 'divi-csv-events';

const intoPluginDir = () => new Transform({
    objectMode: true,
    transform(file, _enc, cb) {
        file.path = path.join(file.base, PLUGIN_DIR, file.relative);
        cb(null, file);
    },
});

const files = [
    '**/*',

    // Ignored folders.
    '!**/.*/**',
    '!**/__*/**',
    '!**/node_modules/**',
    // iCloud Drive leaves "<name> 2", "<name> 3" ... duplicates (files and dirs)
    // when composer swaps dev deps.
    '!**/* [0-9]',
    '!**/* [0-9]/**',
    '!src/**',
    '!reference/**',
    '!_scaffold/**',
    '!tests/**',
    '!docs/**',

    // Ignored files.
    '!**/*.zip',
    '!**/*.map',
    '!.gitignore',
    '!gulpfile.js',
    '!package.json',
    '!package-lock.json',
    '!tsconfig.json',
    '!webpack.config.js',
    '!composer.json',
    '!composer.lock',
    '!phpunit.xml.dist',
    '!.npmrc',
    '!CLAUDE.md',
    '!PROJECT_CONTEXT.md',
];

const zip = async () => {
    const gulpZip = (await import('gulp-zip')).default;
    return src(files, { nodir: true })
        .pipe(intoPluginDir())
        .pipe(gulpZip(pkg.name + '-v' + pkg.version + '.zip'))
        .pipe(dest('./'));
};

exports.zip = zip;
