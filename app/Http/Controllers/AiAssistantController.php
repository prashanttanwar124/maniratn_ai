<?php

namespace App\Http\Controllers;

use App\Models\AiApiKey;
use App\Models\AiChatLog;
use App\Services\GeminiAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function __construct(
        protected GeminiAiService $geminiService
    ) {}

    /**
     * Render the AI Voice Hub, Studio & API Key Management Dashboard
     */
    public function index(): Response
    {
        $apiKeys = AiApiKey::orderByDesc('created_at')->get();
        $totalQueries = AiApiKey::sum('query_count');
        $activeTenants = AiApiKey::where('is_active', true)->count();
        $totalTenants = AiApiKey::count();
        $voiceTenants = AiApiKey::where('voice_enabled', true)->count();
        $totalChatMessages = AiChatLog::count();

        $recentLogs = AiChatLog::with('apiKey:id,key,business_name')
            ->orderByDesc('id')
            ->take(25)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'role' => $log->role,
                'message' => $log->message,
                'store_name' => $log->apiKey?->business_name ?? 'Local Admin Studio',
                'store_url' => $log->store_url ?: 'http://127.0.0.1:8000',
                'api_key' => $log->api_key,
                'actions' => $log->actions,
                'has_audio' => $log->has_audio,
                'created_at' => $log->created_at->diffForHumans(),
                'time_exact' => $log->created_at->format('d M Y, h:i A'),
            ]);

        return Inertia::render('AiStudio', [
            'appName' => config('app.name', 'Maniratn AI Hub'),
            'apiKeys' => $apiKeys,
            'recentLogs' => $recentLogs,
            'stats' => [
                'total_queries' => $totalQueries,
                'active_tenants' => $activeTenants,
                'total_tenants' => $totalTenants,
                'voice_tenants' => $voiceTenants,
                'total_chat_messages' => $totalChatMessages,
            ],
            'samplePrompts' => [
                'Aaj 22K gold aur silver ka bhav kya hai?',
                '14.5 gram ki 22K gold chain add kar do inventory me.',
                'Vault me abhi kitna cash aur sona hai?',
                '15 gram ki 22K chain ka total estimate kitna banega?',
            ],
        ]);
    }

    /**
     * Create a new Tenant API Key
     */
    public function storeKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'type' => 'required|in:live,test',
            'plan' => 'nullable|string|in:starter,growth,enterprise',
            'voice_enabled' => 'nullable|boolean',
        ]);

        $token = AiApiKey::generateToken($validated['type']);

        $apiKey = AiApiKey::create([
            'business_name' => $validated['business_name'],
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'key' => $token,
            'type' => $validated['type'],
            'plan' => $validated['plan'] ?? 'growth',
            'voice_enabled' => $request->boolean('voice_enabled', true),
            'is_active' => true,
            'query_count' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API Key generated successfully',
            'key' => $apiKey,
        ]);
    }

    /**
     * Toggle API Key Active/Suspended status
     */
    public function toggleKey(int $id): JsonResponse
    {
        $apiKey = AiApiKey::findOrFail($id);
        $apiKey->is_active = ! $apiKey->is_active;
        $apiKey->save();

        return response()->json([
            'success' => true,
            'is_active' => $apiKey->is_active,
            'message' => $apiKey->is_active ? 'API Key activated' : 'API Key suspended',
        ]);
    }

    /**
     * Delete an API Key
     */
    public function deleteKey(int $id): JsonResponse
    {
        $apiKey = AiApiKey::findOrFail($id);
        $apiKey->delete();

        return response()->json([
            'success' => true,
            'message' => 'API Key deleted successfully',
        ]);
    }

    /**
     * Process AI Chat & Voice Requests with Token Verification & Usage Tracking
     */
    public function chat(Request $request): JsonResponse
    {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'voice' => 'nullable|string',
            'include_audio' => 'nullable|boolean',
        ]);

        // Extract token from Bearer header or api_key query param
        $bearerToken = $request->bearerToken();
        $apiKeyParam = $request->input('api_key', $bearerToken);

        // Strict Token Verification: Only allow without token if Master Admin is logged into web UI
        if (empty($apiKeyParam)) {
            if (! auth()->check()) {
                return response()->json([
                    'error' => 'Unauthorized: Missing AI Secret Key. Please configure your Shop AI Secret Key in ERP settings.',
                    'reply' => 'Unauthorized: Missing AI Secret Key.',
                ], 401);
            }
            $matchedKey = null;
        } else {
            $matchedKey = AiApiKey::where('key', $apiKeyParam)->first();

            if (! $matchedKey) {
                return response()->json([
                    'error' => 'Unauthorized: Invalid AI Secret Key. Please check your token in ERP settings.',
                    'reply' => 'Unauthorized: Invalid AI Secret Key.',
                ], 401);
            }

            if (! $matchedKey->is_active) {
                return response()->json([
                    'error' => 'Forbidden: This AI Secret Key has been suspended.',
                    'reply' => 'Forbidden: AI Subscription is suspended.',
                ], 403);
            }

            // Increment usage metrics
            $matchedKey->increment('query_count');
            $matchedKey->update(['last_used_at' => now()]);
        }

        $userMessage = $request->input('message');
        $history = $request->input('history', []);
        $voice = $request->input('voice', 'Aoede');
        $includeAudio = $request->boolean('include_audio', true);
        $storeUrl = $request->input('store_url') ?: $request->header('Origin') ?: $request->header('Referer') ?: 'http://127.0.0.1:8000';
        $sessionId = $request->input('session_id', 'default_session');

        // If tenant disabled voice, override include_audio
        if ($matchedKey && ! $matchedKey->voice_enabled) {
            $includeAudio = false;
        }

        // 1. Log User Message into Database
        AiChatLog::create([
            'api_key_id' => $matchedKey?->id,
            'api_key' => $matchedKey?->key ?? 'web_admin',
            'store_url' => $storeUrl,
            'session_id' => $sessionId,
            'role' => 'user',
            'message' => $userMessage,
            'actions' => null,
            'has_audio' => false,
            'metadata' => [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 150),
            ],
        ]);

        $erpContext = $request->input('erp_context', []);
        $result = $this->geminiService->chat($userMessage, $history, $voice, $includeAudio, $erpContext);

        $replyMessage = ! empty($result['reply'])
            ? $result['reply']
            : (! empty($result['actions']) ? 'Aapke command ke anusar action draft prepare kar diya hai.' : 'Done');

        // 2. Log Assistant Response into Database
        AiChatLog::create([
            'api_key_id' => $matchedKey?->id,
            'api_key' => $matchedKey?->key ?? 'web_admin',
            'store_url' => $storeUrl,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $replyMessage,
            'actions' => $result['actions'] ?? [],
            'has_audio' => ! empty($result['audio']),
            'audio_url' => $result['audio'] ?? null,
            'metadata' => [
                'cached' => $result['cached'] ?? false,
            ],
        ]);

        return response()->json($result);
    }

    /**
     * Get paginated chat history for ERP client (Last 10 chats, with load more pagination)
     */
    public function getHistory(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();
        $apiKeyParam = $request->input('api_key', $bearerToken);

        if (empty($apiKeyParam) && ! auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $matchedKey = null;
        if (! empty($apiKeyParam)) {
            $matchedKey = AiApiKey::where('key', $apiKeyParam)->first();
            if (! $matchedKey) {
                return response()->json(['error' => 'Invalid API Key'], 401);
            }
        }

        $query = AiChatLog::whereNotNull('message')->where('message', '!=', '');
        if ($matchedKey) {
            $query->where('api_key', $matchedKey->key);
        } else {
            $query->where('api_key', 'web_admin');
        }

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->input('before_id'));
        }

        $limit = min((int) $request->input('limit', 10), 50);

        // Fetch latest messages descending, then reverse so oldest of this page comes first
        $logs = $query->orderByDesc('id')->take($limit + 1)->get();
        $hasMore = $logs->count() > $limit;
        $logs = $logs->take($limit)->reverse()->values();

        $messages = $logs->map(fn ($log) => [
            'id' => (string) $log->id,
            'role' => $log->role,
            'content' => $log->message,
            'actions' => $log->actions ?? [],
            'audio' => $log->audio_url,
            'timestamp' => $log->created_at->format('d M, h:i A'),
        ]);

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
            'oldest_id' => $logs->first()?->id,
            'total_count' => AiChatLog::where('api_key', $matchedKey?->key ?? 'web_admin')->count(),
        ]);
    }

    /**
     * Clear Chat History for store / session
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();
        $apiKeyParam = $request->input('api_key', $bearerToken);

        if (empty($apiKeyParam) && ! auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = AiChatLog::query();
        if (! empty($apiKeyParam)) {
            $query->where('api_key', $apiKeyParam);
        } else {
            $query->where('api_key', 'web_admin');
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }

        $query->delete();

        return response()->json(['success' => true, 'message' => 'Chat history cleared.']);
    }

    /**
     * Update an action in chat log (e.g. When confirmed or discarded by user)
     */
    public function updateAction(Request $request): JsonResponse
    {
        $logId = $request->input('message_id');
        $actions = $request->input('actions');
        $reply = $request->input('reply');

        if ($logId) {
            $log = AiChatLog::find($logId);
            if ($log) {
                if ($actions !== null) {
                    $log->actions = $actions;
                }
                if ($reply !== null) {
                    $log->message = $reply;
                }
                $log->save();
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Synthesize Google Gemini Studio HD Voice on demand
     */
    public function tts(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $request->validate([
            'text' => 'required|string|max:2000',
            'voice' => 'nullable|string',
        ]);

        $bearerToken = $request->bearerToken();
        $apiKeyParam = $request->input('api_key', $bearerToken);

        if (empty($apiKeyParam) && ! auth()->check()) {
            return response()->json([
                'error' => 'Unauthorized: Missing AI Secret Key.',
            ], 401);
        }

        if (! empty($apiKeyParam)) {
            $matchedKey = AiApiKey::where('key', $apiKeyParam)->first();
            if (! $matchedKey || ! $matchedKey->is_active) {
                return response()->json([
                    'error' => 'Unauthorized: Invalid or suspended AI Secret Key.',
                ], 401);
            }
        }

        $text = $request->input('text');
        $voice = $request->input('voice', 'Aoede');

        $audioUri = $this->geminiService->synthesizeSpeech($text, $voice);

        return response()->json([
            'success' => ! empty($audioUri),
            'audio' => $audioUri,
        ]);
    }
}
