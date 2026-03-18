const { src, dest } = require('gulp');
const pkg = require('./package.json');

const files = [
    '**/*',

    // Ignored folders.
    '!**/.*/**',
    '!**/__*/**',
    '!**/node_modules/**',
    '!src/**',
    '!reference/**',
    '!_scaffold/**',

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
    '!CLAUDE.md',
    '!PROJECT_CONTEXT.md',
];

const zip = async () => {
    const gulpZip = (await import('gulp-zip')).default;
    return src(files)
        .pipe(gulpZip(pkg.name + '-v' + pkg.version + '.zip'))
        .pipe(dest('./'));
};

exports.zip = zip;
