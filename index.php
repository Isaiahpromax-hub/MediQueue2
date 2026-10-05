<?php
require_once 'includes/auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/* Contact Us form (guest submissions, stored for admin only) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $errors = [];
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    }
    $contact = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
    ];
    if ($contact['name'] === '' || $contact['email'] === '' || $contact['subject'] === '' || $contact['message'] === '') {
        $errors[] = 'Please fill in all fields.';
    } elseif (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!$errors) {
        db()->insert(
            "INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)",
            [$contact['name'], $contact['email'], $contact['subject'], $contact['message'], $_SERVER['REMOTE_ADDR'] ?? null]
        );
        $admins = db()->fetchAll("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        foreach ($admins as $a) {
            createNotification($a['id'], 'New Contact Message', $contact['name'] . ' (' . $contact['email'] . ') sent: ' . $contact['subject'], 'system');
        }
        setFlash('success', 'Thank you! Your message has been received. Our team will get back to you soon.');
    } else {
        setFlash('danger', implode(' ', $errors));
    }
    header('Location: index.php#contact');
    exit;
}

$contactFlash = getFlash();

if (isLoggedIn()) {
    $role = getRole();
    if ($role === 'patient') redirect('patient/dashboard.php');
    elseif ($role === 'receptionist') redirect('staff/dashboard.php');
    elseif ($role === 'doctor') redirect('doctor/dashboard.php');
    elseif ($role === 'admin') redirect('admin/dashboard.php');
    elseif ($role === 'nurse') redirect('nurse/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediQueue - Healthcare Queue & Appointment Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=5">
    <script>document.documentElement.classList.add('js');</script>
    <style>
        /* Slow zoom in/out ("Ken Burns") effect on the hero background image */
        .hero-bg-zoom-wrap{
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: 0;
        }
        .hero-bg{
            width: 100%;
            height: 100%;
            animation: hero-zoom 22s ease-in-out infinite alternate;
            transform-origin: center center;
            will-change: transform;
            transition: opacity 1.3s ease;
        }
        @keyframes hero-zoom{
            from { transform: scale(1); }
            to   { transform: scale(1.15); }
        }
        @media (prefers-reduced-motion: reduce){
            .hero-bg{ animation: none; }
        }
        .hero-shade{ position: relative; z-index: 1; }
        .landing-nav{ position: relative; z-index: 2; }
        .hero-content{ position: relative; z-index: 2; }
    </style>
</head>
<body>
<div class="landing-hero" id="home">
    <div class="hero-bg-zoom-wrap"><div class="hero-bg"></div></div>
    <div class="hero-shade"></div>
    <nav class="landing-nav">
        <div class="landing-logo" style="display:flex;align-items:center;gap:14px;">
            <img src="assets/images/logo.png?v=<?php echo @filemtime('assets/images/logo.png') ?: 1; ?>" alt="MediQueue logo" style="height:84px;width:84px;object-fit:contain;filter:brightness(0.82) saturate(0.95);">
            <span style="color:#ffffff;font-size:3.8rem;font-weight:800;line-height:1;">Medi<span>Queue</span></span>
        </div>


        <div class="d-flex gap-10">
            <a href="register.php" class="btn btn-outline-white">Register</a>
            <a href="login.php" class="btn btn-info">Login</a>
            <a href="#about" class="btn btn-outline-white">About Us</a>
             <a href="#contact" class="btn btn-outline-white">Contact Us</a>
        </div>


    </nav>
    <div class="hero-content">
        <div class="hero-text">
            <h1>Skip the waiting room. Manage your <span>healthcare journey</span>.</h1>
            <p>MediQueue lets you book appointments, join virtual queues, and monitor your position in real-time  all from your device. No more long waits in crowded waiting rooms.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn btn-info btn-lg">Get Started Free</a>
                <a href="login.php" class="btn btn-outline-white btn-lg">Sign In</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="number">1000+</div>
                    <div class="label">Patients Served</div>
                </div>
                <div class="hero-stat">
                    <div class="number">50+</div>
                    <div class="label">Doctors</div>
                </div>
                <div class="hero-stat">
                    <div class="number">24/7</div>
                    <div class="label">Queue Access</div>
                </div>
            </div>
        </div>
</div>
    </div>
</div>

<nav class="sticky-nav" id="stickyNav">
    <a href="#home" class="sticky-nav-brand">Medi<span>Queue</span></a>
    <div class="landing-nav-links">
        <a href="#how-it-works" class="landing-nav-link">How It Works</a>
        <a href="#features" class="landing-nav-link">Features</a>
        <a href="#about" class="landing-nav-link">About Us</a>
        <a href="#contact" class="landing-nav-link">Contact Us</a>
    </div>
    <div class="d-flex gap-10">
        <a href="register.php" class="btn btn-outline-white">Register</a>
        <a href="login.php" class="btn btn-info">Login</a>
    </div>
</nav>


<section class="how-it-works" id="how-it-works">
    <div class="section-title reveal">
        <h2>How MediQueue Works</h2>
        <p>Getting started is simple. Follow these five easy steps.</p>
    </div>
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-number">1</div>
            <h3>Create an Account</h3>
            <p>Register with your email and personal details to get started.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <h3>Join or Book</h3>
            <p>Join a virtual queue for your preferred service or book an appointment.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <h3>Monitor Your Position</h3>
            <p>Track your queue position and estimated wait time in real-time.</p>
        </div>
        <div class="step-card">
            <div class="step-number">4</div>
            <h3>Arrive on Time</h3>
            <p>Get notified when it's almost your turn and head to the medical Room.</p>
        </div>
        <div class="step-card">
            <div class="step-number">5</div>
            <h3>Get Served</h3>
            <p>Receive quality healthcare without the long wait.</p>
        </div>
    </div>
</section>






<section class="features-section" id="features">
    <div class="section-title reveal">
        <h2>Features Built for You</h2>
        <p>Everything you need for a seamless healthcare experience.</p>
    </div>

    <div class="about-slider" id="featuresSlider">
        <div class="about-slider-track about-slider-track-reverse" id="featuresSliderTrack">
            <div class="feature-card about-slide">
                <div class="feature-icon blue">&#128197;</div>


                <h3>Book Appointments</h3>
                <p>Schedule appointments with your preferred doctor and department at your convenience.</p>
            </div>


            <div class="feature-card about-slide">
                <div class="feature-icon orange">&#9201;</div>


                <h3>Virtual Queue</h3>
                <p>Join the queue remotely and monitor your position in real-time from anywhere.</p>
            </div>
            <div class="feature-card about-slide">
                


                <h3>Smart Notifications</h3>
                <p>Receive timely alerts about your queue position, appointments, and updates.</p>
            </div>
            <div class="feature-card about-slide">
                

                <h3>Real-time Updates</h3>
                <p>Watch your queue status update live without refreshing the page.</p>
            </div>


            <div class="feature-card about-slide">
               
                <h3>Manage Profile</h3>
                <p>Keep your personal and medical information up to date in one place.</p>
            </div>

            <div class="feature-card about-slide">
               

                <h3>Queue History</h3>
                <p>View your past queue records, waiting times, and service details.</p>
            </div>
            <!-- duplicated for seamless infinite loop, hidden from assistive tech -->
            <div class="feature-card about-slide" aria-hidden="true">
                
                <h3>Book Appointments</h3>
                <p>Schedule appointments with your preferred doctor and department at your convenience.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
                

                <h3>Virtual Queue</h3>
                <p>Join the queue remotely and monitor your position in real-time from anywhere.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
              
                <h3>Smart Notifications</h3>
                <p>Receive timely alerts about your queue position, appointments, and updates.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
                
                <h3>Real-time Updates</h3>
                <p>Watch your queue status update live without refreshing the page.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
              
                <h3>Manage Profile</h3>
                <p>Keep your personal and medical information up to date in one place.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
              
                <h3>Queue History</h3>
                <p>View your past queue records, waiting times, and service details.</p>
            </div>
        </div>
    </div>
</section>






<section class="features-section reveal" id="about">
    <div class="section-title">
        <h2>About Us</h2>
        <p>We are on a mission to make healthcare visits faster, calmer, and more predictable.</p>
    </div>

    <div class="about-slider" id="aboutSlider">
        <div class="about-slider-track" id="aboutSliderTrack">
            <div class="feature-card about-slide">
                
                <h3>Our Mission</h3>
                <p>To eliminate the stress of crowded waiting rooms by giving patients a clear view of their queue and appointments, and by helping healthcare staff manage their day efficiently.</p>
            </div>
            <div class="feature-card about-slide">
               
                <h3>Why MediQueue</h3>
                <p>Real-time queue updates, online appointment booking, urgent and emergency triage, and instant notifications mean shorter waits for patients and smoother operations for staff.</p>
            </div>
            <div class="feature-card about-slide">
                
                <h3>Trusted Healthcare</h3>
                <p>Built with healthcare providers in mind — from receptionists and nurses to doctors — so every department works together in one system.</p>
            </div>
            <div class="feature-card about-slide">
                
                <h3>Our Team</h3>
                <p>A dedicated group of developers, clinicians, and support staff working together to make every clinic visit simpler and more predictable.</p>
            </div>
            <div class="feature-card about-slide">
               
                <h3>Patient First</h3>
                <p>Every feature we build starts with one question: does this make the patient's experience calmer and more transparent?</p>
            </div>
            <!-- duplicated for seamless infinite loop, hidden from assistive tech -->
            <div class="feature-card about-slide" aria-hidden="true">
                

                <h3>Our Mission</h3>
                <p>To eliminate the stress of crowded waiting rooms by giving patients a clear view of their queue and appointments, and by helping healthcare staff manage their day efficiently.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
               

                <h3>Why MediQueue</h3>
                <p>Real-time queue updates, online appointment booking, urgent and emergency triage, and instant notifications mean shorter waits for patients and smoother operations for staff.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
                
                <h3>Trusted Healthcare</h3>
                <p>Built with healthcare providers in mind — from receptionists and nurses to doctors — so every department works together in one system.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
                
                <h3>Our Team</h3>
                <p>A dedicated group of developers, clinicians, and support staff working together to make every clinic visit simpler and more predictable.</p>
            </div>
            <div class="feature-card about-slide" aria-hidden="true">
              
                <h3>Patient First</h3>
                <p>Every feature we build starts with one question: does this make the patient's experience calmer and more transparent?</p>
            </div>
        </div>
    </div>
</section>

<style>
/* ---- About Us auto-sliding boxes ---- */
.about-slider{
    position: relative;
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    overflow: hidden;
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 48px, #000 calc(100% - 48px), transparent 100%);
    mask-image: linear-gradient(90deg, transparent 0, #000 48px, #000 calc(100% - 48px), transparent 100%);
}
.about-slider-track{
    display: flex;
    gap: 24px;
    width: max-content;
    animation: about-slide-scroll 28s linear infinite;
}
.about-slider:hover .about-slider-track,
.about-slider:focus-within .about-slider-track,
.about-slider.is-paused .about-slider-track{
    animation-play-state: paused;
}
.about-slide{
    flex: 0 0 280px;
    width: 280px;
    margin: 0;
}
@keyframes about-slide-scroll{
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}
.about-slider-track-reverse{
    animation-name: about-slide-scroll-reverse;
    animation-duration: 34s;
}
@keyframes about-slide-scroll-reverse{
    from { transform: translateX(-50%); }
    to   { transform: translateX(0); }
}
@media (prefers-reduced-motion: reduce){
    .about-slider-track{
        animation: none;
        overflow-x: auto;
        scroll-snap-type: x proximity;
    }
    .about-slide{ scroll-snap-align: start; }
}
@media (max-width: 600px){
    .about-slide{ flex-basis: 220px; width: 220px; }
}

/* ---- About card tap-to-read zoom modal ---- */
.about-modal-overlay{
    position: fixed;
    inset: 0;
    background: rgba(10, 22, 40, .66);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .28s ease;
}
.about-modal-overlay.open{
    opacity: 1;
    pointer-events: auto;
}
.about-modal-card{
    position: relative;
    background: var(--white);
    max-width: 600px;
    width: 100%;
    border-radius: 18px;
    padding: 38px 34px;
    box-shadow: 0 26px 70px rgba(0, 0, 0, .4);
    transform: scale(.72) translateY(20px);
    transition: transform .32s cubic-bezier(.18, .9, .28, 1.15);
}
.about-modal-overlay.open .about-modal-card{
    transform: scale(1) translateY(0);
}
.about-modal-card .feature-icon{
    width: 66px;
    height: 66px;
    font-size: 2rem;
    margin-bottom: 18px;
}
.about-modal-card h3{
    font-size: 1.8rem;
    color: var(--ink);
    margin-bottom: 12px;
}
.about-modal-card p{
    font-size: 1.08rem;
    color: var(--gray);
    line-height: 1.65;
    margin: 0;
}
.about-modal-close{
    position: absolute;
    top: 12px;
    right: 14px;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: var(--body-bg);
    color: var(--ink);
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
    transition: background .2s ease, transform .2s ease;
}
.about-modal-close:hover{
    background: var(--danger);
    color: #fff;
    transform: scale(1.08);
}

/* ---- Contact Us section ---- */
.contact-grid{
    display: flex;
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
    flex-wrap: wrap;
    align-items: stretch;
}
.contact-info{
    flex: 1 1 360px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}
.contact-card{
    background: var(--white);
    border-radius: 14px;
    padding: 24px 22px;
    box-shadow: var(--shadow);
    transition: transform .2s ease, box-shadow .2s ease;
}
.contact-card:hover{
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}
.contact-card h3{ font-size: 1.05rem; margin-bottom: 6px; }
.contact-card p{ color: var(--gray); font-size: .95rem; margin: 0; }
.contact-form-card{
    flex: 1 1 360px;
    background: var(--white);
    border-radius: 16px;
    padding: 30px;
    box-shadow: var(--shadow-lg);
}
.contact-form-card h3{ font-size: 1.3rem; margin-bottom: 18px; }
.contact-form .form-control{ width: 100%; margin-bottom: 14px; }
.form-row{ display: flex; gap: 14px; }
.form-row .form-control{ flex: 1; }
@media (max-width: 560px){
    .form-row{ flex-direction: column; gap: 0; }
}
</style>

<section class="features-section reveal" id="contact">
    <div class="section-title">
        <h2>Contact Us</h2>
        <p>Have a question or need help with the system? Reach out to our team anytime.</p>
    </div>
    <div class="contact-grid">
        <div class="contact-info">
            <div class="contact-card">
                <div class="feature-icon blue">&#128205;</div>
                <h3>Visit Us</h3>
                <p>Soroti-Arapai branch<br>Soroti, Uganda</p>
            </div>
            <div class="contact-card">
                <div class="feature-icon teal">&#128222;</div>
                <h3>Call Us</h3>
                <p>+256 700 000 000<br>+256 711 000 000</p>
            </div>
            <div class="contact-card">
                <div class="feature-icon green">&#128231;</div>
                <h3>Email Us</h3>
                <p>support@mediqueue.com<br>info@mediqueue.com</p>
            </div>
            <div class="contact-card">
                <div class="feature-icon orange">&#128337;</div>
                <h3>Working Hours</h3>
                <p>Mon &ndash; Sat: 8:00 AM &ndash; 12:00 AM<br>Sun: 9:00 AM &ndash; 10:00 PM</p>
            </div>
        </div>
        <div class="contact-form-card">
            <h3>Send Us a Message</h3>
            <?php if ($contactFlash): ?>
                <div class="alert alert-<?php echo $contactFlash['type']; ?>" style="margin-bottom:14px"><?php echo escape($contactFlash['message']); ?></div>
            <?php endif; ?>
            <form class="contact-form" method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="contact_submit" value="1">
                <div class="form-row">
                    <input type="text" name="name" placeholder="Your name" class="form-control" required>
                    <input type="email" name="email" placeholder="Your email" class="form-control" required>
                </div>
                <input type="text" name="subject" placeholder="Subject" class="form-control" required>
                <textarea name="message" rows="4" placeholder="Your message..." class="form-control" required></textarea>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
    
</section>

<footer class="landing-footer reveal">
    <p>&copy; 2026 MediQueue. All rights reserved. | Healthcare Queue & Appointment Management System</p>
</footer>
<script>
(function(){
    var revealEls = document.querySelectorAll('.reveal');

    function revealAll() {
        revealEls.forEach(function(el){ el.classList.add('in-view'); });
    }

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var step = 0;
                var prev = el.previousElementSibling;
                while (prev) {
                    if (prev.classList.contains('reveal')) step++;
                    prev = prev.previousElementSibling;
                }
                el.style.transitionDelay = Math.min(step * 80, 480) + 'ms';
                el.classList.add('in-view');
                io.unobserve(el);
            });
        }, { threshold: 0.12 });
        revealEls.forEach(function(el){ io.observe(el); });
    } else {
        revealAll();
    }

    setTimeout(function(){
        document.querySelectorAll('.hero-stat .number').forEach(function(el){
            var m = el.textContent.match(/^(\d+)(.*)$/);
            if (!m) return;
            var end = parseInt(m[1], 10), suffix = m[2], start = null, dur = 1300;
            function tick(ts){
                if (start === null) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(end * eased).toLocaleString() + suffix;
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }, 700);

    // Pause the auto-sliding boxes (About Us + Features) when off-screen
    var autoSliders = [
        document.getElementById('aboutSlider'),
        document.getElementById('featuresSlider')
    ];
    if ('IntersectionObserver' in window) {
        var sliderIO = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                entry.target.classList.toggle('is-paused', !entry.isIntersecting);
            });
        }, { threshold: 0.05 });
        autoSliders.forEach(function(el){ if (el) sliderIO.observe(el); });
    }

    document.querySelectorAll('.landing-nav a[href^="#"], .sticky-nav a[href^="#"]').forEach(function(link){
        link.addEventListener('click', function(e){
            var target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Show the floating section nav once the visitor scrolls past the hero
    var stickyNav = document.getElementById('stickyNav');
    if (stickyNav) {
        var stickyShown = false;
        function updateStickyNav(){
            var y = window.scrollY || document.documentElement.scrollTop;
            var show = y > 320;
            if (show !== stickyShown) {
                stickyShown = show;
                stickyNav.classList.toggle('show', show);
            }
        }
        window.addEventListener('scroll', updateStickyNav, { passive: true });
        updateStickyNav();
    }
})();

/* Auto-rotate the hero background image */
(function(){
    var heroes = <?php
        $heroImgs = ['hero-doctor-patient.jpg','logo2.jpg','logo3.jpg','logo4.jpg','logo5.jpg','logo8.jpg','logo10.jpg','logo11.jpg','logo12.jpg','logo13.jpg','logo14.jpg'];
        $heroList = [];
        foreach ($heroImgs as $h) {
            $ts = @filemtime("assets/images/$h") ?: time();
            $heroList[] = "assets/images/$h?v=$ts";
        }
        echo json_encode($heroList);
    ?>;
    var bg = document.querySelector('.hero-bg');
    if (!bg || heroes.length < 2) return;
    var i = 0;

    function show(src) {
        var probe = new Image();
        probe.onload = function(){
            bg.style.backgroundImage = "url('" + src + "')";
            bg.style.opacity = '.75';
        };
        probe.src = src;
    }

    show(heroes[0]);

    setInterval(function(){
        if (document.hidden) return;
        i = (i + 1) % heroes.length;
        bg.style.opacity = '0';
        setTimeout(function(){ show(heroes[i]); }, 1300);
    }, 6000);
})();

/* Tap a moving card to zoom it open and read the message */
(function(){
    var overlay = document.createElement('div');
    overlay.className = 'about-modal-overlay';
    overlay.innerHTML = '<button type="button" class="about-modal-close" aria-label="Close">&times;</button><div class="about-modal-card"></div>';
    document.body.appendChild(overlay);
    var card = overlay.querySelector('.about-modal-card');

    function resumeSliders(){
        document.querySelectorAll('.about-slider.is-paused').forEach(function(slider){
            slider.classList.remove('is-paused');
        });
    }
    function openCard(slide){
        card.innerHTML = slide.querySelector('.feature-icon').outerHTML +
                         '<h3>' + slide.querySelector('h3').innerText + '</h3>' +
                         '<p>' + slide.querySelector('p').innerText + '</p>';
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeOverlay(){
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        resumeSliders();
    }

    document.querySelectorAll('.about-slider').forEach(function(slider){
        slider.addEventListener('click', function(e){
            var slide = e.target.closest('.feature-card.about-slide');
            if (!slide) return;
            e.preventDefault();
            slider.classList.add('is-paused');
            openCard(slide);
        });
    });

    overlay.addEventListener('click', function(e){
        if (e.target === overlay || e.target.classList.contains('about-modal-close')) {
            closeOverlay();
        }
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeOverlay();
    });
})();
</script>
</body>
</html>