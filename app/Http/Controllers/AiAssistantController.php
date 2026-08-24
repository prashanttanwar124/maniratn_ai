<?php

namespace App\Http\Controllers;

use App\Models\AiApiKey;
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

        return Inertia::render('AiStudio', [
            'appName' => config('app.name', 'Maniratn AI Hub'),
            'apiKeys' => $apiKeys,
            'stats' => [
                'total_queries' => $totalQueries,
                'active_tenants' => $activeTenants,
                'total_tenants' => $totalTenants,
                'voice_tenants' => $voiceTenants,
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

        // If tenant disabled voice, override include_audio
        if ($matchedKey && ! $matchedKey->voice_enabled) {
            $includeAudio = false;
        }

        $result = $this->geminiService->chat($userMessage, $history, $voice, $includeAudio);

        return response()->json($result);
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
