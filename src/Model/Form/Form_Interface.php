<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

interface Form_Interface {
	public function get_slug(): string;
	public function validate( array $data ): bool;
	public function get_errors(): array;
	public function get_data(): array;
}