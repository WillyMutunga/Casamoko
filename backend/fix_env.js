const fs = require('fs');

const envPath = 'c:/Users/hp/Desktop/Casamoko/backend/.env';
const content = fs.readFileSync(envPath, 'utf8');

// Find the position of SAFARICOM_SDP_BALANCE_URL
const marker = 'SAFARICOM_SDP_BALANCE_URL=https://dsvc.safaricom.com:8481/api/public/CMS/accountBalance';
const markerPos = content.indexOf(marker);

if (markerPos !== -1) {
    const cleanContent = content.substring(0, markerPos + marker.length) + '\n\n' +
        'MPESA_CONSUMER_SECRET=A3wAK5k1N5ewCNq0Hbfb5GBoULnO2NZAjGLM5qrJWm0PMxrhD7r5dpQWsn5MZKpl\n' +
        'MPESA_CONSUMER_KEY=lftHbXWIqdL4T15Dje3bl2c16cniFUhgHgebH9GonVRhIIV0\n' +
        'MPESA_SHORTCODE=174379\n' +
        'MPESA_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919\n' +
        'MPESA_CALLBACK_URL=https://google.com/mpesa\n';

    fs.writeFileSync(envPath, cleanContent, 'utf8');
    console.log('Successfully fixed .env file.');
} else {
    console.log('Marker not found!');
}
