const fs = require('fs');

const styleFile = 'c:/xampp/htdocs/vrakeit/assets/css/style.css';
let styleContent = fs.readFileSync(styleFile, 'utf8');

// Update .auth-card to light mode
styleContent = styleContent.replace(
    /\.auth-card\s*\{[\s\S]*?\}/,
    `.auth-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 24px;
    padding: 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    border: 1px solid rgba(255, 255, 255, 0.8);
}`
);

// Update enforcer-access-btn hover to light mode
styleContent = styleContent.replace(
    /\.enforcer-access-btn:hover\s*\{[\s\S]*?\}/,
    `.enforcer-access-btn:hover {
  background: rgba(0, 0, 0, 0.04);
  border-color: rgba(0,0,0,0.1);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}`
);

fs.writeFileSync(styleFile, styleContent, 'utf8');
console.log('Updated style.css');


const phpFile = 'c:/xampp/htdocs/vrakeit/index.php';
let phpContent = fs.readFileSync(phpFile, 'utf8');

phpContent = phpContent.replace(
    /<body[^>]*>/,
    `<body style="background-color: #f8fafc;">`
);

phpContent = phpContent.replace(
    /<div class="auth-bg" style="background-image: url\('assets\/img\/background.png'\); background-size: cover; background-position: center; position: relative;">/,
    `<div class="auth-bg" style="background: radial-gradient(circle at 15% 50%, rgba(0, 126, 210, 0.08), transparent 25%), radial-gradient(circle at 85% 30%, rgba(233, 1, 1, 0.06), transparent 25%); background-color: #f8fafc; position: relative; min-height: 100vh; overflow: hidden;">`
);

phpContent = phpContent.replace(
    /<p class="text-white">Road Incident Reporting System<\/p>/,
    `<p style="color: rgba(0,0,0,0.6); font-weight: 500; font-size: 14px;">Road Incident Reporting System</p>`
);

phpContent = phpContent.replace(
    /style="font-size:13px;color: whitesmoke;text-decoration:none;"/g,
    `style="font-size:13px;color: rgba(0,0,0,0.6);text-decoration:none;transition: color 0.3s;" onmouseover="this.style.color='#007ED2'" onmouseout="this.style.color='rgba(0,0,0,0.6)'"`
);

phpContent = phpContent.replace(
    /<div class="divider-text" style="color: whitesmoke;">or<\/div>/,
    `<div class="divider-text" style="color: rgba(0,0,0,0.4); font-weight: 600;">or</div>`
);

// Merchant Login Button
phpContent = phpContent.replace(
    /<a href="merchant\/login.php"[^>]*>[\s\S]*?Merchant Login[\s\S]*?<\/a>/,
    `<a href="merchant/login.php" style="background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.1); color: #0f172a; border-radius: 30px; backdrop-filter: blur(10px); padding: 8px 16px; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05);" onmouseover="this.style.background='#ffffff'; this.style.borderColor='rgba(0,0,0,0.15)'; this.style.color='#007ED2'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='rgba(255,255,255,0.7)'; this.style.borderColor='rgba(0,0,0,0.1)'; this.style.color='#0f172a'; this.style.transform='translateY(0)';">
        <i class="bi bi-shop-window me-2"></i> Merchant Login
      </a>`
);

// btn-outline-vr Create Account button
phpContent = phpContent.replace(
    /<a href="register.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px;">/,
    `<a href="register.php" class="btn-outline-vr d-block text-center text-decoration-none mt-3" style="padding:13px; color: #007ED2; border-color: rgba(0, 126, 210, 0.3); background: rgba(0, 126, 210, 0.05);" onmouseover="this.style.background='rgba(0, 126, 210, 0.1)';" onmouseout="this.style.background='rgba(0, 126, 210, 0.05)';">`
);

// Enforcer Link styling at bottom
phpContent = phpContent.replace(
    /class="enforcer-icon">\s*<i class="bi bi-shield-lock-fill"><\/i>/,
    `class="enforcer-icon" style="background: rgba(233,1,1,0.1); color: #E90101;">\n          <i class="bi bi-shield-lock-fill"></i>`
);

phpContent = phpContent.replace(
    /<span class="enforcer-text">Enforcer Portal<\/span>\s*<span class="enforcer-arrow">/,
    `<span class="enforcer-text" style="color: #0f172a; font-weight:600;">Enforcer Portal</span>\n        <span class="enforcer-arrow" style="color: #0f172a;">`
);

fs.writeFileSync(phpFile, phpContent, 'utf8');
console.log('Updated index.php');
