<?php $store_name = $store_name ?? getSetting('store_name', 'Smart Market'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((isLoggedIn() ? getRoleLabel($_SESSION['role']) : 'Login') . ' - ' . $store_name); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>window.CSRF_TOKEN = <?php echo json_encode(csrfToken()); ?>;</script>
    <script>
    // Keep the URL clean: hide "?page=..." from the address bar (page is kept in the session)
    (function(){
        var q = window.location.search;
        if (/^\?page=[^&]+$/.test(q) && window.history && window.history.replaceState) {
            window.history.replaceState({}, '', window.location.pathname);
        }
    })();
    </script>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<!-- ============================================ -->
<!-- TOAST NOTIFICATION CONTAINER -->
<!-- ============================================ -->
<div class="toast-container" id="toastContainer"></div>

<?php if (!isLoggedIn()): ?>
    <?php if (isset($_GET['2fa']) && isset($_SESSION['2fa_required'])): ?>
        <div class="login-page">
            <div class="login-container">
                <div class="logo-large">🔐</div>
                <h2>Two-Factor Authentication</h2>
                <p class="subtitle">Enter the verification code</p>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Verification Code</label>
                        <div class="input-with-icon">
                            <span class="input-icon">🔑</span>
                            <input type="text" name="2fa_code" placeholder="Enter 6-digit code" required maxlength="6" autofocus>
                        </div>
                        <small style="color:var(--text-light);">For demo, use code: 123456</small>
                    </div>
                    <button type="submit" name="verify_2fa" class="btn btn-primary btn-block">✅ Verify</button>
                    <a href="?logout=1" class="btn btn-outline btn-block" style="margin-top:0.5rem;">← Back to Login</a>
                </form>
            </div>
        </div>
        <script>
            document.querySelector('input[name="2fa_code"]').focus();
        </script>
    <?php else: ?>
       <div class="login-page">
    <div class="login-wrapper">
        
        <!-- ============================================ -->
        <!-- LEFT PANEL - BRANDING WITH PHOTO -->
        <!-- ============================================ -->
        <div class="login-left-panel">
    
            <!-- Photo background (entire panel) -->
            <div class="left-panel-bg"></div>
            
            <!-- Purple gradient overlay -->
            <div class="left-panel-overlay"></div>
            
            <!-- Decorative circles -->
            <div class="decor-circle decor-circle-1"></div>
            <div class="decor-circle decor-circle-2"></div>
            
            <!-- Content area -->
            <div class="left-panel-content">
                
                <!-- Logo -->
                <div class="brand-logo">
                    <svg viewBox="0 0 100 100" width="110" height="110" xmlns="http://www.w3.org/2000/svg">
                        <line x1="8" y1="32" x2="18" y2="32" stroke="rgba(255,255,255,0.7)" stroke-width="3" stroke-linecap="round"/>
                        <line x1="4" y1="42" x2="16" y2="42" stroke="rgba(255,255,255,0.5)" stroke-width="3" stroke-linecap="round"/>
                        <line x1="10" y1="52" x2="18" y2="52" stroke="rgba(255,255,255,0.7)" stroke-width="3" stroke-linecap="round"/>
                        <path d="M 26 30 L 26 24 L 82 24 L 72 44" 
                            fill="none" 
                            stroke="white" 
                            stroke-width="6" 
                            stroke-linecap="round" 
                            stroke-linejoin="round"/>
                        <text x="55" y="62" 
                            font-family="Inter, Arial, sans-serif" 
                            font-size="34" 
                            font-weight="900" 
                            fill="white" 
                            text-anchor="middle" 
                            letter-spacing="-2">SM</text>
                        <circle cx="48" cy="78" r="4.5" fill="white"/>
                        <circle cx="74" cy="78" r="4.5" fill="white"/>
                    </svg>
                </div>
                
                <!-- Brand name -->
                <h1 class="brand-name">
                    <?php 
                    $store_parts = explode(' ', $store_name, 2);
                    if (count($store_parts) == 2) {
                        echo htmlspecialchars($store_parts[0]) . ' <span class="brand-accent">' . htmlspecialchars($store_parts[1]) . '</span>';
                    } else {
                        echo htmlspecialchars($store_name);
                    }
                    ?>
                </h1>
                
                <p class="brand-tagline">Shop More, Save More.</p>
                
                <div class="brand-divider"></div>
                
                <!-- Features -->
                <div class="brand-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Wide Selection</strong>
                            <span>Everything in one place.</span>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                <path d="M9 12l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Secure Access</strong>
                            <span>Your data is protected.</span>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                        </div>
                        <div class="feature-text">
                            <strong>Fast & Efficient</strong>
                            <span>Streamlined for staff.</span>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Bottom tagline -->
            <p class="brand-bottom-tagline">Work Smarter,<br>Serve Better.</p>
            
        </div>
        
        <!-- ============================================ -->
        <!-- RIGHT PANEL - EMPLOYEE LOGIN FORM -->
        <!-- ============================================ -->
        <div class="login-right-panel">
            
            <!-- Decorative shapes -->
            <div class="decor-shape decor-shape-1"></div>
            <div class="decor-shape decor-shape-2"></div>
            
            <div class="form-wrapper">
                
                <!-- Welcome label -->
                <div class="welcome-label">
                    <span class="welcome-line"></span>
                </div>
                
                <!-- Heading -->
                <h2 class="login-heading">
                    Login to Your<br>
                    <span class="heading-accent">Smart Market</span> Account
                </h2>
                
                <!-- Subtitle -->
                <p class="login-subtitle">Access the employee dashboard to manage daily operations securely.</p>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" style="margin-bottom:16px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning" style="margin-bottom:16px;">⏰ Your session has expired. Please login again.</div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" autocomplete="off" id="loginForm">
                    
                    <!-- Username -->
                    <div class="form-group-modern">
                        <label>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Username
                        </label>
                        <div class="input-modern">
                            <span class="input-icon-modern">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            <input type="text" name="username" placeholder="Enter your username" required autocomplete="off" id="username">
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group-modern">
                        <label>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Password
                        </label>
                        <div class="input-modern">
                            <span class="input-icon-modern">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <input type="password" name="password" placeholder="Enter your password" required autocomplete="new-password" id="password">
                            <button type="button" class="toggle-password-modern" onclick="togglePassword()" title="Show/Hide Password">
                                <span id="eyeIcon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Remember + Forgot -->
                    <div class="form-options-modern">
                        <label class="remember-modern">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Remember Me</span>
                        </label>
                        <a href="#" class="forgot-modern" onclick="showForgotPassword(); return false;">Forgot Password?</a>
                    </div>
                    
                    <!-- Login Button -->
                    <button type="submit" name="login" class="login-btn-modern">
                        <span class="login-arrow">→</span>
                        <span>Login</span>
                    </button>
                    
                </form>

                <?php
                $openJobs = [];
                try {
                    $openJobs = $pdo->query("SELECT id, title, department, location, type, salary_min, salary_max, description, requirements, created_at FROM job_postings WHERE status = 'open' ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $openJobs = [];
                }
                $jobTypeLabels = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'];
                ?>
                <?php if (!empty($openJobs)): ?>
                    <!-- Job postings banner -->
                    <button type="button" class="hiring-banner" onclick="document.getElementById('jobsModal').classList.add('show')">
                        <span class="hiring-banner-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </span>
                        <span class="hiring-banner-text">
                            <strong>We're Hiring!</strong>
                            <span><?php echo count($openJobs); ?> open position<?php echo count($openJobs) === 1 ? '' : 's'; ?> &mdash; view details</span>
                        </span>
                        <span class="hiring-banner-arrow">→</span>
                    </button>
                <?php endif; ?>
                
                <!-- Footer badges -->
                <div class="footer-badges">
                    <div class="footer-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>Safe & Secure</span>
                    </div>
                    <div class="footer-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                        <span>Fast Access</span>
                    </div>
                    <div class="footer-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Employee Portal</span>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
</div>
        <div class="modal" id="forgotModal">
            <div class="modal-content forgot-modal-content">
                
                <!-- Header -->
                <div class="forgot-header">
                    <div class="forgot-header-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div class="forgot-header-text">
                        <h2>Reset Password</h2>
                        <p>Enter your username to create a new password</p>
                    </div>
                    <button class="forgot-close" onclick="closeForgotModal()" title="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                
                <!-- Info Banner -->
                <div class="forgot-info-banner">
                    <div class="forgot-info-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                    </div>
                    <div class="forgot-info-text">
                        Enter your username and choose a new password. You'll be able to log in immediately.
                    </div>
                </div>
                
                <!-- Form -->
                <form id="resetForm" onsubmit="resetPassword(event)" class="forgot-form">
                    
                    <div class="forgot-field">
                        <label class="forgot-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Username
                        </label>
                        <div class="forgot-input-wrap">
                            <input type="text" id="resetUsername" placeholder="Enter your username" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="forgot-field">
                        <label class="forgot-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            New Password
                        </label>
                        <div class="forgot-input-wrap">
                            <input type="password" id="resetNewPassword" placeholder="Minimum 4 characters" required minlength="4" autocomplete="new-password">
                            <button type="button" class="forgot-toggle-pw" onclick="toggleResetPw('resetNewPassword', this)" title="Show password">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="forgot-field">
                        <label class="forgot-label">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            Confirm Password
                        </label>
                        <div class="forgot-input-wrap">
                            <input type="password" id="resetConfirmPassword" placeholder="Re-enter new password" required autocomplete="new-password">
                            <button type="button" class="forgot-toggle-pw" onclick="toggleResetPw('resetConfirmPassword', this)" title="Show password">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="forgot-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 2v6h-6"></path>
                            <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                            <path d="M3 22v-6h6"></path>
                            <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                        </svg>
                        Reset Password
                    </button>
                </form>
                
            </div>
        </div>

        <?php if (!empty($openJobs)): ?>
        <div class="modal" id="jobsModal" onclick="if (event.target === this) this.classList.remove('show');">
            <div class="modal-content jobs-modal-content">
                <div class="modal-header">
                    <h2>Open Positions</h2>
                    <button type="button" class="close" onclick="document.getElementById('jobsModal').classList.remove('show')" title="Close">&times;</button>
                </div>
                <?php foreach ($openJobs as $job): ?>
                    <div class="job-card">
                        <div class="job-card-head">
                            <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                            <span class="job-type-badge"><?php echo htmlspecialchars($jobTypeLabels[$job['type']] ?? $job['type']); ?></span>
                        </div>
                        <div class="job-meta">
                            <?php if (!empty($job['department'])): ?><span>🏢 <?php echo htmlspecialchars($job['department']); ?></span><?php endif; ?>
                            <?php if (!empty($job['location'])): ?><span>📍 <?php echo htmlspecialchars($job['location']); ?></span><?php endif; ?>
                            <?php if ($job['salary_min'] !== null || $job['salary_max'] !== null): ?>
                                <span>💰 <?php
                                    $min = $job['salary_min'] !== null ? '₱' . number_format((float)$job['salary_min'], 2) : '';
                                    $max = $job['salary_max'] !== null ? '₱' . number_format((float)$job['salary_max'], 2) : '';
                                    echo htmlspecialchars($min && $max ? "$min – $max" : ($min ?: $max));
                                ?></span>
                            <?php endif; ?>
                            <span>🗓 Posted <?php echo htmlspecialchars(date('M j, Y', strtotime($job['created_at']))); ?></span>
                        </div>
                        <?php if (!empty($job['description'])): ?>
                            <p class="job-section-label">Description</p>
                            <p class="job-text"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($job['requirements'])): ?>
                            <p class="job-section-label">Requirements</p>
                            <p class="job-text"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <script>
                function togglePassword() {
                    var passwordInput = document.getElementById('password');
                    var eyeIcon = document.getElementById('eyeIcon');
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        eyeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                    } else {
                        passwordInput.type = 'password';
                        eyeIcon.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    }
                }

                function toggleResetPw(inputId, btn) {
                    var input = document.getElementById(inputId);
                    if (!input) return;
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
                        btn.title = 'Hide password';
                    } else {
                        input.type = 'password';
                        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                        btn.title = 'Show password';
                    }
                }
            
            function showForgotPassword() {
                document.getElementById('forgotModal').classList.add('show');
                document.getElementById('resetUsername').value = '';
                document.getElementById('resetNewPassword').value = '';
                document.getElementById('resetConfirmPassword').value = '';
            }
            
            function closeForgotModal() {
                document.getElementById('forgotModal').classList.remove('show');
            }
            
            function resetPassword(e) {
                e.preventDefault();
                var username = document.getElementById('resetUsername').value.trim();
                var newPassword = document.getElementById('resetNewPassword').value;
                var confirmPassword = document.getElementById('resetConfirmPassword').value;
                
                if (!username) { alert('Please enter your username'); return; }
                if (newPassword.length < 4) { alert('Password must be at least 4 characters'); return; }
                if (newPassword !== confirmPassword) { alert('Passwords do not match!'); return; }
                
                fetch('?action=check_user&username=' + encodeURIComponent(username))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data.exists) {
                            alert('❌ Username not found. Please check and try again.');
                            return;
                        }
                        var formData = new FormData();
                        formData.append('username', username);
                        formData.append('new_password', newPassword);
                        fetch('?action=reset_password', { method: 'POST', body: formData })
                            .then(function(res) { return res.json(); })
                            .then(function(result) {
                                if (result.success) {
                                    alert('✅ Password reset successfully!\n\nUsername: ' + username);
                                    closeForgotModal();
                                } else {
                                    alert('❌ Error resetting password: ' + (result.message || 'Unknown error'));
                                }
                            });
                    })
                    ['catch'](function() { alert('⚠️ An error occurred. Please try again.'); });
            }
            
            document.getElementById('forgotModal').addEventListener('click', function(e) {
                if (e.target === this) { closeForgotModal(); }
            });
        </script>
    <?php endif; ?>
    
