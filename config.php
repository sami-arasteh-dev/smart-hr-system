<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tolika Recruitment & AI Assessment Configuration
|--------------------------------------------------------------------------
|
| فایل تنظیمات مشترک:
| - فرم استخدام
| - Wizard جذب
| - ذخیره اطلاعات متقاضی
| - تحلیل هوش مصنوعی پنل ادمین
| - API ارتباط با GapGPT
|
*/

const APP_NAME = 'سامانه هوشمند جذب و استخدام تولیکا';
const APP_VERSION = '1.1.0';

const COMPANY_NAME = 'شرکت صنایع چوب و فلز تولیکا';


/*
|--------------------------------------------------------------------------
| Directories
|--------------------------------------------------------------------------
*/

const DATA_DIR       = __DIR__ . '/data';
const APPLICANTS_DIR = DATA_DIR . '/applicants';
const PHOTOS_DIR     = DATA_DIR . '/photos';
const SIGNATURES_DIR = DATA_DIR . '/signatures';

/*
| فایل‌های تحلیلی AI
*/
const ANALYSIS_DIR = DATA_DIR . '/analysis';

/*
| لاگ‌های سیستم
*/
const LOG_DIR = DATA_DIR . '/logs';


/*
|--------------------------------------------------------------------------
| GapGPT
|--------------------------------------------------------------------------
|
| کلید API فقط در PHP نگهداری می‌شود و هرگز نباید به JavaScript ارسال شود.
|
*/

const GAPGPT_API_URL = 'https://api.gapgpt.app/v1/chat/completions';

const GAPGPT_API_KEY = 'sk-K0UYE8QlMeFGcQ14afhOIXGy4MM2OjXGoaVH34aQqC7t2w0H';

const GAPGPT_MODEL = 'gpt-4o';


/*
|--------------------------------------------------------------------------
| AI Configuration
|--------------------------------------------------------------------------
*/

const AI_TEMPERATURE = 0.25;

/*
| حداکثر زمان انتظار ارتباط با API
*/
const AI_CONNECT_TIMEOUT = 15;
const AI_TIMEOUT = 120;

/*
| نسخه پرامپت تحلیل
| در آینده اگر الگوریتم تحلیل تغییر کرد، نسخه را افزایش می‌دهیم.
*/
const AI_PROMPT_VERSION = '1.0';


/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

const SESSION_NAME = 'TOLIKA_RECRUITMENT_SESSION';

const CSRF_TOKEN_LENGTH = 32;


/*
|--------------------------------------------------------------------------
| Upload limits
|--------------------------------------------------------------------------
*/

const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5 MB
const MAX_SIGNATURE_SIZE = 2 * 1024 * 1024; // 2 MB

const ALLOWED_PHOTO_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp'
];


/*
|--------------------------------------------------------------------------
| Application helpers
|--------------------------------------------------------------------------
*/

/**
 * ایجاد پوشه‌های مورد نیاز سیستم
 */
function ensureDirectories(): void
{
    $directories = [
        DATA_DIR,
        APPLICANTS_DIR,
        PHOTOS_DIR,
        SIGNATURES_DIR,
        ANALYSIS_DIR,
        LOG_DIR
    ];

    foreach ($directories as $directory) {

        if (!is_dir($directory)) {

            if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create directory: ' . $directory
                );
            }
        }
    }
}


/**
 * شروع Session
 */
function startApplicationSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {

        session_name(SESSION_NAME);

        session_start();
    }
}


/**
 * ایجاد CSRF Token
 */
function initializeCsrfToken(): void
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
}


/**
 * دریافت CSRF Token
 */
function csrfToken(): string
{
    return $_SESSION['csrf_token'] ?? '';
}


/**
 * بررسی CSRF
 */
