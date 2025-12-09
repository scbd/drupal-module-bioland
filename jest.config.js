/** @type {import('jest').Config} */
module.exports = {
	testEnvironment: 'node',
	collectCoverage: true,
	collectCoverageFrom: ['js/**/*.js', '!**/node_modules/**'],
};
