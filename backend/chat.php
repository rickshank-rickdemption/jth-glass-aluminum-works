<?php
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is required.']);
    exit;
}
if (mb_strlen($message) > AI_CHAT_MAX_INPUT_CHARS) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Message is too long.']);
    exit;
}

if (!function_exists('chatFallbackReply')) {
    function chatFallbackReply($msg, $preferFilipino = false)
    {
        $m = mb_strtolower($msg);
        $normalize = preg_replace('/\s+/', ' ', trim($m));
        $containsAny = function ($text, array $terms) {
            foreach ($terms as $term) {
                if (strpos($text, $term) !== false) {
                    return true;
                }
            }
            return false;
        };

        $pogiQuestions = [
            'pogi ba si gerald',
            'pogi ba si dave',
            'pogi ba si set'
        ];
        foreach ($pogiQuestions as $q) {
            if (strpos($normalize, $q) !== false) {
                $answers = ['omai namn', 'omai sau tol', 'siraulo'];
                return $answers[array_rand($answers)];
            }
        }

        $pangetQuestions = [
            'panget ba si set',
            'panget ba ko',
            'panget ba si gerald',
            'jieng'
        ];
        foreach ($pangetQuestions as $q) {
            if (strpos($normalize, $q) !== false) {
                $answers = ['oo eh', 'syempre'];
                return $answers[array_rand($answers)];
            }
        }

        if (
            $containsAny($normalize, ['track', 'tracking', 'status', 'booking status', 'reference', 'ref', 'bk-']) &&
            $containsAny($normalize, ['booking', 'request', 'appointment', 'quotation', 'nasaan', 'ano na', 'follow up', 'follow-up', 'update'])
        ) {
            if ($preferFilipino) {
                return "Pwede mong i-track ang booking mo sa Track Booking page. Kailangan mo lang ang booking reference (hal. BK-XXXXXXX) at email o phone na ginamit sa submission. Open mo ang: /track-booking.html";
            }
            return "You can track your booking on the Track Booking page. Prepare your booking reference (e.g., BK-XXXXXXX) and the email or phone used during submission. Open: /track-booking.html";
        }

        if (
            $containsAny($normalize, ['how to', 'how can i', 'paano', 'papaano']) &&
            $containsAny($normalize, ['quote', 'quotation', 'booking', 'book', 'inquiry', 'estimate', 'pa-quote', 'magpa quote', 'appointment'])
        ) {
            if ($preferFilipino) {
                return "Ganito ang flow: (1) Pumili ng product at dimensions sa Instant Quotation, (2) i-review ang estimate at items, (3) mag-book ng preferred site visit date, at (4) maghintay ng confirmation ng team. Pwede ka rin mag-inquiry sa Contact section kung may special requirements.";
            }
            return "Here is the process: (1) choose product and dimensions in Instant Quotation, (2) review your estimate and items, (3) book a preferred site visit date, and (4) wait for team confirmation. You can also submit an inquiry for special requirements.";
        }

        if (
            $containsAny($normalize, ['when', 'kailan', 'what time', 'oras']) &&
            $containsAny($normalize, ['open', 'office', 'visit', 'schedule', 'available', 'availability', 'site visit', 'booking'])
        ) {
            if ($preferFilipino) {
                return "Para sa exact availability ng site visit at processing time, kino-confirm ito ng team pagkatapos ng submission. Mas mabilis ang update kapag kumpleto ang details (product type, dimensions, address, at preferred date). Maaari kang tumawag sa 0916 339 5673 para follow-up.";
            }
            return "Exact site visit availability and processing timeline are confirmed by the team after submission. Updates are faster when your details are complete (product type, dimensions, address, and preferred date). You may also follow up at 0916 339 5673.";
        }

        if (
            $containsAny($normalize, ['what', 'ano', 'alin', 'which']) &&
            $containsAny($normalize, ['need', 'requirements', 'services', 'offer', 'products', 'scope', 'included'])
        ) {
            if ($preferFilipino) {
                return "Typical scope namin: custom glass at aluminum works (windows, doors, railings, at related systems), kasama ang fabrication at installation depende sa project. Basic requirements para sa initial estimate: product/category, dimensions, quantity, at site address.";
            }
            return "Our typical scope includes custom glass and aluminum works (windows, doors, railings, and related systems), with fabrication and installation depending on project requirements. For an initial estimate, prepare product/category, dimensions, quantity, and site address.";
        }

        if (
            strpos($m, 'schedule') !== false || strpos($m, 'lead time') !== false || strpos($m, 'timeline') !== false ||
            strpos($m, 'iskedyul') !== false || strpos($m, 'schedule') !== false || strpos($m, 'gaano katagal') !== false
        ) {
            if ($preferFilipino) {
                return "Depende ang lead time sa system type, glass specification, at site readiness. Para sa exact schedule, magsubmit ng inquiry at magco-confirm ang team ng available window.";
            }
            return "Lead time depends on system type, glass specification, and site readiness. For exact scheduling, please submit your inquiry and our team will confirm an available window.";
        }
        if (
            strpos($m, 'price') !== false || strpos($m, 'cost') !== false || strpos($m, 'quotation') !== false || strpos($m, 'quote') !== false ||
            strpos($m, 'presyo') !== false || strpos($m, 'magkano') !== false
        ) {
            if ($preferFilipino) {
                return "May estimate sa Instant Quotation flow. Ang final price ay kino-confirm pagkatapos ng actual site measurement at final specifications.";
            }
            return "We provide estimates through the Instant Quotation flow. Final pricing is confirmed after site measurement and selected specifications.";
        }
        if (
            strpos($m, 'location') !== false || strpos($m, 'office') !== false || strpos($m, 'address') !== false ||
            strpos($m, 'saan') !== false || strpos($m, 'opisina') !== false || strpos($m, 'address') !== false
        ) {
            if ($preferFilipino) {
                return "Ang office namin ay sa 142 M. Dela Fuente St, Sampaloc, Manila. Pwede rin kayo tumawag sa 0916 339 5673.";
            }
            return "Our office is at 142 M. Dela Fuente St, Sampaloc, Manila. You can also contact us at 0916 339 5673.";
        }
        if (
            strpos($m, 'contact') !== false || strpos($m, 'email') !== false || strpos($m, 'phone') !== false ||
            strpos($m, 'kontak') !== false || strpos($m, 'tawag') !== false || strpos($m, 'numero') !== false
        ) {
            if ($preferFilipino) {
                return "Pwede niyo kaming i-contact sa 0916 339 5673 o jthglass.aluminumworks.biz@gmail.com. Pwede rin magsend ng message sa Contact section.";
            }
            return "You can contact JTH at 0916 339 5673 or jthglass.aluminumworks.biz@gmail.com. You can also send a message through the Contact section.";
        }
        if (
            strpos($m, 'product') !== false || strpos($m, 'service') !== false || strpos($m, 'glass') !== false || strpos($m, 'aluminum') !== false ||
            strpos($m, 'serbisyo') !== false || strpos($m, 'produkto') !== false || strpos($m, 'salamin') !== false
        ) {
            if ($preferFilipino) {
                return "Gumagawa kami ng custom glass at aluminum works para sa residential at commercial projects, kasama ang windows, doors, railings, at related fabrication/installation.";
            }
            return "We handle custom glass and aluminum works for residential and commercial projects, including windows, doors, railings, and related fabrication/installation.";
        }
        if (
            strpos($m, 'warranty') !== false || strpos($m, 'after service') !== false || strpos($m, 'guarantee') !== false ||
            strpos($m, 'garantiya') !== false || strpos($m, 'warantee') !== false
        ) {
            if ($preferFilipino) {
                return "May workmanship support kami at post-installation service guidance. Ang exact warranty terms ay nasa signed project documents.";
            }
            return "We provide workmanship support and post-installation service guidance. Exact warranty terms are stated in signed project documents.";
        }
        if (
            strpos($m, 'status') !== false || strpos($m, 'booking') !== false || strpos($m, 'workflow') !== false ||
            strpos($m, 'pending') !== false || strpos($m, 'fabrication') !== false || strpos($m, 'installation') !== false
        ) {
            if ($preferFilipino) {
                return "Typical booking stages: Pending, Site Visit, Confirmed, Fabrication, Installation, at Completed. Para sa current status ng request mo, mag-contact directly sa team.";
            }
            return "Typical booking stages are Pending, Site Visit, Confirmed, Fabrication, Installation, and Completed. For your exact booking status, please contact the team directly.";
        }
        if (
            strpos($m, 'payment') !== false || strpos($m, 'downpayment') !== false || strpos($m, 'terms') !== false ||
            strpos($m, 'bayad') !== false || strpos($m, 'hulog') !== false
        ) {
            if ($preferFilipino) {
                return "For payment terms and downpayment details, depende ito sa project scope at final quotation. Iche-check ito ng team during confirmation.";
            }
            return "Payment terms and downpayment details depend on project scope and final quotation. The team confirms these during project approval.";
        }
        if ($preferFilipino) {
            return "Salamat sa message. Pwede akong tumulong sa general questions tungkol sa services, quotation flow, lead time, booking stages, at contact details. Para sa exact project advice, magsubmit ng inquiry para ma-assist kayo ng team.";
        }
        return "Thanks for your message. I can help with general questions about services, quotation flow, lead time, and contact details. For exact project advice, please submit an inquiry and our team will assist you directly.";
    }
}

