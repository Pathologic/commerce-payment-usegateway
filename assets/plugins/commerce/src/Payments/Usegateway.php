<?php

namespace Commerce\Payments;

class Usegateway extends Payment
{
    protected $debug = false;

    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('usegateway');
        $this->debug = $this->getSetting('debug') == '1';
    }

    public function getMarkup()
    {
        if (empty($this->getSetting('secret_key'))) {
            return '<span class="error" style="color: red;">' . $this->lang['usegateway.error_empty_params'] . '</span>';
        }
    }

    public function getPaymentLink()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $payment   = $this->createPayment($order['id'], $order['amount']);
        $data = [
            'name' => $this->lang['usegateway.order_description'] . ' ' . $order['id'],
            'description' => $this->lang['usegateway.order_description'] . ' ' . $order['id'],
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount' => $payment['amount'],
                'currency' => $order['currency'],
            ],
            'metadata' => [
                'order_id' => $order['id'],
                'payment_id' => $payment['id'],
            ],
            'cancel_url' => MODX_SITE_URL . 'commerce/usegateway/payment-failed',
            'redirect_url' => MODX_SITE_URL . 'commerce/usegateway/payment-success',
        ];
        try {
            $response = $this->request('payments', $data);

            return $response['hosted_url'];
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->modx->logEvent(0, 3,
                    'Request failed: <pre>' . print_r($data, true) . '</pre><pre>' . print_r($e->getMessage() . ' ' . $e->getCode(), true) . '</pre>', 'Commerce Usegateway Payment');
            }
        }

        return false;
    }

    public function handleCallback()
    {
        $input = file_get_contents('php://input');
        $response = json_decode($input, true) ?? [];
        $secret = $this->getSetting('secret_key');
        $headers = getallheaders();
        if ($this->debug) {
            $this->modx->logEvent(0, 3, 'Callback start <pre>' . print_r($response, true) . '</pre><pre>' . print_r($response, true) . '</pre>', 'Commerce Usegateway Payment Callback');
        }
        if(isset($headers['Svix-Id']) && isset($headers['Svix-Timestamp']) && isset($headers['Svix-Signature']) && isset($response['event']) && $response['event'] == 'payment.completed' && isset($response['metadata']['order_id']) && isset($response['metadata']['payment_id'])) {
            $processor = $this->modx->commerce->loadProcessor();
            try {
                $payment = $processor->loadPayment($response['metadata']['payment_id']);

                if (!$payment || $payment['order_id'] != $response['metadata']['order_id']) {
                    throw new Exception('Payment "' . htmlentities(print_r($response['metadata']['payment_id'], true)) . '" . not found!');
                }

                return $processor->processPayment($payment['id'], $payment['amount']);
            } catch (Exception $e) {
                if ($this->debug) {
                    $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Usegateway Payment Callback');

                    return false;
                }
            }
        }

        return false;
    }

    protected function request($method, array $data)
    {
        $curl = curl_init();
        $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secret = $this->getSetting('secret_key');
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.usegateway.net/v1/' . $method . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $secret
            ],
        ]);
        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $response = json_decode($response, true) ?? [];
        if ($httpcode !== 201 || empty($response)) {
            throw new \Exception('Request failed with ' . $httpcode . ': <pre>' . print_r($data, true) . '</pre>', $httpcode);
        }

        return $response;
    }
}
