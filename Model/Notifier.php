<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Url;
use Magento\Store\Model\ScopeInterface;
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
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        // Storefront URL builder: the injected UrlInterface is the admin one in admin requests (links get the admin path and a secret key).
        private readonly Url $frontendUrl
    ) {
    }

    public function requestSubmitted(int $requestId): void
    {
        $row = $this->requests->getById($requestId);
        if (!$row) {
            return;
        }
        try {
            $this->send('request', $row);
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    public function approved(int $requestId): void
    {
        try {
            $row = $this->requests->getById($requestId);
            if (!$row) {
                return;
            }
            $this->notifyBuyer($row, 'approved');
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    public function rejected(int $requestId): void
    {
        try {
            $row = $this->requests->getById($requestId);
            if (!$row) {
                return;
            }
            $this->notifyBuyer($row, 'rejected');
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    /**
     * Template variables of an approval email for a request row, as sent; also the Email Designer's
     * real-data preview.
     *
     * @param string $template request, approved or rejected
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function getVariables(string $template, array $row): array
    {
        return $this->compose($template, $row)[0];
    }

    /** @param array<string, mixed> $row */
    private function notifyBuyer(array $row, string $template): void
    {
        try {
            $this->send($template, $row);
        } catch (\Exception $exception) {
            $this->logger->error('B2B order approval notification failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param string $template request, approved or rejected: the email whose template setting is used
     * @param array<string, mixed> $row
     */
    private function send(string $template, array $row): void
    {
        [$vars, $recipient] = $this->compose($template, $row);
        if ($recipient === '') {
            return;
        }
        $storeId = (int)$row['store_id'];
        $transport = $this->transportBuilder
            ->setTemplateIdentifier((string)$this->scopeConfig->getValue(
                'tonyapps_b2b_order_approval/email/' . $template . '_template',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ))
            ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($vars)
            ->setFromByScope('general', $storeId)
            ->addTo($recipient)
            ->getTransport();
        $transport->sendMessage();
    }

    /**
     * The email's variables and recipient: the company administrator for a request, the buyer for a decision.
     *
     * @param array<string, mixed> $row
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function compose(string $template, array $row): array
    {
        if ($template === 'request') {
            $company = $this->companies->getById((int)$row['company_id']);
            $recipient = $this->customers->getById((int)$company->getAdminCustomerId());
            // Email {{trans}} and {{var}} escape values themselves; escaping here doubles it.
            $vars = [
                'admin_name' => trim($recipient->getFirstname() . ' ' . $recipient->getLastname()),
                'company_name' => (string)$company->getName(),
                'buyer_name' => (string)$row['requester_name'],
                'request_id' => (int)$row['entity_id'],
                'approval_url' => $this->frontendUrl->setScope((int)$row['store_id'])
                    ->getUrl('b2borderapproval/approval/index', ['_nosid' => true]),
            ];
            $email = (string)$recipient->getEmail();
        } else {
            $vars = ['checkout_url' => $this->frontendUrl->setScope((int)($row['store_id'] ?? 0))
                ->getUrl('checkout/cart', ['_nosid' => true])];
            if ($template === 'rejected') {
                $vars['comment'] = (string)($row['comment'] ?? '');
            }
            $vars += [
                'buyer_name' => (string)$row['requester_name'],
                'request_id' => (int)$row['entity_id'],
            ];
            $email = (string)$row['requester_email'];
        }
        return [$vars + $this->cartVars($row, (int)$row['store_id']), $email];
    }

    /**
     * The requested cart for the emails' item table: lines from the request's snapshot and its subtotal,
     * in the website's base currency.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function cartVars(array $row, int $storeId): array
    {
        $currency = $this->storeManager->getStore($storeId)->getBaseCurrency();
        $format = static fn($amount): string => (string)$currency->format((float)$amount, [], false);
        $snapshot = json_decode((string)($row['cart_snapshot'] ?? ''), true);
        $items = [];
        foreach (is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [] as $item) {
            $items[] = [
                'name' => (string)($item['name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ''),
                'qty' => (string)(float)($item['qty'] ?? 0),
                // Free gifts and other $0 lines.
                'total' => (float)($item['row_total'] ?? 0) > 0 ? $format($item['row_total']) : (string)__('Free'),
            ];
        }
        return [
            'items' => $items,
            'subtotal' => $format($row['base_subtotal'] ?? 0),
        ];
    }
}
