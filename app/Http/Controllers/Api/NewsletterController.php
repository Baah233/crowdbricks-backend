<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email address',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;

        // Check if already subscribed
        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            if ($existing->is_active) {
                return response()->json([
                    'message' => 'This email is already subscribed to our newsletter',
                    'status' => 'already_subscribed'
                ], 200);
            } else {
                // Reactivate subscription
                $existing->update([
                    'is_active' => true,
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ]);

                return response()->json([
                    'message' => 'Welcome back! Your subscription has been reactivated',
                    'status' => 'reactivated'
                ], 200);
            }
        }

        // Create new subscription
        NewsletterSubscriber::create([
            'email' => $email,
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Successfully subscribed to our newsletter',
            'status' => 'subscribed'
        ], 201);
    }

    /**
     * Unsubscribe from newsletter
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email address',
                'errors' => $validator->errors()
            ], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$subscriber) {
            return response()->json([
                'message' => 'Email address not found in our subscriber list',
            ], 404);
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Successfully unsubscribed from our newsletter',
        ], 200);
    }
}
