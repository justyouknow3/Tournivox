<?php
require_once __DIR__ . "/includes/config.php";

$systemName = TOURNIVOX_NAME;
$systemTagline = TOURNIVOX_TAGLINE;
$capstoneTitle = "Integrated Esports Tournament Management and Live Broadcast Overlay System with Automated Standings and Multi-Game Support";
$currentYear = date("Y");

// TEMPORARY DEVELOPMENT SWITCH:
// Replace this later with an automatic database check for an active live tournament/broadcast.
$liveAvailable = true;
$liveUrl = TOURNIVOX_BASE_URL . "/live/index.php";

$pageTitle = TOURNIVOX_NAME . " | Esports Tournament Platform";
$pageDescription = TOURNIVOX_NAME . " - Esports Tournament Management and Live Broadcast Overlay System";
$activeNav = "home";

$games = [

    [
        "short" => "ML",
        "name" => "Mobile Legends"
    ],

    [
        "short" => "COD",
        "name" => "Call of Duty Mobile"
    ],

    [
        "short" => "HOK",
        "name" => "Honor of Kings"
    ],

    [
        "short" => "VAL",
        "name" => "VALORANT"
    ],

    [
        "short" => "D2",
        "name" => "Dota 2"
    ],

    [
        "short" => "LOL",
        "name" => "League of Legends"
    ],

    [
        "short" => "PUBG",
        "name" => "PUBG Mobile"
    ]

];

$features = [

    [
        "number" => "01",
        "title" => "Tournament Operations",
        "description" => "Create events, configure formats, manage schedules, teams, rosters, match stations and tournament flow from one control center."
    ],

    [
        "number" => "02",
        "title" => "Automated Standings",
        "description" => "Calculate rankings, match points and standings automatically using tournament rules and configurable tie-breakers."
    ],

    [
        "number" => "03",
        "title" => "Broadcast Control",
        "description" => "Prepare browser-based broadcast overlays, match information and live presentation states without relying on a game API."
    ],

    [
        "number" => "04",
        "title" => "Multi-Game Support",
        "description" => "Operate different esports titles in one platform with game-specific stations, formats and competition settings."
    ],

    [
        "number" => "05",
        "title" => "Offline / LAN Ready",
        "description" => "Keep tournament operations available through a local network during unstable or unavailable internet connectivity."
    ],

    [
        "number" => "06",
        "title" => "Audit & Recovery",
        "description" => "Track important actions, verification history, technical incidents, changes and recovery recommendations."
    ]

];

require_once __DIR__ . "/includes/header.php";
?>

<main>


<section
    class="hero"
    id="home"
