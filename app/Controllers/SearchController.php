<?php
namespace App\Controllers;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND - Search Controller
 * Global search across members, loans, and transactions.
 */
class SearchController extends BaseController
{
    private Database $db;
    public function __construct() {
        parent::__construct();
        $this->auth->requireAuth();
        $this->db = Database::getInstance();
    }

    public function search(): void
    {
        $q = $this->getQuery('q', '');
        if (strlen($q) < 2) { $this->jsonSuccess(['members' => [], 'loans' => [], 'transactions' => []]); return; }

        $like = "%{$q}%";

        $members = $this->db->fetchAll("
            SELECT id, member_no, first_name, last_name, phone, status,
                   (SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE member_id=members.id AND status='active') AS total_savings
            FROM members
            WHERE deleted_at IS NULL AND (CONCAT(first_name,' ',last_name) LIKE ? OR member_no LIKE ? OR phone LIKE ?)
            LIMIT 5
        ", [$like,$like,$like]);

        $loans = $this->db->fetchAll("
            SELECT l.id, l.loan_no, l.status, l.principal_amount,
                   CONCAT(m.first_name,' ',m.last_name) AS member_name,
                   lp.name AS product_name
            FROM loans l JOIN members m ON m.id=l.member_id JOIN loan_products lp ON lp.id=l.loan_product_id
            WHERE l.deleted_at IS NULL AND (l.loan_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?)
            LIMIT 5
        ", [$like,$like]);

        $members = array_map(fn($m) => array_merge($m, [
            'full_name'        => trim($m['first_name'].' '.$m['last_name']),
            'initials'         => Format::initials(trim($m['first_name'].' '.$m['last_name'])),
            'total_savings_fmt'=> Format::currency((float)$m['total_savings']),
        ]), $members);

        $this->jsonSuccess(compact('members','loans'));
    }
}
