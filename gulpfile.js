import gulp from 'gulp';
import concat from 'gulp-concat';
import connect from 'gulp-connect';
import {deleteAsync} from 'del';
import fs from 'fs';
import readdirRecursive from 'fs-readdir-recursive';
import gulpIf from 'gulp-if';
import imagemin from 'gulp-imagemin';
import imageminGifsicle from 'imagemin-gifsicle';
import imageminMozjpeg from 'imagemin-mozjpeg';
import imageminOptipng from 'imagemin-optipng';
import imageminSvgo from 'imagemin-svgo';
import mergeJson from 'gulp-merge-json';
import rev from 'gulp-rev';
import terser from 'gulp-terser';
import postcss from 'gulp-postcss';

// ===============================================================
// ENVIROMENT VARIABLES
// ===============================================================
let ENV = 'production';
const SRC = 'assets';
const DEST = 'www/assets';

const setEnvDevelopment = () => {
	return new Promise((resolve, reject) => {
		ENV = 'development';
		resolve();
	});
};

// ===============================================================
// CLEAN
// ===============================================================
// Empty directories to start fresh.
const clean = () => deleteAsync([
	'.tmp',
	'www/assets',
	'favicon.svg',
	'www/android-chrome-192x192.png',
	'www/android-chrome-512x512.png',
	'apple-touch-icon.png',
	'favicon.ico'
]);

// ===============================================================
// CONCAT CSS
// ===============================================================
// Source should be compiled CSS (.tmp/gulp/css/copmiled), resp. vendor CSS.
const concatCSSAdmin = () => {
	return gulp.src('assets/css/admin.css')
		.pipe(concat('admin.css'))
		.pipe(gulp.dest('.tmp/gulp/css/concated'));
};

const concatCSSPrint = () => {
	return gulp.src('assets/css/print.css')
		.pipe(concat('print.css'))
		.pipe(gulp.dest('.tmp/gulp/css/concated'));
};

const concatCSSReset = () => {
	return gulp.src('assets/css/reset.css')
		.pipe(concat('reset.css'))
		.pipe(gulp.dest('.tmp/gulp/css/concated'));
};

const concatCSSScreen = () => {
	return gulp.src('assets/css/screen.css')
		.pipe(concat('screen.css'))
		.pipe(gulp.dest('.tmp/gulp/css/concated'));
};

const concatCSSLeaflet = () => {
	return gulp.src([
			'node_modules/leaflet/dist/leaflet.css',
			'node_modules/leaflet-fullscreen/dist/leaflet.fullscreen.css',
		])
		.pipe(concat('leaflet.css'))
		.pipe(gulp.dest('.tmp/gulp/css/concated/leaflet'));
};

const concatCSS = gulp.parallel(
	concatCSSAdmin,
  concatCSSPrint,
  concatCSSReset,
  concatCSSScreen,
	concatCSSLeaflet
);

// ===============================================================
// CSS PROCESSING
// ===============================================================
const processCSS = () => {
	const stream = gulp.src('.tmp/gulp/css/concated/**/*.css')
					.pipe(postcss());

	if ( ENV == 'production' ) {
		return stream
			.pipe(rev())
			.pipe(gulp.dest(DEST + '/css'))
			.pipe(rev.manifest({
				path: 'css-rev-manifest.json',
				transformer: {
					parse: JSON.parse,
					stringify: (assets, replacer, space) => {
						let prefixed = {};
						for (let key in assets) {
							if (Object.prototype.hasOwnProperty.call(assets, key)) {
								prefixed['/assets/css/' + key] = '/assets/css/' + assets[key];
							}
						}
						return JSON.stringify(prefixed, replacer, space);
					}
				}
			}))
			.pipe(gulp.dest('.tmp/gulp'));
	}
	else {
		return stream
			.pipe(gulp.dest(DEST + '/css'))
			.pipe(connect.reload());
	}
};

// ===============================================================
// MAIN CSS TASK
// ===============================================================
const css = gulp.series(concatCSS, processCSS);

