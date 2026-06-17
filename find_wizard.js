const fs = require('fs');
const lines = fs.readFileSync('frontend/src/App.tsx', 'utf8').split('\n');
let start = -1;
let end = -1;
let openDivs = 0;
let found = false;

for (let i = 0; i < lines.length; i++) {
  if (lines[i].includes('Bulk Campaign Wizard') && !found) {
    // The Bulk Campaign Wizard header is at lines[i]. We want the div that wraps it.
    start = i - 2; // Rough guess for the wrapper div
    found = true;
    
    // Count initial braces for this block
    openDivs = (lines[start].match(/<div/g) || []).length;
  } else if (found) {
    openDivs += (lines[i].match(/<div/g) || []).length;
    openDivs -= (lines[i].match(/<\/div>/g) || []).length;
    
    if (openDivs === 0) {
      end = i;
      break;
    }
  }
}

console.log("Start:", start);
console.log("End:", end);
