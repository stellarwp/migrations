<?php

namespace Test_Plugin\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

class Simple_Migration extends Migration_Abstract {
	public function get_label(): string {
		return 'Simple Migration';
	}

	public function get_description(): string {
		return 'This is a simple migration that runs a single batch.';
	}

	public function get_total_items( ?Operation $operation = null ): int {
		return 1;
	}

	public function get_default_batch_size(): int {
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

	public function up( int $batch, int $batch_size ): void {
		// Do nothing.
	}

	public function down( int $batch, int $batch_size ): void {
		// Do nothing.
	}
}
