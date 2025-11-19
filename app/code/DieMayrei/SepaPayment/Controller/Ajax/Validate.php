<?php

declare(strict_types=1);

namespace DieMayrei\SepaPayment\Controller\Ajax;

use DieMayrei\SepaPayment\Model\Validator\IbanValidator;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

class Validate extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly IbanValidator $ibanValidator,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LoggerInterface $logger,
        private readonly JsonSerializer $jsonSerializer
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $request = $this->getRequest();

        $requestData = $this->getRequestData();
        if (!empty($requestData['form_key'])) {
            $request->setParam('form_key', $requestData['form_key']);
        }

        if (!$this->formKeyValidator->validate($request)) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.'),
            ]);
        }

        $iban = $this->normalizeIban((string)($requestData['iban'] ?? ''));
        if ($iban === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('Bitte geben Sie eine IBAN ein.'),
            ]);
        }

        try {
            $validation = $this->ibanValidator->validate($iban);
            return $result->setData([
                'success' => true,
                'data' => $validation,
            ]);
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('Unexpected IBAN validation error.', ['exception' => $throwable]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('Die IBAN konnte nicht geprüft werden. Bitte versuchen Sie es erneut.'),
            ]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(): array
    {
        $content = (string)$this->getRequest()->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $data = $this->jsonSerializer->unserialize($content);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning('Invalid JSON payload received for IBAN validation.');
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }
}
