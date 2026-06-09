<?php
namespace App\Controllers;

use App\Models\Approval;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Fund Transfer Controller
 * Member-to-member savings transfers with mandatory approval workflow.
 */
class FundTransferController extends BaseController
{
    private Approval $approval;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->approval = new Approval();
        $this->db       = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('transfers.view');
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $status = $this->getQuery('status', '');
        $search = $this->getQuery('search', '');

        $where  = ['1=1'];
        $params = [];

        if ($status) { $where[] = 'ft.status=?'; $params[] = $status; }
        if ($search) {
            $like   = "%{$search}%";
            $where[]= "(ft.transfer_ref LIKE ? OR CONCAT(mf.first_name,' ',mf.last_name) LIKE ?
                        OR CONCAT(mt.first_name,' ',mt.last_name) LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }

        $ws     = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $total  = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM fund_transfers ft
             JOIN members mf ON mf.id=ft.from_member_id
             JOIN members mt ON mt.id=ft.to_member_id $ws", $params
        );

        $transfers = $this->db->fetchAll("
            SELECT ft.*,
                   CONCAT(mf.first_name,' ',mf.last_name) AS from_name, mf.member_no AS from_no,
                   CONCAT(mt.first_name,' ',mt.last_name) AS to_name,   mt.member_no AS to_no,
                   CONCAT(u.first_name,' ',u.last_name)   AS created_by_name
            FROM fund_transfers ft
            JOIN members mf ON mf.id = ft.from_member_id
            JOIN members mt ON mt.id = ft.to_member_id
            JOIN users u    ON u.id  = ft.created_by
            $ws ORDER BY ft.created_at DESC
            LIMIT $limit OFFSET $offset
        ", $params);

        $result   = ['data'=>$transfers,'total'=>$total,'page'=>$page,'per_page'=>$limit,
                     'last_page'=>(int)ceil($total/$limit),'from'=>$total>0?$offset+1:0,'to'=>min($offset+$limit,$total)];
        $stats    = $this->db->fetchOne("
            SELECT SUM(status='pending') AS pending, SUM(status='completed') AS completed,
                   SUM(status='rejected') AS rejected,
                   COALESCE(SUM(CASE WHEN status='completed' THEN amount END),0) AS total_transferred
            FROM fund_transfers
        ");
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Fund Transfers';
        $activePage  = 'transfers';
        $breadcrumbs = ['Transfers' => null];

        $this->view('transfers/index', compact(
            'result','stats','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('transfers.create');
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'New Fund Transfer';
        $activePage  = 'transfers';
        $breadcrumbs = ['Transfers' => APP_URL.'/transfers', 'New' => null];

        $this->view('transfers/create', compact(
            'settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('transfers.create');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['from_member_id','to_member_id','amount','transfer_date','description']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);
        }

        if (empty(trim($data['description']))) {
            $this->jsonError('Transfer description/reason is required.', null, 422);
        }

        $fromId = (int)$data['from_member_id'];
        $toId   = (int)$data['to_member_id'];
        $amount = (float)$data['amount'];

        if ($fromId === $toId) {
            $this->jsonError('Cannot transfer to the same member.', null, 422);
        }
        if ($amount <= 0) {
            $this->jsonError('Transfer amount must be greater than zero.', null, 422);
        }

        // Validate sender has sufficient balance
        $fromAcc = $this->db->fetchOne(
            "SELECT * FROM savings_accounts WHERE member_id=? AND status='active' ORDER BY id LIMIT 1", [$fromId]
        );
        if (!$fromAcc || (float)$fromAcc['balance'] < $amount) {
            $this->jsonError(
                "Insufficient balance. Available: " . Format::currency((float)($fromAcc['balance'] ?? 0)), null, 422
            );
        }

        // Validate receiver exists
        $toMember = $this->db->fetchOne(
            "SELECT id, first_name, last_name FROM members WHERE id=? AND status='active' AND deleted_at IS NULL", [$toId]
        );
        if (!$toMember) {
            $this->jsonError('Recipient member not found or inactive.', null, 404);
        }

        $this->db->beginTransaction();
        try {
            $ref = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $transferId = (int)$this->db->insert("
                INSERT INTO fund_transfers
                    (transfer_ref, from_member_id, to_member_id, amount, description,
                     transfer_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ", [$ref, $fromId, $toId, $amount, $data['description'], $data['transfer_date'], $_SESSION['user_id']]);

            $this->approval->request(
                'transfer', $transferId, $ref, $amount, $_SESSION['user_id'],
                "Transfer of " . Format::currency($amount) . " from member #{$fromId} to member #{$toId}. " . $data['description']
            );

            $this->auth->logAudit($_SESSION['user_id'], 'transfer_requested', 'transfers',
                $transferId, 'FundTransfer',
                "Transfer {$ref}: " . Format::currency($amount) . " from M#{$fromId} to M#{$toId}");

            $this->db->commit();
            $this->jsonSuccess(
                ['transfer_id' => $transferId, 'redirect' => APP_URL.'/transfers'],
                "Transfer {$ref} submitted for approval."
            );

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Transfer failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        $this->auth->requirePermission('transfers.view');

        $transfer = $this->db->fetchOne("
            SELECT ft.*,
                   CONCAT(mf.first_name,' ',mf.last_name) AS from_name, mf.member_no AS from_no, mf.phone AS from_phone,
                   CONCAT(mt.first_name,' ',mt.last_name) AS to_name,   mt.member_no AS to_no, mt.phone AS to_phone,
                   CONCAT(u.first_name,' ',u.last_name)   AS created_by_name,
                   CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name
            FROM fund_transfers ft
            JOIN members mf  ON mf.id  = ft.from_member_id
            JOIN members mt  ON mt.id  = ft.to_member_id
            JOIN users u     ON u.id   = ft.created_by
            LEFT JOIN users ua ON ua.id = ft.approved_by
            WHERE ft.id = ?
        ", [$id]);

        if (!$transfer) { $this->flash('error','Transfer not found.'); $this->redirect('/transfers'); }

        $approval = $this->approval->getForRecord('transfer', $id);
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Transfer ' . $transfer['transfer_ref'];
        $activePage  = 'transfers';
        $breadcrumbs = ['Transfers' => APP_URL.'/transfers', $transfer['transfer_ref'] => null];

        $this->view('transfers/show', compact(
            'transfer','approval','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    /** Reverse a completed transfer (requires approval notes) */
    public function reverse(int $id): void
    {
        $this->auth->requirePermission('approvals.process');
        $this->verifyCsrf();

        $notes = trim($this->getPost()['reversal_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Reversal notes are mandatory.', null, 422);
        }

        $transfer = $this->db->fetchOne("SELECT * FROM fund_transfers WHERE id=?", [$id]);
        if (!$transfer || $transfer['status'] !== 'completed') {
            $this->jsonError('Only completed transfers can be reversed.', null, 400);
        }

        $this->db->execute("
            UPDATE fund_transfers SET status='reversed', reversed_by=?, reversed_at=NOW(),
            reversal_notes=?, updated_at=NOW() WHERE id=?
        ", [$_SESSION['user_id'], $notes, $id]);

        // Log a reversal approval request for audit trail
        $this->approval->request(
            'reversal', $id, $transfer['transfer_ref'],
            (float)$transfer['amount'], $_SESSION['user_id'],
            "Reversal of transfer {$transfer['transfer_ref']}: {$notes}"
        );

        $this->auth->logAudit($_SESSION['user_id'], 'transfer_reversed', 'transfers',
            $id, 'FundTransfer', "Transfer {$transfer['transfer_ref']} reversed: {$notes}");

        $this->jsonSuccess(null, "Transfer {$transfer['transfer_ref']} reversed successfully.");
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