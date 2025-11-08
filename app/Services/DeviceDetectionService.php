<?php

namespace App\Services;

use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class DeviceDetectionService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Parse device information from request
     */
    public function parseDeviceInfo(Request $request): array
    {
        $this->agent->setUserAgent($request->userAgent());
        $this->agent->setHttpHeaders($request->headers->all());

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $this->getDeviceType(),
            'device_name' => $this->getDeviceName(),
            'browser' => $this->agent->browser(),
            'platform' => $this->agent->platform(),
        ];
    }

    /**
     * Get device type (mobile, tablet, desktop)
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'mobile';
        }
        
        if ($this->agent->isTablet()) {
            return 'tablet';
        }
        
        return 'desktop';
    }

    /**
     * Get human-readable device name
     */
    protected function getDeviceName(): string
    {
        $browser = $this->agent->browser();
        $platform = $this->agent->platform();
        
        if ($this->agent->isDesktop()) {
            return "{$browser} on {$platform}";
        }
        
        $device = $this->agent->device() ?: ($this->agent->isMobile() ? 'Mobile' : 'Tablet');
        return "{$browser} on {$device}";
    }
}
