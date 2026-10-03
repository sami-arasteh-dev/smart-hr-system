<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Tolika Smart HR Admin Panel
|--------------------------------------------------------------------------
| نسخه بازنویسی‌شده:
| - هرگز فایل اصلی متقاضی را هنگام تحلیل تغییر نمی‌دهد.
| - تحلیل AI در data/analysis/ جداگانه ذخیره می‌شود.
| - با ساختار JSON فعلی شما سازگار است.
| - اگر JSONهای قدیمی داخل data/applicants یا حتی data باشند، پیدا می‌شوند.
| - خطاهای API به صورت JSON قابل مشاهده‌اند.
| - پاسخ مدل حتی اگر Markdown باشد، استخراج و ذخیره می‌شود.
|--------------------------------------------------------------------------
*/

const ADMIN_PASSWORD = 'TolikaAdmin@2026';

/* -------------------------------------------------------------------------
   Helpers
------------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function jsonResponse(
    bool $success,
    array $data = [],
    string $message = '',
    int $status = 200
): never {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function requestCsrf(): string
{
    return (string)($_POST['csrf_token'] ?? '');
}

function requireCsrf(): void
{
    if (!verifyCsrfToken(requestCsrf())) {
        jsonResponse(false, [], 'درخواست امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.', 419);
    }
}

function safeFileName(string $file): string
{
    return basename(str_replace('\\', '/', $file));
}

function decodeJsonFile(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $raw = file_get_contents($path);

    if ($raw === false || trim($raw) === '') {
        return null;
    }

    try {
        $data = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (Throwable $e) {
        return null;
    }

    return is_array($data) ? $data : null;
}

/*
|--------------------------------------------------------------------------
| Applicant discovery
|--------------------------------------------------------------------------
| مسیر استاندارد:
| data/applicants/*.json
|
| برای سازگاری با نسخه‌های قبلی:
| data/*.json نیز بررسی می‌شود.
|--------------------------------------------------------------------------
*/

function applicantFiles(): array
{
    $paths = [];

    if (is_dir(APPLICANTS_DIR)) {
        $paths = array_merge(
            $paths,
            glob(APPLICANTS_DIR . DIRECTORY_SEPARATOR . '*.json') ?: []
        );
    }

    /*
    | Legacy fallback
    */
    if (is_dir(DATA_DIR)) {
        $rootFiles = glob(DATA_DIR . DIRECTORY_SEPARATOR . '*.json') ?: [];

        foreach ($rootFiles as $file) {
            $paths[] = $file;
        }
    }

    $paths = array_values(array_unique($paths));

    usort(
        $paths,
        static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        }
    );

    return $paths;
}

function trackingCodeFromRecord(array $record, string $file = ''): string
{
    $code = $record['system']['tracking_code'] ?? '';

    if (is_string($code) && $code !== '') {
        return $code;
    }

    return pathinfo($file, PATHINFO_FILENAME);
}

function analysisPathFor(string $trackingCode): string
{
    return analysisJsonPath($trackingCode);
}

function loadExistingAnalysis(array $record): ?array
{
    $trackingCode = trackingCodeFromRecord($record);

    if ($trackingCode !== '') {
        $separate = loadAnalysis($trackingCode);

        if (is_array($separate)) {
            return $separate;
        }
    }

    /*
    | Backward compatibility with old admin.php which stored
    | ai_profile inside the applicant JSON.
    */
    if (isset($record['ai_profile']) && is_array($record['ai_profile'])) {
        return [
            'meta' => [
                'source' => 'legacy_applicant_json'
            ],
            'analysis' => $record['ai_profile']['analysis'] ?? [],
            'raw_response' => $record['ai_profile']['raw_response'] ?? null
        ];
    }

    return null;
}

function applicantSummary(string $file, array $record): array
{
    $a = $record['applicant'] ?? [];
    $system = $record['system'] ?? [];

    $tracking = trackingCodeFromRecord($record, $file);

    return [
        'file'          => basename($file),
        'tracking_code' => (string)($system['tracking_code'] ?? $tracking),
        'created_at'    => (string)($system['created_at'] ?? ''),
        'first_name'    => (string)($a['first_name'] ?? ''),
        'last_name'     => (string)($a['last_name'] ?? ''),
        'position'      => (string)($a['desired_position'] ?? ''),
        'department'    => (string)($a['desired_department'] ?? ''),
        'company'       => (string)($a['last_company'] ?? ''),
        'experience'    => (string)($a['total_experience'] ?? ''),
        'education'     => (string)($a['education_level'] ?? ''),
        'has_analysis'  => loadExistingAnalysis($record) !== null
    ];
}

/* -------------------------------------------------------------------------
   Login
------------------------------------------------------------------------- */

if (isset($_GET['logout'])) {
    unset($_SESSION['tolika_admin']);

    header('Location: admin.php');
    exit;
}

$loginError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login_password']) &&
    empty($_POST['admin_action'])
) {
    $password = (string)$_POST['login_password'];

    if (hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['tolika_admin'] = true;

        header('Location: admin.php');
        exit;
    }

    $loginError = 'رمز عبور مدیر صحیح نیست.';
}

