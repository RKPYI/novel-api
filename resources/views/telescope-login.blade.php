<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Telescope Access - Admin Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .telescope-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .success-message.show {
            display: block;
        }

        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
            display: none;
        }

        .loader.show {
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 12px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 13px;
            color: #1976d2;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
            color: #999;
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider span {
            padding: 0 16px;
        }

        .btn-google {
            width: 100%;
            padding: 14px;
            background: white;
            color: #333;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-google:hover {
            border-color: #4285f4;
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .btn-google:active {
            transform: translateY(0);
        }

        .google-icon {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="telescope-icon">🔭</div>
            <h1>Telescope Access</h1>
            <p>Admin authentication required</p>
        </div>

        <div id="errorMessage" class="error-message"></div>
        <div id="successMessage" class="success-message"></div>

        <!-- Google Login Button -->
        <button type="button" class="btn-google" id="googleLoginBtn">
            <svg class="google-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Continue with Google
        </button>

        <div class="divider">
            <span>OR</span>
        </div>

        <!-- Email/Password Login Form -->
        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                       placeholder="admin@example.com">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                Login to Telescope
                <span class="loader" id="loader"></span>
            </button>
        </form>

        <div class="info-box">
            <strong>Note:</strong> Only administrators can access Telescope in production.
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const googleLoginBtn = document.getElementById('googleLoginBtn');
        const loader = document.getElementById('loader');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');

        // Handle Google Login
        googleLoginBtn.addEventListener('click', async () => {
            try {
                // Get Google OAuth URL with telescope state
                const response = await fetch('/api/auth/google?telescope=true', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.url) {
                    // Redirect to Google OAuth
                    window.location.href = data.url;
                } else {
                    errorMessage.textContent = 'Google login is not configured';
                    errorMessage.classList.add('show');
                }
            } catch (error) {
                errorMessage.textContent = 'Connection error. Please try again.';
                errorMessage.classList.add('show');
            }
        });

        // Check if returning from Google OAuth (for Telescope login-callback)
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');

            if (error) {
                const errorMessages = {
                    'authentication_failed': 'Google authentication failed. Please try again.',
                    'not_admin': 'Access denied. Only administrators can access Telescope.',
                    'user_not_found': 'User not found. Please contact support.',
                    'invalid_data': 'Invalid authentication data. Please try again.'
                };

                errorMessage.textContent = errorMessages[error] || 'An error occurred. Please try again.';
                errorMessage.classList.add('show');
            }
        });

        // Handle Email/Password Login
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous messages
            errorMessage.classList.remove('show');
            successMessage.classList.remove('show');

            // Get form data
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            // Disable button and show loader
            loginBtn.disabled = true;
            loader.classList.add('show');

            try {
                // First, get CSRF token
                await fetch('/sanctum/csrf-cookie');

                // Login request to Telescope login endpoint (creates session)
                const response = await fetch('/telescope/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ email, password }),
                    credentials: 'same-origin' // Important for session cookies
                });

                const data = await response.json();

                if (response.ok) {
                    successMessage.textContent = '✓ Login successful! Redirecting to Telescope...';
                    successMessage.classList.add('show');

                    // Redirect to Telescope after a short delay
                    setTimeout(() => {
                        window.location.href = data.redirect || '/telescope';
                    }, 1000);
                } else {
                    errorMessage.textContent = data.message || 'Invalid email or password';
                    errorMessage.classList.add('show');
                    loginBtn.disabled = false;
                    loader.classList.remove('show');
                }
            } catch (error) {
                errorMessage.textContent = 'Connection error. Please try again.';
                errorMessage.classList.add('show');
                loginBtn.disabled = false;
                loader.classList.remove('show');
            }
        });
    </script>
</body>
</html>