>

    <div class="hero-grid"></div>

    <div class="hero-glow hero-glow-one"></div>

    <div class="hero-glow hero-glow-two"></div>


    <div class="container hero-layout">


        <div class="hero-copy">


            <div class="eyebrow">

                <span class="live-dot"></span>

                <?php if ($liveAvailable): ?>

                    LIVE TOURNAMENT AVAILABLE

                <?php else: ?>

                    EVSU-INSPIRED CAPSTONE PLATFORM

                <?php endif; ?>

            </div>


            <h1>

                RUN THE

                <span>
                    TOURNAMENT.
                </span>

                OWN THE

                <em>
                    BROADCAST.
                </em>

            </h1>


            <p class="hero-description">

                <?php echo htmlspecialchars($systemName); ?>

                brings tournament operations,
                automated standings,
                match control and live broadcast presentation
                into one competition-ready esports platform.

            </p>


            <div class="hero-actions">


                <?php if ($liveAvailable): ?>

                    <a
                        href="<?php echo htmlspecialchars($liveUrl); ?>"
                        class="btn btn-primary"
                    >

                        View Live

                        <span>
                            ●
                        </span>

                    </a>

                <?php endif; ?>


                <a
                    href="#platform"
                    class="btn btn-primary"
                >

                    Explore the Platform

                    <span>
                        →
                    </span>

                </a>


                <a
                    href="#about"
                    class="btn btn-secondary"
                >

                    View Capstone

                </a>


            </div>


            <div class="hero-mini-stats">


                <div>

                    <strong>
                        07+
                    </strong>

                    <span>
                        Supported Games
                    </span>

                </div>


                <div>

                    <strong>
                        03
                    </strong>

                    <span>
                        Tournament Formats
                    </span>

                </div>


                <div>

                    <strong>
                        LAN
                    </strong>

                    <span>
                        Offline Ready
                    </span>

                </div>


            </div>


        </div>


        <div
            class="hero-visual"
            aria-label="Tournament control center preview"
        >


            <div class="visual-frame">


                <div class="visual-topbar">


                    <div class="window-dots">

                        <span></span>
                        <span></span>
                        <span></span>

                    </div>


                    <div class="visual-status">

                        <span class="status-dot"></span>

                        LIVE CONTROL

                    </div>


                </div>


                <div class="visual-body">


                    <div class="visual-sidebar">

                        <div class="mini-brand">
                            T
                        </div>

                        <span class="side-icon active"></span>

                        <span class="side-icon"></span>

                        <span class="side-icon"></span>

                        <span class="side-icon"></span>

                    </div>


                    <div class="dashboard-preview">


                        <div class="preview-heading">


                            <div>

                                <small>
                                    TOURNAMENT CONTROL
                                </small>

                                <h3>
                                    Grand Finals Console
                                </h3>

                            </div>


                            <span class="round-badge">
                                LIVE
                            </span>


                        </div>


                        <div class="scoreboard">


                            <div class="team-block">

                                <div class="team-logo">
                                    A
                                </div>

                                <span>
                                    TEAM APEX
                                </span>

                            </div>


                            <div class="score-center">

                                <small>
                                    BEST OF 5
                                </small>


                                <strong>

                                    <span>
                                        2
                                    </span>

                                    <b>
                                        :
                                    </b>

                                    <span>
                                        1
                                    </span>

                                </strong>


                                <em>
                                    GAME 4
                                </em>

                            </div>


                            <div class="team-block right">

                                <div class="team-logo gold">
                                    N
                                </div>

                                <span>
                                    NOVA FIVE
                                </span>

                            </div>


                        </div>


                        <div class="preview-columns">


                            <div class="standings-card">


                                <div class="card-head">

                                    <span>
                                        LIVE STANDINGS
                                    </span>

                                    <small>
                                        GROUP A
                                    </small>

                                </div>


                                <div class="standing-row head">

                                    <span>#</span>

                                    <span>TEAM</span>

                                    <span>PTS</span>

                                </div>


                                <div class="standing-row">

                                    <span>01</span>

                                    <span>
                                        <i></i>
                                        APEX
                                    </span>

                                    <strong>
                                        12
                                    </strong>

                                </div>


                                <div class="standing-row">

                                    <span>02</span>

                                    <span>
                                        <i class="gold-dot"></i>
                                        NOVA
                                    </span>

                                    <strong>
                                        9
                                    </strong>

                                </div>


                                <div class="standing-row">

                                    <span>03</span>

                                    <span>
                                        <i class="green-dot"></i>
                                        ORBIT
                                    </span>

                                    <strong>
                                        7
                                    </strong>

                                </div>


                                <div class="standing-row">

                                    <span>04</span>

                                    <span>
                                        <i class="dim-dot"></i>
                                        VERTEX
                                    </span>

                                    <strong>
                                        4
                                    </strong>

                                </div>


                            </div>


                            <div class="control-card">


                                <div class="card-head">

                                    <span>
                                        BROADCAST STATE
                                    </span>

                                    <small>
                                        OUTPUT
                                    </small>

                                </div>


                                <div class="broadcast-screen">

                                    <div class="broadcast-lines"></div>

                                    <div class="broadcast-logo">
                                        TVX
                                    </div>

                                    <small>
                                        MATCH OVERLAY
                                    </small>

                                </div>


                                <div class="control-buttons">

                                    <span class="control active">
                                        ON AIR
                                    </span>

                                    <span class="control">
                                        NEXT
                                    </span>

                                    <span class="control">
                                        HOLD
                                    </span>

                                </div>


                            </div>


                        </div>


                        <div class="activity-strip">

                            <span>
                                <i></i>
                                Station 01 Ready
                            </span>

                            <span>
                                <i></i>
                                Results Verified
                            </span>

                            <span>
                                <i></i>
                                Overlay Connected
                            </span>

                        </div>


                    </div>


                </div>


                <div class="corner-label">
                    01 // CONTROL ROOM
                </div>


            </div>


        </div>


    </div>


    <div class="hero-bottom-line">


        <div class="container ticker">

            <span>
                TOURNAMENT MANAGEMENT
            </span>

            <b>
                ◆
            </b>

            <span>
                AUTOMATED STANDINGS
            </span>

            <b>
                ◆
            </b>

            <span>
                LIVE BROADCAST OVERLAYS
            </span>

            <b>
                ◆
            </b>

            <span>
                MULTI-GAME SUPPORT
            </span>

            <b>
                ◆
            </b>

            <span>
                OFFLINE / LAN OPERATIONS
            </span>

        </div>


    </div>


