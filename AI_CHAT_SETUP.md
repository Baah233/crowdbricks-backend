# 🤖 Crowdbricks AI Chat Assistant - Setup Guide

## Overview
The AI Chat Assistant is now fully functional and integrated into your Crowdbricks platform. Users can ask questions about projects, investments, ROI, and platform features.

## Features
✅ **Smart Fallback Mode** - Works without OpenAI API (uses predefined responses)
✅ **OpenAI Integration** - Connects to GPT-3.5/GPT-4 for intelligent responses
✅ **Context-Aware** - Knows about Crowdbricks platform features
✅ **Authenticated** - Uses user authentication tokens
✅ **Responsive Design** - Works on mobile and desktop
✅ **Real-time Chat** - Instant responses with loading indicators

## Current Status

### Backend Setup ✅
- **Controller**: `InvestorController@aiChat` method created
- **Route**: `POST /api/v1/ai/chat` added
- **Package**: `openai-php/laravel` installed
- **Configuration**: Added to `config/services.php`

### Frontend Setup ✅
- **Component**: `AIChatToggle.jsx` (already implemented)
- **Widget**: `AIChatWidget.jsx` (updated with auth token)
- **Environment**: `VITE_AI_API_URL` configured
- **Integration**: Available on all public pages

## How It Works

### Without OpenAI API (Current State)
The chat works immediately with intelligent fallback responses:
- Questions about investing → Step-by-step guide
- Questions about ROI → Explains where to find ROI info
- Questions about KYC → Verification requirements
- Other questions → Helpful suggestions

### With OpenAI API (Optional)
Connect to GPT-3.5-turbo or GPT-4 for real AI responses.

## Setup OpenAI Integration (Optional)

### Step 1: Get OpenAI API Key
1. Visit [https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Sign up or log in
3. Click "Create new secret key"
4. Copy the key (starts with `sk-proj-...`)

### Step 2: Add to Laravel .env
Open `crowdbricks-backend/.env` and update:

```env
# Find this section and add your key
OPENAI_API_KEY=sk-proj-your-actual-key-here
OPENAI_MODEL=gpt-3.5-turbo  # or gpt-4 for better quality
```

### Step 3: Restart Backend
```bash
# Stop the backend server (Ctrl+C)
# Then restart it
php artisan serve --port=8000
```

### Step 4: Test It
1. Open your frontend: `http://localhost:5173`
2. Click the "Assistant" button (bottom-right blue button)
3. Ask: "How do I invest in a project?"
4. You should get an AI-powered response!

## Configuration Options

### Backend (.env)
```env
# AI Configuration
OPENAI_API_KEY=sk-proj-xxxxxxxx  # Your OpenAI API key
OPENAI_MODEL=gpt-3.5-turbo       # Model to use (gpt-3.5-turbo or gpt-4)
```

### Frontend (.env)
```env
# Already configured
VITE_AI_API_URL=http://127.0.0.1:8000/api/v1/ai/chat
```

## API Endpoint Details

### POST /api/v1/ai/chat
**Authentication**: Required (Bearer token)

**Request Body**:
```json
{
  "messages": [
    {
      "role": "user",
      "text": "How do I invest in a project?"
    }
  ]
}
```

**Response**:
```json
{
  "reply": "To invest in a Crowdbricks project: 1️⃣ Sign up for an account...",
  "message": "To invest in a Crowdbricks project: 1️⃣ Sign up for an account..."
}
```

**Error Response**:
```json
{
  "reply": "⚠️ Sorry — I couldn't connect to the AI service. Please try again later.",
  "error": "AI service unavailable"
}
```

## Testing Without OpenAI

The system works perfectly without OpenAI API! Try these questions:

1. **"How do I invest?"**
   - Gets: Step-by-step investment guide

2. **"What are the returns?"**
   - Gets: Where to find ROI information

3. **"Tell me about KYC"**
   - Gets: Verification requirements

4. **"How does Crowdbricks work?"**
   - Gets: Platform overview

## Cost Estimates (if using OpenAI)

### GPT-3.5-turbo (Recommended)
- **Cost**: $0.50 per 1M input tokens, $1.50 per 1M output tokens
- **Average chat**: ~$0.002 per conversation
- **1000 conversations**: ~$2.00

### GPT-4
- **Cost**: $10 per 1M input tokens, $30 per 1M output tokens
- **Average chat**: ~$0.04 per conversation
- **1000 conversations**: ~$40.00

**Recommendation**: Start with GPT-3.5-turbo, upgrade to GPT-4 only if needed.

## Customization

### System Context
Edit the system prompt in `InvestorController@aiChat` to customize AI behavior:

```php
'content' => 'You are Crowdbricks Assistant, a helpful AI for...'
```

### Fallback Responses
Edit `sendLocalFallback()` in `AIChatWidget.jsx` to add more fallback responses:

```javascript
if (q.includes("your keyword"))
  return "Your custom response";
```

### Max Token Limit
Adjust response length in `InvestorController@aiChat`:

```php
'max_tokens' => 500,  // Increase for longer responses
```

## Troubleshooting

### Issue: "Couldn't connect to AI service"
**Solutions**:
1. Check if backend is running: `http://127.0.0.1:8000/api/v1/health`
2. Verify `VITE_AI_API_URL` in frontend .env
3. Check browser console for errors
4. Verify user is logged in (authToken exists)

### Issue: Fallback responses only
**Cause**: No OpenAI API key configured
**Solution**: This is normal! Add OPENAI_API_KEY to enable real AI

### Issue: "Invalid API key"
**Solutions**:
1. Check API key starts with `sk-proj-` or `sk-`
2. Verify no extra spaces in .env file
3. Restart Laravel server after adding key
4. Check OpenAI account has credits

### Issue: Slow responses
**Solutions**:
1. Switch from GPT-4 to GPT-3.5-turbo (faster)
2. Reduce `max_tokens` limit
3. Check internet connection
4. Monitor OpenAI status: [status.openai.com](https://status.openai.com)

## Production Checklist

Before deploying to production:

- [ ] Add OpenAI API key to production .env
- [ ] Set up billing alerts on OpenAI dashboard
- [ ] Add rate limiting to prevent abuse
- [ ] Monitor chat logs for inappropriate content
- [ ] Set up error tracking (Sentry, etc.)
- [ ] Update CORS settings for production domain
- [ ] Test with actual user questions
- [ ] Add analytics to track usage

## Rate Limiting Recommendations

Add to `routes/api.php`:
```php
Route::post('/ai/chat', [InvestorController::class, 'aiChat'])
    ->middleware('throttle:20,1'); // 20 requests per minute
```

## Analytics & Logging

Current logging (already implemented):
```php
\Log::info('AI Chat interaction', [
    'user_id' => $user->id,
    'message_count' => count($messages),
]);
```

View logs:
```bash
tail -f storage/logs/laravel.log | grep "AI Chat"
```

## Next Steps

1. **Test the current fallback mode** - Works immediately!
2. **Add OpenAI API key** - For real AI responses (optional)
3. **Customize responses** - Edit system context for your needs
4. **Monitor usage** - Check logs and costs
5. **Gather feedback** - Ask users what they think

## Support

Questions? Issues?
- Check Laravel logs: `storage/logs/laravel.log`
- Check browser console: F12 → Console tab
- Test API directly: Use Postman/Insomnia
- OpenAI docs: [platform.openai.com/docs](https://platform.openai.com/docs)

---

**Status**: ✅ Fully functional with fallback mode
**OpenAI Integration**: ⏳ Optional (add API key when ready)
**User Impact**: 🚀 Available to all users immediately!
