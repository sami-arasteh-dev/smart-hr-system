<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function responseJson(
    bool $success,
    array $data = [],
    string $message = ''
): never {

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responseJson(
        false,
        [],
        'درخواست نامعتبر است.'
    );
}


$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($csrf) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {

    responseJson(
        false,
        [],
        'نشست امنیتی معتبر نیست.'
    );
}


$raw = $_POST['application'] ?? '';

if (!$raw) {

    responseJson(
        false,
        [],
        'اطلاعات متقاضی دریافت نشد.'
    );
}


$application = json_decode(
    $raw,
    true
);

if (!is_array($application)) {

    responseJson(
        false,
        [],
        'ساختار اطلاعات نامعتبر است.'
    );
}


$position = $application['desired_position']
    ?? 'نامشخص';

$department = $application['desired_department']
    ?? 'نامشخص';


$systemPrompt = <<<PROMPT
شما یک متخصص طراحی مصاحبه و ارزیابی شغلی مبتنی بر شایستگی هستید.

برای شرکت «صنایع چوب و فلز تولیکا» سه سؤال مصاحبه شغلی طراحی کن.

هدف سؤال‌ها ارزیابی ویژگی‌های مرتبط با عملکرد شغلی است، از جمله:

- مسئولیت‌پذیری
- حل مسئله
- تصمیم‌گیری
- همکاری و کار تیمی
- مدیریت تعارض
- سازگاری
- خودآگاهی حرفه‌ای
- اخلاق کاری
- مدیریت فشار کاری
- یادگیری و رشد

سؤال‌ها باید متناسب با عنوان شغلی و سابقه متقاضی باشند.

مهم:
- دقیقاً سه سؤال تولید کن.
- سؤال‌ها تشریحی باشند.
- سؤال‌ها واقعی و موقعیت‌محور باشند.
- از سؤال‌های کلیشه‌ای مانند «بزرگترین نقطه قوت شما چیست؟» اجتناب کن.
- سؤال‌ها نباید تشخیص پزشکی، روان‌پزشکی یا بیماری روانی انجام دهند.
- درباره اختلالات روانی، سلامت ذهنی یا تشخیص بالینی سؤال نکن.
- ارزیابی صرفاً از منظر شایستگی‌های شغلی باشد.
- هر سؤال باید امکان پاسخ چند پاراگرافی داشته باشد.

خروجی فقط JSON معتبر باشد و هیچ متن دیگری خارج از JSON تولید نشود.

فرمت دقیق:

{
  "questions": [
    {
      "id": 1,
      "competency": "...",
      "question": "..."
    },
    {
      "id": 2,
      "competency": "...",
      "question": "..."
    },
    {
      "id": 3,
      "competency": "...",
      "question": "..."
    }
  ]
}
PROMPT;


$userPrompt = <<<PROMPT
عنوان شغلی مورد درخواست:
{$position}

واحد مورد درخواست:
{$department}

اطلاعات متقاضی:

PROMPT;

$userPrompt .= json_encode(
    $application,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_PRETTY_PRINT
);


$payload = [
    'model' => GAPGPT_MODEL,

    'messages' => [

        [
            'role' => 'system',
            'content' => $systemPrompt
        ],

        [
            'role' => 'user',
            'content' => $userPrompt
        ]

    ],

    'temperature' => 0.7,

    'response_format' => [
        'type' => 'json_object'
    ]
];


$ch = curl_init(GAPGPT_API_URL);

curl_setopt_array(
    $ch,
    [

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . GAPGPT_API_KEY,

            'Content-Type: application/json'

        ],

        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ),

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_TIMEOUT => 60,

        CURLOPT_CONNECTTIMEOUT => 15

    ]
);


$result = curl_exec($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);


if ($result === false) {

    responseJson(
        false,
        [],
        'ارتباط با سرویس هوش مصنوعی برقرار نشد: ' .
        $curlError
    );
}


if ($httpCode < 200 || $httpCode >= 300) {

    responseJson(
        false,
        [],
        'سرویس هوش مصنوعی خطا برگرداند. کد: ' .
        $httpCode
    );
}


$apiResponse = json_decode(
    $result,
    true
);


if (!is_array($apiResponse)) {

    responseJson(
        false,
        [],
        'پاسخ سرویس هوش مصنوعی نامعتبر است.'
    );
}


$content =
    $apiResponse['choices'][0]['message']['content']
    ?? null;


if (!$content) {

    responseJson(
        false,
        [],
        'پاسخ سؤال‌ها دریافت نشد.'
    );
}


$questions = json_decode(
    $content,
    true
);


if (
    !is_array($questions) ||
    empty($questions['questions']) ||
    count($questions['questions']) !== 3
) {

    responseJson(
        false,
        [],
        'مدل نتوانست دقیقاً سه سؤال معتبر تولید کند.'
    );
}


responseJson(
    true,
    [
        'questions' => array_values(
            $questions['questions']
        )
    ]
);
