const fs = require('fs');
let env = fs.readFileSync('backend/.env', 'utf8');

env = env.replace(/MAIL_MAILER=.*/g, 'MAIL_MAILER=smtp');
env = env.replace(/MAIL_HOST=.*/g, 'MAIL_HOST=smtp.gmail.com');
env = env.replace(/MAIL_PORT=.*/g, 'MAIL_PORT=587');
env = env.replace(/MAIL_USERNAME=.*/g, 'MAIL_USERNAME=wmutunga003@gmail.com');
env = env.replace(/MAIL_PASSWORD=.*/g, 'MAIL_PASSWORD="tpha fjny idzj dkxr"');
env = env.replace(/MAIL_ENCRYPTION=.*/g, 'MAIL_ENCRYPTION=tls');
env = env.replace(/MAIL_FROM_ADDRESS=.*/g, 'MAIL_FROM_ADDRESS=wmutunga003@gmail.com');
env = env.replace(/MAIL_FROM_NAME=.*/g, 'MAIL_FROM_NAME="Casamoko SMS"');
env = env.replace(/FRONTEND_URL=.*/g, 'FRONTEND_URL=http://localhost:5173');

fs.writeFileSync('backend/.env', env);
console.log('Updated backend/.env');
