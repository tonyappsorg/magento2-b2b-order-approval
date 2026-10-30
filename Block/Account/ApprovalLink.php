<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Block\Account;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/** Account navigation link, shown only to the company administrator who reviews requests. */
class ApprovalLink extends Current
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly CompanyContext $companyContext,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    public function toHtml()
    {
        $company = $this->companyContext->isAllowed(Permissions::PROFILE_VIEW) ? $this->companyContext->getCompany() : null;
        return $company && (int)$company->getAdminCustomerId() === (int)$this->companyContext->getCustomerId()
            ? parent::toHtml()
            : '';
    }
}
