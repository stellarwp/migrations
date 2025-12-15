<?php

namespace Test_Plugin\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;

class Simple_Migration extends Migration_Abstract {
	public function get_label(): string {
		return 'Simple Migration';
	}

	public function get_description(): string {
		return 'This is a simple migration that runs a single batch.';
	}

	public function get_total_batches(): int {
		return 1;
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		return false;
	}

	public function is_down_done(): bool {
		return false;
	}

	public function up( int $batch ): void {
		// Do nothing.
	}

	public function down( int $batch ): void {
		// Do nothing.
	}
}
