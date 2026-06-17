const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// Replace the span blocks containing ACTIVE SIMULATION or SIMULATION
const regex = /<span className="px-\d py-[\d.]+ bg-amber-500\/10 text-amber-400 border border-amber-500\/20 text-[^>]+>[\s\S]*?SIMULATION:[\s\S]*?<\/span>/g;

code = code.replace(regex, '');

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Done!');
