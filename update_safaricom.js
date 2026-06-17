const fs = require('fs');
let env = fs.readFileSync('backend/.env', 'utf8');

// Ensure correct complete URLs
env = env.replace(/SAFARICOM_SDP_AUTH_URL=.*/g, 'SAFARICOM_SDP_AUTH_URL=https://dsvc.safaricom.com:8481/api/auth/login');
env = env.replace(/SAFARICOM_SDP_SEND_URL=.*/g, 'SAFARICOM_SDP_SEND_URL=https://dsvc.safaricom.com:8481/api/public/CMS/bulksms');

if (!env.includes('SAFARICOM_SDP_BALANCE_URL=')) {
    env += '\nSAFARICOM_SDP_BALANCE_URL=https://dsvc.safaricom.com:8481/api/public/CMS/accountBalance\n';
} else {
    env = env.replace(/SAFARICOM_SDP_BALANCE_URL=.*/g, 'SAFARICOM_SDP_BALANCE_URL=https://dsvc.safaricom.com:8481/api/public/CMS/accountBalance');
}

fs.writeFileSync('backend/.env', env);
console.log('Fixed Safaricom URLs in .env');
