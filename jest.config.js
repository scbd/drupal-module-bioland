/** @type {import('jest').Config} */
module.exports = {
	testEnvironment: 'jsdom',
	collectCoverage: true,
	collectCoverageFrom: [
		'js/**/*.js',
		'!**/node_modules/**',
		'!**/vendor/**',
		'!**/.claude/**',
		'!**/.agents/**',
		'!**/coverage/**',
		'!**/.git/**',
		'!js/**/*.test.js',
	],
	testMatch: ['<rootDir>/js/**/*.test.js'],
	testPathIgnorePatterns: [
		'/node_modules/',
		'/vendor/',
		'/.claude/',
		'/\.agents/',
		'/coverage/',
		'/web/',
		'/\.git/',
	],
	setupFilesAfterEnv: ['<rootDir>/tests/stubs/jest.setup.js'],
};