<?php else: ?>
    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">
                <?php 
                $logo = getSetting('store_logo', '');
                if (!empty($logo) && file_exists($logo)): 
                ?>
                    <img src="<?php echo $logo; ?>?t=<?php echo time(); ?>" alt="Store Logo">
                <?php else: ?>
                    SM
                <?php endif; ?>
            </div>
            <div class="brand-text"><?php echo $store_name; ?><small>Point of Sale</small></div>
        </div>
        
      <svg class="nav-icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="nav-dashboard" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></symbol>
        <symbol id="nav-cart" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></symbol>
        <symbol id="nav-finance" viewBox="0 0 24 24"><path d="M12 2v20m5-16H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></symbol>
        <symbol id="nav-returns" viewBox="0 0 24 24"><path d="M3 7v6h6M21 17v-6h-6"/><path d="M5.1 13a7 7 0 0 0 11.8 3L21 13M18.9 11A7 7 0 0 0 7.1 8L3 11"/></symbol>
        <symbol id="nav-people" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></symbol>
        <symbol id="nav-products" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></symbol>
        <symbol id="nav-stock" viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></symbol>
        <symbol id="nav-purchases" viewBox="0 0 24 24"><path d="M12 3v13m-5-5 5 5 5-5"/><path d="M5 17v4h14v-4"/></symbol>
        <symbol id="nav-suppliers" viewBox="0 0 24 24"><path d="M3 7h11v12H3zM14 11h4l3 3v5h-7"/><circle cx="7.5" cy="19" r="2"/><circle cx="17.5" cy="19" r="2"/></symbol>
        <symbol id="nav-reports" viewBox="0 0 24 24"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/><path d="M16 6h4v4"/></symbol>
        <symbol id="nav-categories" viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></symbol>
        <symbol id="nav-archive" viewBox="0 0 24 24"><path d="M3 4h18v5H3zM5 9v11h14V9M10 13h4"/></symbol>
        <symbol id="nav-customers" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 5a4 4 0 0 1 0 7m2 3a5 5 0 0 1 3 4v2"/></symbol>
        <symbol id="nav-sales" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="nav-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m-3-3h6"/></symbol>
        <symbol id="nav-activity" viewBox="0 0 24 24"><path d="M3 12h4l3-8 4 16 3-8h4"/></symbol>
        <symbol id="nav-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.7-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1 0 2Z"/></symbol>
        <symbol id="nav-notifications" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 13h4"/></symbol>
        <symbol id="nav-add" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="nav-attendance" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 5 2 2 4-4"/></symbol>
        <symbol id="nav-leave" viewBox="0 0 24 24"><path d="M12 3v9l6 3"/><circle cx="12" cy="12" r="9"/></symbol>
        <symbol id="nav-payroll" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-13 5h4"/></symbol>
        <symbol id="nav-logout" viewBox="0 0 24 24"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></symbol>
        <symbol id="nav-switch" viewBox="0 0 24 24"><path d="M16 3h5v5m0-5-7 7M8 21H3v-5m0 5 7-7"/><path d="M14 14h7v7M21 14l-7 7M3 3l7 7"/></symbol>
      </svg>
      <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <?php if (canAccess('dashboard')): ?>
    <a href="?page=dashboard" class="<?php echo (!isset($_GET['page']) || $_GET['page'] == 'dashboard') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-dashboard"></use></svg>Dashboard</a>
    <?php endif; ?>
    
    <?php if (canAccess('cart')): ?>
    <a href="?page=cart" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'cart') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-cart"></use></svg>Cart</a>
    <?php endif; ?>
    
    <div class="nav-label" style="margin-top:1rem;">Management</div>
    
    <?php if (canAccess('returns') || canAccess('returns_create')): ?>
    <a href="?page=returns" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'returns') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-returns"></use></svg>Returns</a>
    <?php endif; ?>

    <?php
    $sidebarPage = $_GET['page'] ?? 'dashboard';
    $sidebarInventoryPages = ['products', 'stock', 'purchases', 'suppliers', 'inventory_reports', 'categories', 'archive'];
    $sidebarHrPages = ['hr', 'job_postings'];
    if (!isAdmin() && hasRole('hr')) {
        $sidebarHrPages[] = 'users';
    }
    $sidebarFinancePages = ['finance_dashboard', 'finance_reports'];
    ?>
    <?php if (canAccess('procurement')): ?>
    <details class="sidebar-group" <?php echo $sidebarPage === 'procurement' ? 'open' : ''; ?>>
        <summary class="<?php echo $sidebarPage === 'procurement' ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-purchases"></use></svg><span>Procurement</span><span class="sidebar-chevron"></span></summary>
        <div class="sidebar-submenu">
            <a href="?page=procurement&amp;tab=requisitions" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? 'requisitions') === 'requisitions' ? 'active' : ''; ?>">Requisition &amp; Budget</a>
            <a href="?page=procurement&amp;tab=rfqs" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'rfqs' ? 'active' : ''; ?>">Sourcing / RFQ</a>
            <a href="?page=procurement&amp;tab=analysis" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'analysis' ? 'active' : ''; ?>">Supplier Selection</a>
            <a href="?page=procurement&amp;tab=orders" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'orders' ? 'active' : ''; ?>">Purchase Order</a>
            <a href="?page=procurement&amp;tab=delivery" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'delivery' ? 'active' : ''; ?>">Delivery &amp; Receiving</a>
            <a href="?page=procurement&amp;tab=invoices" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'invoices' ? 'active' : ''; ?>">Invoice Match</a>
            <a href="?page=procurement&amp;tab=payments" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'payments' ? 'active' : ''; ?>">Payment</a>
            <a href="?page=procurement&amp;tab=ratings" class="<?php echo $sidebarPage === 'procurement' && ($_GET['tab'] ?? '') === 'ratings' ? 'active' : ''; ?>">Close &amp; Review</a>
        </div>
    </details>
    <?php endif; ?>

    <?php if (canAccess('products') || canAccess('stock') || canAccess('purchases') || canAccess('suppliers') || canAccess('inventory_reports') || canAccess('categories') || canAccess('archive')): ?>
    <details class="sidebar-group" <?php echo in_array($sidebarPage, $sidebarInventoryPages, true) ? 'open' : ''; ?>>
        <summary class="<?php echo in_array($sidebarPage, $sidebarInventoryPages, true) ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-stock"></use></svg><span>Inventory</span><span class="sidebar-chevron"></span></summary>
        <div class="sidebar-submenu">
            <?php if (canAccess('products')): ?><a href="?page=products" class="<?php echo $sidebarPage === 'products' ? 'active' : ''; ?>">Products</a><?php endif; ?>
            <?php if (canAccess('stock')): ?><a href="?page=stock" class="<?php echo $sidebarPage === 'stock' ? 'active' : ''; ?>">Stock</a><?php endif; ?>
            <?php if (canAccess('purchases')): ?><a href="?page=purchases" class="<?php echo $sidebarPage === 'purchases' ? 'active' : ''; ?>">Purchases</a><?php endif; ?>
            <?php if (canAccess('suppliers')): ?><a href="?page=suppliers" class="<?php echo $sidebarPage === 'suppliers' ? 'active' : ''; ?>">Suppliers</a><?php endif; ?>
            <?php if (canAccess('inventory_reports')): ?><a href="?page=inventory_reports" class="<?php echo $sidebarPage === 'inventory_reports' ? 'active' : ''; ?>">Inventory Reports</a><?php endif; ?>
            <?php if (canAccess('categories')): ?><a href="?page=categories" class="<?php echo $sidebarPage === 'categories' ? 'active' : ''; ?>">Categories</a><?php endif; ?>
            <?php if (canAccess('archive')): ?><a href="?page=archive" class="<?php echo $sidebarPage === 'archive' ? 'active' : ''; ?>">Archive</a><?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if (canAccess('hr')): ?>
    <details class="sidebar-group" <?php echo in_array($sidebarPage, $sidebarHrPages, true) ? 'open' : ''; ?>>
        <summary class="<?php echo in_array($sidebarPage, $sidebarHrPages, true) ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-people"></use></svg><span>HR</span><span class="sidebar-chevron"></span></summary>
        <div class="sidebar-submenu">
            <a href="?page=hr" class="<?php echo $sidebarPage === 'hr' && empty($_GET['open']) ? 'active' : ''; ?>">HR Dashboard</a>
            <a href="?page=hr&amp;open=add_employee">Add Employee</a>
            <a href="?page=hr&amp;open=attendance">Take Attendance</a>
            <a href="?page=hr&amp;open=leave">Leave Request</a>
            <a href="?page=hr#payroll">Payroll Overview</a>
            <a href="?page=hr&amp;open=stats">Employee Stats</a>
            <?php if (canAccess('users') && !isAdmin()): ?><a href="?page=users&amp;add_role=1" class="<?php echo $sidebarPage === 'users' && isset($_GET['add_role']) ? 'active' : ''; ?>">Add Role</a><a href="?page=users" class="<?php echo $sidebarPage === 'users' && !isset($_GET['manage_roles']) ? 'active' : ''; ?>">Edit User Roles</a><?php endif; ?>
            <?php if (canAccess('hr')): ?><a href="?page=job_postings" class="<?php echo $sidebarPage === 'job_postings' ? 'active' : ''; ?>">Job Postings</a><?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if (canAccess('finance_dashboard') || canAccess('finance_reports')): ?>
    <?php $sidebarFinanceOpen = in_array($sidebarPage, $sidebarFinancePages, true); ?>
    <details class="sidebar-group" <?php echo $sidebarFinanceOpen ? 'open' : ''; ?>>
        <summary class="<?php echo in_array($sidebarPage, $sidebarFinancePages, true) ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-finance"></use></svg><span>Finance</span><span class="sidebar-chevron"></span></summary>
        <div class="sidebar-submenu">
            <?php if (canAccess('finance_dashboard')): ?><a href="?page=finance_dashboard" class="<?php echo $sidebarPage === 'finance_dashboard' ? 'active' : ''; ?>">Finance Dashboard</a><?php endif; ?>
            <?php if (canAccess('finance_reports')): ?><a href="?page=finance_reports" class="<?php echo $sidebarPage === 'finance_reports' ? 'active' : ''; ?>">Finance Reports</a><?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if (canAccess('tokenization')): ?>
    <a href="?page=tokenization" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'tokenization') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-finance"></use></svg>Card Tokens</a>
    <?php endif; ?>

    <?php if (canAccess('customers')): ?>
    <a href="?page=customers" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'customers') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-customers"></use></svg>Customers</a>
    <?php endif; ?>
    
    <?php if (canAccess('sales')): ?>
    <a href="?page=sales" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'sales') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-sales"></use></svg>Sales</a>
    <?php endif; ?>
    
    <?php if (canAccess('reports') || canAccess('customer_reports') || canAccess('finance_reports')): ?>
    <div class="nav-label" style="margin-top:1rem;">Analytics</div>
    <?php endif; ?>
    
    <?php if (canAccess('reports')): ?>
    <a href="?page=reports" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'reports') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-reports"></use></svg>Reports</a>
    <?php endif; ?>
    
    <?php if (canAccess('customer_reports')): ?>
    <a href="?page=customer_reports" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'customer_reports') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-customers"></use></svg>Customer Reports</a>
    <?php endif; ?>
    
    <?php if (canAccess('users') || canAccess('activity') || canAccess('settings')): ?>
    <div class="nav-label" style="margin-top:1rem;">System</div>
    <?php endif; ?>
    
    <?php if (isAdmin()): ?>
    <a href="?page=users&manage_roles=1" class="<?php echo (isset($_GET['page'], $_GET['manage_roles']) && $_GET['page'] == 'users' && $_GET['manage_roles'] == '1') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-users"></use></svg>Manage Roles</a>
    <?php endif; ?>
    
    <?php if (canAccess('activity')): ?>
    <a href="?page=activity" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'activity') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-activity"></use></svg>Activity Log</a>
    <?php endif; ?>
    
    <?php if (canAccess('settings')): ?>
    <a href="?page=settings" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'settings') ? 'active' : ''; ?>"><svg class="nav-icon"><use href="#nav-settings"></use></svg>Settings</a>
    <?php endif; ?>
