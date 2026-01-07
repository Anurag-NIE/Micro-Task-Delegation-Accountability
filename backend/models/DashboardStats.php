<?php

class DashboardStats {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function computeScore(array $row): array {
        $assigned = (int)($row['assigned_count'] ?? 0);
        $completed = (int)($row['completed_count'] ?? 0);
        $expired = (int)($row['expired_count'] ?? 0);
        $onTime = (int)($row['on_time_completed_count'] ?? 0);
        $late = (int)($row['late_completed_count'] ?? 0);
        $accepted = (int)($row['accepted_count'] ?? 0);
        $rejected = (int)($row['rejected_count'] ?? 0);

        $reviewed = $accepted + $rejected;

        // Rates in [0,1]
        $completionRate = $assigned > 0 ? ($completed / $assigned) : 0.0;
        // If there are no reviews yet, treat acceptance as neutral (1.0)
        $acceptanceRate = $reviewed > 0 ? ($accepted / $reviewed) : 1.0;
        // If nothing completed yet, treat timeliness as neutral (1.0)
        $onTimeRate = $completed > 0 ? ($onTime / $completed) : 1.0;

        // Simple, defensible scoring (0..100):
        // - 50% based on completion rate
        // - 30% based on manager acceptance rate
        // - 20% based on on-time completion rate
        $score = (50.0 * $completionRate) + (30.0 * $acceptanceRate) + (20.0 * $onTimeRate);
        $score = (int)round(max(0.0, min(100.0, $score)));

        $label = 'Needs Improvement';
        if ($score >= 90) {
            $label = 'Excellent';
        } elseif ($score >= 75) {
            $label = 'Good';
        } elseif ($score >= 60) {
            $label = 'Fair';
        }

        return [
            'completion_rate' => $completionRate,
            'acceptance_rate' => $acceptanceRate,
            'on_time_rate' => $onTimeRate,
            'reviewed_count' => $reviewed,
            'score' => $score,
            'label' => $label,
            // convenience
            'assigned_count' => $assigned,
            'completed_count' => $completed,
            'expired_count' => $expired,
            'on_time_completed_count' => $onTime,
            'late_completed_count' => $late,
            'accepted_count' => $accepted,
            'rejected_count' => $rejected,
        ];
    }

    public function getMemberStats(): array {
        $query = "SELECT
                    u.user_id,
                    u.name,
                    u.role,
                    COUNT(a.id) as assigned_count,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN a.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
                    SUM(CASE WHEN a.status = 'completed' AND a.completed_at IS NOT NULL AND t.expiry_at IS NOT NULL AND a.completed_at <= t.expiry_at THEN 1 ELSE 0 END) as on_time_completed_count,
                    SUM(CASE WHEN a.status = 'completed' AND a.completed_at IS NOT NULL AND t.expiry_at IS NOT NULL AND a.completed_at > t.expiry_at THEN 1 ELSE 0 END) as late_completed_count,
                    SUM(CASE WHEN r.decision = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    SUM(CASE WHEN r.decision = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                  FROM users u
                  LEFT JOIN task_assignments a ON u.user_id = a.user_id
                  LEFT JOIN tasks t ON a.task_id = t.task_id
                  LEFT JOIN task_reviews r ON a.id = r.assignment_id
                  WHERE u.role = 'member'
                  GROUP BY u.user_id, u.name, u.role
                  ORDER BY u.name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $computed = $this->computeScore($row);
            $result[] = array_merge($row, $computed);
        }
        return $result;
    }

    public function getPendingReviewsCount(): int {
        $query = "SELECT COUNT(*)
                  FROM task_assignments a
                  LEFT JOIN task_reviews r ON a.id = r.assignment_id
                  WHERE a.status = 'completed' AND r.review_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function getOverview(): array {
        $members = $this->getMemberStats();

        $totals = [
            'member_count' => count($members),
            'members_with_assignments' => 0,
            'assigned_count' => 0,
            'completed_count' => 0,
            'expired_count' => 0,
            'on_time_completed_count' => 0,
            'late_completed_count' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0,
            'reviewed_count' => 0,
            'pending_reviews_count' => $this->getPendingReviewsCount(),
            'avg_score_all_members' => 0,
            'avg_score_active_members' => 0,
        ];

        $sumScoreAll = 0;
        $sumScoreActive = 0;

        foreach ($members as $m) {
            $assigned = (int)$m['assigned_count'];
            if ($assigned > 0) {
                $totals['members_with_assignments']++;
                $sumScoreActive += (int)$m['score'];
            }

            $totals['assigned_count'] += $assigned;
            $totals['completed_count'] += (int)$m['completed_count'];
            $totals['expired_count'] += (int)$m['expired_count'];
            $totals['on_time_completed_count'] += (int)$m['on_time_completed_count'];
            $totals['late_completed_count'] += (int)$m['late_completed_count'];
            $totals['accepted_count'] += (int)$m['accepted_count'];
            $totals['rejected_count'] += (int)$m['rejected_count'];
            $totals['reviewed_count'] += (int)$m['reviewed_count'];

            $sumScoreAll += (int)$m['score'];
        }

        $totals['avg_score_all_members'] = $totals['member_count'] > 0
            ? (int)round($sumScoreAll / $totals['member_count'])
            : 0;

        $totals['avg_score_active_members'] = $totals['members_with_assignments'] > 0
            ? (int)round($sumScoreActive / $totals['members_with_assignments'])
            : 0;

        return [
            'overview' => $totals,
            'members' => $members,
        ];
    }
}
