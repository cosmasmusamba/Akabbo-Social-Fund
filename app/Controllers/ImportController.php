<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\Format;
use App\Helpers\MemberSequence;
use Database;

/**
 * AKABBO SOCIAL FUND — Import Controller
 * Bulk import members and transactions from CSV/Excel.
 */
class ImportController extends BaseController
{
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('members.create');
        $recentImports = $this->db->fetchAll("
            SELECT al.* FROM audit_logs al
            WHERE al.action LIKE 'import_%'
            ORDER BY al.created_at DESC LIMIT 10
        ");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();
        $pageTitle   = 'Import Data';
        $activePage  = 'import';
        $breadcrumbs = ['Import' => null];
        $this->view('import/index', compact('recentImports','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function downloadTemplate(string $type): void
    {
        $this->auth->requireAuth();
        $templates = [
            'members' => [
                'filename' => 'akabbo-member-import-template.csv',
                'headers'  => ['first_name','last_name','middle_name','gender','date_of_birth','phone','phone_alt','email','national_id','district','address','occupation','employer','membership_date','next_of_kin_name','next_of_kin_phone','next_of_kin_relationship'],
                'sample'   => ['Amara','Nakato','','female','1990-05-15','+256700000001','','amara@example.com','CM90000000XYZW','Kampala','Wandegeya, Kampala','Teacher','Kampala City Council','2024-01-15','John Nakato','+256700000002','Husband'],
            ],
            'transactions' => [
                'filename' => 'akabbo-transaction-import-template.csv',
                'headers'  => ['member_no','txn_type','amount','payment_method','transaction_date','description','external_ref'],
                'sample'   => ['AKB-00001','deposit','50000','cash','2024-01-20','Monthly contribution',''],
            ],
        ];

        if (!isset($templates[$type])) {
            $this->flash('error', 'Unknown template type.');
            $this->redirect('/import');
        }

        $tpl = $templates[$type];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tpl['filename'] . '"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $tpl['headers']);
        fputcsv($out, $tpl['sample']);
        fclose($out);
        exit;
    }

    public function importMembers(): void
    {
        $this->auth->requirePermission('members.create');
        $this->verifyCsrf();

        if (empty($_FILES['csv_file']['name'])) {
            $this->jsonError('Please select a CSV file to import.', null, 422);
        }

        $file = $_FILES['csv_file'];
        if (!in_array($file['type'], ['text/csv','text/plain','application/csv','application/vnd.ms-excel'], true)
            && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            $this->jsonError('Only CSV files are supported.', null, 422);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { $this->jsonError('Could not read the uploaded file.', null, 500); }

        // Skip header row
        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); $this->jsonError('File appears to be empty.', null, 422); }

        $required = ['first_name','last_name','phone','membership_date'];
        $headerMap = array_flip(array_map('trim', $headers));
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                $this->jsonError("Missing required column: {$req}. Download the template for correct format.", null, 422);
            }
        }

        // Pre-read all rows so we know exactly how many member numbers to reserve
        $rows = [];
        while (($rowData = fgetcsv($handle)) !== false) {
            $r = [];
            foreach ($headers as $i => $h) {
                $r[trim($h)] = trim($rowData[$i] ?? '');
            }
            if (!empty($r['first_name']) && !empty($r['last_name']) && !empty($r['phone'])) {
                $rows[] = $r;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            $this->jsonError('No valid data rows found in the file.', null, 422);
        }

        // ── Atomically reserve exactly the member numbers we need ────
        // Filter out rows that will definitely be skipped (duplicate phones)
        $eligibleRows = [];
        foreach ($rows as $r) {
            $phoneExists = $this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE phone=?", [$r['phone']]);
            $nidExists   = !empty($r['national_id'])
                && $this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE national_id=?", [$r['national_id']]);

            if (!$phoneExists && !$nidExists) {
                $eligibleRows[] = $r;
            }
        }

        // Reserve a contiguous block of member numbers in one atomic DB call
        $memberNumbers = !empty($eligibleRows)
            ? MemberSequence::batch(count($eligibleRows))
            : [];

        $inserted = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        $this->db->beginTransaction();
        try {
            $mnIndex = 0;
            foreach ($rows as $r) {
                $row++;

                if (empty($r['first_name']) || empty($r['last_name']) || empty($r['phone'])) {
                    $errors[] = "Row {$row}: Missing required fields — skipped.";
                    $skipped++;
                    continue;
                }

                // Re-check for duplicates inside the transaction
                if ($this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE phone=?", [$r['phone']])) {
                    $errors[] = "Row {$row}: Phone {$r['phone']} already exists — skipped.";
                    $skipped++;
                    continue;
                }

                if (!empty($r['national_id']) && $this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE national_id=?", [$r['national_id']])) {
                    $errors[] = "Row {$row}: National ID {$r['national_id']} already exists — skipped.";
                    $skipped++;
                    continue;
                }

                // Take the next pre-allocated member number
                $memberNo = $memberNumbers[$mnIndex] ?? MemberSequence::nextFormatted();
                $mnIndex++;

                // Safety guard — should never happen due to pre-allocation, but verify uniqueness
                if (!MemberSequence::isUnique($memberNo)) {
                    $memberNo = MemberSequence::nextFormatted(); // grab a fresh one
                }

                $memberId = (int)$this->db->insert("
                    INSERT INTO members
                        (member_no, first_name, last_name, middle_name, gender, date_of_birth,
                         phone, email, national_id, district, address, occupation, employer,
                         membership_date, next_of_kin_name, next_of_kin_phone, next_of_kin_relationship,
                         status, kyc_verified, created_by, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',0,?,NOW(),NOW())
                ", [
                    $memberNo,
                    $r['first_name'], $r['last_name'], $r['middle_name'] ?? null,
                    $r['gender'] ?? null,
                    !empty($r['date_of_birth']) ? $r['date_of_birth'] : null,
                    $r['phone'], $r['email'] ?? null, $r['national_id'] ?? null,
                    $r['district'] ?? null, $r['address'] ?? null,
                    $r['occupation'] ?? null, $r['employer'] ?? null,
                    !empty($r['membership_date']) ? $r['membership_date'] : date('Y-m-d'),
                    $r['next_of_kin_name'] ?? null,
                    $r['next_of_kin_phone'] ?? null,
                    $r['next_of_kin_relationship'] ?? null,
                    $_SESSION['user_id'],
                ]);

                // Auto-create savings account
                $this->db->execute(
                    "INSERT INTO savings_accounts
                         (account_no, member_id, account_type, balance, status, opened_at, created_by, created_at, updated_at)
                     VALUES (?,?,'regular',0,'active',?,?,NOW(),NOW())",
                    [
                        'SAV-' . str_pad($memberId, 6, '0', STR_PAD_LEFT),
                        $memberId,
                        !empty($r['membership_date']) ? $r['membership_date'] : date('Y-m-d'),
                        $_SESSION['user_id'],
                    ]
                );

                $inserted++;
            }

            $this->db->commit();

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Import failed at row ' . $row . ': ' . $e->getMessage(), null, 500);
        }

        $this->auth->logAudit($_SESSION['user_id'], 'import_members', 'members', null, null,
            "{$inserted} members imported, {$skipped} skipped");

        $this->jsonSuccess([
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 20),
        ], "{$inserted} member(s) imported successfully." . ($skipped ? " {$skipped} skipped." : ''));
    }

    private function getSettings(): array
    {
        return array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"), 'value', 'key');
    }
    private function getUnread(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$_SESSION['user_id']]);
    }
}