</nav>
        
        <!-- 🔔 Inventory Notifications Link -->
        <?php 
        if (hasRole('inventory')):
            $unreadCount = 0;
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM inventory_notifications n
                    WHERE NOT EXISTS (
                        SELECT 1 FROM notification_reads r 
                        WHERE r.notification_id = n.id AND r.user_id = ?
                    )
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $unreadCount = (int)$stmt->fetch()['count'];
            } catch (Exception $e) {}
        ?>
        <a href="?page=notifications" class="sidebar-notifications" style="<?php echo $unreadCount > 0 ? 'background:rgba(239,68,68,0.15);color:#F87171;' : ''; ?>">
            <svg class="nav-icon"><use href="#nav-notifications"></use></svg>
            Notifications
            <?php if ($unreadCount > 0): ?>
            <span style="margin-left:auto;background:#EF4444;color:white;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar">👤</div>
                <div>
                    <div class="name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="role"><span class="badge <?php echo getRoleBadge($_SESSION['role']); ?>"><?php echo htmlspecialchars(getRoleLabel($_SESSION['role'])); ?></span></div>
                </div>
            </div>
            <a href="#" class="logout-btn" onclick="confirmLogout(event)"><svg class="nav-icon"><use href="#nav-logout"></use></svg>Logout</a>
            <a href="#" class="switch-user-btn" onclick="switchUser()"><svg class="nav-icon"><use href="#nav-switch"></use></svg>Switch User</a>
        </div>
    </div>

    <div class="modal logout-modal" id="logoutModal">
    <div class="modal-content">
        
        <!-- Animated icon -->
        <div class="logout-icon">
            <div class="logout-icon-circle">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
        </div>
        
        <!-- Title -->
        <h2 class="logout-title">Confirm Logout</h2>
        
        <!-- Subtitle -->
        <p class="logout-subtitle">Are you sure you want to logout? You will need to sign in again to access the employee portal.</p>
        
        <!-- Action buttons -->
        <div class="logout-actions">
            <button class="logout-btn-cancel" onclick="closeModal('logoutModal')">
                Cancel
            </button>
            <button class="logout-btn-confirm" onclick="proceedLogout()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Yes, Logout
            </button>
        </div>
        
    </div>
