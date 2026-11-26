<?php

use WTSBill\Core\Config;
use WTSBill\Services\CompanyBrandingService;

$appName = Config::get('app.name', 'WTSBill ERP');
$pageTitle = 'Login — ' . $appName;
$companyLogoUrl = CompanyBrandingService::getCompanyLogo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= e($companyLogoUrl) ?>">
    <link rel="stylesheet" href="/assets/css/login.css">
    <style>
        /* ==========================================================================
           WTSBill ERP — Full-Scene 3D Business Stage & Glassmorphic Login
           Brand Palette: #DAF1DE, #8EB69B, #235347, #163832, #0B2B26, #051F20
           ========================================================================== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #051F20;
            color: #DAF1DE;
        }
        body {
            min-height: 100vh;
            position: relative;
            background: radial-gradient(ellipse at 35% 45%, #0e3832 0%, #051F20 55%, #021112 100%);
            display: flex;
            align-items: stretch;
        }

        /* Flowing Ambient Light Waves */
        .bg-wave-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(110px);
            pointer-events: none;
            z-index: 0;
        }
        .wave-1 {
            width: 680px;
            height: 680px;
            top: -180px;
            left: 15%;
            background: radial-gradient(circle, rgba(142, 182, 155, 0.25) 0%, rgba(35, 83, 71, 0) 70%);
        }
        .wave-2 {
            width: 620px;
            height: 620px;
            bottom: -180px;
            left: -80px;
            background: radial-gradient(circle, rgba(35, 83, 71, 0.4) 0%, rgba(5, 31, 32, 0) 70%);
        }
        .wave-3 {
            width: 500px;
            height: 500px;
            top: 20%;
            right: -120px;
            background: radial-gradient(circle, rgba(142, 182, 155, 0.18) 0%, rgba(5, 31, 32, 0) 70%);
        }

        /* Master Full-Viewport Split Stage */
        .login-stage {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1480px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem 3.5rem;
            gap: 2.5rem;
        }

        /* ==========================================================================
           LEFT PANEL — 3D STAGE, FLOATING DASHBOARD & 3D MODELS
           ========================================================================== */
        .panel-3d-experience {
            flex: 1 1 58%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: calc(100vh - 4rem);
            position: relative;
        }

        /* Top-Left Brand Hero */
        .hero-brand-block {
            position: relative;
            z-index: 10;
        }
        .hero-brand-title {
            font-size: clamp(2rem, 3.2vw, 2.85rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.1;
            color: #FFFFFF;
        }
        .hero-brand-title .accent-erp {
            background: linear-gradient(135deg, #DAF1DE 0%, #8EB69B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-brand-sub {
            font-size: 0.96rem;
            color: rgba(218, 241, 222, 0.78);
            margin-top: 0.45rem;
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        .hero-accent-line {
            width: 54px;
            height: 3px;
            background: linear-gradient(90deg, #8EB69B, transparent);
            border-radius: 999px;
            margin-top: 0.75rem;
        }

        /* Center 3D Scene Container */
        .stage-3d-center {
            position: relative;
            flex: 1;
            min-height: 440px;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1200px;
            margin: 1rem 0;
        }

        /* Three.js Canvas (Laptop, Podium, Plant, 3D Bars, Floating Cubes) */
        #three-canvas-container {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 4;
            pointer-events: auto;
        }
        #three-canvas-container canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }

        /* Floating Glassmorphic Dashboard Cards Layered with 3D Scene */
        .hud-layer {
            position: relative;
            width: 100%;
            max-width: 740px;
            height: 430px;
            z-index: 6;
            pointer-events: none;
            transform-style: preserve-3d;
            transition: transform 0.25s ease-out;
        }

        /* Main Back Glass Panel: Business Overview + Donut Chart */
        .hud-overview-card {
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-48%) rotateY(4deg) rotateX(2deg);
            width: 410px;
            padding: 1.25rem 1.45rem;
            background: rgba(11, 43, 38, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(142, 182, 155, 0.35);
            border-radius: 18px;
            box-shadow: 0 25px 50px -12px rgba(2, 17, 18, 0.75), 0 0 25px rgba(142, 182, 155, 0.12) inset;
            z-index: 2;
        }
        .hud-card-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: #DAF1DE;
            margin-bottom: 1rem;
        }
        .hud-overview-body {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .hud-donut-wrap {
            position: relative;
            width: 122px;
            height: 122px;
            flex-shrink: 0;
        }
        .hud-donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .hud-donut-pct {
            font-size: 1.35rem;
            font-weight: 800;
            color: #FFFFFF;
            line-height: 1;
        }
        .hud-donut-lbl {
            font-size: 0.65rem;
            color: #8EB69B;
            margin-top: 2px;
            font-weight: 600;
        }
        .hud-legend-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            font-size: 0.76rem;
        }
        .hud-legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #DAF1DE;
        }
        .hud-legend-name {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            color: rgba(218, 241, 222, 0.85);
        }
        .dot-c { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        /* Floating Stat Cards */
        .hud-stat-card {
            position: absolute;
            padding: 0.9rem 1.15rem;
            background: rgba(16, 56, 50, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(218, 241, 222, 0.25);
            border-radius: 14px;
            box-shadow: 0 20px 40px -10px rgba(2, 17, 18, 0.7);
            z-index: 6;
        }
        .hud-orders {
            top: 90px;
            left: -18px;
            width: 188px;
            animation: floatSlow 6s ease-in-out infinite reverse;
        }
        .hud-revenue {
            top: -12px;
            right: 20px;
            width: 195px;
            animation: floatSlow 5.5s ease-in-out infinite 0.8s;
        }
        .hud-customers {
            bottom: 22px;
            right: -18px;
            width: 205px;
            animation: floatSlow 6.5s ease-in-out infinite 1.2s;
        }
        .stat-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.72rem;
            color: #8EB69B;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }
        .stat-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .stat-num {
            font-size: 1.25rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }
        .stat-pill {
            font-size: 0.68rem;
            font-weight: 700;
            color: #DAF1DE;
            background: rgba(35, 83, 71, 0.9);
            border: 1px solid rgba(142, 182, 155, 0.45);
            padding: 0.12rem 0.42rem;
            border-radius: 6px;
        }

        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-9px); }
        }
        .hud-overview-card {
            animation: floatOverview 7s ease-in-out infinite;
        }
        @keyframes floatOverview {
            0%, 100% { transform: translateX(-52%) rotateY(4deg) rotateX(2deg) translateY(0px); }
            50% { transform: translateX(-52%) rotateY(4deg) rotateX(2deg) translateY(-8px); }
        }

        /* Bottom-Left 3 Feature Items */
        .hero-features-bar {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 2.25rem;
            flex-wrap: wrap;
            padding-top: 0.75rem;
        }
        .hero-feat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .hero-feat-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(35, 83, 71, 0.55);
            border: 1px solid rgba(142, 182, 155, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8EB69B;
            box-shadow: 0 0 18px rgba(142, 182, 155, 0.15);
            flex-shrink: 0;
        }
        .hero-feat-title {
            font-size: 0.84rem;
            font-weight: 700;
            color: #DAF1DE;
        }
        .hero-feat-sub {
            font-size: 0.72rem;
            color: rgba(142, 182, 155, 0.85);
            margin-top: 1px;
        }

        /* CSS Fallback if WebGL is unavailable */
        .css-fallback-visual {
            display: none;
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 320px;
            height: 90px;
            border-radius: 50%;
            background: radial-gradient(ellipse at center, rgba(142, 182, 155, 0.35) 0%, rgba(22, 56, 50, 0.8) 60%, transparent 100%);
            border: 2px solid rgba(142, 182, 155, 0.45);
            box-shadow: 0 0 40px rgba(142, 182, 155, 0.3);
            z-index: 2;
        }

        /* ==========================================================================
           RIGHT PANEL — GLASSMORPHIC LOGIN CARD
           ========================================================================== */
        .panel-login-form {
            flex: 0 0 430px;
            width: 100%;
            max-width: 440px;
            padding: 2.5rem 2.35rem 2rem 2.35rem;
            background: rgba(11, 43, 38, 0.72);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1.5px solid rgba(142, 182, 155, 0.3);
            border-radius: 24px;
            box-shadow: 0 30px 60px -15px rgba(2, 17, 18, 0.85), 0 0 0 1px rgba(218, 241, 222, 0.08) inset;
            position: relative;
            z-index: 15;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            animation: cardEntrance 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }
        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card-header {
            text-align: center;
            margin-bottom: 1.85rem;
        }
        .login-logo-center {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: #FFFFFF;
            padding: 8px;
            margin-bottom: 0.75rem;
            box-shadow: 0 10px 25px rgba(2, 17, 18, 0.45), 0 0 20px rgba(142, 182, 155, 0.25);
        }
        .login-logo-center img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .login-brand-name {
            font-size: 1.25rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.01em;
            margin-bottom: 0.85rem;
        }
        .login-brand-name span {
            color: #8EB69B;
        }
        .login-heading {
            font-size: 1.55rem;
            font-weight: 800;
            color: #DAF1DE;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }
        .login-subtitle {
            font-size: 0.86rem;
            color: rgba(142, 182, 155, 0.9);
            font-weight: 400;
        }

        /* Alerts */
        .alert-box {
            padding: 0.75rem 0.95rem;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            line-height: 1.4;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.16);
            border: 1px solid rgba(239, 68, 68, 0.45);
            color: #FCA5A5;
        }
        .alert-success {
            background: rgba(35, 83, 71, 0.55);
            border: 1px solid rgba(142, 182, 155, 0.55);
            color: #DAF1DE;
        }
        .alert-warning {
            background: rgba(245, 158, 11, 0.16);
            border: 1px solid rgba(245, 158, 11, 0.45);
            color: #FDE68A;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 1.15rem;
            position: relative;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-leading {
            position: absolute;
            left: 1rem;
            color: #8EB69B;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }
        .form-group:focus-within .input-icon-leading {
            color: #DAF1DE;
        }
        .form-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.75rem;
            background: rgba(5, 31, 32, 0.65);
            border: 1.5px solid rgba(142, 182, 155, 0.26);
            border-radius: 12px;
            color: #FFFFFF;
            font-family: inherit;
            font-size: 0.94rem;
            outline: none;
            transition: all 0.22s ease;
        }
        .form-input::placeholder {
            color: rgba(142, 182, 155, 0.65);
            font-size: 0.9rem;
        }
        .form-input:focus {
            background: rgba(5, 31, 32, 0.95);
            border-color: #8EB69B;
            box-shadow: 0 0 0 3px rgba(142, 182, 155, 0.2);
        }
        .btn-toggle-password {
            position: absolute;
            right: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #8EB69B;
            padding: 0.4rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
            border-radius: 8px;
        }
        .btn-toggle-password:hover {
            color: #DAF1DE;
        }

        /* Aux Row */
        .form-aux-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.82rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(218, 241, 222, 0.85);
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }
        .checkbox-label input[type="checkbox"] {
            accent-color: #235347;
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .forgot-link {
            color: #8EB69B;
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover {
            color: #DAF1DE;
            text-decoration: underline;
        }

        /* Primary Sign-In Pill Button */
        .btn-submit-login {
            width: 100%;
            padding: 0.92rem 1.5rem;
            background: linear-gradient(135deg, #235347 0%, #3d8471 100%);
            color: #FFFFFF;
            border: 1px solid rgba(218, 241, 222, 0.35);
            border-radius: 9999px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(35, 83, 71, 0.6), 0 0 20px rgba(142, 182, 155, 0.25);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-submit-login:hover {
            background: linear-gradient(135deg, #2a6455 0%, #4a9d87 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -5px rgba(35, 83, 71, 0.75), 0 0 28px rgba(142, 182, 155, 0.4);
        }
        .btn-submit-login:active {
            transform: translateY(0);
        }

        /* ==========================================================================
           FOOTER ATTRIBUTION (STRICTLY PRESERVED UNCHANGED)
           ========================================================================== */
        .login-footer-preserved {
            margin-top: 1.75rem;
            padding-top: 1.1rem;
            border-top: 1px solid rgba(142, 182, 155, 0.2);
            font-size: 0.78rem;
            color: #8EB69B;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1120px) {
            .login-stage {
                flex-direction: column;
                padding: 1.5rem;
                justify-content: center;
            }
            .panel-3d-experience {
                min-height: auto;
                width: 100%;
                align-items: center;
                text-align: center;
            }
            .hero-accent-line {
                margin: 0.75rem auto 0 auto;
            }
            .stage-3d-center {
                width: 100%;
                min-height: 360px;
            }
            .hud-layer {
                display: none;
            }
            .hero-features-bar {
                justify-content: center;
                gap: 1.25rem;
            }
            .panel-login-form {
                flex: 0 0 auto;
                max-width: 460px;
            }
        }
        @media (max-width: 520px) {
            .login-stage { padding: 1rem; }
            .stage-3d-center { min-height: 220px; }
            .panel-login-form { padding: 1.75rem 1.35rem; border-radius: 20px; }
            .hero-features-bar { display: none; }
            .login-footer-preserved { flex-direction: column; align-items: center; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- Ambient Lighting Waves -->
    <div class="bg-wave-glow wave-1" aria-hidden="true"></div>
    <div class="bg-wave-glow wave-2" aria-hidden="true"></div>
    <div class="bg-wave-glow wave-3" aria-hidden="true"></div>

    <!-- Master Dual-Panel Stage -->
    <main class="login-stage">

        <!-- ======================================================================
             LEFT PANEL — 3D MODEL STAGE & FLOATING BUSINESS ANALYTICS
             ====================================================================== -->
        <section class="panel-3d-experience" id="panel-3d" aria-label="WTSBill 3D Business Overview">
            <!-- Top-Left Brand Hero -->
            <div class="hero-brand-block">
                <h1 class="hero-brand-title">WTSBill <span class="accent-erp">ERP</span></h1>
                <p class="hero-brand-sub">Smart Billing • Powerful Business Management • Better Growth</p>
                <div class="hero-accent-line"></div>
            </div>

            <!-- Center 3D Model + Floating Dashboard Layer -->
            <div class="stage-3d-center" id="stage-3d-center">
                <!-- Three.js 3D Model Canvas (Podium, 3D Laptop, Plant, Rising Bars, Floating Cubes) -->
                <div id="three-canvas-container" aria-hidden="true"></div>
                <div class="css-fallback-visual" id="css-fallback-visual" aria-hidden="true"></div>

                <!-- Layered Floating Glass Dashboard HUD -->
                <div class="hud-layer" id="hud-parallax-layer">
                    <!-- Main Business Overview Glass Screen -->
                    <div class="hud-overview-card">
                        <div class="hud-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8EB69B" stroke-width="2.5"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                            <span>Business Overview</span>
                        </div>
                        <div class="hud-overview-body">
                            <div class="hud-donut-wrap">
                                <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                    <circle cx="18" cy="18" r="14" fill="transparent" stroke="rgba(218, 241, 222, 0.12)" stroke-width="4.5"></circle>
                                    <circle cx="18" cy="18" r="14" fill="transparent" stroke="#8EB69B" stroke-width="4.5" stroke-dasharray="68 32" stroke-dashoffset="0"></circle>
                                    <circle cx="18" cy="18" r="14" fill="transparent" stroke="#DAF1DE" stroke-width="4.5" stroke-dasharray="14 86" stroke-dashoffset="-68"></circle>
                                    <circle cx="18" cy="18" r="14" fill="transparent" stroke="#235347" stroke-width="4.5" stroke-dasharray="10 90" stroke-dashoffset="-82"></circle>
                                </svg>
                                <div class="hud-donut-center">
                                    <span class="hud-donut-pct">78%</span>
                                    <span class="hud-donut-lbl">Total Sales</span>
                                </div>
                            </div>
                            <div class="hud-legend-list">
                                <div class="hud-legend-row">
                                    <span class="hud-legend-name"><span class="dot-c" style="background: #8EB69B;"></span>Completed</span>
                                    <strong>78%</strong>
                                </div>
                                <div class="hud-legend-row">
                                    <span class="hud-legend-name"><span class="dot-c" style="background: #DAF1DE;"></span>Pending</span>
                                    <strong>12%</strong>
                                </div>
                                <div class="hud-legend-row">
                                    <span class="hud-legend-name"><span class="dot-c" style="background: #235347;"></span>In Process</span>
                                    <strong>10%</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Card: Total Orders (Left) -->
                    <div class="hud-stat-card hud-orders">
                        <div class="stat-top">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <span>Total Orders</span>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-num">1,248</span>
                            <span class="stat-pill">↑ +18%</span>
                        </div>
                    </div>

                    <!-- Floating Card: Total Revenue (Top-Right) -->
                    <div class="hud-stat-card hud-revenue">
                        <div class="stat-top">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span>Total Revenue</span>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-num">₹1.57Cr</span>
                            <span class="stat-pill">↑ +12%</span>
                        </div>
                    </div>

                    <!-- Floating Card: Active Customers (Bottom-Right) -->
                    <div class="hud-stat-card hud-customers">
                        <div class="stat-top">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                            <span>Active Customers</span>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-num">892</span>
                            <span class="stat-pill">↑ +14%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom-Left 3 Feature Badges -->
            <div class="hero-features-bar">
                <div class="hero-feat-item">
                    <div class="hero-feat-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <div>
                        <div class="hero-feat-title">Fast &amp; Reliable</div>
                        <div class="hero-feat-sub">Blazing fast performance</div>
                    </div>
                </div>

                <div class="hero-feat-item">
                    <div class="hero-feat-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <div>
                        <div class="hero-feat-title">Secure &amp; Trusted</div>
                        <div class="hero-feat-sub">Your data, our priority</div>
                    </div>
                </div>

                <div class="hero-feat-item">
                    <div class="hero-feat-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    </div>
                    <div>
                        <div class="hero-feat-title">24/7 Support</div>
                        <div class="hero-feat-sub">Always here for you</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================================
             RIGHT PANEL — GLASSMORPHIC LOGIN CARD
             ====================================================================== -->
        <section class="panel-login-form" aria-label="Sign In Form">
            <div>
                <div class="login-card-header">
                    <div class="login-logo-center">
                        <img src="<?= e($companyLogoUrl) ?>" alt="WTS Logo" onerror="this.onerror=null; this.src='/assets/images/logo.png';">
                    </div>
                    <div class="login-brand-name">WTSBill <span>ERP</span></div>
                    <h2 class="login-heading">Welcome Back!</h2>
                    <p class="login-subtitle">Sign in to your account to continue</p>
                </div>

                <!-- Session Expiration Notice -->
                <?php if (isset($_GET['session_expired'])): ?>
                    <div class="alert-box alert-warning" role="alert">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Your session has expired. Please sign in again.</span>
                    </div>
                <?php endif; ?>

                <!-- Server Error Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert-box alert-error" role="alert">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Server Success Alert -->
                <?php if (!empty($success)): ?>
                    <div class="alert-box alert-success" role="alert">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><?= e($success) ?></span>
                    </div>
                <?php endif; ?>

                <form id="login-form" action="/login" method="POST" autocomplete="on">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">

                    <!-- Username / Email -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <span class="input-icon-leading">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <input
                                type="text"
                                id="email"
                                name="email"
                                class="form-input"
                                placeholder="Username or Email"
                                aria-label="Username or Email"
                                value="<?= e($_POST['email'] ?? '') ?>"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <div class="input-wrapper">
                            <span class="input-icon-leading">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Password"
                                aria-label="Password"
                                required
                                style="padding-right: 2.75rem;"
                            >
                            <button
                                type="button"
                                id="btn-toggle-password"
                                class="btn-toggle-password"
                                aria-label="Show password"
                                title="Show password"
                                onclick="window.togglePasswordVisibility(event)"
                            >
                                <svg class="icon-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="icon-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="form-aux-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1">
                            <span>Remember Me</span>
                        </label>
                        <a href="/forgot-password" class="forgot-link">Forgot Password?</a>
                    </div>

                    <!-- Primary Sign In Button -->
                    <button type="submit" id="btn-login-submit" class="btn-submit-login">
                        <span>Sign In</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </form>
            </div>

            <!-- ==================================================================
                 FOOTER ATTRIBUTION & SECURITY (STRICTLY PRESERVED UNCHANGED)
                 ================================================================== -->
            <div class="login-footer-preserved login-footer">
                <div class="footer-attribution" style="display: flex; align-items: center; gap: 7px; font-size: 0.78rem; color: #8EB69B;">
                    <img src="<?= e($companyLogoUrl) ?>" alt="WTS" style="width: 18px; height: 18px; object-fit: contain; border-radius: 50%; vertical-align: middle;" onerror="this.onerror=null; this.src='/assets/images/logo.png';">
                    <span>Designed &amp; Developed by <a href="https://wtsindia.co.in" target="_blank" rel="noopener noreferrer" style="color: #DAF1DE; font-weight: 700; text-decoration: none;">WTS</a> &copy; <?= date('Y') ?></span>
                </div>
                <div class="footer-security" style="display: flex; align-items: center; gap: 5px; font-size: 0.78rem; color: #8EB69B; font-weight: 500;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8EB69B" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    <span>256-Bit Encrypted</span>
                </div>
            </div>
        </section>
    </main>

    <!-- Password Toggle Script -->
    <script>
        window.togglePasswordVisibility = function(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            var pwd = document.getElementById('password');
            var btn = document.getElementById('btn-toggle-password');
            if (!pwd) return;
            var isPass = pwd.getAttribute('type') === 'password';
            pwd.setAttribute('type', isPass ? 'text' : 'password');
            if (btn) {
                var openIcon = btn.querySelector('.icon-eye-open');
                var closeIcon = btn.querySelector('.icon-eye-closed');
                if (openIcon && closeIcon) {
                    openIcon.style.display = isPass ? 'none' : 'block';
                    closeIcon.style.display = isPass ? 'block' : 'none';
                }
                btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
                btn.setAttribute('title', isPass ? 'Hide password' : 'Show password');
            }
            try { pwd.focus(); } catch (err) {}
        };
    </script>

    <!-- Three.js 3D Model Scene (Podium, 3D Laptop, Plant, Rising Bars, Floating Wireframe Cubes) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
    (function initFull3DStage() {
        var container = document.getElementById('three-canvas-container');
        var fallback = document.getElementById('css-fallback-visual');
        var hudLayer = document.getElementById('hud-parallax-layer');
        if (!container) return;

        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var hasWebGL = (function() {
            try {
                var c = document.createElement('canvas');
                return !!(window.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl')));
            } catch (e) { return false; }
        })();

        if (!hasWebGL || typeof THREE === 'undefined' || prefersReducedMotion) {
            if (fallback) fallback.style.display = 'block';
            return;
        }

        var width = container.clientWidth || 700;
        var height = container.clientHeight || 440;

        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 100);
        camera.position.set(0, 2.2, 13.5);
        camera.lookAt(0, -0.4, 0);

        var renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.25;
        container.appendChild(renderer.domElement);

        // Lighting
        scene.add(new THREE.AmbientLight(0x8EB69B, 0.9));
        var keyLight = new THREE.DirectionalLight(0xDAF1DE, 1.8);
        keyLight.position.set(6, 12, 8);
        scene.add(keyLight);

        var rimLight = new THREE.PointLight(0x8EB69B, 2.2, 25);
        rimLight.position.set(-6, 3, 5);
        scene.add(rimLight);

        var underGlow = new THREE.PointLight(0x235347, 2.5, 16);
        underGlow.position.set(0, -2.5, 2);
        scene.add(underGlow);

        var stageGroup = new THREE.Group();
        stageGroup.position.y = -0.9;
        scene.add(stageGroup);

        // 1. LAYERED 3D PODIUM PLATFORM (Base + Upper Tier + Glowing Rings)
        var baseMat = new THREE.MeshStandardMaterial({ color: 0x0B2B26, metalness: 0.8, roughness: 0.25 });
        var upperMat = new THREE.MeshStandardMaterial({ color: 0x163832, metalness: 0.7, roughness: 0.3 });
        var glowRingMat = new THREE.MeshBasicMaterial({ color: 0x8EB69B, transparent: true, opacity: 0.85 });

        var tier1 = new THREE.Mesh(new THREE.CylinderGeometry(4.6, 4.9, 0.35, 64), baseMat);
        tier1.position.y = -1.6;
        stageGroup.add(tier1);

        var ring1 = new THREE.Mesh(new THREE.TorusGeometry(4.65, 0.045, 12, 100), glowRingMat);
        ring1.rotation.x = Math.PI / 2;
        ring1.position.y = -1.42;
        stageGroup.add(ring1);

        var tier2 = new THREE.Mesh(new THREE.CylinderGeometry(3.1, 3.3, 0.32, 64), upperMat);
        tier2.position.set(-0.7, -1.28, 0.2);
        stageGroup.add(tier2);

        var ring2 = new THREE.Mesh(new THREE.TorusGeometry(3.12, 0.04, 12, 100), glowRingMat);
        ring2.rotation.x = Math.PI / 2;
        ring2.position.set(-0.7, -1.12, 0.2);
        stageGroup.add(ring2);

        // 2. 3D LAPTOP MODEL ON THE PODIUM
        var laptopGroup = new THREE.Group();
        laptopGroup.position.set(-0.7, -1.08, 0.35);
        laptopGroup.rotation.y = 0.42;
        stageGroup.add(laptopGroup);

        var laptopBodyMat = new THREE.MeshStandardMaterial({ color: 0x0B2B26, metalness: 0.85, roughness: 0.2 });
        var laptopBase = new THREE.Mesh(new THREE.BoxGeometry(2.5, 0.1, 1.7), laptopBodyMat);
        laptopGroup.add(laptopBase);

        // Keyboard glow deck
        var kbMat = new THREE.MeshStandardMaterial({ color: 0x163832, emissive: 0x235347, emissiveIntensity: 0.35 });
        var kbMesh = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.02, 1.05), kbMat);
        kbMesh.position.set(0, 0.055, -0.15);
        laptopGroup.add(kbMesh);

        // Laptop Screen Lid (angled back)
        var screenGroup = new THREE.Group();
        screenGroup.position.set(0, 0.05, -0.82);
        screenGroup.rotation.x = -0.35;
        laptopGroup.add(screenGroup);

        var lidMesh = new THREE.Mesh(new THREE.BoxGeometry(2.5, 1.6, 0.08), laptopBodyMat);
        lidMesh.position.set(0, 0.8, 0);
        screenGroup.add(lidMesh);

        // Glowing WTSBill Screen Back/Front Display
        var screenGlowMat = new THREE.MeshStandardMaterial({
            color: 0x235347,
            emissive: 0x8EB69B,
            emissiveIntensity: 0.45,
            roughness: 0.15
        });
        var innerScreen = new THREE.Mesh(new THREE.PlaneGeometry(2.3, 1.4), screenGlowMat);
        innerScreen.position.set(0, 0.8, 0.045);
        screenGroup.add(innerScreen);

        var backLogoEmblem = new THREE.Mesh(new THREE.OctahedronGeometry(0.24, 0), new THREE.MeshBasicMaterial({ color: 0xDAF1DE, wireframe: true }));
        backLogoEmblem.position.set(0, 0.8, -0.06);
        screenGroup.add(backLogoEmblem);

        // 3. 3D POTTED PLANT ON LEFT SIDE OF PODIUM
        var plantGroup = new THREE.Group();
        plantGroup.position.set(-4.1, -1.4, 0.8);
        stageGroup.add(plantGroup);

        var potMesh = new THREE.Mesh(
            new THREE.CylinderGeometry(0.42, 0.32, 0.65, 24),
            new THREE.MeshStandardMaterial({ color: 0xDAF1DE, roughness: 0.4 })
        );
        potMesh.position.y = 0.32;
        plantGroup.add(potMesh);

        var leafMat = new THREE.MeshStandardMaterial({ color: 0x235347, roughness: 0.45, side: THREE.DoubleSide });
        var leafGeo = new THREE.ConeGeometry(0.18, 0.75, 5);
        for (var l = 0; l < 6; l++) {
            var leaf = new THREE.Mesh(leafGeo, leafMat);
            var la = (l / 6) * Math.PI * 2;
            leaf.position.set(Math.cos(la) * 0.16, 0.85, Math.sin(la) * 0.16);
            leaf.rotation.z = Math.cos(la) * 0.45;
            leaf.rotation.x = Math.sin(la) * 0.45;
            plantGroup.add(leaf);
        }

        // 4. 3D RISING ANALYTICS BAR CHART (RIGHT OF PODIUM)
        var barsGroup = new THREE.Group();
        barsGroup.position.set(2.3, -1.4, 0.5);
        barsGroup.rotation.y = -0.25;
        stageGroup.add(barsGroup);

        var barHeights = [0.85, 1.25, 1.7, 2.2, 2.75];
        var barMeshes = [];
        for (var b = 0; b < 5; b++) {
            var bColor = b >= 3 ? 0xDAF1DE : (b >= 1 ? 0x8EB69B : 0x235347);
            var bMat = new THREE.MeshStandardMaterial({
                color: bColor,
                emissive: bColor,
                emissiveIntensity: 0.18,
                metalness: 0.3,
                roughness: 0.25
            });
            var barMesh = new THREE.Mesh(new THREE.BoxGeometry(0.42, barHeights[b], 0.42), bMat);
            barMesh.position.set(b * 0.54, barHeights[b] / 2, 0);
            barsGroup.add(barMesh);
            barMeshes.push(barMesh);
        }

        // 5. FLOATING 3D WIREFRAME CUBES
        var cubes = [];
        var cubePositions = [
            [-4.2, 2.1, -1.2, 0.42],
            [3.2, 1.8, -0.8, 0.48],
            [-1.8, 2.9, -2.5, 0.32],
            [4.5, 0.5, 0.6, 0.3]
        ];
        cubePositions.forEach(function(pos, idx) {
            var cGeo = new THREE.BoxGeometry(pos[3], pos[3], pos[3]);
            var edges = new THREE.EdgesGeometry(cGeo);
            var line = new THREE.LineSegments(edges, new THREE.LineBasicMaterial({ color: 0xDAF1DE, transparent: true, opacity: 0.85 }));
            var inner = new THREE.Mesh(cGeo, new THREE.MeshStandardMaterial({ color: 0x8EB69B, transparent: true, opacity: 0.22 }));
            var cGroup = new THREE.Group();
            cGroup.add(line);
            cGroup.add(inner);
            cGroup.position.set(pos[0], pos[1], pos[2]);
            stageGroup.add(cGroup);
            cubes.push({ mesh: cGroup, baseY: pos[1], speed: 0.008 + idx * 0.003, phase: idx * 1.4 });
        });

        // Mouse Parallax for Both 3D Scene & Floating Glass HUD
        var mouseX = 0, mouseY = 0;
        window.addEventListener('mousemove', function(e) {
            mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
            if (hudLayer) {
                hudLayer.style.transform = 'rotateY(' + (mouseX * 3.5) + 'deg) rotateX(' + (-mouseY * 2.5) + 'deg)';
            }
        }, { passive: true });

        var isTabVisible = true;
        document.addEventListener('visibilitychange', function() {
            isTabVisible = !document.hidden;
        });

        var clock = new THREE.Clock();
        function animate() {
            requestAnimationFrame(animate);
            if (!isTabVisible) return;

            var t = clock.getElapsedTime();

            // Subtle parallax rotation of the 3D podium scene
            stageGroup.rotation.y += (mouseX * 0.14 - stageGroup.rotation.y) * 0.05;
            stageGroup.rotation.x += (mouseY * 0.05 - stageGroup.rotation.x) * 0.05;

            // Gentle laptop hover/breath
            laptopGroup.position.y = -1.08 + Math.sin(t * 1.8) * 0.03;

            // Floating wireframe cubes
            cubes.forEach(function(item) {
                item.mesh.rotation.x += item.speed;
                item.mesh.rotation.y += item.speed * 1.2;
                item.mesh.position.y = item.baseY + Math.sin(t * 1.6 + item.phase) * 0.18;
            });

            // Subtle bar pulse
            barMeshes.forEach(function(bm, idx) {
                bm.scale.y = 1 + Math.sin(t * 2 + idx * 0.7) * 0.04;
            });

            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', function() {
            var nw = container.clientWidth;
            var nh = container.clientHeight;
            if (nw > 0 && nh > 0) {
                camera.aspect = nw / nh;
                camera.updateProjectionMatrix();
                renderer.setSize(nw, nh);
            }
        });
    })();
    </script>
</body>
</html>
