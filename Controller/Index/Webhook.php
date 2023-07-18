<?php

namespace Vindi\Payment\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Vindi\Payment\Helper\Api;
use Vindi\Payment\Helper\Data;
use Vindi\Payment\Helper\WebhookHandler;

/**
 * Class Webhook
 * @package Vindi\Payment\Controller\Index
 */
class Webhook
{

    private $webhookHandler;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Data
     */
    private $helperData;
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

    /**
     * Webhook constructor.
     * @param Api $api
     * @param LoggerInterface $logger
     * @param WebhookHandler $webhookHandler
     * @param Data $helperData
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        LoggerInterface $logger,
        WebhookHandler $webhookHandler,
        Data $helperData,
        \Magento\Framework\App\Request\Http $request
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->webhookHandler = $webhookHandler;
        $this->helperData = $helperData;
    }

    /**
     * The route that webhooks will use.
     * 
     * @return bool
     */
    public function execute()
    {
        try {
            $body = file_get_contents('php://input');
            $this->logger->info(__(sprintf("Webhook New Event!\n%s", $body)));

            if (!$this->validateRequest()) {
                $ip = $this->webhookHandler->getRemoteIp();
                $this->logger->error(__(sprintf('Invalid webhook attempt from IP %s', $ip)));
                return false;
            }

            $this->webhookHandler->handle($body);
        } catch (\Throwable $th) {
            $this->logger->error(__(sprintf('Error on webhook: %s', $th->getMessage())));
            return false;
        }

        return true;
    }

    /**
     * Validate the webhook for security reasons.
     *
     * @return bool
     */
    private function validateRequest()
    {
        $systemKey = $this->helperData->getWebhookKey();
        $requestKey = $this->request->getParam('key');

        return $systemKey === $requestKey;
    }
}
