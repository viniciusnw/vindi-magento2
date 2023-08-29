<?php

namespace Vindi\Payment\Plugin;

use Magento\Sales\Model\Order\Payment;
use Vindi\Payment\Helper\Data;
use Vindi\Payment\Model\Payment\BankSlip;
use Vindi\Payment\Model\Payment\Vindi;
use Magento\Sales\Model\Order;

class SetOrderStatusOnPlace
{
    /**
     * @var Data
     */
    private $helperData;

    /**
     * SetOrderStatusOnPlace constructor.
     * @param Data $helperData
     */
    public function __construct(
        Data $helperData
    ) {
        $this->helperData = $helperData;
    }

    /** 
     * Faz com que os status de pagamento dos pedidos
     * sejam atualizados exclusivamente via webhooks da Vindi
     * 
     * @param Payment $subject, mixed $result
     *
     * @return mixed
     */
    public function afterPlace(Payment $subject, $result)
    {
        $this->processingStatus($subject);
        return $result;
    }

    /**
     * @param Payment $subject
     */
    private function processingStatus(Payment $subject)
    {
        $order = $subject->getOrder();
        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addCommentToStatusHistory(__('The payment has not yet been confirmed and the order is being processed.'));
    }
}
