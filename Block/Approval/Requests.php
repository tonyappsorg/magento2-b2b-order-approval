<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Block\Approval;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class Requests extends Template
{
    private const PAGE_SIZE = 50;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $rows = null;

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
        return array_slice($this->loadPage(), 0, self::PAGE_SIZE);
    }

    public function getPreviousPageUrl(): ?string
    {
        return $this->getPage() > 1 ? $this->getUrl('*/*/*', ['_query' => ['p' => $this->getPage() - 1]]) : null;
    }

    public function getNextPageUrl(): ?string
    {
        return count($this->loadPage()) > self::PAGE_SIZE
            ? $this->getUrl('*/*/*', ['_query' => ['p' => $this->getPage() + 1]])
            : null;
    }

    /** @return array<int, array<string, mixed>> one row more than a page, to know whether another page exists */
    private function loadPage(): array
    {
        if ($this->rows === null) {
            $company = $this->companyContext->getCompany();
            $this->rows = $company
                ? $this->approvalRequest->getForCompany(
                    (int)$company->getId(),
                    self::PAGE_SIZE + 1,
                    ($this->getPage() - 1) * self::PAGE_SIZE
                )
                : [];
        }
        return $this->rows;
    }

    private function getPage(): int
    {
        return max(1, (int)$this->getRequest()->getParam('p', 1));
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
