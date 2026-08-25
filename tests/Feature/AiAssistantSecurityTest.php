<?php

namespace Tests\Feature;

use App\Models\AiApiKey;
use App\Models\AiChatLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_history_isolates_messages_by_session_id(): void
    {
        AiApiKey::create([
            'business_name' => 'Test Store',
            'key' => 'test_key_123',
            'is_active' => true,
        ]);

        // Session A
        AiChatLog::create([
            'api_key' => 'test_key_123',
            'session_id' => 'user_session_AAA',
            'role' => 'user',
            'message' => 'What is 22k gold rate?',
        ]);
        AiChatLog::create([
            'api_key' => 'test_key_123',
            'session_id' => 'user_session_AAA',
            'role' => 'assistant',
            'message' => '22k gold rate is ₹6,888/gm.',
        ]);

        // Session B
        AiChatLog::create([
            'api_key' => 'test_key_123',
            'session_id' => 'user_session_BBB',
            'role' => 'user',
            'message' => 'Private ledger inquiry for Sharma ji',
        ]);
        AiChatLog::create([
            'api_key' => 'test_key_123',
            'session_id' => 'user_session_BBB',
            'role' => 'assistant',
            'message' => 'Pending balance is ₹50,000.',
        ]);

        // Fetch history for Session A
        $resA = $this->withHeaders([
            'Authorization' => 'Bearer test_key_123',
        ])->getJson('/api/ai/history?session_id=user_session_AAA');
        $resA->assertOk();

        $messagesA = $resA->json('messages');
        $this->assertCount(2, $messagesA);
        $this->assertEquals('What is 22k gold rate?', $messagesA[0]['content']);

        // Assert Session A does NOT see Session B's private messages
        $jsonStringA = json_encode($messagesA);
        $this->assertStringNotContainsString('Sharma ji', $jsonStringA);

        // Fetch history for Session B
        $resB = $this->withHeaders([
            'Authorization' => 'Bearer test_key_123',
        ])->getJson('/api/ai/history?session_id=user_session_BBB');
        $resB->assertOk();

        $messagesB = $resB->json('messages');
        $this->assertCount(2, $messagesB);
        $this->assertEquals('Private ledger inquiry for Sharma ji', $messagesB[0]['content']);
    }

    public function test_update_action_requires_valid_api_key_authentication(): void
    {
        AiApiKey::create([
            'business_name' => 'Live Store',
            'key' => 'secure_live_key_999',
            'is_active' => true,
        ]);
        $log = AiChatLog::create([
            'api_key' => 'secure_live_key_999',
            'session_id' => 'user_session_AAA',
            'role' => 'assistant',
            'message' => 'Bill draft ready',
            'actions' => [
                [
                    'tool' => 'create_bill',
                    'result' => ['is_preview' => true],
                ],
            ],
        ]);

        // 1. Unauthorized request without API key
        $unauthRes = $this->postJson('/api/ai/history/update-action', [
            'log_id' => $log->id,
            'actions' => [
                [
                    'tool' => 'create_bill',
                    'result' => ['is_preview' => false, 'invoice_number' => 'INV-20260825-001'],
                ],
            ],
        ]);
        $unauthRes->assertStatus(401);

        // 2. Request with wrong API key
        $wrongKeyRes = $this->withHeaders([
            'Authorization' => 'Bearer wrong_token_123',
        ])->postJson('/api/ai/history/update-action', [
            'log_id' => $log->id,
            'actions' => [
                [
                    'tool' => 'create_bill',
                    'result' => ['is_preview' => false, 'invoice_number' => 'INV-20260825-001'],
                ],
            ],
        ]);
        $wrongKeyRes->assertStatus(401);

        // 3. Authorized request with matching API key
        $authRes = $this->withHeaders([
            'Authorization' => 'Bearer secure_live_key_999',
        ])->postJson('/api/ai/history/update-action', [
            'log_id' => $log->id,
            'actions' => [
                [
                    'tool' => 'create_bill',
                    'result' => ['is_preview' => false, 'invoice_number' => 'INV-20260825-001'],
                ],
            ],
        ]);
        $authRes->assertOk()->assertJson(['success' => true]);

        $log->refresh();
        $actions = $log->actions;
        $this->assertEquals('INV-20260825-001', $actions[0]['result']['invoice_number']);
        $this->assertFalse($actions[0]['result']['is_preview']);
    }
}