</section>



<section
    class="platform-section section"
    id="platform"
>


    <div class="container">


        <div class="section-heading split-heading">


            <div>

                <span class="section-kicker">
                    THE PLATFORM
                </span>

                <h2>

                    ONE COMMAND CENTER.

                    <br>

                    <span>
                        EVERY MATCH.
                    </span>

                </h2>

            </div>


            <p>

                Built for tournament organizers
                who need more than a bracket.

                <?php echo htmlspecialchars($systemName); ?>

                connects competition management,
                verification,
                scheduling,
                standings and broadcast presentation
                in one workflow.

            </p>


        </div>


        <div class="workflow">


            <div class="workflow-line"></div>


            <article class="workflow-step">

                <span class="workflow-number">
                    01
                </span>

                <div class="workflow-icon">
                    ◎
                </div>

                <h3>
                    Prepare
                </h3>

                <p>
                    Configure games, formats, schedules,
                    stations and tournament rules.
                </p>

            </article>


            <article class="workflow-step">

                <span class="workflow-number">
                    02
                </span>

                <div class="workflow-icon">
                    ⚔
                </div>

                <h3>
                    Compete
                </h3>

                <p>
                    Manage match lifecycle, team readiness,
                    technical pauses and results.
                </p>

            </article>


            <article class="workflow-step">

                <span class="workflow-number">
                    03
                </span>

                <div class="workflow-icon">
                    ✓
                </div>

                <h3>
                    Verify
                </h3>

                <p>
                    Review submitted results,
                    resolve disputes and protect
                    competition records.
                </p>

            </article>


            <article class="workflow-step">

                <span class="workflow-number">
                    04
                </span>

                <div class="workflow-icon">
                    ◉
                </div>

                <h3>
                    Broadcast
                </h3>

                <p>
                    Push match information
                    and tournament states
                    to presentation-ready overlays.
                </p>

            </article>


        </div>


    </div>


</section>



<section
    class="feature-section section"
    id="features"
>


    <div class="container">


        <div class="section-heading centered-heading">

            <span class="section-kicker">
                CORE CAPABILITIES
            </span>

            <h2>

                BUILT BEYOND THE

                <span>
                    BRACKET.
                </span>

            </h2>

            <p>
                Tournament administration,
                competition intelligence and broadcast
                operations designed as one connected system.
            </p>

        </div>


        <div class="feature-grid">


            <?php foreach ($features as $feature): ?>


                <article class="feature-card">


                    <div class="feature-card-top">

                        <span class="feature-number">
                            <?php echo htmlspecialchars($feature["number"]); ?>
                        </span>

                        <span class="feature-arrow">
                            ↗
                        </span>

                    </div>


                    <h3>
                        <?php echo htmlspecialchars($feature["title"]); ?>
                    </h3>


                    <p>
                        <?php echo htmlspecialchars($feature["description"]); ?>
                    </p>


                    <div class="feature-line"></div>


                </article>


            <?php endforeach; ?>


        </div>


    </div>


</section>



<section
    class="games-section section"
    id="games"
