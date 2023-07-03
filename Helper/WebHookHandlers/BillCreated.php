<?php

namespace Vindi\Payment\Helper\WebHookHandlers;

class BillCreated
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle 'bill_created' event.
     * The bill can be related to a subscription or a single payment.
     *
     * @param array $data
     *
     * @return bool
     */
    public function billCreated($data)
    {
        $bill = $data['bill'];

        if (!$bill) {
            $this->logger->error(__('Error while interpreting webhook "bill_created"'));
            return false;
        }

        if (!isset($bill['subscription']) || $bill['subscription'] === null) {
            $this->logger->info(__(sprintf('Ignoring the event "bill_created" for single sell')));
            return false;
        }
    }
}