// ===============================================================
// CONCAT JS
// ===============================================================
const concatJSMaps = () => {
	return gulp.src([
      'node_modules/leaflet/dist/leaflet.js',
      'node_modules/leaflet-editable/src/Leaflet.Editable.js',
			'node_modules/leaflet-fullscreen/dist/Leaflet.fullscreen.min.js',
      SRC + '/js/maps.js'
    ])
		.pipe(concat('maps.js'))
		.pipe(gulp.dest('.tmp/gulp/js/concated'));
};

const concatJSNetteAjax = () => {
	return gulp.src(SRC + '/js/nette.ajax.js')
		.pipe(concat('nette.ajax.js'))
		.pipe(gulp.dest('.tmp/gulp/js/concated'));
};

const concatJS = gulp.parallel(
	concatJSMaps,
  concatJSNetteAjax
);

// ===============================================================
// JS PROCESSING
// ===============================================================
const processJS = () => {
	const stream = gulp.src('.tmp/gulp/js/concated/**/*.js');

	if ( ENV == 'production' ) {
		return stream
			.pipe(terser())
			.pipe(rev())
			.pipe(gulp.dest(DEST + '/js'))
			.pipe(rev.manifest({
				path: 'js-rev-manifest.json',
				transformer: {
					parse: JSON.parse,
					stringify: (assets, replacer, space) => {
						let prefixed = {};
						for (let key in assets) {
							if (Object.prototype.hasOwnProperty.call(assets, key)) {
								prefixed['/assets/js/' + key] = '/assets/js/' + assets[key];
							}
						}
						return JSON.stringify(prefixed, replacer, space);
					}
				}
			}))
			.pipe(gulp.dest('.tmp/gulp'));
	}
	else {
		return stream
			.pipe(gulp.dest(DEST + '/js'))
			.pipe(connect.reload());
	}
};

// ===============================================================
// MAIN JS TASK
// ===============================================================
const js = gulp.series(concatJS, processJS);

// ===============================================================
// REVISION MANIFEST
// ===============================================================
const revManifest = () => {
	if ( ENV == 'production' ) {
		return gulp.src([
				'.tmp/gulp/css-rev-manifest.json',
				'.tmp/gulp/js-rev-manifest.json',
				'.tmp/gulp/jquery-rev-manifest.json',
        '.tmp/gulp/nette-forms-rev-manifest.json',
			])
			.pipe(mergeJson({
				fileName: 'rev-manifest.json',
				jsonSpace: '  '
			}))
			.pipe(gulp.dest(DEST));
	}
	else {
		return new Promise((resolve, reject) => {
			const css = readdirRecursive(DEST + '/css');
			const js = readdirRecursive(DEST + '/js');
			let manifest = {};

			css.forEach(c => {
				manifest['/assets/css/' + c] = '/assets/css/' + c;
			});

			js.forEach(j => {
				manifest['/assets/js/' + j] = '/assets/js/' + j;
			});

			fs.writeFileSync(DEST + '/rev-manifest.json', JSON.stringify(manifest, null, '  '));
			resolve();
		});
	}
}

// ===============================================================
// IMAGE PROCESSING
// ===============================================================
const imageminOptions = [
	imageminGifsicle(),
	imageminMozjpeg({ progressive: true }),
	imageminOptipng(),
	imageminSvgo({
		plugins: [{
			name: 'preset-default',
			params: {
				overrides: {
					removeViewBox: false
				}
			}
		}]
	})
];

const imgPlain = () => {
	return gulp.src([
			SRC + '/img/**/*.{webp,jpg,png,svg,gif}',
			'!' + SRC + '/img/icons/**/*.svg'
		])
		.pipe(gulpIf(ENV == 'production', imagemin(imageminOptions)))
		.pipe(gulp.dest(DEST + '/img'))
		.pipe(connect.reload());
};

const imgLeaflet = () => {
	return gulp.src([
			'node_modules/leaflet/dist/images/*'
		])
		.pipe(gulpIf(ENV == 'production', imagemin(imageminOptions)))
		.pipe(gulp.dest(DEST + '/css/leaflet/images'))
};

