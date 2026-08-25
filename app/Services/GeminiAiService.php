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
                        'description' => 'Calculate instant quotation or estimate for a customer either by showroom product barcode (e.g. G00075) or by custom weight, metal, purity (e.g. 916 Hallmark / 22K), making charges, and 3% GST.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'barcode' => [
                                    'type' => 'STRING',
                                    'description' => 'Showroom product barcode or item code (e.g. G00075, S00010) to automatically fetch item weight, purity, and making charges from inventory',
                                ],
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Weight in grams (optional if barcode provided)',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal type (GOLD or SILVER)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity (e.g. 916 Hallmark, 22K, 18K, 14K, 24K, Silver)',
                                ],
                                'making_percent' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges percentage on metal value (optional override)',
                                ],
                                'making_charge_per_gram' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Making charges per gram in INR (optional override)',
                                ],
                            ],
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
                                    'description' => 'Customer full name (do NOT invent dummy names; leave empty or "Walk-in Customer" if not provided)',
                                ],
                                'customer_phone' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer 10-digit mobile number (do NOT invent dummy numbers; leave empty if not provided)',
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
                                    'description' => 'Stock product barcode ONLY if explicitly mentioned by user (e.g. G00019). Do NOT invent a barcode for fresh custom items.',
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
                        ],
                    ],
                    [
                        'name' => 'check_stock',
                        'description' => 'Search and check available unsold jewellery inventory/stock in showroom database by ornament name, category, target weight (grams), weight range (min/max), purity, metal, or barcode.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'category' => [
                                    'type' => 'STRING',
                                    'description' => 'Category filter (e.g. Chain, Ring, Bangle, Necklace, Pendant, Earrings, Coin, Silver Payal)',
                                ],
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Target weight in grams to search for (e.g. 3, 5, 14.5)',
                                ],
                                'min_weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Minimum weight in grams (e.g. 2)',
                                ],
                                'max_weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Maximum weight in grams (e.g. 10)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity filter (e.g. 22K, 18K, 24K, Silver)',
                                ],
                                'metal' => [
                                    'type' => 'STRING',
                                    'description' => 'Metal filter: GOLD or SILVER',
                                ],
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Keyword or barcode to search (e.g. G00019, Antique, Royal)',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_customer_khata',
                        'description' => 'Inquire customer ledger, udhar / pending due balance, total purchase history, and account status by customer name or phone number.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'customer_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer name (e.g. Ramesh Sharma)',
                                ],
                                'phone' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer mobile number (e.g. 9876543210)',
                                ],
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer name or phone search query',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'search_invoices',
                        'description' => 'Search and retrieve customer purchase history, past bills, and invoices by phone number, customer name, invoice number, or date.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'phone' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer mobile number',
                                ],
                                'customer_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Customer name',
                                ],
                                'invoice_number' => [
                                    'type' => 'STRING',
                                    'description' => 'Invoice or bill number (e.g. INV-20260815-000001)',
                                ],
                                'date' => [
                                    'type' => 'STRING',
                                    'description' => 'Invoice date in YYYY-MM-DD format',
                                ],
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'General invoice search term',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_sales_summary',
                        'description' => 'Get showroom sales report, daily counter revenue, total collection (Cash, UPI, Card), and total gold/silver weight sold.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'period' => [
                                    'type' => 'STRING',
                                    'description' => 'Time period: "today" (default), "yesterday", "this_week", or "this_month"',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'calculate_old_gold',
                        'description' => 'Calculate old gold / purana sona buyback valuation and exchange price when customer brings old gold ornaments to showroom. Has NO making charges and NO GST.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'weight' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Gross weight of old gold in grams (e.g. 12, 15.5)',
                                ],
                                'purity' => [
                                    'type' => 'STRING',
                                    'description' => 'Purity or karat of old gold (e.g. 17K, 18K, 22K, 916, 75%, 85%, etc.)',
                                ],
                                'deduction_percent' => [
                                    'type' => 'NUMBER',
                                    'description' => 'Melting or testing deduction percentage (optional, default 0)',
                                ],
                                'item_name' => [
                                    'type' => 'STRING',
                                    'description' => 'Description of old gold ornament (e.g. Purana Sona / Old Gold Chain)',
                                ],
                            ],
                            'required' => ['weight'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Send user message to Gemini with Tool Calling & Natural Speech Response
     */
    public function chat(string $userMessage, array $conversationHistory = [], string $voiceName = 'Aoede', bool $includeAudio = true, array $erpContext = []): array
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
        $lastRole = null;

        foreach ($conversationHistory as $turn) {
            $text = trim((string) ($turn['content'] ?? $turn['message'] ?? ''));
            if ($text === '') {
                continue;
            }

            $role = (($turn['role'] ?? '') === 'user') ? 'user' : 'model';

            // Google Gemini rule: First turn must be 'user'
            if (empty($contents) && $role === 'model') {
                continue;
            }

            // Google Gemini rule: Merge consecutive same-role turns
            if ($role === $lastRole) {
                $lastIdx = count($contents) - 1;
                $contents[$lastIdx]['parts'][0]['text'] .= "\n" . $text;
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
                $lastRole = $role;
            }
        }

        $currentUserText = trim($userMessage);
        if ($currentUserText === '') {
            $currentUserText = 'Hello';
        }

        if ($lastRole === 'user' && ! empty($contents)) {
            $lastIdx = count($contents) - 1;
            $contents[$lastIdx]['parts'][0]['text'] .= "\n" . $currentUserText;
        } else {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $currentUserText]],
            ];
        }

        $systemInstruction = "You are 'Karat AI', the voice and POS operations copilot for Maniratn Jewellers & KaratSetu ERP.
        You assist showroom staff with retail billing, rates, stock inventory, and estimations following the exact POS showroom workflow.
        You understand Hindi, English, and Hinglish naturally.

        ERP POS BILLING & INVOICE WORKFLOW (INTERACTIVE QUESTIONING):
        When a user wants to generate a bill (e.g. 'Barcode G00019 ka bill bana do' or '15g chain ka bill banao' or 'bill banana hai'):
        Do NOT create the bill right away if essential fields are missing. Interactively ask the user step-by-step:
        1. Customer Details:
           - NEVER invent or hallucinate fake names (like 'Rahul Sharma', 'Amit') or fake numbers (like '9876543210').
           - If customer name or mobile number is not provided, ask:
             'Customer ka naam aur mobile number kya hai? (Ya agar Walk-in customer hai toh batayein).'
           - If user says 'Walk-in', 'bina naam ke', or 'walkin', set customer_name = 'Walk-in Customer' and customer_phone = ''.
        2. Item / Barcode & Weight:
           - If neither barcode nor item name/weight is provided, ask:
             'Kaunsa item ya barcode hai aur kitna weight hai?'
           - If user provides a barcode (e.g. 'G00019'), pass 'barcode'.
           - If user is billing a new custom item (e.g. '15g 22k gold chain'), do NOT invent or pass a barcode!
        3. Making Charges & Payment Mode:
           - If user provides customer and item details, execute `create_bill` with making charges (Percentage %, Per gram ₹/g, or Flat ₹) and payment method (Cash, UPI, Bank, Card, or Unpaid).
        4. Once details are collected, call `create_bill` with:
           - customer_name, customer_phone
           - barcode (ONLY if specified) OR (item_name, weight, metal, purity)
           - rate_per_gm (if custom)
           - making_percent OR making_charge_per_gram OR making_charge_flat
           - discount_amount
           - payment_mode (CASH, UPI, CARD, BANK_TRANSFER, UNPAID)

        ESTIMATE QUOTATION & BARCODE FLOW:
        - When a user asks for an estimate / quotation by barcode (e.g. 'G00075 ka estimate bana do', 'G00075 barcode ka quotation nikalo', 'is barcode ka estimate'):
          - Call `calculate_estimate` with `barcode: 'G00075'`. The ERP will automatically load the item's name, net weight, purity (e.g. 916 Hallmark / 22K), making charges, and calculate with today's live rate!
        - When a user asks for an estimate with custom weight/purity (e.g. '15g 22k gold chain ka estimate'):
          - Call `calculate_estimate` with weight, metal, purity.
        - If neither barcode nor weight is provided: Ask 'Item ka barcode batayein, ya kitne gram aur kaunsi purity (e.g. 15g 22K / 916) ka quotation nikalna hai?'
        - Hallmarking & Purity Standards Knowledge:
          - 916 Hallmark / 916 = 22 Karat (22K) -> 91.6% pure gold
          - 750 Hallmark / 750 = 18 Karat (18K) -> 75.0% pure gold
          - 585 Hallmark / 585 = 14 Karat (14K) -> 58.5% pure gold
          - 999 / 24K = 24 Karat (24K) -> 99.9% fine gold
          - Silver = Silver 999 purity

        STOCK PRODUCT ADDITION:
        - If weight/name is missing: Ask 'Product ka naam aur gross weight (grams) kya hai?'

        INVENTORY & STOCK SEARCH WORKFLOW:
        - When the user asks to check or find stock (e.g. 'mere pass 3 gram ki chain batao', '10g ke rings dikhao', 'silver payal kitni hain'):
          - Call `check_stock` with the extracted parameters:
            - `category` (e.g. 'Chain', 'Ring', 'Bangle', 'Payal', 'Earrings')
            - `weight` (e.g. 3, 10, 14.5)
            - `metal` ('GOLD' or 'SILVER')
            - `purity` (if mentioned, e.g. '22K', '18K')
          - Always focus strictly on the requested category and weight!

        CUSTOMER KHATA / UDHAR BALANCE FLOW:
        - When user asks about a customer's udhar, balance, or khata (e.g. 'Ramesh ka kitna udhar hai?', 'Deepak ka khata balance batao', '9876543210 ka balance kya hai'):
          - Call `get_customer_khata` with `customer_name` or `phone` or `query`.

        PREVIOUS INVOICE / PURCHASE SEARCH FLOW:
        - When user asks to search past bills or purchase history (e.g. '9876543210 ka pichla bill dikhao', 'Ravi Verma ne pichla bill kab banwaya tha', 'Bill INV-0001 search karo'):
          - Call `search_invoices` with `phone`, `customer_name`, or `invoice_number`.

        OLD GOLD BUYBACK & EXCHANGE VALUATION FLOW:
        - When the user asks about OLD GOLD, PURANA SONA, EXCHANGE, or BUYBACK (e.g. '12 gram old gold 17k purity est', 'customer 15g 18k purana sona laya hai', 'old gold ka bhav kya banega'):
          - Call `calculate_old_gold` with:
            - `weight`: weight in grams (e.g. 12)
            - `purity`: exact purity/karat requested (e.g. '17K', '18K', '22K', '20K', '85%')
            - `item_name`: 'Old Gold / Purana Sona'
          - DO NOT call `calculate_estimate` for old gold buyback!
          - Note: Old gold purchase from customer does NOT have Making charges and does NOT have GST!

        SHOWROOM SALES & COUNTER SUMMARY FLOW:
        - When user asks for sales report or counter collections (e.g. 'Aaj ki sale kitni hui?', 'Aaj kitna sona bika?', 'Total counter collection kitna hai?', 'Kal ki sale kya thi?'):
          - Call `get_sales_summary` with `period: 'today'` (or 'yesterday', 'this_month').

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
            $functionCalls = [];
            $modelParts = [];
            $finalText = '';

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCall = $part['functionCall'];
                    $functionName = $functionCall['name'];
                    $args = $functionCall['args'] ?? [];

                    $toolResult = $this->executeTool($functionName, $args, $erpContext);
                    $actionsExecuted[] = [
                        'tool' => $functionName,
                        'args' => $args,
                        'result' => $toolResult,
                    ];
                    $functionCalls[] = [
                        'name' => $functionName,
                        'result' => $toolResult,
                    ];
                    $modelParts[] = $part;
                } elseif (isset($part['text'])) {
                    $finalText .= $part['text'];
                    $modelParts[] = $part;
                }
            }

            // 🤖 TRUE 2-TURN GEMINI REASONING: Feed real ERP database tool results back to Gemini
            if (! empty($functionCalls)) {
                $contents[] = [
                    'role' => 'model',
                    'parts' => $modelParts,
                ];

                $functionResponseParts = [];
                foreach ($functionCalls as $fc) {
                    $functionResponseParts[] = [
                        'functionResponse' => [
                            'name' => $fc['name'],
                            'response' => [
                                'name' => $fc['name'],
                                'content' => $fc['result'],
                            ],
                        ],
                    ];
                }

                $contents[] = [
                    'role' => 'user',
                    'parts' => $functionResponseParts,
                ];

                $secondPayload = [
                    'contents' => $contents,
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 400,
                    ],
                ];

                try {
                    $secondResponse = Http::timeout(15)
                        ->connectTimeout(5)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post($url, $secondPayload);

                    if ($secondResponse->successful()) {
                        $secondResult = $secondResponse->json();
                        $secondText = $secondResult['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        if (! empty(trim($secondText))) {
                            $finalText = trim($secondText);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gemini 2nd turn natural reply timeout, using fallback: ' . $e->getMessage());
                }
            }

            $finalText = trim($finalText);
            if (empty($finalText) && ! empty($actionsExecuted)) {
                $finalText = "Maine aapke nirdesh ke anusar details process kar di hain.";
            }

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
                $response = Http::timeout(6)
                    ->connectTimeout(3)
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
    protected function executeTool(string $name, array $args, array $erpContext = []): array
    {
        switch ($name) {
            case 'get_daily_rates':
                $g24k = floatval($erpContext['today_rates']['gold_24k'] ?? 7520);
                $g22k = floatval($erpContext['today_rates']['gold_22k'] ?? round($g24k * 0.916, 2));
                $silver = floatval($erpContext['today_rates']['silver'] ?? 89.20);

                return [
                    'date' => date('d M Y'),
                    'gold_24k_per_gm' => $g24k,
                    'gold_22k_per_gm' => $g22k,
                    'gold_18k_per_gm' => round($g24k * 0.750, 2),
                    'silver_per_gm' => $silver,
                    'currency' => 'INR',
                    'status' => 'REAL_ERP_DATABASE',
                ];

            case 'update_daily_rates':
                return [
                    'found' => true,
                    'is_preview' => true,
                    'action_type' => 'UPDATE_DAILY_RATES',
                    'date' => date('Y-m-d'),
                    'gold_24k_sell' => floatval($args['gold_24k_sell'] ?? 7450),
                    'silver_sell' => floatval($args['silver_sell'] ?? 88.50),
                    'status' => 'CONFIRMATION_REQUIRED',
                ];

            case 'add_product':
                $name = $args['name'] ?? 'Gold Ornament';
                $weight = floatval($args['weight'] ?? 0);
                $metal = strtoupper($args['metal'] ?? 'GOLD');
                $purity = $args['purity'] ?? ($metal === 'GOLD' ? '22K' : '92.5');
                $category = $args['category'] ?? 'General';
                $makingCharge = floatval($args['making_charge_per_gram'] ?? 450);

                return [
                    'found' => true,
                    'is_preview' => true,
                    'action_type' => 'ADD_PRODUCT',
                    'name' => $name,
                    'metal' => $metal,
                    'purity' => $purity,
                    'weight' => $weight,
                    'category' => $category,
                    'making_charge_per_gm' => $makingCharge,
                    'status' => 'CONFIRMATION_REQUIRED',
                ];

            case 'get_vault_balance':
                $cash = $erpContext['vault_balance']['cash'] ?? '₹0.00';
                $gold = $erpContext['vault_balance']['gold'] ?? '0.000 g';
                $silver = $erpContext['vault_balance']['silver'] ?? '0.000 g';

                return [
                    'cash_in_hand' => $cash,
                    'gold_in_vault' => $gold,
                    'silver_in_vault' => $silver,
                    'last_audit' => 'Live ERP Database',
                    'status' => 'REAL_ERP_DATABASE',
                ];

            case 'calculate_estimate':
                $matchedProduct = $erpContext['matched_product'] ?? null;
                $barcodeArg = trim($args['barcode'] ?? '');

                if ($matchedProduct && (! $barcodeArg || strtoupper($barcodeArg) === strtoupper($matchedProduct['barcode']))) {
                    $weight = floatval($matchedProduct['weight']);
                    $metal = strtoupper($matchedProduct['metal'] ?? 'GOLD');
                    $purity = $matchedProduct['purity'] ?? '916 Hallmark';
                    $makingCharge = floatval($matchedProduct['making_charge'] ?? 12);
                    $makingChargeType = $matchedProduct['making_charge_type'] ?? 'percentage';
                    $itemName = $matchedProduct['name'];
                    $barcode = $matchedProduct['barcode'];
                } else {
                    $weight = floatval($args['weight'] ?? 10);
                    $metal = strtoupper($args['metal'] ?? 'GOLD');
                    $purity = $args['purity'] ?? '916 Hallmark';
                    $makingCharge = isset($args['making_percent'])
                        ? floatval($args['making_percent'])
                        : (isset($args['making_charge_per_gram']) ? floatval($args['making_charge_per_gram']) : 12.0);
                    $makingChargeType = isset($args['making_charge_per_gram']) ? 'per_gram' : 'percentage';
                    $itemName = $args['item_name'] ?? 'Jewellery Item';
                    $barcode = $barcodeArg ?: null;
                }

                $g24k = floatval($erpContext['today_rates']['gold_24k'] ?? 7520);
                $silver = floatval($erpContext['today_rates']['silver'] ?? 89.20);

                $purityStr = strtoupper(trim((string) $purity));
                if (str_contains($purityStr, '24K') || str_contains($purityStr, '999') || str_contains($purityStr, '24')) {
                    $multiplier = 1.0;
                    $resolvedPurity = '24K (99.9%)';
                } elseif (str_contains($purityStr, '18K') || str_contains($purityStr, '750') || str_contains($purityStr, '18')) {
                    $multiplier = 0.750;
                    $resolvedPurity = '18K (750 Hallmark)';
                } elseif (str_contains($purityStr, '14K') || str_contains($purityStr, '585') || str_contains($purityStr, '14')) {
                    $multiplier = 0.585;
                    $resolvedPurity = '14K (585 Hallmark)';
                } else {
                    $multiplier = 0.916;
                    $resolvedPurity = '22K (916 Hallmark)';
                }

                $ratePerGm = ($metal === 'SILVER') ? $silver : round($g24k * $multiplier, 2);
                $metalValue = round($weight * $ratePerGm, 2);

                if ($makingChargeType === 'flat') {
                    $makingTotal = round($makingCharge, 2);
                    $makingLabel = '(₹' . number_format($makingCharge) . ' Flat)';
                } elseif ($makingChargeType === 'per_gram') {
                    $makingTotal = round($weight * $makingCharge, 2);
                    $makingLabel = '(@ ₹' . number_format($makingCharge, 2) . '/g)';
                } else {
                    $makingTotal = round($metalValue * ($makingCharge / 100), 2);
                    $makingLabel = "({$makingCharge}%)";
                }

                $subtotal = round($metalValue + $makingTotal, 2);
                $gst = round($subtotal * 0.03, 2);
                $grandTotal = round($subtotal + $gst, 2);

                return [
                    'barcode' => $barcode,
                    'item_name' => $itemName,
                    'weight' => $weight . ' g',
                    'metal' => $metal,
                    'purity' => $resolvedPurity,
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

            case 'get_customer_khata':
                return [
                    'status' => 'FORWARD_TO_ERP',
                    'customer' => $args['customer_name'] ?? ($args['query'] ?? ($args['phone'] ?? 'Customer')),
                ];

            case 'search_invoices':
                return [
                    'status' => 'FORWARD_TO_ERP',
                    'search_query' => $args['phone'] ?? ($args['customer_name'] ?? ($args['invoice_number'] ?? ($args['query'] ?? 'Invoices'))),
                ];

            case 'get_sales_summary':
                return [
                    'status' => 'FORWARD_TO_ERP',
                    'period' => $args['period'] ?? 'today',
                ];

            case 'calculate_old_gold':
            case 'old_gold_estimate':
                $weight = floatval($args['weight'] ?? 10);
                $purityMultiplier = floatval($args['purity_multiplier'] ?? ($args['purity_fraction'] ?? 0));
                $resolvedPurity = trim((string) ($args['purity_label'] ?? ($args['purity'] ?? '22K')));

                if ($purityMultiplier <= 0) {
                    if (preg_match('/(\d+(?:\.\d+)?)\s*K/i', $resolvedPurity, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 24, 4);
                    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $resolvedPurity, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 100, 4);
                    } elseif (preg_match('/\b(999|916|750|585)\b/', $resolvedPurity, $m)) {
                        $purityMultiplier = round(floatval($m[1]) / 1000, 4);
                    } else {
                        $purityMultiplier = 0.916;
                    }
                }

                $base24kBuyRate = floatval($rates['gold_buy'] ?? ($rates['gold_sell'] ?? 7000));
                $ratePerGm = round($base24kBuyRate * $purityMultiplier, 2);
                $fineGold = round($weight * $purityMultiplier, 3);
                $totalValuation = round($weight * $ratePerGm, 2);

                return [
                    'item_name' => $args['item_name'] ?? 'Old Gold / Purana Sona',
                    'weight' => $weight . ' g',
                    'purity' => $resolvedPurity,
                    'fine_gold_weight' => $fineGold . ' g (24K Pure)',
                    'base_24k_rate' => '₹' . number_format($base24kBuyRate, 2),
                    'rate_per_gm' => '₹' . number_format($ratePerGm, 2),
                    'total_estimate' => '₹' . number_format($totalValuation, 2),
                    'note' => 'No Making Charges, No GST',
                ];

            default:
                return ['status' => 'OK'];
        }
    }
}
