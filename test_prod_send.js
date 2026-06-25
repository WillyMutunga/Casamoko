const https = require('https');

function makeRequest(options, postData) {
  return new Promise((resolve, reject) => {
    const req = https.request(options, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ statusCode: res.statusCode, data: data, headers: res.headers }));
    });
    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function testSend() {
  console.log('Logging in to production casamoko.co.ke...');
  const loginData = JSON.stringify({
    email: 'info@casamoko.co.ke',
    password: 'Casa7405'
  });

  try {
    const loginRes = await makeRequest({
      hostname: 'casamoko.co.ke',
      path: '/api/accounts/login',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(loginData)
      }
    }, loginData);

    console.log('Login Status:', loginRes.statusCode);
    const authData = JSON.parse(loginRes.data);
    
    if (!authData.access_token) {
      console.log('Failed to get access token:', authData);
      return;
    }

    const token = authData.access_token;
    console.log('Got token. Sending SMS to 254742765445...');

    const smsData = JSON.stringify({
      recipient: '254742765445',
      message: 'Test message from Casamoko Diagnostics',
      sender_id: 'CASAMOKO'
    });

    const sendRes = await makeRequest({
      hostname: 'casamoko.co.ke',
      path: '/api/quick-send',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Content-Length': Buffer.byteLength(smsData)
      }
    }, smsData);

    console.log('Send Status:', sendRes.statusCode);
    console.log('Send Response:', sendRes.data);

  } catch (err) {
    console.error('Error:', err);
  }
}

testSend();