const imgLeafletFullscreen = () => {
	return gulp.src([
			'node_modules/leaflet-fullscreen/dist/*.png'
		])
		.pipe(gulpIf(ENV == 'production', imagemin(imageminOptions)))
		.pipe(gulp.dest(DEST + '/css/leaflet'))
};

const icons = () => {
	return gulp.src(SRC + '/img/icons/**/*.svg')
		.pipe(gulpIf(ENV == 'dist', imagemin([
				imageminSvgo({
					plugins: [{
						name: 'preset-default',
						params: {
							overrides: {
								removeViewBox: false
							}
						}
					}, {
						name: 'addAttributesToSVGElement',
						params: {
							attributes: [{
								'aria-hidden': 'true'
							}, {
								focusable: 'false'
							}]
						}
					}]
				})
			])))
		.pipe(gulp.dest(DEST + '/img/icons'))
		.pipe(connect.reload());
};

const img = gulp.parallel(
	imgPlain,
  imgLeaflet,
  imgLeafletFullscreen
);

const favicon = () => {
	return gulp.src(SRC + '/favicon/*.{svg,png,ico}')
		.pipe(gulpIf(ENV == 'dist', imagemin(imageminOptions)))
		.pipe(gulp.dest('www'));
};

// ===============================================================
// COPY FILES
// ===============================================================
const copyJQuery = () => {
	return gulp.src([
			'node_modules/jquery/dist/jquery.min.js'
		])
		.pipe(gulpIf(ENV == 'production', rev()))
		.pipe(gulp.dest(DEST+'/js'))
		.pipe(gulpIf(ENV == 'production', rev.manifest({
			path: 'jquery-rev-manifest.json',
			transformer: {
				parse: JSON.parse,
				stringify: (assets, replacer, space) => {
					let prefixed = {};
					for (let key in assets) {
						if (Object.prototype.hasOwnProperty.call(assets, key)) {
							prefixed['/assets/js/' + key] = '/assets/js/' + assets[key];
						}
					}
					return JSON.stringify(prefixed, replacer, space);
				}
			}
		})))
		.pipe(gulpIf(ENV == 'production', gulp.dest('.tmp/gulp')));
};

const copyNetteForms = () => {
	return gulp.src([
			'node_modules/nette-forms/src/assets/netteForms.min.js'
		])
		.pipe(gulpIf(ENV == 'production', rev()))
		.pipe(gulp.dest(DEST+'/js'))
		.pipe(gulpIf(ENV == 'production', rev.manifest({
			path: 'nette-forms-rev-manifest.json',
			transformer: {
				parse: JSON.parse,
				stringify: (assets, replacer, space) => {
					let prefixed = {};
					for (let key in assets) {
						if (Object.prototype.hasOwnProperty.call(assets, key)) {
							prefixed['/assets/js/' + key] = '/assets/js/' + assets[key];
						}
					}
					return JSON.stringify(prefixed, replacer, space);
				}
			}
		})))
		.pipe(gulpIf(ENV == 'production', gulp.dest('.tmp/gulp')));
};

const copy = gulp.parallel(
	copyJQuery,
	copyNetteForms
);

// ===============================================================
// BUILD
// ===============================================================
const build = gulp.series(
	clean,
	gulp.parallel(
		gulp.series(
			gulp.parallel(css, js, copy),
			revManifest
		),
		img,
		icons,
		favicon
	)
);

// ===============================================================
// DEVELOPMENT TASKS
// ===============================================================
const watchFiles = () => new Promise((resolve, reject) => {
	gulp.watch(SRC + '/css/**/*.css', css);
	gulp.watch(SRC + '/js/**/*.js', js);
	gulp.watch([SRC + '/img/**/*.{webp,jpg,png,svg}', '!'+ SRC +'/img/icons**/*.svg'], img);
	gulp.watch(SRC + '/img/icons/**/*.svg', icons);
	resolve();
});

const defaultTasks = gulp.series(
	setEnvDevelopment,
	clean,
	gulp.parallel(css, js, img, icons, favicon, copy),
	revManifest,
	watchFiles
);

// ===============================================================
// EXPORT TASKS FOR CLI
// ===============================================================
export {
	build,
	defaultTasks as default
};
