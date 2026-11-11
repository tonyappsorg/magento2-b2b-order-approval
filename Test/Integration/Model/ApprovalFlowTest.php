<?php
declare(strict_types=1);

namespace Tonyapps\B2bOrderApproval\Test\Integration\Model;

use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\CompanyInterfaceFactory;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;
use Tonyapps\B2bOrderApproval\Model\ApprovalService;
use Tonyapps\B2bOrderApproval\Model\ResourceModel\ApprovalRequest;

/**
 * The whole approval cycle on a real quote: submit, approve, place the order through core's
 * QuoteManagement (the plugin's gate) and the cart-change observer.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
 * Class-level @magentoConfigFixture is ignored by this framework version, so setUp() switches approval on.
 */
class ApprovalFlowTest extends TestCase
{
    private const BUYER_ID = 1;
    private const APPROVER_ID = 77;

    private ApprovalService $service;
    private ApprovalRequest $requests;
    private CartRepositoryInterface $cartRepository;
    private CartManagementInterface $cartManagement;
    private const APPROVAL_ENABLED = 'tonyapps_b2b_order_approval/general/enabled';

    private int $companyId;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(MutableScopeConfigInterface::class)->setValue(self::APPROVAL_ENABLED, '1', 'store', 'default');
        $this->service = $objectManager->get(ApprovalService::class);
        $this->requests = $objectManager->get(ApprovalRequest::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->cartManagement = $objectManager->get(CartManagementInterface::class);

        /** @var CompanyInterface $company */
        $company = $objectManager->get(CompanyInterfaceFactory::class)->create();
        $company->setName('Approval Test ' . uniqid())->setStatus(CompanyInterface::STATUS_ACTIVE);
        $this->companyId = (int)$objectManager->get(CompanyRepositoryInterface::class)->save($company)->getId();
        $objectManager->get(CompanyCustomer::class)->assign(self::BUYER_ID, $this->companyId);
    }

    protected function tearDown(): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(self::APPROVAL_ENABLED, '0', 'store', 'default');
    }

    public function testAnApprovedCartPlacesTheOrderAndUsesUpTheApproval(): void
    {
        $quote = $this->loadQuote();
        $requestId = $this->service->submit($quote, self::BUYER_ID);
        self::assertSame($requestId, $this->service->submit($this->loadQuote(), self::BUYER_ID), 'Same cart, same request');
        self::assertSame(ApprovalService::CHECKOUT_NEEDS_APPROVAL, $this->service->getCheckoutState($quote));

        $this->service->approve($requestId, self::APPROVER_ID, $this->companyId);
        $orderId = $this->cartManagement->placeOrder((int)$quote->getId());

        $request = $this->requests->getById($requestId);
        self::assertSame(ApprovalRequest::ORDERED, $request['status']);
        self::assertSame($orderId, (int)$request['order_id']);
        self::assertSame(self::APPROVER_ID, (int)$request['approver_customer_id']);
        $order = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class)->get($orderId);
        self::assertEquals(10.0, (float)$order->getBaseSubtotal());
    }

    public function testACompanyCartCannotBeOrderedWithoutApproval(): void
    {
        $quoteId = (int)$this->loadQuote()->getId();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('needs approval');
        $this->cartManagement->placeOrder($quoteId);
    }

    public function testAPendingRequestIsNotEnough(): void
    {
        $quote = $this->loadQuote();
        $this->service->submit($quote, self::BUYER_ID);

        $this->expectException(LocalizedException::class);
        $this->cartManagement->placeOrder((int)$quote->getId());
    }

    public function testChangingTheCartWithdrawsTheApproval(): void
    {
        $quote = $this->loadQuote();
        $requestId = $this->service->submit($quote, self::BUYER_ID);
        $this->service->approve($requestId, self::APPROVER_ID, $this->companyId);

        $quote = $this->loadQuote();
        $item = $quote->getAllVisibleItems()[0];
        $item->setQty(3);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $request = $this->requests->getById($requestId);
        self::assertSame(ApprovalRequest::REJECTED, $request['status']);
        self::assertSame('Cart changed after approval.', $request['comment']);

        // Back to the approved contents: the approval stays withdrawn.
        $quote = $this->loadQuote();
        $quote->getAllVisibleItems()[0]->setQty(1);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $this->expectException(LocalizedException::class);
        $this->cartManagement->placeOrder((int)$quote->getId());
    }

    public function testAChangeBeforeApprovalRejectsTheRequest(): void
    {
        $quote = $this->loadQuote();
        $requestId = $this->service->submit($quote, self::BUYER_ID);

        $quote = $this->loadQuote();
        $quote->getAllVisibleItems()[0]->setQty(2);
        $quote->collectTotals();
        $this->cartRepository->save($quote);

        try {
            $this->service->approve($requestId, self::APPROVER_ID, $this->companyId);
            self::fail('Approving a changed cart must fail.');
        } catch (LocalizedException $exception) {
            self::assertStringContainsString('cart changed', $exception->getMessage());
        }
        self::assertSame(ApprovalRequest::REJECTED, $this->requests->getById($requestId)['status']);
    }

    public function testAnotherCompanyCannotApprove(): void
    {
        $requestId = $this->service->submit($this->loadQuote(), self::BUYER_ID);

        try {
            $this->service->approve($requestId, self::APPROVER_ID, $this->companyId + 1000);
            self::fail('Another company must not see the request.');
        } catch (LocalizedException) {
        }
        self::assertSame(ApprovalRequest::PENDING, $this->requests->getById($requestId)['status']);
    }

    /**
     * @magentoConfigFixture current_store tonyapps_b2b_order_approval/general/minimum_subtotal 1000
     */
    public function testCartsBelowTheMinimumNeedNoApproval(): void
    {
        $quote = $this->loadQuote();

        self::assertSame(ApprovalService::CHECKOUT_OPEN, $this->service->getCheckoutState($quote));
        self::assertGreaterThan(0, $this->cartManagement->placeOrder((int)$quote->getId()));
        self::assertSame([], $this->requests->getForCompany($this->companyId));
    }

    private function loadQuote(): Quote
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class)->load('test01', 'reserved_order_id');
        $quote = $this->cartRepository->get((int)$quote->getId());
        if ($quote->getIsMultiShipping() || $quote->getShippingAddress()->getShippingMethod() !== 'flatrate_flatrate') {
            $quote->setIsMultiShipping(0);
            $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $quote = $this->cartRepository->get((int)$quote->getId());
        }
        return $quote;
    }
}
