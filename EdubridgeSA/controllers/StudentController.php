<?php
// Lightweight StudentController compatible with older PHP versions and missing PDO
// Provides application statistics without fatal errors when DB is unavailable

class StudentController {
    private $pdo; // intentionally untyped for wider compatibility

    public function __construct($pdo = null) {
        $this->pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
    }

    // Helper: check if applications table exists
    private function hasApplications(): bool {
        if (!$this->pdo) { return false; }
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'applications'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) { return false; }
    }

    // Helper: discover columns and build expressions
    private function columnMap(): array {
        $map = [
            'id' => 'id',
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'phone' => null,
            'id_number' => null,
            'school' => null,
            'grade' => null,
            'province' => null,
            'status' => null,
            'date_applied' => null,
            'name_expr' => "'-'",
        ];
        if (!$this->pdo || !$this->hasApplications()) { return $map; }

        try {
            $cols = $this->pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
            $cols = array_map('strval', $cols);

            // Basic mappings
            $map['email'] = in_array('email_address', $cols, true) ? '`email_address`' : (in_array('email', $cols, true) ? '`email`' : null);
            $map['phone'] = in_array('cellphone_number', $cols, true) ? '`cellphone_number`' : (in_array('phone', $cols, true) ? '`phone`' : null);
            $map['id_number'] = in_array('id_number', $cols, true) ? '`id_number`' : (in_array('rsa_id', $cols, true) ? '`rsa_id`' : null);
            $map['school'] = in_array('school', $cols, true) ? '`school`' : (in_array('school_university', $cols, true) ? '`school_university`' : null);
            $map['grade'] = in_array('grade', $cols, true) ? '`grade`' : null;
            $map['province'] = in_array('province', $cols, true) ? '`province`' : null;
            $map['status'] = in_array('status', $cols, true) ? '`status`' : (in_array('application_status', $cols, true) ? '`application_status`' : null);
            $map['date_applied'] = in_array('created_at', $cols, true) ? '`created_at`' : (in_array('submitted_at', $cols, true) ? '`submitted_at`' : null);

            // Name expression
            $hasFull  = in_array('full_name', $cols, true);
            $hasSur   = in_array('surname', $cols, true);
            $hasFirst = in_array('first_name', $cols, true);
            $hasLast  = in_array('last_name', $cols, true);
            $hasAppl  = in_array('applicant_name', $cols, true);

            $nameParts = [];
            if ($hasFull && $hasSur) { $nameParts[] = "CONCAT(`full_name`, ' ', `surname`)"; }
            if ($hasFirst && $hasLast) { $nameParts[] = "CONCAT(`first_name`, ' ', `last_name`)"; }
            if ($hasAppl) { $nameParts[] = '`applicant_name`'; }
            if ($hasFull) { $nameParts[] = '`full_name`'; }
            if ($hasFirst) { $nameParts[] = '`first_name`'; }
            if ($hasLast) { $nameParts[] = '`last_name`'; }
            $map['name_expr'] = !empty($nameParts) ? ('COALESCE(' . implode(', ', $nameParts) . ')') : "'-'";

            // Expose first_name/last_name when present
            $map['first_name'] = $hasFirst ? '`first_name`' : null;
            $map['last_name'] = $hasLast ? '`last_name`' : null;

        } catch (Throwable $e) {
            // Leave defaults
        }
        return $map;
    }

    // List students/applications with filters/search/pagination
    public function list(array $filters, string $q, int $page, int $perPage): array {
        if (!$this->pdo || !$this->hasApplications()) { return ['rows' => [], 'total' => 0]; }
        $map = $this->columnMap();
        $selects = [
            '`id` AS id',
            $map['name_expr'] . ' AS name',
        ];
        if ($map['email']) { $selects[] = $map['email'] . ' AS email'; }
        if ($map['phone']) { $selects[] = $map['phone'] . ' AS phone'; }
        if ($map['id_number']) { $selects[] = $map['id_number'] . ' AS id_number'; }
        if ($map['school']) { $selects[] = $map['school'] . ' AS school'; }
        if ($map['grade']) { $selects[] = $map['grade'] . ' AS grade'; }
        if ($map['province']) { $selects[] = $map['province'] . ' AS province'; }
        if ($map['status']) { $selects[] = $map['status'] . ' AS status'; }
        if ($map['date_applied']) { $selects[] = $map['date_applied'] . ' AS date_applied'; }

        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM applications';
        $where = [];
        $params = [];

        // Filters
        if (!empty($filters['status']) && $map['status']) { $where[] = $map['status'] . ' = ?'; $params[] = $filters['status']; }
        if (!empty($filters['grade']) && $map['grade']) { $where[] = $map['grade'] . ' = ?'; $params[] = $filters['grade']; }
        if (!empty($filters['province']) && $map['province']) { $where[] = $map['province'] . ' = ?'; $params[] = $filters['province']; }

        // Search
        if ($q !== '') {
            $likeParts = [];
            if ($map['first_name']) { $likeParts[] = $map['first_name'] . ' LIKE ?'; $params[] = '%' . $q . '%'; }
            if ($map['last_name']) { $likeParts[] = $map['last_name'] . ' LIKE ?'; $params[] = '%' . $q . '%'; }
            if ($map['email']) { $likeParts[] = $map['email'] . ' LIKE ?'; $params[] = '%' . $q . '%'; }
            if ($map['id_number']) { $likeParts[] = $map['id_number'] . ' LIKE ?'; $params[] = '%' . $q . '%'; }
            if (!empty($likeParts)) { $where[] = '(' . implode(' OR ', $likeParts) . ')'; }
        }

        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sqlCount = 'SELECT COUNT(*) FROM applications' . (!empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '');

        // Order by most recent
        if ($map['date_applied']) { $sql .= ' ORDER BY ' . $map['date_applied'] . ' DESC'; } else { $sql .= ' ORDER BY id DESC'; }

        // Pagination
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        try {
            $stmt = $this->pdo->prepare($sqlCount);
            $stmt->execute($params);
            $total = (int)($stmt->fetchColumn() ?: 0);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalize name into first_name/last_name for UI
            foreach ($rows as &$r) {
                if (!isset($r['first_name']) && isset($r['name'])) {
                    $parts = preg_split('/\s+/', trim((string)$r['name']));
                    $r['first_name'] = $parts[0] ?? '';
                    $r['last_name'] = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
                }
            }

            return ['rows' => $rows, 'total' => $total];
        } catch (Throwable $e) {
            return ['rows' => [], 'total' => 0];
        }
    }

    public function get(int $id): ?array {
        if (!$this->pdo || !$this->hasApplications()) { return null; }
        $map = $this->columnMap();
        $selects = ['`id` AS id', $map['name_expr'] . ' AS name'];
        foreach (['email','phone','id_number','school','grade','province','status','date_applied'] as $k) {
            if ($map[$k]) { $selects[] = $map[$k] . ' AS ' . $k; }
        }
        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM applications WHERE id = ? LIMIT 1';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { return null; }
            if (!isset($row['first_name']) && isset($row['name'])) {
                $parts = preg_split('/\s+/', trim((string)$row['name']));
                $row['first_name'] = $parts[0] ?? '';
                $row['last_name'] = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
            }
            return $row;
        } catch (Throwable $e) { return null; }
    }

    public function update(int $id, array $data): bool {
        if (!$this->pdo || !$this->hasApplications()) { return false; }
        $cols = $this->pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
        $set = [];
        $params = [];
        $fields = [
            'first_name','last_name','email','email_address','phone','cellphone_number',
            'id_number','rsa_id','school','school_university','grade','province','status'
        ];
        foreach ($fields as $f) {
            if (in_array($f, $cols, true)) {
                // Map input keys to actual column names
                $keyMap = [
                    'email_address' => 'email',
                    'cellphone_number' => 'phone',
                    'rsa_id' => 'id_number',
                    'school_university' => 'school',
                ];
                $inputKey = $keyMap[$f] ?? $f;
                if (array_key_exists($inputKey, $data)) {
                    $set[] = "`$f` = ?";
                    $params[] = $data[$inputKey];
                }
            }
        }
        if (empty($set)) { return false; }
        $sql = 'UPDATE applications SET ' . implode(', ', $set) . ' WHERE id = ?';
        $params[] = $id;
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $e) { return false; }
    }

    public function delete(int $id): bool {
        if (!$this->pdo || !$this->hasApplications()) { return false; }
        try {
            $stmt = $this->pdo->prepare('DELETE FROM applications WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (Throwable $e) { return false; }
    }

    public function updateStatusBulk(array $ids, string $status): bool {
        if (!$this->pdo || !$this->hasApplications() || empty($ids)) { return false; }
        $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v>0; }));
        if (empty($ids)) { return false; }
        try {
            $stmt = $this->pdo->prepare('UPDATE applications SET status = ?, updated_at = NOW() WHERE id IN (' . implode(',', $ids) . ')');
            return $stmt->execute([$status]);
        } catch (Throwable $e) { return false; }
    }

    public function exportSelectedCSV(array $ids): void {
        if (!$this->pdo || !$this->hasApplications() || empty($ids)) { return; }
        $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v>0; }));
        if (empty($ids)) { return; }
        $map = $this->columnMap();
        $selects = ['`id` AS id', $map['name_expr'] . ' AS name'];
        foreach (['email','phone','id_number','school','grade','province','status','date_applied'] as $k) {
            if ($map[$k]) { $selects[] = $map[$k] . ' AS ' . $k; }
        }
        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM applications WHERE id IN (' . implode(',', $ids) . ') ORDER BY id DESC';
        try {
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $rows = []; }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_export_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); }
        foreach ($rows as $r) { fputcsv($out, $r); }
        fclose($out);
    }

    // Returns an associative array of stats for dashboard cards
    public function stats() {
        $stats = [
            'today' => 0,
            'month' => 0,
            'year' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        if (!$this->pdo || !$this->hasApplications()) {
            return $stats; // limited mode if DB is down
        }

        try {
            // Total applications today
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM applications WHERE DATE(created_at) = CURDATE()");
            $stmt->execute();
            $stats['today'] = (int)($stmt->fetchColumn() ?: 0);

            // This month
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM applications WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
            $stmt->execute();
            $stats['month'] = (int)($stmt->fetchColumn() ?: 0);

            // This year
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM applications WHERE YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute();
            $stats['year'] = (int)($stmt->fetchColumn() ?: 0);

            // Status counts
            $stmt = $this->pdo->query("SELECT status, COUNT(*) AS c FROM applications GROUP BY status");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $key = strtolower($r['status'] ?? '');
                if (isset($stats[$key])) { $stats[$key] = (int)$r['c']; }
            }
        } catch (Throwable $e) {
            // Swallow errors and return whatever we have
        }

        return $stats;
    }
}