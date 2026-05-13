@php
    /** @var bool $isRtl */
    $isRtl = $isRtl ?? false;
    $fontFamily = $isRtl
        ? "'IBM Plex Sans Arabic', 'Tahoma', system-ui, -apple-system, sans-serif"
        : "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif";
@endphp
<style>
    :root {
        --mkt-primary:        #0d6efd;
        --mkt-primary-600:    #0b5ed7;
        --mkt-primary-700:    #0a58ca;
        --mkt-primary-50:     #e7f1ff;
        --mkt-accent:         #00c897;
        --mkt-dark:           #0b1220;
        --mkt-dark-soft:      #111827;
        --mkt-text:           #1f2937;
        --mkt-muted:          #6b7280;
        --mkt-border:         #e5e7eb;
        --mkt-bg:             #ffffff;
        --mkt-bg-soft:        #f8fafc;
        --mkt-bg-section:     #f3f6fb;
        --mkt-warning-soft:   #fff7ed;
        --mkt-radius-sm:      8px;
        --mkt-radius:         14px;
        --mkt-radius-lg:      24px;
        --mkt-shadow-sm:      0 1px 2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.05);
        --mkt-shadow:         0 6px 18px rgba(15, 23, 42, 0.08);
        --mkt-shadow-lg:      0 24px 48px rgba(15, 23, 42, 0.12);
        --mkt-gradient-hero:  radial-gradient(1200px 600px at 80% -10%, rgba(13, 110, 253, 0.18), transparent 60%),
                              radial-gradient(900px 500px at -10% 30%, rgba(0, 200, 151, 0.12), transparent 60%),
                              linear-gradient(180deg, #ffffff 0%, #f5f8ff 100%);
    }

    * { box-sizing: border-box; }

    html, body { height: 100%; }

    body.marketing-body {
        font-family: {!! $fontFamily !!};
        color: var(--mkt-text);
        background: var(--mkt-bg);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        margin: 0;
    }

    .marketing-skip-link {
        position: absolute;
        left: 8px;
        top: 8px;
        z-index: 9999;
    }

    /* Container */
    .mkt-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.25rem;
    }

    /* Header */
    .mkt-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: saturate(150%) blur(8px);
        border-bottom: 1px solid var(--mkt-border);
    }
    .mkt-header-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 0.875rem 0;
    }
    .mkt-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--mkt-dark);
        text-decoration: none;
    }
    .mkt-brand img { height: 36px; width: auto; }
    .mkt-brand-product { color: var(--mkt-primary); }

    .mkt-nav {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .mkt-nav-link {
        color: var(--mkt-text);
        font-weight: 500;
        text-decoration: none;
        padding: 0.5rem 0.875rem;
        border-radius: 8px;
        transition: background-color .15s ease, color .15s ease;
        font-size: 0.95rem;
    }
    .mkt-nav-link:hover, .mkt-nav-link:focus { background: var(--mkt-bg-section); color: var(--mkt-primary); }
    .mkt-nav-toggle { display: none; }

    .mkt-dropdown {
        position: relative;
    }
    .mkt-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        [dir=ltr] & { left: 0; }
        [dir=rtl] & { right: 0; }
        background: #fff;
        border: 1px solid var(--mkt-border);
        border-radius: var(--mkt-radius);
        box-shadow: var(--mkt-shadow-lg);
        min-width: 320px;
        padding: 0.625rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all .18s ease;
    }
    body[dir="rtl"] .mkt-dropdown-menu { left: auto; right: 0; }
    .mkt-dropdown:hover .mkt-dropdown-menu,
    .mkt-dropdown:focus-within .mkt-dropdown-menu {
        opacity: 1; visibility: visible; transform: translateY(0);
    }
    .mkt-dropdown-item {
        display: block;
        padding: 0.75rem 0.875rem;
        border-radius: 10px;
        text-decoration: none;
        color: var(--mkt-text);
    }
    .mkt-dropdown-item:hover { background: var(--mkt-bg-section); color: var(--mkt-primary); }
    .mkt-dropdown-item-title { display: block; font-weight: 600; margin-bottom: 0.125rem; }
    .mkt-dropdown-item-desc { display: block; color: var(--mkt-muted); font-size: 0.85rem; }

    .mkt-cta-group { display: flex; align-items: center; gap: 0.5rem; }
    .mkt-lang-switch {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--mkt-border);
        border-radius: 999px;
        background: #fff;
        color: var(--mkt-text);
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
    }
    .mkt-lang-switch:hover { border-color: var(--mkt-primary); color: var(--mkt-primary); }

    /* Buttons */
    .mkt-btn {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        transition: transform .12s ease, background-color .15s ease, border-color .15s ease, color .15s ease;
        white-space: nowrap;
    }
    .mkt-btn:active { transform: translateY(1px); }
    .mkt-btn-primary { background: var(--mkt-primary); color: #fff; }
    .mkt-btn-primary:hover { background: var(--mkt-primary-700); color: #fff; }
    .mkt-btn-outline { background: #fff; color: var(--mkt-primary); border-color: var(--mkt-primary); }
    .mkt-btn-outline:hover { background: var(--mkt-primary-50); }
    .mkt-btn-ghost { background: transparent; color: var(--mkt-text); }
    .mkt-btn-ghost:hover { background: var(--mkt-bg-section); color: var(--mkt-primary); }
    .mkt-btn-lg { padding: 0.95rem 1.6rem; font-size: 1rem; }

    /* Hero */
    .mkt-hero {
        position: relative;
        background: var(--mkt-gradient-hero);
        padding: 4.5rem 0 3.5rem;
        overflow: hidden;
    }
    .mkt-hero::before {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(13, 110, 253, 0.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(13, 110, 253, 0.06) 1px, transparent 1px);
        background-size: 56px 56px;
        mask-image: linear-gradient(to bottom, transparent 0%, black 30%, transparent 100%);
        opacity: 0.5;
    }
    .mkt-hero-grid {
        position: relative;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 3rem;
        align-items: center;
    }
    .mkt-eyebrow {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.4rem 0.875rem;
        background: rgba(13, 110, 253, 0.10);
        color: var(--mkt-primary-700);
        border-radius: 999px;
        font-size: 0.8125rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
    }
    .mkt-h1 {
        font-size: clamp(2rem, 4.5vw, 3.25rem);
        line-height: 1.18;
        font-weight: 800;
        color: var(--mkt-dark);
        margin: 0 0 1.25rem;
        letter-spacing: -0.01em;
    }
    .mkt-h1 .accent { color: var(--mkt-primary); }
    .mkt-lead {
        font-size: 1.125rem;
        color: var(--mkt-muted);
        margin: 0 0 2rem;
        max-width: 38rem;
    }
    .mkt-hero-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
    .mkt-hero-trust {
        margin-top: 2rem;
        display: flex; flex-wrap: wrap; gap: 1.25rem;
        color: var(--mkt-muted); font-size: 0.875rem;
    }
    .mkt-hero-visual {
        position: relative;
        background: #fff;
        border-radius: var(--mkt-radius-lg);
        box-shadow: var(--mkt-shadow-lg);
        border: 1px solid var(--mkt-border);
        padding: 1.5rem;
        min-height: 360px;
    }
    .mkt-hero-visual::before {
        content: "";
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        background: linear-gradient(135deg, rgba(13,110,253,0.5), rgba(0,200,151,0.4));
        z-index: -1;
        filter: blur(28px); opacity: 0.5;
    }

    /* Sections */
    .mkt-section { padding: 4.5rem 0; }
    .mkt-section-soft { background: var(--mkt-bg-soft); }
    .mkt-section-dark { background: var(--mkt-dark); color: #fff; }
    .mkt-section-dark .mkt-section-title { color: #fff; }
    .mkt-section-dark .mkt-section-kicker { color: #93c5fd; }
    .mkt-section-dark .mkt-section-subtitle { color: rgba(255,255,255,0.7); }

    .mkt-section-head { text-align: center; max-width: 760px; margin: 0 auto 3rem; }
    .mkt-section-kicker {
        display: inline-block;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--mkt-primary);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .mkt-section-title {
        font-size: clamp(1.75rem, 3vw, 2.5rem);
        line-height: 1.2;
        font-weight: 800;
        color: var(--mkt-dark);
        margin: 0 0 1rem;
    }
    .mkt-section-subtitle { color: var(--mkt-muted); font-size: 1.0625rem; }

    /* Cards grid */
    .mkt-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .mkt-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .mkt-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; }

    .mkt-card {
        background: #fff;
        border: 1px solid var(--mkt-border);
        border-radius: var(--mkt-radius);
        padding: 1.75rem;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    a.mkt-card:hover, .mkt-card:hover { transform: translateY(-3px); box-shadow: var(--mkt-shadow); border-color: rgba(13,110,253,0.4); }
    .mkt-card-icon {
        width: 52px; height: 52px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 14px;
        background: var(--mkt-primary-50);
        color: var(--mkt-primary);
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }
    .mkt-card-title { font-size: 1.125rem; font-weight: 700; color: var(--mkt-dark); margin: 0 0 0.5rem; }
    .mkt-card-desc { color: var(--mkt-muted); font-size: 0.95rem; margin: 0; }
    .mkt-card-link { color: var(--mkt-primary); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.375rem; margin-top: 1rem; }
    .mkt-card-link:hover { text-decoration: underline; }

    .mkt-feature-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.75rem; }
    .mkt-feature-list li {
        display: flex; gap: 0.625rem; align-items: flex-start;
        color: var(--mkt-text);
        font-size: 0.95rem;
    }
    .mkt-feature-list li::before {
        content: "✓";
        flex-shrink: 0;
        width: 22px; height: 22px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: var(--mkt-primary-50);
        color: var(--mkt-primary);
        font-weight: 700;
        font-size: 0.75rem;
    }

    /* Pricing */
    .mkt-pricing-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; align-items: stretch; }
    .mkt-pricing-card {
        background: #fff;
        border: 1px solid var(--mkt-border);
        border-radius: var(--mkt-radius-lg);
        padding: 2rem 1.75rem;
        position: relative;
        display: flex; flex-direction: column;
    }
    .mkt-pricing-card.is-featured {
        border-color: var(--mkt-primary);
        box-shadow: 0 12px 32px rgba(13,110,253,0.18);
        transform: translateY(-4px);
    }
    .mkt-pricing-badge {
        position: absolute;
        top: -12px;
        [dir=ltr] body & { left: 50%; transform: translateX(-50%); }
        [dir=rtl] body & { right: 50%; transform: translateX(50%); }
        background: var(--mkt-primary);
        color: #fff;
        padding: 0.25rem 0.875rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    body[dir="ltr"] .mkt-pricing-badge { left: 50%; right: auto; transform: translateX(-50%); }
    body[dir="rtl"] .mkt-pricing-badge { right: 50%; left: auto; transform: translateX(50%); }
    .mkt-pricing-name { font-size: 1.0625rem; font-weight: 700; color: var(--mkt-dark); margin: 0 0 0.5rem; }
    .mkt-pricing-tagline { color: var(--mkt-muted); font-size: 0.875rem; margin: 0 0 1.5rem; min-height: 2.4em; }
    .mkt-pricing-price { display: flex; align-items: baseline; gap: 0.375rem; margin-bottom: 0.5rem; }
    .mkt-pricing-price .num { font-size: 2.25rem; font-weight: 800; color: var(--mkt-dark); }
    .mkt-pricing-price .currency { font-size: 0.95rem; color: var(--mkt-muted); }
    .mkt-pricing-period { color: var(--mkt-muted); font-size: 0.875rem; margin-bottom: 1.5rem; }
    .mkt-pricing-cta { margin-top: auto; }

    /* FAQ */
    .mkt-faq { max-width: 880px; margin: 0 auto; }
    .mkt-faq details {
        background: #fff;
        border: 1px solid var(--mkt-border);
        border-radius: var(--mkt-radius);
        padding: 1rem 1.25rem;
        margin-bottom: 0.75rem;
    }
    .mkt-faq details[open] { box-shadow: var(--mkt-shadow-sm); border-color: var(--mkt-primary); }
    .mkt-faq summary {
        font-weight: 600;
        color: var(--mkt-dark);
        cursor: pointer;
        list-style: none;
        position: relative;
        padding-inline-end: 2rem;
    }
    .mkt-faq summary::-webkit-details-marker { display: none; }
    .mkt-faq summary::after {
        content: "+";
        position: absolute;
        [dir=ltr] body & { right: 0; }
        [dir=rtl] body & { left: 0; }
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.25rem;
        color: var(--mkt-primary);
    }
    body[dir="ltr"] .mkt-faq summary::after { right: 0; left: auto; }
    body[dir="rtl"] .mkt-faq summary::after { left: 0; right: auto; }
    .mkt-faq details[open] summary::after { content: "−"; }
    .mkt-faq-answer { padding-top: 0.875rem; color: var(--mkt-muted); }

    /* CTA */
    .mkt-cta {
        background: linear-gradient(135deg, var(--mkt-primary) 0%, #4f46e5 100%);
        color: #fff;
        border-radius: var(--mkt-radius-lg);
        padding: 3rem 2rem;
        text-align: center;
        margin: 4rem auto;
        max-width: 1100px;
        position: relative; overflow: hidden;
    }
    .mkt-cta h2 { color: #fff; font-size: clamp(1.5rem, 2.4vw, 2rem); font-weight: 800; margin: 0 0 0.75rem; }
    .mkt-cta p { color: rgba(255,255,255,0.9); margin: 0 auto 1.75rem; max-width: 640px; }
    .mkt-cta .mkt-btn-primary { background: #fff; color: var(--mkt-primary); }
    .mkt-cta .mkt-btn-primary:hover { background: var(--mkt-bg-soft); color: var(--mkt-primary-700); }
    .mkt-cta .mkt-btn-outline { background: transparent; border-color: rgba(255,255,255,0.6); color: #fff; }
    .mkt-cta .mkt-btn-outline:hover { background: rgba(255,255,255,0.1); }

    /* Breadcrumbs */
    .mkt-breadcrumbs {
        background: var(--mkt-bg-soft);
        border-bottom: 1px solid var(--mkt-border);
        padding: 0.875rem 0;
        font-size: 0.875rem;
    }
    .mkt-breadcrumbs ol { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .mkt-breadcrumbs li { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--mkt-muted); }
    .mkt-breadcrumbs li:not(:last-child)::after {
        content: "›"; color: var(--mkt-muted); margin-inline-start: 0.25rem;
    }
    body[dir="rtl"] .mkt-breadcrumbs li:not(:last-child)::after { content: "‹"; }
    .mkt-breadcrumbs a { color: var(--mkt-primary); text-decoration: none; }
    .mkt-breadcrumbs a:hover { text-decoration: underline; }

    /* Footer */
    .mkt-footer {
        background: var(--mkt-dark);
        color: rgba(255,255,255,0.75);
        padding: 4rem 0 2rem;
        margin-top: 4rem;
    }
    .mkt-footer-grid {
        display: grid;
        grid-template-columns: 1.4fr repeat(4, 1fr);
        gap: 2rem;
        margin-bottom: 3rem;
    }
    .mkt-footer h4 { color: #fff; font-size: 0.95rem; font-weight: 700; margin: 0 0 1rem; }
    .mkt-footer ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.625rem; }
    .mkt-footer a { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.9rem; }
    .mkt-footer a:hover { color: #fff; }
    .mkt-footer-bottom {
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 1.5rem;
        display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
        font-size: 0.85rem;
    }

    /* Forms */
    .mkt-form-section { padding: 3rem 0; }
    .mkt-form-card {
        max-width: 720px; margin: 0 auto;
        background: #fff;
        border: 1px solid var(--mkt-border);
        border-radius: var(--mkt-radius-lg);
        padding: 2rem;
        box-shadow: var(--mkt-shadow);
    }
    .mkt-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .mkt-form-group { margin-bottom: 1rem; }
    .mkt-form-label {
        display: block; font-weight: 600; color: var(--mkt-dark);
        margin-bottom: 0.375rem; font-size: 0.9rem;
    }
    .mkt-form-control {
        width: 100%;
        padding: 0.75rem 0.875rem;
        border: 1px solid var(--mkt-border);
        border-radius: 10px;
        font-size: 0.95rem;
        font-family: inherit;
        background: #fff;
        color: var(--mkt-text);
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .mkt-form-control:focus {
        outline: none;
        border-color: var(--mkt-primary);
        box-shadow: 0 0 0 3px rgba(13,110,253,0.12);
    }
    .mkt-form-error {
        color: #b91c1c; font-size: 0.85rem; margin-top: 0.25rem;
    }
    .mkt-form-help { color: var(--mkt-muted); font-size: 0.85rem; margin-top: 0.25rem; }
    .mkt-form-honeypot { position: absolute; left: -9999px; opacity: 0; pointer-events: none; }

    .mkt-flash {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #6ee7b7;
        padding: 0.875rem 1rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
    }
    .mkt-error-box {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        padding: 0.875rem 1rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
    }

    /* Trust badges */
    .mkt-trust-row {
        display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; justify-content: center;
        padding: 2rem 0;
        border-top: 1px solid var(--mkt-border);
        border-bottom: 1px solid var(--mkt-border);
        color: var(--mkt-muted);
        font-size: 0.9rem;
    }
    .mkt-trust-item { display: inline-flex; align-items: center; gap: 0.5rem; }

    /* Mobile */
    @media (max-width: 992px) {
        .mkt-grid-3, .mkt-grid-4, .mkt-pricing-grid { grid-template-columns: repeat(2, 1fr); }
        .mkt-hero-grid { grid-template-columns: 1fr; }
        .mkt-footer-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .mkt-nav-toggle {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 40px; height: 40px;
            border: 1px solid var(--mkt-border);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
        }
        .mkt-nav { display: none; }
        .mkt-nav.is-open {
            display: flex; flex-direction: column;
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: #fff;
            padding: 1rem;
            border-top: 1px solid var(--mkt-border);
            gap: 0.25rem;
            align-items: stretch;
        }
        .mkt-nav.is-open .mkt-nav-link { width: 100%; }
        .mkt-dropdown-menu { position: static; opacity: 1; visibility: visible; transform: none; box-shadow: none; border: none; padding: 0 0 0 1rem; min-width: 0; }
        .mkt-cta-group .mkt-btn-ghost { display: none; }
        .mkt-grid-2, .mkt-grid-3, .mkt-grid-4, .mkt-pricing-grid { grid-template-columns: 1fr; }
        .mkt-form-row { grid-template-columns: 1fr; }
        .mkt-footer-grid { grid-template-columns: 1fr; }
        .mkt-section { padding: 3rem 0; }
        .mkt-hero { padding: 3rem 0 2rem; }
    }
</style>
