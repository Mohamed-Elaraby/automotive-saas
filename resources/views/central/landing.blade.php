<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seven S Capital | Under Development</title>

    <meta name="description" content="Seven S Capital - Computer Systems and Software Designing.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#020502">

    <link rel="icon" href="/assets/company/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #55c844;
            --green-soft: #9aff7d;
            --white: #ffffff;
            --muted: rgba(255, 255, 255, 0.66);
            --muted-soft: rgba(255, 255, 255, 0.46);
            --border: rgba(104, 230, 82, 0.22);
            --border-strong: rgba(104, 230, 82, 0.42);
            --panel: rgba(7, 17, 9, 0.80);
            --max-width: 1380px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        body {
            font-family: "IBM Plex Sans Arabic", Tahoma, Arial, sans-serif;
            color: var(--white);
            direction: rtl;
        }

        .page {
            position: relative;
            width: 100vw;
            height: 100svh;
            overflow: hidden;
            isolation: isolate;
            background:
                radial-gradient(circle at 50% 18%, rgba(85, 200, 68, 0.13), transparent 22%),
                radial-gradient(circle at 18% 48%, rgba(85, 200, 68, 0.07), transparent 30%),
                radial-gradient(circle at 82% 52%, rgba(85, 200, 68, 0.06), transparent 32%),
                linear-gradient(135deg, #000 0%, #020502 48%, #071108 100%);
        }

        .page::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -5;
            background-image:
                linear-gradient(rgba(104, 230, 82, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(104, 230, 82, 0.03) 1px, transparent 1px);
            background-size: 56px 56px;
            opacity: 0.34;
            mask-image: linear-gradient(to bottom, transparent 0%, black 12%, black 88%, transparent 100%);
        }

        .page::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -4;
            pointer-events: none;
            background:
                linear-gradient(to bottom, rgba(0,0,0,0.08), rgba(0,0,0,0.25) 62%, rgba(0,0,0,0.88)),
                radial-gradient(circle at 50% 104%, rgba(104,230,82,0.11), transparent 34%);
        }

        .circuit-lines {
            position: absolute;
            inset: 0;
            z-index: -3;
            pointer-events: none;
            opacity: 0.30;
            overflow: hidden;
        }

        .circuit-lines::before,
        .circuit-lines::after {
            content: "";
            position: absolute;
            top: -5%;
            width: 38vw;
            height: 110%;
            background:
                linear-gradient(45deg, transparent 0 39%, rgba(85,200,68,0.24) 39.4% 40%, transparent 40.4%),
                linear-gradient(-45deg, transparent 0 53%, rgba(85,200,68,0.16) 53.4% 54%, transparent 54.4%),
                linear-gradient(90deg, transparent 0 58%, rgba(85,200,68,0.16) 58.4% 59%, transparent 59.4%);
        }

        .circuit-lines::before {
            left: 0;
        }

        .circuit-lines::after {
            right: 0;
            transform: scaleX(-1);
        }

        .dot-field {
            position: absolute;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            opacity: 0.18;
            background-image: radial-gradient(circle, rgba(104,230,82,0.55) 0 1px, transparent 1.5px);
            background-size: 34px 34px;
            mask-image:
                radial-gradient(circle at 13% 74%, black 0 11%, transparent 28%),
                radial-gradient(circle at 86% 19%, black 0 12%, transparent 30%);
        }

        .shell {
            width: min(var(--max-width), calc(100vw - 64px));
            height: 100svh;
            margin: 0 auto;
            display: grid;
            grid-template-rows: 10svh 31svh 24svh 25svh 10svh;
            gap: 0;
            min-height: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }

        .brand img {
            width: clamp(180px, 15vw, 280px);
            height: auto;
            max-height: 62%;
            object-fit: contain;
            opacity: 0.84;
            filter:
                drop-shadow(0 0 10px rgba(104, 230, 82, 0.18))
                drop-shadow(0 0 24px rgba(104, 230, 82, 0.07));
        }

        .hero {
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding-bottom: 4px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            height: clamp(29px, 3.8svh, 40px);
            padding: 0 clamp(15px, 1.8vw, 24px);
            margin-bottom: clamp(7px, 1.1svh, 12px);
            border-radius: 999px;
            color: var(--green-soft);
            border: 1px solid var(--border-strong);
            background:
                linear-gradient(135deg, rgba(104,230,82,0.12), rgba(104,230,82,0.025)),
                rgba(6, 15, 8, 0.76);
            box-shadow:
                inset 0 0 18px rgba(104,230,82,0.045),
                0 0 24px rgba(104,230,82,0.07);
            font-size: clamp(11px, 0.92vw, 15px);
            font-weight: 600;
            white-space: nowrap;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green-soft);
            box-shadow: 0 0 12px rgba(154,255,125,0.65);
        }

        h1 {
            font-size: clamp(36px, 4.55vw, 72px);
            line-height: 1.03;
            letter-spacing: -0.8px;
            font-weight: 700;
            margin-bottom: clamp(7px, 1.1svh, 13px);
        }

        h1 span {
            display: block;
        }

        .headline-white {
            color: #fff;
            text-shadow: 0 0 20px rgba(255,255,255,0.10);
        }

        .headline-green {
            color: var(--green);
            text-shadow:
                0 0 22px rgba(104,230,82,0.17),
                0 0 44px rgba(104,230,82,0.08);
        }

        .lead {
            width: min(700px, 92%);
            color: var(--muted);
            font-size: clamp(12px, 1vw, 16px);
            line-height: 1.65;
            font-weight: 400;
            margin-bottom: clamp(9px, 1.5svh, 16px);
        }

        .cta {
            width: clamp(180px, 14vw, 240px);
            height: clamp(38px, 4.7svh, 50px);
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: #fff;
            border: 1px solid rgba(104,230,82,0.48);
            background:
                linear-gradient(135deg, rgba(104,230,82,0.12), rgba(104,230,82,0.025)),
                rgba(7, 17, 9, 0.82);
            box-shadow:
                0 14px 34px rgba(0,0,0,0.24),
                inset 0 0 18px rgba(104,230,82,0.04);
            font-size: clamp(14px, 1.05vw, 18px);
            font-weight: 700;
            white-space: nowrap;
        }

        .cta b {
            color: var(--green-soft);
            font-size: clamp(17px, 1.3vw, 22px);
            line-height: 1;
        }

        .services {
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: clamp(18px, 1.9vw, 28px);
            align-items: stretch;
            padding-block: clamp(14px, 2svh, 22px);
        }

        .service-card {
            min-width: 0;
            min-height: 0;
            height: 100%;
            border-radius: clamp(13px, 1.1vw, 18px);
            border: 1px solid rgba(255,255,255,0.08);
            background:
                linear-gradient(145deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01)),
                rgba(7, 17, 9, 0.82);
            backdrop-filter: blur(12px);
            box-shadow:
                0 16px 36px rgba(0,0,0,0.24),
                inset 0 0 18px rgba(104,230,82,0.014);
            padding: clamp(9px, 1.1vw, 16px);
            display: grid;
            grid-template-rows: auto auto auto;
            place-items: center;
            text-align: center;
            gap: clamp(4px, 0.65svh, 8px);
            overflow: hidden;
        }

        .service-icon {
            width: clamp(34px, 3.2vw, 50px);
            height: clamp(34px, 3.2vw, 50px);
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: var(--green-soft);
            border: 1px solid rgba(104,230,82,0.24);
            background: rgba(104,230,82,0.04);
            box-shadow: inset 0 0 16px rgba(104,230,82,0.035);
        }

        svg {
            width: clamp(18px, 1.55vw, 26px);
            height: clamp(18px, 1.55vw, 26px);
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .service-card h2 {
            color: #fff;
            font-size: clamp(13px, 1.12vw, 18px);
            line-height: 1.22;
            font-weight: 700;
        }

        .service-card p {
            color: var(--muted-soft);
            font-size: clamp(9px, 0.78vw, 12.5px);
            line-height: 1.45;
            max-width: 220px;
        }

        .progress-wrap {
            min-height: 0;
            display: flex;
            align-items: center;
            padding-top: clamp(8px, 1.4svh, 14px);
        }

        .progress-panel {
            width: 100%;
            height: calc(100% - clamp(10px, 1.5svh, 16px));
            border-radius: clamp(14px, 1.35vw, 21px);
            border: 1px solid rgba(104,230,82,0.23);
            background:
                linear-gradient(145deg, rgba(255,255,255,0.033), rgba(255,255,255,0.010)),
                rgba(5, 14, 7, 0.88);
            box-shadow:
                0 20px 52px rgba(0,0,0,0.31),
                inset 0 0 26px rgba(104,230,82,0.018);
            padding: clamp(10px, 1.25vw, 18px);
            display: grid;
            grid-template-columns: clamp(82px, 9vw, 125px) 1fr 0.56fr;
            gap: clamp(14px, 2vw, 32px);
            align-items: center;
            overflow: hidden;
        }

        .circle {
            width: clamp(68px, 6.7vw, 104px);
            height: clamp(68px, 6.7vw, 104px);
            border-radius: 50%;
            display: grid;
            place-items: center;
            justify-self: center;
            color: var(--green-soft);
            font-size: clamp(20px, 1.95vw, 30px);
            font-weight: 700;
            background:
                radial-gradient(circle at center, #061007 0 56%, transparent 57%),
                conic-gradient(var(--green) 0 245deg, rgba(255,255,255,0.08) 245deg 360deg);
            box-shadow:
                0 0 26px rgba(104,230,82,0.16),
                inset 0 0 22px rgba(0,0,0,0.55);
        }

        .progress-copy {
            text-align: right;
            border-inline-start: 1px solid rgba(255,255,255,0.08);
            padding-inline-start: clamp(14px, 2vw, 28px);
            min-width: 0;
        }

        .progress-copy h2 {
            color: var(--green-soft);
            font-size: clamp(18px, 1.75vw, 28px);
            line-height: 1.18;
            font-weight: 700;
            margin-bottom: clamp(3px, 0.55svh, 6px);
        }

        .progress-copy p {
            color: var(--muted);
            font-size: clamp(9.5px, 0.82vw, 12.8px);
            line-height: 1.55;
        }

        .mini-circuit {
            height: 100%;
            min-height: 54px;
            border-radius: 14px;
            position: relative;
            opacity: 0.43;
            overflow: hidden;
            background:
                linear-gradient(90deg, transparent 0 30%, rgba(104,230,82,0.16) 30% 31%, transparent 31%),
                linear-gradient(28deg, transparent 0 44%, rgba(104,230,82,0.24) 44.3% 45%, transparent 45.3%),
                linear-gradient(-24deg, transparent 0 40%, rgba(104,230,82,0.16) 40.3% 41%, transparent 41.3%);
        }

        .mini-circuit::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(104,230,82,0.58) 0 2px, transparent 2px);
            background-size: 36px 26px;
            opacity: 0.34;
        }

        .footer {
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.42);
            font-size: clamp(9px, 0.8vw, 12px);
            text-align: center;
            white-space: nowrap;
        }

        @media (max-width: 1100px) {
            .shell {
                width: calc(100vw - 36px);
                grid-template-rows: 9svh 31svh 26svh 25svh 9svh;
            }

            .services {
                gap: clamp(12px, 1.4vw, 18px);
            }

            .progress-panel {
                grid-template-columns: clamp(72px, 9vw, 108px) 1fr;
            }

            .mini-circuit {
                display: none;
            }
        }

        @media (max-height: 740px) and (min-width: 761px) {
            .shell {
                grid-template-rows: 9svh 30svh 26svh 26svh 9svh;
            }

            .brand img {
                width: clamp(170px, 14vw, 250px);
            }

            .status {
                height: 29px;
                margin-bottom: 6px;
            }

            h1 {
                font-size: clamp(32px, 4vw, 60px);
                margin-bottom: 6px;
            }

            .lead {
                font-size: clamp(11px, 0.9vw, 14px);
                line-height: 1.45;
                margin-bottom: 8px;
            }

            .cta {
                height: 36px;
                font-size: 13px;
            }

            .services {
                padding-block: 12px;
                gap: 18px;
            }

            .service-card {
                padding: 8px;
            }

            .service-icon {
                width: 34px;
                height: 34px;
            }

            .service-card p {
                font-size: 9px;
                line-height: 1.32;
            }

            .circle {
                width: 68px;
                height: 68px;
                font-size: 20px;
            }

            .progress-panel {
                padding: 9px 14px;
            }
        }

        @media (max-width: 760px) {
            .shell {
                width: calc(100vw - 22px);
                grid-template-rows: 8svh 33svh 31svh 22svh 6svh;
            }

            .brand img {
                width: min(220px, 66vw);
                max-height: 46px;
            }

            .status {
                height: 27px;
                padding-inline: 13px;
                font-size: 10.5px;
                margin-bottom: 6px;
            }

            h1 {
                font-size: clamp(29px, 8.8vw, 42px);
                letter-spacing: -0.3px;
                margin-bottom: 6px;
            }

            .lead {
                width: 96%;
                font-size: clamp(9.5px, 2.65vw, 12.5px);
                line-height: 1.45;
                margin-bottom: 8px;
            }

            .cta {
                width: min(176px, 60vw);
                height: 35px;
                border-radius: 11px;
                font-size: 13px;
            }

            .services {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding-block: 10px;
            }

            .service-card {
                border-radius: 12px;
                padding: 7px;
                gap: 3px;
            }

            .service-icon {
                width: 29px;
                height: 29px;
                border-radius: 9px;
            }

            svg {
                width: 16px;
                height: 16px;
            }

            .service-card h2 {
                font-size: 11.5px;
            }

            .service-card p {
                font-size: 8.3px;
                line-height: 1.25;
                max-width: 140px;
            }

            .progress-wrap {
                padding-top: 8px;
            }

            .progress-panel {
                height: calc(100% - 8px);
                grid-template-columns: 62px 1fr;
                padding: 8px;
                gap: 8px;
                border-radius: 13px;
            }

            .circle {
                width: 56px;
                height: 56px;
                font-size: 17px;
            }

            .progress-copy {
                padding-inline-start: 8px;
            }

            .progress-copy h2 {
                font-size: 16px;
                margin-bottom: 3px;
            }

            .progress-copy p {
                font-size: 8.8px;
                line-height: 1.38;
            }

            .footer {
                font-size: 9px;
            }
        }

        @media (max-width: 390px) {
            .shell {
                width: calc(100vw - 16px);
                grid-template-rows: 8svh 32svh 32svh 22svh 6svh;
            }

            .brand img {
                width: min(190px, 64vw);
            }

            h1 {
                font-size: 28px;
            }

            .lead {
                font-size: 9px;
                line-height: 1.38;
            }

            .service-card p {
                display: none;
            }

            .service-icon {
                width: 33px;
                height: 33px;
            }

            .progress-copy p {
                font-size: 8.2px;
            }
        }
    </style>