</div>

    <!-- ============================================ -->
<!-- SWITCH USER MODAL - CUSTOM DESIGN -->
<!-- ============================================ -->
        <div class="modal switch-user-modal" id="switchUserModal">
        <div class="modal-content">
            
            <!-- Animated icon -->
            <div class="switch-icon">
                <div class="switch-icon-circle">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <polyline points="17 11 19 13 23 9"></polyline>
                    </svg>
                </div>
            </div>
            
            <h2 class="switch-title">Switch Account</h2>
            <p class="switch-subtitle">Choose a saved account to continue.</p>

            <div class="saved-switch-list">
                <?php $switchAccounts = getSavedLoginAccounts(); ?>
                <?php foreach ($switchAccounts as $savedAccount): ?>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="switch_saved_account" value="<?php echo (int)$savedAccount['id']; ?>" class="saved-switch-account">
                        <span class="saved-switch-avatar"><?php echo htmlspecialchars(strtoupper(substr($savedAccount['full_name'] ?: $savedAccount['username'], 0, 1))); ?></span>
                        <span class="saved-switch-info">
                            <strong><?php echo htmlspecialchars($savedAccount['full_name'] ?: $savedAccount['username']); ?></strong>
                            <span><?php echo htmlspecialchars($savedAccount['username']); ?> · <?php echo htmlspecialchars(getRoleLabel($savedAccount['role'])); ?></span>
                        </span>
                    </button>
                </form>
                <?php endforeach; ?>
                <?php if (empty($switchAccounts)): ?>
                <div style="padding:14px;border:1px solid #E2E8F0;border-radius:11px;color:#70798B;font-size:13px;">
                    No saved accounts on this device. Use “Remember Me” when signing in to add one.
                </div>
                <?php endif; ?>
            </div>

            <div class="saved-switch-actions">
                <button type="button" class="switch-btn-cancel" onclick="closeModal('switchUserModal')">Cancel</button>
                <button type="button" class="switch-btn-confirm" id="switchSignInAnother" onclick="proceedSwitchUser()">Sign in another account</button>
            </div>
            
        </div>
