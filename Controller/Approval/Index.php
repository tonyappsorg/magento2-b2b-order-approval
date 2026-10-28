<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Controller\Approval;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::PROFILE_VIEW;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if ((int)$company->getAdminCustomerId() !== (int)$this->companyContext->getCustomerId()) {
            $this->messageManager->addErrorMessage(__('Only the company administrator can review order requests.'));
            return $this->redirect('b2b/company/index');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Order Approval Requests'));
        return $page;
    }
}