</head>

<body>
<main class="page">
    <div class="circuit-lines" aria-hidden="true"></div>
    <div class="dot-field" aria-hidden="true"></div>

    <section class="shell">
        <header class="brand">
            <img src="/assets/company/logo.png" alt="Seven S Capital">
        </header>

        <section class="hero">
            <div class="status">تحت التطوير</div>

            <h1>
                <span class="headline-white">نحن نبني</span>
                <span class="headline-green">المستقبل الرقمي</span>
            </h1>

            <p class="lead">
                في Seven S Capital نقدم حلولًا رقمية متكاملة تشمل تصميم وتطوير مواقع الويب،
                تصميم واجهات وتجربة المستخدم، وتطوير البرمجيات وبناء أنظمة الأعمال الذكية
                لمساعدة شركائنا على النمو بثقة في عالم رقمي متطور.
            </p>

            <a href="mailto:info@seven-scapital.com" class="cta">
                <span>تواصل معنا</span>
                <b>←</b>
            </a>
        </section>

        <section class="services">
            <article class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M6 7h12"></path>
                        <path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"></path>
                        <path d="M4 7h16v13H4z"></path>
                    </svg>
                </div>
                <h2>أنظمة أعمال</h2>
                <p>بناء أنظمة أعمال ذكية تدير عملياتك وترفع كفاءة شركتك.</p>
            </article>

            <article class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 18l-6-6 6-6"></path>
                        <path d="M15 6l6 6-6 6"></path>
                        <path d="M14 4l-4 16"></path>
                    </svg>
                </div>
                <h2>تطوير برمجيات</h2>
                <p>تطوير تطبيقات وحلول برمجية مخصصة تلبي احتياجات عملك.</p>
            </article>

            <article class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 5h16v14H4z"></path>
                        <path d="M8 10h8"></path>
                        <path d="M8 14h5"></path>
                    </svg>
                </div>
                <h2>تصميم UI/UX</h2>
                <p>تصميم واجهات وتجارب مستخدم عصرية تركز على العميل.</p>
            </article>

            <article class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M3 12h18"></path>
                        <path d="M12 3a15 15 0 0 1 0 18"></path>
                        <path d="M12 3a15 15 0 0 0 0 18"></path>
                    </svg>
                </div>
                <h2>مواقع ويب</h2>
                <p>تصميم وتطوير مواقع احترافية متجاوبة وسريعة وآمنة.</p>
            </article>
        </section>

        <section class="progress-wrap">
            <div class="progress-panel">
                <div class="circle">68%</div>

                <div class="progress-copy">
                    <h2>الموقع قيد التطوير</h2>
                    <p>
                        نعمل حاليًا على تقديم تجربة رقمية متكاملة تعكس رؤيتنا وجودة خدماتنا.
                        سيتم إطلاق الموقع قريبًا، شكرًا لثقتكم واهتمامكم.
                    </p>
                </div>

                <div class="mini-circuit" aria-hidden="true"></div>
            </div>
        </section>

        <footer class="footer">
            © {{ date('Y') }} Seven S Capital. جميع الحقوق محفوظة.
        </footer>
    </section>
</main>
</body>
</html>
