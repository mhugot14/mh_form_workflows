<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

abstract class Abstract_Form implements Form_Interface {

	protected array $errors = [];
	protected array $data   = [];

	public function get_errors(): array {
		return $this->errors;
	}

	public function get_data(): array {
		return $this->data;
	}

	protected function add_error( string $field, string $message ): void {
		$this->errors[ $field ] = $message;
	}

	/**
	 * Helfer: Text bereinigen.
	 */
	protected function sanitize_text( mixed $input ): string {
		return sanitize_text_field( (string) $input );
	}
}