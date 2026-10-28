<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Quote\Model\Quote;

class ApprovalRequest extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_order_approval';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const ORDERED = 'ordered';

    protected function _construct()
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    public function create(Quote $quote, int $companyId, int $customerId, string $cartHash, string $cartSnapshot): int
    {
        $connection = $this->getConnection();
        $pending = $connection->fetchRow(
            $connection->select()->from($this->getMainTable(), ['entity_id', 'cart_hash'])
                ->where('quote_id = ?', (int)$quote->getId())
                ->where('status = ?', self::PENDING)
                ->order('entity_id DESC')->limit(1)
        );
        if ($pending && (string)$pending['cart_hash'] === $cartHash) {
            return (int)$pending['entity_id'];
        }
        if ($pending) {
            $connection->update($this->getMainTable(), ['status' => self::REJECTED, 'comment' => 'Replaced by a newer cart submission.'], ['entity_id = ?' => (int)$pending['entity_id'], 'status = ?' => self::PENDING]);
        }

        $connection->insert($this->getMainTable(), [
            'quote_id' => (int)$quote->getId(),
            'store_id' => (int)$quote->getStoreId(),
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'requester_name' => trim((string)$quote->getCustomerFirstname() . ' ' . (string)$quote->getCustomerLastname()),
            'requester_email' => (string)$quote->getCustomerEmail(),
            'base_subtotal' => (float)$quote->getBaseSubtotal(),
            'cart_hash' => $cartHash,
            'cart_snapshot' => $cartSnapshot,
            'status' => self::PENDING,
        ]);

        return (int)$connection->lastInsertId($this->getMainTable());
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()->from($this->getMainTable())->where('entity_id = ?', $id)
        );
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getForCompany(int $companyId, int $limit = 100): array
    {
        $select = $this->getConnection()->select()->from($this->getMainTable())
            ->where('company_id = ?', $companyId)
            ->where('status IN (?)', [self::PENDING, self::APPROVED, self::REJECTED, self::ORDERED])
            ->order('entity_id DESC')->limit($limit);
        return $this->getConnection()->fetchAll($select);
    }

    public function approve(int $id, int $approverId, string $expectedHash): bool
    {
        return (bool)$this->getConnection()->update(
            $this->getMainTable(),
            ['status' => self::APPROVED, 'approver_customer_id' => $approverId],
            ['entity_id = ?' => $id, 'status = ?' => self::PENDING, 'cart_hash = ?' => $expectedHash]
        );
    }

    public function reject(int $id, int $approverId, string $comment): bool
    {
        return (bool)$this->getConnection()->update(
            $this->getMainTable(),
            ['status' => self::REJECTED, 'approver_customer_id' => $approverId, 'comment' => $comment],
            ['entity_id = ?' => $id, 'status = ?' => self::PENDING]
        );
    }

    public function markOrdered(int $id, int $orderId): void
    {
        $this->getConnection()->update($this->getMainTable(), ['status' => self::ORDERED, 'order_id' => $orderId], ['entity_id = ?' => $id, 'status = ?' => self::APPROVED]);
    }

    public function getApprovedForQuote(int $quoteId): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()->from($this->getMainTable())
                ->where('quote_id = ?', $quoteId)->where('status = ?', self::APPROVED)
                ->order('entity_id DESC')->limit(1)
        );
        return $row ?: null;
    }
}
