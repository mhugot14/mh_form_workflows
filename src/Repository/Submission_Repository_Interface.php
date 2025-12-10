<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Repository;

interface Submission_Repository_Interface {
	public function create( array $data ): int;
	public function get_by_id( int $id ): ?array;
}