function verifyCsrfToken(?string $token): bool
{
    if (!$token) {
        return false;
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| JSON Helpers
|--------------------------------------------------------------------------
*/

/**
 * خواندن JSON امن
 */
function readJsonFile(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return null;
    }

    $data = json_decode(
        $content,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    return is_array($data) ? $data : null;
}


/**
 * ذخیره JSON اتمیک
 *
 * نکته بسیار مهم:
 * ابتدا فایل موقت نوشته می‌شود و بعد جایگزین فایل اصلی می‌شود.
 *
 * این روش جلوی خالی شدن JSON در صورت خطای API یا قطع شدن
 * اجرای PHP را می‌گیرد.
 */
function writeJsonFile(
    string $file,
    array $data
): bool
{
    $directory = dirname($file);

    if (!is_dir($directory)) {
        mkdir($directory, 0750, true);
    }

    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        return false;
    }

    $temporaryFile =
        $file . '.tmp.' . bin2hex(random_bytes(8));

    $result = file_put_contents(
        $temporaryFile,
        $json,
        LOCK_EX
    );

    if ($result === false) {

        @unlink($temporaryFile);

        return false;
    }

    /*
    | جایگزینی اتمیک
    */
    if (!rename($temporaryFile, $file)) {

        @unlink($temporaryFile);

        return false;
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| Applicant JSON
|--------------------------------------------------------------------------
*/

/**
 * ساخت مسیر فایل متقاضی
 */
function applicantJsonPath(string $trackingCode): string
{
    /*
    | جلوگیری از Path Traversal
    */
    $safeCode = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        $trackingCode
    );

    return APPLICANTS_DIR . '/' . $safeCode . '.json';
}


/**
 * خواندن پرونده متقاضی بر اساس Tracking Code
 */
function loadApplicant(
    string $trackingCode
): ?array
{
    $file = applicantJsonPath($trackingCode);

    return readJsonFile($file);
}


/**
 * ذخیره پرونده متقاضی
 */
function saveApplicant(
    string $trackingCode,
    array $data
): bool
{
    $file = applicantJsonPath($trackingCode);

    return writeJsonFile(
        $file,
        $data
    );
}


/*
|--------------------------------------------------------------------------
| AI Analysis JSON
|--------------------------------------------------------------------------
*/

/**
 * مسیر فایل تحلیل AI
 */
function analysisJsonPath(string $trackingCode): string
{
    $safeCode = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '',
        $trackingCode
    );

    return ANALYSIS_DIR . '/' . $safeCode . '.json';
}


/**
 * خواندن تحلیل AI
 */
function loadAnalysis(
    string $trackingCode
): ?array
{
    return readJsonFile(
        analysisJsonPath($trackingCode)
    );
}


/**
 * ذخیره تحلیل AI
 */
function saveAnalysis(
    string $trackingCode,
    array $analysis
): bool
{
    return writeJsonFile(
        analysisJsonPath($trackingCode),
        $analysis
    );
}


/*
|--------------------------------------------------------------------------
| AI API
|--------------------------------------------------------------------------
*/

/**
 * ارسال درخواست به GapGPT
 *
 * نکته:
 * این تابع فقط در PHP اجرا می‌شود.
 * API Key هرگز به Browser ارسال نمی‌شود.
 */
function callGapGPT(
    array $messages,
    ?string $model = null,
    float $temperature = AI_TEMPERATURE
): array
{
    $model = $model ?: GAPGPT_MODEL;

    $payload = [
        'model' => $model,

        'messages' => $messages,

        'temperature' => $temperature
    ];

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($jsonPayload === false) {

        throw new RuntimeException(
            'Unable to encode AI request.'
        );
    }


    $ch = curl_init(GAPGPT_API_URL);

    if ($ch === false) {

        throw new RuntimeException(
            'Unable to initialize cURL.'
        );
    }


    curl_setopt_array($ch, [

        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' . GAPGPT_API_KEY,

            'Content-Type: application/json',

            'Accept: application/json'
        ],

        CURLOPT_POSTFIELDS => $jsonPayload,

        CURLOPT_CONNECTTIMEOUT => AI_CONNECT_TIMEOUT,

        CURLOPT_TIMEOUT => AI_TIMEOUT,

        CURLOPT_FOLLOWLOCATION => false,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2
    ]);


    $response = curl_exec($ch);

    $curlError = curl_error($ch);

    $curlErrno = curl_errno($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | خطای اتصال
    |--------------------------------------------------------------------------
    */

    if ($response === false) {

        throw new RuntimeException(
            'GapGPT connection error: ' .
            $curlError .
            ' (' . $curlErrno . ')'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HTTP Error
    |--------------------------------------------------------------------------
    */

    if ($httpCode < 200 || $httpCode >= 300) {

        /*
        | پاسخ API را برای Debug نگه می‌داریم،
        | اما کلید API هرگز داخل آن ذخیره نمی‌شود.
        */

        throw new RuntimeException(
            'GapGPT HTTP error ' .
            $httpCode .
            ': ' .
            mb_substr($response, 0, 2000)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode
    |--------------------------------------------------------------------------
    */

    $decoded = json_decode(
        $response,
        true
    );


    if (!is_array($decoded)) {

        throw new RuntimeException(
            'Invalid JSON response from GapGPT.'
        );
    }


    return $decoded;
}


/*
|--------------------------------------------------------------------------
| استخراج متن پاسخ AI
|--------------------------------------------------------------------------
*/

function extractGapGPTText(
    array $response
): string
{
    /*
    | ساختار استاندارد Chat Completions
    */

    if (
        isset($response['choices'][0]['message']['content'])
    ) {

        $content =
            $response['choices'][0]['message']['content'];

        if (is_string($content)) {
            return trim($content);
        }
    }


    /*
    | بعضی Gatewayها ممکن است content را به صورت array برگردانند.
    */

    if (
        isset($response['choices'][0]['message']['content']) &&
        is_array($response['choices'][0]['message']['content'])
    ) {

        $parts = [];

        foreach (
            $response['choices'][0]['message']['content']
            as $part
        ) {

            if (
                is_array($part) &&
                isset($part['text'])
            ) {

                $parts[] = (string) $part['text'];
            }
        }

        if ($parts) {
            return trim(implode("\n", $parts));
        }
    }


    return '';
}


/*
|--------------------------------------------------------------------------
| AI Analysis Prompt
|--------------------------------------------------------------------------
|
| پرامپت پایه برای تحلیل متقاضی در پنل مدیریت.
|
*/

function buildApplicantAnalysisPrompt(
    array $applicantData
): string
{
    $json = json_encode(
        $applicantData,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    return <<<PROMPT

شما یک سیستم ارزیابی تخصصی منابع انسانی برای
«شرکت صنایع چوب و فلز تولیکا» هستید.

وظیفه شما تحلیل ساختاریافته یک متقاضی استخدام بر اساس
اطلاعات رزومه، سوابق کاری، تحصیلی و پاسخ‌های ارزیابی
شایستگی است.

تحلیل باید حرفه‌ای، بی‌طرفانه، مستند به اطلاعات ارائه‌شده
و مناسب تصمیم‌گیری منابع انسانی باشد.

از تشخیص پزشکی، روان‌پزشکی یا برچسب‌زنی بالینی خودداری کنید.

تحلیل را در محورهای زیر ارائه کنید:

1. خلاصه مدیریتی پروفایل
2. نقاط قوت حرفه‌ای
3. نقاط قابل بهبود
4. سابقه و سطح مدیریتی
5. تناسب با جایگاه مورد درخواست
6. توانمندی رهبری
7. تصمیم‌گیری
8. مدیریت تعارض
9. یادگیری و رشد
10. تفکر استراتژیک
11. توان تحلیل کسب‌وکار
12. بلوغ حرفه‌ای
13. ریسک‌های استخدامی احتمالی
14. تناسب فرهنگی احتمالی
15. پیشنهاد برای مصاحبه نهایی
16. سوالات پیشنهادی مصاحبه‌کننده
17. امتیازدهی شایستگی‌ها از 0 تا 100
18. امتیاز کلی پیشنهادی از 0 تا 100
19. جمع‌بندی نهایی

در مورد اطلاعات حساس مانند مصرف دخانیات و الکل،
صرفاً به عنوان اطلاعات خوداظهاری ثبت‌شده در فرم اشاره کنید
و از هرگونه نتیجه‌گیری پزشکی یا اخلاقی قطعی خودداری کنید.

خروجی را به صورت JSON معتبر برگردانید.

ساختار پیشنهادی:

{
  "executive_summary": "",
  "strengths": [],
  "development_areas": [],
  "management_profile": "",
  "position_fit": "",
  "competencies": {
    "leadership": 0,
    "decision_making": 0,
    "conflict_management": 0,
    "learning_and_growth": 0,
    "strategic_thinking": 0,
    "business_analysis": 0,
    "professional_maturity": 0
  },
  "risks": [],
  "cultural_fit": "",
  "interview_recommendation": "",
  "interview_questions": [],
  "overall_score": 0,
  "final_recommendation": ""
}

اطلاعات متقاضی:

$json

PROMPT;
}


/*
|--------------------------------------------------------------------------
| AI Analysis
|--------------------------------------------------------------------------
*/

/**
 * تحلیل کامل متقاضی
 *
 * مهم:
 * این تابع ابتدا اطلاعات اصلی را دست‌نخورده نگه می‌دارد.
 * تحلیل AI در فایل جداگانه ذخیره می‌شود.
 */
function analyzeApplicant(
    array $applicantData
): array
{
    $prompt =
        buildApplicantAnalysisPrompt(
            $applicantData
        );


    $messages = [

        [
            'role' => 'system',

            'content' =>
                'شما یک متخصص ارزیابی منابع انسانی و شایستگی‌های شغلی هستید.'
        ],

        [
            'role' => 'user',

            'content' => $prompt
        ]
    ];


    $response =
        callGapGPT(
            $messages
        );


    $text =
        extractGapGPTText(
            $response
        );


    if ($text === '') {

        throw new RuntimeException(
            'AI returned an empty response.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | تلاش برای استخراج JSON از پاسخ
    |--------------------------------------------------------------------------
    */

    $cleanText = trim($text);


    /*
    | حذف Markdown code fence در صورت وجود
    */

    $cleanText = preg_replace(
        '/^```(?:json)?\s*/i',
        '',
        $cleanText
    );

    $cleanText = preg_replace(
        '/\s*```$/',
        '',
        $cleanText
    );


    $analysis =
        json_decode(
            trim($cleanText),
            true
        );


    /*
    | اگر مدل JSON معتبر برنگرداند،
    | پاسخ خام را نگه می‌داریم و اطلاعات متقاضی از بین نمی‌رود.
    */

    if (!is_array($analysis)) {

        $analysis = [

            'parse_status' => 'raw',

            'raw_response' => $text
        ];
    }


    return [

        'meta' => [

            'company' => COMPANY_NAME,

            'model' => GAPGPT_MODEL,

            'prompt_version' =>
                AI_PROMPT_VERSION,

            'analyzed_at' =>
                date('c')
        ],

        'analysis' => $analysis,

        'raw_response' => $text
    ];
}


/*
|--------------------------------------------------------------------------
| Logging
|--------------------------------------------------------------------------
*/

function logApplicationEvent(
    string $event,
    array $context = []
): void
{
    $entry = [

        'timestamp' => date('c'),

        'event' => $event,

        'context' => $context
    ];


    $file =
        LOG_DIR . '/application-' .
        date('Y-m-d') .
        '.log';


    file_put_contents(

        $file,

        json_encode(
            $entry,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) .
        PHP_EOL,

        FILE_APPEND |
        LOCK_EX
    );
}


/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

ensureDirectories();

startApplicationSession();

initializeCsrfToken();
