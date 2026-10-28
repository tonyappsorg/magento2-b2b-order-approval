<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\Config as B2bConfig;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

class OrderApprovalPolicy
{
    private const XML_PATH_ENABLED = 'tonyapps_b2b_order_approval/general/enabled';
    private const XML_PATH_MINIMUM_SUBTOTAL = 'tonyapps_b2b_order_approval/general/minimum_subtotal';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyRepositoryInterface $companies,
        private readonly B2bConfig $b2bConfig,
        private readonly CompanyResource $companyResource
    ) {
    }

    public function requiresApproval(Quote $quote): bool
    {
        if (!$this->b2bConfig->isEnabled((int)$quote->getStoreId())
            || !$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $quote->getStoreId())
            || !$quote->getCustomerId()
        ) {
            return false;
        }

        $companyId = $this->membership->getCompanyId((int)$quote->getCustomerId());
        if ($companyId === null) {
            return false;
        }

        try {
            $company = $this->companies->getById($companyId);
        } catch (\Exception) {
            return false;
        }
        if (!$this->companyResource->isActivePath($companyId)) {
            return false;
        }

        if ((int)$company->getAdminCustomerId() === (int)$quote->getCustomerId()) {
            return false;
        }

        $minimum = (float)$this->scopeConfig->getValue(
            self::XML_PATH_MINIMUM_SUBTOTAL,
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );

        return (float)$quote->getBaseSubtotal() >= max(0.0, $minimum);
    }

    public function getCompanyId(Quote $quote): ?int
    {
        $customerId = (int)$quote->getCustomerId();
        return $customerId > 0 ? $this->membership->getCompanyId($customerId) : null;
    }
}