if (!function_exists('detectFilipinoPreference')) {
    function detectFilipinoPreference($msg)
    {
        $m = mb_strtolower((string)$msg);
        $keywords = [
            'ano', 'paano', 'pwede', 'puwede', 'saan', 'magkano', 'presyo', 'salamin',
            'aluminum', 'serbisyo', 'kontak', 'kayo', 'namin', 'natin', 'gusto', 'tanong',
            'gaano', 'katagal', 'schedule', 'iskedyul', 'oo', 'hindi', 'opo', 'po'
        ];
        $hits = 0;
        foreach ($keywords as $kw) {
            if (strpos($m, $kw) !== false) {
                $hits++;
            }
        }
        return $hits >= 2;
    }
}

if (!function_exists('extractAssistantText')) {
    function extractAssistantText(array $resp)
    {
        if (!empty($resp['output_text']) && is_string($resp['output_text'])) {
            return trim($resp['output_text']);
        }
        if (!empty($resp['output']) && is_array($resp['output'])) {
            foreach ($resp['output'] as $item) {
                if (!is_array($item) || empty($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $content) {
                    if (!is_array($content)) {
                        continue;
                    }
                    if (!empty($content['text']) && is_string($content['text'])) {
                        return trim($content['text']);
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('extractGeminiText')) {
    function extractGeminiText(array $resp)
    {
        if (empty($resp['candidates']) || !is_array($resp['candidates'])) {
            return '';
        }
        foreach ($resp['candidates'] as $candidate) {
            if (!is_array($candidate) || empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                continue;
            }
            foreach ($candidate['content']['parts'] as $part) {
                if (is_array($part) && !empty($part['text']) && is_string($part['text'])) {
                    return trim($part['text']);
                }
            }
        }
        return '';
    }
}

if (!function_exists('checkChatRateLimit')) {
    function checkChatRateLimit()
    {
        $bucket = $_SESSION['chat_rate_bucket'] ?? [];
        $now = time();
        $windowStart = $now - 60;

        if (!is_array($bucket)) {
            $bucket = [];
        }
        $bucket = array_values(array_filter($bucket, function ($ts) use ($windowStart) {
            return is_int($ts) && $ts >= $windowStart;
        }));

        if (count($bucket) >= AI_CHAT_RATE_LIMIT_PER_MIN) {
            return false;
        }
        $bucket[] = $now;
        $_SESSION['chat_rate_bucket'] = $bucket;
        return true;
    }
}

if (!checkChatRateLimit()) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many requests. Please wait a moment and try again.'
    ]);
    exit;
}

$preferFilipino = detectFilipinoPreference($message);

if (AI_CHAT_ENABLED !== true || AI_CHAT_API_KEY === '') {
    echo json_encode([
        'status' => 'success',
        'source' => 'fallback',
        'reply' => chatFallbackReply($message, $preferFilipino)
    ]);
    exit;
}

$systemPrompt = <<<TXT
You are the website inquiry assistant for JTH Glass and Aluminum Works (Philippines).
Only answer general business questions in a concise, professional tone.

Allowed topics:
- Services: custom glass and aluminum works for residential/commercial projects
- Process: inquiry, quotation, site measurement, schedule coordination
- Step-by-step HOW guidance (how to quote, how to book, how to submit inquiry)
- Availability/WHEN guidance (when schedule is confirmed, when to follow up)
- Scope/WHAT guidance (what services are included, what details are needed)
- Booking tracking guidance (how to check status using reference + contact info)
- Contact details: phone/email/office address
- General timeline guidance
- Product categories (doors, windows, railings, shower enclosures, related systems)
- General glass specs (tempered, laminated, low-e, insulated, tinted, annealed)
- Booking lifecycle overview (pending to completed)
- Warranty/after-service guidance
- General payment/downpayment guidance without exact figures

Rules:
- Do not invent exact prices, discounts, or guaranteed schedules.
- If user asks for exact quote, instruct them to use Instant Quotation or submit inquiry.
- If uncertain, clearly say you are not sure and suggest contacting the team.
- Keep response under 120 words.
- Reply in the same language as the user (English, Filipino, or Taglish).
TXT;

if (AI_CHAT_PROVIDER === 'gemini') {
    $endpoint = rtrim(AI_CHAT_GEMINI_BASE_URL, '/') . '/models/' . rawurlencode(AI_CHAT_MODEL) . ':generateContent?key=' . rawurlencode(AI_CHAT_API_KEY);
    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $message]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 220
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 18,
        CURLOPT_CONNECTTIMEOUT => 8
    ]);

    $apiRaw = curl_exec($ch);
    $apiHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $apiErr = curl_errno($ch);
    curl_close($ch);

    if ($apiErr !== 0 || $apiHttp < 200 || $apiHttp >= 300 || !$apiRaw) {
        echo json_encode([
            'status' => 'success',
            'source' => 'fallback',
            'reply' => chatFallbackReply($message, $preferFilipino)
        ]);
        exit;
    }

    $apiJson = json_decode($apiRaw, true);
    $reply = is_array($apiJson) ? extractGeminiText($apiJson) : '';
    if ($reply === '') {
        $reply = chatFallbackReply($message, $preferFilipino);
    }
} else {
    $payload = [
        'model' => AI_CHAT_MODEL,
        'temperature' => 0.3,
        'max_output_tokens' => 220,
        'input' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ]
    ];

    $ch = curl_init(AI_CHAT_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . AI_CHAT_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 18,
        CURLOPT_CONNECTTIMEOUT => 8
    ]);

    $apiRaw = curl_exec($ch);
    $apiHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $apiErr = curl_errno($ch);
    curl_close($ch);

    if ($apiErr !== 0 || $apiHttp < 200 || $apiHttp >= 300 || !$apiRaw) {
        echo json_encode([
            'status' => 'success',
            'source' => 'fallback',
            'reply' => chatFallbackReply($message, $preferFilipino)
        ]);
        exit;
    }

    $apiJson = json_decode($apiRaw, true);
    $reply = is_array($apiJson) ? extractAssistantText($apiJson) : '';
    if ($reply === '') {
        $reply = chatFallbackReply($message, $preferFilipino);
    }
}

echo json_encode([
    'status' => 'success',
    'source' => AI_CHAT_PROVIDER === 'gemini' ? 'gemini' : 'ai',
    'reply' => $reply
]);
