const https = require('https');

// 1. Authenticate to the backend to get a token
const postData = JSON.stringify({
    email: 'wmutunga003@gmail.com',
    password: 'William#20'
});

const options = {
    hostname: 'casamoko.co.ke',
    port: 443,
    path: '/api/accounts/login',
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Content-Length': postData.length
    }
};

const req = https.request(options, (res) => {
    let data = '';
    res.on('data', (chunk) => data += chunk);
    res.on('end', () => {
        try {
            const parsed = JSON.parse(data);
            if (parsed.success && parsed.data.token) {
                console.log('Successfully logged in. Token:', parsed.data.token.substring(0, 10) + '...');
                checkBalance(parsed.data.token);
            } else {
                console.log('Login failed:', parsed);
            }
        } catch (e) {
            console.log('Error parsing login response:', data);
        }
    });
});

req.on('error', (e) => {
    console.error('Problem with login request:', e.message);
});

req.write(postData);
req.end();

function checkBalance(token) {
    const options = {
        hostname: 'casamoko.co.ke',
        port: 443,
        path: '/api/messaging/admin/routes/balance',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/json'
        }
    };

    const req = https.request(options, (res) => {
        let data = '';
        res.on('data', (chunk) => data += chunk);
        res.on('end', () => {
            console.log('Balance API HTTP Status:', res.statusCode);
            console.log('Balance API Response:', data);
        });
    });

    req.on('error', (e) => {
        console.error('Problem with balance request:', e.message);
    });

    req.end();
}
