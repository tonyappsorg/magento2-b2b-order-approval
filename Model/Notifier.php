<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

class Notifier
{
    public function __construct(
        private readonly ApprovalRequest $requests,
        private readonly CompanyRepositoryInterface $companies,
        private readonly CustomerRepositoryInterface $customers,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function requestSubmitted(int $requestId): void
    {
        $row = $this->requests->getById($requestId);
        if (!$row) {
            return;
        }
        try {
            $company = $this->companies->getById((int)$row['company_id']);
            $recipient = $this->customers->getById((int)$company->getAdminCustomerId());
            $this->send(
                'tonyapps_b2borderapproval_request',
                $row,
                (string)$recipient->getEmail(),
                [
                    'company_name' => $this->escaper->escapeHtml((string)$company->getName()),
                    'buyer_name' => $this->escaper->escapeHtml((string)$row['requester_name']),
                    'request_id' => (int)$row['entity_id'],
                    'approval_url' => $this->storeManager->getStore((int)$row['store_id'])->getUrl('b2borderapproval/approval/index'),
                ]
            );
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    public function approved(int $requestId): void
    {
        try {
            $row = $this->requests->getById($requestId);
            $this->notifyBuyer($requestId, 'tonyapps_b2borderapproval_approved', [
                'checkout_url' => $this->storeManager->getStore((int)($row['store_id'] ?? 0))->getUrl('checkout/cart'),
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    public function rejected(int $requestId): void
    {
        try {
            $row = $this->requests->getById($requestId);
            $this->notifyBuyer($requestId, 'tonyapps_b2borderapproval_rejected', [
                'checkout_url' => $this->storeManager->getStore((int)($row['store_id'] ?? 0))->getUrl('checkout/cart'),
                'comment' => $this->escaper->escapeHtml((string)($row['comment'] ?? '')),
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $extra */
    private function notifyBuyer(int $requestId, string $template, array $extra): void
    {
        $row = $this->requests->getById($requestId);
        if (!$row) {
            return;
        }
        try {
            $this->send($template, $row, (string)$row['requester_email'], $extra + [
                'buyer_name' => $this->escaper->escapeHtml((string)$row['requester_name']),
                'request_id' => (int)$row['entity_id'],
            ]);
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $row
     *  @param array<string, mixed> $vars
     */
    private function send(string $template, array $row, string $recipient, array $vars): void
    {
        if ($recipient === '') {
            return;
        }
        $storeId = (int)$row['store_id'];
        $transport = $this->transportBuilder
            ->setTemplateIdentifier($template)
            ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($vars)
            ->setFromByScope('general', $storeId)
            ->addTo($recipient)
            ->getTransport();
        $transport->sendMessage();
    }
}
