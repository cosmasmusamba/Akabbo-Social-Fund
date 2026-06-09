<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — LoanProduct Model
 */
class LoanProduct extends BaseModel
{
    protected string $table = 'loan_products';

    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM loan_products WHERE status='active' ORDER BY name"
        );
    }

    public function getWithLoanCounts(): array
    {
        return $this->db->fetchAll("
            SELECT lp.*,
                   COUNT(l.id)                                                   AS total_loans,
                   SUM(l.status IN ('active','disbursed'))                       AS active_loans,
                   COALESCE(SUM(l.principal_amount),0)                           AS total_disbursed,
                   COALESCE(SUM(CASE WHEN l.status IN ('active','disbursed') THEN l.balance_outstanding ELSE 0 END),0) AS outstanding
            FROM loan_products lp
            LEFT JOIN loans l ON l.loan_product_id = lp.id AND l.deleted_at IS NULL
            GROUP BY lp.id
            ORDER BY lp.name
        ");
    }
}
