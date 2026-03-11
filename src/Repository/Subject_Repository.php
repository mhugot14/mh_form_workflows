<?php
declare(strict_types=1);
namespace Mh\FormWorkflows\Repository;

class Subject_Repository {
    public function __construct(private \wpdb $db) {}

    public function get_all_subjects(): array {
        $table = $this->db->prefix . 'wa_subjects';
        return $this->db->get_results("SELECT short_name, display_name FROM $table WHERE is_active = 1 ORDER BY short_name ASC", ARRAY_A);
    }
}