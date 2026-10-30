<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;

/**
 * controller_action_predispatch_checkout_index_index: the mini-cart and direct /checkout links
 * bypass the hidden cart button, so send buyers back to the cart to request approval first.
 */
class RedirectCheckoutUntilApproved implements ObserverInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ApprovalService $approvalService,
        private readonly ActionFlag $actionFlag,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $this->checkoutSession->getQuote();
        if ($this->approvalService->getCheckoutState($quote) !== ApprovalService::CHECKOUT_NEEDS_APPROVAL) {
            return;
        }
        $this->messageManager->addNoticeMessage(
            __('This order needs approval from your company administrator before checkout.')
        );
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $observer->getEvent()->getControllerAction()->getResponse()->setRedirect($this->url->getUrl('checkout/cart'));
    }
}