</div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
        
        function confirmLogout(e) {
            e.preventDefault();
            document.getElementById('logoutModal').classList.add('show');
        }
        
        function proceedLogout() {
            window.location.href = '?logout=1';
        }
        
        // Switch user - opens custom modal instead of browser alert
        function switchUser() {
            document.getElementById('switchUserModal').classList.add('show');
        }

        // Confirm switch user - performs the actual logout
        function proceedSwitchUser() {
            // Show loading state on button
            var btn = document.getElementById('switchSignInAnother');
            if (btn) {
                btn.textContent = 'Signing out...';
                btn.disabled = true;
                btn.style.opacity = '0.8';
            }
            
            // Redirect after short delay
            setTimeout(function() {
                window.location.href = '?logout=1';
            }, 400);
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) { closeModal('logoutModal'); }
        });
    </script>

    <div class="main-content">
        <?php
        require __DIR__ . '/pages.php';
        ?>
    </div>
<?php endif; ?>

        <script>
                // ============================================
                // TOAST NOTIFICATION SYSTEM - GLOBAL
                // ============================================

                window.showToast = function(type, title, message, duration) {
                    if (!duration) duration = 4000;
                    
                    var container = document.getElementById('toastContainer');
                    if (!container) {
                        container = document.createElement('div');
                        container.id = 'toastContainer';
                        container.style.cssText = 'position:fixed;top:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:12px;max-width:420px;width:calc(100% - 48px);pointer-events:none;';
                        document.body.appendChild(container);
                    }
                    
                    var icons = {
                        success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
                        error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
                        warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                        info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
                    };
                    
                    var colors = {
                        success: { border: '#10B981', bg: '#ECFDF5', color: '#10B981' },
                        error: { border: '#EF4444', bg: '#FEF2F2', color: '#EF4444' },
                        warning: { border: '#F59E0B', bg: '#FFFBEB', color: '#F59E0B' },
                        info: { border: '#6366F1', bg: '#EEF2FF', color: '#6366F1' }
                    };
                    
                    var c = colors[type] || colors.info;
                    
                    var toast = document.createElement('div');
                    toast.style.cssText = 'display:flex;align-items:flex-start;gap:14px;padding:16px 18px;background:#FFFFFF;border-radius:14px;box-shadow:0 20px 50px rgba(30,27,75,0.15),0 8px 20px rgba(30,27,75,0.1),0 0 0 1px rgba(30,27,75,0.05);border-left:4px solid ' + c.border + ';pointer-events:auto;animation:toastSlideIn 0.4s cubic-bezier(0.4,0,0.2,1);font-family:Inter,-apple-system,sans-serif;';
                    toast.innerHTML = 
                        '<div style="width:40px;height:40px;border-radius:10px;background:' + c.bg + ';color:' + c.color + ';display:flex;align-items:center;justify-content:center;flex-shrink:0;">' + (icons[type] || icons.info) + '</div>' +
                        '<div style="flex:1;min-width:0;">' +
                            '<div style="font-size:14px;font-weight:700;color:#111827;letter-spacing:-0.01em;margin-bottom:3px;line-height:1.3;">' + title + '</div>' +
                            (message ? '<div style="font-size:13px;color:#6B7280;line-height:1.5;font-weight:500;word-wrap:break-word;">' + message + '</div>' : '') +
                        '</div>' +
                        '<button onclick="this.parentElement.remove()" style="width:24px;height:24px;border-radius:6px;background:transparent;border:none;color:#9CA3AF;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:300;line-height:1;padding:0;flex-shrink:0;">&times;</button>';
                    
                    container.appendChild(toast);
                    
                    var dismissTimer = setTimeout(function() {
                        if (toast.parentElement) {
                            toast.style.opacity = '0';
                            toast.style.transform = 'translateX(100%)';
                            toast.style.transition = 'all 0.3s';
                            setTimeout(function() { if (toast.parentElement) toast.remove(); }, 300);
                        }
                    }, duration);
                    
                    toast.addEventListener('mouseenter', function() { clearTimeout(dismissTimer); });
                    toast.addEventListener('mouseleave', function() {
                        dismissTimer = setTimeout(function() {
                            if (toast.parentElement) {
                                toast.style.opacity = '0';
                                toast.style.transform = 'translateX(100%)';
                                toast.style.transition = 'all 0.3s';
                                setTimeout(function() { if (toast.parentElement) toast.remove(); }, 300);
                            }
                        }, 1500);
                    });
                };

                window.dismissToast = function(toast) {
                    if (!toast) return;
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    toast.style.transition = 'all 0.3s';
                    setTimeout(function() { if (toast.parentElement) toast.remove(); }, 300);
                };

                // Inject animation
                (function() {
                    var style = document.createElement('style');
                    style.textContent = '@keyframes toastSlideIn{from{opacity:0;transform:translateX(100%) scale(0.9)}to{opacity:1;transform:translateX(0) scale(1)}}';
                    document.head.appendChild(style);
                })();

                // Override window.alert
                window.alert = function(message) {
                    if (!message) return;
                    
                    var msg = String(message);
                    var type = 'info';
                    var title = 'Notice';
                    
                    if (/error|❌|failed|cannot|invalid|expired|unauthorized/i.test(msg)) {
                        type = 'error';
                        title = 'Error';
                    } else if (/warning|⚠️|⏰|already|not enough|low stock/i.test(msg)) {
                        type = 'warning';
                        title = 'Warning';
                    } else if (/success|✅|saved|added|completed|approved|deleted|updated/i.test(msg)) {
                        type = 'success';
                        title = 'Success';
                    }
                    
                    var clean = msg.replace(/^[✅❌⚠️⏰🔄💰📋📦👤🎉🚪🔑]+\s*/g, '').replace(/^error:\s*/i, '').replace(/^success:\s*/i, '').replace(/^warning:\s*/i, '').trim();
                    
                    window.showToast(type, title, clean);
                };

                // ============================================
                // REPLACE NATIVE CONFIRM WITH CUSTOM MODAL
                // ============================================

                // Override window.confirm to auto-show a custom modal
                // Note: This changes confirm() to non-blocking, so we need to handle it differently
                // For existing code, we'll create a helper that existing functions can use
                window.customConfirm = function(message, callback, variant) {
                    // Auto-detect variant from message
                    var v = variant || 'info';
                    var lower = String(message).toLowerCase();
                    if (/delete|remove|permanently/i.test(message)) v = 'danger';
                    else if (/archive/i.test(message)) v = 'warning';
                    else if (/restore|approve/i.test(message)) v = 'success';
                    
                    // Auto-detect title
                    var title = 'Confirm Action';
                    if (/delete|remove/i.test(message)) title = 'Delete Item?';
                    else if (/archive/i.test(message)) title = 'Archive Item?';
                    else if (/restore/i.test(message)) title = 'Restore Item?';
                    else if (/logout|switch/i.test(message)) title = 'Confirm Action';
                    
                    // Auto-detect button text
                    var confirmText = 'Confirm';
                    if (/delete|remove/i.test(message)) confirmText = 'Delete';
                    else if (/archive/i.test(message)) confirmText = 'Archive';
                    else if (/restore/i.test(message)) confirmText = 'Restore';
                    
                    showConfirm({
                        title: title,
                        message: message,
                        confirmText: confirmText,
                        variant: v,
                        onConfirm: callback
                    });
                };

                console.log('✅ Toast system loaded successfully!');

                // ============================================
                // Confirmation modal
                // ============================================

                var _confirmCallback = null;

                window.showConfirm = function(options) {
                    options = options || {};
                    
                    var title = options.title || 'Are you sure?';
                    var message = options.message || 'This action cannot be undone.';
                    var confirmText = options.confirmText || 'Confirm';
                    var variant = options.variant || 'info';
                    
                    var iconMap = {
                        'danger': '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>',
                        'warning': '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                        'success': '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
                        'info': '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
                    };
                    
                    var modal = document.getElementById('confirmModal');
                    if (!modal) {
                        // Fallback to native confirm if modal doesn't exist
                        if (window.confirm(message)) {
                            if (typeof options.onConfirm === 'function') options.onConfirm();
                        }
                        return;
                    }
                    
                    document.getElementById('confirmTitle').textContent = title;
                    document.getElementById('confirmMessage').textContent = message;
                    document.getElementById('confirmOkText').textContent = confirmText;
                    document.getElementById('confirmIcon').innerHTML = iconMap[variant] || iconMap.info;
                    
                    document.getElementById('confirmIcon').className = 'confirm-icon confirm-' + variant;
                    document.getElementById('confirmOkBtn').className = 'confirm-btn-ok confirm-btn-' + variant;
                    
                    _confirmCallback = options.onConfirm || null;
                    modal.classList.add('show');
                };

                window.closeConfirm = function() {
                    var modal = document.getElementById('confirmModal');
                    if (modal) modal.classList.remove('show');
                    _confirmCallback = null;
                };

                window.confirmProceed = function() {
                    var cb = _confirmCallback;
                    closeConfirm();
                    if (typeof cb === 'function') {
                        cb();
                    }
                };

                // Auto-detect variant helper
                window.customConfirm = function(message, callback, variant) {
                    var v = variant || 'info';
                    if (/delete|remove|permanently/i.test(message)) v = 'danger';
                    else if (/archive/i.test(message)) v = 'warning';
                    else if (/restore|approve/i.test(message)) v = 'success';
                    
                    var title = 'Confirm Action';
                    if (/delete|remove/i.test(message)) title = 'Delete Item?';
                    else if (/archive/i.test(message)) title = 'Archive Item?';
                    else if (/restore/i.test(message)) title = 'Restore Item?';
                    
                    var confirmText = 'Confirm';
                    if (/delete|remove/i.test(message)) confirmText = 'Delete';
                    else if (/archive/i.test(message)) confirmText = 'Archive';
                    else if (/restore/i.test(message)) confirmText = 'Restore';
                    
                    showConfirm({
                        title: title,
                        message: message,
                        confirmText: confirmText,
                        variant: v,
                        onConfirm: callback
                    });
                };

                // Attach outside click to confirm modal (if it exists)
                (function() {
                    var modal = document.getElementById('confirmModal');
                    if (modal) {
                        modal.addEventListener('click', function(e) {
                            if (e.target === this) {
                                closeConfirm();
                            }
                        });
                    }
                })();

                console.log('✅ Custom confirm system loaded');

        </script>

