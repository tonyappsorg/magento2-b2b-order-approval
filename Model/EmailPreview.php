<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Framework\Exception\LocalizedException;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

/**
 * Real-data preview of the approval emails for Tonyapps_EmailDesigner (registered in etc/di.xml, duck-typed).
 * The variables come from Notifier, the sender itself. Read-only.
 */
class EmailPreview
{
    /** Template => the Notifier's email, and the request statuses that email was sent for. */
    private const TEMPLATES = [
        'tonyapps_b2b_order_approval_email_request_template' => ['request', []],
        'tonyapps_b2b_order_approval_email_approved_template' => [
            'approved', [ApprovalRequest::APPROVED, ApprovalRequest::ORDERED],
        ],
        'tonyapps_b2b_order_approval_email_rejected_template' => ['rejected', [ApprovalRequest::REJECTED]],
    ];

    public function __construct(
        private readonly ApprovalRequest $requests,
        private readonly Notifier $notifier
    ) {
    }

    /** @return array<string, string> request ID => label, newest first */
    public function getRecords(string $templateId, int $limit): array
    {
        $statuses = $this->parse($templateId)[1];
        $rows = $statuses ? $this->fetch($limit, $statuses) : [];
        // No request with a matching status yet: any request still shows the design with real data.
        $rows = $rows ?: $this->fetch($limit, []);

        $records = [];
        foreach ($rows as $row) {
            $records[(string)$row['entity_id']] = implode(' · ', array_filter([
                '#' . $row['entity_id'],
                (string)$row['company_name'],
                (string)$row['requester_name'],
                substr((string)$row['created_at'], 0, 10),
            ]));
        }
        return $records;
    }

    /** @return array<string, mixed> */
    public function getVariables(string $templateId, string $recordId): array
    {
        $template = $this->parse($templateId)[0];
        $row = $this->requests->getById((int)$recordId);
        if (!$row) {
            throw new LocalizedException(__('The approval request could not be found.'));
        }
        return $this->notifier->getVariables($template, $row);
    }

    /** The request's store, which the notifier sends from. */
    public function getStoreId(string $templateId, string $recordId): ?int
    {
        $this->parse($templateId);
        $row = $this->requests->getById((int)$recordId);
        return $row ? (int)$row['store_id'] : null;
    }

    /** @return array{0: string, 1: string[]} */
    private function parse(string $templateId): array
    {
        return self::TEMPLATES[$templateId] ?? throw new LocalizedException(__('No real-data preview for this email.'));
    }

    /**
     * @param string[] $statuses empty for any
     * @return array<int, array<string, mixed>>
     */
    private function fetch(int $limit, array $statuses): array
    {
        $connection = $this->requests->getConnection();
        $select = $connection->select()
            ->from(['r' => $this->requests->getMainTable()], ['entity_id', 'requester_name', 'created_at'])
            ->joinLeft(
                ['c' => $this->requests->getTable('tonyapps_b2b_company')],
                'c.entity_id = r.company_id',
                ['company_name' => 'name']
            )
            ->order('r.entity_id DESC')
            ->limit($limit);
        if ($statuses) {
            $select->where('r.status IN (?)', $statuses);
        }
        return $connection->fetchAll($select);
    }
}
