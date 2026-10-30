<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Controller\Approval;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\Notifier;

class Approve extends AbstractCompanyAction implements HttpPostActionInterface
{
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly ApprovalService $approvalService,
        private readonly Notifier $notifier
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if ((int)$company->getAdminCustomerId() !== (int)$this->customerSession->getCustomerId()) {
            $this->messageManager->addErrorMessage(__('Only the company administrator can review order requests.'));

            return $this->redirect('customer/account');
        }
        try {
            $row = $this->approvalService->approve(
                (int)$this->request->getParam('request_id'),
                (int)$this->customerSession->getCustomerId(),
                (int)$company->getId()
            );
            $this->notifier->approved((int)$row['entity_id']);
            $this->messageManager->addSuccessMessage(__('Order request #%1 was approved. The buyer can now complete checkout.', $row['entity_id']));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->redirect('b2borderapproval/approval/index');
    }
}
