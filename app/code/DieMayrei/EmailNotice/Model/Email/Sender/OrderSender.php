<?php

namespace DieMayrei\EmailNotice\Model\Email\Sender;

use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;

class OrderSender extends \Magento\Sales\Model\Order\Email\Sender\OrderSender
{
    protected function prepareTemplate(Order $order)
    {
        parent::prepareTemplate($order);

        //Get Payment Method
        $paymentMethod = $order->getPayment()->getMethod();
        //Define email template for each payment method
        switch ($paymentMethod) {
            case 'debitpayment':
                if ($order->getCustomerIsGuest()) {
                    $templateId = 'chach_debitpayment_guest_template';
                } else {
                    $templateId = 'chach_debitpayment_template';
                }
                $transport = [
                    'order' => $order,
                    'order_id' => $order->getId(),
                    'billing' => $order->getBillingAddress(),
                    'payment_html' => $order->getPayment()->getAdditionalInformation(),
                    'store' => $order->getStore(),
                    'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
                    'formattedBillingAddress' => $this->getFormattedBillingAddress($order),
                    'order_data' => [
                        'increment_id' => $order->getIncrementId(),
                        'created_at_formatted' => $order->getCreatedAtFormatted(2)
                    ],
                ];
                $transportObject = new DataObject($transport);

                /**
                 * Event argument `transport` is @deprecated. Use `transportObject` instead.
                 */
                $this->eventManager->dispatch(
                    'email_order_set_template_vars_before',
                    ['sender' => $this, 'transport' => $transportObject, 'transportObject' => $transportObject]
                );
                $this->templateContainer->setTemplateVars($transportObject->getData());
                break;
            // Add cases if you have more payment methods
            default:
                $transport = [
                    'order' => $order,
                    'order_id' => $order->getId(),
                    'billing' => $order->getBillingAddress(),
                    'payment_html' => $this->getPaymentHtml($order),
                    'store' => $order->getStore(),
                    'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
                    'formattedBillingAddress' => $this->getFormattedBillingAddress($order),
                    'order_data' => [
                        'increment_id' => $order->getIncrementId(),
                        'created_at_formatted' => $order->getCreatedAtFormatted(2)
                    ],
                ];

                $transportObject = new DataObject($transport);

                /**
                 * Event argument `transport` is @deprecated. Use `transportObject` instead.
                 */
                $this->eventManager->dispatch(
                    'email_order_set_template_vars_before',
                    ['sender' => $this, 'transport' => $transportObject, 'transportObject' => $transportObject]
                );
                $this->templateContainer->setTemplateVars($transportObject->getData());

                $templateId = $order->getCustomerIsGuest() ?
                    $this->identityContainer->getGuestTemplateId()
                    : $this->identityContainer->getTemplateId();

        }

        $this->templateContainer->setTemplateId($templateId);
    }
}
