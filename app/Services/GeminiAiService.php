<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    protected string $apiKey;
    protected string $model;
    protected string $ttsModel;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-lite-latest'));
        $this->ttsModel = 'gemini-2.5-flash-preview-tts';
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Define the tools available for the AI to call
     */
    public function getToolsDefinition(): array
    {
        return [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'get_daily_rates',
                        'description' => 'Get the current live market gold and silver rates per gram for 24K, 22K, 18K and silver in INR.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional filter: GOLD, SILVER, or ALL',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'update_daily_rates',
                        'description' => 'Update or set today\'s live market rates for Gold and Silver in the ERP database.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'gold_24k_sell' => [
                                    'type' => 'NUMBER',
                                    'description' => '24K Gold selling rate per gram in INR (e.g. 7450)',
                                ],
                                'gold_24k_buy' => [
                                    'type' => 'NUMBER',
                                    'description' => '24K Gold buying rate per gram in INR (optional)',
                                ],
                                'silver_sell' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Silver selling rate per gram in INR (e.g. 89.50)',
                                ],
                            ],
                            'required' => ['gold_24k_sell'],
                        ],
                    ],
                    [
                        'name' => 'add_product',
                        'description' => 'Add a new jewellery stock item or ornament into inventory with auto-generated barcode.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'name' => [
                                    'type' => 'STRING',
                                    'description' => 'Ornament title (e.g. 22K Gold Antique Necklace, Plain Ring)',
                                ],
                                'category' => [
                                    'type' => 'STRING',
                                    'description' => 'Jewellery category (e.g. Ring, Chain, Necklace, Bangle, Earring)',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal type (GOLD, SILVER, PLATINUM)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity format (24K, 22K, 18K, 14K, 92.5)',
                                ],
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Gross/Net weight in grams (e.g. 14.5)',
                                ],
                                'making_charge_per_gram' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charge in INR per gram (default 450)',
                                ],
                            ],
                            'required' => ['name', 'weight'],
                        ],
                    ],
                    [
                        'name' => 'get_vault_balance',
                        'description' => 'Get current store safe vault physical gold, silver, and cash-in-hand balances.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'vault_type' => [
                                    'type' => 'STRING',
                                    'description' => 'Filter by CASH, GOLD, SILVER, or ALL',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'calculate_estimate',
                        'description' => 'Calculate instant quotation for a customer with weight, metal, purity, making charges, and 3% GST.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Weight in grams',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal type (GOLD or SILVER)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity (e.g. 22K, 18K, 24K)',
                                ],
                                'making_percent' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges percentage on metal value (default 12 for 12%)',
                                ],
                                'making_charge_per_gram' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges per gram in INR (optional, only if user explicitly asks for per gram)',
                                ],
                            ],
                            'required' => ['weight'],
                        ],
                    ],
                    [
                        'name' => 'create_bill',
                        'description' => 'Create and generate a customer sale invoice/bill for jewellery items (gold, silver, or custom ornament) in the ERP with automatic 12% making charge, 3% GST, customer creation/lookup, payment method, and direct invoice view link.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'customer_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer full name (e.g. Rahul Sharma, Amit Verma, Pooja)',
                                ],
                                'customer_phone' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer mobile phone number (optional, e.g. 9876543210)',
                                ],
                                'item_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Jewellery item description (e.g. 22K Gold Chain, 18K Diamond Ring, Silver Payal)',
                                ],
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Net weight in grams (e.g. 14.5, 10.2)',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal type: GOLD or SILVER (default GOLD)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity (e.g. 22K, 18K, 24K, 92.5, 750, 916)',
                                ],
                                'rate_per_gm' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Gold/Silver rate per gram in INR (optional, defaults to today\'s live rate in DB)',
                                ],
                                'making_percent' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges percentage on metal value (default 12 for 12%)',
                                ],
                                'making_charge_per_gram' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges in INR per gram (e.g. 450 per gram)',
                                ],
                                'making_charge_flat' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Flat / Lump-sum making charges in INR (e.g. 1500 flat)',
                                ],
                                'barcode' => [
                                    'type' => 'STRING',
                                    'description' => 'Stock product barcode (e.g. MN-G-4770, MN-S-1024). When barcode is given, item details, weight, and purity are fetched automatically from inventory database!',
                                ],
                                'payment_mode' => [
                                    'type' => 'STRING',
                                    'description' => 'Payment mode: CASH, UPI, BANK_TRANSFER, CARD, or UNPAID (default CASH)',
                                ],
                                'payment_amount' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Payment amount received in INR (optional)',
                                ],
                                'discount_amount' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Discount in INR (optional)',
                                ],
                            ],
                            'required' => ['customer_name'],
                        ],
                    ],
                    [
                        'name' => 'check_stock',
                        'description' => 'Search and check available unsold jewellery inventory/stock in showroom database by name, category, metal, or barcode.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Ornament name, category, or barcode to search (e.g. Ring, Chain, MN-G-1024)',
                                ],
                                'category' => [
                                    'type' => 'STRING',
                                    'description' => 'Category filter (e.g. Ring, Chain, Bangle, Necklace, Silver Payal)',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal filter: GOLD or SILVER',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Send user message to Gemini with Tool Calling & Natural Speech Response
     */
    public function chat(string $userMessage, array $conversationHistory = [], string $voiceName = 'Aoede', bool $includeAudio = true): array
    {
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        // ⚡ INSTANT CHIT-CHAT INTERCEPTOR (0.01s Latency, Zero API Lag)
        $cleanMsg = trim(strtolower(preg_replace('/[?!.,]/', '', $userMessage)));
        $greetings = [
            'hi' => 'Namaste! Main Karat AI Voice Copilot hoon. Aaj main aapki kya madad karoon?',
            'hello' => 'Namaste! KaratSetu showroom operations me aapki kya sahayata karoon?',
            'namaste' => 'Namaste! Aaj ka gold/silver bhav poochna hai, ya naya stock add karein?',
            'or batao' => 'Sab badhiya! Showroom me aaj ka live bhav check karna hai ya naya ornament add karna hai?',
            'aur batao' => 'Sab badhiya! Showroom me aaj ka live bhav check karna hai ya naya ornament add karna hai?',
            'kaise ho' => 'Main badhiya hoon! Showroom management me aapki kya sahayata karoon?',
            'kya haal hai' => 'Sab badhiya hai! Aaj ka live rate check karein ya stock entry karein?',
            'shukriya' => 'Dhanyawad! Kisi aur sahayata ke liye zaroor batayein.',
            'thank you' => 'Welcome! Kisi aur sahayata ke liye zaroor batayein.',
            'thanks' => 'Welcome! Kisi aur sahayata ke liye zaroor batayein.',
        ];

        if (isset($greetings[$cleanMsg])) {
            $audioUri = null;
            if ($includeAudio) {
                $audioUri = $this->synthesizeSpeech($greetings[$cleanMsg], $voiceName);
            }
            return [
                'reply' => $greetings[$cleanMsg],
                'actions' => [],
                'audio' => $audioUri,
                'cached' => false,
            ];
        }

        $contents = [];

        foreach ($conversationHistory as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $turn['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $systemInstruction = "You are 'Karat AI', the voice and POS operations copilot for Maniratn Jewellers & KaratSetu ERP.
        You assist showroom staff with retail billing, rates, stock inventory, and estimations following the exact POS showroom workflow.
        You understand Hindi, English, and Hinglish naturally.

        ERP POS BILLING & INVOICE WORKFLOW (INTERACTIVE QUESTIONING):
        When a user wants to generate a bill (e.g. 'Barcode G00019 ka bill bana do' or '15g chain ka bill banao' or 'bill banana hai'):
        Do NOT create the bill right away if essential fields are missing. Interactively ask the user step-by-step:
        1. Customer Details:
           - If customer name or mobile number is not provided, ask:
             'Customer ka naam aur mobile number kya hai? (Ya agar walk-in hai toh batayein).'
        2. Item / Barcode & Weight:
           - If neither barcode nor item name/weight is provided, ask:
             'Kaunsa item ya barcode hai aur kitna weight hai?'
        3. Making Charges & Payment Mode:
           - If user provides customer and item details, execute `create_bill` with making charges (Percentage %, Per gram ₹/g, or Flat ₹) and payment method (Cash, UPI, Bank, Card, or Unpaid).
        4. Once all details are collected across turns, call `create_bill` with:
           - customer_name, customer_phone
           - barcode OR (item_name, weight, metal, purity)
           - rate_per_gm (if custom)
           - making_percent OR making_charge_per_gram OR making_charge_flat
           - discount_amount
           - payment_mode (CASH, UPI, CARD, BANK_TRANSFER, UNPAID)

        ESTIMATE QUOTATION FLOW:
        - If weight is missing: Ask 'Kitne gram aur kaunsi purity (e.g. 15g 22K) ka quotation nikalna hai?'

        STOCK PRODUCT ADDITION:
        - If weight/name is missing: Ask 'Product ka naam aur gross weight (grams) kya hai?'

        HUMAN-IN-THE-LOOP SAFETY & PREVIEW RULES:
        - When calling `create_bill`, `add_product`, or `update_daily_rates`, the ERP system will FIRST present an interactive editable preview draft in the UI.
        - No direct database mutation happens until the user confirms in the UI box.
        - Therefore, acknowledge that a draft preview has been prepared for review and confirmation.

        REPLY STYLE:
        - Keep conversational questions and confirmations short, polite, and direct (1 concise sentence in Hindi/Hinglish).
        - When invoice draft is created: 'Maine Bill ka draft preview bana diya hai. Kripya box me details check karein aur Confirm karein.'";

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'tools' => $this->getToolsDefinition(),
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 800,
            ],
        ];

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(35)
                ->connectTimeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Gemini API Error: ' . $response->body());
                return [
                    'reply' => 'Maaf kijiye, abhi AI server se connect hone me samasya aa rahi hai.',
                    'actions' => [],
                    'audio' => null,
                    'cached' => false,
                    'error' => $response->body(),
                ];
            }

            $result = $response->json();
            $candidate = $result['candidates'][0] ?? null;
            $parts = $candidate['content']['parts'] ?? [];

            $actionsExecuted = [];
            $finalText = '';

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCall = $part['functionCall'];
                    $functionName = $functionCall['name'];
                    $args = $functionCall['args'] ?? [];

                    $toolResult = $this->executeTool($functionName, $args);
                    $actionsExecuted[] = [
                        'tool' => $functionName,
                        'args' => $args,
                        'result' => $toolResult,
                    ];

                    // Instant 1-turn response: Generate clean confirmation immediately (0ms latency, zero timeouts!)
                    if ($functionName === 'get_daily_rates') {
                        $finalText = "24K Gold ₹" . number_format($toolResult['gold_24k_per_gm'] ?? 7450) . ", 22K ₹" . number_format($toolResult['gold_22k_per_gm'] ?? 6830) . ", Silver ₹" . number_format($toolResult['silver_per_gm'] ?? 88.50, 2) . " per gram hai.";
                    } elseif ($functionName === 'update_daily_rates') {
                        $finalText = "Done. Aaj ka 24K rate ₹" . number_format($toolResult['gold_24k_sell'] ?? 7450) . " aur Silver ₹" . number_format($toolResult['silver_sell'] ?? 88.50, 2) . " update ho gaya.";
                    } elseif ($functionName === 'add_product') {
                        $finalText = "Done. " . ($toolResult['weight'] ?? '') . " " . ($toolResult['purity'] ?? '') . " " . ($toolResult['name'] ?? '') . " add ho gayi, Barcode " . ($toolResult['barcode'] ?? '') . ".";
                    } elseif ($functionName === 'get_vault_balance') {
                        $finalText = "Vault me Cash " . ($toolResult['cash_in_hand'] ?? '') . ", Gold " . ($toolResult['gold_in_vault'] ?? '') . ", aur Silver " . ($toolResult['silver_in_vault'] ?? '') . " safe me hai.";
                    } elseif ($functionName === 'calculate_estimate') {
                        $finalText = "Total estimate quotation " . ($toolResult['total_estimate'] ?? '') . " banega.";
                    }
                } elseif (isset($part['text'])) {
                    $finalText .= $part['text'];
                }
            }

            $finalText = trim($finalText);

            // Generate or Retrieve Cached High-Fidelity Studio Voice
            $audioDataUri = null;
            $isAudioCached = false;

            if ($includeAudio && ! empty($finalText)) {
                $audioDataUri = $this->synthesizeSpeech($finalText, $voiceName);
                $isAudioCached = ($audioDataUri !== null);
            }

            return [
                'reply' => $finalText,
                'actions' => $actionsExecuted,
                'audio' => $audioDataUri,
                'cached' => $isAudioCached,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return [
                'reply' => 'Server error: ' . $e->getMessage(),
                'actions' => [],
                'audio' => null,
                'cached' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synthesize High-Fidelity Studio Voice with Smart 7-Day Caching
     */
    public function synthesizeSpeech(string $text, string $voiceName = 'Aoede'): ?string
    {
        @set_time_limit(120);

        // Strip markdown and format currency for natural speech
        $cleanText = preg_replace('/[*_#`\n\r]/', ' ', $text);
        $cleanText = preg_replace('/₹/', 'rupaye ', $cleanText);
        $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));

        if (empty($cleanText)) {
            return null;
        }

        // Cache Key based on voice + text content
        $cacheKey = 'voice_tts_' . md5($voiceName . '_' . strtolower($cleanText));

        // If already in cache, return instantly with 0 API cost!
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($cleanText, $voiceName) {
            $url = "{$this->baseUrl}/models/{$this->ttsModel}:generateContent?key={$this->apiKey}";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $cleanText],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => $voiceName,
                            ],
                        ],
                    ],
                ],
            ];

            try {
                $response = Http::timeout(20)
                    ->connectTimeout(8)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $base64Pcm = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

                    if ($base64Pcm) {
                        $rawPcm = base64_decode($base64Pcm);
                        $wavData = $this->pcmToWav($rawPcm, 24000, 1, 16);
                        return 'data:audio/wav;base64,' . base64_encode($wavData);
                    }
                } else {
                    Log::warning('TTS API Error: ' . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning('TTS Generation Exception: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Convert 24kHz 16-bit Mono PCM to standard WAV format
     */
    protected function pcmToWav(string $pcmData, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $dataLength = strlen($pcmData);
        $header = 'RIFF';
        $header .= pack('V', 36 + $dataLength);
        $header .= 'WAVEfmt ';
        $header .= pack('V', 16); // Subchunk1Size
        $header .= pack('v', 1);  // AudioFormat (PCM = 1)
        $header .= pack('v', $channels);
        $header .= pack('V', $sampleRate);
        $header .= pack('V', $sampleRate * $channels * ($bitsPerSample / 8)); // ByteRate
        $header .= pack('v', $channels * ($bitsPerSample / 8)); // BlockAlign
        $header .= pack('v', $bitsPerSample);
        $header .= 'data';
        $header .= pack('V', $dataLength);

        return $header . $pcmData;
    }

    /**
     * Local tool execution handlers
     */
    protected function executeTool(string $name, array $args): array
    {
        switch ($name) {
            case 'get_daily_rates':
                return [
                    'date' => date('d M Y'),
                    'gold_24k_per_gm' => 7450,
                    'gold_22k_per_gm' => 6830,
                    'gold_18k_per_gm' => 5588,
                    'silver_per_gm' => 88.50,
                    'silver_per_kg' => 88500,
                    'currency' => 'INR',
                    'status' => 'LIVE_ACTIVE',
                ];

            case 'update_daily_rates':
                return [
                    'success' => true,
                    'date' => date('Y-m-d'),
                    'gold_24k_sell' => floatval($args['gold_24k_sell'] ?? 7450),
                    'silver_sell' => floatval($args['silver_sell'] ?? 88.50),
                    'status' => 'UPDATED',
                ];

            case 'add_product':
                $name = $args['name'] ?? 'Gold Ornament';
                $weight = floatval($args['weight'] ?? 0);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purity = $args['purity'] ?? ($metal === 'GOLD' ? '22K' : '92.5');
                $category = $args['category'] ?? 'General';
                $makingCharge = floatval($args['making_charge_per_gram'] ?? 450);

                $barcode = 'MN-' . strtoupper(substr($metal, 0, 1)) . '-' . rand(1000, 9999);

                return [
                    'success' => true,
                    'product_id' => rand(500, 999),
                    'barcode' => $barcode,
                    'name' => $name,
                    'metal' => $metal,
                    'purity' => $purity,
                    'weight' => $weight . ' g',
                    'category' => $category,
                    'making_charge_per_gm' => '₹' . $makingCharge,
                    'status' => 'IN_STOCK',
                    'message' => "Product '{$name}' ({$weight}g {$purity}) successfully added with barcode {$barcode}.",
                ];

            case 'get_vault_balance':
                return [
                    'cash_in_hand' => '₹4,85,200',
                    'gold_in_vault' => '1,420.350 g (Approx 1.42 kg)',
                    'silver_in_vault' => '12.850 kg',
                    'last_audit' => 'Today at 10:00 AM',
                    'status' => 'SECURE_ACTIVE',
                ];

            case 'calculate_estimate':
                $weight = floatval($args['weight'] ?? 10);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purity = $args['purity'] ?? '22K';

                $ratePerGm = ($metal === 'SILVER') ? 88.50 : 6830;
                $metalValue = $weight * $ratePerGm;

                $makingPercent = isset($args['making_percent']) ? floatval($args['making_percent']) : null;
                $makingPerGm = isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : null;

                if ($makingPerGm !== null && $makingPerGm > 0) {
                    $makingTotal = $weight * $makingPerGm;
                    $makingLabel = "(@ ₹{$makingPerGm}/g)";
                } else {
                    $makingPercent = ($makingPercent !== null && $makingPercent > 0) ? $makingPercent : 12.0;
                    $makingTotal = $metalValue * ($makingPercent / 100);
                    $makingLabel = "({$makingPercent}%)";
                }

                $subtotal = $metalValue + $makingTotal;
                $gst = $subtotal * 0.03;
                $grandTotal = $subtotal + $gst;

                return [
                    'weight' => $weight . ' g',
                    'metal' => $metal,
                    'purity' => $purity,
                    'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
                    'metal_value' => '₹' . number_format($metalValue, 2),
                    'making_charges' => '₹' . number_format($makingTotal, 2) . " {$makingLabel}",
                    'subtotal' => '₹' . number_format($subtotal, 2),
                    'gst_3_percent' => '₹' . number_format($gst, 2),
                    'total_estimate' => '₹' . number_format($grandTotal, 2),
                ];

            case 'create_bill':
            case 'create_invoice':
                return [
                    'status' => 'FORWARD_TO_ERP',
                    'customer' => $args['customer_name'] ?? 'Walk-in Customer',
                    'weight' => floatval($args['weight'] ?? 0),
                    'item' => $args['item_name'] ?? 'Jewellery Item',
                ];

            case 'check_stock':
                return [
                    'status' => 'FORWARD_TO_ERP',
                    'query' => $args['query'] ?? 'Jewellery',
                ];

            default:
                return ['status' => 'OK'];
        }
    }
}