if (empty($_SESSION['tolika_admin'])):
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود مدیر | ادمین منابع انسانی</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Tahoma,Arial,sans-serif}
body{
    min-height:100vh;
    display:grid;
    place-items:center;
    background:
        radial-gradient(circle at 15% 10%,#395b82 0,#1c3552 30%,#0c1724 80%);
}
.login{
    width:min(440px,calc(100% - 28px));
    background:#fff;
    border-radius:24px;
    padding:34px;
    box-shadow:0 30px 90px rgba(0,0,0,.35);
}
.logo{
    width:68px;height:68px;border-radius:19px;
    display:grid;place-items:center;
    background:#172b4d;color:#fff;
    font-size:30px;font-weight:900;
    margin-bottom:20px;
}
h1{margin:0 0 8px;font-size:22px;color:#17202a}
.sub{color:#667085;font-size:13px;line-height:2;margin:0 0 20px}
input{
    width:100%;padding:14px 15px;
    border:1px solid #d0d5dd;border-radius:12px;
    font:inherit;outline:none;
}
input:focus{border-color:#274c77;box-shadow:0 0 0 3px rgba(39,76,119,.1)}
button{
    width:100%;margin-top:12px;padding:14px;
    border:0;border-radius:12px;
    background:#172b4d;color:#fff;
    font:inherit;font-weight:700;cursor:pointer;
}
.error{
    padding:12px 14px;border-radius:11px;
    background:#fff1f0;color:#b42318;
    font-size:12px;margin-bottom:12px;
}
</style>
</head>
<body>
<div class="login">
    <div class="logo">ت</div>
    <h1>پنل مدیریت جذب و استخدام</h1>
    <p class="sub">سامانه هوشمند منابع انسانی<br>نسخه کاربردی | گیت هاب - سامان آراسته</p>

    <?php if ($loginError !== ''): ?>
        <div class="error"><?= e($loginError) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input
            type="password"
            name="login_password"
            placeholder="رمز عبور مدیر"
            required
            autofocus
        >
        <button type="submit">ورود به داشبورد</button>
    </form>
</div>
</body>
</html>
<?php
exit;
endif;

/* -------------------------------------------------------------------------
   AJAX
------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {

    requireCsrf();

    $action = (string)$_POST['admin_action'];

    /*
    |--------------------------------------------------------------------------
    | GET JSON
    |--------------------------------------------------------------------------
    */

    if ($action === 'get_json') {

        $file = safeFileName((string)($_POST['file'] ?? ''));

        if ($file === '') {
            jsonResponse(false, [], 'نام فایل ارسال نشده است.', 400);
        }

        $candidate = APPLICANTS_DIR . DIRECTORY_SEPARATOR . $file;

        if (!is_file($candidate)) {
            $candidate = DATA_DIR . DIRECTORY_SEPARATOR . $file;
        }

        if (
            !is_file($candidate) ||
            pathinfo($candidate, PATHINFO_EXTENSION) !== 'json'
        ) {
            jsonResponse(false, [], 'فایل متقاضی پیدا نشد.', 404);
        }

        $record = decodeJsonFile($candidate);

        if (!is_array($record)) {
            jsonResponse(false, [], 'JSON متقاضی خالی، خراب یا غیرقابل پردازش است.', 422);
        }

        /*
        | تحلیل جداگانه را به پاسخ merge نمی‌کنیم.
        | در JS با ai_profile ارائه می‌شود.
        */
        $analysis = loadExistingAnalysis($record);

        $recordForUI = $record;

        if ($analysis !== null) {
            $recordForUI['ai_profile'] = [
                'analyzed_at' => $analysis['meta']['analyzed_at'] ?? '',
                'model'       => $analysis['meta']['model'] ?? GAPGPT_MODEL,
                'analysis'    => $analysis['analysis'] ?? [],
                'raw_response'=> $analysis['raw_response'] ?? null
            ];
        } else {
            unset($recordForUI['ai_profile']);
        }

        jsonResponse(
            true,
            [
                'record' => $recordForUI,
                'file'   => basename($candidate)
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYZE
    |--------------------------------------------------------------------------
    */

    if ($action === 'analyze') {

        $file = safeFileName((string)($_POST['file'] ?? ''));

        if ($file === '') {
            jsonResponse(false, [], 'نام فایل متقاضی ارسال نشده است.', 400);
        }

        $path = APPLICANTS_DIR . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            $path = DATA_DIR . DIRECTORY_SEPARATOR . $file;
        }

        if (
            !is_file($path) ||
            pathinfo($path, PATHINFO_EXTENSION) !== 'json'
        ) {
            jsonResponse(false, [], 'فایل متقاضی معتبر نیست.', 404);
        }

        $record = decodeJsonFile($path);

        if (!is_array($record)) {
            jsonResponse(false, [], 'JSON متقاضی خالی یا خراب است. تحلیل متوقف شد و هیچ فایلی تغییر نکرد.', 422);
        }

        $applicant = $record['applicant'] ?? [];
        $assessment = $record['ai_assessment'] ?? [];

        if (!is_array($applicant)) {
            $applicant = [];
        }

        if (!is_array($assessment)) {
            $assessment = [];
        }

        /*
        |--------------------------------------------------------------------------
        | اطلاعات مورد نیاز مدل
        |--------------------------------------------------------------------------
        */

        $safeProfile = [
            'desired_position'     => $applicant['desired_position'] ?? '',
            'desired_department'   => $applicant['desired_department'] ?? '',
            'employment_type'      => $applicant['employment_type'] ?? '',
            'expected_salary'      => $applicant['expected_salary'] ?? '',
            'available_from'       => $applicant['available_from'] ?? '',

            'education_level'      => $applicant['education_level'] ?? '',
            'major'                => $applicant['major'] ?? '',
            'field_of_study'      => $applicant['field_of_study'] ?? '',
            'university'           => $applicant['university'] ?? '',
            'graduation_year'      => $applicant['graduation_year'] ?? '',
            'gpa'                  => $applicant['gpa'] ?? '',

            'last_job_title'       => $applicant['last_job_title'] ?? '',
            'last_company'         => $applicant['last_company'] ?? '',
            'total_experience'     => $applicant['total_experience'] ?? '',
            'work_history'         => $applicant['work_history'] ?? '',
            'technical_skills'     => $applicant['technical_skills'] ?? '',
            'software_skills'      => $applicant['software_skills'] ?? '',
            'languages'            => $applicant['languages'] ?? '',
            'courses'              => $applicant['courses'] ?? '',
            'motivation'           => $applicant['motivation'] ?? '',

            'military_status'      => $applicant['military_status'] ?? '',
            'military_description' => $applicant['military_description'] ?? '',

            'criminal_record'      => $applicant['criminal_record'] ?? '',
            'disciplinary_record'  => $applicant['disciplinary_record'] ?? '',
            'work_limitation'      => $applicant['work_limitation'] ?? '',

            /*
            | صرفاً اطلاعات خوداظهاری؛ مدل نباید از آن شخصیت یا سلامت روان را استنباط کند.
            */
            'smoking'              => $applicant['smoking'] ?? '',
            'alcohol'              => $applicant['alcohol'] ?? ''
        ];

        $questions = is_array($assessment['questions'] ?? null)
            ? $assessment['questions']
            : [];

        $answers = is_array($assessment['answers'] ?? null)
            ? $assessment['answers']
            : [];

        $systemPrompt = <<<PROMPT
تو یک متخصص ارشد Assessment Center، منابع انسانی،
مدیریت استعداد و ارزیابی شایستگی‌های شغلی هستی.

وظیفه تو تحلیل یک متقاضی استخدام برای
«شرکت» است.

این تحلیل یک تصمیم‌یار منابع انسانی است و نباید به عنوان
تشخیص پزشکی، روان‌پزشکی یا ارزیابی بالینی استفاده شود.

از اطلاعات حرفه‌ای، تحصیلی، سابقه شغلی، انگیزه و پاسخ‌های
مصاحبه استفاده کن.

شایستگی‌های مورد ارزیابی:

1. تصمیم‌گیری
2. حل مسئله
3. تفکر سیستمی
4. رهبری
5. مدیریت تعارض
6. مدیریت ذی‌نفعان
7. مسئولیت‌پذیری
8. ارتباطات
9. یادگیری و رشد
10. تفکر استراتژیک
11. خودآگاهی حرفه‌ای
12. سازگاری
13. اخلاق حرفه‌ای
14. مدیریت فشار و ابهام
15. تناسب با نقش مورد درخواست

برای هر شایستگی:
- score از 0 تا 100
- confidence از 0 تا 100
- evidence کوتاه و مستند به اطلاعات متقاضی

اگر شواهد کافی وجود ندارد، confidence را پایین اعلام کن.
صرف نبودن شواهد، دلیل امتیاز پایین نیست.

درباره بیماری روانی، اختلال روانی، IQ یا سلامت روان هیچ
تشخیص یا نتیجه‌گیری ارائه نکن.

درباره مصرف دخانیات و الکل فقط به عنوان اطلاعات خوداظهاری
ثبت‌شده نگاه کن و آن را شاخص شخصیت، اخلاق یا سلامت روان قرار نده.

تناسب فرد با عنوان شغلی مورد درخواست را نیز ارزیابی کن.

خروجی باید فقط JSON معتبر باشد و هیچ Markdown یا توضیح
خارج از JSON نداشته باشد.

ساختار دقیق خروجی:

{
  "overall_score": 0,
  "role_fit_score": 0,
  "executive_summary": "",
  "competencies": [
    {
      "name": "",
      "score": 0,
      "confidence": 0,
      "evidence": ""
    }
  ],
  "strengths": [],
  "development_areas": [],
  "risk_flags": [],
  "interview_recommendations": [],
  "role_fit_explanation": "",
  "decision": "strong_fit"
}

مقادیر decision فقط یکی از این موارد باشد:
strong_fit
fit
conditional_fit
weak_fit
insufficient_evidence
PROMPT;

        $userPayload = [
            'position' => $safeProfile['desired_position'],
            'department' => $safeProfile['desired_department'],
            'profile' => $safeProfile,
            'questions' => $questions,
            'answers' => $answers
        ];

        $userPrompt =
            "اطلاعات متقاضی و ارزیابی او:\n\n" .
            json_encode(
                $userPayload,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_INVALID_UTF8_SUBSTITUTE
            );

        /*
        |--------------------------------------------------------------------------
        | GapGPT request
        |--------------------------------------------------------------------------
        | response_format عمداً ارسال نشده است تا با Gateway/مدل‌های مختلف
        | سازگاری بیشتری داشته باشد.
        |--------------------------------------------------------------------------
        */

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
            'temperature' => AI_TEMPERATURE
        ];

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($body === false) {
            jsonResponse(false, [], 'ساخت درخواست AI ناموفق بود. فایل متقاضی هیچ تغییری نکرد.', 500);
        }

        $ch = curl_init(GAPGPT_API_URL);

        if ($ch === false) {
            jsonResponse(false, [], 'cURL روی سرور در دسترس نیست. فایل متقاضی هیچ تغییری نکرد.', 500);
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . GAPGPT_API_KEY,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT => AI_CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => AI_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false
            ]
        );

        $apiResult = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($apiResult === false) {

            logApplicationEvent(
                'ai_connection_error',
                [
                    'file' => $file,
                    'curl_errno' => $curlErrno,
                    'curl_error' => $curlError
                ]
            );

            jsonResponse(
                false,
                [],
                'خطا در ارتباط با GapGPT: ' . $curlError .
                ' | فایل اصلی متقاضی هیچ تغییری نکرد.',
                502
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {

            /*
            | پاسخ API را کامل در UI نشان نمی‌دهیم.
            | حداکثر 2000 کاراکتر برای تشخیص خطا.
            */
            $shortApiError = mb_substr(
                trim($apiResult),
                0,
                2000
            );

            logApplicationEvent(
                'ai_http_error',
                [
                    'file' => $file,
                    'http_code' => $httpCode,
                    'response' => $shortApiError
                ]
            );

            jsonResponse(
                false,
                [
                    'http_code' => $httpCode,
                    'provider_response' => $shortApiError
                ],
                'GapGPT خطا برگرداند (HTTP ' . $httpCode . '). فایل اصلی متقاضی هیچ تغییری نکرد.',
                502
            );
        }

        $api = json_decode($apiResult, true);

        if (!is_array($api)) {

            jsonResponse(
                false,
                [
                    'raw_provider_response' => mb_substr($apiResult, 0, 2000)
                ],
                'پاسخ GapGPT JSON معتبر نبود. فایل اصلی متقاضی هیچ تغییری نکرد.',
                502
            );
        }

        $content = '';

        if (
            isset($api['choices'][0]['message']['content']) &&
            is_string($api['choices'][0]['message']['content'])
        ) {
            $content = trim($api['choices'][0]['message']['content']);
        }

        /*
        | بعضی Gatewayها ممکن است content را array برگردانند.
        */
        if (
            $content === '' &&
            isset($api['choices'][0]['message']['content']) &&
            is_array($api['choices'][0]['message']['content'])
        ) {
            $parts = [];

            foreach ($api['choices'][0]['message']['content'] as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $parts[] = (string)$part['text'];
                }
            }

            $content = trim(implode("\n", $parts));
        }

        if ($content === '') {
            jsonResponse(
                false,
                [
                    'provider_response' => mb_substr($apiResult, 0, 2000)
                ],
                'GapGPT پاسخ متنی قابل استفاده برنگرداند. فایل اصلی متقاضی هیچ تغییری نکرد.',
                502
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Parse AI JSON
        |--------------------------------------------------------------------------
        */

        $clean = trim($content);

        /*
        | حذف code fence
        */
        $clean = preg_replace('/^\s*```(?:json)?\s*/iu', '', $clean);
        $clean = preg_replace('/\s*```\s*$/u', '', $clean);
        $clean = trim((string)$clean);

        $analysis = json_decode(
            $clean,
            true
        );

        /*
        | اگر قبل/بعد JSON متن اضافه آمده، اولین object را پیدا کن.
        */
        if (!is_array($analysis)) {

            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');

            if ($start !== false && $end !== false && $end > $start) {

                $candidate = substr(
                    $clean,
                    $start,
                    $end - $start + 1
                );

                $analysis = json_decode(
                    $candidate,
                    true
                );
            }
        }

        if (!is_array($analysis)) {

            /*
            | پاسخ خام را در فایل تحلیل جداگانه نگه می‌داریم.
            | JSON اصلی هرگز تغییر نمی‌کند.
            */
            $tracking = trackingCodeFromRecord($record, $file);

            $rawAnalysis = [
                'meta' => [
                    'company' => COMPANY_NAME,
                    'model' => GAPGPT_MODEL,
                    'prompt_version' => AI_PROMPT_VERSION,
                    'analyzed_at' => date('c'),
                    'status' => 'raw_response'
                ],
                'analysis' => [
                    'parse_status' => 'raw',
                    'raw_response' => $content
                ],
                'raw_response' => $content
            ];

            if ($tracking !== '' && !saveAnalysis($tracking, $rawAnalysis)) {
                jsonResponse(
                    false,
                    [],
                    'مدل پاسخ داد اما ذخیره فایل تحلیل ناموفق بود. فایل اصلی متقاضی هیچ تغییری نکرد.',
                    500
                );
            }

            jsonResponse(
                false,
                [
                    'raw_response' => $content
                ],
                'مدل پاسخ داد اما پاسخ JSON معتبر نبود. پاسخ خام در پرونده تحلیل جداگانه نگهداری شد؛ فایل اصلی متقاضی تغییر نکرد.',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Save analysis ONLY in data/analysis
        |--------------------------------------------------------------------------
        */

        $tracking = trackingCodeFromRecord($record, $file);

        if ($tracking === '') {
            jsonResponse(
                false,
                [],
                'کد پیگیری متقاضی پیدا نشد؛ تحلیل ذخیره نشد و فایل اصلی تغییر نکرد.',
                422
            );
        }

        $analysisRecord = [
            'meta' => [
                'company' => COMPANY_NAME,
                'tracking_code' => $tracking,
                'model' => GAPGPT_MODEL,
                'prompt_version' => AI_PROMPT_VERSION,
                'analyzed_at' => date('c'),
                'source_applicant_file' => basename($path)
            ],
            'analysis' => $analysis,
            'raw_response' => $content
        ];

        if (!saveAnalysis($tracking, $analysisRecord)) {

            logApplicationEvent(
                'analysis_save_error',
                [
                    'tracking_code' => $tracking,
                    'file' => $file
                ]
            );

            jsonResponse(
                false,
                [],
                'تحلیل دریافت شد اما ذخیره فایل تحلیل ناموفق بود. فایل اصلی متقاضی هیچ تغییری نکرد.',
                500
            );
        }

        logApplicationEvent(
            'ai_analysis_success',
            [
                'tracking_code' => $tracking,
                'file' => $file
            ]
        );

        jsonResponse(
            true,
            [
                'analysis' => $analysis,
                'tracking_code' => $tracking
            ],
            'تحلیل با موفقیت انجام شد و در فایل جداگانه ذخیره شد.'
        );
    }

    jsonResponse(false, [], 'عملیات ناشناخته است.', 400);
}

/* -------------------------------------------------------------------------
   Load applicant list
------------------------------------------------------------------------- */

$applicants = [];

foreach (applicantFiles() as $file) {

    $record = decodeJsonFile($file);

    if (!is_array($record)) {
        continue;
    }

    /*
    | فقط فایل‌هایی که ساختار applicant دارند به لیست می‌آیند.
    */
    if (!isset($record['applicant']) || !is_array($record['applicant'])) {
        continue;
    }

    $applicants[] = applicantSummary(
        $file,
        $record
    );
}

?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">

<title>داشبورد هوشمند منابع انسانی | نسخه کاربردی | گیت هاب - سامان آراسته</title>

<style>
*{box-sizing:border-box}

:root{
    --bg:#f3f5f8;
    --card:#fff;
    --text:#17202a;
    --muted:#667085;
    --border:#e1e5ea;
    --primary:#172b4d;
    --primary2:#274c77;
    --success:#087443;
    --danger:#b42318;
    --warning:#b54708;
}

html{scroll-behavior:smooth}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Tahoma,Arial,sans-serif;
}

button,input{
    font:inherit;
}

button{
    cursor:pointer;
}

.header{
    background:linear-gradient(135deg,#132238,#274c77);
    color:#fff;
    padding:16px 24px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    position:sticky;
    top:0;
    z-index:50;
    box-shadow:0 5px 25px rgba(0,0,0,.15);
}

.brand{
    display:flex;
    align-items:center;
    gap:13px;
}

.brand-logo{
    width:48px;
    height:48px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:rgba(255,255,255,.14);
    font-size:23px;
    font-weight:900;
}

.brand h1{
    font-size:17px;
    margin:0 0 4px;
}

.brand span{
    opacity:.75;
    font-size:11px;
}

.header a{
    color:#fff;
    text-decoration:none;
    font-size:12px;
}

.layout{
    max-width:1550px;
    margin:auto;
    padding:20px;
    display:grid;
    grid-template-columns:330px minmax(0,1fr);
    gap:20px;
}

.sidebar{
    background:var(--card);
    border-radius:18px;
    padding:18px;
    height:calc(100vh - 105px);
    position:sticky;
    top:85px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
}

.sidebar-title{
    font-weight:800;
    margin-bottom:14px;
}

.search{
    position:relative;
    margin-bottom:12px;
}

.search input{
    width:100%;
    border:1px solid var(--border);
    border-radius:12px;
    padding:12px 40px 12px 12px;
    outline:none;
    background:#fff;
}

.search input:focus{
    border-color:#274c77;
    box-shadow:0 0 0 3px rgba(39,76,119,.08);
}

.search-icon{
    position:absolute;
    right:13px;
    top:11px;
}

.list{
    overflow-y:auto;
    padding-left:3px;
}

.applicant{
    padding:13px;
    border:1px solid transparent;
    border-radius:13px;
    cursor:pointer;
    margin-bottom:7px;
    transition:.15s;
}

.applicant:hover{
    background:#f4f6f8;
}

.applicant.selected{
    background:#edf3fa;
    border-color:#cbd9e8;
}

.applicant-name{
    font-weight:800;
    font-size:13px;
}

.applicant-position{
    font-size:11px;
    color:var(--muted);
    margin-top:5px;
}

.badge{
    display:inline-block;
    margin-top:8px;
    padding:4px 7px;
    border-radius:20px;
    font-size:9px;
}

.badge.ai{
    background:#e9f8ef;
    color:var(--success);
}

.badge.pending{
    background:#fff5e8;
    color:var(--warning);
}

.empty-list{
    padding:25px 10px;
    text-align:center;
    color:#98a2b3;
    font-size:12px;
}

.main{
    min-width:0;
}

.empty{
    background:#fff;
    border-radius:18px;
    padding:80px 20px;
    text-align:center;
    color:var(--muted);
}

.profile-header{
    background:#fff;
    border-radius:18px;
    padding:20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:15px;
}

.identity{
    display:flex;
    gap:17px;
    align-items:center;
    min-width:0;
}

.avatar{
    width:82px;
    height:82px;
    border-radius:18px;
    overflow:hidden;
    flex:0 0 82px;
    display:grid;
    place-items:center;
    background:#e9edf2;
    color:#7c8795;
    font-size:28px;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.identity h2{
    margin:0 0 7px;
    font-size:21px;
}

.identity p{
    margin:0;
    color:var(--muted);
    font-size:12px;
    line-height:1.8;
}

.actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    justify-content:flex-end;
}

.btn{
    border:0;
    border-radius:10px;
    padding:10px 13px;
    font-size:12px;
    font-weight:700;
}

.btn.primary{
    background:var(--primary);
    color:#fff;
}

.btn.ai{
    background:linear-gradient(135deg,#172b4d,#365f8b);
    color:#fff;
}

.btn.secondary{
    background:#eef1f4;
    color:#344054;
}

.btn.danger{
    background:#fff1f0;
    color:#b42318;
}

.kpis{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
    margin-bottom:15px;
}

.kpi{
    background:#fff;
    border-radius:18px;
    padding:18px;
    text-align:center;
}

.kpi small{
    color:var(--muted);
    font-size:11px;
}

.kpi strong{
    display:block;
    font-size:30px;
    margin:7px 0 2px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:20px;
    margin-bottom:15px;
}

.card-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:17px;
}

.card-title h3{
    margin:0;
    font-size:15px;
}

.card-title small{
    color:var(--muted);
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
}

.info{
    background:#f7f8fa;
    padding:12px;
    border-radius:11px;
    min-width:0;
}

.info small{
    display:block;
    color:var(--muted);
    font-size:10px;
    margin-bottom:6px;
}

.info strong{
    font-size:12px;
    overflow-wrap:anywhere;
    line-height:1.8;
}

.long-info{
    background:#f7f8fa;
    border-radius:11px;
    padding:14px;
    margin-bottom:10px;
}

.long-info small{
    display:block;
    color:var(--muted);
    margin-bottom:7px;
    font-size:10px;
}

.long-info div{
    white-space:pre-line;
    font-size:12px;
    line-height:2;
}

.score-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
}

.score-card{
    border:1px solid var(--border);
    border-radius:14px;
    padding:15px;
}

.score-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}

.score-name{
    font-weight:800;
    font-size:12px;
}

.score-number{
    font-size:23px;
    font-weight:900;
}

.progress{
    height:7px;
    background:#edf0f2;
    border-radius:10px;
    overflow:hidden;
    margin:12px 0;
}

.progress-bar{
    height:100%;
    border-radius:10px;
    background:linear-gradient(90deg,#274c77,#4f7da8);
}

.confidence{
    font-size:10px;
    color:var(--muted);
}

.evidence{
    font-size:11px;
    color:#475467;
    line-height:1.9;
    margin-top:9px;
}

.two-col{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}

.list-box{
    margin:0;
    padding-right:20px;
}

.list-box li{
    margin-bottom:9px;
    line-height:1.8;
    font-size:12px;
}

.answer{
    border-bottom:1px dotted #ccd2d8;
    padding:17px 0;
}

.answer:last-child{
    border-bottom:0;
}

.answer h4{
    margin:0 0 8px;
    font-size:13px;
}

.answer p{
    margin:0;
    white-space:pre-line;
    color:#475467;
    font-size:12px;
    line-height:2;
}

.notice{
    padding:14px;
    border-radius:12px;
    font-size:12px;
    line-height:2;
}

.notice.info{
    background:#f4f6f8;
    color:#475467;
}

.notice.success{
    background:#edf9f1;
    color:#087443;
}

.notice.warning{
    background:#fff7ed;
    color:#b54708;
}

.notice.danger{
    background:#fff1f0;
    color:#b42318;
}

.loading{
    position:fixed;
    inset:0;
    background:rgba(10,20,30,.68);
    display:none;
    place-items:center;
    z-index:1000;
}

.loading.show{
    display:grid;
}

.loading-box{
    width:min(430px,calc(100% - 30px));
    background:#fff;
    padding:30px;
    border-radius:18px;
    text-align:center;
}

.spinner{
    width:44px;
    height:44px;
    border:4px solid #e4e8ed;
    border-top-color:#274c77;
    border-radius:50%;
    animation:spin .8s linear infinite;
    margin:0 auto 15px;
}

@keyframes spin{
    to{transform:rotate(360deg)}
}

.toast{
    position:fixed;
    left:20px;
    bottom:20px;
    z-index:1200;
    width:min(440px,calc(100% - 40px));
    padding:14px 16px;
    border-radius:13px;
    color:#fff;
    background:#172b4d;
    box-shadow:0 15px 40px rgba(0,0,0,.22);
    display:none;
    font-size:12px;
    line-height:1.9;
}

.toast.show{
    display:block;
}

.toast.error{
    background:#b42318;
}

.toast.success{
    background:#087443;
}

.raw-box{
    background:#101820;
    color:#e9eef4;
    padding:16px;
    border-radius:12px;
    overflow:auto;
    direction:ltr;
    text-align:left;
    white-space:pre-wrap;
    font-family:Consolas,monospace;
    font-size:11px;
    line-height:1.7;
}

@media(max-width:1100px){
    .layout{
        grid-template-columns:270px minmax(0,1fr);
    }

    .info-grid{
        grid-template-columns:repeat(3,1fr);
    }

    .score-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:800px){
    .layout{
        display:block;
    }

    .sidebar{
        position:static;
        height:auto;
        margin-bottom:15px;
    }

    .list{
        max-height:300px;
    }

    .profile-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .actions{
        justify-content:flex-start;
    }

    .info-grid,
    .score-grid,
    .two-col{
        grid-template-columns:1fr 1fr;
    }
}

@media(max-width:550px){
    .header{
        padding:13px;
    }

    .header a{
        font-size:10px;
    }

    .layout{
        padding:10px;
    }

    .brand h1{
        font-size:14px;
    }

    .brand span{
        font-size:9px;
    }

    .info-grid,
    .score-grid,
    .two-col,
    .kpis{
        grid-template-columns:1fr;
    }

    .identity{
        align-items:flex-start;
    }

    .avatar{
        width:68px;
        height:68px;
        flex-basis:68px;
    }
}

@media print{
    body{background:#fff}

    .header,
    .sidebar,
    .actions,
    .kpis,
    .toast,
    .loading{
        display:none!important;
    }

    .layout{
        display:block;
        max-width:none;
        padding:0;
    }

    .card,
    .profile-header{
        box-shadow:none;
        border:1px solid #ddd;
        break-inside:avoid;
    }
}
</style>
</head>

<body>

<header class="header">
    <div class="brand">
        <div class="brand-logo">ت</div>
        <div>
            <h1>داشبورد هوشمند منابع انسانی</h1>
            <span>گیت هاب - سامان آراسته</span>
        </div>
    </div>

    <a href="?logout=1">خروج از پنل</a>
</header>

<div class="layout">

    <aside class="sidebar">

        <div class="sidebar-title">
            متقاضیان
            <span style="color:#98a2b3">
                (<?= count($applicants) ?>)
            </span>
        </div>

        <div class="search">
            <span class="search-icon">🔎</span>
            <input
                id="searchInput"
                type="search"
                autocomplete="off"
                placeholder="نام، سمت، کد پیگیری..."
            >
        </div>

        <div id="applicantList" class="list">

            <?php if (!$applicants): ?>

                <div class="empty-list">
                    هیچ پرونده JSON معتبر در
                    <br>
                    <strong>data/applicants/</strong>
                    <br>
                    پیدا نشد.
                </div>

            <?php else: ?>

                <?php foreach ($applicants as $item): ?>

                    <div
                        class="applicant"
                        data-file="<?= e($item['file']) ?>"
                        data-search="<?= e(
                            $item['first_name'] . ' ' .
                            $item['last_name'] . ' ' .
                            $item['position'] . ' ' .
                            $item['tracking_code'] . ' ' .
                            $item['department'] . ' ' .
                            $item['company']
                        ) ?>"
                    >

                        <div class="applicant-name">
                            <?= e(
                                trim(
                                    $item['first_name'] . ' ' .
                                    $item['last_name']
                                )
                            ) ?: 'بدون نام' ?>
                        </div>

                        <div class="applicant-position">
                            <?= e($item['position']) ?: 'سمت ثبت نشده' ?>
                        </div>

                        <div>
                            <?php if ($item['has_analysis']): ?>
                                <span class="badge ai">✓ تحلیل شده</span>
                            <?php else: ?>
                                <span class="badge pending">در انتظار تحلیل</span>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </aside>

    <main class="main" id="main">

        <div class="empty">

            <div style="font-size:44px">👤</div>

            <h2>یک متقاضی را انتخاب کنید</h2>

            <p>
                از فهرست متقاضیان یک پرونده را انتخاب کنید.
                <br>
                اطلاعات اصلی، پاسخ‌های ارزیابی و پروفایل شایستگی
                در این بخش نمایش داده می‌شود.
            </p>

        </div>

    </main>

</div>

<div id="loading" class="loading">
    <div class="loading-box">
        <div class="spinner"></div>
        <h3 id="loadingTitle">در حال تحلیل هوشمند...</h3>
        <p style="color:#667085;font-size:12px;line-height:2">
            اطلاعات حرفه‌ای و پاسخ‌های مصاحبه در حال تحلیل توسط
            مدل زبانی هستند. لطفاً صفحه را نبندید.
        </p>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
'use strict';

const applicants = <?= json_encode(
    $applicants,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;

const csrfToken = <?= json_encode(
    csrfToken(),
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;

let currentRecord = null;
let currentFile = null;

/* -------------------------------------------------------------------------
   Generic helpers
------------------------------------------------------------------------- */

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

function num(value) {
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : '—';
}

function clamp(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return 0;
    return Math.max(0, Math.min(100, n));
}

function safeUrl(value) {
    const s = String(value ?? '').trim();

    /*
    | عکس‌ها باید فقط local path باشند.
    | javascript: و data: عمداً رد می‌شوند.
    */
    if (
        s.startsWith('javascript:') ||
        s.startsWith('data:') ||
        s.startsWith('vbscript:')
    ) {
        return '';
    }

    return s;
}

function showToast(message, type='') {
    const toast = document.getElementById('toast');

    toast.textContent = message;
    toast.className = 'toast show ' + type;

    clearTimeout(window.__toastTimer);

    window.__toastTimer = setTimeout(() => {
        toast.className = 'toast';
    }, 5000);
}

function setLoading(show, title='در حال پردازش...') {
    const loading = document.getElementById('loading');
    const loadingTitle = document.getElementById('loadingTitle');

    loadingTitle.textContent = title;
    loading.classList.toggle('show', show);
}

async function postAction(action, extra={}) {

    const params = new URLSearchParams();

    params.set('admin_action', action);
    params.set('csrf_token', csrfToken);

    Object.entries(extra).forEach(([key,value]) => {
        params.set(key, String(value ?? ''));
    });

    const response = await fetch('admin.php', {
        method:'POST',
        headers:{
            'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept':'application/json'
        },
        body:params.toString(),
        credentials:'same-origin'
    });

    const text = await response.text();

    let result;

    try {
        result = JSON.parse(text);
    } catch (error) {
        throw new Error(
            'پاسخ سرور JSON معتبر نبود. احتمالاً PHP Error یا خطای سرور رخ داده است.'
        );
    }

    if (!response.ok || !result.success) {

        let message = result.message || 'عملیات ناموفق بود.';

        if (
            result.data &&
            result.data.http_code
        ) {
            message +=
                ' HTTP: ' +
                result.data.http_code;
        }

        if (
            result.data &&
            result.data.provider_response
        ) {
            message +=
                '\n\nپاسخ سرویس:\n' +
                result.data.provider_response;
        }

        throw new Error(message);
    }

    return result;
}

/* -------------------------------------------------------------------------
   Search
------------------------------------------------------------------------- */

const searchInput = document.getElementById('searchInput');

if (searchInput) {

    searchInput.addEventListener('input', function() {

        const q = this.value
            .trim()
            .toLocaleLowerCase('fa-IR');

        document
            .querySelectorAll('.applicant')
            .forEach(item => {

                const text = (
                    item.dataset.search || ''
                ).toLocaleLowerCase('fa-IR');

                item.style.display =
                    text.includes(q)
                        ? ''
                        : 'none';
            });
    });
}

/* -------------------------------------------------------------------------
   Applicant selection
------------------------------------------------------------------------- */

document
    .querySelectorAll('.applicant')
    .forEach(item => {

        item.addEventListener('click', () => {

            document
                .querySelectorAll('.applicant')
                .forEach(x =>
                    x.classList.remove('selected')
                );

            item.classList.add('selected');

            loadApplicant(
                item.dataset.file
            );
        });

    });

/* -------------------------------------------------------------------------
   Load applicant
------------------------------------------------------------------------- */

async function loadApplicant(file) {

    currentFile = file;

    setLoading(
        true,
        'در حال دریافت پرونده متقاضی...'
    );

    try {

        const result = await postAction(
            'get_json',
            {file:file}
        );

        currentRecord =
            result.data.record;

        renderProfile(
            currentRecord,
            file
        );

    } catch(error) {

        showToast(
            error.message ||
            'خطا در دریافت پرونده.',
            'error'
        );

    } finally {

        setLoading(false);
    }
}

/* -------------------------------------------------------------------------
   Render profile
------------------------------------------------------------------------- */

function renderProfile(record, file) {

    const a = record.applicant || {};
    const system = record.system || {};
    const assessment = record.ai_assessment || {};
    const profile = record.ai_profile || null;

    const photoPath =
        safeUrl(record.files?.photo || '');

    const photo =
        photoPath
            ? `<img src="${escapeHtml(photoPath)}"
                    alt="تصویر پرسنلی"
                    onerror="this.style.display='none'">`
            : '';

    const main =
        document.getElementById('main');

    main.innerHTML = `

        <div class="profile-header">

            <div class="identity">

                <div class="avatar">
                    ${photo || '👤'}
                </div>

                <div>

                    <h2>
                        ${escapeHtml(
                            (a.first_name || '') +
                            ' ' +
                            (a.last_name || '')
                        ).trim() || 'متقاضی'}
                    </h2>

                    <p>
                        ${escapeHtml(
                            a.desired_position || 'سمت ثبت نشده'
                        )}

                        ${a.desired_department ? ' · ' : ''}

                        ${escapeHtml(
                            a.desired_department || ''
                        )}
                    </p>

                    <p style="margin-top:5px">
                        کد پیگیری:
                        <strong>
                            ${escapeHtml(
                                system.tracking_code || ''
                            )}
                        </strong>
                    </p>

                </div>

            </div>

            <div class="actions">

                <button
                    class="btn ai"
                    onclick="runAIAnalysis()"
                >
                    ✨ تحلیل هوشمند
                </button>

                <button
                    class="btn secondary"
                    onclick="window.print()"
                >
                    🖨 چاپ
                </button>

                <button
                    class="btn secondary"
                    onclick="exportJSON()"
                >
                    ↓ JSON
                </button>

                <button
                    class="btn secondary"
                    onclick="exportCSV()"
                >
                    ↓ CSV
                </button>

            </div>

        </div>

        <div class="kpis">

            <div class="kpi">
                <small>سابقه کاری</small>
                <strong>
                    ${escapeHtml(
                        a.total_experience || '—'
                    )}
                </strong>
                <small>سال</small>
            </div>

            <div class="kpi">
                <small>آخرین مدرک</small>
                <strong style="font-size:18px">
                    ${escapeHtml(
                        a.education_level || '—'
                    )}
                </strong>
            </div>

            <div class="kpi">
                <small>امتیاز کلی AI</small>
                <strong id="overallScore">
                    ${
                        profile?.analysis
                            ? escapeHtml(
                                profile.analysis.overall_score ?? '—'
                            )
                            : '—'
                    }
                </strong>
                <small>از ۱۰۰</small>
            </div>

        </div>

        ${renderAIProfile(profile)}

        ${renderPersonal(a)}

        ${renderEducation(a)}

        ${renderProfessional(a)}

        ${renderSkills(a)}

        ${renderExtra(a)}

        ${renderAnswers(
            assessment.answers || []
        )}

    `;
}

/* -------------------------------------------------------------------------
   Sections
------------------------------------------------------------------------- */

function info(label,value) {

    return `
        <div class="info">
            <small>${escapeHtml(label)}</small>
            <strong>${escapeHtml(value || '—')}</strong>
        </div>
    `;
}

function longInfo(label,value) {

    if (
        value === undefined ||
        value === null ||
        String(value).trim() === ''
    ) {
        return '';
    }

    return `
        <div class="long-info">
            <small>${escapeHtml(label)}</small>
            <div>${escapeHtml(value)}</div>
        </div>
    `;
}

function renderPersonal(a) {

    return `
        <section class="card">

            <div class="card-title">
                <h3>اطلاعات شخصی</h3>
                <small>Personal Information</small>
            </div>

            <div class="info-grid">

                ${info('نام پدر',a.father_name)}
                ${info('کد ملی',a.national_id)}
                ${info('شماره شناسنامه',a.birth_certificate)}
                ${info('تاریخ تولد',a.birth_date)}
                ${info('محل تولد',a.birth_place)}
                ${info('جنسیت',a.gender)}
                ${info('وضعیت تأهل',a.marital_status)}
                ${info('تعداد فرزند',a.children)}
                ${info('موبایل',a.mobile)}
                ${info('تلفن',a.phone)}
                ${info('ایمیل',a.email)}
                ${info('شهر',a.city)}
                ${info('وضعیت نظام وظیفه',a.military_status)}
                ${info('شرح نظام وظیفه',a.military_description)}

            </div>

            ${longInfo('نشانی',a.address)}

        </section>
    `;
}

function renderEducation(a) {

    return `
        <section class="card">

            <div class="card-title">
                <h3>تحصیلات</h3>
            </div>

            <div class="info-grid">

                ${info('مقطع',a.education_level)}
                ${info('رشته',a.major)}
                ${info('گرایش',a.field_of_study)}
                ${info('دانشگاه',a.university)}
                ${info('سال فارغ‌التحصیلی',a.graduation_year)}
                ${info('معدل',a.gpa)}

            </div>

        </section>
    `;
}

function renderProfessional(a) {

    return `
        <section class="card">

            <div class="card-title">
                <h3>سوابق حرفه‌ای و شغلی</h3>
            </div>

            <div class="info-grid">

                ${info('آخرین سمت',a.last_job_title)}
                ${info('آخرین شرکت',a.last_company)}
                ${info('سابقه کل',a.total_experience)}
                ${info('آخرین حقوق',a.last_salary)}
                ${info('علت ترک کار',a.leaving_reason)}
                ${info('نوع همکاری',a.employment_type)}
                ${info('سمت مورد درخواست',a.desired_position)}
                ${info('واحد مورد درخواست',a.desired_department)}
                ${info('حقوق مورد انتظار',a.expected_salary)}
                ${info('آمادگی شروع',a.available_from)}

            </div>

            ${longInfo('شرح سوابق کاری',a.work_history)}

        </section>
    `;
}

function renderSkills(a) {

    return `
        <section class="card">

            <div class="card-title">
                <h3>مهارت‌ها و توانمندی‌ها</h3>
            </div>

            ${longInfo('مهارت‌های تخصصی',a.technical_skills)}
            ${longInfo('نرم‌افزارها',a.software_skills)}
            ${longInfo('زبان‌ها',a.languages)}
            ${longInfo('دوره‌ها و گواهینامه‌ها',a.courses)}

        </section>
    `;
}

function renderExtra(a) {

    return `
        <section class="card">

            <div class="card-title">
                <h3>اطلاعات تکمیلی و معرف</h3>
            </div>

            <div class="info-grid">

                ${info('معرف',a.reference_name)}
                ${info('نسبت با معرف',a.reference_relation)}
                ${info('شرکت/سازمان معرف',a.reference_company)}
                ${info('تلفن معرف',a.reference_phone)}

                ${info('سابقه کیفری',a.criminal_record)}
                ${info('سابقه انضباطی',a.disciplinary_record)}
                ${info('محدودیت شغلی',a.work_limitation)}

                ${info('دخانیات',a.smoking)}
                ${info('الکل',a.alcohol)}

            </div>

            ${longInfo('انگیزه و معرفی',a.motivation)}

        </section>
    `;
}

function renderAnswers(answers) {

    if (
        !Array.isArray(answers) ||
        answers.length === 0
    ) {

        return `
            <section class="card">
                <div class="card-title">
                    <h3>پاسخ‌های مصاحبه هوشمند</h3>
                </div>

                <div class="notice info">
                    هنوز پاسخی برای مصاحبه هوشمند ثبت نشده است.
                </div>
            </section>
        `;
    }

    return `
        <section class="card">

            <div class="card-title">

                <h3>
                    پاسخ‌های مصاحبه هوشمند
                </h3>

                <small>
                    ${answers.length} سؤال
                </small>

            </div>

            ${answers.map((item,index) => `

                <div class="answer">

                    <h4>
                        سؤال ${index + 1}
                        ·
                        ${escapeHtml(
                            item.competency || ''
                        )}
                    </h4>

                    <p>
                        <strong>
                            ${escapeHtml(
                                item.question || ''
                            )}
                        </strong>
                    </p>

                    <p style="margin-top:10px">
                        ${escapeHtml(
                            item.answer || ''
                        )}
                    </p>

                </div>

            `).join('')}

        </section>
    `;
}

/* -------------------------------------------------------------------------
   AI profile
------------------------------------------------------------------------- */

function renderAIProfile(profile) {

    if (
        !profile ||
        !profile.analysis
    ) {

        return `
            <section class="card">

                <div class="card-title">
                    <h3>✨ پروفایل شایستگی هوشمند</h3>
                    <small>AI Assessment</small>
                </div>

                <div class="notice info">
                    هنوز تحلیل AI برای این متقاضی انجام نشده است.
                    <br>
                    با دکمه «تحلیل هوشمند» می‌توانید تحلیل را اجرا کنید.
                    <br><br>
                    <strong>
                        نکته امنیتی:
                    </strong>
                    نتیجه تحلیل در فایل جداگانه
                    data/analysis/
                    ذخیره می‌شود و فایل اصلی متقاضی دست‌کاری نمی‌شود.
                </div>

            </section>
        `;
    }

    const x = profile.analysis || {};

    const competencies =
        Array.isArray(x.competencies)
            ? x.competencies
            : [];

    const strengths =
        Array.isArray(x.strengths)
            ? x.strengths
            : [];

    const development =
        Array.isArray(x.development_areas)
            ? x.development_areas
            : [];

    const risks =
        Array.isArray(x.risk_flags)
            ? x.risk_flags
            : [];

    const recommendations =
        Array.isArray(x.interview_recommendations)
            ? x.interview_recommendations
            : [];

    return `

        <section class="card">

            <div class="card-title">

                <h3>✨ پروفایل شایستگی هوشمند</h3>

                <small>
                    مدل:
                    ${escapeHtml(
                        profile.model || 'gpt-4o'
                    )}
                </small>

            </div>

            <div class="kpis">

                <div class="kpi">
                    <small>امتیاز کلی</small>
                    <strong>
                        ${num(x.overall_score)}
                    </strong>
                    <small>از ۱۰۰</small>
                </div>

                <div class="kpi">
                    <small>تناسب با شغل</small>
                    <strong>
                        ${num(x.role_fit_score)}
                    </strong>
                    <small>از ۱۰۰</small>
                </div>

                <div class="kpi">
                    <small>نتیجه</small>
                    <strong style="font-size:16px">
                        ${decisionText(x.decision)}
                    </strong>
                </div>

            </div>

            <div class="notice info">

                <strong>جمع‌بندی اجرایی</strong>

                <div style="margin-top:8px;line-height:2">
                    ${escapeHtml(
                        x.executive_summary || '—'
                    )}
                </div>

            </div>

            <div style="margin-top:18px">

                <h4>نقشه شایستگی‌ها</h4>

                ${
                    competencies.length
                        ? `
                            <div class="score-grid">
                                ${competencies
                                    .map(competencyCard)
                                    .join('')}
                            </div>
                          `
                        : `
                            <div class="notice warning">
                                مدل شایستگی‌ها را در آرایه competencies برنگردانده است.
                            </div>
                          `
                }

            </div>

            <div class="two-col" style="margin-top:18px">

                <div
                    class="card"
                    style="margin:0;background:#f8fbf9"
                >
                    <div class="card-title">
                        <h3>نقاط قوت</h3>
                    </div>
                    ${arrayList(strengths)}
                </div>

                <div
                    class="card"
                    style="margin:0;background:#fffaf5"
                >
                    <div class="card-title">
                        <h3>حوزه‌های توسعه</h3>
                    </div>
                    ${arrayList(development)}
                </div>

            </div>

            <div class="two-col" style="margin-top:15px">

                <div
                    class="card"
                    style="margin:0;background:#fff8f7"
                >
                    <div class="card-title">
                        <h3>⚠️ نکات نیازمند بررسی</h3>
                    </div>
                    ${arrayList(risks)}
                </div>

                <div
                    class="card"
                    style="margin:0;background:#f7f9fc"
                >
                    <div class="card-title">
                        <h3>پیشنهاد برای مصاحبه حضوری</h3>
                    </div>
                    ${arrayList(recommendations)}
                </div>

            </div>

            <div
                style="
                    margin-top:15px;
                    background:#f4f6f8;
                    padding:18px;
                    border-radius:13px;
                    font-size:12px;
                    line-height:2;
                "
            >

                <strong>تحلیل تناسب با نقش</strong>

                <p>
                    ${escapeHtml(
                        x.role_fit_explanation || '—'
                    )}
                </p>

            </div>

            ${
                profile.raw_response &&
                (!x.competencies || !Array.isArray(x.competencies))
                    ? `
                        <details style="margin-top:15px">
                            <summary>
                                مشاهده پاسخ خام مدل
                            </summary>
                            <div class="raw-box" style="margin-top:10px">
                                ${escapeHtml(
                                    profile.raw_response
                                )}
                            </div>
                        </details>
                      `
                    : ''
            }

        </section>

    `;
}

function competencyCard(c) {

    const score = clamp(c.score);

    return `

        <div class="score-card">

            <div class="score-top">

                <div class="score-name">
                    ${escapeHtml(
                        c.name || 'شایستگی'
                    )}
                </div>

                <div class="score-number">
                    ${score}
                </div>

            </div>

            <div class="progress">

                <div
                    class="progress-bar"
                    style="width:${score}%"
                ></div>

            </div>

            <div class="confidence">

                اطمینان:
                ${num(c.confidence)}%

            </div>

            <div class="evidence">

                ${escapeHtml(
                    c.evidence || 'شواهد ثبت نشده است.'
                )}

            </div>

        </div>

    `;
}

function arrayList(items) {

    if (
        !Array.isArray(items) ||
        !items.length
    ) {

        return `
            <div style="color:#98a2b3;font-size:11px">
                موردی ثبت نشده است.
            </div>
        `;
    }

    return `
        <ul class="list-box">
            ${items.map(item => `
                <li>
                    ${escapeHtml(
                        typeof item === 'string'
                            ? item
                            : JSON.stringify(item)
                    )}
                </li>
            `).join('')}
        </ul>
    `;
}

function decisionText(value) {

    const map = {
        strong_fit:'تناسب بسیار خوب',
        fit:'مناسب',
        conditional_fit:'مناسب مشروط',
        weak_fit:'تناسب ضعیف',
        insufficient_evidence:'شواهد ناکافی'
    };

    return map[value] || 'نامشخص';
}

/* -------------------------------------------------------------------------
   AI analysis
------------------------------------------------------------------------- */

async function runAIAnalysis() {

    if (!currentFile) {
        showToast(
            'ابتدا یک متقاضی را انتخاب کنید.',
            'error'
        );
        return;
    }

    const confirmed = confirm(
        'تحلیل هوشمند برای این متقاضی اجرا شود؟\n\n' +
        'فایل اصلی متقاضی تغییر نخواهد کرد و نتیجه در data/analysis/ ذخیره می‌شود.'
    );

    if (!confirmed) {
        return;
    }

    setLoading(
        true,
        'در حال تحلیل هوشمند...'
    );

    try {

        const result =
            await postAction(
                'analyze',
                {
                    file:currentFile
                }
            );

        showToast(
            result.message ||
            'تحلیل با موفقیت انجام شد.',
            'success'
        );

        /*
        | دوباره پرونده را از سرور می‌خوانیم تا تحلیل جداگانه
        | در UI نمایش داده شود.
        */
        await loadApplicant(currentFile);

    } catch(error) {

        showToast(
            error.message ||
            'خطا در اجرای تحلیل.',
            'error'
        );

    } finally {

        setLoading(false);
    }
}

/* -------------------------------------------------------------------------
   Exports
------------------------------------------------------------------------- */

function downloadBlob(blob, filename) {

    const url =
        URL.createObjectURL(blob);

    const a =
        document.createElement('a');

    a.href = url;
    a.download = filename;

    document.body.appendChild(a);

    a.click();

    a.remove();

    setTimeout(
        () => URL.revokeObjectURL(url),
        1000
    );
}

function exportJSON() {

    if (!currentRecord) {
        showToast(
            'ابتدا یک متقاضی را انتخاب کنید.',
            'error'
        );
        return;
    }

    const blob =
        new Blob(
            [
                JSON.stringify(
                    currentRecord,
                    null,
                    4
                )
            ],
            {
                type:'application/json;charset=utf-8'
            }
        );

    downloadBlob(
        blob,
        (
            currentRecord.system?.tracking_code ||
            'tolika-applicant'
        ) + '.json'
    );
}

function exportCSV() {

    if (!currentRecord) {
        showToast(
            'ابتدا یک متقاضی را انتخاب کنید.',
            'error'
        );
        return;
    }

    const a =
        currentRecord.applicant || {};

    const p =
        currentRecord.ai_profile?.analysis || {};

    const rows = [

        ['فیلد','مقدار'],

        ['نام',
            `${a.first_name || ''} ${a.last_name || ''}`],

        ['کد پیگیری',
            currentRecord.system?.tracking_code || ''],

        ['سمت',
            a.desired_position || ''],

        ['واحد',
            a.desired_department || ''],

        ['تحصیلات',
            a.education_level || ''],

        ['رشته',
            a.major || ''],

        ['گرایش',
            a.field_of_study || ''],

        ['دانشگاه',
            a.university || ''],

        ['سابقه',
            a.total_experience || ''],

        ['آخرین شرکت',
            a.last_company || ''],

        ['امتیاز کلی AI',
            p.overall_score ?? ''],

        ['امتیاز تناسب شغل',
            p.role_fit_score ?? ''],

        ['تصمیم AI',
            p.decision ?? '']

    ];

    const csv =
        rows
            .map(row =>
                row
                    .map(value =>
                        `"${String(value ?? '')
                            .replace(/"/g,'""')}"`
                    )
                    .join(',')
            )
            .join('\n');

    const blob =
        new Blob(
            [
                '\uFEFF' + csv
            ],
            {
                type:'text/csv;charset=utf-8'
            }
        );

    downloadBlob(
        blob,
        (
            currentRecord.system?.tracking_code ||
            'tolika-applicant'
        ) + '.csv'
    );
}

/*
|--------------------------------------------------------------------------
| Initial state
|--------------------------------------------------------------------------
*/

if (applicants.length === 1) {

    const first =
        document.querySelector('.applicant');

    if (first) {
        first.click();
    }
}

</script>
<script>
/*
 * Universal Translator Engine v3
 * Persian -> English
 *
 * Private-project mode:
 * API key is intentionally stored in this JS file.
 *
 * Features:
 * - In-memory cache
 * - localStorage persistent cache
 * - IndexedDB persistent cache
 * - SHA-256 cache keys
 * - Batch translation
 * - Glossary / forced translations
 * - Dynamic DOM translation
 * - Placeholder/title/aria-label/alt
 * - English <-> Persian toggle without reload
 */

(() => {
  "use strict";

  const CONFIG = {
    apiKey: "sk-K0UYE8QlMeFGcQ14afhOIXGy4MM2OjXGoaVH34aQqC7t2w0H",
    endpoint: "https://api.gapgpt.app/v1/chat/completions",
    model: "gapgpt-qwen-3.6",

    defaultLanguage: "en",

    batchSize: 25,
    maxCharsPerRequest: 7000,

    localStorageKey: "universal_translator_cache_v3",
    languageKey: "universal_translator_language_v3",

    dbName: "UniversalTranslatorDB",
    dbVersion: 1,
    storeName: "translations",

    ignoredTags: new Set([
      "SCRIPT", "STYLE", "NOSCRIPT", "IFRAME",
      "OBJECT", "CODE", "PRE", "SVG", "CANVAS"
    ]),

    attributes: ["placeholder", "title", "aria-label", "alt"],

    glossary: {
      // Add your permanent terminology here:
      // "شبکه افکار": "Thought Network",
      // "آینه مجازی": "Virtual Mirror",
      // "مسئول فنی": "Technical Manager"
    }
  };

  const state = {
    memoryCache: new Map(),
    originalNodes: new WeakMap(),
    originalAttributes: new WeakMap(),
    translatedNodes: new Set(),
    translatedElements: new Set(),
    observer: null,
    processing: false,
    initialized: false,
    db: null
  };

  /* ---------------- Utilities ---------------- */

  const normalize = text =>
    String(text ?? "")
      .replace(/\u200c/g, " ")
      .replace(/\s+/g, " ")
      .trim();

  const isPersian = text =>
    /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]/.test(text || "");

  const sleep = ms => new Promise(r => setTimeout(r, ms));

  function ignored(el) {
    if (!el || !el.tagName) return true;
    if (CONFIG.ignoredTags.has(el.tagName)) return true;
    if (el.closest?.("[data-no-translate]")) return true;
    if (el.id === "universal-translator-button") return true;
    return false;
  }

  /* ---------------- SHA-256 ---------------- */

  async function hash(text) {
    const data = new TextEncoder().encode(normalize(text));
    const digest = await crypto.subtle.digest("SHA-256", data);
    return [...new Uint8Array(digest)]
      .map(b => b.toString(16).padStart(2, "0"))
      .join("");
  }

  /* ---------------- IndexedDB ---------------- */

  function openDB() {
    return new Promise((resolve, reject) => {
      if (!("indexedDB" in window)) {
        resolve(null);
        return;
      }

      const request = indexedDB.open(CONFIG.dbName, CONFIG.dbVersion);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(CONFIG.storeName)) {
          db.createObjectStore(CONFIG.storeName, { keyPath: "hash" });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function idbGet(key) {
    if (!state.db) return Promise.resolve(null);

    return new Promise(resolve => {
      try {
        const tx = state.db.transaction(CONFIG.storeName, "readonly");
        const req = tx.objectStore(CONFIG.storeName).get(key);
        req.onsuccess = () => resolve(req.result?.translation || null);
        req.onerror = () => resolve(null);
      } catch {
        resolve(null);
      }
    });
  }

  function idbSet(key, source, translation) {
    if (!state.db) return Promise.resolve();

    return new Promise(resolve => {
      try {
        const tx = state.db.transaction(CONFIG.storeName, "readwrite");
        tx.objectStore(CONFIG.storeName).put({
          hash: key,
          source,
          translation,
          updatedAt: Date.now()
        });
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
      } catch {
        resolve();
      }
    });
  }

  /* ---------------- localStorage fallback/cache ---------------- */

  function loadLocalCache() {
    try {
      const raw = localStorage.getItem(CONFIG.localStorageKey);
      const parsed = raw ? JSON.parse(raw) : {};
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch {
      return {};
    }
  }

  const localCache = loadLocalCache();

  function saveLocalCache() {
    try {
      localStorage.setItem(
        CONFIG.localStorageKey,
        JSON.stringify(localCache)
      );
    } catch (e) {
      console.warn("[Translator] localStorage write failed:", e);
    }
  }

  /* ---------------- Glossary ---------------- */

  function glossaryLookup(source) {
    const exact = normalize(source);

    if (Object.prototype.hasOwnProperty.call(CONFIG.glossary, exact)) {
      return CONFIG.glossary[exact];
    }

    return null;
  }

  /* ---------------- Cache ---------------- */

  async function cacheGet(source) {
    const text = normalize(source);
    if (!text) return null;

    if (state.memoryCache.has(text)) {
      return state.memoryCache.get(text);
    }

    const key = await hash(text);

    // localStorage first
    if (localCache[key]) {
      state.memoryCache.set(text, localCache[key]);
      return localCache[key];
    }

    // IndexedDB
    const idbValue = await idbGet(key);
    if (idbValue) {
      state.memoryCache.set(text, idbValue);
      localCache[key] = idbValue;
      saveLocalCache();
      return idbValue;
    }

    return null;
  }

  async function cacheSet(source, translation) {
    const text = normalize(source);
    const result = normalize(translation);
    if (!text || !result) return;

    const key = await hash(text);

    state.memoryCache.set(text, result);
    localCache[key] = result;
    saveLocalCache();

    await idbSet(key, text, result);
  }

  /* ---------------- DOM collection ---------------- */

  function collectTextNodes(root = document.body) {
    const result = [];
    if (!root) return result;

    const walker = document.createTreeWalker(
      root,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode(node) {
          const parent = node.parentElement;
          if (!parent || ignored(parent)) {
            return NodeFilter.FILTER_REJECT;
          }

          const value = normalize(node.nodeValue);
          if (!value || !isPersian(value)) {
            return NodeFilter.FILTER_REJECT;
          }

          return NodeFilter.FILTER_ACCEPT;
        }
      }
    );

    let node;
    while ((node = walker.nextNode())) result.push(node);
    return result;
  }

  function collectAttributes(root = document.body) {
    const result = [];
    if (!root?.querySelectorAll) return result;

    for (const el of root.querySelectorAll("*")) {
      if (ignored(el)) continue;

      for (const attr of CONFIG.attributes) {
        if (!el.hasAttribute(attr)) continue;

        const value = normalize(el.getAttribute(attr));
        if (!value || !isPersian(value)) continue;

        result.push({ el, attr, value });
      }
    }

    return result;
  }

  /* ---------------- API ---------------- */

  function stripFences(text) {
    return String(text)
      .trim()
      .replace(/^```(?:json)?\s*/i, "")
      .replace(/\s*```$/i, "")
      .trim();
  }

  async function callAPI(texts) {
    if (!CONFIG.apiKey || CONFIG.apiKey === "YOUR_API_KEY") {
      throw new Error("API key is not configured in translation.js");
    }

    const response = await fetch(CONFIG.endpoint, {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${CONFIG.apiKey}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        model: CONFIG.model,
        messages: [
          {
            role: "system",
            content:
              "You are a professional Persian-to-English website translator. " +
              "Translate each item naturally and accurately. Preserve names, " +
              "numbers, punctuation, URLs, technical terms and placeholders. " +
              "Return ONLY a JSON array of strings, exactly one output per input."
          },
          {
            role: "user",
            content: JSON.stringify(texts)
          }
        ],
        temperature: 0.1,
        stream: false
      })
    });

    if (!response.ok) {
      const body = await response.text().catch(() => "");
      throw new Error(`API HTTP ${response.status}: ${body.slice(0, 500)}`);
    }

    const data = await response.json();

    const content =
      data?.choices?.[0]?.message?.content ??
      data?.choices?.[0]?.text ??
      "";

    if (!content) throw new Error("Empty translation response");

    let parsed;

    try {
      parsed = JSON.parse(stripFences(content));
    } catch {
      const match = content.match(/\[[\s\S]*\]/);
      if (!match) throw new Error("Invalid JSON array returned by model");
      parsed = JSON.parse(match[0]);
    }

    if (!Array.isArray(parsed)) {
      throw new Error("Translation response is not an array");
    }

    return parsed.map(x => String(x ?? ""));
  }

  /* ---------------- Batch translation ---------------- */

  async function translateTexts(texts) {
    const unique = [...new Set(texts.map(normalize).filter(Boolean))];
    const result = {};
    const missing = [];

    // Glossary + cache
    for (const text of unique) {
      const glossary = glossaryLookup(text);

      if (glossary) {
        result[text] = glossary;
        await cacheSet(text, glossary);
        continue;
      }

      const cached = await cacheGet(text);

      if (cached) {
        result[text] = cached;
      } else {
        missing.push(text);
      }
    }

    if (!missing.length) return result;

    const groups = [];
    let current = [];
    let chars = 0;

    for (const text of missing) {
      const size = text.length + 40;

      if (
        current.length >= CONFIG.batchSize ||
        (chars + size > CONFIG.maxCharsPerRequest && current.length)
      ) {
        groups.push(current);
        current = [];
        chars = 0;
      }

      current.push(text);
      chars += size;
    }

    if (current.length) groups.push(current);

    for (const group of groups) {
      const translated = await callAPI(group);

      for (let i = 0; i < group.length; i++) {
        const source = group[i];
        const target = normalize(translated[i] || source);

        result[source] = target;

        // Persist immediately.
        await cacheSet(source, target);
      }

      await sleep(10);
    }

    return result;
  }

  /* ---------------- Translation / restore ---------------- */

  async function translatePage() {
    if (state.processing) return;

    state.processing = true;
    setBusy(true);

    try {
      const nodes = collectTextNodes();
      const attrs = collectAttributes();

      const all = [
        ...nodes.map(n => normalize(n.nodeValue)),
        ...attrs.map(x => x.value)
      ];

      const translations = await translateTexts(all);

      for (const node of nodes) {
        if (!state.originalNodes.has(node)) {
          state.originalNodes.set(node, node.nodeValue);
        }

        const source = normalize(node.nodeValue);
        const target = translations[source];

        if (target && target !== source) {
          node.nodeValue = target;
          state.translatedNodes.add(node);
        }
      }

      for (const item of attrs) {
        let map = state.originalAttributes.get(item.el);

        if (!map) {
          map = {};
          state.originalAttributes.set(item.el, map);
        }

        if (!(item.attr in map)) {
          map[item.attr] = item.value;
        }

        const target = translations[item.value];

        if (target && target !== item.value) {
          item.el.setAttribute(item.attr, target);
          state.translatedElements.add(item.el);
        }
      }

      document.documentElement.lang = "en";
      document.documentElement.dir = "ltr";
      localStorage.setItem(CONFIG.languageKey, "en");
      updateButton("🇮🇷", "بازگشت به فارسی");
    } catch (error) {
      console.error("[Universal Translator]", error);
      showError(error.message);
    } finally {
      state.processing = false;
      setBusy(false);
    }
  }

  function restorePage() {
    for (const node of state.translatedNodes) {
      const original = state.originalNodes.get(node);
      if (original != null && node.isConnected) {
        node.nodeValue = original;
      }
    }

    for (const el of state.translatedElements) {
      const map = state.originalAttributes.get(el);
      if (!map || !el.isConnected) continue;

      for (const [attr, value] of Object.entries(map)) {
        el.setAttribute(attr, value);
      }
    }

    state.translatedNodes.clear();
    state.translatedElements.clear();

    document.documentElement.lang = "fa";
    document.documentElement.dir = "rtl";
    localStorage.setItem(CONFIG.languageKey, "fa");

    updateButton("🇬🇧", "Translate to English");
  }

  /* ---------------- Dynamic content ---------------- */

  function setupObserver() {
    if (state.observer || !document.body) return;

    let timer = null;

    state.observer = new MutationObserver(mutations => {
      const language =
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage;

      if (language !== "en") return;

      clearTimeout(timer);

      timer = setTimeout(async () => {
        if (state.processing) return;

        const nodes = [];

        for (const mutation of mutations) {
          for (const added of mutation.addedNodes) {
            if (added.nodeType === Node.TEXT_NODE) {
              if (isPersian(added.nodeValue)) nodes.push(added);
            } else if (added.nodeType === Node.ELEMENT_NODE) {
              nodes.push(...collectTextNodes(added));
            }
          }
        }

        if (!nodes.length) return;

        state.processing = true;
        setBusy(true);

        try {
          for (const node of nodes) {
            if (!state.originalNodes.has(node)) {
              state.originalNodes.set(node, node.nodeValue);
            }
          }

          const translations = await translateTexts(
            nodes.map(n => normalize(n.nodeValue))
          );

          for (const node of nodes) {
            const source = normalize(node.nodeValue);
            const target = translations[source];

            if (target && target !== source) {
              node.nodeValue = target;
              state.translatedNodes.add(node);
            }
          }
        } catch (error) {
          console.error("[Universal Translator observer]", error);
        } finally {
          state.processing = false;
          setBusy(false);
        }
      }, 300);
    });

    state.observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /* ---------------- UI ---------------- */

  function createButton() {
    if (document.getElementById("universal-translator-button")) return;

    const button = document.createElement("button");
    button.id = "universal-translator-button";
    button.type = "button";
    button.textContent = "🇬🇧";
    button.title = "Translate to English";
    button.setAttribute("aria-label", "Translate to English");

    Object.assign(button.style, {
      position: "fixed",
      right: "16px",
      bottom: "16px",
      width: "42px",
      height: "42px",
      border: "0",
      borderRadius: "50%",
      background: "rgba(20,20,20,.92)",
      color: "#fff",
      cursor: "pointer",
      zIndex: "2147483647",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      fontSize: "20px",
      lineHeight: "1",
      padding: "0",
      boxShadow: "0 4px 16px rgba(0,0,0,.25)",
      transition: "transform .15s ease, opacity .15s ease"
    });

    button.addEventListener("mouseenter", () => {
      button.style.transform = "scale(1.08)";
    });

    button.addEventListener("mouseleave", () => {
      button.style.transform = "scale(1)";
    });

    button.addEventListener("click", () => {
      const language =
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage;

      if (language === "en") {
        restorePage();
      } else {
        translatePage();
      }
    });

    document.body.appendChild(button);
  }

  function updateButton(icon, title) {
    const button =
      document.getElementById("universal-translator-button");

    if (!button) return;

    button.textContent = icon;
    button.title = title;
    button.setAttribute("aria-label", title);
  }

  function setBusy(busy) {
    const button =
      document.getElementById("universal-translator-button");

    if (!button) return;

    button.disabled = busy;
    button.style.opacity = busy ? ".55" : "1";
    button.style.cursor = busy ? "wait" : "pointer";
  }

  function showError(message) {
    document.getElementById("universal-translator-error")?.remove();

    const box = document.createElement("div");
    box.id = "universal-translator-error";

    Object.assign(box.style, {
      position: "fixed",
      right: "16px",
      bottom: "68px",
      maxWidth: "380px",
      padding: "10px 12px",
      borderRadius: "10px",
      background: "#2b1111",
      color: "#ffdede",
      font: "13px/1.5 Arial,sans-serif",
      zIndex: "2147483647",
      boxShadow: "0 5px 20px rgba(0,0,0,.25)"
    });

    box.textContent = "Translator: " + message;
    document.body.appendChild(box);

    setTimeout(() => box.remove(), 8000);
  }

  /* ---------------- Diagnostics ---------------- */

  async function cacheInfo() {
    const localEntries = Object.keys(localCache).length;
    let idbEntries = null;

    if (state.db) {
      idbEntries = await new Promise(resolve => {
        try {
          const tx = state.db.transaction(CONFIG.storeName, "readonly");
          const req = tx.objectStore(CONFIG.storeName).count();
          req.onsuccess = () => resolve(req.result);
          req.onerror = () => resolve(null);
        } catch {
          resolve(null);
        }
      });
    }

    const info = {
      memoryEntries: state.memoryCache.size,
      localStorageEntries: localEntries,
      indexedDBEntries: idbEntries,
      language:
        localStorage.getItem(CONFIG.languageKey) ||
        CONFIG.defaultLanguage
    };

    console.table(info);
    return info;
  }

  async function clearCache() {
    state.memoryCache.clear();

    for (const key of Object.keys(localCache)) {
      delete localCache[key];
    }

    localStorage.removeItem(CONFIG.localStorageKey);

    if (state.db) {
      await new Promise(resolve => {
        try {
          const tx = state.db.transaction(CONFIG.storeName, "readwrite");
          tx.objectStore(CONFIG.storeName).clear();
          tx.oncomplete = () => resolve();
          tx.onerror = () => resolve();
        } catch {
          resolve();
        }
      });
    }

    console.info("[Universal Translator] All translation caches cleared.");
  }

  /* ---------------- Init ---------------- */

  async function initialize() {
    if (state.initialized) return;
    state.initialized = true;

    try {
      state.db = await openDB();
    } catch {
      state.db = null;
    }

    createButton();
    setupObserver();

    const language =
      localStorage.getItem(CONFIG.languageKey) ||
      CONFIG.defaultLanguage;

    if (language === "fa") {
      updateButton("🇬🇧", "Translate to English");
      document.documentElement.lang = "fa";
      document.documentElement.dir = "rtl";
      return;
    }

    setTimeout(() => translatePage(), 150);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, {
      once: true
    });
  } else {
    initialize();
  }

  window.UniversalTranslator = {
    translate: translatePage,
    restore: restorePage,
    cacheInfo,
    clearCache,
    config: CONFIG
  };
})();
</script>
</body>
</html>
