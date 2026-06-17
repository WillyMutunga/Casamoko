const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// Restore optOutList
code = code.replace('// optOutList removed', 'const [optOutList, setOptOutList] = useState<{ id: number; msisdn: string; hash: string }[]>([]);');

// Remove simPhone, simShortcode, simMessage completely
code = code.replace(/const \[simPhone, setSimPhone\] = useState\([^)]+\);\n/g, '');
code = code.replace(/const \[simShortcode, setSimShortcode\] = useState\([^)]+\);\n/g, '');
code = code.replace(/const \[simMessage, setSimMessage\] = useState\([^)]+\);\n/g, '');

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Fixed vars again');
