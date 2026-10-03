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

    responseJson(false, [], 'درخواست نامعتبر است.');
}


$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($csrf) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {

    responseJson(false, [], 'نشست امنیتی معتبر نیست.');
}


$applicationRaw =
    $_POST['application'] ?? '';

if (!$applicationRaw) {

    responseJson(
        false,
        [],
        'اطلاعات متقاضی ارسال نشده است.'
    );
}


$application = json_decode(
    $applicationRaw,
    true
);

if (!is_array($application)) {

    responseJson(
        false,
        [],
        'اطلاعات متقاضی نامعتبر است.'
    );
}


$questionsRaw =
    $_POST['questions'] ?? '[]';

$questions = json_decode(
    $questionsRaw,
    true
);

$answersRaw =
    $_POST['answers'] ?? '[]';

$answers = json_decode(
    $answersRaw,
    true
);


$signature =
    $_POST['signature'] ?? '';


if (!$signature) {

    responseJson(
        false,
        [],
        'امضای متقاضی ثبت نشده است.'
    );
}


$trackingCode =
    'TOL-' .
    date('Ymd') .
    '-' .
    strtoupper(
        substr(
            bin2hex(random_bytes(5)),
            0,
            8
        )
    );


/*
|--------------------------------------------------------------------------
| PHOTO
|--------------------------------------------------------------------------
*/

$photoPath = null;

if (
    isset($_FILES['photo']) &&
    $_FILES['photo']['error'] === UPLOAD_ERR_OK
) {

    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {

        responseJson(
            false,
            [],
            'حجم تصویر بیش از ۵ مگابایت است.'
        );
    }


    $tmp = $_FILES['photo']['tmp_name'];

    $mime = mime_content_type($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];


    if (!isset($allowed[$mime])) {

        responseJson(
            false,
            [],
            'فرمت تصویر مجاز نیست.'
        );
    }


    $filename =
        $trackingCode .
        '.' .
        $allowed[$mime];


    $destination =
        PHOTOS_DIR .
        '/' .
        $filename;


    if (!move_uploaded_file(
        $tmp,
        $destination
    )) {

        responseJson(
            false,
            [],
            'ذخیره تصویر انجام نشد.'
        );
    }


    $photoPath =
        'data/photos/' .
        $filename;
}


/*
|--------------------------------------------------------------------------
| SIGNATURE
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '#^data:image/png;base64,#',
        $signature
    )
) {

    responseJson(
        false,
        [],
        'فرمت امضا معتبر نیست.'
    );
}


$signatureData =
    preg_replace(
        '#^data:image/png;base64,#',
        '',
        $signature
    );


$signatureBinary =
    base64_decode(
        $signatureData,
        true
    );


if ($signatureBinary === false) {

    responseJson(
        false,
        [],
        'امضا قابل پردازش نیست.'
    );
}


$signatureFilename =
    $trackingCode .
    '.png';


$signatureDestination =
    SIGNATURES_DIR .
    '/' .
    $signatureFilename;


if (
    file_put_contents(
        $signatureDestination,
        $signatureBinary,
        LOCK_EX
    ) === false
) {

    responseJson(
        false,
        [],
        'ذخیره امضا انجام نشد.'
    );
}


$signaturePath =
    'data/signatures/' .
    $signatureFilename;


/*
|--------------------------------------------------------------------------
| FINAL JSON
|--------------------------------------------------------------------------
*/

$record = [

    'system' => [

        'company' =>
            'شرکت صنایع چوب و فلز تولیکا',

        'system' =>
            APP_NAME,

        'tracking_code' =>
            $trackingCode,

        'created_at' =>
            date('c'),

        'version' =>
            '1.0.0'

    ],


    'applicant' =>
        $application,


    'ai_assessment' => [

        'questions' =>
            is_array($questions)
                ? $questions
                : [],

        'answers' =>
            is_array($answers)
                ? $answers
                : []

    ],


    'files' => [

        'photo' =>
            $photoPath,

        'signature' =>
            $signaturePath

    ]

];


$jsonFilename =
    $trackingCode .
    '.json';


$jsonPath =
    APPLICANTS_DIR .
    '/' .
    $jsonFilename;


$written =
    file_put_contents(
        $jsonPath,
        json_encode(
            $record,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT,
            4
        ),
        LOCK_EX
    );


if ($written === false) {

    responseJson(
        false,
        [],
        'ثبت اطلاعات انجام نشد.'
    );
}


/*
|--------------------------------------------------------------------------
| SESSION CLEANUP
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['application']
);


responseJson(
    true,

    [
        'tracking_code' =>
            $trackingCode
    ],

    'درخواست شما با موفقیت ثبت شد.'
);
