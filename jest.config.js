/** @type {import('jest').Config} */
module.exports = {
	testEnvironment: 'jsdom',
	collectCoverage: true,
	collectCoverageFrom: [
		'js/**/*.js',
		'!**/node_modules/**',
		'!**/vendor/**',
		'!**/.claude/**',
		'!**/coverage/**',
		'!**/.git/**',
		'!js/**/*.test.js',
	],
	testMatch: ['**/js/**/*.test.js'],
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/.claude/',
		'/coverage/',
		'/web/',
		'/\.git/',
	],
	setupFilesAfterEnv: ['<rootDir>/tests/stubs/jest.setup.js'],
};
