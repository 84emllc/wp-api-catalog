<?php
/**
 * Baked-in integration test runner.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Cli;

defined( 'ABSPATH' ) || exit;

use WP_CLI;

/**
 * Integration tests that run against the live install. No mocks.
 */
class TestCommand {

	/**
	 * Number of passed assertions.
	 *
	 * @var int
	 */
	private int $passed = 0;

	/**
	 * Failure messages.
	 *
	 * @var array
	 */
	private array $failures = array();

	/**
	 * Run the plugin's integration tests against this install.
	 *
	 * ## EXAMPLES
	 *
	 *     wp 84em api-catalog test
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$this->report();
	}

	/**
	 * Assert a condition, recording the result.
	 *
	 * @param bool   $condition Condition under test.
	 * @param string $message   Description of the assertion.
	 * @return void
	 */
	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			++$this->passed;
			WP_CLI::log( 'PASS: ' . $message );
		} else {
			$this->failures[] = $message;
			WP_CLI::log( 'FAIL: ' . $message );
		}
	}

	/**
	 * Print the summary and exit non-zero on failure.
	 *
	 * @return void
	 */
	private function report(): void {
		if ( array() !== $this->failures ) {
			WP_CLI::error( sprintf( '%d passed, %d failed: %s', $this->passed, count( $this->failures ), implode( '; ', $this->failures ) ) );
		}
		WP_CLI::success( sprintf( '%d passed, 0 failed', $this->passed ) );
	}
}
