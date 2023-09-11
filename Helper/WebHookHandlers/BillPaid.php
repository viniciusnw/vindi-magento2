<?php

namespace Vindi\Payment\Helper\WebHookHandlers;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Vindi\Payment\Helper\Data;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class BillPaid
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    protected $invoiceRepository;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var OrderInterface
     */
    private $order;
    /**
     * @var Data
     */
    private $helperData;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    /**
     * @var OrderSender
     */
    private $orderSender;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        Order $order,
        InvoiceSender $invoiceSender,
        Data $helperData,
        OrderSender $orderSender
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->order = $order;
        $this->helperData = $helperData;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
    }

    /**
     * Handle 'bill_paid' event.
     * The bill can be related to a subscription or a single payment.
     *
     * @param array $data
     *
     * @return bool
     */
    public function billPaid($data)
    {
        $order = null;
        $isSubscription = false;

        if (
            array_key_exists('subscription', $data['bill'])
            && isset($data['bill']['subscription']['code'])
            && $data['bill']['subscription'] != null
        ) {
            $isSubscription = true;
            $code = explode("/", $data['bill']['subscription']['code'])[0];
            $order = $this->getOrder($code);
        } elseif (isset($data['bill']['code']) && $data['bill']['code'] != null) {
            $code = explode("/", $data['bill']['code'])[0];
            $order = $this->getOrder($code);
        }

        if (!$order && !($order = $this->order->getOrder($data))) {
            $this->logger->error(
                __(sprintf(
                    'There is no cycle %s of signature %d.',
                    $data['bill']['period']['cycle'],
                    $data['bill']['subscription']['id']
                ))
            );

            return false;
        }

        return $this->createInvoice($order, $isSubscription);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param bool $isSubscription
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createInvoice(\Magento\Sales\Model\Order $order, $isSubscription = false)
    {
        if (!$order->getId()) return false;

        $this->logger->info(__(sprintf('Generating invoice for the order %s.', $order->getId())));

        if (!$isSubscription)
            if (!$order->canInvoice()) {
                $this->logger->error(__(sprintf('Impossible to generate invoice for order %s.', $order->getId())));
                return false;
            }

        if ($this->helperData->getCreateInvoiceOnComplete()) {
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            // $invoice->setSendEmail(true);
            $this->invoiceSender->send($invoice, true);
            $this->invoiceRepository->save($invoice);
            $this->logger->info(__('Invoice created with success'));
        }

        if ($isSubscription) $order->addCommentToStatusHistory(
            __('The payment was confirmed.')->getText(),
            $this->helperData->getStatusToSubOrderComplete()
        );
        else $order->addCommentToStatusHistory(
            __('The payment was confirmed.')->getText(),
            $this->helperData->getStatusToOrderComplete()
        );
        
        if (!$this->helperData->getCreateInvoiceOnComplete()) $this->orderSender->send($order);
        return $this->orderRepository->save($order);
    }

    /**
     * @param $incrementId
     * @return bool|OrderInterface
     */
    private function getOrder($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();

        $orderList = $this->orderRepository
            ->getList($searchCriteria)
            ->getItems();

        try {
            return reset($orderList);
        } catch (\Throwable $e) {
            $this->logger->error(__('Order #%1 not found', $incrementId));
            $this->logger->error($e->getMessage());
        }

        return false;
    }
}
