const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// Replacements
code = code.replace(/Internal Ledger Balance: \$\$\{/g, 'Internal Ledger Balance: Ksh ${');
code = code.replace(/Total Cost: \$\$\{/g, 'Total Cost: Ksh ${');
code = code.replace(/>\$([0-9]+\.[0-9]+)</g, '>Ksh $1<'); // >$184.20<
code = code.replace(/>\$\$\{/g, '>Ksh ${'); // >$${Number...}
code = code.replace(/Cost Per SMS \(\$\)/g, 'Cost Per SMS (Ksh)');
code = code.replace(/Adjustment Amount \(\$ USD\)/g, 'Adjustment Amount (Ksh)');
code = code.replace(/Seeding Balance \(\$\)/g, 'Seeding Balance (Ksh)');
code = code.replace(/Credit Limit \(\$\)/g, 'Credit Limit (Ksh)');
code = code.replace(/\+\$\$\{/g, '+Ksh ${'); // +$${Number
code = code.replace(/-\$\$\{/g, '-Ksh ${'); // -$${Math.abs
code = code.replace(/Threshold \(\$\)/g, 'Threshold (Ksh)');
code = code.replace(/below \$\$\{/g, 'below Ksh ${');
code = code.replace(/\$18\.4200/g, 'Ksh 18.4200');

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Replaced successfully');
