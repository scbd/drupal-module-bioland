/** @type {import('jest').Config} */
module.exports = {
	testEnvironment: 'jsdom',
	collectCoverage: true,
	collectCoverageFrom: ['js/**/*.js', '!**/node_modules/**', '!js/**/*.test.js'],
	testMatch: ['**/js/**/*.test.js'],
	setupFilesAfterEnv: ['<rootDir>/tests/stubs/jest.setup.js'],
};
