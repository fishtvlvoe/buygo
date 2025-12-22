/** @type {import('tailwindcss').Config} */
export default {
	content: [
		"./index.html",
		"./src/**/*.{vue,js,ts,jsx,tsx}",
	],
	theme: {
		extend: {
			colors: {
				primary: '#2563eb', // Fluent-like blue
				secondary: '#64748b',
			}
		},
	},
	plugins: [],
}
