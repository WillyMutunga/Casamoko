const login = async () => {
    try {
        console.log("Logging in...");
        const res = await fetch('https://casamoko.co.ke/api/auth/login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email: 'wmutunga003@gmail.com', password: 'William#20'})
        });
        const data = await res.json();
        const token = data.token;
        if (!token) throw new Error("No token: " + JSON.stringify(data));
        console.log("Logged in. Token:", token.substring(0, 15) + "...");
        
        console.log("Sending message...");
        const sendRes = await fetch('https://casamoko.co.ke/api/messaging/quick-send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                recipient: '0742765445',
                message: 'Test SC0011 error',
                sender_id: 'CASAMOKO'
            })
        });
        const sendData = await sendRes.json();
        console.log("Response:", sendData);
    } catch (e) {
        console.error("Error:", e.message);
    }
};

login();
