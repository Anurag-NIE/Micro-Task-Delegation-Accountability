<?php
require_once __DIR__ . '/../models/DashboardStats.php';

class DashboardController {
    private $stats;

    public function __construct($db) {
        $this->stats = new DashboardStats($db);
    }

    public function getDashboardData(): array {
        return $this->stats->getOverview();
    }
}
