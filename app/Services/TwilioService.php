<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->fromNumber = config('services.twilio.from');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Send SMS message
     *
     * @param string $to Phone number in E.164 format (e.g., +233123456789)
     * @param string $message Message content
     * @return bool Success status
     */
    public function sendSMS($to, $message)
    {
        try {
            if (!$this->client) {
                Log::warning('Twilio not configured. SMS not sent to: ' . $to);
                Log::info('SMS Message (dev mode): ' . $message);
                return false;
            }

            $this->client->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $message
            ]);

            Log::info('SMS sent successfully to: ' . $to);
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send SMS to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send phone verification code
     *
     * @param string $phoneNumber Phone number
     * @param string $code Verification code
     * @return bool Success status
     */
    public function sendVerificationCode($phoneNumber, $code)
    {
        $message = "Your CrowdBricks verification code is: {$code}. This code expires in 15 minutes. Do not share this code with anyone.";
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send notification SMS
     *
     * @param string $phoneNumber Phone number
     * @param string $message Custom message
     * @return bool Success status
     */
    public function sendNotification($phoneNumber, $message)
    {
        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Check if Twilio is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->client !== null;
    }
}
