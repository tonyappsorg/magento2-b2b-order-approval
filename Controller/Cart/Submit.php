<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\Notifier;

class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly ApprovalService $approvalService,
        private readonly Notifier $notifier,
        private readonly ResultFactory $resultFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addErrorMessage(__('Sign in before requesting order approval.'));
            return $redirect->setPath('customer/account/login');
        }

        try {
            $requestId = $this->approvalService->submit(
                $this->checkoutSession->getQuote(),
                (int)$this->customerSession->getCustomerId()
            );
            $this->notifier->requestSubmitted($requestId);
            $this->messageManager->addSuccessMessage(__('Order approval request #%1 was sent to your company administrator.', $requestId));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Could not submit this cart for approval. Please try again.'));
        }
        return $redirect->setPath('checkout/cart');
    }
}
