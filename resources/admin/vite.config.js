import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
	plugins: [vue()],
	base: '',
	build: {
		outDir: '../../assets',
		emptyOutDir: true,
		manifest: true,
		rollupOptions: {
			input: 'src/main.js',
		},
	},
	resolve: {
		alias: {
			'@': path.resolve(__dirname, './src'),
		},
	},
	server: {
		origin: 'http://localhost:5173',
	},
});
