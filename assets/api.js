(() => {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    const state = {

        currentStep: 1,

        application: {},

        questions: [],

        answers: [],

        photoFile: null,

        photoPreview: null

    };


    const form =
        document.getElementById('applicationForm');

    const questionsContainer =
        document.getElementById('questionsContainer');

    const aiLoading =
        document.getElementById('aiLoading');

    const nextStep2 =
        document.getElementById('nextStep2');

    const photoInput =
        document.getElementById('photoInput');

    const photoPreview =
        document.getElementById('photoPreview');


    /*
    |--------------------------------------------------------------------------
    | STEP
    |--------------------------------------------------------------------------
    */

    function showStep(step) {

        document
            .querySelectorAll('.wizard-step')
            .forEach(element => {

                element.classList.remove('active');

            });


        const target =
            document.getElementById(
                `step${step}`
            );


        if (target) {
            target.classList.add('active');
        }


        document
            .querySelectorAll('.step')
            .forEach(element => {

                const number =
                    Number(
                        element.dataset.step
                    );

                element.classList.toggle(
                    'active',
                    number <= step
                );

            });


        state.currentStep = step;


        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }


    /*
    |--------------------------------------------------------------------------
    | FORM DATA
    |--------------------------------------------------------------------------
    */

    function collectFormData() {

        const formData =
            new FormData(form);

        const object = {};

        formData.forEach(
            (value, key) => {

                if (key === 'photo') {
                    return;
                }

                if (
                    key === 'csrf_token' ||
                    key === 'consent'
                ) {
                    return;
                }

                object[key] = value;

            }
        );


        return object;
    }


    /*
    |--------------------------------------------------------------------------
    | PHOTO
    |--------------------------------------------------------------------------
    */

    photoInput.addEventListener(
        'change',
        event => {

            const file =
                event.target.files[0];

            if (!file) {
                return;
            }


            if (file.size > 5 * 1024 * 1024) {

                alert(
                    'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.'
                );

                photoInput.value = '';

                return;
            }


            const allowedTypes = [

                'image/jpeg',
                'image/png',
                'image/webp'

            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                alert(
                    'فرمت تصویر مجاز نیست.'
                );

                photoInput.value = '';

                return;
            }


            state.photoFile = file;


            const reader =
                new FileReader();


            reader.onload = e => {

                state.photoPreview =
                    e.target.result;

                photoPreview.innerHTML =
                    `<img src="${e.target.result}"
                           alt="تصویر پرسنلی">`;

            };


            reader.readAsDataURL(file);

        }
    );


    /*
    |--------------------------------------------------------------------------
    | CAMERA
    |--------------------------------------------------------------------------
    */

    const cameraModal =
        document.getElementById(
            'cameraModal'
        );

    const camera =
        document.getElementById(
            'camera'
        );

    const cameraCanvas =
        document.getElementById(
            'cameraCanvas'
        );

    const cameraButton =
        document.getElementById(
            'cameraButton'
        );

    const closeCamera =
        document.getElementById(
            'closeCamera'
        );

    const takePhoto =
        document.getElementById(
            'takePhoto'
        );


    let cameraStream = null;


    cameraButton.addEventListener(
        'click',
        async () => {

            try {

                cameraStream =
                    await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user'
                        },
                        audio: false
                    });


                camera.srcObject =
                    cameraStream;


                cameraModal.classList.add(
                    'show'
                );

            } catch (error) {

                alert(
                    'دسترسی به دوربین امکان‌پذیر نیست.'
                );

            }

        }
    );


    function stopCamera() {

        if (!cameraStream) {
            return;
        }


        cameraStream
            .getTracks()
            .forEach(track => track.stop());


        cameraStream = null;

    }


    closeCamera.addEventListener(
        'click',
        () => {

            stopCamera();

            cameraModal.classList.remove(
                'show'
            );

        }
    );


    takePhoto.addEventListener(
        'click',
        () => {

            const width =
                camera.videoWidth;

            const height =
                camera.videoHeight;


            cameraCanvas.width =
                width;

            cameraCanvas.height =
                height;


            const context =
                cameraCanvas.getContext(
                    '2d'
                );


            context.drawImage(
                camera,
                0,
                0,
                width,
                height
            );


            cameraCanvas.toBlob(
                blob => {

                    if (!blob) {
                        return;
                    }


                    state.photoFile =
                        new File(
                            [blob],
                            'portrait.jpg',
                            {
                                type:
                                    'image/jpeg'
                            }
                        );


                    const url =
                        URL.createObjectURL(
                            state.photoFile
                        );


                    state.photoPreview =
                        url;


                    photoPreview.innerHTML =
                        `<img src="${url}"
                               alt="تصویر پرسنلی">`;


                    stopCamera();


                    cameraModal.classList.remove(
                        'show'
                    );

                },
                'image/jpeg',
                0.92
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | STEP 1 -> STEP 2
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        async event => {

            event.preventDefault();


            if (!form.reportValidity()) {
                return;
            }


            state.application =
                collectFormData();


            showStep(2);


            await generateQuestions();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | AI QUESTIONS
    |--------------------------------------------------------------------------
    */

    async function generateQuestions() {

        aiLoading.style.display =
            'block';

        questionsContainer.innerHTML =
            '';

        nextStep2.disabled =
            true;


        try {

            const response =
                await fetch(
                    'api.php',
                    {

                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded'
                        },

                        body:
                            new URLSearchParams({

                                csrf_token:
                                    window.TOLIKA.csrf,

                                application:
                                    JSON.stringify(
                                        state.application
                                    )

                            })

                    }
                );


            const result =
                await response.json();


            if (!result.success) {

                throw new Error(
                    result.message ||
                    'خطا در دریافت سؤالات'
                );

            }


            state.questions =
                result.data.questions;


            renderQuestions();


        } catch (error) {

            questionsContainer.innerHTML = `

                <div class="ai-question">

                    <h3>خطا</h3>

                    <p>
                        ${escapeHtml(
                            error.message
                        )}
                    </p>

                    <button
                        type="button"
                        class="primary"
                        onclick="location.reload()">

                        تلاش مجدد

                    </button>

                </div>

            `;

        } finally {

            aiLoading.style.display =
                'none';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER QUESTIONS
    |--------------------------------------------------------------------------
    */

    function renderQuestions() {

        questionsContainer.innerHTML =
            '';


        state.questions
            .forEach(
                (question, index) => {

                    const div =
                        document.createElement(
                            'div'
                        );


                    div.className =
                        'ai-question';


                    div.innerHTML = `

                        <h3>
                            سؤال ${index + 1}
                        </h3>

                        <p>
                            <strong>
                                شایستگی مورد ارزیابی:
                            </strong>
                            ${escapeHtml(
                                question.competency ||
                                ''
                            )}
                        </p>

                        <p>
                            ${escapeHtml(
                                question.question ||
                                ''
                            )}
                        </p>

                        <textarea
                            data-question-index="${index}"
                            placeholder="پاسخ خود را با ذکر مثال واقعی توضیح دهید..."
                            required></textarea>

                    `;


                    questionsContainer.appendChild(
                        div
                    );

                }
            );


        questionsContainer
            .querySelectorAll('textarea')
            .forEach(textarea => {

                textarea.addEventListener(
                    'input',
                    checkAnswers
                );

            });


        checkAnswers();

    }


    function checkAnswers() {

        const fields =
            questionsContainer
                .querySelectorAll(
                    'textarea'
                );


        const valid =
            fields.length === 3 &&
            [...fields].every(
                field =>
                    field.value.trim().length >= 10
            );


        nextStep2.disabled =
            !valid;

    }


    /*
    |--------------------------------------------------------------------------
    | STEP 2 -> STEP 3
    |--------------------------------------------------------------------------
    */

    nextStep2.addEventListener(
        'click',
        () => {

            const fields =
                questionsContainer
                    .querySelectorAll(
                        'textarea'
                    );


            state.answers =
                [...fields]
                    .map(
                        (field, index) => ({

                            question_id:
                                state.questions[index].id,

                            competency:
                                state.questions[index].competency,

                            question:
                                state.questions[index].question,

                            answer:
                                field.value.trim()

                        })
                    );


            renderSummary();


            showStep(3);

        }
    );


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    function renderSummary() {

        const summary =
            document.getElementById(
                'finalSummary'
            );


        const first =
            state.application.first_name ||
            '';

        const last =
            state.application.last_name ||
            '';

        const position =
            state.application.desired_position ||
            '';

        const mobile =
            state.application.mobile ||
            '';


        summary.innerHTML = `

            <h3>
                خلاصه اطلاعات ثبت‌شده
            </h3>

            <div class="summary-grid">

                <div class="summary-item">

                    <small>نام و نام خانوادگی</small>

                    <strong>
                        ${escapeHtml(
                            first + ' ' + last
                        )}
                    </strong>

                </div>

                <div class="summary-item">

                    <small>کد ملی</small>

                    <strong>
                        ${escapeHtml(
                            state.application.national_id || ''
                        )}
                    </strong>

                </div>

                <div class="summary-item">

                    <small>شماره موبایل</small>

                    <strong>
                        ${escapeHtml(mobile)}
                    </strong>

                </div>

                <div class="summary-item">

                    <small>عنوان شغلی</small>

                    <strong>
                        ${escapeHtml(position)}
                    </strong>

                </div>

            </div>

            <p>
                در صورت صحت اطلاعات، امضای خود را در پایین صفحه ثبت
                و درخواست را نهایی کنید.
            </p>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | SIGNATURE PAD
    |--------------------------------------------------------------------------
    */

    const canvas =
        document.getElementById(
            'signaturePad'
        );

    const ctx =
        canvas.getContext('2d');


    let drawing = false;


    function resizeCanvas() {

        const ratio =
            Math.max(
                window.devicePixelRatio || 1,
                1
            );


        const rect =
            canvas.getBoundingClientRect();


        const old =
            canvas.toDataURL();


        canvas.width =
            rect.width * ratio;

        canvas.height =
            rect.height * ratio;


        ctx.scale(
            ratio,
            ratio
        );


        ctx.lineWidth = 2.2;

        ctx.lineCap =
            'round';

        ctx.lineJoin =
            'round';

    }


    resizeCanvas();


    window.addEventListener(
        'resize',
        resizeCanvas
    );


    function getPosition(event) {

        const rect =
            canvas.getBoundingClientRect();


        if (event.touches) {

            return {

                x:
                    event.touches[0].clientX -
                    rect.left,

                y:
                    event.touches[0].clientY -
                    rect.top

            };

        }


        return {

            x:
                event.clientX -
                rect.left,

            y:
                event.clientY -
                rect.top

        };

    }


    function startDrawing(event) {

        event.preventDefault();

        drawing = true;

        const p =
            getPosition(event);

        ctx.beginPath();

        ctx.moveTo(
            p.x,
            p.y
        );

    }


    function draw(event) {

        if (!drawing) {
            return;
        }

        event.preventDefault();


        const p =
            getPosition(event);


        ctx.lineTo(
            p.x,
            p.y
        );

        ctx.stroke();

    }


    function stopDrawing() {

        drawing = false;

    }


    canvas.addEventListener(
        'mousedown',
        startDrawing
    );

    canvas.addEventListener(
        'mousemove',
        draw
    );

    canvas.addEventListener(
        'mouseup',
        stopDrawing
    );

    canvas.addEventListener(
        'mouseleave',
        stopDrawing
    );


    canvas.addEventListener(
        'touchstart',
        startDrawing,
        { passive: false }
    );

    canvas.addEventListener(
        'touchmove',
        draw,
        { passive: false }
    );

    canvas.addEventListener(
        'touchend',
        stopDrawing
    );


    document
        .getElementById('clearSignature')
        .addEventListener(
            'click',
            () => {

                ctx.clearRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | BACK BUTTONS
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('backToStep1')
        .addEventListener(
            'click',
            () => showStep(1)
        );


    document
        .getElementById('backToStep2')
        .addEventListener(
            'click',
            () => showStep(2)
        );


    /*
    |--------------------------------------------------------------------------
    | FINAL SUBMIT
    |--------------------------------------------------------------------------
    */

    document
        .getElementById(
            'submitApplication'
        )
        .addEventListener(
            'click',
            async () => {

                const signature =
                    canvas.toDataURL(
                        'image/png'
                    );


                if (
                    isBlankCanvas()
                ) {

                    alert(
                        'لطفاً ابتدا امضای خود را ثبت کنید.'
                    );

                    return;

                }


                const button =
                    document.getElementById(
                        'submitApplication'
                    );


                button.disabled = true;

                button.textContent =
                    'در حال ثبت...';


                try {

                    const data =
                        new FormData();


                    data.append(
                        'csrf_token',
                        window.TOLIKA.csrf
                    );


                    data.append(
                        'application',
                        JSON.stringify(
                            state.application
                        )
                    );


                    data.append(
                        'questions',
                        JSON.stringify(
                            state.questions
                        )
                    );


                    data.append(
                        'answers',
                        JSON.stringify(
                            state.answers
                        )
                    );


                    data.append(
                        'signature',
                        signature
                    );


                    if (state.photoFile) {

                        data.append(
                            'photo',
                            state.photoFile
                        );

                    }


                    const response =
                        await fetch(
                            'submit.php',
                            {
                                method: 'POST',
                                body: data
                            }
                        );


                    const result =
                        await response.json();


                    if (!result.success) {

                        throw new Error(
                            result.message
                        );

                    }


                    showSuccess(
                        result.data.tracking_code
                    );


                } catch (error) {

                    alert(
                        error.message ||
                        'ثبت اطلاعات ناموفق بود.'
                    );


                    button.disabled =
                        false;

                    button.textContent =
                        'ثبت نهایی درخواست';

                }

            }
        );


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    function showSuccess(code) {

        const result =
            document.getElementById(
                'submitResult'
            );


        result.innerHTML = `

            <h2>
                ✓ درخواست شما با موفقیت ثبت شد
            </h2>

            <p>
                کد پیگیری درخواست:
            </p>

            <div class="tracking-code">
                ${escapeHtml(code)}
            </div>

            <p>
                این کد را برای پیگیری درخواست خود نزد خود نگه دارید.
            </p>

        `;


        result.classList.add(
            'show'
        );


        document
            .getElementById(
                'submitApplication'
            )
            .style.display =
            'none';

    }


    /*
    |--------------------------------------------------------------------------
    | BLANK SIGNATURE
    |--------------------------------------------------------------------------
    */

    function isBlankCanvas() {

        const pixelData =
            ctx.getImageData(
                0,
                0,
                canvas.width,
                canvas.height
            ).data;


        for (
            let i = 3;
            i < pixelData.length;
            i += 4
        ) {

            if (
                pixelData[i] !== 0
            ) {
                return false;
            }

        }


        return true;

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(value)
            .replace(
                /&/g,
                '&amp;'
            )
            .replace(
                /</g,
                '&lt;'
            )
            .replace(
                />/g,
                '&gt;'
            )
            .replace(
                /"/g,
                '&quot;'
            )
            .replace(
                /'/g,
                '&#039;'
            );

    }


})();