>


    <div class="games-light"></div>


    <div class="container">


        <div class="section-heading split-heading games-heading">


            <div>

                <span class="section-kicker">
                    MULTI-GAME ENGINE
                </span>

                <h2>

                    ONE SYSTEM.

                    <br>

                    <span>
                        MULTIPLE ARENAS.
                    </span>

                </h2>

            </div>


            <p>
                Configure and operate different esports titles
                while keeping the same tournament workflow,
                control structure and reporting environment.
            </p>


        </div>


        <div class="games-grid">


            <?php foreach ($games as $index => $game): ?>


                <article class="game-card">


                    <div class="game-index">

                        <?php

                        echo str_pad(
                            (string)($index + 1),
                            2,
                            "0",
                            STR_PAD_LEFT
                        );

                        ?>

                    </div>


                    <div class="game-monogram">

                        <?php echo htmlspecialchars($game["short"]); ?>

                    </div>


                    <h3>

                        <?php echo htmlspecialchars($game["name"]); ?>

                    </h3>


                    <span>
                        SUPPORTED TITLE
                    </span>


                </article>


            <?php endforeach; ?>


            <article class="game-card add-game">


                <div class="game-index">
                    +
                </div>


                <div class="game-monogram">
                    +
                </div>


                <h3>
                    More Games
                </h3>


                <span>
                    ADMIN CONFIGURABLE
                </span>


            </article>


        </div>


    </div>


</section>



<section
    class="about-section section"
    id="about"
>


    <div class="container about-grid">


        <div class="about-brand-panel">


            <div class="about-logo-card">
                <img src="<?= e(TOURNIVOX_LOGO_URL) ?>" alt="TOURNIVOX logo" class="about-logo-image">
            </div>


            <p>
                CAPSTONE PROJECT
            </p>


            <h2>
                <?php echo htmlspecialchars($systemName); ?>
            </h2>


            <span class="about-tagline">
                <?php echo htmlspecialchars($systemTagline); ?>
            </span>


        </div>


        <div class="about-content">


            <span class="section-kicker">
                ABOUT THE PROJECT
            </span>


            <h2>

                COMPETITION TECHNOLOGY

                <br>

                <span>
                    WITH PURPOSE.
                </span>

            </h2>


            <p class="capstone-title">

                <?php echo htmlspecialchars($capstoneTitle); ?>

            </p>


            <p>

                Developed as an academic capstone project,
                the platform focuses on solving real
                tournament-operation challenges such as
                fragmented match handling, manual standings,
                event delays, result verification and
                disconnected broadcast presentation workflows.

            </p>


            <div class="about-values">


                <span>
                    <b></b>
                    Competition
                </span>


                <span>
                    <b></b>
                    Innovation
                </span>


                <span>
                    <b></b>
                    Reliability
                </span>


                <span>
                    <b></b>
                    Integrity
                </span>


            </div>


            <a
                href="<?= e(TOURNIVOX_BASE_URL) ?>/auth/login.php"
                class="btn btn-primary"
            >

                Enter System

                <span>
                    →
                </span>

            </a>


        </div>


    </div>


</section>



<section class="cta-section">


    <div class="container cta-inner">


        <div class="cta-code">
            TVX // READY
        </div>


        <div>

            <span class="section-kicker light">
                READY FOR MATCH DAY?
            </span>


            <h2>

                FROM FIRST CHECK-IN

                <br>

                TO

                <span>
                    FINAL BROADCAST.
                </span>

            </h2>

        </div>


        <?php if ($liveAvailable): ?>


            <a
                href="<?php echo htmlspecialchars($liveUrl); ?>"
                class="cta-button"
            >

                <span>
                    WATCH LIVE
                </span>

                <b>
                    →
                </b>

            </a>


        <?php else: ?>


            <a
                href="<?= e(TOURNIVOX_BASE_URL) ?>/auth/login.php"
                class="cta-button"
            >

                <span>
                    LAUNCH
                </span>

                <b>
                    →
                </b>

            </a>


        <?php endif; ?>


    </div>


</section>


</main>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
