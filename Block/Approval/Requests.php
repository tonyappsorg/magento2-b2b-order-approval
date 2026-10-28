<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Block\Approval;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class Requests extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CompanyContext $companyContext,
        private readonly ApprovalRequest $approvalRequest,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRequests(): array
    {
        $company = $this->companyContext->getCompany();
        return $company ? $this->approvalRequest->getForCompany((int)$company->getId()) : [];
    }

    public function getApproveUrl(): string
    {
        return $this->getUrl('b2borderapproval/approval/approve');
    }

    public function getRejectUrl(): string
    {
        return $this->getUrl('b2borderapproval/approval/reject');
    }

    /** @param array<string, mixed> $row
     *  @return array<int, array<string, mixed>>
     */
    public function getItems(array $row): array
    {
        try {
            $snapshot = $this->json->unserialize((string)($row['cart_snapshot'] ?? ''));
            return is_array($snapshot) && is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        } catch (\InvalidArgumentException) {
            return [];
        }
    }
}
