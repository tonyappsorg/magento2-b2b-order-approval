<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Controller\Approval;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\Notifier;

class Reject implements HttpPostActionInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly CompanyContext $companyContext,
        private readonly ApprovalService $approvalService,
        private readonly Notifier $notifier,
        private readonly \Magento\Framework\App\RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $company = $this->companyContext->getCompany();
        if (!$this->customerSession->isLoggedIn() || !$this->companyContext->isEnabled()
            || !$this->companyContext->isAllowed(Permissions::PROFILE_VIEW)
            || !$company || (int)$company->getAdminCustomerId() !== (int)$this->customerSession->getCustomerId()
        ) {
            $this->messageManager->addErrorMessage(__('Only the company administrator can review order requests.'));
            return $redirect->setPath('customer/account');
        }
        try {
            $requestId = (int)$this->request->getParam('request_id');
            $this->approvalService->reject(
                $requestId,
                (int)$this->customerSession->getCustomerId(),
                (int)$company->getId(),
                (string)$this->request->getParam('comment', '')
            );
            $this->notifier->rejected($requestId);
            $this->messageManager->addSuccessMessage(__('The order request was rejected.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $redirect->setPath('b2borderapproval/approval/index');
    }
}
