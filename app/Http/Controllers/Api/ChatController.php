<?php
// app/Http/Controllers/Api/ChatController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function chat(Request $req)
    {
        $req->validate(['message'=>'required|string|max:2000']);

        $user = $req->user(); // may be null if guests allowed
        $model = config('services.openai.model', env('OPENAI_MODEL', 'gpt-4o-mini'));
        $apiKey = config('services.openai.key', env('OPENAI_API_KEY'));

        // Save user message
        $userMsg = ChatMessage::create([
            'user_id'=>$user?->id,
            'role'=>'user',
            'content'=>$req->message,
            'model'=>$model,
        ]);

        // Build conversation: fetch last N messages for context (server-side)
        // NOTE: keep messages short enough to fit token limits; trim older ones.
        $recent = ChatMessage::where('user_id', $user?->id)
                  ->orderBy('created_at', 'desc')
                  ->take(10) // latest 10 messages (reverse later)
                  ->get()
                  ->reverse()
                  ->map(function($m){
                      return ['role' => $m->role, 'content' => $m->content];
                  })->values()->all();

        // Prepend a system prompt (optional)
        array_unshift($recent, [
            'role' => 'system',
            'content' => 'You are the CrowdBricks assistant. Answer concisely and help users find projects, funding info, and how to contribute.'
        ]);

        // Prepare request payload (Chat Completions)
        $payload = [
            'model' => $model,
            'messages' => $recent,
            'temperature' => 0.2,
            'max_tokens' => 800,
        ];

        // Call OpenAI
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            // handle errors elegantly (log + user-friendly)
            \Log::error('OpenAI error', ['status'=>$response->status(), 'body'=>$response->body()]);
            return response()->json(['error'=>'AI service unavailable'], 503);
        }

        $data = $response->json();

        // Pull assistant content (supports choices[0].message.content)
        $assistantText = $data['choices'][0]['message']['content'] ?? '';

        // Save assistant reply
        $assistantMsg = ChatMessage::create([
            'user_id'=>$user?->id,
            'role'=>'assistant',
            'content'=>$assistantText,
            'model'=>$model,
            'meta'=>$data,
        ]);

        return response()->json([
            'assistant' => $assistantText,
            'message_id' => $assistantMsg->id,
            'raw' => $data, // optional - remove in production or trim
        ]);
    }
}
