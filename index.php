<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$csrf = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e(APP_NAME) ?></title>

<link rel="stylesheet" href="assets/style.css">

</head>

<body>

<div class="page">

    <header class="topbar">

        <div class="brand">

            <div class="logo">
                ت
            </div>

            <div>
                <h1>نسخه کاربردی - گیت هاب | سامان آراسته</h1>
                <span>سامانه هوشمند جذب و استخدام</span>
            </div>

        </div>

        <div class="secure">
            🔒 اطلاعات محرمانه
        </div>

    </header>


    <!-- STEPPER -->

    <div class="stepper">

        <div class="step active" data-step="1">
            <b>۱</b>
            <span>اطلاعات متقاضی</span>
        </div>

        <div class="line"></div>

        <div class="step" data-step="2">
            <b>۲</b>
            <span>ارزیابی هوشمند</span>
        </div>

        <div class="line"></div>

        <div class="step" data-step="3">
            <b>۳</b>
            <span>تأیید و امضا</span>
        </div>

    </div>


    <main>


        <!-- ======================================================
             STEP 1
        ======================================================= -->

        <section id="step1" class="wizard-step active">

            <div class="section-title">

                <div>
                    <h2>فرم اطلاعات متقاضی استخدام</h2>
                    <p>
                        لطفاً اطلاعات را با دقت و مطابق مدارک معتبر وارد نمایید.
                    </p>
                </div>

                <span class="required-note">
                    * الزامی
                </span>

            </div>


            <form id="applicationForm"
                  enctype="multipart/form-data"
                  autocomplete="off">


                <!-- PERSONAL -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۱</span>
                        اطلاعات شخصی و هویتی
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>نام *</label>
                            <input name="first_name" required>
                        </div>

                        <div class="field">
                            <label>نام خانوادگی *</label>
                            <input name="last_name" required>
                        </div>

                        <div class="field">
                            <label>نام پدر</label>
                            <input name="father_name">
                        </div>

                        <div class="field">
                            <label>کد ملی *</label>
                            <input name="national_id"
                                   maxlength="10"
                                   inputmode="numeric"
                                   required>
                        </div>

                        <div class="field">
                            <label>شماره شناسنامه</label>
                            <input name="birth_certificate">
                        </div>

                        <div class="field">
                            <label>تاریخ تولد</label>
                            <input type="date" name="birth_date">
                        </div>

                        <div class="field">
                            <label>محل تولد</label>
                            <input name="birth_place">
                        </div>

                        <div class="field">

                            <label>جنسیت</label>

                            <select name="gender">

                                <option value="">انتخاب کنید</option>
                                <option>مرد</option>
                                <option>زن</option>

                            </select>

                        </div>

                        <div class="field">

                            <label>وضعیت تأهل</label>

                            <select name="marital_status">

                                <option value="">انتخاب کنید</option>
                                <option>مجرد</option>
                                <option>متأهل</option>
                                <option>سایر</option>

                            </select>

                        </div>

                        <div class="field">
                            <label>تعداد فرزند</label>
                            <input type="number"
                                   name="children"
                                   min="0">
                        </div>

                    </div>

                </div>


                <!-- PHOTO -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۲</span>
                        تصویر پرسنلی
                    </div>

                    <div class="photo-area">

                        <div class="photo-box" id="photoPreview">

                            <span>تصویر پرسنلی</span>

                        </div>

                        <div class="photo-actions">

                            <label class="upload-button">

                                انتخاب تصویر

                                <input
                                    type="file"
                                    name="photo"
                                    id="photoInput"
                                    accept="image/jpeg,image/png,image/webp"
                                    hidden>

                            </label>

                            <button type="button"
                                    class="secondary"
                                    id="cameraButton">
                                📷 استفاده از دوربین
                            </button>

                            <small>
                                JPG / PNG / WEBP - حداکثر ۵ مگابایت
                            </small>

                        </div>

                    </div>

                </div>


                <!-- CONTACT -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۳</span>
                        اطلاعات تماس و نشانی
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>شماره موبایل *</label>
                            <input name="mobile"
                                   type="tel"
                                   required>
                        </div>

                        <div class="field">
                            <label>تلفن ثابت</label>
                            <input name="phone">
                        </div>

                        <div class="field">
                            <label>ایمیل</label>
                            <input type="email" name="email">
                        </div>

                        <div class="field">
                            <label>شهر</label>
                            <input name="city">
                        </div>

                    </div>

                    <div class="field full">
                        <label>نشانی کامل</label>
                        <textarea name="address"></textarea>
                    </div>

                </div>


                <!-- EDUCATION -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۴</span>
                        سوابق تحصیلی
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>آخرین مقطع تحصیلی</label>

                            <select name="education_level">

                                <option value="">انتخاب</option>
                                <option>دیپلم</option>
                                <option>کاردانی</option>
                                <option>کارشناسی</option>
                                <option>کارشناسی ارشد</option>
                                <option>دکتری</option>

                            </select>

                        </div>

                        <div class="field">
                            <label>رشته تحصیلی</label>
                            <input name="major">
                        </div>

                        <div class="field">
                            <label>گرایش</label>
                            <input name="field_of_study">
                        </div>

                        <div class="field">
                            <label>دانشگاه / مؤسسه</label>
                            <input name="university">
                        </div>

                        <div class="field">
                            <label>سال فارغ‌التحصیلی</label>
                            <input name="graduation_year">
                        </div>

                        <div class="field">
                            <label>معدل</label>
                            <input name="gpa">
                        </div>

                    </div>

                </div>


                <!-- WORK EXPERIENCE -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۵</span>
                        سوابق کاری
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>آخرین عنوان شغلی</label>
                            <input name="last_job_title">
                        </div>

                        <div class="field">
                            <label>آخرین محل کار</label>
                            <input name="last_company">
                        </div>

                        <div class="field">
                            <label>سابقه کار کل</label>
                            <input name="total_experience"
                                   placeholder="مثلاً ۵ سال">
                        </div>

                        <div class="field">
                            <label>حقوق آخرین شغل</label>
                            <input name="last_salary">
                        </div>

                        <div class="field">
                            <label>دلیل ترک آخرین شغل</label>
                            <input name="leaving_reason">
                        </div>

                    </div>

                    <div class="field full">
                        <label>شرح سوابق و مسئولیت‌های مهم</label>

                        <textarea
                            name="work_history"
                            rows="5"></textarea>

                    </div>

                </div>


                <!-- JOB -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۶</span>
                        اطلاعات شغلی مورد درخواست
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>عنوان شغلی مورد درخواست *</label>
                            <input name="desired_position"
                                   required>
                        </div>

                        <div class="field">
                            <label>واحد مورد علاقه</label>
                            <input name="desired_department">
                        </div>

                        <div class="field">
                            <label>نوع همکاری مورد نظر</label>

                            <select name="employment_type">

                                <option value="">انتخاب</option>
                                <option>تمام وقت</option>
                                <option>پاره وقت</option>
                                <option>پروژه‌ای</option>
                                <option>قراردادی</option>

                            </select>

                        </div>

                        <div class="field">
                            <label>حداقل حقوق مورد انتظار</label>
                            <input name="expected_salary">
                        </div>

                        <div class="field">
                            <label>تاریخ آمادگی شروع</label>
                            <input type="date"
                                   name="available_from">
                        </div>

                    </div>

                </div>


                <!-- SKILLS -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۷</span>
                        مهارت‌ها، نرم‌افزارها و زبان‌ها
                    </div>

                    <div class="field full">
                        <label>مهارت‌های تخصصی</label>
                        <textarea name="technical_skills"
                                  placeholder="مهارت‌های تخصصی خود را وارد کنید"></textarea>
                    </div>

                    <div class="field full">
                        <label>نرم‌افزارها و ابزارهای کاری</label>
                        <textarea name="software_skills"></textarea>
                    </div>

                    <div class="field full">
                        <label>زبان‌های خارجی و سطح تسلط</label>
                        <textarea name="languages"></textarea>
                    </div>

                </div>


                <!-- COURSES -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۸</span>
                        دوره‌ها و گواهینامه‌ها
                    </div>

                    <div class="field full">

                        <label>
                            دوره‌ها، گواهینامه‌های حرفه‌ای و آموزش‌های تخصصی
                        </label>

                        <textarea name="courses"
                                  rows="5"></textarea>

                    </div>

                </div>


                <!-- MILITARY -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۰۹</span>
                        وضعیت نظام وظیفه
                    </div>

                    <div class="grid">

                        <div class="field">

                            <label>وضعیت</label>

                            <select name="military_status">

                                <option value="">انتخاب</option>
                                <option>مشمول</option>
                                <option>در حال خدمت</option>
                                <option>پایان خدمت</option>
                                <option>معافیت دائم</option>
                                <option>معافیت موقت</option>
                                <option>غیرمشمول</option>

                            </select>

                        </div>

                        <div class="field">
                            <label>نوع معافیت / توضیحات</label>
                            <input name="military_description">
                        </div>

                    </div>

                </div>


                <!-- REFERENCES -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۱۰</span>
                        معرف و مراجع شغلی
                    </div>

                    <div class="grid">

                        <div class="field">
                            <label>نام و نام خانوادگی معرف</label>
                            <input name="reference_name">
                        </div>

                        <div class="field">
                            <label>سمت / نسبت</label>
                            <input name="reference_relation">
                        </div>

                        <div class="field">
                            <label>شرکت / سازمان</label>
                            <input name="reference_company">
                        </div>

                        <div class="field">
                            <label>شماره تماس</label>
                            <input name="reference_phone">
                        </div>

                    </div>

                </div>


                <!-- BEHAVIORAL / BACKGROUND -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۱۱</span>
                        سوابق و اطلاعات تکمیلی
                    </div>

                    <div class="question">

                        <label>
                            آیا سابقه محکومیت کیفری مؤثر دارید؟
                        </label>

                        <div class="radio-group">

                            <label>
                                <input type="radio"
                                       name="criminal_record"
                                       value="خیر"
                                       checked>
                                خیر
                            </label>

                            <label>
                                <input type="radio"
                                       name="criminal_record"
                                       value="بله">
                                بله
                            </label>

                        </div>

                    </div>


                    <div class="question">

                        <label>
                            آیا سابقه اخراج، فسخ قرارداد یا قطع همکاری
                            به دلیل مسائل انضباطی داشته‌اید؟
                        </label>

                        <div class="radio-group">

                            <label>
                                <input type="radio"
                                       name="disciplinary_record"
                                       value="خیر"
                                       checked>
                                خیر
                            </label>

                            <label>
                                <input type="radio"
                                       name="disciplinary_record"
                                       value="بله">
                                بله
                            </label>

                        </div>

                    </div>


                    <div class="question">

                        <label>
                            آیا محدودیت یا شرایطی دارید که مستقیماً
                            بر توانایی انجام وظایف شغل مورد درخواست
                            اثر بگذارد؟
                        </label>

                        <div class="radio-group">

                            <label>
                                <input type="radio"
                                       name="work_limitation"
                                       value="خیر"
                                       checked>
                                خیر
                            </label>

                            <label>
                                <input type="radio"
                                       name="work_limitation"
                                       value="بله">
                                بله
                            </label>

                        </div>

                    </div>

                </div>


                <!-- TOBACCO -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۱۲</span>
                        دخانیات و الکل
                    </div>

                    <div class="grid">

                        <div class="field">

                            <label>وضعیت مصرف دخانیات</label>

                            <select name="smoking">

                                <option>هرگز مصرف نکرده‌ام</option>
                                <option>قبلاً مصرف می‌کردم</option>
                                <option>گاهی</option>
                                <option>روزانه</option>

                            </select>

                        </div>

                        <div class="field">

                            <label>مصرف الکل</label>

                            <select name="alcohol">

                                <option>خیر</option>
                                <option>گاهی</option>
                                <option>قبلاً مصرف می‌کردم</option>
                                <option>ترجیح می‌دهم پاسخ ندهم</option>

                            </select>

                        </div>

                    </div>

                    <div class="notice">
                        این اطلاعات صرفاً برای ارزیابی الزامات شغلی،
                        محیط کار و الزامات سازمانی دریافت می‌شود و
                        نباید مبنای تشخیص پزشکی یا روان‌شناختی قرار گیرد.
                    </div>

                </div>


                <!-- ABOUT -->

                <div class="form-section">

                    <div class="section-heading">
                        <span>۱۳</span>
                        معرفی و انگیزه
                    </div>

                    <div class="field full">

                        <label>
                            خودتان را معرفی کنید و درباره انگیزه خود برای
                            همکاری با تولیکا توضیح دهید.
                        </label>

                        <textarea
                            name="motivation"
                            rows="6"></textarea>

                    </div>

                </div>


                <!-- CONSENT -->

                <div class="form-section consent">

                    <label>

                        <input type="checkbox"
                               name="consent"
                               value="1"
                               required>

                        صحت اطلاعات واردشده را تأیید می‌کنم و اجازه می‌دهم
                        اطلاعات ارائه‌شده برای فرآیند جذب و ارزیابی شغلی
                        شرکت صنایع چوب و فلز تولیکا مورد استفاده قرار گیرد.

                    </label>

                </div>


                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($csrf) ?>">


                <div class="wizard-actions">

                    <button type="submit"
                            class="primary"
                            id="nextStep1">

                        مرحله بعد
                        <span>←</span>

                    </button>

                </div>


            </form>

        </section>



        <!-- ======================================================
             STEP 2
        ======================================================= -->

        <section id="step2" class="wizard-step">

            <div class="section-title">

                <div>

                    <h2>ارزیابی هوشمند شغلی</h2>

                    <p>
                        سه سؤال متناسب با اطلاعات شغلی شما توسط
                        سامانه هوشمند طراحی شده است.
                    </p>

                </div>

            </div>


            <div id="aiLoading" class="loading">

                <div class="spinner"></div>

                <h3>در حال طراحی سؤالات اختصاصی...</h3>

                <p>
                    لطفاً چند لحظه صبر کنید.
                </p>

            </div>


            <div id="questionsContainer"
                 class="questions-container">
            </div>


            <div class="wizard-actions">

                <button type="button"
                        class="secondary"
                        id="backToStep1">

                    → مرحله قبل

                </button>

                <button type="button"
                        class="primary"
                        id="nextStep2"
                        disabled>

                    مرحله بعد ←

                </button>

            </div>

        </section>



        <!-- ======================================================
             STEP 3
        ======================================================= -->

        <section id="step3" class="wizard-step">

            <div class="section-title">

                <div>

                    <h2>تأیید نهایی و امضای متقاضی</h2>

                    <p>
                        اطلاعات را بررسی و سپس امضای خود را ثبت کنید.
                    </p>

                </div>

            </div>


            <div id="finalSummary"
                 class="summary">
            </div>


            <div class="signature-section">

                <h3>امضای متقاضی</h3>

                <p>
                    لطفاً داخل کادر زیر با ماوس، قلم نوری یا لمس انگشت امضا کنید.
                </p>

                <canvas id="signaturePad"
                        width="800"
                        height="260">
                </canvas>

                <div class="signature-actions">

                    <button type="button"
                            class="secondary"
                            id="clearSignature">

                        پاک کردن امضا

                    </button>

                </div>

            </div>


            <div class="wizard-actions">

                <button type="button"
                        class="secondary"
                        id="backToStep2">

                    → مرحله قبل

                </button>

                <button type="button"
                        class="primary"
                        id="submitApplication">

                    ثبت نهایی درخواست

                </button>

            </div>


            <div id="submitResult"
                 class="submit-result">
            </div>

        </section>


    </main>


    <footer>

        <strong>نسخه کاربردی مبتنی بر هوش مصنوعی - گیت هاب | سامان آراسته</strong>

        <span>
            سامانه هوشمند جذب و استخدام
        </span>

    </footer>

</div>


<!-- CAMERA MODAL -->

<div id="cameraModal"
     class="modal">

    <div class="modal-content">

        <button id="closeCamera"
                class="close-modal">
            ×
        </button>

        <h3>تصویر پرسنلی</h3>

        <video id="camera"
               autoplay
               playsinline>
        </video>

        <button id="takePhoto"
                class="primary">
            ثبت تصویر
        </button>

        <canvas id="cameraCanvas"
                hidden>
        </canvas>

    </div>

</div>


<script>

window.TOLIKA = {
    csrf: <?= json_encode($csrf) ?>
};

</script>

<script src="assets/app.js"></script>
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