<!-- ============================================ -->
<!-- UNIVERSAL CONFIRMATION MODAL -->
<!-- ============================================ -->
<div class="modal" id="confirmModal">
    <div class="modal-content confirm-modal-content">
        
        <div class="confirm-icon-wrap" id="confirmIconWrap">
            <div class="confirm-icon" id="confirmIcon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
        </div>
        
        <h2 class="confirm-title" id="confirmTitle">Are you sure?</h2>
        <p class="confirm-message" id="confirmMessage">This action cannot be undone.</p>
        
        <div class="confirm-actions">
            <button type="button" class="confirm-btn-cancel" onclick="closeConfirm()">
                Cancel
            </button>
            <button type="button" class="confirm-btn-ok" id="confirmOkBtn" onclick="confirmProceed()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span id="confirmOkText">Confirm</span>
            </button>
        </div>
        
    </div>
</div>

<script>
// ============================================
// SIDEBAR SCROLL POSITION PERSISTENCE
// Keeps sidebar scroll position when clicking links
// ============================================
(function() {
    var sidebar = document.querySelector('.sidebar-nav');
    if (!sidebar) return;
    
    // Restore saved scroll position on page load
    var savedScroll = sessionStorage.getItem('sidebarScroll');
    if (savedScroll !== null) {
        sidebar.scrollTop = parseInt(savedScroll, 10);
    }
    
    // Auto-scroll the active link into view on load
    var activeLink = sidebar.querySelector('a.active');
    if (activeLink) {
        activeLink.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    }
    
    // Save scroll position when leaving the page
    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('sidebarScroll', sidebar.scrollTop);
    });
    
    // Also save on link click (in case beforeunload doesn't fire in some browsers)
    sidebar.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            sessionStorage.setItem('sidebarScroll', sidebar.scrollTop);
        });
    });
})();
</script>

</body>
</html>