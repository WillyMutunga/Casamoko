const https = require('https');

const data = JSON.stringify({
  username: "Casamoko",
  password: "SZBB3"
});

const options = {
  hostname: 'dsvc2.safaricom.com',
  port: 9480,
  path: '/api/auth/login',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Content-Length': data.length,
    'X-Requested-With': 'XMLHttpRequest',
    'X-Country': 'KEN'
  }
};

const req = https.request(options, res => {
  console.log(`statusCode: ${res.statusCode}`);
  let body = '';
  res.on('data', d => {
    body += d;
  });
  res.on('end', () => {
    console.log(body);
  });
});

req.on('error', error => {
  console.error(error);
});

req.write(data);
req.end();
