<?php

namespace App\Services;

use App\Models\Order;
use Exception;
use Fancourier\Fancourier;
use Fancourier\Objects\AwbIntern;
use Fancourier\Request\CreateAwb;
use Illuminate\Support\Facades\Log;

class FanCourierService
{
    protected Fancourier $fan;

    public function __construct()
    {
        // Dynamically switch between the Test Sandbox and Production 
        // based on your Laravel environment (.env APP_ENV variable)
        if (config('app.env') === 'production') {
            $this->fan = new Fancourier(
                config('services.fancourier.client_id'),
                config('services.fancourier.username'),
                config('services.fancourier.password')
            );
        } else {
            // This built-in method automatically points to the test API
            // and injects the 7032158 / clienttest / testing credentials.
            $this->fan = Fancourier::testInstance('');
        }
    }

    /**
     * Generates a Standard Internal AWB for a fully paid e-commerce order.
     *
     * @param Order $order
     * @return string The generated AWB number.
     * @throws Exception
     */
    public function generateAwb(Order $order): string
    {
        try {
            $awb = new AwbIntern();

            $awb->setService('Standard')
                ->setPaymentType('expeditor') // Sender pays since order is already paid via Stripe
                ->setParcels(1)
                ->setWeight(1)
                ->setWidth(10)
                ->setHeight(10)
                ->setLength(10)
                ->setRecipientName($order->customer_name)
                ->setPhone($order->customer_phone)
                ->setCounty($order->shipping_county)
                ->setCity($order->shipping_city)
                ->setStreet($order->shipping_address)
                ->setContents('Comanda MERCADO-NEON #' . $order->invoice_number);

            $request = new CreateAwb();
            $request->addAwb($awb);

            $response = $this->fan->createAwb($request);

            if (!$response->isOk()) {
                throw new Exception("FAN Courier API Error: " . $response->getErrorMessage());
            }

            $data = $response->getData();

            if (empty($data) || !isset($data[0])) {
                throw new Exception("FAN Courier API returned an empty or malformed response.");
            }

            $result = $data[0];

            Log::debug("FAN_COURIER_RESPONSE_DATA", ['result' => $result]);

            if (isset($result['error']) && !empty($result['error'])) {
                throw new Exception("API Validation Error: " . $result['error']);
            }

            // FAN Courier API returns AWB number in different possible keys
            $awbNumber = $result['awbNumber'] ?? $result['lineNumber'] ?? $result['awb'] ?? null;

            if (!$awbNumber) {
                throw new Exception("AWB number could not be parsed from the response. Keys found: " . implode(', ', array_keys($result)));
            }

            Log::info("FAN_COURIER_AWB_CREATED: Order {$order->id} -> AWB: {$awbNumber}");

            return (string) $awbNumber;
        } catch (Exception $e) {
            Log::error("FAN_COURIER_SERVICE_ERROR: " . $e->getMessage(), [
                'order_id' => $order->id,
            ]);

            throw $e;
        }
    }
}
