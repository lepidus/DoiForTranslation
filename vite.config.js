import {resolve} from 'path';
import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import i18nExtractKeys from './i18nExtractKeys.vite.js';

export default defineConfig({
	plugins: [i18nExtractKeys(), vue()],
	publicDir: false,
	build: {
		lib: {
			entry: resolve(__dirname, 'resources/js/main.js'),
			fileName: 'build',
			formats: ['iife'],
			name: 'DoiForTranslationPlugin',
		},
		outDir: resolve(__dirname, 'public/build'),
		rollupOptions: {
			external: ['vue'],
			output: {
				globals: {
					vue: 'pkp.modules.vue',
				},
			},
		},
		target: 'es2016',
	},
});
